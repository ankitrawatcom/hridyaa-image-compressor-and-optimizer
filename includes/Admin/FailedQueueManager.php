<?php
/**
 * Failed Conversion Queue Manager & Retry Engine for NextGen Image Optimizer.
 *
 * Provides durable failure tracking, bounded FIFO queue storage,
 * state machine transitions, worker locking, and retry policies.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class FailedQueueManager {

    public const OPTION_KEY = 'nextgen_failed_queue';
    private const LOCK_KEY  = 'nextgen_retry_worker_lock';
    private const MAX_ITEMS = 250;
    public const MAX_RETRIES = 3;

    public const STATE_PENDING            = 'PENDING';
    public const STATE_PROCESSING         = 'PROCESSING';
    public const STATE_FAILED             = 'FAILED';
    public const STATE_RETRYING           = 'RETRYING';
    public const STATE_SUCCEEDED          = 'SUCCEEDED';
    public const STATE_PERMANENTLY_FAILED = 'PERMANENTLY_FAILED';

    /**
     * Get all active failure items in queue.
     *
     * @return array<int, array{attachment_id:int, format:string, file_path:string, failure_category:string, safe_message:string, state:string, retry_count:int, first_failed_at:int, last_attempt_at:int}>
     */
    public static function getFailedItems(): array {
        $items = get_option(self::OPTION_KEY, []);
        if (!is_array($items)) {
            return [];
        }
        return $items;
    }

    /**
     * Record a conversion failure.
     *
     * @param int $attachmentId
     * @param string $format 'webp' or 'avif'
     * @param string $category 'memory_limit_exhausted' | 'timeout_interrupted' | 'corrupt_source' | 'generic'
     * @param string $message Safe sanitized error description
     * @param string $filePath Relative file path if available
     * @return void
     */
    public static function recordFailure(int $attachmentId, string $format, string $category, string $message, string $filePath = ''): void {
        $format = strtolower($format);
        $items = self::getFailedItems();

        // Check if item already exists in queue
        $foundIndex = -1;
        foreach ($items as $idx => $item) {
            if ($item['attachment_id'] === $attachmentId && $item['format'] === $format) {
                $foundIndex = $idx;
                break;
            }
        }

        $now = time();
        $safeMsg = function_exists('sanitize_text_field') ? sanitize_text_field($message) : (function_exists('wp_strip_all_tags') ? wp_strip_all_tags($message) : (string) $message);
        $cleanPath = function_exists('sanitize_text_field') ? sanitize_text_field($filePath) : (function_exists('wp_strip_all_tags') ? wp_strip_all_tags($filePath) : (string) $filePath);

        // Sanitize any absolute root server paths
        $cleanPath = preg_replace('#^.*[\\\\/]wp-content[\\\\/]uploads[\\\\/]#i', '', $cleanPath);

        if ($foundIndex >= 0) {
            $existing = $items[$foundIndex];
            $retries = ($existing['retry_count'] ?? 0) + 1;
            $state = ($retries >= self::MAX_RETRIES || $category === 'corrupt_source')
                ? self::STATE_PERMANENTLY_FAILED
                : self::STATE_FAILED;

            $items[$foundIndex]['failure_category'] = $category;
            $items[$foundIndex]['safe_message']     = $safeMsg;
            $items[$foundIndex]['retry_count']      = $retries;
            $items[$foundIndex]['state']            = $state;
            $items[$foundIndex]['last_attempt_at']  = $now;
        } else {
            // New failure item
            $newItem = [
                'attachment_id'    => $attachmentId,
                'format'           => $format,
                'file_path'        => $cleanPath,
                'failure_category' => $category,
                'safe_message'     => $safeMsg,
                'state'            => ($category === 'corrupt_source') ? self::STATE_PERMANENTLY_FAILED : self::STATE_FAILED,
                'retry_count'      => 0,
                'first_failed_at'  => $now,
                'last_attempt_at'  => $now,
            ];

            // Maintain FIFO bound (Max 250 items)
            if (count($items) >= self::MAX_ITEMS) {
                array_shift($items);
            }
            $items[] = $newItem;
        }

        update_option(self::OPTION_KEY, $items);
    }

    /**
     * Mark an item as succeeded and remove from active failure queue.
     *
     * @param int $attachmentId
     * @param string $format
     * @return void
     */
    public static function markSucceeded(int $attachmentId, string $format): void {
        $format = strtolower($format);
        $items = self::getFailedItems();
        $filtered = [];

        foreach ($items as $item) {
            if (!($item['attachment_id'] === $attachmentId && $item['format'] === $format)) {
                $filtered[] = $item;
            }
        }

        update_option(self::OPTION_KEY, $filtered);
    }

    /**
     * Dismiss a specific failed item without retrying.
     *
     * @param int $attachmentId
     * @param string $format
     * @return bool
     */
    public static function dismissItem(int $attachmentId, string $format): bool {
        self::markSucceeded($attachmentId, $format);
        return true;
    }

    /**
     * Clear all failed items.
     *
     * @return void
     */
    public static function clearQueue(): void {
        delete_option(self::OPTION_KEY);
    }

    /**
     * Acquire concurrency lock for retry worker.
     *
     * @return bool
     */
    public static function acquireLock(): bool {
        $lock = get_transient(self::LOCK_KEY);
        $now = time();

        if ($lock !== false) {
            // Check stale lock (older than 10 minutes)
            if (($now - (int) $lock) > 600) {
                self::releaseLock();
            } else {
                return false; // Currently locked by active worker
            }
        }

        set_transient(self::LOCK_KEY, $now, 300); // 5-minute lock
        return true;
    }

    /**
     * Release concurrency lock.
     *
     * @return void
     */
    public static function releaseLock(): void {
        delete_transient(self::LOCK_KEY);
    }
}

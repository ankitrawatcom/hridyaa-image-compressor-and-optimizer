<?php
/**
 * Scheduled Background WP-Cron Batch Optimizer Engine.
 *
 * Provides headless, asynchronous media library optimization that operates
 * continuously in the background without requiring an open admin browser tab.
 *
 * Implements monotonic cursor seeking, transient mutual exclusion locking (120s TTL),
 * time-budget enforcement (15s per tick), and crash/timeout self-recovery.
 *
 * @package NextGen\Batch
 */

namespace NextGen\Batch;

use NextGen\Core\Config;
use NextGen\Converter\ConverterManager;
use NextGen\Admin\QualityPresetManager;
use NextGen\Admin\StatsManager;
use NextGen\Admin\FailedQueueManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BackgroundBatchWorker {

    public const OPTION_KEY = 'nextgen_background_optimizer_state';
    public const LOCK_KEY   = 'nextgen_bg_worker_lock';
    public const CRON_HOOK  = 'nextgen_cron_batch_worker';
    public const LOCK_TTL   = 120; // 120 seconds mutual exclusion TTL

    public const STATE_IDLE      = 'IDLE';
    public const STATE_RUNNING   = 'RUNNING';
    public const STATE_PAUSED    = 'PAUSED';
    public const STATE_COMPLETED = 'COMPLETED';
    public const STATE_CANCELLED = 'CANCELLED';

    /**
     * Get the current background optimizer state.
     *
     * @return array
     */
    public static function getState(): array {
        $state = get_option(self::OPTION_KEY, []);
        if (!is_array($state) || empty($state)) {
            return self::getDefaultState();
        }

        return array_merge(self::getDefaultState(), $state);
    }

    /**
     * Get default initial state.
     */
    public static function getDefaultState(): array {
        return [
            'status'             => self::STATE_IDLE,
            'target_format'      => 'all',
            'quality_preset'     => QualityPresetManager::getActivePreset(),
            'total_attachments'  => 0,
            'processed_count'    => 0,
            'failed_count'       => 0,
            'last_attachment_id' => 0,
            'batch_size'         => 5,
            'time_budget_sec'    => 15,
            'started_at'         => null,
            'updated_at'         => null,
            'completed_at'       => null,
            'error_message'      => null,
        ];
    }

    /**
     * Start background library optimization.
     *
     * @param string $format Target format ('all', 'webp', 'avif').
     * @param string $preset Quality preset ('high', 'balanced', 'aggressive').
     * @return bool True if started, false if already running.
     */
    public static function start(string $format = 'all', string $preset = 'balanced'): bool {
        $currentState = self::getState();
        if ($currentState['status'] === self::STATE_RUNNING) {
            return false;
        }

        $total = self::countTotalEligibleAttachments();
        $cleanFormat = in_array($format, ['all', 'webp', 'avif'], true) ? $format : 'all';
        $cleanPreset = QualityPresetManager::normalizePreset($preset);

        $newState = [
            'status'             => self::STATE_RUNNING,
            'target_format'      => $cleanFormat,
            'quality_preset'     => $cleanPreset,
            'total_attachments'  => $total,
            'processed_count'    => 0,
            'failed_count'       => 0,
            'last_attachment_id' => 0,
            'batch_size'         => 5,
            'time_budget_sec'    => 15,
            'started_at'         => time(),
            'updated_at'         => time(),
            'completed_at'       => null,
            'error_message'      => null,
        ];

        update_option(self::OPTION_KEY, $newState, false);
        delete_transient(self::LOCK_KEY);

        // Schedule immediate next tick
        self::scheduleNextTick(0);

        return true;
    }

    /**
     * Pause the running background optimization.
     */
    public static function pause(): bool {
        $state = self::getState();
        if ($state['status'] !== self::STATE_RUNNING) {
            return false;
        }

        $state['status'] = self::STATE_PAUSED;
        $state['updated_at'] = time();
        update_option(self::OPTION_KEY, $state, false);

        delete_transient(self::LOCK_KEY);
        self::clearScheduledCron();

        return true;
    }

    /**
     * Resume a paused background optimization.
     */
    public static function resume(): bool {
        $state = self::getState();
        if ($state['status'] !== self::STATE_PAUSED) {
            return false;
        }

        $state['status'] = self::STATE_RUNNING;
        $state['updated_at'] = time();
        update_option(self::OPTION_KEY, $state, false);

        delete_transient(self::LOCK_KEY);
        self::scheduleNextTick(0);

        return true;
    }

    /**
     * Cancel background optimization.
     */
    public static function cancel(): bool {
        $state = self::getState();
        $state['status'] = self::STATE_CANCELLED;
        $state['updated_at'] = time();
        update_option(self::OPTION_KEY, $state, false);

        delete_transient(self::LOCK_KEY);
        self::clearScheduledCron();

        return true;
    }

    /**
     * Process one batch chunk during WP-Cron or CLI invocation.
     *
     * @param int $batchLimit        Maximum attachments per tick (default: 5).
     * @param int $timeBudgetSeconds Maximum execution time budget in seconds (default: 15).
     * @return array Batch outcome metrics.
     */
    public static function processCronTick(int $batchLimit = 5, int $timeBudgetSeconds = 15): array {
        $state = self::getState();
        if ($state['status'] !== self::STATE_RUNNING) {
            return ['status' => 'not_running', 'processed' => 0];
        }

        // Mutual exclusion lock (120s TTL)
        if (!self::acquireLock()) {
            return ['status' => 'locked', 'message' => 'Concurrent worker already active.'];
        }

        $startTime = microtime(true);
        $lastId = (int) ($state['last_attachment_id'] ?? 0);
        $batchLimit = max(1, min(20, $batchLimit));

        $attachmentIds = self::fetchNextAttachmentBatch($lastId, $batchLimit);

        if (empty($attachmentIds)) {
            // Queue completed
            $state['status'] = self::STATE_COMPLETED;
            $state['completed_at'] = time();
            $state['updated_at'] = time();
            update_option(self::OPTION_KEY, $state, false);
            self::releaseLock();
            self::clearScheduledCron();

            return ['status' => 'completed', 'processed' => 0];
        }

        $cfg = new Config();
        $converterManager = new ConverterManager($cfg);
        $attachmentHandler = new \NextGen\Image\AttachmentHandler($cfg, $converterManager);
        $processedInBatch = 0;
        $failedInBatch = 0;

        foreach ($attachmentIds as $attachmentId) {
            $attachmentId = (int) $attachmentId;
            if ($attachmentId <= 0) {
                continue;
            }

            // Check time budget before starting next image
            if ((microtime(true) - $startTime) >= $timeBudgetSeconds) {
                break;
            }

            $sourcePath = get_attached_file($attachmentId);
            if (empty($sourcePath) || !file_exists($sourcePath)) {
                // Record missing source failure and advance cursor
                FailedQueueManager::recordFailure($attachmentId, 'webp', 'source_missing', 'Original attachment file not found.');
                $failedInBatch++;
                $lastId = $attachmentId;
                continue;
            }

            // Perform standard conversion with quality preset
            $result = $attachmentHandler->processAttachment($attachmentId);
            if (isset($result['status']) && ($result['status'] === 'completed' || $result['status'] === 'skipped')) {
                $processedInBatch++;
            } else {
                $failedInBatch++;
            }

            $lastId = $attachmentId;
        }

        // Update state with updated cursor and counts
        $state['processed_count'] += $processedInBatch;
        $state['failed_count'] += $failedInBatch;
        $state['last_attachment_id'] = $lastId;
        $state['updated_at'] = time();
        update_option(self::OPTION_KEY, $state, false);

        self::releaseLock();

        // If more items exist, schedule next tick
        if ($lastId < self::getMaxAttachmentId()) {
            self::scheduleNextTick(60); // 60 seconds interval
        } else {
            $state['status'] = self::STATE_COMPLETED;
            $state['completed_at'] = time();
            update_option(self::OPTION_KEY, $state, false);
            self::clearScheduledCron();
        }

        return [
            'status'             => 'progress',
            'processed_in_batch' => $processedInBatch,
            'failed_in_batch'    => $failedInBatch,
            'last_attachment_id' => $lastId,
            'total_processed'    => $state['processed_count'],
            'elapsed_sec'        => round(microtime(true) - $startTime, 2),
        ];
    }

    /**
     * Acquire transient worker lock with heartbeat mutual exclusion.
     */
    public static function acquireLock(): bool {
        if (get_transient(self::LOCK_KEY)) {
            return false;
        }

        return (bool) set_transient(self::LOCK_KEY, time(), self::LOCK_TTL);
    }

    /**
     * Release transient worker lock.
     */
    public static function releaseLock(): void {
        delete_transient(self::LOCK_KEY);
    }

    /**
     * Fetch next batch using monotonic indexed cursor seek.
     *
     * @param int $lastId Monotonic cursor ID.
     * @param int $limit  Batch size limit.
     * @return int[]
     */
    public static function fetchNextAttachmentBatch(int $lastId, int $limit): array {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return [];
        }

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'
                   AND post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')
                   AND ID > %d
                 ORDER BY ID ASC
                 LIMIT %d",
                $lastId,
                $limit
            )
        );
        return array_map('intval', (array) $results);
    }

    /**
     * Count total eligible image attachments in library.
     */
    public static function countTotalEligibleAttachments(): int {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_mime_type IN (%s, %s, %s)",
                'attachment',
                'image/jpeg',
                'image/png',
                'image/gif'
            )
        );
    }

    /**
     * Get highest attachment ID.
     */
    public static function getMaxAttachmentId(): int {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(ID) FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_mime_type IN (%s, %s, %s)",
                'attachment',
                'image/jpeg',
                'image/png',
                'image/gif'
            )
        );
    }

    /**
     * Schedule next cron event tick safely.
     */
    public static function scheduleNextTick(int $delaySeconds = 60): void {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) {
            return;
        }

        $timestamp = time() + max(0, $delaySeconds);
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_single_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Clear all scheduled background cron hooks.
     */
    public static function clearScheduledCron(): void {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}

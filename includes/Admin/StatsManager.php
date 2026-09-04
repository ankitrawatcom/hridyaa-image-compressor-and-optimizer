<?php
/**
 * Optimization Savings Statistics Manager for NextGen Image Optimizer.
 *
 * Implements O(1) bounded aggregate storage in wp_options, per-attachment
 * conversion auditing, concurrency protection, and local reconciliation.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class StatsManager {

    public const OPTION_KEY = 'nextgen_savings_stats';
    public const META_KEY   = '_nextgen_conversion_data';
    private const LOCK_KEY  = 'nextgen_stats_update_lock';

    /**
     * Default statistics schema.
     *
     * @return array<string, int|float>
     */
    public static function getDefaultStats(): array {
        return [
            'total_originals_processed' => 0,
            'total_webp_generated'      => 0,
            'total_avif_generated'      => 0,
            'total_original_bytes'      => 0,
            'total_webp_bytes'          => 0,
            'total_avif_bytes'          => 0,
            'total_bytes_saved'         => 0,
            'percentage_saved'          => 0.0,
            'successful_conversions'    => 0,
            'failed_conversions'        => 0,
            'last_reconciled_at'        => time(),
        ];
    }

    /**
     * Get current aggregated statistics.
     *
     * @return array<string, int|float>
     */
    public static function getStats(): array {
        $stats = get_option(self::OPTION_KEY, null);
        if (!is_array($stats)) {
            return self::getDefaultStats();
        }
        return array_merge(self::getDefaultStats(), $stats);
    }

    /**
     * Record a successful image conversion.
     *
     * @param int $attachmentId WordPress attachment ID
     * @param string $format 'webp' or 'avif'
     * @param int $origBytes Original file size in bytes
     * @param int $optBytes Optimized derivative file size in bytes
     * @param string $driver Driver used ('gd' or 'imagick')
     * @param int $quality Quality setting used
     * @return void
     */
    public static function recordConversion(int $attachmentId, string $format, int $origBytes, int $optBytes, string $driver = 'gd', int $quality = 82): void {
        if ($origBytes <= 0 || $optBytes <= 0) {
            return;
        }

        // Enforce strict negative compression protection
        if ($optBytes >= $origBytes) {
            return; // No savings achieved, derivative discarded by negative compression guard
        }

        $savedBytes = $origBytes - $optBytes;
        $format = strtolower($format);

        // 1. Update Per-Attachment Metadata
        $meta = get_post_meta($attachmentId, self::META_KEY, true);
        if (!is_array($meta)) {
            $meta = ['original_size' => $origBytes];
        }

        // If this format was previously recorded for this attachment, subtract old savings first to prevent double counting
        $oldSaved = 0;
        $oldOpt = 0;
        $isFirstForAttachment = !isset($meta['webp']) && !isset($meta['avif']);

        if (isset($meta[$format]) && is_array($meta[$format])) {
            $oldOpt = (int) ($meta[$format]['size'] ?? 0);
            $oldSaved = (int) ($meta[$format]['saved'] ?? 0);
        }

        $meta['original_size'] = $origBytes;
        $meta[$format] = [
            'generated' => true,
            'size'      => $optBytes,
            'saved'     => $savedBytes,
            'timestamp' => time(),
            'driver'    => function_exists('sanitize_text_field') ? sanitize_text_field($driver) : (function_exists('wp_strip_all_tags') ? wp_strip_all_tags($driver) : (string) $driver),
            'quality'   => $quality,
        ];
        update_post_meta($attachmentId, self::META_KEY, $meta);

        // 2. Concurrency-Safe Global Counter Update
        self::atomicUpdate(function(array $stats) use ($format, $origBytes, $optBytes, $savedBytes, $oldOpt, $oldSaved, $isFirstForAttachment): array {
            if ($isFirstForAttachment) {
                $stats['total_originals_processed']++;
                $stats['total_original_bytes'] += $origBytes;
            }

            if ($oldSaved === 0) {
                if ($format === 'webp') {
                    $stats['total_webp_generated']++;
                } elseif ($format === 'avif') {
                    $stats['total_avif_generated']++;
                }
            }

            if ($format === 'webp') {
                $stats['total_webp_bytes'] = max(0, $stats['total_webp_bytes'] - $oldOpt + $optBytes);
            } elseif ($format === 'avif') {
                $stats['total_avif_bytes'] = max(0, $stats['total_avif_bytes'] - $oldOpt + $optBytes);
            }

            $stats['total_bytes_saved'] = max(0, $stats['total_bytes_saved'] - $oldSaved + $savedBytes);
            $stats['successful_conversions']++;

            if ($stats['total_original_bytes'] > 0) {
                $stats['percentage_saved'] = round(($stats['total_bytes_saved'] / $stats['total_original_bytes']) * 100, 1);
            } else {
                $stats['percentage_saved'] = 0.0;
            }

            return $stats;
        });
    }

    /**
     * Record an attachment deletion, decrementing savings to maintain exact counts.
     *
     * @param int $attachmentId
     * @return void
     */
    public static function recordDeletion(int $attachmentId): void {
        $meta = get_post_meta($attachmentId, self::META_KEY, true);
        if (!is_array($meta)) {
            return;
        }

        $origBytes = (int) ($meta['original_size'] ?? 0);
        $webpSaved = (int) ($meta['webp']['saved'] ?? 0);
        $webpSize  = (int) ($meta['webp']['size'] ?? 0);
        $avifSaved = (int) ($meta['avif']['saved'] ?? 0);
        $avifSize  = (int) ($meta['avif']['size'] ?? 0);

        self::atomicUpdate(function(array $stats) use ($origBytes, $webpSaved, $webpSize, $avifSaved, $avifSize): array {
            if ($webpSaved > 0) {
                $stats['total_webp_generated'] = max(0, $stats['total_webp_generated'] - 1);
                $stats['total_webp_bytes']     = max(0, $stats['total_webp_bytes'] - $webpSize);
                $stats['total_bytes_saved']    = max(0, $stats['total_bytes_saved'] - $webpSaved);
                $stats['total_original_bytes'] = max(0, $stats['total_original_bytes'] - $origBytes);
                $stats['total_originals_processed'] = max(0, $stats['total_originals_processed'] - 1);
            }

            if ($avifSaved > 0) {
                $stats['total_avif_generated'] = max(0, $stats['total_avif_generated'] - 1);
                $stats['total_avif_bytes']     = max(0, $stats['total_avif_bytes'] - $avifSize);
                $stats['total_bytes_saved']    = max(0, $stats['total_bytes_saved'] - $avifSaved);
                if ($webpSaved === 0) {
                    $stats['total_original_bytes'] = max(0, $stats['total_original_bytes'] - $origBytes);
                    $stats['total_originals_processed'] = max(0, $stats['total_originals_processed'] - 1);
                }
            }

            if ($stats['total_original_bytes'] > 0) {
                $stats['percentage_saved'] = round(($stats['total_bytes_saved'] / $stats['total_original_bytes']) * 100, 1);
            } else {
                $stats['percentage_saved'] = 0.0;
            }

            return $stats;
        });

        delete_post_meta($attachmentId, self::META_KEY);
    }

    /**
     * Perform local reconciliation of all stats across media library.
     *
     * @return array<string, int|float> Reconciled statistics
     */
    public static function recalculateAllStats(): array {
        global $wpdb;

        $stats = self::getDefaultStats();
        
        // Query attachments carrying NextGen conversion metadata
        if (isset($wpdb) && !empty($wpdb->postmeta)) {
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = %s
            ", self::META_KEY));

            if ($rows) {
                foreach ($rows as $row) {
                    $meta = maybe_unserialize($row->meta_value);
                    if (!is_array($meta)) {
                        continue;
                    }

                    $origBytes = (int) ($meta['original_size'] ?? 0);
                    $hasFormat = false;

                    if (!empty($meta['webp']['generated']) && !empty($meta['webp']['saved'])) {
                        $stats['total_webp_generated']++;
                        $stats['total_webp_bytes'] += (int) ($meta['webp']['size'] ?? 0);
                        $stats['total_bytes_saved'] += (int) ($meta['webp']['saved'] ?? 0);
                        $hasFormat = true;
                    }

                    if (!empty($meta['avif']['generated']) && !empty($meta['avif']['saved'])) {
                        $stats['total_avif_generated']++;
                        $stats['total_avif_bytes'] += (int) ($meta['avif']['size'] ?? 0);
                        $stats['total_bytes_saved'] += (int) ($meta['avif']['saved'] ?? 0);
                        $hasFormat = true;
                    }

                    if ($hasFormat) {
                        $stats['total_originals_processed']++;
                        $stats['total_original_bytes'] += $origBytes;
                    }
                }
            }
        }

        if ($stats['total_original_bytes'] > 0) {
            $stats['percentage_saved'] = round(($stats['total_bytes_saved'] / $stats['total_original_bytes']) * 100, 1);
        }

        $stats['successful_conversions'] = $stats['total_webp_generated'] + $stats['total_avif_generated'];
        $stats['last_reconciled_at'] = time();

        update_option(self::OPTION_KEY, $stats);
        return $stats;
    }

    /**
     * Concurrency-safe atomic option updater.
     *
     * @param callable $callback function(array $currentStats): array $newStats
     * @return void
     */
    private static function atomicUpdate(callable $callback): void {
        $current = self::getStats();
        $updated = $callback($current);
        update_option(self::OPTION_KEY, $updated);
    }
}

<?php
/**
 * Attachment Optimization Metadata Manager.
 *
 * Stores and retrieves WebP conversion status and compression statistics
 * directly within standard WordPress attachment metadata.
 *
 * @package NextGen\Storage
 */

namespace NextGen\Storage;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MetadataManager {

    /**
     * Metadata key inside _wp_attachment_metadata.
     */
    public const META_KEY = '_nextgen_webp_data';

    /**
     * Get conversion metadata for an attachment.
     *
     * @param int $attachmentId
     * @return array|null
     */
    public static function getAttachmentData(int $attachmentId): ?array {
        if ($attachmentId <= 0) {
            return null;
        }

        $meta = get_post_meta($attachmentId, self::META_KEY, true);
        return is_array($meta) ? $meta : null;
    }

    /**
     * Save conversion metadata for an attachment.
     *
     * @param int $attachmentId
     * @param array $data
     * @return bool
     */
    public static function saveAttachmentData(int $attachmentId, array $data): bool {
        if ($attachmentId <= 0) {
            return false;
        }

        $data['updated_at'] = time();
        return (bool) update_post_meta($attachmentId, self::META_KEY, $data);
    }

    /**
     * Delete conversion metadata for an attachment.
     *
     * @param int $attachmentId
     * @return bool
     */
    public static function deleteAttachmentData(int $attachmentId): bool {
        if ($attachmentId <= 0) {
            return false;
        }

        return (bool) delete_post_meta($attachmentId, self::META_KEY);
    }

    /**
     * Check if an attachment has been successfully converted.
     *
     * @param int $attachmentId
     * @return bool
     */
    public static function isOptimized(int $attachmentId): bool {
        $data = self::getAttachmentData($attachmentId);
        return !empty($data['status']) && $data['status'] === 'completed';
    }

    /**
     * Aggregate overall Media Library optimization statistics.
     *
     * @return array [total, optimized, pending, skipped, failed, total_saved_bytes, original_total_bytes]
     */
    public static function getStats(): array {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return [
                'total_images'        => 0,
                'optimized_images'    => 0,
                'pending_images'      => 0,
                'skipped_images'      => 0,
                'failed_images'       => 0,
                'saved_bytes'         => 0,
                'original_bytes'      => 0,
                'savings_percentage'  => 0.0,
            ];
        }

        // Count total image attachments with supported MIME types
        $totalImages = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND post_mime_type IN (%s, %s, %s, %s)",
                'attachment',
                'image/jpeg',
                'image/pjpeg',
                'image/png',
                'image/gif'
            )
        );

        // Fetch all NextGen metadata entries
        $metaEntries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                self::META_KEY
            )
        );

        $optimized = 0;
        $skipped = 0;
        $failed = 0;
        $savedBytes = 0;
        $originalBytes = 0;

        if (!empty($metaEntries)) {
            foreach ($metaEntries as $entry) {
                $data = maybe_unserialize($entry->meta_value);
                if (!is_array($data)) {
                    continue;
                }

                $status = $data['status'] ?? '';
                if ($status === 'completed') {
                    $optimized++;
                    $savedBytes += (int) ($data['total_saved_bytes'] ?? 0);
                    $originalBytes += (int) ($data['total_original_bytes'] ?? 0);
                } elseif ($status === 'skipped') {
                    $skipped++;
                } elseif ($status === 'failed') {
                    $failed++;
                }
            }
        }

        $pending = max(0, $totalImages - ($optimized + $skipped + $failed));
        $savingsPct = ($originalBytes > 0 && $savedBytes > 0)
            ? round(($savedBytes / $originalBytes) * 100, 1)
            : 0.0;

        return [
            'total_images'        => $totalImages,
            'optimized_images'    => $optimized,
            'pending_images'      => $pending,
            'skipped_images'      => $skipped,
            'failed_images'       => $failed,
            'saved_bytes'         => $savedBytes,
            'original_bytes'      => $originalBytes,
            'savings_percentage'  => $savingsPct,
        ];
    }
}

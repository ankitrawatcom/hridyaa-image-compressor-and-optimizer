<?php
/**
 * Bulk Queue Manager.
 *
 * Discovers and queries Media Library image attachment IDs eligible for conversion.
 *
 * @package NextGen\Queue
 */

namespace NextGen\Queue;

use NextGen\Storage\MetadataManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QueueManager {

    /**
     * Get list of unoptimized or pending attachment IDs.
     *
     * @param bool $includeFailed Include previously failed items for retry.
     * @param int $limit Max IDs to return (0 for all).
     * @return int[] Array of attachment post IDs.
     */
    public static function getPendingAttachmentIds(bool $includeFailed = false, int $limit = 0): array {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return [];
        }

        $metaKey = MetadataManager::META_KEY;
        $mimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'];
        $placeholders = implode(',', array_fill(0, count($mimes), '%s'));

        if ($includeFailed) {
            $sql = "
                SELECT p.ID FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s)
                WHERE p.post_type = %s
                AND p.post_mime_type IN (%s, %s, %s, %s)
                AND (pm.meta_id IS NULL OR pm.meta_value LIKE %s)
                ORDER BY p.ID DESC
            ";
            $args = [$metaKey, 'attachment', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', '%"status";s:6:"failed"%'];
            if ($limit > 0) {
                $sql .= " LIMIT %d";
                $args[] = $limit;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $results = $wpdb->get_col($wpdb->prepare($sql, ...$args));
        } else {
            $sql = "
                SELECT p.ID FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s)
                WHERE p.post_type = %s
                AND p.post_mime_type IN (%s, %s, %s, %s)
                AND pm.meta_id IS NULL
                ORDER BY p.ID DESC
            ";
            $args = [$metaKey, 'attachment', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'];
            if ($limit > 0) {
                $sql .= " LIMIT %d";
                $args[] = $limit;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $results = $wpdb->get_col($wpdb->prepare($sql, ...$args));
        }

        return !empty($results) ? array_map('intval', (array) $results) : [];
    }

    /**
     * Get all supported attachment IDs in the Media Library.
     *
     * @return int[]
     */
    public static function getAllSupportedAttachmentIds(): array {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return [];
        }

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND post_mime_type IN (%s, %s, %s, %s) 
                 ORDER BY ID DESC",
                'attachment',
                'image/jpeg',
                'image/pjpeg',
                'image/png',
                'image/gif'
            )
        );

        return !empty($results) ? array_map('intval', (array) $results) : [];
    }

    /**
     * Reset all conversion metadata to allow complete re-optimization.
     *
     * @return int Number of reset attachments.
     */
    public static function resetAllMetadata(): int {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return 0;
        }

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                MetadataManager::META_KEY
            )
        );
    }
}

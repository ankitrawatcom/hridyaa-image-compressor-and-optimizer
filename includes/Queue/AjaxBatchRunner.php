<?php
/**
 * AJAX Batch Runner for Bulk Optimization.
 *
 * Provides responsive, chunked batch processing with live progress, pause/resume,
 * and error resilience.
 *
 * @package NextGen\Queue
 */

namespace NextGen\Queue;

use NextGen\Image\AttachmentHandler;
use NextGen\Storage\MetadataManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AjaxBatchRunner {

    /**
     * Attachment handler.
     *
     * @var AttachmentHandler
     */
    private AttachmentHandler $attachmentHandler;

    /**
     * Constructor.
     */
    public function __construct(AttachmentHandler $attachmentHandler) {
        $this->attachmentHandler = $attachmentHandler;
    }

    /**
     * Register WordPress AJAX hooks.
     *
     * @return void
     */
    public function registerHooks(): void {
        add_action('wp_ajax_nextgen_get_bulk_queue', [$this, 'handleGetBulkQueue']);
        add_action('wp_ajax_nextgen_process_bulk_item', [$this, 'handleProcessBulkItem']);
        add_action('wp_ajax_nextgen_reset_bulk_queue', [$this, 'handleResetBulkQueue']);
    }

    /**
     * AJAX: Fetch pending queue attachment IDs.
     */
    public function handleGetBulkQueue(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied: manage_options capability required.'], 403);
            return;
        }

        $includeFailed = !empty($_POST['include_failed']);
        $pendingIds = QueueManager::getPendingAttachmentIds($includeFailed);
        $totalSupported = count(QueueManager::getAllSupportedAttachmentIds());

        wp_send_json_success([
            'queue'           => $pendingIds,
            'total_pending'   => count($pendingIds),
            'total_supported' => $totalSupported,
        ]);
    }

    /**
     * AJAX: Process a single attachment with all intermediate sizes.
     */
    public function handleProcessBulkItem(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied: manage_options capability required.'], 403);
            return;
        }

        $attachmentId = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        if ($attachmentId <= 0) {
            wp_send_json_error(['message' => 'Invalid attachment ID.'], 400);
            return;
        }

        $force = !empty($_POST['force']);
        $result = $this->attachmentHandler->processAttachment($attachmentId, null, ['force' => $force]);

        $stats = MetadataManager::getStats();

        wp_send_json_success([
            'attachment_id' => $attachmentId,
            'result'        => $result,
            'stats'         => $stats,
        ]);
    }

    /**
     * AJAX: Reset all metadata to allow complete re-optimization.
     */
    public function handleResetBulkQueue(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied: manage_options capability required.'], 403);
            return;
        }

        $resetCount = QueueManager::resetAllMetadata();
        $stats = MetadataManager::getStats();

        wp_send_json_success([
            'reset_count' => $resetCount,
            'stats'       => $stats,
        ]);
    }
}

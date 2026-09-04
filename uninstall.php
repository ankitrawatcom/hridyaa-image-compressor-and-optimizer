<?php
/**
 * Plugin Uninstall Routine.
 *
 * Cleans up options, transients, postmeta, scheduled cron hooks, and ephemeral previews.
 * Original user media files are strictly preserved. Supports single-site and multisite.
 *
 * @package NextGen
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up all NextGen data for the current site.
 */
function nextgen_uninstall_single_site(): void {
    global $wpdb;

    // 1. Delete all plugin options
    $options = [
        'nextgen_image_optimizer_options',
        'nextgen_preset',
        'nextgen_webp_quality',
        'nextgen_avif_quality',
        'nextgen_failed_queue',
        'nextgen_savings_stats',
        'nextgen_background_optimizer_state',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    // 2. Delete all transients
    $transients = [
        'nextgen_system_capabilities',
        'nextgen_bg_worker_lock',
        'nextgen_stats_update_lock',
        'nextgen_retry_worker_lock',
    ];

    foreach ($transients as $transient) {
        delete_transient($transient);
    }

    // 3. Clear scheduled cron events
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook('nextgen_cron_batch_worker');
    }

    // 4. Clean up attachment conversion metadata
    if (isset($wpdb) && $wpdb instanceof \wpdb && !empty($wpdb->postmeta)) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)",
                '_nextgen_webp_data',
                '_nextgen_conversion_data'
            )
        );
    }

    // 5. Clean up ephemeral preview directory in uploads
    if (function_exists('wp_upload_dir')) {
        $uploadDir = wp_upload_dir();
        $previewDir = ($uploadDir['basedir'] ?? '') . '/nextgen-previews';
        if (!empty($previewDir) && is_dir($previewDir)) {
            $files = glob($previewDir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        if (function_exists('wp_delete_file')) {
                            wp_delete_file($file);
                        } else {
                            @unlink($file);
                        }
                    }
                }
            }
            @rmdir($previewDir);
        }
    }
}

// Execute single-site or multisite cleanup
if (is_multisite()) {
    $nextgen_sites = get_sites(['number' => 0]);
    if (!empty($nextgen_sites)) {
        foreach ($nextgen_sites as $nextgen_site) {
            switch_to_blog((int) $nextgen_site->blog_id);
            nextgen_uninstall_single_site();
            restore_current_blog();
        }
    }
} else {
    nextgen_uninstall_single_site();
}


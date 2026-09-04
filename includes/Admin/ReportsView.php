<?php
/**
 * Admin Reports & Optimization Breakdown View.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

use NextGen\Storage\MetadataManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReportsView {

    /**
     * Render the Reports page.
     *
     * @return void
     */
    public static function render(): void {
        $stats = StatsManager::getStats();
        $metaStats = MetadataManager::getStats();
        $failedItems = FailedQueueManager::getFailedItems();
        $failedCount = count($failedItems);

        $totalOriginals = (int) ($stats['total_originals_processed'] ?? 0);
        $totalSavedBytes = (int) ($stats['total_bytes_saved'] ?? 0);
        $pctSaved = (float) ($stats['percentage_saved'] ?? 0.0);

        $origBytes = (int) ($stats['total_original_bytes'] ?? 0);
        $webpBytes = (int) ($stats['total_webp_bytes'] ?? 0);
        $webpCount = (int) ($stats['total_webp_generated'] ?? 0);
        $avifCount = (int) ($stats['total_avif_generated'] ?? 0);
        $avifBytes = (int) ($stats['total_avif_bytes'] ?? 0);

        $totalLibraryImages = (int) ($metaStats['total_images'] ?? 0);
        $conversionRatio = $totalLibraryImages > 0 ? round(($totalOriginals / $totalLibraryImages) * 100, 1) : 0;

        $savedFormatted = self::formatBytes($totalSavedBytes);
        $origFormatted = self::formatBytes($origBytes);
        $webpFormatted = self::formatBytes($webpBytes);
        $webpSaved = self::formatBytes($origBytes - $webpBytes);
        ?>
        <div class="wrap nextgen-admin-wrap">
            <?php AdminHeaderView::render(__('Optimization Reports & Logs', 'nextgen-image-optimizer'), __('Detailed metrics and format-by-format breakdown of your media library optimization.', 'nextgen-image-optimizer')); ?>

            <div class="nextgen-grid-main-sidebar">
                <div class="nextgen-col-main">
                    <!-- Efficiency Summary Card -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-analytics"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Media Library Compression Summary', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Aggregate storage reduction achieved across all image conversions.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <div class="nextgen-metrics-row">
                            <div class="nextgen-metric-box">
                                <span class="nextgen-metric-label"><?php esc_html_e('Total Library Images', 'nextgen-image-optimizer'); ?></span>
                                <span class="nextgen-metric-num"><?php echo esc_html(number_format_i18n($totalLibraryImages)); ?></span>
                                <span class="nextgen-metric-sub"><?php esc_html_e('Supported image attachments', 'nextgen-image-optimizer'); ?></span>
                            </div>
                            <div class="nextgen-metric-box">
                                <span class="nextgen-metric-label"><?php esc_html_e('Optimized Originals', 'nextgen-image-optimizer'); ?></span>
                                <span class="nextgen-metric-num nextgen-num-primary"><?php echo esc_html(number_format_i18n($totalOriginals)); ?></span>
                                <span class="nextgen-metric-sub"><?php echo esc_html(sprintf(__('%s%% of total library', 'nextgen-image-optimizer'), $conversionRatio)); ?></span>
                            </div>
                            <div class="nextgen-metric-box">
                                <span class="nextgen-metric-label"><?php esc_html_e('Total Storage Saved', 'nextgen-image-optimizer'); ?></span>
                                <span class="nextgen-metric-num nextgen-num-success"><?php echo esc_html($savedFormatted); ?></span>
                                <span class="nextgen-metric-sub"><?php echo esc_html(sprintf(__('%s%% average reduction', 'nextgen-image-optimizer'), $pctSaved)); ?></span>
                            </div>
                        </div>

                        <table class="nextgen-table" style="margin-top: 20px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Metric', 'nextgen-image-optimizer'); ?></th>
                                    <th><?php esc_html_e('Original Data', 'nextgen-image-optimizer'); ?></th>
                                    <th><?php esc_html_e('Optimized Data', 'nextgen-image-optimizer'); ?></th>
                                    <th><?php esc_html_e('Net Savings', 'nextgen-image-optimizer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong><?php esc_html_e('WebP Format (Free Engine)', 'nextgen-image-optimizer'); ?></strong></td>
                                    <td><?php echo esc_html($origFormatted); ?></td>
                                    <td><?php echo esc_html($webpFormatted); ?> (<?php echo esc_html(number_format_i18n($webpCount)); ?> derivatives)</td>
                                    <td><span class="nextgen-badge nextgen-badge-success"><?php echo esc_html($webpSaved); ?> (<?php echo esc_html($pctSaved); ?>%)</span></td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('AVIF Format (NextGen Pro)', 'nextgen-image-optimizer'); ?></strong></td>
                                    <td><?php echo esc_html($avifCount > 0 ? $origFormatted : '—'); ?></td>
                                    <td><?php echo esc_html($avifCount > 0 ? self::formatBytes($avifBytes) . ' (' . number_format_i18n($avifCount) . ' derivatives)' : __('Unlock with Pro', 'nextgen-image-optimizer')); ?></td>
                                    <td>
                                        <?php if ($avifCount > 0): ?>
                                            <span class="nextgen-badge nextgen-badge-success"><?php echo esc_html(self::formatBytes($origBytes - $avifBytes)); ?></span>
                                        <?php else: ?>
                                            <span class="nextgen-badge nextgen-badge-neutral"><?php esc_html_e('Available in Pro', 'nextgen-image-optimizer'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Failed Queue Section -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header" style="justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="nextgen-card-header-icon"><span class="dashicons dashicons-warning"></span></div>
                                <div>
                                    <h2 class="nextgen-card-title"><?php esc_html_e('Failed Conversion Queue', 'nextgen-image-optimizer'); ?></h2>
                                    <p class="nextgen-card-desc"><?php esc_html_e('Images that failed conversion due to memory bounds, corrupt streams, or timeouts.', 'nextgen-image-optimizer'); ?></p>
                                </div>
                            </div>
                            <?php if ($failedCount > 0): ?>
                                <div class="nextgen-btn-group">
                                    <button type="button" id="btn-retry-failed-reports" class="nextgen-btn nextgen-btn-primary">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php esc_html_e('Retry All Failed', 'nextgen-image-optimizer'); ?>
                                    </button>
                                    <button type="button" id="btn-clear-failed-reports" class="nextgen-btn nextgen-btn-secondary">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php esc_html_e('Clear Queue', 'nextgen-image-optimizer'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($failedCount === 0): ?>
                            <div class="nextgen-empty-state">
                                <span class="dashicons dashicons-yes-alt nextgen-empty-icon" style="color: #059669;"></span>
                                <h3><?php esc_html_e('No Failed Conversions', 'nextgen-image-optimizer'); ?></h3>
                                <p><?php esc_html_e('All processed image attachments were converted successfully without errors.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        <?php else: ?>
                            <table class="nextgen-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Attachment ID', 'nextgen-image-optimizer'); ?></th>
                                        <th><?php esc_html_e('Format', 'nextgen-image-optimizer'); ?></th>
                                        <th><?php esc_html_e('Category', 'nextgen-image-optimizer'); ?></th>
                                        <th><?php esc_html_e('Error Details', 'nextgen-image-optimizer'); ?></th>
                                        <th><?php esc_html_e('Attempts', 'nextgen-image-optimizer'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($failedItems as $item): ?>
                                        <tr>
                                            <td><strong>#<?php echo esc_html($item['attachment_id']); ?></strong> (<code><?php echo esc_html($item['file_path']); ?></code>)</td>
                                            <td><span class="nextgen-badge nextgen-badge-neutral"><?php echo esc_html(strtoupper($item['format'])); ?></span></td>
                                            <td><span class="nextgen-badge nextgen-badge-danger"><?php echo esc_html($item['failure_category']); ?></span></td>
                                            <td><?php echo esc_html($item['safe_message']); ?></td>
                                            <td><?php echo esc_html($item['retry_count'] ?? 1); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="nextgen-col-sidebar">
                    <!-- Quick Actions Card -->
                    <div class="nextgen-card">
                        <h3 class="nextgen-sidebar-heading"><?php esc_html_e('Quick Actions', 'nextgen-image-optimizer'); ?></h3>
                        <ul class="nextgen-sidebar-links">
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-settings')); ?>" class="nextgen-sidebar-link"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Optimization Settings', 'nextgen-image-optimizer'); ?></a></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-diagnostics')); ?>" class="nextgen-sidebar-link"><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('System Diagnostics', 'nextgen-image-optimizer'); ?></a></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-visualizer')); ?>" class="nextgen-sidebar-link"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Quality Visualizer', 'nextgen-image-optimizer'); ?></a></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-tools')); ?>" class="nextgen-sidebar-link"><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Maintenance Tools', 'nextgen-image-optimizer'); ?></a></li>
                        </ul>
                    </div>

                    <!-- Pro Upsell Card -->
                    <div class="nextgen-card nextgen-card-pro-upsell">
                        <div class="nextgen-pro-badge"><?php esc_html_e('PRO ADDON', 'nextgen-image-optimizer'); ?></div>
                        <h3 class="nextgen-pro-title"><?php esc_html_e('NextGen Image Optimizer Pro', 'nextgen-image-optimizer'); ?></h3>
                        <p class="nextgen-pro-desc"><?php esc_html_e('Unlock next-generation AVIF codec encoding, multi-tier WebP + AVIF picture tag delivery, and priority technical support.', 'nextgen-image-optimizer'); ?></p>
                        <ul class="nextgen-pro-features">
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('AVIF encoding & companion files', 'nextgen-image-optimizer'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Multi-tier HTML5 Picture tag delivery', 'nextgen-image-optimizer'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Interactive split-view AVIF preview', 'nextgen-image-optimizer'); ?></li>
                            <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Priority commercial updates & support', 'nextgen-image-optimizer'); ?></li>
                        </ul>
                        <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-btn nextgen-btn-pro nextgen-btn-block">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('Upgrade to Pro Now', 'nextgen-image-optimizer'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private static function formatBytes(int $bytes): string {
        if ($bytes <= 0) {
            return '0 B';
        }
        if (function_exists('size_format')) {
            return size_format($bytes, 2);
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}

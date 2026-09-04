<?php
/**
 * Diagnostics & System Status View for NextGen Image Optimizer.
 *
 * Renders read-only local capability status table and copyable markdown report.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

use NextGen\Support\SystemDetector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DiagnosticsView {

    /**
     * Render System Status & Diagnostics View HTML.
     *
     * @param SystemDetector|null $detector Optional system detector instance.
     * @return void
     */
    public static function render(?SystemDetector $detector = null): void {
        $audit = DiagnosticsScanner::runFullAudit();
        $report = DiagnosticsScanner::getFormattedMarkdownReport();
        ?>
        <div class="wrap nextgen-admin-wrap nextgen-diagnostics-wrap">
            <?php AdminHeaderView::render(__('System & Codec Diagnostics', 'nextgen-image-optimizer'), __('Verify PHP environment capabilities, image encoding libraries, memory limits, and filesystem permissions.', 'nextgen-image-optimizer')); ?>

            <div class="nextgen-grid-main-sidebar">
                <div class="nextgen-col-main">
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-dashboard"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Environment & Codec Audit', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Real-time capability scan of your host PHP runtime.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <table class="nextgen-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%;"><?php esc_html_e('Check', 'nextgen-image-optimizer'); ?></th>
                                    <th style="width: 15%;"><?php esc_html_e('Status', 'nextgen-image-optimizer'); ?></th>
                                    <th style="width: 20%;"><?php esc_html_e('Detected Value', 'nextgen-image-optimizer'); ?></th>
                                    <th style="width: 40%;"><?php esc_html_e('Operational Details', 'nextgen-image-optimizer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($audit as $item): ?>
                                    <?php
                                    $status = $item['status'];
                                    $badgeClass = 'nextgen-badge-success';
                                    $badgeIcon = 'dashicons-yes-alt';
                                    if ($status === DiagnosticsScanner::WARNING) {
                                        $badgeClass = 'nextgen-badge-warning';
                                        $badgeIcon = 'dashicons-warning';
                                    } elseif ($status === DiagnosticsScanner::FAIL) {
                                        $badgeClass = 'nextgen-badge-danger';
                                        $badgeIcon = 'dashicons-dismiss';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($item['label']); ?></strong></td>
                                        <td>
                                            <span class="nextgen-badge <?php echo esc_attr($badgeClass); ?>">
                                                <span class="dashicons <?php echo esc_attr($badgeIcon); ?>"></span>
                                                <?php echo esc_html($status); ?>
                                            </span>
                                        </td>
                                        <td><code><?php echo esc_html($item['value']); ?></code></td>
                                        <td><?php echo esc_html($item['message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                            <h3 style="font-size: 15px; margin: 0 0 8px;"><?php esc_html_e('Support Diagnostics Report', 'nextgen-image-optimizer'); ?></h3>
                            <p class="description" style="margin-bottom: 10px;"><?php esc_html_e('Copy this markdown report when opening support discussions or debugging server issues.', 'nextgen-image-optimizer'); ?></p>
                            <textarea id="nextgen-system-report" readonly="readonly" class="large-text code" rows="7" style="font-family: monospace; font-size: 12px; background: #f8fafc;"><?php echo esc_textarea($report); ?></textarea>
                            <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                                <button type="button" class="nextgen-btn nextgen-btn-secondary" id="nextgen-copy-report-btn">
                                    <span class="dashicons dashicons-clipboard"></span>
                                    <?php esc_html_e('Copy Diagnostics Report', 'nextgen-image-optimizer'); ?>
                                </button>
                                <span id="nextgen-copy-report-feedback" style="font-weight: 600; color: #059669; display: none;"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nextgen-col-sidebar">
                    <!-- Diagnostics Info Card -->
                    <div class="nextgen-card">
                        <h3 class="nextgen-sidebar-heading"><?php esc_html_e('Recommended Server Setup', 'nextgen-image-optimizer'); ?></h3>
                        <ul class="nextgen-sidebar-checklist">
                            <li><strong>PHP:</strong> 7.4 or higher (8.1+ recommended)</li>
                            <li><strong>Memory Limit:</strong> 256MB+ recommended</li>
                            <li><strong>Extensions:</strong> <code>ext-gd</code> or <code>ext-imagick</code> with WebP support enabled</li>
                            <li><strong>Uploads:</strong> Writable permissions on <code>wp-content/uploads</code></li>
                        </ul>
                    </div>

                    <!-- Pro Card -->
                    <div class="nextgen-card nextgen-card-pro-upsell">
                        <div class="nextgen-pro-badge"><?php esc_html_e('PRO ADDON', 'nextgen-image-optimizer'); ?></div>
                        <h3 class="nextgen-pro-title"><?php esc_html_e('Unlock AVIF Codec in Pro', 'nextgen-image-optimizer'); ?></h3>
                        <p class="nextgen-pro-desc"><?php esc_html_e('AVIF provides up to 50% better compression than WebP. Upgrade to Pro to enable automated AVIF conversions.', 'nextgen-image-optimizer'); ?></p>
                        <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-btn nextgen-btn-pro nextgen-btn-block">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('Upgrade to Pro', 'nextgen-image-optimizer'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

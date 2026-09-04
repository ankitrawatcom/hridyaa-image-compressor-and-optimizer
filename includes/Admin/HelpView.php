<?php
/**
 * Admin Help & Support View.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

use NextGen\Support\SystemDetector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HelpView {

    /**
     * Render the Help & Support page.
     *
     * @param SystemDetector|null $detector Optional system detector instance.
     * @return void
     */
    public static function render(?SystemDetector $detector = null): void {
        if ($detector === null) {
            $detector = new SystemDetector();
        }
        $reportMarkdown = DiagnosticsScanner::getFormattedMarkdownReport();
        ?>
        <div class="wrap nextgen-admin-wrap">
            <?php AdminHeaderView::render(__('Help & Documentation', 'nextgen-image-optimizer'), __('Quick start guide, troubleshooting tips, and official documentation resources.', 'nextgen-image-optimizer')); ?>

            <div class="nextgen-grid-main-sidebar">
                <div class="nextgen-col-main">
                    <!-- Quick Start Guide -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-welcome-learn-more"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Quick Start Guide', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Get your WordPress media optimized in three easy steps.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <div class="nextgen-steps-grid">
                            <div class="nextgen-step-card">
                                <div class="nextgen-step-num">1</div>
                                <h4><?php esc_html_e('Check System Diagnostics', 'nextgen-image-optimizer'); ?></h4>
                                <p><?php esc_html_e('Verify that PHP GD or ImageMagick is active on your server with WebP support.', 'nextgen-image-optimizer'); ?></p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-diagnostics')); ?>" class="button button-small"><?php esc_html_e('View Diagnostics', 'nextgen-image-optimizer'); ?></a>
                            </div>

                            <div class="nextgen-step-card">
                                <div class="nextgen-step-num">2</div>
                                <h4><?php esc_html_e('Configure Settings', 'nextgen-image-optimizer'); ?></h4>
                                <p><?php esc_html_e('Choose your compression preset (Balanced is recommended) and enable HTML5 Picture delivery.', 'nextgen-image-optimizer'); ?></p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-settings')); ?>" class="button button-small"><?php esc_html_e('Configure Settings', 'nextgen-image-optimizer'); ?></a>
                            </div>

                            <div class="nextgen-step-card">
                                <div class="nextgen-step-num">3</div>
                                <h4><?php esc_html_e('Run Bulk Optimization', 'nextgen-image-optimizer'); ?></h4>
                                <p><?php esc_html_e('Optimize existing media library images with the live progress tool or background worker.', 'nextgen-image-optimizer'); ?></p>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-settings')); ?>" class="button button-small"><?php esc_html_e('Start Bulk Optimizer', 'nextgen-image-optimizer'); ?></a>
                            </div>
                        </div>
                    </div>

                    <!-- FAQs Accordion / Cards -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-format-chat"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Frequently Asked Questions', 'hridyaa-image-compressor-and-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Common questions and answers regarding Hridyaa Image Compressor and Optimizer.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                            </div>
                        </div>

                        <div class="nextgen-faq-list">
                            <div class="nextgen-faq-item">
                                <h4 class="nextgen-faq-q"><span class="dashicons dashicons-arrow-right-alt2"></span> <?php esc_html_e('Does the plugin overwrite or delete my original images?', 'hridyaa-image-compressor-and-optimizer'); ?></h4>
                                <p class="nextgen-faq-a"><?php esc_html_e('No. Your original JPEG, PNG, and GIF images are permanently preserved. The plugin generates separate companion files alongside your originals.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                            </div>

                            <div class="nextgen-faq-item">
                                <h4 class="nextgen-faq-q"><span class="dashicons dashicons-arrow-right-alt2"></span> <?php esc_html_e('How does frontend HTML5 picture delivery work?', 'hridyaa-image-compressor-and-optimizer'); ?></h4>
                                <p class="nextgen-faq-a"><?php esc_html_e('When enabled, the plugin dynamically rewrites <img> tags in your post content into modern HTML5 <picture> tags containing <source type="image/webp"> with native fallback for older browsers.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                            </div>

                            <div class="nextgen-faq-item">
                                <h4 class="nextgen-faq-q"><span class="dashicons dashicons-arrow-right-alt2"></span> <?php esc_html_e('What is the Negative Compression Guard?', 'hridyaa-image-compressor-and-optimizer'); ?></h4>
                                <p class="nextgen-faq-a"><?php esc_html_e('If a converted WebP image is larger than or equal to the original image in byte size, the optimizer automatically discards the WebP file to ensure you never waste server storage.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                            </div>

                            <div class="nextgen-faq-item">
                                <h4 class="nextgen-faq-q"><span class="dashicons dashicons-arrow-right-alt2"></span> <?php esc_html_e('Can I optimize images in the background without keeping my browser open?', 'hridyaa-image-compressor-and-optimizer'); ?></h4>
                                <p class="nextgen-faq-a"><?php esc_html_e('Yes! Hridyaa Image Compressor and Optimizer includes a headless WP-Cron background worker that runs on scheduled cron events in the background.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- System Report Box for Support -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-clipboard"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Support Diagnostics Report', 'hridyaa-image-compressor-and-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Share this technical report when seeking assistance or opening a support ticket.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                            </div>
                        </div>

                        <div class="nextgen-report-box">
                            <textarea id="nextgen-system-report" readonly="readonly" class="large-text code" rows="8" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea($reportMarkdown); ?></textarea>
                            <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                                <button type="button" id="nextgen-copy-report-btn" class="nextgen-btn nextgen-btn-secondary">
                                    <span class="dashicons dashicons-clipboard"></span>
                                    <?php esc_html_e('Copy Diagnostics Report to Clipboard', 'hridyaa-image-compressor-and-optimizer'); ?>
                                </button>
                                <span id="nextgen-copy-report-feedback" style="display: none; font-weight: 600; color: #008a20;"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nextgen-col-sidebar">
                    <!-- Official Resources Card -->
                    <div class="nextgen-card">
                        <h3 class="nextgen-sidebar-heading"><?php esc_html_e('Official Resources', 'hridyaa-image-compressor-and-optimizer'); ?></h3>
                        <ul class="nextgen-sidebar-links">
                            <li>
                                <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-sidebar-link">
                                    <span class="dashicons dashicons-admin-site-alt3"></span>
                                    <?php esc_html_e('Official Product Website', 'hridyaa-image-compressor-and-optimizer'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-sidebar-link">
                                    <span class="dashicons dashicons-book"></span>
                                    <?php esc_html_e('Documentation & Guides', 'hridyaa-image-compressor-and-optimizer'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="https://wordpress.org/support/plugin/hridyaa-image-compressor-and-optimizer/" target="_blank" rel="noopener noreferrer" class="nextgen-sidebar-link">
                                    <span class="dashicons dashicons-wordpress"></span>
                                    <?php esc_html_e('Community Support Forum', 'hridyaa-image-compressor-and-optimizer'); ?>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Pro Card -->
                    <div class="nextgen-card nextgen-card-pro-upsell">
                        <div class="nextgen-pro-badge"><?php esc_html_e('HRIDYAA PRO', 'hridyaa-image-compressor-and-optimizer'); ?></div>
                        <h3 class="nextgen-pro-title"><?php esc_html_e('Make Your Images Even Smaller', 'hridyaa-image-compressor-and-optimizer'); ?></h3>
                        <p class="nextgen-pro-desc"><?php esc_html_e('Unlock AVIF compression for up to 35–40% smaller image files than WebP*, smart multi-tier delivery, and priority commercial support.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                        <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-btn nextgen-btn-pro nextgen-btn-block">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('Upgrade to Pro ★', 'hridyaa-image-compressor-and-optimizer'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

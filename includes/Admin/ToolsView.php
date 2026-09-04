<?php
/**
 * Admin Tools & Maintenance View.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

use NextGen\Converter\PreviewGenerator;
use NextGen\Queue\QueueManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ToolsView {

    /**
     * Render the Tools & Maintenance page.
     *
     * @return void
     */
    public static function render(): void {
        $failedCount = count(FailedQueueManager::getFailedItems());
        $nonce = wp_create_nonce('nextgen_tools_action');
        ?>
        <div class="wrap nextgen-admin-wrap">
            <?php AdminHeaderView::render(__('Tools & Maintenance', 'nextgen-image-optimizer'), __('Administrative utilities for cache clearing, queue management, and metadata maintenance.', 'nextgen-image-optimizer')); ?>

            <?php if (!empty($_GET['tool-executed'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(sanitize_text_field($_GET['tool-executed'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="nextgen-grid-main-sidebar">
                <div class="nextgen-col-main">
                    <!-- Tool 1: Reset Optimization Metadata -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-update"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Reset Optimization Status', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Clears conversion postmeta records across all images so the Bulk Converter can re-process your full Media Library.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>
                        <p class="nextgen-card-text">
                            <?php esc_html_e('Note: This does not delete existing .webp files from disk. It resets database flags so all images become eligible for optimization again with your current preset settings.', 'nextgen-image-optimizer'); ?>
                        </p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset all optimization metadata?', 'nextgen-image-optimizer')); ?>');">
                            <?php wp_nonce_field('nextgen_tool_reset_metadata', 'nextgen_tool_nonce'); ?>
                            <input type="hidden" name="action" value="nextgen_tool_reset_metadata" />
                            <button type="submit" class="nextgen-btn nextgen-btn-secondary">
                                <span class="dashicons dashicons-image-rotate"></span>
                                <?php esc_html_e('Reset All Conversion Metadata', 'nextgen-image-optimizer'); ?>
                            </button>
                        </form>
                    </div>

                    <!-- Tool 2: Failed Queue Reset -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-trash"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Clear Failed Conversion Queue', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php echo esc_html(sprintf(__('Currently %d item(s) in failed queue.', 'nextgen-image-optimizer'), $failedCount)); ?></p>
                            </div>
                        </div>
                        <p class="nextgen-card-text">
                            <?php esc_html_e('Clears the list of logged conversion errors. Cleared items will not show up in the error log and can be re-attempted on subsequent bulk runs.', 'nextgen-image-optimizer'); ?>
                        </p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('nextgen_tool_clear_failed', 'nextgen_tool_nonce'); ?>
                            <input type="hidden" name="action" value="nextgen_tool_clear_failed" />
                            <button type="submit" class="nextgen-btn nextgen-btn-secondary" <?php disabled($failedCount === 0); ?>>
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('Clear Failed Error Log', 'nextgen-image-optimizer'); ?>
                            </button>
                        </form>
                    </div>

                    <!-- Tool 3: Preview Cache Cleaner -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-images-alt2"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Clean Quality Visualizer Previews', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Deletes temporary preview comparisons generated in wp-content/uploads/nextgen-previews.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>
                        <p class="nextgen-card-text">
                            <?php esc_html_e('Temporary visualizer preview files are automatically purged every 2 hours. Use this tool to immediately delete all cached preview derivatives.', 'nextgen-image-optimizer'); ?>
                        </p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('nextgen_tool_purge_previews', 'nextgen_tool_nonce'); ?>
                            <input type="hidden" name="action" value="nextgen_tool_purge_previews" />
                            <button type="submit" class="nextgen-btn nextgen-btn-secondary">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e('Purge Visualizer Previews', 'nextgen-image-optimizer'); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="nextgen-col-sidebar">
                    <!-- Quick Info -->
                    <div class="nextgen-card">
                        <h3 class="nextgen-sidebar-heading"><?php esc_html_e('Maintenance Best Practices', 'nextgen-image-optimizer'); ?></h3>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.5;">
                            <?php esc_html_e('Always create a backup of your WordPress database before running bulk reset operations.', 'nextgen-image-optimizer'); ?>
                        </p>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.5;">
                            <?php esc_html_e('If your server has low memory (under 128MB), use the Balanced preset to avoid memory exhaustion on high-resolution images.', 'nextgen-image-optimizer'); ?>
                        </p>
                    </div>

                    <!-- Pro Card -->
                    <div class="nextgen-card nextgen-card-pro-upsell">
                        <div class="nextgen-pro-badge"><?php esc_html_e('PRO ADDON', 'nextgen-image-optimizer'); ?></div>
                        <h3 class="nextgen-pro-title"><?php esc_html_e('Need AVIF Support?', 'nextgen-image-optimizer'); ?></h3>
                        <p class="nextgen-pro-desc"><?php esc_html_e('Upgrade to Pro for next-generation AVIF encoding with up to 50% better compression than WebP.', 'nextgen-image-optimizer'); ?></p>
                        <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-btn nextgen-btn-pro nextgen-btn-block">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('Explore Pro Edition', 'nextgen-image-optimizer'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

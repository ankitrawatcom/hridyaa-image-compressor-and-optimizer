<?php
/**
 * Admin Dashboard & Savings Overview View for NextGen Image Optimizer.
 *
 * Renders commercial overview metrics, hero welcome banner, format breakdown,
 * quick actions, system status, and Pro positioning.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

use NextGen\Storage\MetadataManager;
use NextGen\Support\SystemDetector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminDashboardView {

    /**
     * Render the Overview & Savings Dashboard HTML.
     *
     * @param SystemDetector|null $detector Optional system detector instance.
     * @return void
     */
    public static function render(?SystemDetector $detector = null): void {
        if ($detector === null) {
            $detector = new SystemDetector();
        }

        $stats = StatsManager::getStats();
        $metaStats = MetadataManager::getStats();
        $caps = $detector->getCapabilities();
        $failedCount = count(FailedQueueManager::getFailedItems());

        $totalOriginals = (int) ($stats['total_originals_processed'] ?? 0);
        $totalSavedBytes = (int) ($stats['total_bytes_saved'] ?? 0);
        $pctSaved = (float) ($stats['percentage_saved'] ?? 0.0);

        $savedFormatted = self::formatBytes($totalSavedBytes);
        $webpCount = (int) ($stats['total_webp_generated'] ?? 0);
        $origBytes = (int) ($stats['total_original_bytes'] ?? 0);
        $webpBytes = (int) ($stats['total_webp_bytes'] ?? 0);
        $webpSaved = self::formatBytes(max(0, $origBytes - $webpBytes));
        $avifCount = (int) ($stats['total_avif_generated'] ?? 0);
        $avifBytes = (int) ($stats['total_avif_bytes'] ?? 0);
        $avifSaved = self::formatBytes(max(0, $origBytes - $avifBytes));

        $totalLibraryImages = (int) ($metaStats['total_images'] ?? 0);
        $conversionRatio = $totalLibraryImages > 0 ? round(($totalOriginals / $totalLibraryImages) * 100, 1) : 0;
        ?>
        <div class="wrap nextgen-admin-wrap nextgen-io-wrap">
            <?php AdminHeaderView::render(__('Dashboard & Savings Overview', 'nextgen-image-optimizer'), __('Monitor media library compression performance, storage savings, and image engine health.', 'nextgen-image-optimizer')); ?>

            <?php if ($failedCount > 0): ?>
                <div class="nextgen-banner nextgen-banner-warning">
                    <div class="nextgen-banner-content">
                        <span class="dashicons dashicons-warning nextgen-banner-icon"></span>
                        <div>
                            <strong><?php echo esc_html(sprintf(_n('%d image failed optimization.', '%d images failed optimization.', $failedCount, 'nextgen-image-optimizer'), $failedCount)); ?></strong>
                            <span><?php esc_html_e('Review the error log or retry all failed items from the Reports screen.', 'nextgen-image-optimizer'); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-reports')); ?>" class="nextgen-btn nextgen-btn-secondary nextgen-btn-sm">
                        <?php esc_html_e('View Failed Queue', 'nextgen-image-optimizer'); ?>
                    </a>
                </div>
            <?php endif; ?>

            <!-- 1. Hero / Welcome Card -->
            <div class="nextgen-hero-card">
                <div class="nextgen-hero-content">
                    <div class="nextgen-hero-text">
                        <h2 class="nextgen-hero-title">
                            <span class="nextgen-hero-emoji">🚀</span>
                            <?php esc_html_e('Welcome to NextGen Image Optimizer', 'nextgen-image-optimizer'); ?>
                        </h2>
                        <p class="nextgen-hero-desc">
                            <?php esc_html_e('Optimize your media library images, slash file sizes with automated WebP conversion, and deliver lightning-fast WordPress pages to your site visitors.', 'nextgen-image-optimizer'); ?>
                        </p>
                        <div class="nextgen-hero-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-settings')); ?>" class="nextgen-btn nextgen-btn-primary nextgen-btn-lg">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php esc_html_e('Run Bulk Optimization', 'nextgen-image-optimizer'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-settings')); ?>" class="nextgen-btn nextgen-btn-secondary nextgen-btn-lg">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php esc_html_e('View Settings', 'nextgen-image-optimizer'); ?>
                            </a>
                        </div>
                    </div>
                    <?php $isProActive = \NextGen\Core\Features::isAvifEnabled(); ?>
                    <div class="nextgen-hero-pro-highlight <?php echo $isProActive ? 'nextgen-hero-pro-active-card' : ''; ?>">
                        <?php if ($isProActive): ?>
                            <div class="nextgen-pro-pill nextgen-pro-pill-active">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('PRO ACTIVE', 'nextgen-image-optimizer'); ?>
                            </div>
                            <h3 class="nextgen-hero-pro-heading nextgen-hero-pro-active-heading"><?php esc_html_e('AVIF Optimization Unlocked', 'nextgen-image-optimizer'); ?></h3>
                            <p class="nextgen-pro-benefit-lead"><?php esc_html_e('Your site is equipped with next-generation AVIF image compression.', 'nextgen-image-optimizer'); ?></p>
                            
                            <div class="nextgen-pro-active-status-box">
                                <div class="nextgen-status-row">
                                    <span class="nextgen-status-label"><?php esc_html_e('Optimization Mode:', 'nextgen-image-optimizer'); ?></span>
                                    <span class="nextgen-status-val"><strong><?php esc_html_e('AVIF + WebP Fallback', 'nextgen-image-optimizer'); ?></strong></span>
                                </div>
                                <div class="nextgen-status-row">
                                    <span class="nextgen-status-label"><?php esc_html_e('Compatibility:', 'nextgen-image-optimizer'); ?></span>
                                    <span class="nextgen-status-val"><?php esc_html_e('Universal (AVIF → WebP → <img>)', 'nextgen-image-optimizer'); ?></span>
                                </div>
                                <div class="nextgen-status-row">
                                    <span class="nextgen-status-label"><?php esc_html_e('Image Engine:', 'nextgen-image-optimizer'); ?></span>
                                    <span class="nextgen-status-val">
                                        <?php
                                        $engineName = 'Active Engine';
                                        if (!empty($caps['primary_avif_engine']) && $caps['primary_avif_engine'] === 'gd') {
                                            $engineName = 'GD Engine (AVIF Ready)';
                                        } elseif (!empty($caps['primary_avif_engine']) && $caps['primary_avif_engine'] === 'imagick') {
                                            $engineName = 'ImageMagick (AVIF Ready)';
                                        } elseif (!empty($caps['avif_supported'])) {
                                            $engineName = 'Active Engine (AVIF Ready)';
                                        }
                                        echo esc_html($engineName);
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div style="margin-top: 14px;">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-settings#nextgen-format-section')); ?>" class="nextgen-btn nextgen-btn-primary nextgen-btn-sm nextgen-btn-block">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                    <?php esc_html_e('Configure AVIF Settings', 'nextgen-image-optimizer'); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="nextgen-pro-pill"><?php esc_html_e('NEXTGEN PRO', 'nextgen-image-optimizer'); ?></div>
                            <h3 class="nextgen-hero-pro-heading"><?php esc_html_e('Make Your Images Even Smaller', 'nextgen-image-optimizer'); ?></h3>
                            <p class="nextgen-pro-benefit-lead"><?php esc_html_e('Go beyond WebP with next-generation AVIF compression.', 'nextgen-image-optimizer'); ?></p>
                            <ul class="nextgen-hero-pro-list">
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Up to 35–40% smaller files than WebP*', 'nextgen-image-optimizer'); ?></li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Faster page loads & improved Core Web Vitals', 'nextgen-image-optimizer'); ?></li>
                                <li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Smart delivery — serves an efficient image format automatically', 'nextgen-image-optimizer'); ?></li>
                            </ul>
                            <div class="nextgen-pro-explainer-box">
                                <strong><?php esc_html_e('What is AVIF?', 'nextgen-image-optimizer'); ?></strong> <?php esc_html_e('AVIF is a modern image format that keeps images looking great while making them smaller than WebP in many cases.', 'nextgen-image-optimizer'); ?>
                            </div>

                            <!-- In-Dashboard License Key Activation Box -->
                            <div class="nextgen-hero-activation-box">
                                <div class="nextgen-hero-activation-title">
                                    <span class="dashicons dashicons-key"></span>
                                    <strong><?php esc_html_e('Already purchased Pro?', 'nextgen-image-optimizer'); ?></strong>
                                </div>
                                <p class="nextgen-hero-activation-desc"><?php esc_html_e('Enter your license key to unlock AVIF optimization.', 'nextgen-image-optimizer'); ?></p>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nextgen-hero-activation-form">
                                    <?php wp_nonce_field('nextgen_pro_license_action', 'nextgen_pro_license_nonce'); ?>
                                    <input type="hidden" name="action" value="nextgen_pro_save_license">
                                    <input type="hidden" name="nextgen_redirect_page" value="nextgen-image-optimizer">
                                    <input type="hidden" name="nextgen_pro_action_type" value="activate">
                                    <div class="nextgen-hero-activation-row">
                                        <input type="text" id="nextgen_dashboard_license_key" name="nextgen_license_key" placeholder="NGPRO-XXXX-XXXX-XXXX-XXXX" class="nextgen-hero-activation-input" required autocomplete="off">
                                        <button type="submit" name="nextgen_pro_activate" value="1" class="nextgen-btn nextgen-btn-primary nextgen-btn-sm nextgen-hero-activate-btn">
                                            <?php esc_html_e('Activate Pro', 'nextgen-image-optimizer'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-btn nextgen-btn-pro nextgen-btn-sm nextgen-btn-block" style="margin-top: 10px;">
                                <span class="dashicons dashicons-star-filled"></span>
                                <?php esc_html_e('Upgrade to Pro ★', 'nextgen-image-optimizer'); ?>
                            </a>
                            <p class="nextgen-pro-disclaimer">
                                <?php esc_html_e('*Potential savings compared to WebP at equivalent visual quality. Actual savings vary by image type, dimensions, quality preset, and original format.', 'nextgen-image-optimizer'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Key Metrics 4-Card Grid -->
            <div class="nextgen-stats-grid">
                <!-- Card 1: Optimized Images -->
                <div class="nextgen-metric-card">
                    <div class="nextgen-metric-header">
                        <span class="nextgen-metric-title"><?php esc_html_e('Optimized Images', 'nextgen-image-optimizer'); ?></span>
                        <span class="nextgen-metric-icon-wrap nextgen-icon-blue"><span class="dashicons dashicons-images-alt2"></span></span>
                    </div>
                    <div class="nextgen-metric-value"><?php echo esc_html(number_format_i18n($totalOriginals)); ?></div>
                    <div class="nextgen-metric-desc">
                        <?php
                        if ($totalOriginals > 0) {
                            echo esc_html(sprintf(__('%s of %s library attachments', 'nextgen-image-optimizer'), number_format_i18n($totalOriginals), number_format_i18n($totalLibraryImages)));
                        } else {
                            esc_html_e('No images optimized yet', 'nextgen-image-optimizer');
                        }
                        ?>
                    </div>
                </div>

                <!-- Card 2: Total Storage Saved -->
                <div class="nextgen-metric-card">
                    <div class="nextgen-metric-header">
                        <span class="nextgen-metric-title"><?php esc_html_e('Total Storage Saved', 'nextgen-image-optimizer'); ?></span>
                        <span class="nextgen-metric-icon-wrap nextgen-icon-green"><span class="dashicons dashicons-database"></span></span>
                    </div>
                    <div class="nextgen-metric-value nextgen-text-green"><?php echo esc_html($savedFormatted); ?></div>
                    <div class="nextgen-metric-desc">
                        <?php
                        if ($totalSavedBytes > 0) {
                            esc_html_e('Disk space saved on your server', 'nextgen-image-optimizer');
                        } else {
                            esc_html_e('No disk space saved yet', 'nextgen-image-optimizer');
                        }
                        ?>
                    </div>
                </div>

                <!-- Card 3: Average Reduction -->
                <div class="nextgen-metric-card">
                    <div class="nextgen-metric-header">
                        <span class="nextgen-metric-title"><?php esc_html_e('Average Reduction', 'nextgen-image-optimizer'); ?></span>
                        <span class="nextgen-metric-icon-wrap nextgen-icon-indigo"><span class="dashicons dashicons-performance"></span></span>
                    </div>
                    <div class="nextgen-metric-value nextgen-text-indigo"><?php echo esc_html($pctSaved); ?>%</div>
                    <div class="nextgen-metric-desc">
                        <?php
                        if ($pctSaved > 0) {
                            esc_html_e('Average file size reduction per image', 'nextgen-image-optimizer');
                        } else {
                            esc_html_e('Average compression reduction', 'nextgen-image-optimizer');
                        }
                        ?>
                    </div>
                </div>

                <!-- Card 4: Library Coverage -->
                <div class="nextgen-metric-card">
                    <div class="nextgen-metric-header">
                        <span class="nextgen-metric-title"><?php esc_html_e('Library Coverage', 'nextgen-image-optimizer'); ?></span>
                        <span class="nextgen-metric-icon-wrap nextgen-icon-amber"><span class="dashicons dashicons-chart-pie"></span></span>
                    </div>
                    <div class="nextgen-metric-value nextgen-text-amber"><?php echo esc_html($conversionRatio); ?>%</div>
                    <div class="nextgen-metric-desc">
                        <?php
                        if ($totalLibraryImages > 0) {
                            echo esc_html(sprintf(__('%s%% of media library converted', 'nextgen-image-optimizer'), $conversionRatio));
                        } else {
                            esc_html_e('No media attachments found', 'nextgen-image-optimizer');
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- 3. Main Layout Grid (2 Column) -->
            <div class="nextgen-grid-main-sidebar">
                <div class="nextgen-col-main">
                    <!-- Format Compression Breakdown & Free vs Pro Comparison -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-category"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Image Compression & Format Breakdown', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Current derivative files, storage savings, and Free vs Pro compression tiers.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <table class="nextgen-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Format Tier', 'nextgen-image-optimizer'); ?></th>
                                    <th><?php esc_html_e('Derivatives Generated', 'nextgen-image-optimizer'); ?></th>
                                    <th><?php esc_html_e('Storage Saved', 'nextgen-image-optimizer'); ?></th>
                                    <th><?php esc_html_e('Status', 'nextgen-image-optimizer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong><?php esc_html_e('WebP Format', 'nextgen-image-optimizer'); ?></strong>
                                        <span class="nextgen-subtext"><?php esc_html_e('Standard modern image format supported by 96%+ of web browsers', 'nextgen-image-optimizer'); ?></span>
                                    </td>
                                    <td><?php echo esc_html(sprintf(__('%s files', 'nextgen-image-optimizer'), number_format_i18n($webpCount))); ?></td>
                                    <td><strong><?php echo esc_html($webpSaved); ?></strong></td>
                                    <td><span class="nextgen-badge nextgen-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('ACTIVE (FREE)', 'nextgen-image-optimizer'); ?></span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong><?php esc_html_e('AVIF Format', 'nextgen-image-optimizer'); ?></strong>
                                        <span class="nextgen-subtext"><?php esc_html_e('Next-gen format — up to 35–40% smaller files than WebP at comparable visual quality*', 'nextgen-image-optimizer'); ?></span>
                                    </td>
                                    <td><?php echo esc_html($avifCount > 0 ? sprintf(__('%s files', 'nextgen-image-optimizer'), number_format_i18n($avifCount)) : '—'); ?></td>
                                    <td><?php echo esc_html($avifCount > 0 ? $avifSaved : '—'); ?></td>
                                    <td>
                                        <?php if (\NextGen\Core\Features::isAvifEnabled() || $avifCount > 0): ?>
                                            <span class="nextgen-badge nextgen-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('ACTIVE (PRO)', 'nextgen-image-optimizer'); ?></span>
                                        <?php else: ?>
                                            <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-badge nextgen-badge-pro">
                                                <span class="dashicons dashicons-lock"></span> <?php esc_html_e('PRO UPGRADE ★', 'nextgen-image-optimizer'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Compact Free vs Pro Comparison Table -->
                        <div style="margin-top: 24px;">
                            <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 10px; display: flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-awards" style="color: #d97706;"></span>
                                <?php esc_html_e('Free vs Pro Edition Comparison', 'nextgen-image-optimizer'); ?>
                            </h3>

                            <table class="nextgen-compare-table">
                                <thead>
                                    <tr>
                                        <th class="col-feature"><?php esc_html_e('Feature / Benefit', 'nextgen-image-optimizer'); ?></th>
                                        <th class="col-free"><?php esc_html_e('Free Edition', 'nextgen-image-optimizer'); ?></th>
                                        <th class="col-pro"><?php esc_html_e('Pro Edition', 'nextgen-image-optimizer'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <strong><?php esc_html_e('WebP Image Compression', 'nextgen-image-optimizer'); ?></strong>
                                            <span class="nextgen-subtext"><?php esc_html_e('Reduces image sizes by 50%–70% vs original JPEGs and PNGs', 'nextgen-image-optimizer'); ?></span>
                                        </td>
                                        <td class="col-free"><span class="nextgen-compare-tag-free">✓ <?php esc_html_e('Included', 'nextgen-image-optimizer'); ?></span></td>
                                        <td class="col-pro"><span class="nextgen-compare-tag-pro">✓ <?php esc_html_e('Included', 'nextgen-image-optimizer'); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong><?php esc_html_e('Next-Gen AVIF Compression', 'nextgen-image-optimizer'); ?></strong>
                                            <span class="nextgen-subtext"><?php esc_html_e('Advanced AVIF codec for up to 35–40% smaller files than WebP*', 'nextgen-image-optimizer'); ?></span>
                                        </td>
                                        <td class="col-free"><span style="color: #94a3b8; font-size: 13px;">—</span></td>
                                        <td class="col-pro"><span class="nextgen-compare-tag-pro">★ <?php esc_html_e('Up to 40% Smaller*', 'nextgen-image-optimizer'); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong><?php esc_html_e('Smart Format Delivery', 'nextgen-image-optimizer'); ?></strong>
                                            <span class="nextgen-subtext"><?php esc_html_e('Automatically serves an efficient image format supported by the visitor\'s browser', 'nextgen-image-optimizer'); ?></span>
                                        </td>
                                        <td class="col-free"><?php esc_html_e('WebP + Fallback', 'nextgen-image-optimizer'); ?></td>
                                        <td class="col-pro"><span class="nextgen-compare-tag-pro">✓ <?php esc_html_e('AVIF + WebP + Fallback', 'nextgen-image-optimizer'); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong><?php esc_html_e('Media Library Bulk Optimizer', 'nextgen-image-optimizer'); ?></strong>
                                            <span class="nextgen-subtext"><?php esc_html_e('One-click batch conversion & WP-Cron background worker', 'nextgen-image-optimizer'); ?></span>
                                        </td>
                                        <td class="col-free"><span class="nextgen-compare-tag-free">✓ <?php esc_html_e('Unlimited Images', 'nextgen-image-optimizer'); ?></span></td>
                                        <td class="col-pro"><span class="nextgen-compare-tag-pro">✓ <?php esc_html_e('Unlimited Images', 'nextgen-image-optimizer'); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong><?php esc_html_e('Quality Visualizer Split-View', 'nextgen-image-optimizer'); ?></strong>
                                            <span class="nextgen-subtext"><?php esc_html_e('Interactive before-and-after image quality comparison tool', 'nextgen-image-optimizer'); ?></span>
                                        </td>
                                        <td class="col-free"><?php esc_html_e('WebP vs Original', 'nextgen-image-optimizer'); ?></td>
                                        <td class="col-pro"><span class="nextgen-compare-tag-pro">✓ <?php esc_html_e('AVIF & WebP Visualizer', 'nextgen-image-optimizer'); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong><?php esc_html_e('Updates & Support', 'nextgen-image-optimizer'); ?></strong>
                                            <span class="nextgen-subtext"><?php esc_html_e('Plugin updates, technical assistance, and customer support', 'nextgen-image-optimizer'); ?></span>
                                        </td>
                                        <td class="col-free"><?php esc_html_e('Community Support', 'nextgen-image-optimizer'); ?></td>
                                        <td class="col-pro"><span class="nextgen-compare-tag-pro">✓ <?php esc_html_e('Priority Support & Updates', 'nextgen-image-optimizer'); ?></span></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="nextgen-compare-summary">
                                <div class="nextgen-summary-box-free">
                                    <strong><?php esc_html_e('Free Edition:', 'hridyaa-image-compressor-and-optimizer'); ?></strong>
                                    <?php esc_html_e('Optimizes your WordPress site with WebP compression, significantly reducing image file sizes and improving page load speeds for all visitors.', 'hridyaa-image-compressor-and-optimizer'); ?>
                                </div>
                                <div class="nextgen-summary-box-pro">
                                    <strong><?php esc_html_e('Hridyaa Image Compressor Pro:', 'hridyaa-image-compressor-and-optimizer'); ?></strong>
                                    <?php esc_html_e('Adds cutting-edge AVIF compression to make images even lighter, saving more server bandwidth and helping improve page-load performance.', 'hridyaa-image-compressor-and-optimizer'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Grid Card -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-admin-generic"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Quick Actions', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Common shortcuts for image optimization, configuration, and diagnostics.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <div class="nextgen-actions-grid">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-settings')); ?>" class="nextgen-action-tile">
                                <span class="dashicons dashicons-controls-play nextgen-tile-icon"></span>
                                <strong><?php esc_html_e('Run Bulk Optimization', 'nextgen-image-optimizer'); ?></strong>
                                <span><?php esc_html_e('Optimize existing media library files', 'nextgen-image-optimizer'); ?></span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-settings')); ?>" class="nextgen-action-tile">
                                <span class="dashicons dashicons-admin-settings nextgen-tile-icon"></span>
                                <strong><?php esc_html_e('Configure Quality Settings', 'nextgen-image-optimizer'); ?></strong>
                                <span><?php esc_html_e('Adjust compression presets and delivery', 'nextgen-image-optimizer'); ?></span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-visualizer')); ?>" class="nextgen-action-tile">
                                <span class="dashicons dashicons-visibility nextgen-tile-icon"></span>
                                <strong><?php esc_html_e('Quality Visualizer', 'nextgen-image-optimizer'); ?></strong>
                                <span><?php esc_html_e('Compare original vs optimized in split-view', 'nextgen-image-optimizer'); ?></span>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-diagnostics')); ?>" class="nextgen-action-tile">
                                <span class="dashicons dashicons-dashboard nextgen-tile-icon"></span>
                                <strong><?php esc_html_e('System Diagnostics', 'nextgen-image-optimizer'); ?></strong>
                                <span><?php esc_html_e('Check PHP, GD, Imagick, and WebP support', 'nextgen-image-optimizer'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="nextgen-col-sidebar">
                    <!-- System Status Card -->
                    <div class="nextgen-card">
                        <h3 class="nextgen-sidebar-heading">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php esc_html_e('System Status', 'nextgen-image-optimizer'); ?>
                        </h3>
                        <div class="nextgen-status-list">
                            <div class="nextgen-status-item">
                                <span><?php esc_html_e('PHP Version', 'nextgen-image-optimizer'); ?></span>
                                <strong>PHP <?php echo esc_html(PHP_VERSION); ?></strong>
                            </div>
                            <div class="nextgen-status-item">
                                <span><?php esc_html_e('GD WebP Engine', 'nextgen-image-optimizer'); ?></span>
                                <span>
                                    <?php if (!empty($caps['gd']['webp_encode'])): ?>
                                        <span class="nextgen-badge nextgen-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Supported', 'nextgen-image-optimizer'); ?></span>
                                    <?php else: ?>
                                        <span class="nextgen-badge nextgen-badge-danger"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Unsupported', 'nextgen-image-optimizer'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="nextgen-status-item">
                                <span><?php esc_html_e('ImageMagick Engine', 'nextgen-image-optimizer'); ?></span>
                                <span>
                                    <?php if (!empty($caps['imagick']['installed'])): ?>
                                        <span class="nextgen-badge nextgen-badge-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Supported', 'nextgen-image-optimizer'); ?></span>
                                    <?php else: ?>
                                        <span class="nextgen-badge nextgen-badge-neutral"><span class="dashicons dashicons-minus"></span> <?php esc_html_e('Not Loaded', 'nextgen-image-optimizer'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="nextgen-status-item">
                                <span><?php esc_html_e('Memory Limit', 'nextgen-image-optimizer'); ?></span>
                                <strong><?php echo esc_html(ini_get('memory_limit') ?: 'N/A'); ?></strong>
                            </div>
                            <div class="nextgen-status-item">
                                <span><?php esc_html_e('Upload Max Size', 'nextgen-image-optimizer'); ?></span>
                                <strong><?php echo esc_html(ini_get('upload_max_filesize') ?: 'N/A'); ?></strong>
                            </div>
                        </div>
                        <div style="margin-top: 18px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-diagnostics')); ?>" class="nextgen-btn nextgen-btn-secondary nextgen-btn-block">
                                <span class="dashicons dashicons-dashboard"></span>
                                <?php esc_html_e('Run Diagnostics Audit', 'nextgen-image-optimizer'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Pro Upsell Card -->
                    <div class="nextgen-card nextgen-card-pro-upsell">
                        <div class="nextgen-pro-badge"><?php esc_html_e('HRIDYAA PRO', 'hridyaa-image-compressor-and-optimizer'); ?></div>
                        <h3 class="nextgen-pro-title"><?php esc_html_e('Make Your Site Even Faster', 'hridyaa-image-compressor-and-optimizer'); ?></h3>
                        <p class="nextgen-pro-benefit-lead"><?php esc_html_e('Reduce image file sizes by up to 35–40% beyond WebP with AVIF compression.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                        <ul class="nextgen-pro-features">
                            <li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Smaller Image Files: AVIF delivers substantially smaller files than WebP at comparable visual quality.', 'nextgen-image-optimizer'); ?></li>
                            <li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Faster Page Loads: Lighter images help improve mobile and desktop speed.', 'nextgen-image-optimizer'); ?></li>
                            <li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Smart Delivery: Automatically serves an efficient image format supported by each browser.', 'nextgen-image-optimizer'); ?></li>
                            <li><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Priority Support: Direct developer updates and assistance.', 'nextgen-image-optimizer'); ?></li>
                        </ul>
                        <div class="nextgen-pro-explainer-box">
                            <strong><?php esc_html_e('Why AVIF?', 'nextgen-image-optimizer'); ?></strong> <?php esc_html_e('AVIF is a modern image format designed to deliver excellent image quality at significantly smaller file sizes than WebP and JPEG.', 'nextgen-image-optimizer'); ?>
                        </div>
                        <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-btn nextgen-btn-pro nextgen-btn-block nextgen-btn-lg">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('Upgrade to Pro Now ★', 'nextgen-image-optimizer'); ?>
                        </a>
                        <p class="nextgen-pro-disclaimer" style="text-align: center;">
                            <?php esc_html_e('*Potential savings compared to WebP at equivalent visual quality. Actual savings vary by image type, dimensions, quality preset, and original format.', 'nextgen-image-optimizer'); ?>
                        </p>
                        <div style="text-align: center; margin-top: 10px;">
                            <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-pro-learn-more">
                                <?php esc_html_e('Learn more about Pro features & pricing →', 'nextgen-image-optimizer'); ?>
                            </a>
                        </div>
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

<?php
/**
 * Admin Settings & Bulk Tools View.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

use NextGen\Core\Config;
use NextGen\Storage\MetadataManager;
use NextGen\Support\SystemDetector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingsPage {

    private Config $config;
    private SystemDetector $detector;

    public function __construct(Config $config, SystemDetector $detector) {
        $this->config = $config;
        $this->detector = $detector;
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'nextgen-image-optimizer'));
        }

        $capabilities = $this->detector->getCapabilities();
        $stats = MetadataManager::getStats();
        $options = $this->config->getOptions();
        $activePreset = QualityPresetManager::getActivePreset();

        $savedBytesFormatted = size_format($stats['saved_bytes'], 2);
        ?>
        <div class="wrap nextgen-admin-wrap">
            <?php AdminHeaderView::render(__('Settings & Bulk Tools', 'nextgen-image-optimizer'), __('Configure compression profiles, automatic conversion triggers, HTML5 delivery, and batch conversion.', 'nextgen-image-optimizer')); ?>

            <?php if (!empty($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully.', 'nextgen-image-optimizer'); ?></p>
                </div>
            <?php endif; ?>

            <div class="nextgen-grid-main-sidebar">
                <div class="nextgen-col-main">

                    <!-- Section 1: Optimization Settings Form -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-admin-settings"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Optimization Settings', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Configure image encoding quality, compression presets, and automatic optimization triggers.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('nextgen_save_settings', 'nextgen_settings_nonce'); ?>
                            <input type="hidden" name="action" value="nextgen_save_settings_action" />

                            <table class="form-table nextgen-form-table" role="presentation">
                                <?php
                                $isAvifEntitled = \NextGen\Core\Features::isAvifEnabled();
                                $currentFormat = $options['optimization_format'] ?? ($isAvifEntitled ? 'avif_webp' : 'webp');
                                if (!$isAvifEntitled) {
                                    $currentFormat = 'webp';
                                }
                                ?>
                                <tr id="nextgen-format-section">
                                    <th scope="row">
                                        <label><strong><?php esc_html_e('Image Format / Mode', 'nextgen-image-optimizer'); ?></strong></label>
                                    </th>
                                    <td>
                                        <div class="nextgen-preset-selector nextgen-format-selector">
                                            <label class="nextgen-radio-card <?php echo $currentFormat === 'webp' ? 'active' : ''; ?>">
                                                <input type="radio" name="optimization_format" value="webp" <?php checked($currentFormat, 'webp'); ?>>
                                                <div class="nextgen-radio-card-header">
                                                    <strong><?php esc_html_e('WebP Format', 'nextgen-image-optimizer'); ?></strong>
                                                    <span class="nextgen-badge-free"><?php esc_html_e('Included', 'nextgen-image-optimizer'); ?></span>
                                                </div>
                                                <span><?php esc_html_e('Creates standard WebP derivatives for fast loading with 96%+ browser compatibility.', 'nextgen-image-optimizer'); ?></span>
                                            </label>

                                            <label class="nextgen-radio-card <?php echo $currentFormat === 'avif_webp' ? 'active' : ''; ?> <?php echo !$isAvifEntitled ? 'nextgen-card-disabled' : ''; ?>">
                                                <input type="radio" name="optimization_format" value="avif_webp" <?php checked($currentFormat, 'avif_webp'); ?> <?php echo !$isAvifEntitled ? 'disabled' : ''; ?>>
                                                <div class="nextgen-radio-card-header">
                                                    <strong><?php esc_html_e('AVIF + WebP Fallback', 'nextgen-image-optimizer'); ?></strong>
                                                    <?php if ($isAvifEntitled): ?>
                                                        <span class="nextgen-badge-recommended"><?php esc_html_e('Recommended', 'nextgen-image-optimizer'); ?></span>
                                                    <?php else: ?>
                                                        <span class="nextgen-badge-pro"><?php esc_html_e('PRO', 'nextgen-image-optimizer'); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span><?php esc_html_e('Recommended — creates smaller AVIF images for supported browsers while keeping WebP and original fallbacks for universal compatibility.', 'nextgen-image-optimizer'); ?></span>
                                            </label>

                                            <label class="nextgen-radio-card <?php echo $currentFormat === 'avif' ? 'active' : ''; ?> <?php echo !$isAvifEntitled ? 'nextgen-card-disabled' : ''; ?>">
                                                <input type="radio" name="optimization_format" value="avif" <?php checked($currentFormat, 'avif'); ?> <?php echo !$isAvifEntitled ? 'disabled' : ''; ?>>
                                                <div class="nextgen-radio-card-header">
                                                    <strong><?php esc_html_e('AVIF Only', 'nextgen-image-optimizer'); ?></strong>
                                                    <span class="nextgen-badge-pro"><?php esc_html_e('PRO', 'nextgen-image-optimizer'); ?></span>
                                                </div>
                                                <span><?php esc_html_e('Generates next-generation AVIF images for maximum compression efficiency on ultra-modern devices.', 'nextgen-image-optimizer'); ?></span>
                                            </label>
                                        </div>
                                        <?php if (!$isAvifEntitled): ?>
                                            <p class="description" style="margin-top: 8px;">
                                                <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: -2px; color: #d97706;"></span>
                                                <?php esc_html_e('AVIF optimization is a Pro feature. Enter your license key below or on the Dashboard to unlock.', 'nextgen-image-optimizer'); ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="nextgen_preset"><strong><?php esc_html_e('Compression Preset', 'nextgen-image-optimizer'); ?></strong></label>
                                    </th>
                                    <td>
                                        <div class="nextgen-preset-selector">
                                            <label class="nextgen-radio-card <?php echo $activePreset === 'high' ? 'active' : ''; ?>">
                                                <input type="radio" name="nextgen_preset" value="high" <?php checked($activePreset, 'high'); ?>>
                                                <strong><?php esc_html_e('High Quality', 'nextgen-image-optimizer'); ?></strong>
                                                <span><?php esc_html_e('WebP Quality: 90. Best visual fidelity with light compression.', 'nextgen-image-optimizer'); ?></span>
                                            </label>
                                            <label class="nextgen-radio-card <?php echo $activePreset === 'balanced' ? 'active' : ''; ?>">
                                                <input type="radio" name="nextgen_preset" value="balanced" <?php checked($activePreset, 'balanced'); ?>>
                                                <strong><?php esc_html_e('Balanced (Recommended)', 'nextgen-image-optimizer'); ?></strong>
                                                <span><?php esc_html_e('WebP Quality: 82. Optimal balance of visual clarity and file size.', 'nextgen-image-optimizer'); ?></span>
                                            </label>
                                            <label class="nextgen-radio-card <?php echo $activePreset === 'aggressive' ? 'active' : ''; ?>">
                                                <input type="radio" name="nextgen_preset" value="aggressive" <?php checked($activePreset, 'aggressive'); ?>>
                                                <strong><?php esc_html_e('Aggressive', 'nextgen-image-optimizer'); ?></strong>
                                                <span><?php esc_html_e('WebP Quality: 75. Maximum compression savings for fast mobile delivery.', 'nextgen-image-optimizer'); ?></span>
                                            </label>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="webp_quality"><?php esc_html_e('WebP Quality Slider', 'nextgen-image-optimizer'); ?></label>
                                    </th>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 15px;">
                                            <input type="range" id="webp_quality" name="webp_quality" min="10" max="100" value="<?php echo esc_attr($options['webp_quality']); ?>" oninput="document.getElementById('webp-quality-val').innerText = this.value" style="width: 240px;">
                                            <span id="webp-quality-val" class="nextgen-range-val"><?php echo esc_html($options['webp_quality']); ?></span>%
                                        </div>
                                        <p class="description"><?php esc_html_e('Default: 82. Higher quality increases visual fidelity; lower quality yields smaller files.', 'nextgen-image-optimizer'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row"><?php esc_html_e('Automatic Optimization', 'nextgen-image-optimizer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="auto_convert_uploads" value="1" <?php checked($options['auto_convert_uploads']); ?>>
                                            <strong><?php esc_html_e('Auto-convert on upload', 'nextgen-image-optimizer'); ?></strong>
                                        </label>
                                        <p class="description"><?php esc_html_e('Automatically convert newly uploaded images to WebP upon uploading to Media Library.', 'nextgen-image-optimizer'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row"><?php esc_html_e('Thumbnail Derivatives', 'nextgen-image-optimizer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="convert_thumbnails" value="1" <?php checked($options['convert_thumbnails']); ?>>
                                            <strong><?php esc_html_e('Optimize intermediate thumbnail sizes', 'nextgen-image-optimizer'); ?></strong>
                                        </label>
                                        <p class="description"><?php esc_html_e('Generates WebP files for all registered thumbnail sizes (thumbnail, medium, large, etc.).', 'nextgen-image-optimizer'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row"><?php esc_html_e('Frontend Delivery', 'nextgen-image-optimizer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="delivery_enabled" value="1" <?php checked($options['delivery_enabled']); ?>>
                                            <strong><?php esc_html_e('Enable HTML5 Picture tag delivery', 'nextgen-image-optimizer'); ?></strong>
                                        </label>
                                        <p class="description"><?php esc_html_e('Delivers modern image formats with fallback HTML5 <picture> tags for older browsers.', 'nextgen-image-optimizer'); ?></p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row"><?php esc_html_e('PNG Optimization', 'nextgen-image-optimizer'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="png_lossless" value="1" <?php checked($options['png_lossless']); ?>>
                                            <strong><?php esc_html_e('Lossless PNG compression', 'nextgen-image-optimizer'); ?></strong>
                                        </label>
                                        <p class="description"><?php esc_html_e('Preserves exact pixel fidelity for PNG illustrations, logos, and UI graphics.', 'nextgen-image-optimizer'); ?></p>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                                <button type="submit" class="nextgen-btn nextgen-btn-primary">
                                    <span class="dashicons dashicons-saved"></span>
                                    <?php esc_html_e('Save Settings', 'nextgen-image-optimizer'); ?>
                                </button>
                            </div>
                        </form>

                        <?php do_action('nextgen_after_settings_form'); ?>
                    </div>

                    <!-- Section 2: Interactive Bulk Media Library Converter -->
                    <div class="nextgen-card nextgen-bulk-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-controls-play"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Bulk Media Library Converter', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Convert all existing Media Library images to WebP. Your original images are never deleted or modified.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <?php if (!$capabilities['webp_supported'] && !$capabilities['avif_supported']) : ?>
                            <div class="notice notice-error inline">
                                <p><strong><?php esc_html_e('Modern Formats Unavailable:', 'nextgen-image-optimizer'); ?></strong> <?php esc_html_e('Your server does not have a supported image library (GD or Imagick with WebP encoding).', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        <?php else : ?>
                            <div class="nextgen-bulk-controls">
                                <div class="nextgen-bulk-options-bar">
                                    <label class="nextgen-checkbox-label">
                                        <input type="checkbox" id="bulk-include-failed">
                                        <span><?php esc_html_e('Retry previously failed images', 'nextgen-image-optimizer'); ?></span>
                                    </label>
                                    <label class="nextgen-select-label">
                                        <span><?php esc_html_e('Throttle Delay:', 'nextgen-image-optimizer'); ?></span>
                                        <select id="bulk-throttle-delay" class="nextgen-select">
                                            <option value="0"><?php esc_html_e('No Delay (Fastest)', 'nextgen-image-optimizer'); ?></option>
                                            <option value="200" selected><?php esc_html_e('200ms (Balanced)', 'nextgen-image-optimizer'); ?></option>
                                            <option value="500"><?php esc_html_e('500ms (Low Server Load)', 'nextgen-image-optimizer'); ?></option>
                                        </select>
                                    </label>
                                </div>

                                <div class="nextgen-progress-container" id="bulk-progress-container" style="display: none;">
                                    <div class="nextgen-progress-bar">
                                        <div class="nextgen-progress-fill" id="bulk-progress-fill" style="width: 0%;"></div>
                                    </div>
                                    <div class="nextgen-progress-status">
                                        <span id="bulk-progress-text">0%</span>
                                        <span id="bulk-progress-counts">0 / 0 images</span>
                                    </div>
                                </div>

                                <div class="nextgen-btn-group" style="margin-top: 15px;">
                                    <button type="button" class="nextgen-btn nextgen-btn-primary nextgen-btn-lg" id="btn-start-bulk">
                                        <span class="dashicons dashicons-controls-play"></span>
                                        <?php esc_html_e('Start Bulk Optimization', 'nextgen-image-optimizer'); ?>
                                    </button>
                                    <button type="button" class="nextgen-btn nextgen-btn-secondary nextgen-btn-lg" id="btn-pause-bulk" style="display: none;">
                                        <span class="dashicons dashicons-controls-pause"></span>
                                        <?php esc_html_e('Pause', 'nextgen-image-optimizer'); ?>
                                    </button>
                                    <button type="button" class="nextgen-btn nextgen-btn-secondary" id="btn-reset-bulk">
                                        <span class="dashicons dashicons-image-rotate"></span>
                                        <?php esc_html_e('Reset Stats', 'nextgen-image-optimizer'); ?>
                                    </button>
                                </div>

                                <div class="nextgen-bulk-log" id="bulk-log" style="display: none;">
                                    <ul id="bulk-log-list"></ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Section 3: Headless WP-Cron Background Worker -->
                    <div class="nextgen-card">
                        <div class="nextgen-card-header">
                            <div class="nextgen-card-header-icon"><span class="dashicons dashicons-clock"></span></div>
                            <div>
                                <h2 class="nextgen-card-title"><?php esc_html_e('Headless Background WP-Cron Optimizer', 'nextgen-image-optimizer'); ?></h2>
                                <p class="nextgen-card-desc"><?php esc_html_e('Run optimization asynchronously in the background via WP-Cron without keeping this browser tab open.', 'nextgen-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <div class="nextgen-cron-controls">
                            <p class="nextgen-card-text">
                                <?php esc_html_e('The background worker runs in small time-budgeted chunks during WordPress cron executions. It automatically acquires locks to prevent concurrency issues and throttles execution safely.', 'nextgen-image-optimizer'); ?>
                            </p>
                            <div class="nextgen-btn-group" style="margin-top: 12px;">
                                <button type="button" id="btn-cron-start" class="nextgen-btn nextgen-btn-secondary">
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php esc_html_e('Start Background WP-Cron Worker', 'nextgen-image-optimizer'); ?>
                                </button>
                                <button type="button" id="btn-cron-cancel" class="nextgen-btn nextgen-btn-secondary">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Cancel Background Worker', 'nextgen-image-optimizer'); ?>
                                </button>
                                <span id="cron-status-indicator" class="nextgen-badge nextgen-badge-neutral"><?php esc_html_e('Worker Idle', 'nextgen-image-optimizer'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Column -->
                <div class="nextgen-col-sidebar">
                    <!-- Diagnostics Badge Card -->
                    <div class="nextgen-card">
                        <h3 class="nextgen-sidebar-heading">
                            <span class="dashicons dashicons-dashboard"></span>
                            <?php esc_html_e('System Diagnostics', 'nextgen-image-optimizer'); ?>
                        </h3>

                        <div class="nextgen-status-badge <?php echo $capabilities['webp_supported'] ? 'badge-success' : 'badge-danger'; ?>" style="margin-bottom: 10px; width: 100%; box-sizing: border-box;">
                            <?php if ($capabilities['webp_supported']) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php printf(esc_html__('WebP Ready (%s)', 'nextgen-image-optimizer'), esc_html(strtoupper($capabilities['primary_webp_engine']))); ?>
                            <?php else : ?>
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('WebP Not Supported', 'nextgen-image-optimizer'); ?>
                            <?php endif; ?>
                        </div>

                        <table class="nextgen-diag-table">
                            <tr>
                                <td><?php esc_html_e('PHP Version:', 'nextgen-image-optimizer'); ?></td>
                                <td><code><?php echo esc_html($capabilities['php_version']); ?></code></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('GD Extension:', 'nextgen-image-optimizer'); ?></td>
                                <td>
                                    <?php if ($capabilities['gd']['installed']) : ?>
                                        <span class="status-ok">✔ <?php echo esc_html($capabilities['gd']['status']); ?></span>
                                    <?php else : ?>
                                        <span class="status-missing">✖ <?php esc_html_e('Not installed', 'nextgen-image-optimizer'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('ImageMagick:', 'nextgen-image-optimizer'); ?></td>
                                <td>
                                    <?php if ($capabilities['imagick']['installed']) : ?>
                                        <span class="status-ok">✔ <?php echo esc_html($capabilities['imagick']['status']); ?></span>
                                    <?php else : ?>
                                        <span class="status-missing">✖ <?php esc_html_e('Not installed', 'nextgen-image-optimizer'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Memory Limit:', 'nextgen-image-optimizer'); ?></td>
                                <td><code><?php echo esc_html($capabilities['memory_limit']['formatted']); ?></code></td>
                            </tr>
                        </table>

                        <div style="margin-top: 15px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nextgen-diagnostics')); ?>" class="nextgen-btn nextgen-btn-secondary nextgen-btn-block">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php esc_html_e('View Detailed Report', 'nextgen-image-optimizer'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Original Safety Card -->
                    <div class="nextgen-card">
                        <h3 class="nextgen-sidebar-heading">
                            <span class="dashicons dashicons-shield"></span>
                            <?php esc_html_e('Non-Destructive Guarantee', 'hridyaa-image-compressor-and-optimizer'); ?>
                        </h3>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0;">
                            <?php esc_html_e('Your original JPEG, PNG, and GIF images are permanently preserved. The plugin creates separate companion files alongside your originals.', 'hridyaa-image-compressor-and-optimizer'); ?>
                        </p>
                    </div>

                    <!-- Pro Upsell Card -->
                    <div class="nextgen-card nextgen-card-pro-upsell">
                        <div class="nextgen-pro-badge"><?php esc_html_e('HRIDYAA PRO', 'hridyaa-image-compressor-and-optimizer'); ?></div>
                        <h3 class="nextgen-pro-title"><?php esc_html_e('Make Your Site Even Faster', 'hridyaa-image-compressor-and-optimizer'); ?></h3>
                        <p class="nextgen-pro-desc"><?php esc_html_e('Make your images up to 35–40% smaller than WebP* with next-generation AVIF compression, smart format delivery, and priority support.', 'hridyaa-image-compressor-and-optimizer'); ?></p>
                        <a href="<?php echo esc_url(AdminHeaderView::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-btn nextgen-btn-pro nextgen-btn-block">
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('Upgrade to Pro ★', 'hridyaa-image-compressor-and-optimizer'); ?>
                        </a>
                    </div>

                    <?php if (!\NextGen\Core\Features::isAvifEnabled()) : ?>
                        <!-- Pro License Activation Card for Free Users -->
                        <div class="nextgen-card nextgen-card-license" style="margin-top: 20px;">
                            <h3 class="nextgen-sidebar-heading">
                                <span class="dashicons dashicons-admin-network"></span>
                                <?php esc_html_e('Activate Pro License', 'hridyaa-image-compressor-and-optimizer'); ?>
                            </h3>
                            <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">
                                <?php esc_html_e('Already purchased Hridyaa Image Compressor and Optimizer Pro? Enter your license key below for instant one-click activation.', 'hridyaa-image-compressor-and-optimizer'); ?>
                            </p>
                            <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="nextgen_pro_save_license">
                                <input type="hidden" name="nextgen_redirect_page" value="nextgen-settings">
                                <?php wp_nonce_field('nextgen_pro_license_action', 'nextgen_pro_license_nonce'); ?>
                                <div style="margin-bottom: 10px;">
                                    <input type="text" id="nextgen_license_key_input" name="nextgen_license_key" placeholder="NGPRO-XXXX-XXXX-XXXX-XXXX" style="width: 100%; box-sizing: border-box; font-family: monospace;" required>
                                </div>
                                <button type="submit" id="btn-activate-pro" name="nextgen_pro_activate" value="1" class="nextgen-btn nextgen-btn-primary nextgen-btn-block">
                                    <span class="dashicons dashicons-unlock"></span>
                                    <?php esc_html_e('Activate Pro', 'hridyaa-image-compressor-and-optimizer'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

<?php
/**
 * Admin Shared Header Component for NextGen Image Optimizer.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminHeaderView {

    public const PRO_URL = 'https://ankitrawat.com/products/hridyaa-image-compressor-and-optimizer/';

    /**
     * Render the top-level product header.
     *
     * @param string $activeTitle Page title for the current screen.
     * @param string $subtitle Optional subtitle / description.
     * @return void
     */
    public static function render(string $activeTitle = '', string $subtitle = ''): void {
        $version = defined('NEXTGEN_VERSION') ? NEXTGEN_VERSION : '1.2.1';
        $helpUrl = admin_url('admin.php?page=nextgen-help');
        ?>
        <div class="nextgen-header-wrap">
            <div class="nextgen-header-main">
                <div class="nextgen-brand">
                    <div class="nextgen-logo-icon">
                        <span class="dashicons dashicons-format-image"></span>
                    </div>
                    <div class="nextgen-brand-meta">
                        <div class="nextgen-brand-title-row">
                            <span class="nextgen-brand-title"><?php esc_html_e('Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'); ?></span>
                            <span class="nextgen-version-pill">v<?php echo esc_html($version); ?></span>
                            <span class="nextgen-edition-badge nextgen-edition-free"><?php esc_html_e('Free Edition', 'hridyaa-image-compressor-and-optimizer'); ?></span>
                        </div>
                        <?php if (!empty($subtitle)): ?>
                            <p class="nextgen-header-subtitle"><?php echo esc_html($subtitle); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="nextgen-header-actions">
                    <a href="<?php echo esc_url($helpUrl); ?>" class="nextgen-btn nextgen-btn-secondary">
                        <span class="dashicons dashicons-editor-help"></span>
                        <?php esc_html_e('Help & Support', 'image-optimizer-by-ankit-rawat'); ?>
                    </a>
                    <a href="<?php echo esc_url(self::PRO_URL); ?>" target="_blank" rel="noopener noreferrer" class="nextgen-btn nextgen-btn-pro">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php esc_html_e('Upgrade to Pro', 'image-optimizer-by-ankit-rawat'); ?>
                    </a>
                </div>
            </div>
            <?php if (!empty($activeTitle)): ?>
                <div class="nextgen-page-title-bar">
                    <h1 class="nextgen-page-heading"><?php echo esc_html($activeTitle); ?></h1>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

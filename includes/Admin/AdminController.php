<?php
/**
 * Admin Menu and Controller.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

use NextGen\Converter\PreviewGenerator;
use NextGen\Core\Config;
use NextGen\Queue\QueueManager;
use NextGen\Support\SystemDetector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdminController {

    public const MENU_SLUG = 'nextgen-image-optimizer';

    private Config $config;
    private SystemDetector $detector;
    private SettingsPage $settingsPage;

    public function __construct(Config $config, SystemDetector $detector, SettingsPage $settingsPage) {
        $this->config = $config;
        $this->detector = $detector;
        $this->settingsPage = $settingsPage;
    }

    public function registerHooks(): void {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_init', [$this, 'handleSettingsPost']);
        add_action('admin_post_nextgen_save_settings_action', [$this, 'handleSettingsPost']);
        add_action('admin_post_nextgen_tool_reset_metadata', [$this, 'handleToolResetMetadata']);
        add_action('admin_post_nextgen_tool_clear_failed', [$this, 'handleToolClearFailed']);
        add_action('admin_post_nextgen_tool_purge_previews', [$this, 'handleToolPurgePreviews']);
        add_action('admin_post_nextgen_pro_save_license', [$this, 'handleProActivationAction']);
        add_action('admin_notices', [$this, 'renderAdminNotices']);

        add_action('wp_ajax_nextgen_retry_failed', [$this, 'handleAjaxRetryFailed']);
        add_action('wp_ajax_nextgen_clear_failed_queue', [$this, 'handleAjaxClearFailedQueue']);
        add_action('wp_ajax_nextgen_generate_preview', [$this, 'handleAjaxGeneratePreview']);
        add_action('wp_ajax_nextgen_bg_start', [$this, 'handleAjaxBgStart']);
        add_action('wp_ajax_nextgen_bg_pause', [$this, 'handleAjaxBgPause']);
        add_action('wp_ajax_nextgen_bg_resume', [$this, 'handleAjaxBgResume']);
        add_action('wp_ajax_nextgen_bg_cancel', [$this, 'handleAjaxBgCancel']);
        add_action('wp_ajax_nextgen_bg_status', [$this, 'handleAjaxBgStatus']);
        add_action('nextgen_cron_batch_worker', ['\NextGen\Batch\BackgroundBatchWorker', 'processCronTick']);
    }

    public function handleAjaxGeneratePreview(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        $attachmentId = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'webp';
        $preset = isset($_POST['preset']) ? sanitize_text_field($_POST['preset']) : 'balanced';

        $result = PreviewGenerator::generatePreview($attachmentId, $format, $preset);
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            $code = $result['error'] === 'pro_required' ? 403 : 400;
            wp_send_json_error($result, $code);
        }
    }

    public function handleAjaxBgStart(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'all';
        $preset = isset($_POST['preset']) ? sanitize_text_field($_POST['preset']) : 'balanced';

        $started = \NextGen\Batch\BackgroundBatchWorker::start($format, $preset);
        if ($started) {
            wp_send_json_success(['message' => 'Background optimization started.', 'state' => \NextGen\Batch\BackgroundBatchWorker::getState()]);
        } else {
            wp_send_json_error(['message' => 'Background optimization is already running.', 'state' => \NextGen\Batch\BackgroundBatchWorker::getState()], 400);
        }
    }

    public function handleAjaxBgPause(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        \NextGen\Batch\BackgroundBatchWorker::pause();
        wp_send_json_success(['message' => 'Background optimization paused.', 'state' => \NextGen\Batch\BackgroundBatchWorker::getState()]);
    }

    public function handleAjaxBgResume(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        \NextGen\Batch\BackgroundBatchWorker::resume();
        wp_send_json_success(['message' => 'Background optimization resumed.', 'state' => \NextGen\Batch\BackgroundBatchWorker::getState()]);
    }

    public function handleAjaxBgCancel(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        \NextGen\Batch\BackgroundBatchWorker::cancel();
        wp_send_json_success(['message' => 'Background optimization cancelled.', 'state' => \NextGen\Batch\BackgroundBatchWorker::getState()]);
    }

    public function handleAjaxBgStatus(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        wp_send_json_success(['state' => \NextGen\Batch\BackgroundBatchWorker::getState()]);
    }

    public function handleAjaxRetryFailed(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        $items = FailedQueueManager::getFailedItems();
        $retried = count($items);
        FailedQueueManager::clearQueue();

        wp_send_json_success(['message' => 'Queue cleared for retry.', 'retried' => $retried]);
    }

    public function handleAjaxClearFailedQueue(): void {
        check_ajax_referer('nextgen_bulk_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        FailedQueueManager::clearQueue();
        wp_send_json_success(['message' => 'Failed queue cleared.']);
    }

    public function registerAdminMenu(): void {
        $iconSvg = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" /></svg>'
        );

        // Top-Level Main Menu
        add_menu_page(
            __('Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            __('Image Compressor', 'hridyaa-image-compressor-and-optimizer'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderDashboardPage'],
            $iconSvg,
            65
        );

        // 1. Dashboard Submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard ‹ Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            __('Dashboard', 'hridyaa-image-compressor-and-optimizer'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderDashboardPage']
        );

        // 2. Settings & Bulk Tools
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings & Bulk Tools ‹ Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            __('Settings & Bulk Tools', 'hridyaa-image-compressor-and-optimizer'),
            'manage_options',
            'nextgen-settings',
            [$this, 'renderSettingsPage']
        );

        // 3. System Diagnostics
        add_submenu_page(
            self::MENU_SLUG,
            __('System Diagnostics ‹ Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            __('System Diagnostics', 'hridyaa-image-compressor-and-optimizer'),
            'manage_options',
            'nextgen-diagnostics',
            [$this, 'renderDiagnosticsPage']
        );

        // 4. Quality Visualizer
        add_submenu_page(
            self::MENU_SLUG,
            __('Quality Visualizer ‹ Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            __('Quality Visualizer', 'hridyaa-image-compressor-and-optimizer'),
            'manage_options',
            'nextgen-visualizer',
            [$this, 'renderVisualizerPage']
        );

        // 5. Reports
        add_submenu_page(
            self::MENU_SLUG,
            __('Reports ‹ Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            __('Reports', 'hridyaa-image-compressor-and-optimizer'),
            'manage_options',
            'nextgen-reports',
            [$this, 'renderReportsPage']
        );

        // 6. Tools
        add_submenu_page(
            self::MENU_SLUG,
            __('Tools ‹ Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            __('Tools', 'hridyaa-image-compressor-and-optimizer'),
            'manage_options',
            'nextgen-tools',
            [$this, 'renderToolsPage']
        );

        // 7. Help & Support
        add_submenu_page(
            self::MENU_SLUG,
            __('Help & Support ‹ Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            __('Help & Support', 'hridyaa-image-compressor-and-optimizer'),
            'manage_options',
            'nextgen-help',
            [$this, 'renderHelpPage']
        );

        // 8. Upgrade to Pro
        add_submenu_page(
            self::MENU_SLUG,
            __('Upgrade to Pro ‹ Hridyaa Image Compressor and Optimizer', 'hridyaa-image-compressor-and-optimizer'),
            '<span style="color:#d97706;font-weight:600;">' . esc_html__('Upgrade to Pro ★', 'hridyaa-image-compressor-and-optimizer') . '</span>',
            'manage_options',
            'https://ankitrawat.com/products/hridyaa-image-compressor-and-optimizer/',
            '__return_null'
        );
    }

    public function renderDashboardPage(): void {
        // Handle backward compatibility for legacy ?page=nextgen-image-optimizer&tab=...
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        if ($tab === 'settings') {
            $this->renderSettingsPage();
            return;
        } elseif ($tab === 'diagnostics') {
            $this->renderDiagnosticsPage();
            return;
        } elseif ($tab === 'visualizer') {
            $this->renderVisualizerPage();
            return;
        } elseif ($tab === 'failed-queue' || $tab === 'reports') {
            $this->renderReportsPage();
            return;
        }

        try {
            AdminDashboardView::render($this->detector);
        } catch (\Throwable $e) {
            echo '<div style="background:#fff;color:#c00;padding:20px;margin:20px;border:2px solid #c00;font-family:monospace;">';
            echo '<h3>NextGen Dashboard Render Exception:</h3>';
            echo '<p><strong>' . esc_html($e->getMessage()) . '</strong> in ' . esc_html($e->getFile()) . ':' . (int) $e->getLine() . '</p>';
            echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
    }

    public function renderSettingsPage(): void {
        try {
            $this->settingsPage->render();
        } catch (\Throwable $e) {
            echo '<div style="background:#fff;color:#c00;padding:20px;margin:20px;border:2px solid #c00;font-family:monospace;">';
            echo '<h3>NextGen Settings Render Exception:</h3>';
            echo '<p><strong>' . esc_html($e->getMessage()) . '</strong> in ' . esc_html($e->getFile()) . ':' . (int) $e->getLine() . '</p>';
            echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
    }

    public function renderDiagnosticsPage(): void {
        DiagnosticsView::render($this->detector);
    }

    public function renderVisualizerPage(): void {
        ?>
        <div class="wrap nextgen-admin-wrap">
            <?php AdminHeaderView::render(__('Interactive Quality Visualizer', 'nextgen-image-optimizer'), __('Compare visual fidelity and compression savings directly on your own media files.', 'nextgen-image-optimizer')); ?>
            <div class="nextgen-card" style="padding: 0; overflow: hidden; border: 0; background: transparent; box-shadow: none;">
                <?php echo ComparisonSliderView::render(); ?>
            </div>
        </div>
        <?php
    }

    public function renderReportsPage(): void {
        ReportsView::render();
    }

    public function renderToolsPage(): void {
        ToolsView::render();
    }

    public function renderHelpPage(): void {
        HelpView::render($this->detector);
    }

    public function enqueueAdminAssets($hook = ''): void {
        $hookStr = is_string($hook) ? $hook : '';
        $pageStr = isset($_GET['page']) && is_string($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $isNextgenPage = (strpos($hookStr, 'nextgen') !== false) || (strpos($pageStr, 'nextgen') !== false);
        if (!$isNextgenPage) {
            return;
        }

        $pluginFile = defined('NEXTGEN_FILE') ? NEXTGEN_FILE : dirname(dirname(__FILE__)) . '/nextgen-image-optimizer.php';
        $cssUrl = plugins_url('assets/css/admin.css', $pluginFile);
        $jsUrl  = plugins_url('assets/js/admin.js', $pluginFile);
        $version = defined('NEXTGEN_VERSION') ? NEXTGEN_VERSION : '1.2.0';

        wp_enqueue_style(
            'nextgen-admin-css',
            $cssUrl,
            ['dashicons'],
            $version
        );

        wp_enqueue_script(
            'nextgen-admin-js',
            $jsUrl,
            ['jquery'],
            $version,
            true
        );

        wp_localize_script('nextgen-admin-js', 'nextgenOptimizer', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('nextgen_bulk_nonce'),
            'i18n'    => [
                'processing'       => __('Converting image...', 'nextgen-image-optimizer'),
                'paused'           => __('Bulk conversion paused.', 'nextgen-image-optimizer'),
                'complete'         => __('Bulk optimization complete!', 'nextgen-image-optimizer'),
                'error'            => __('An error occurred during conversion.', 'nextgen-image-optimizer'),
                'resetConfirm'     => __('Are you sure you want to reset all optimization status? This will allow re-converting all images.', 'nextgen-image-optimizer'),
                'fetchingQueue'    => __('Fetching queue...', 'nextgen-image-optimizer'),
                'allOptimized'     => __('All supported images in your Media Library are already optimized!', 'nextgen-image-optimizer'),
                'startBulk'        => __('Start Bulk Optimization', 'nextgen-image-optimizer'),
                'resumeBulk'       => __('Resume Bulk Optimization', 'nextgen-image-optimizer'),
                'startingBulk'     => __('Starting bulk optimization for %d images...', 'nextgen-image-optimizer'),
                'resumingBulk'     => __('Resuming bulk conversion...', 'nextgen-image-optimizer'),
                'pausedByUser'     => __('Conversion paused by user.', 'nextgen-image-optimizer'),
                'resetComplete'    => __('Reset complete! You can now re-convert your Media Library.', 'nextgen-image-optimizer'),
                'reportCopied'     => __('System report copied to clipboard!', 'nextgen-image-optimizer'),
                'copyFailed'       => __('Failed to copy to clipboard. Please copy manually.', 'nextgen-image-optimizer'),
                'converted'        => __('Converted', 'nextgen-image-optimizer'),
                'skipped'          => __('Skipped', 'nextgen-image-optimizer'),
                'failed'           => __('Failed', 'nextgen-image-optimizer'),
                'saved'            => __('Saved', 'nextgen-image-optimizer'),
            ],
        ]);
    }

    public function handleSettingsPost(): void {
        if (!isset($_POST['nextgen_settings_nonce'])) {
            return;
        }

        if (!check_admin_referer('nextgen_save_settings', 'nextgen_settings_nonce')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $preset = isset($_POST['nextgen_preset']) ? sanitize_text_field($_POST['nextgen_preset']) : QualityPresetManager::PRESET_BALANCED;
        $webpQuality = QualityPresetManager::getQuality('webp', $preset);
        $avifQuality = QualityPresetManager::getQuality('avif', $preset);

        if (isset($_POST['webp_quality'])) {
            $rawWebp = (int) $_POST['webp_quality'];
            if ($rawWebp >= 10 && $rawWebp <= 100) {
                $webpQuality = $rawWebp;
            }
        }

        update_option('nextgen_preset', $preset);
        update_option('nextgen_webp_quality', $webpQuality);
        update_option('nextgen_avif_quality', $avifQuality);

        $format = isset($_POST['optimization_format']) ? sanitize_text_field($_POST['optimization_format']) : 'webp';
        if ($format !== 'webp' && $format !== 'avif_webp' && $format !== 'avif') {
            $format = 'webp';
        }
        if (!\NextGen\Core\Features::isAvifEnabled()) {
            $format = 'webp';
        }

        $input = [
            'optimization_format'  => $format,
            'webp_quality'         => $webpQuality,
            'avif_quality'         => $avifQuality,
            'auto_convert_uploads' => !empty($_POST['auto_convert_uploads']),
            'convert_thumbnails'   => !empty($_POST['convert_thumbnails']),
            'delivery_enabled'     => !empty($_POST['delivery_enabled']),
            'png_lossless'         => !empty($_POST['png_lossless']),
            'keep_larger_converted'=> !empty($_POST['keep_larger_converted']),
        ];

        $this->config->updateOptions($input);

        wp_safe_redirect(add_query_arg(['page' => 'nextgen-settings', 'settings-updated' => 'true'], admin_url('admin.php')));
        exit;
    }

    public function handleToolResetMetadata(): void {
        if (!check_admin_referer('nextgen_tool_reset_metadata', 'nextgen_tool_nonce')) {
            wp_die(esc_html__('Security check failed.', 'nextgen-image-optimizer'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'nextgen-image-optimizer'));
        }

        $count = QueueManager::resetAllMetadata();
        $msg = sprintf(__('Conversion metadata reset for %d images.', 'nextgen-image-optimizer'), $count);
        wp_safe_redirect(add_query_arg(['page' => 'nextgen-tools', 'tool-executed' => urlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handleToolClearFailed(): void {
        if (!check_admin_referer('nextgen_tool_clear_failed', 'nextgen_tool_nonce')) {
            wp_die(esc_html__('Security check failed.', 'nextgen-image-optimizer'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'nextgen-image-optimizer'));
        }

        FailedQueueManager::clearQueue();
        $msg = __('Failed conversion error log cleared.', 'nextgen-image-optimizer');
        wp_safe_redirect(add_query_arg(['page' => 'nextgen-tools', 'tool-executed' => urlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handleToolPurgePreviews(): void {
        if (!check_admin_referer('nextgen_tool_purge_previews', 'nextgen_tool_nonce')) {
            wp_die(esc_html__('Security check failed.', 'nextgen-image-optimizer'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'nextgen-image-optimizer'));
        }

        PreviewGenerator::cleanOldPreviews(0);
        $msg = __('Quality Visualizer preview cache purged.', 'nextgen-image-optimizer');
        wp_safe_redirect(add_query_arg(['page' => 'nextgen-tools', 'tool-executed' => urlencode($msg)], admin_url('admin.php')));
        exit;
    }

    public function handleProActivationAction(): void {
        // If Pro plugin is already loaded and active, let ProAdminController handle it
        if (class_exists('NextGenPro\Admin\ProAdminController')) {
            return;
        }

        $redirectPage = 'nextgen-settings';
        if (isset($_POST['nextgen_redirect_page']) && is_string($_POST['nextgen_redirect_page'])) {
            $redirectPage = sanitize_key($_POST['nextgen_redirect_page']);
        }

        $settingsUrl = admin_url('admin.php?page=' . $redirectPage);

        // 1. Nonce validation
        if (!isset($_POST['nextgen_pro_license_nonce']) || !wp_verify_nonce($_POST['nextgen_pro_license_nonce'], 'nextgen_pro_license_action')) {
            $redirect = add_query_arg([
                'pro-status'  => 'error',
                'pro-message' => 'Security check failed. Please refresh the page and try again.',
            ], $settingsUrl);
            wp_safe_redirect($redirect);
            exit;
        }

        // 2. Capability check
        if (!current_user_can('manage_options')) {
            $redirect = add_query_arg([
                'pro-status'  => 'error',
                'pro-message' => 'You do not have sufficient permissions to manage Pro licenses.',
            ], $settingsUrl);
            wp_safe_redirect($redirect);
            exit;
        }

        $licenseKey = isset($_POST['nextgen_license_key']) ? sanitize_text_field(wp_unslash($_POST['nextgen_license_key'])) : '';
        if (empty($licenseKey)) {
            $redirect = add_query_arg([
                'pro-status'  => 'error',
                'pro-message' => 'Please enter a valid Pro license key.',
            ], $settingsUrl);
            wp_safe_redirect($redirect);
            exit;
        }

        try {
            require_once __DIR__ . '/ProInstaller.php';
            $installer = new ProInstaller();
            $result = $installer->activateAndInstall($licenseKey);

            if ($result['success']) {
                $redirect = add_query_arg([
                    'pro-status'  => 'activated',
                    'pro-message' => 'Hridyaa Image Compressor and Optimizer Pro activated successfully! AVIF and advanced optimization features are now active.',
                ], $settingsUrl);
            } else {
                $redirect = add_query_arg([
                    'pro-status'  => 'error',
                    'pro-message' => $result['message'],
                ], $settingsUrl);
            }
        } catch (\Throwable $e) {
            $redirect = add_query_arg([
                'pro-status'  => 'error',
                'pro-message' => 'An unexpected error occurred during Pro activation: ' . $e->getMessage(),
            ], $settingsUrl);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function renderAdminNotices(): void {
        if (!isset($_GET['page']) || strpos($_GET['page'], 'nextgen-') !== 0) {
            return;
        }

        if (isset($_GET['pro-status'])) {
            $status = sanitize_key($_GET['pro-status']);
            $rawMessage = isset($_GET['pro-message']) ? sanitize_text_field(wp_unslash($_GET['pro-message'])) : '';

            if ($status === 'activated') {
                $message = !empty($rawMessage) ? $rawMessage : __('Hridyaa Image Compressor and Optimizer Pro activated successfully! AVIF and advanced optimization features are now active.', 'hridyaa-image-compressor-and-optimizer');
                printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($message));
            } elseif ($status === 'error') {
                $message = !empty($rawMessage) ? $rawMessage : __('Pro license activation failed. Please check your license key and try again.', 'hridyaa-image-compressor-and-optimizer');
                printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($message));
            }
        }
    }
}

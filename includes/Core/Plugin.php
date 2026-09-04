<?php
/**
 * Main Plugin Kernel.
 *
 * @package NextGen\Core
 */

namespace NextGen\Core;

use NextGen\Admin\AdminController;
use NextGen\Admin\SettingsPage;
use NextGen\Converter\ConverterManager;
use NextGen\Delivery\PictureTagDelivery;
use NextGen\Image\AttachmentHandler;
use NextGen\Queue\AjaxBatchRunner;
use NextGen\Support\SystemDetector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    /**
     * Plugin instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    private Config $config;
    private SystemDetector $detector;
    private ConverterManager $converter;
    private AttachmentHandler $attachmentHandler;
    private PictureTagDelivery $delivery;
    private AjaxBatchRunner $ajaxRunner;
    private ?AdminController $adminController = null;
    private ?SettingsPage $settingsPage = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton.
     */
    private function __construct() {
        $this->config = new Config();
        $this->detector = new SystemDetector();
        $this->converter = new ConverterManager($this->config);
        $this->attachmentHandler = new AttachmentHandler($this->config, $this->converter);
        $this->delivery = new PictureTagDelivery($this->config);
        $this->ajaxRunner = new AjaxBatchRunner($this->attachmentHandler);

        if (is_admin()) {
            $this->settingsPage = new SettingsPage($this->config, $this->detector);
            $this->adminController = new AdminController($this->config, $this->detector, $this->settingsPage);
        }
    }

    /**
     * Initialize plugin lifecycle and register hooks.
     *
     * @return void
     */
    public function init(): void {
        // Register media lifecycle hooks
        $this->attachmentHandler->registerHooks();

        // Register frontend delivery hooks
        $this->delivery->registerHooks();

        // Register AJAX batch actions
        $this->ajaxRunner->registerHooks();

        // Register Admin UI
        if ($this->adminController !== null) {
            $this->adminController->registerHooks();
        }
    }

    /**
     * Plugin activation routine.
     *
     * @return void
     */
    public function activate(): void {
        // Ensure default options are populated
        $this->config->getOptions();

        // Perform initial capability probe and cache result
        $this->detector->getCapabilities(true);
    }

    /**
     * Plugin deactivation routine.
     *
     * @return void
     */
    public function deactivate(): void {
        // Clear transient caches
        $this->detector->clearCache();

        // Clear scheduled cron worker
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('nextgen_cron_batch_worker');
        }
    }

    // Getters for services
    public function getConfig(): Config {
        return $this->config;
    }

    public function getDetector(): SystemDetector {
        return $this->detector;
    }

    public function getConverter(): ConverterManager {
        return $this->converter;
    }

    public function getAttachmentHandler(): AttachmentHandler {
        return $this->attachmentHandler;
    }

    public function getDelivery(): PictureTagDelivery {
        return $this->delivery;
    }
}

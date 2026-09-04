<?php
/**
 * Quiet Upgrader Skin for Silent In-Memory Plugin Installation.
 *
 * Extends \WP_Upgrader_Skin to suppress raw HTML output during automated Pro addon installation.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

if ( ! defined( 'ABSPATH' ) && ! defined( 'NEXTGEN_TESTING' ) ) {
    exit;
}

if ( ! class_exists( '\WP_Upgrader_Skin' ) && defined( 'ABSPATH' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
}

/**
 * Class QuietUpgraderSkin
 */
class QuietUpgraderSkin extends \WP_Upgrader_Skin {

    /**
     * Suppress standard header output.
     */
    public function header(): void {}

    /**
     * Suppress standard footer output.
     */
    public function footer(): void {}

    /**
     * Suppress standard string feedback.
     *
     * @param string|array $string
     * @param mixed ...$args
     */
    public function feedback( $string, ...$args ): void {}

    /**
     * Suppress error output.
     *
     * @param mixed $errors
     */
    public function error( $errors ): void {}

    /**
     * Suppress before output.
     */
    public function before(): void {}

    /**
     * Suppress after output.
     */
    public function after(): void {}
}

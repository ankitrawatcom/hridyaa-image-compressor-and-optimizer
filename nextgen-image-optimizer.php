<?php
/**
 * Plugin Name:       Hridyaa Image Compressor and Optimizer
 * Plugin URI:        https://ankitrawat.com/products/hridyaa-image-compressor-and-optimizer/
 * Description:       Compress and optimize WordPress images, convert to WebP and AVIF, and reduce image sizes for faster page loads.
 * Version:           1.2.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ankit Rawat
 * Author URI:        https://ankitrawat.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hridyaa-image-compressor-and-optimizer
 * Domain Path:       /languages
 *
 * @package NextGen
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('NEXTGEN_VERSION', '1.2.1');
define('NEXTGEN_FILE', __FILE__);
define('NEXTGEN_DIR', plugin_dir_path(__FILE__));
define('NEXTGEN_URL', plugin_dir_url(__FILE__));

// Require and register PSR-4 autoloader
require_once NEXTGEN_DIR . 'includes/Core/Autoloader.php';
\NextGen\Core\Autoloader::register(NEXTGEN_DIR . 'includes');

// Register activation and deactivation hooks
register_activation_hook(__FILE__, function () {
    \NextGen\Core\Plugin::getInstance()->activate();
});

register_deactivation_hook(__FILE__, function () {
    \NextGen\Core\Plugin::getInstance()->deactivate();
});

// Initialize plugin on plugins_loaded
add_action('plugins_loaded', function () {
    \NextGen\Core\Plugin::getInstance()->init();
});

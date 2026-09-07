<?php
/**
 * PHPUnit Test Bootstrap and WordPress Test Environment Mock Harness.
 */

define('NEXTGEN_TESTING', true);
define('ABSPATH', __DIR__ . '/');

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../includes/Core/Autoloader.php';

\NextGen\Core\Autoloader::register(__DIR__ . '/../includes');

// Sibling dependency resolution for cross-component testing in local/staging environments
$siblingPro = dirname(__DIR__, 2) . '/hridyaa-image-compressor-and-optimizer-pro';
if (is_dir($siblingPro) && file_exists("$siblingPro/includes/Core/Autoloader.php")) {
    spl_autoload_register(function ($class) use ($siblingPro) {
        $prefix = 'NextGenPro\\';
        $baseDir = "$siblingPro/includes/";
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

$siblingBackend = dirname(__DIR__, 2) . '/hridyaa-license-backend';
if (is_dir($siblingBackend) && file_exists("$siblingBackend/src/Database.php")) {
    spl_autoload_register(function ($class) use ($siblingBackend) {
        $prefix = 'NextGen\\LicenseBackend\\';
        $baseDir = "$siblingBackend/src/";
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}


// In-memory WordPress storage mocks
global $mock_options, $mock_post_meta, $mock_posts, $mock_upload_dir, $wpdb;

$mock_options = [];
$mock_post_meta = [];
$mock_posts = [];
$mock_upload_dir = [
    'basedir' => str_replace('\\', '/', sys_get_temp_dir() . '/nextgen_test_uploads'),
    'baseurl' => 'https://example.com/wp-content/uploads',
];

if (!is_dir($mock_upload_dir['basedir'])) {
    @mkdir($mock_upload_dir['basedir'], 0777, true);
}

// WordPress global functions stubs
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        global $mock_options;
        return $mock_options[$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        global $mock_options;
        $mock_options[$key] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($key) {
        global $mock_options;
        unset($mock_options[$key]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return get_option('_transient_' . $key, false);
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        return update_option('_transient_' . $key, $value);
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        return delete_option('_transient_' . $key);
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        global $mock_post_meta;
        if (!isset($mock_post_meta[$post_id])) {
            return $single ? '' : [];
        }
        if (empty($key)) {
            return $mock_post_meta[$post_id];
        }
        if (!isset($mock_post_meta[$post_id][$key])) {
            return $single ? '' : [];
        }
        return $single ? $mock_post_meta[$post_id][$key] : [$mock_post_meta[$post_id][$key]];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        global $mock_post_meta;
        if (!isset($mock_post_meta[$post_id])) {
            $mock_post_meta[$post_id] = [];
        }
        $mock_post_meta[$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $key) {
        global $mock_post_meta;
        if (isset($mock_post_meta[$post_id][$key])) {
            unset($mock_post_meta[$post_id][$key]);
            return true;
        }
        return false;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        global $mock_upload_dir;
        if (is_callable($mock_upload_dir)) {
            return call_user_func($mock_upload_dir);
        }
        return $mock_upload_dir;
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file($attachment_id) {
        global $mock_posts;
        if (isset($mock_posts[$attachment_id])) {
            return is_object($mock_posts[$attachment_id]) ? ($mock_posts[$attachment_id]->file ?? '') : ($mock_posts[$attachment_id]['file'] ?? '');
        }
        return '';
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0) {
        global $mock_posts;
        $id = is_object($post) ? ($post->ID ?? 0) : (int) $post;
        if (isset($mock_posts[$id])) {
            return is_object($mock_posts[$id]) ? ($mock_posts[$id]->post_title ?? '') : ($mock_posts[$id]['post_title'] ?? '');
        }
        return '';
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id = 0) {
        global $mock_posts, $mock_upload_dir;
        $id = (int) $attachment_id;
        if (isset($mock_posts[$id])) {
            $guid = is_object($mock_posts[$id]) ? ($mock_posts[$id]->guid ?? '') : ($mock_posts[$id]['guid'] ?? '');
            if ($guid) return $guid;
        }
        return ($mock_upload_dir['baseurl'] ?? 'https://example.com/wp-content/uploads') . '/sample_' . $id . '.jpg';
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        global $mock_posts;
        if (empty($mock_posts)) {
            return [];
        }
        $results = [];
        $allowedMimes = isset($args['post_mime_type']) ? (array) $args['post_mime_type'] : [];
        $limit = isset($args['posts_per_page']) && $args['posts_per_page'] > 0 ? (int) $args['posts_per_page'] : 50;

        foreach ($mock_posts as $id => $post) {
            $mime = is_object($post) ? ($post->post_mime_type ?? 'image/jpeg') : ($post['post_mime_type'] ?? 'image/jpeg');
            if (!empty($allowedMimes) && !in_array($mime, $allowedMimes, true)) {
                continue;
            }
            if (is_object($post)) {
                $results[] = $post;
            } elseif (is_array($post)) {
                $obj = (object) array_merge(['ID' => $id, 'post_title' => $post['post_title'] ?? "Image #$id", 'post_mime_type' => $mime], $post);
                $results[] = $obj;
            }
            if (count($results) >= $limit) {
                break;
            }
        }
        return $results;
    }
}

if (!function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata($attachment_id) {
        global $mock_posts;
        if (isset($mock_posts[$attachment_id])) {
            return is_object($mock_posts[$attachment_id]) ? ($mock_posts[$attachment_id]->metadata ?? []) : ($mock_posts[$attachment_id]['metadata'] ?? []);
        }
        return [];
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r = &$args;
        } else {
            $r = [];
        }
        return array_merge($defaults, $r);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return (string) $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo (string) $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = ((string) $checked === (string) $current) ? 'checked="checked"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

global $wp_filter_registry;
$wp_filter_registry = [];

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_filter_registry;
        $wp_filter_registry[$tag][$priority][] = [
            'function'      => $callback,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
        return add_filter($tag, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        global $wp_filter_registry;
        if (empty($wp_filter_registry[$tag])) {
            return $value;
        }

        $priorities = array_keys($wp_filter_registry[$tag]);
        sort($priorities);

        foreach ($priorities as $priority) {
            foreach ($wp_filter_registry[$tag][$priority] as $filter) {
                $cb = $filter['function'];
                $acceptedArgs = (int) $filter['accepted_args'];
                $allArgs = array_merge([$value], $args);
                $passedArgs = array_slice($allArgs, 0, $acceptedArgs);
                $value = call_user_func_array($cb, $passedArgs);
            }
        }

        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        global $wp_filter_registry;
        if (empty($wp_filter_registry[$tag])) {
            return;
        }

        $priorities = array_keys($wp_filter_registry[$tag]);
        sort($priorities);

        foreach ($priorities as $priority) {
            foreach ($wp_filter_registry[$tag][$priority] as $filter) {
                $cb = $filter['function'];
                $acceptedArgs = (int) $filter['accepted_args'];
                $passedArgs = array_slice($args, 0, $acceptedArgs);
                call_user_func_array($cb, $passedArgs);
            }
        }
    }
}

if (!function_exists('__return_true')) {
    function __return_true() {
        return true;
    }
}

if (!function_exists('__return_false')) {
    function __return_false() {
        return false;
    }
}

if (!function_exists('__return_empty_array')) {
    function __return_empty_array() {
        return [];
    }
}

if (!function_exists('__return_null')) {
    function __return_null() {
        return null;
    }
}

if (!function_exists('disabled')) {
    function disabled($disabled, $current = true, $echo = true) {
        $result = ((string) $disabled === (string) $current) ? ' disabled="disabled"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0) {
        return number_format((float) $number, $decimals);
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        global $mock_is_admin;
        return $mock_is_admin ?? false;
    }
}

if (!function_exists('wp_doing_cron')) {
    function wp_doing_cron() {
        global $mock_is_cron;
        return $mock_is_cron ?? false;
    }
}

global $wp_mock_cron_events;
$wp_mock_cron_events = [];

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        global $wp_mock_cron_events;
        $wp_mock_cron_events[$hook] = $timestamp;
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        global $wp_mock_cron_events;
        return $wp_mock_cron_events[$hook] ?? false;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook) {
        global $wp_mock_cron_events;
        unset($wp_mock_cron_events[$hook]);
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('is_feed')) {
    function is_feed() {
        return false;
    }
}

if (!function_exists('wp_is_json_request')) {
    function wp_is_json_request() {
        return false;
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($val) {
        if (is_string($val) && (strpos($val, 'a:') === 0 || strpos($val, 'O:') === 0 || strpos($val, 's:') === 0)) {
            $un = @unserialize($val);
            if ($un !== false || $val === 'b:0;') {
                return $un;
            }
        }
        return $val;
    }
}

if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0) {
        $quant = [
            'TB' => 1099511627776,
            'GB' => 1073741824,
            'MB' => 1048576,
            'KB' => 1024,
            'B'  => 1,
        ];
        foreach ($quant as $unit => $mag) {
            if (doubleval($bytes) >= $mag) {
                return number_format($bytes / $mag, $decimals) . ' ' . $unit;
            }
        }
        return '0 B';
    }
}

// Mock wpdb class
if (!class_exists('wpdb')) {
    class wpdb {
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';

        public function get_var($query) {
            global $mock_posts;
            return count($mock_posts);
        }

        public function get_col($query) {
            global $mock_posts, $mock_post_meta;
            if (strpos($query, 'pm.meta_id IS NULL') !== false) {
                $pending = [];
                foreach (array_keys($mock_posts) as $id) {
                    if (empty($mock_post_meta[$id]['_nextgen_webp_data'])) {
                        $pending[] = $id;
                    }
                }
                return $pending;
            }
            return array_keys($mock_posts);
        }

        public function get_results($query) {
            global $mock_post_meta;
            $results = [];
            foreach ($mock_post_meta as $postId => $meta) {
                if (isset($meta['_nextgen_webp_data'])) {
                    $results[] = (object) ['meta_value' => $meta['_nextgen_webp_data']];
                }
            }
            return $results;
        }

        public function prepare($query, ...$args) {
            if (count($args) === 1 && is_array($args[0])) {
                $args = $args[0];
            }
            return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
        }

        public function query($query) {
            global $mock_post_meta;
            $count = count($mock_post_meta);
            $mock_post_meta = [];
            return $count ?: 1;
        }
    }
}

$wpdb = new \wpdb();

if (!class_exists('WP_Error')) {
    class WP_Error {
        public string $code;
        public string $message;
        public function __construct(string $code = '', string $message = '') {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_code(): string {
            return $this->code;
        }
        public function get_error_message(): string {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return ($thing instanceof WP_Error);
    }
}

global $mock_download_url_handler;
$mock_download_url_handler = null;

if (!function_exists('download_url')) {
    function download_url($url, $timeout = 300) {
        global $mock_download_url_handler;
        if ($mock_download_url_handler !== null) {
            return call_user_func($mock_download_url_handler, $url, $timeout);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'wppkg_');
        file_put_contents($tmp, 'MOCK_ZIP_CONTENT');
        return $tmp;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        global $mock_current_user_can;
        return isset($mock_current_user_can) ? $mock_current_user_can : true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($val) {
        return is_string($val) ? stripslashes($val) : $val;
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') {
        echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {
        echo '<input type="submit" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="button button-' . esc_attr($type) . '" value="' . esc_attr($text ?: 'Save Changes') . '" />';
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) {
        throw new \RuntimeException((string) $message);
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'mock_nonce_' . (string) $action;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        $field = '<input type="hidden" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="mock_nonce_' . esc_attr($action) . '" />';
        if ($echo) {
            echo $field;
        }
        return $field;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = '_ajax_nonce', $die = true) {
        global $mock_check_ajax_referer;
        if (isset($mock_check_ajax_referer)) {
            return (bool) $mock_check_ajax_referer;
        }
        $nonce = $_POST[$query_arg] ?? $_GET[$query_arg] ?? $_REQUEST[$query_arg] ?? '';
        $valid = (!empty($nonce) && $nonce === wp_create_nonce($action));
        if (!$valid && $die) {
            throw new \RuntimeException('WP_DIE: check_ajax_referer failed');
        }
        return $valid;
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($response, $status_code = null, $options = 0) {
        global $mock_last_json_response;
        $mock_last_json_response = [
            'status_code' => $status_code ?? 200,
            'response'    => $response,
        ];
        echo json_encode($response, $options);
        if (!defined('NEXTGEN_TESTING')) {
            exit;
        }
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null, $options = 0) {
        $response = ['success' => true];
        if (isset($data)) {
            $response['data'] = $data;
        }
        wp_send_json($response, $status_code, $options);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null, $options = 0) {
        $response = ['success' => false];
        if (isset($data)) {
            $response['data'] = $data;
        }
        wp_send_json($response, $status_code, $options);
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
        global $mock_check_admin_referer;
        if (isset($mock_check_admin_referer)) {
            return (bool) $mock_check_admin_referer;
        }
        $nonce = $_POST[$query_arg] ?? $_GET[$query_arg] ?? '';
        return !empty($nonce) && $nonce === wp_create_nonce($action);
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress') {
        global $mock_last_redirect;
        $mock_last_redirect = $location;
        return true;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args) {
        if (is_array($args[0])) {
            $uri = $args[1] ?? '';
            $qs = http_build_query($args[0]);
            return $uri . (strpos($uri, '?') !== false ? '&' : '?') . $qs;
        }
        return ($args[2] ?? '') . '?' . ($args[0] ?? '') . '=' . ($args[1] ?? '');
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = false) {
        return date($format, $timestamp ?: time());
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text) {
        return addcslashes((string) $text, "'\"\\\r\n");
    }
}

global $mock_registered_submenus;
$mock_registered_submenus = [];

if (!function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null) {
        return $menu_slug;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {
        global $mock_registered_submenus;
        $mock_registered_submenus[] = [
            'parent_slug' => $parent_slug,
            'page_title'  => $page_title,
            'menu_title'  => $menu_title,
            'capability'  => $capability,
            'menu_slug'   => $menu_slug,
            'callback'    => $callback,
            'position'    => $position,
        ];
        return $menu_slug;
    }
}

if (!function_exists('add_media_page')) {
    function add_media_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {
        return $menu_slug;
    }
}

global $mock_enqueued_scripts, $mock_enqueued_styles, $mock_localized_scripts;
$mock_enqueued_scripts = [];
$mock_enqueued_styles = [];
$mock_localized_scripts = [];

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') {
        return 'https://example.com/wp-content/plugins/nextgen/' . ltrim($path, '/');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin') {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        global $mock_enqueued_styles;
        $mock_enqueued_styles[$handle] = [
            'src'     => $src,
            'deps'    => $deps,
            'version' => $ver,
            'media'   => $media,
        ];
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
        global $mock_enqueued_scripts;
        $mock_enqueued_scripts[$handle] = [
            'src'       => $src,
            'deps'      => $deps,
            'version'   => $ver,
            'in_footer' => $in_footer,
        ];
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        global $mock_localized_scripts;
        $mock_localized_scripts[$handle] = [
            'object_name' => $object_name,
            'l10n'        => $l10n,
        ];
        return true;
    }
}



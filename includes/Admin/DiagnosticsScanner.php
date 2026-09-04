<?php
/**
 * Server & AVIF Compatibility Diagnostics Scanner for NextGen Image Optimizer.
 *
 * Performs 100% read-only local PHP inspection of image processing drivers,
 * memory limits, and filesystem capabilities. Strictly prohibits shell execution
 * and external network requests.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class DiagnosticsScanner {

    public const PASS    = 'PASS';
    public const WARNING = 'WARNING';
    public const FAIL    = 'FAIL';
    public const UNKNOWN = 'UNKNOWN';

    /**
     * Run complete local diagnostics audit.
     *
     * @return array<string, array{label:string, status:string, value:string, message:string}>
     */
    public static function runFullAudit(): array {
        $results = [];

        // 1. PHP Version
        $phpVer = PHP_VERSION;
        if (version_compare($phpVer, '8.1.0', '>=')) {
            $results['php_version'] = [
                'label'   => 'PHP Version',
                'status'  => self::PASS,
                'value'   => $phpVer,
                'message' => 'PHP 8.1+ active with full modern AVIF and WebP encoder capabilities.',
            ];
        } elseif (version_compare($phpVer, '7.4.0', '>=')) {
            $results['php_version'] = [
                'label'   => 'PHP Version',
                'status'  => self::WARNING,
                'value'   => $phpVer,
                'message' => 'PHP 7.4/8.0 supports WebP, but PHP 8.1+ is required for local AVIF encoding.',
            ];
        } else {
            $results['php_version'] = [
                'label'   => 'PHP Version',
                'status'  => self::FAIL,
                'value'   => $phpVer,
                'message' => 'PHP version is below 7.4. Upgrade to a supported PHP version.',
            ];
        }

        // 2. Memory Limit
        $memLimitStr = ini_get('memory_limit');
        $memBytes = self::parseBytes($memLimitStr);
        if ($memBytes < 0 || $memBytes >= 256 * 1024 * 1024) {
            $results['memory_limit'] = [
                'label'   => 'PHP Memory Limit',
                'status'  => self::PASS,
                'value'   => $memLimitStr,
                'message' => 'Sufficient memory for high-resolution image processing.',
            ];
        } elseif ($memBytes >= 128 * 1024 * 1024) {
            $results['memory_limit'] = [
                'label'   => 'PHP Memory Limit',
                'status'  => self::WARNING,
                'value'   => $memLimitStr,
                'message' => '128M is adequate for standard images; 256M recommended for large uploads.',
            ];
        } else {
            $results['memory_limit'] = [
                'label'   => 'PHP Memory Limit',
                'status'  => self::FAIL,
                'value'   => $memLimitStr,
                'message' => 'Memory limit is under 128M. Increase memory_limit in php.ini.',
            ];
        }

        // 3. Execution Time
        $maxExec = (int) ini_get('max_execution_time');
        if ($maxExec === 0 || $maxExec >= 60) {
            $results['execution_time'] = [
                'label'   => 'Max Execution Time',
                'status'  => self::PASS,
                'value'   => $maxExec === 0 ? 'Unlimited' : "{$maxExec}s",
                'message' => 'Optimal execution window for bulk conversion batches.',
            ];
        } elseif ($maxExec >= 30) {
            $results['execution_time'] = [
                'label'   => 'Max Execution Time',
                'status'  => self::WARNING,
                'value'   => "{$maxExec}s",
                'message' => '30s is acceptable; 60s+ recommended for large media libraries.',
            ];
        } else {
            $results['execution_time'] = [
                'label'   => 'Max Execution Time',
                'status'  => self::FAIL,
                'value'   => "{$maxExec}s",
                'message' => 'Execution time under 30s may cause timeouts during bulk batch runs.',
            ];
        }

        // 4. GD Library Support
        if (extension_loaded('gd')) {
            $gdInfo = function_exists('gd_info') ? gd_info() : [];
            $gdWebp = !empty($gdInfo['WebP Support']);
            $gdAvif = !empty($gdInfo['AVIF Support']);

            $gdValue = 'Installed';
            if ($gdWebp && $gdAvif) {
                $gdValue .= ' (WebP + AVIF)';
                $gdStatus = self::PASS;
                $gdMsg = 'GD extension active with native WebP and AVIF support.';
            } elseif ($gdWebp) {
                $gdValue .= ' (WebP Only)';
                $gdStatus = self::PASS;
                $gdMsg = 'GD extension active with WebP support. AVIF not compiled in GD.';
            } else {
                $gdStatus = self::WARNING;
                $gdMsg = 'GD is installed but lacks WebP/AVIF compilation.';
            }

            $results['gd_driver'] = [
                'label'   => 'PHP GD Library',
                'status'  => $gdStatus,
                'value'   => $gdValue,
                'message' => $gdMsg,
            ];
        } else {
            $results['gd_driver'] = [
                'label'   => 'PHP GD Library',
                'status'  => self::FAIL,
                'value'   => 'Not Installed',
                'message' => 'PHP GD extension is not loaded on this server.',
            ];
        }

        // 5. ImageMagick Support
        if (class_exists('Imagick')) {
            $formats = method_exists('Imagick', 'queryFormats') ? \Imagick::queryFormats() : [];
            $imWebp = in_array('WEBP', $formats, true);
            $imAvif = in_array('AVIF', $formats, true);

            $imValue = 'Installed';
            if ($imWebp && $imAvif) {
                $imValue .= ' (WebP + AVIF)';
                $imStatus = self::PASS;
                $imMsg = 'ImageMagick active with full multi-threaded WebP and AVIF support.';
            } elseif ($imWebp) {
                $imValue .= ' (WebP Only)';
                $imStatus = self::PASS;
                $imMsg = 'ImageMagick active with WebP support.';
            } else {
                $imStatus = self::WARNING;
                $imMsg = 'ImageMagick is installed but lacks WebP/AVIF delegate support.';
            }

            $results['imagick_driver'] = [
                'label'   => 'ImageMagick Extension',
                'status'  => $imStatus,
                'value'   => $imValue,
                'message' => $imMsg,
            ];
        } else {
            $results['imagick_driver'] = [
                'label'   => 'ImageMagick Extension',
                'status'  => self::WARNING,
                'value'   => 'Not Installed',
                'message' => 'ImageMagick is optional. GD will be used for all image conversions.',
            ];
        }

        // 6. Uploads Directory Writability
        $uploadDir = function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => ''];
        $baseDir = $uploadDir['basedir'] ?? '';
        $isWritable = !empty($baseDir) && is_dir($baseDir) && (function_exists('wp_is_writable') ? wp_is_writable($baseDir) : is_writable($baseDir));

        $results['uploads_writable'] = [
            'label'   => 'Uploads Directory',
            'status'  => $isWritable ? self::PASS : self::FAIL,
            'value'   => $isWritable ? 'Writable' : 'Not Writable',
            'message' => $isWritable
                ? 'WordPress uploads directory is writable.'
                : 'Uploads directory is not writable by PHP process. Check directory permissions.',
        ];

        // 7. WordPress Version
        global $wp_version;
        $wpVer = $wp_version ?? '6.7';
        $results['wordpress_version'] = [
            'label'   => 'WordPress Version',
            'status'  => version_compare($wpVer, '5.8.0', '>=') ? self::PASS : self::WARNING,
            'value'   => $wpVer,
            'message' => 'Compatible with WordPress core media subsystem.',
        ];

        return $results;
    }

    /**
     * Generate sanitized clean Markdown system report for support tickets.
     *
     * @return string
     */
    public static function getFormattedMarkdownReport(): string {
        $audit = self::runFullAudit();
        $lines = ["### Hridyaa Image Compressor System & Codec Capability Report", ""];
        $lines[] = "| Component | Status | Detected Value | Details |";
        $lines[] = "|---|---|---|---|";

        foreach ($audit as $item) {
            $sanitizedLabel = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
            $sanitizedValue = htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8');
            $sanitizedMsg   = htmlspecialchars($item['message'], ENT_QUOTES, 'UTF-8');
            $lines[] = sprintf("| **%s** | `%s` | %s | %s |", $sanitizedLabel, $item['status'], $sanitizedValue, $sanitizedMsg);
        }

        $lines[] = "";
        $lines[] = "*Generated locally on " . gmdate('Y-m-d H:i:s') . " UTC without external network transmission.*";
        return implode("\n", $lines);
    }

    /**
     * Helper to parse ini memory strings like '256M', '1G' into bytes.
     *
     * @param string $val
     * @return int
     */
    private static function parseBytes(string $val): int {
        $val = trim($val);
        if ($val === '-1') {
            return -1;
        }

        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) $val;

        switch ($last) {
            case 'g':
                $num *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $num *= 1024 * 1024;
                break;
            case 'k':
                $num *= 1024;
                break;
        }

        return $num;
    }
}

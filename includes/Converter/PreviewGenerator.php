<?php
/**
 * Ephemeral Preview Generator for Interactive Visual Comparison.
 *
 * Generates temporary, isolated preview derivatives for specific presets and formats
 * without modifying attachment metadata, polluting global savings statistics,
 * or altering permanent media derivatives.
 *
 * @package NextGen\Converter
 */

namespace NextGen\Converter;

use NextGen\Core\Config;
use NextGen\Admin\QualityPresetManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PreviewGenerator {

    public const PREVIEW_DIR_NAME = 'nextgen-previews';

    /**
     * Generate an ephemeral preview for visual comparison.
     *
     * @param int    $attachmentId WordPress media attachment ID.
     * @param string $format       Target format ('webp' or 'avif').
     * @param string $preset       Quality preset ('high', 'balanced', 'aggressive').
     * @return array Result array with status, preview_url, original_size, preview_size, bytes_saved, percentage_saved.
     */
    public static function generatePreview(int $attachmentId, string $format = 'webp', string $preset = 'balanced'): array {
        if ($attachmentId <= 0) {
            return ['success' => false, 'error' => 'invalid_attachment', 'message' => 'Invalid attachment ID.'];
        }

        $cleanFormat = strtolower(trim($format));
        if (!in_array($cleanFormat, ['webp', 'avif'], true)) {
            return ['success' => false, 'error' => 'unsupported_format', 'message' => 'Format must be webp or avif.'];
        }

        $cleanPreset = QualityPresetManager::normalizePreset($preset);

        // Enforce server-side Pro entitlement check for AVIF previews
        if ($cleanFormat === 'avif') {
            $isEntitled = \NextGen\Core\Features::isAvifEnabled();
            if (!$isEntitled) {
                return [
                    'success'  => false,
                    'error'    => 'pro_required',
                    'message'  => 'AVIF preview comparison requires an active NextGen Pro license.',
                    'pro_cta'  => true,
                ];
            }
        }

        $sourcePath = get_attached_file($attachmentId);
        if (empty($sourcePath) || !file_exists($sourcePath)) {
            return ['success' => false, 'error' => 'source_missing', 'message' => 'Original attachment file not found.'];
        }

        $uploadDir = function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => sys_get_temp_dir(), 'baseurl' => (function_exists('content_url') ? content_url('/uploads') : '')];
        $baseDir = self::normalizePath($uploadDir['basedir']);
        $normalizedSource = self::normalizePath($sourcePath);

        if (strpos($normalizedSource, $baseDir) !== 0 && !defined('NEXTGEN_TESTING')) {
            return ['success' => false, 'error' => 'boundary_violation', 'message' => 'Source image is outside allowed upload boundary.'];
        }

        // Validate MIME type
        $mime = function_exists('wp_check_filetype') ? wp_check_filetype($sourcePath)['type'] : mime_content_type($sourcePath);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            return ['success' => false, 'error' => 'unsupported_mime', 'message' => 'Only JPEG, PNG, and GIF images can be previewed.'];
        }

        $previewBaseDir = $baseDir . '/' . self::PREVIEW_DIR_NAME;
        self::ensurePreviewDirectory($previewBaseDir);

        $sourceSize = (int) @filesize($sourcePath);
        $quality = QualityPresetManager::getQuality($cleanFormat, $cleanPreset);

        // Deterministic preview file path based on source modification time & parameters
        $mtime = @filemtime($sourcePath) ?: time();
        $hash = substr(hash('sha256', "{$attachmentId}_{$cleanFormat}_{$cleanPreset}_{$quality}_{$mtime}"), 0, 12);
        $previewFilename = sprintf('preview_%d_%s_%s_%s.%s', $attachmentId, $cleanFormat, $cleanPreset, $hash, $cleanFormat);
        $previewPath = $previewBaseDir . '/' . $previewFilename;
        $previewUrl = ($uploadDir['baseurl'] ?? '') . '/' . self::PREVIEW_DIR_NAME . '/' . $previewFilename;

        // Use cached preview if existing and non-empty
        if (file_exists($previewPath) && filesize($previewPath) > 0) {
            $previewSize = (int) filesize($previewPath);
            $bytesSaved = max(0, $sourceSize - $previewSize);
            $percent = $sourceSize > 0 ? round(($bytesSaved / $sourceSize) * 100, 1) : 0.0;

            return [
                'success'          => true,
                'preview_url'      => $previewUrl,
                'original_size'    => $sourceSize,
                'preview_size'     => $previewSize,
                'bytes_saved'      => $bytesSaved,
                'percentage_saved' => $percent,
                'cached'           => true,
            ];
        }

        // Generate preview using ConverterManager
        $cfg = new Config();
        $converterManager = new ConverterManager($cfg);

        // Ephemeral conversion with explicit quality override
        $result = $converterManager->convert($sourcePath, $previewPath, ['quality' => $quality], $cleanFormat);

        if (!$result->isSuccess() || !file_exists($previewPath) || filesize($previewPath) === 0) {
            @unlink($previewPath);
            return [
                'success' => false,
                'error'   => $result->getErrorCode() ?? 'conversion_failed',
                'message' => $result->getErrorMessage() ?? 'Preview conversion failed.',
            ];
        }

        $previewSize = (int) filesize($previewPath);
        $bytesSaved = max(0, $sourceSize - $previewSize);
        $percent = $sourceSize > 0 ? round(($bytesSaved / $sourceSize) * 100, 1) : 0.0;

        return [
            'success'          => true,
            'preview_url'      => $previewUrl,
            'original_size'    => $sourceSize,
            'preview_size'     => $previewSize,
            'bytes_saved'      => $bytesSaved,
            'percentage_saved' => $percent,
            'cached'           => false,
        ];
    }

    /**
     * Ensure preview directory exists with PHP execution blocked.
     */
    public static function ensurePreviewDirectory(string $previewDir): void {
        if (!is_dir($previewDir)) {
            @mkdir($previewDir, 0750, true);
        }

        $htaccess = $previewDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "# NextGen Security: Disable script execution\n<FilesMatch \"\.(?i:php|phtml|php3|php4|php5|php7|phps|cgi|pl|py|sh)\$\">\nRequire all denied\n</FilesMatch>\nOptions -ExecCGI\n");
        }

        $indexPhp = $previewDir . '/index.php';
        if (!file_exists($indexPhp)) {
            @file_put_contents($indexPhp, "<?php\n// Silence is golden.\n");
        }
    }

    /**
     * Purge abandoned preview files older than max age.
     *
     * @param int $maxAgeSeconds Maximum file age in seconds (default: 86400 / 24 hours).
     * @return int Number of files purged.
     */
    public static function cleanupExpiredPreviews(int $maxAgeSeconds = 86400): int {
        $uploadDir = function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => sys_get_temp_dir()];
        $previewDir = self::normalizePath($uploadDir['basedir']) . '/' . self::PREVIEW_DIR_NAME;

        if (!is_dir($previewDir)) {
            return 0;
        }

        $now = time();
        $purged = 0;
        $files = glob($previewDir . '/preview_*');

        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $mtime = @filemtime($file);
                    if ($mtime && ($now - $mtime) > $maxAgeSeconds) {
                        if (@unlink($file)) {
                            $purged++;
                        }
                    }
                }
            }
        }

        return $purged;
    }

    /**
     * Normalize path separators.
     */
    public static function normalizePath(string $path): string {
        return str_replace('\\', '/', $path);
    }
}

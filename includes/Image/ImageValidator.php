<?php
/**
 * Image Security & Format Validator.
 *
 * Enforces magic byte MIME validation, dimension safety (anti-decompression bomb),
 * and path boundary verification.
 *
 * @package NextGen\Image
 */

namespace NextGen\Image;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImageValidator {

    /**
     * Supported MIME types for conversion in Stage 1.
     */
    public const SUPPORTED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
    ];

    /**
     * Maximum safe image area in pixels (25 Megapixels = e.g., 5000x5000px).
     */
    public const MAX_PIXEL_AREA = 25000000;

    /**
     * Validate an image file completely.
     *
     * @param string $filePath Absolute path to source image.
     * @param int $maxDimension Maximum allowable width or height (e.g. 4096).
     * @return array [bool $isValid, string $reason, string $mimeType, int $width, int $height]
     */
    public static function validate(string $filePath, int $maxDimension = 4096): array {
        if (!file_exists($filePath)) {
            return [false, 'file_not_found', '', 0, 0];
        }

        if (!is_readable($filePath)) {
            return [false, 'file_not_readable', '', 0, 0];
        }

        $fileSize = (int) @filesize($filePath);
        if ($fileSize <= 0) {
            return [false, 'file_empty', '', 0, 0];
        }

        // Magic byte MIME type verification
        $mimeType = self::detectMimeType($filePath);
        if (!isset(self::SUPPORTED_MIME_TYPES[$mimeType])) {
            return [false, 'unsupported_format', $mimeType, 0, 0];
        }

        // Read image dimensions safely without full uncompressed decode
        $sizeInfo = @getimagesize($filePath);
        if ($sizeInfo === false || empty($sizeInfo[0]) || empty($sizeInfo[1])) {
            return [false, 'invalid_image_data', $mimeType, 0, 0];
        }

        $width = (int) $sizeInfo[0];
        $height = (int) $sizeInfo[1];

        // Decompression bomb guard
        if ($width > $maxDimension || $height > $maxDimension || ($width * $height) > self::MAX_PIXEL_AREA) {
            return [false, 'decompression_bomb_guard', $mimeType, $width, $height];
        }

        return [true, 'valid', $mimeType, $width, $height];
    }

    /**
     * Detect real MIME type using binary magic bytes.
     *
     * @param string $filePath Path to file.
     * @return string
     */
    public static function detectMimeType(string $filePath): string {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = @finfo_file($finfo, $filePath);
                if (PHP_VERSION_ID < 80500 && is_resource($finfo)) {
                    @finfo_close($finfo);
                }
                if ($mime && is_string($mime)) {
                    return strtolower($mime);
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($filePath);
            if ($mime && is_string($mime)) {
                return strtolower($mime);
            }
        }

        // Fallback: check file signature directly from binary header
        return self::detectMimeFromHeader($filePath);
    }

    /**
     * Fallback binary header inspection.
     *
     * @param string $filePath Path to file.
     * @return string
     */
    private static function detectMimeFromHeader(string $filePath): string {
        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            return '';
        }
        $header = (string) fread($fp, 12);
        fclose($fp);

        if (strlen($header) < 4) {
            return '';
        }

        // JPEG: FF D8 FF
        if (substr($header, 0, 3) === "\xFF\xD8\xFF") {
            return 'image/jpeg';
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (substr($header, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'image/png';
        }

        // GIF: GIF87a or GIF89a
        if (substr($header, 0, 6) === 'GIF87a' || substr($header, 0, 6) === 'GIF89a') {
            return 'image/gif';
        }

        // WebP: RIFF....WEBP
        if (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return '';
    }

    /**
     * Validate that a target path is safely within allowed WordPress upload directories.
     *
     * @param string $path Target file path.
     * @param string|null $allowedBase Base directory (defaults to WP uploads dir).
     * @return bool
     */
    public static function isPathSafe(string $path, ?string $allowedBase = null): bool {
        if (empty($path)) {
            return false;
        }

        // Forbid directory traversal sequences
        if (strpos($path, '..') !== false) {
            return false;
        }

        if ($allowedBase === null && function_exists('wp_upload_dir')) {
            $uploads = wp_upload_dir();
            $allowedBase = $uploads['basedir'] ?? '';
        }

        if (empty($allowedBase)) {
            return true; // If outside WordPress environment, path without '..' is accepted
        }

        $normalizedPath = strtolower(FilenameHelper::normalizePath($path));
        $normalizedBase = strtolower(FilenameHelper::normalizePath($allowedBase));

        return strpos($normalizedPath, $normalizedBase) === 0;
    }
}

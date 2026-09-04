<?php
/**
 * Filename Helper.
 *
 * Deterministic and collision-free filename generation for WebP and AVIF derivatives.
 *
 * @package NextGen\Image
 */

namespace NextGen\Image;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FilenameHelper {

    /**
     * Format extensions.
     */
    public const EXT_WEBP = '.webp';
    public const EXT_AVIF = '.avif';

    /**
     * Generate the corresponding WebP file path for a source image.
     *
     * @param string $sourcePath Absolute path or filename of source image.
     * @return string Generated WebP path.
     */
    public static function generateWebpPath(string $sourcePath): string {
        return self::generateDerivativePath($sourcePath, 'webp');
    }

    /**
     * Generate the corresponding AVIF file path for a source image.
     *
     * @param string $sourcePath Absolute path or filename of source image.
     * @return string Generated AVIF path.
     */
    public static function generateAvifPath(string $sourcePath): string {
        return self::generateDerivativePath($sourcePath, 'avif');
    }

    /**
     * Generate derivative file path for any supported format.
     *
     * Appending format extension (e.g. `photo.jpg` -> `photo.jpg.webp` or `photo.jpg.avif`)
     * guarantees collision safety and reversibility.
     *
     * @param string $sourcePath Path to source image.
     * @param string $format Target format ('webp' or 'avif').
     * @return string
     */
    public static function generateDerivativePath(string $sourcePath, string $format = 'webp'): string {
        if (empty($sourcePath)) {
            return '';
        }

        $ext = ($format === 'avif') ? self::EXT_AVIF : self::EXT_WEBP;

        // If already ending in the target extension, do not double-append
        if (self::endsWithExtension($sourcePath, $ext)) {
            return $sourcePath;
        }

        return $sourcePath . $ext;
    }

    /**
     * Check if a given path ends with .webp (case-insensitive).
     *
     * @param string $path Path or filename to check.
     * @return bool
     */
    public static function isWebp(string $path): bool {
        $clean = strtok($path, '?#');
        return (bool) preg_match('/\.webp$/i', $clean);
    }

    /**
     * Check if a given path ends with .avif (case-insensitive).
     *
     * @param string $path Path or filename to check.
     * @return bool
     */
    public static function isAvif(string $path): bool {
        $clean = strtok($path, '?#');
        return (bool) preg_match('/\.avif$/i', $clean);
    }

    /**
     * Check if a file is a NextGen generated WebP derivative.
     *
     * @param string $path Path or filename to check.
     * @return bool
     */
    public static function isNextGenWebpDerivative(string $path): bool {
        $clean = strtok($path, '?#');
        return (bool) preg_match('/\.(jpe?g|png|gif)\.webp$/i', $clean);
    }

    /**
     * Check if a file is a NextGen generated AVIF derivative.
     *
     * @param string $path Path or filename to check.
     * @return bool
     */
    public static function isNextGenAvifDerivative(string $path): bool {
        $clean = strtok($path, '?#');
        return (bool) preg_match('/\.(jpe?g|png|gif)\.avif$/i', $clean);
    }

    /**
     * Check if a file is any NextGen generated derivative (.webp or .avif).
     *
     * @param string $path Path to check.
     * @return bool
     */
    public static function isNextGenDerivative(string $path): bool {
        return self::isNextGenWebpDerivative($path) || self::isNextGenAvifDerivative($path);
    }

    /**
     * Get the source file path from a NextGen WebP derivative path.
     *
     * @param string $webpPath Path to WebP derivative.
     * @return string Original source file path, or unchanged if not a derivative.
     */
    public static function getSourcePathFromWebp(string $webpPath): string {
        if (self::isNextGenWebpDerivative($webpPath)) {
            return substr($webpPath, 0, -strlen(self::EXT_WEBP));
        }
        return $webpPath;
    }

    /**
     * Get the source file path from a NextGen AVIF derivative path.
     *
     * @param string $avifPath Path to AVIF derivative.
     * @return string Original source file path, or unchanged if not a derivative.
     */
    public static function getSourcePathFromAvif(string $avifPath): string {
        if (self::isNextGenAvifDerivative($avifPath)) {
            return substr($avifPath, 0, -strlen(self::EXT_AVIF));
        }
        return $avifPath;
    }

    /**
     * Get the source file path from any NextGen derivative path (.webp or .avif).
     *
     * @param string $derivativePath Path to derivative.
     * @return string
     */
    public static function getSourcePathFromDerivative(string $derivativePath): string {
        if (self::isNextGenWebpDerivative($derivativePath)) {
            return substr($derivativePath, 0, -strlen(self::EXT_WEBP));
        }
        if (self::isNextGenAvifDerivative($derivativePath)) {
            return substr($derivativePath, 0, -strlen(self::EXT_AVIF));
        }
        return $derivativePath;
    }

    /**
     * Get the URL for a WebP derivative corresponding to a source image URL,
     * preserving query strings and fragments.
     *
     * @param string $sourceUrl URL of original image.
     * @return string URL of corresponding WebP derivative.
     */
    public static function generateWebpUrl(string $sourceUrl): string {
        return self::generateDerivativeUrl($sourceUrl, 'webp');
    }

    /**
     * Get the URL for an AVIF derivative corresponding to a source image URL,
     * preserving query strings and fragments.
     *
     * @param string $sourceUrl URL of original image.
     * @return string URL of corresponding AVIF derivative.
     */
    public static function generateAvifUrl(string $sourceUrl): string {
        return self::generateDerivativeUrl($sourceUrl, 'avif');
    }

    /**
     * Get derivative URL for any supported format.
     *
     * @param string $sourceUrl URL of source image.
     * @param string $format Target format ('webp' or 'avif').
     * @return string
     */
    public static function generateDerivativeUrl(string $sourceUrl, string $format = 'webp'): string {
        if (empty($sourceUrl)) {
            return '';
        }

        $ext = ($format === 'avif') ? self::EXT_AVIF : self::EXT_WEBP;

        $parsed = wp_parse_url($sourceUrl);
        if (!$parsed || empty($parsed['path'])) {
            return self::endsWithExtension($sourceUrl, $ext) ? $sourceUrl : $sourceUrl . $ext;
        }

        $path = $parsed['path'];
        if (self::endsWithExtension($path, $ext)) {
            return $sourceUrl;
        }

        $derivativePath = $path . $ext;

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        if (empty($scheme) && strpos($sourceUrl, '//') === 0) {
            $scheme = '//';
        }
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $user = $parsed['user'] ?? '';
        $pass = isset($parsed['pass']) ? ':' . $parsed['pass'] : '';
        $auth = ($user || $pass) ? "{$user}{$pass}@" : '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return $scheme . $auth . $host . $port . $derivativePath . $query . $fragment;
    }

    /**
     * Helper to check if string ends with extension.
     */
    private static function endsWithExtension(string $path, string $ext): bool {
        $clean = strtok($path, '?#');
        $extLen = strlen($ext);
        return substr_compare($clean, $ext, -$extLen, $extLen, true) === 0;
    }

    /**
     * Safely normalize directory separators for cross-platform consistency.
     *
     * @param string $path File or directory path.
     * @return string
     */
    public static function normalizePath(string $path): string {
        $path = str_replace('\\', '/', $path);
        return (string) preg_replace('#(?<!:)/+#', '/', $path);
    }
}

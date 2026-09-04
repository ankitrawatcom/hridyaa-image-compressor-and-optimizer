<?php
/**
 * ImageMagick WebP Converter Engine (Free Base).
 *
 * Implements WebP format encoding via PHP Imagick extension with ICC color profile preservation.
 *
 * @package NextGen\Converter
 */

namespace NextGen\Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImagickConverter implements ConverterInterface {

    /**
     * Check if Imagick extension is loaded.
     *
     * @return bool
     */
    public function isSupported(): bool {
        return extension_loaded('imagick') && class_exists('\Imagick');
    }

    /**
     * Check if Imagick supports a specific target format.
     *
     * @param string $format Target format ('webp').
     * @return bool
     */
    public function supportsFormat(string $format): bool {
        if (!$this->isSupported() || strtolower($format) !== 'webp') {
            return false;
        }

        try {
            $formats = \Imagick::queryFormats('WEBP');
            return !empty($formats) && in_array('WEBP', array_map('strtoupper', $formats), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get engine identifier.
     *
     * @return string
     */
    public function getEngineName(): string {
        return 'imagick';
    }

    /**
     * Convert image to WebP using ImageMagick.
     *
     * @param string $sourcePath Absolute path to source image.
     * @param string $outputPath Destination path for derivative.
     * @param array $options Quality and conversion options.
     * @param string $format Target format ('webp').
     * @return ConversionResult
     */
    public function convert(string $sourcePath, string $outputPath, array $options = [], string $format = 'webp'): ConversionResult {
        $startTime = microtime(true);
        $originalSize = (int) @filesize($sourcePath);

        if (!$this->supportsFormat($format)) {
            return ConversionResult::failure(
                $sourcePath,
                'engine_format_unsupported',
                "ImageMagick does not support {$format} format delegate in the Free base plugin.",
                'imagick',
                $originalSize
            );
        }

        $quality = isset($options['quality']) ? max(10, min(100, (int) $options['quality'])) : 82;
        $isLossless = !empty($options['png_lossless']) && preg_match('/\.png$/i', $sourcePath);

        $imagick = null;
        try {
            $imagick = new \Imagick($sourcePath);

            // Auto-orient based on EXIF
            if (method_exists($imagick, 'autoOrient')) {
                $imagick->autoOrient();
            } elseif (method_exists($imagick, 'autoOrientImage')) {
                $imagick->autoOrientImage();
            }

            // Preserve ICC color profile if present
            $iccProfile = null;
            try {
                $iccProfile = $imagick->getImageProfile('icc');
            } catch (\Throwable $e) {
                // No ICC profile present
            }

            // Strip unnecessary metadata (GPS, comments)
            $imagick->stripImage();

            // Re-apply ICC profile if one existed to ensure accurate color reproduction
            if (!empty($iccProfile)) {
                try {
                    $imagick->profileImage('icc', $iccProfile);
                } catch (\Throwable $e) {
                    // Non-fatal if profile re-attachment fails
                }
            }

            $imagick->setImageFormat('webp');

            if ($isLossless) {
                $imagick->setOption('webp:lossless', 'true');
            } else {
                $imagick->setImageCompressionQuality($quality);
            }

            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $imagick->writeImage($outputPath);

            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                if (file_exists($outputPath)) {
                    @unlink($outputPath);
                }
                return ConversionResult::failure($sourcePath, 'encode_failed', "ImageMagick writeImage produced empty {$format} file.", 'imagick', $originalSize);
            }

            $convertedSize = (int) filesize($outputPath);
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            return ConversionResult::success($sourcePath, $outputPath, $originalSize, $convertedSize, $durationMs, 'imagick');

        } catch (\Throwable $e) {
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
            return ConversionResult::failure($sourcePath, 'exception', $e->getMessage(), 'imagick', $originalSize);
        } finally {
            if ($imagick instanceof \Imagick) {
                $imagick->clear();
                $imagick->destroy();
            }
        }
    }
}

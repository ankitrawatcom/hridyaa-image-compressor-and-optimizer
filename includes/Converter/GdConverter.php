<?php
/**
 * GD WebP Converter Engine (Free Base).
 *
 * Implements WebP format encoding via PHP GD extension with alpha support and EXIF auto-orientation.
 *
 * @package NextGen\Converter
 */

namespace NextGen\Converter;

use NextGen\Image\ImageValidator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GdConverter implements ConverterInterface {

    /**
     * Check if GD extension is loaded.
     *
     * @return bool
     */
    public function isSupported(): bool {
        return extension_loaded('gd');
    }

    /**
     * Check if GD supports a specific target format.
     *
     * @param string $format Target format ('webp').
     * @return bool
     */
    public function supportsFormat(string $format): bool {
        if (!$this->isSupported()) {
            return false;
        }

        if ($format === 'webp') {
            return function_exists('imagewebp');
        }

        return false;
    }

    /**
     * Get engine identifier.
     *
     * @return string
     */
    public function getEngineName(): string {
        return 'gd';
    }

    /**
     * Convert image to WebP using GD.
     *
     * @param string $sourcePath Absolute path to source image.
     * @param string $outputPath Destination path for derivative.
     * @param array $options Quality, speed, and conversion options.
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
                "GD does not support {$format} encoding in the Free base plugin.",
                'gd',
                $originalSize
            );
        }

        $mimeType = $options['mime_type'] ?? ImageValidator::detectMimeType($sourcePath);
        $quality = isset($options['quality']) ? max(10, min(100, (int) $options['quality'])) : 82;

        $image = null;
        try {
            switch ($mimeType) {
                case 'image/jpeg':
                case 'image/pjpeg':
                    if (!function_exists('imagecreatefromjpeg')) {
                        return ConversionResult::failure($sourcePath, 'gd_jpeg_unsupported', 'GD JPEG decoder is missing.', 'gd', $originalSize);
                    }
                    $image = @imagecreatefromjpeg($sourcePath);
                    if ($image && function_exists('exif_read_data')) {
                        $image = $this->autoOrientJpeg($image, $sourcePath);
                    }
                    break;

                case 'image/png':
                    if (!function_exists('imagecreatefrompng')) {
                        return ConversionResult::failure($sourcePath, 'gd_png_unsupported', 'GD PNG decoder is missing.', 'gd', $originalSize);
                    }
                    $image = @imagecreatefrompng($sourcePath);
                    if ($image) {
                        imagealphablending($image, false);
                        imagesavealpha($image, true);
                    }
                    break;

                case 'image/gif':
                    if (!function_exists('imagecreatefromgif')) {
                        return ConversionResult::failure($sourcePath, 'gd_gif_unsupported', 'GD GIF decoder is missing.', 'gd', $originalSize);
                    }
                    $image = @imagecreatefromgif($sourcePath);
                    if ($image) {
                        $width = imagesx($image);
                        $height = imagesy($image);
                        $truecolor = imagecreatetruecolor($width, $height);
                        if ($truecolor) {
                            imagealphablending($truecolor, false);
                            imagesavealpha($truecolor, true);
                            $transparent = imagecolorallocatealpha($truecolor, 0, 0, 0, 127);
                            imagefilledrectangle($truecolor, 0, 0, $width, $height, $transparent);
                            imagecopy($truecolor, $image, 0, 0, 0, 0, $width, $height);
                            if (PHP_VERSION_ID < 80000 && is_resource($image)) {
                                @imagedestroy($image);
                            }
                            $image = $truecolor;
                        }
                    }
                    break;

                default:
                    return ConversionResult::failure($sourcePath, 'unsupported_format', "Unsupported MIME type: {$mimeType}", 'gd', $originalSize);
            }

            if (!$image) {
                return ConversionResult::failure($sourcePath, 'corrupt_image', 'GD failed to decode source image stream.', 'gd', $originalSize);
            }

            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $success = @imagewebp($image, $outputPath, $quality);

            if (!$success || !file_exists($outputPath) || filesize($outputPath) === 0) {
                if (file_exists($outputPath)) {
                    @unlink($outputPath);
                }
                return ConversionResult::failure($sourcePath, 'encode_failed', "GD {$format} encoding returned false or empty file.", 'gd', $originalSize);
            }

            $convertedSize = (int) filesize($outputPath);
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            return ConversionResult::success($sourcePath, $outputPath, $originalSize, $convertedSize, $durationMs, 'gd');

        } catch (\Throwable $e) {
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
            return ConversionResult::failure($sourcePath, 'exception', $e->getMessage(), 'gd', $originalSize);
        } finally {
            if ($image && PHP_VERSION_ID < 80000 && is_resource($image)) {
                @imagedestroy($image);
            }
        }
    }

    /**
     * Auto-orient JPEG image based on EXIF metadata.
     *
     * @param resource|\GdImage $image GD image resource.
     * @param string $sourcePath Source file path.
     * @return resource|\GdImage
     */
    private function autoOrientJpeg($image, string $sourcePath) {
        $exif = @exif_read_data($sourcePath);
        if (empty($exif['Orientation'])) {
            return $image;
        }

        $orientation = (int) $exif['Orientation'];
        $rotated = null;

        switch ($orientation) {
            case 3:
                $rotated = @imagerotate($image, 180, 0);
                break;
            case 6:
                $rotated = @imagerotate($image, -90, 0);
                break;
            case 8:
                $rotated = @imagerotate($image, 90, 0);
                break;
        }

        if ($rotated) {
            if (PHP_VERSION_ID < 80000 && is_resource($image)) {
                @imagedestroy($image);
            }
            return $rotated;
        }

        return $image;
    }
}

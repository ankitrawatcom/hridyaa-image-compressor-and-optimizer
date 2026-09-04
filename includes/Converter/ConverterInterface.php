<?php
/**
 * Converter Engine Interface.
 *
 * @package NextGen\Converter
 */

namespace NextGen\Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface ConverterInterface {

    /**
     * Check if this engine is supported and ready to encode images.
     *
     * @return bool
     */
    public function isSupported(): bool;

    /**
     * Check if this engine supports a specific target format ('webp' or 'avif').
     *
     * @param string $format Target format ('webp' or 'avif').
     * @return bool
     */
    public function supportsFormat(string $format): bool;

    /**
     * Get unique engine identifier (e.g. 'gd', 'imagick').
     *
     * @return string
     */
    public function getEngineName(): string;

    /**
     * Convert a source image to the requested format.
     *
     * @param string $sourcePath Absolute path to source image.
     * @param string $outputPath Absolute destination path for derivative.
     * @param array $options Conversion parameters (e.g. quality, speed, png_lossless).
     * @param string $format Target format ('webp' or 'avif').
     * @return ConversionResult
     */
    public function convert(string $sourcePath, string $outputPath, array $options = [], string $format = 'webp'): ConversionResult;
}

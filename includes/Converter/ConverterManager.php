<?php
/**
 * Master Converter Manager.
 *
 * Coordinates engine selection, validation, multi-format conversion,
 * atomic file operations, stale derivative detection, and negative-compression guards.
 *
 * @package NextGen\Converter
 */

namespace NextGen\Converter;

use NextGen\Core\Config;
use NextGen\Image\AnimatedGifHandler;
use NextGen\Image\FilenameHelper;
use NextGen\Image\ImageValidator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConverterManager {

    /**
     * Configuration manager.
     *
     * @var Config
     */
    private Config $config;

    /**
     * Registered converter engines.
     *
     * @var ConverterInterface[]
     */
    private array $engines = [];

    /**
     * Constructor.
     *
     * @param Config $config Configuration instance.
     * @param ConverterInterface[] $engines Optional custom engines list.
     */
    public function __construct(Config $config, array $engines = []) {
        $this->config = $config;

        if (empty($engines)) {
            $this->registerDefaultEngines();
        } else {
            foreach ($engines as $engine) {
                $this->registerEngine($engine);
            }
        }

        if (function_exists('do_action')) {
            do_action('nextgen_register_converters', $this);
        }
    }

    /**
     * Register default available engines in preference order (Imagick > GD).
     *
     * @return void
     */
    private function registerDefaultEngines(): void {
        $imagick = new ImagickConverter();
        if ($imagick->isSupported()) {
            $this->registerEngine($imagick);
        }

        $gd = new GdConverter();
        if ($gd->isSupported()) {
            $this->registerEngine($gd);
        }
    }

    /**
     * Register a converter engine.
     *
     * @param ConverterInterface $engine
     * @return void
     */
    public function registerEngine(ConverterInterface $engine): void {
        $this->engines[$engine->getEngineName()] = $engine;
    }

    /**
     * Get registered engines.
     *
     * @return ConverterInterface[]
     */
    public function getEngines(): array {
        return $this->engines;
    }

    /**
     * Get active primary engine for a specific format.
     *
     * @param string $format Target format ('webp' or 'avif').
     * @return ConverterInterface|null
     */
    public function getActiveEngine(string $format = 'webp'): ?ConverterInterface {
        foreach ($this->engines as $engine) {
            if ($engine->supportsFormat($format)) {
                return $engine;
            }
        }
        return null;
    }

    /**
     * Check if a format is supported by any registered engine.
     *
     * @param string $format Target format ('webp' or 'avif').
     * @return bool
     */
    public function isFormatSupported(string $format): bool {
        return $this->getActiveEngine($format) !== null;
    }

    /**
     * Convert an image file with complete safety guards and stale detection.
     *
     * @param string $sourcePath Path to source image.
     * @param string|null $outputPath Optional destination path. If null, generated automatically.
     * @param array $options Optional override options (quality, speed, force, etc.).
     * @param string $format Target format ('webp' or 'avif').
     * @return ConversionResult
     */
    public function convert(string $sourcePath, ?string $outputPath = null, array $options = [], string $format = 'webp'): ConversionResult {
        $format = strtolower($format);
        if ($format !== 'avif' && $format !== 'webp') {
            $format = 'webp';
        }

        $originalSize = (int) @filesize($sourcePath);

        // 1. Path Safety & Existence Validation
        if (!ImageValidator::isPathSafe($sourcePath)) {
            return ConversionResult::failure($sourcePath, 'invalid_path', 'Source path is outside allowed boundaries.', 'none', $originalSize);
        }

        $attachmentId = (int) ($options['attachment_id'] ?? 0);

        $maxDimension = (int) $this->config->get('max_image_dimension', 4096);
        [$isValid, $reason, $mimeType, $width, $height] = ImageValidator::validate($sourcePath, $maxDimension);

        if (!$isValid) {
            if ($attachmentId > 0 && class_exists('\NextGen\Admin\FailedQueueManager')) {
                $category = ($reason === 'invalid_image_data' || $reason === 'unsupported_format') ? 'corrupt_source' : (string) $reason;
                \NextGen\Admin\FailedQueueManager::recordFailure($attachmentId, $format, $category, "Image validation failed: {$reason}", $sourcePath);
            }
            return ConversionResult::failure($sourcePath, $reason, "Image validation failed: {$reason}", 'none', $originalSize);
        }

        // 2. Animated GIF Guard
        if ($mimeType === 'image/gif' && AnimatedGifHandler::isAnimated($sourcePath)) {
            return ConversionResult::failure(
                $sourcePath,
                'skipped_animated_gif',
                'Animated GIFs are safely skipped to prevent destroying animation.',
                'none',
                $originalSize
            );
        }

        // 3. Derive destination path
        if (empty($outputPath)) {
            $outputPath = FilenameHelper::generateDerivativePath($sourcePath, $format);
        }

        if (!ImageValidator::isPathSafe($outputPath)) {
            return ConversionResult::failure($sourcePath, 'invalid_output_path', 'Output path is outside allowed boundaries.', 'none', $originalSize);
        }

        // 4. Duplicate / Stale Check (Reuse only if derivative exists, is non-empty, and is not older than source)
        $force = !empty($options['force']);
        if (!$force && file_exists($outputPath) && filesize($outputPath) > 0) {
            $sourceMtime = @filemtime($sourcePath);
            $outputMtime = @filemtime($outputPath);

            // If timestamps are valid and output is at least as fresh as source, reuse cached derivative
            if ($sourceMtime !== false && $outputMtime !== false && $outputMtime >= $sourceMtime) {
                $convertedSize = (int) filesize($outputPath);
                return ConversionResult::success($sourcePath, $outputPath, $originalSize, $convertedSize, 0.0, 'cached');
            }
        }

        // 5. Select Engine for requested format
        $engine = $this->getActiveEngine($format);
        if (!$engine) {
            return ConversionResult::failure(
                $sourcePath,
                'no_engine_available',
                "No supported {$format} encoding library is available on this server.",
                'none',
                $originalSize
            );
        }

        // 6. Prepare conversion parameters using QualityPresetManager
        $presetQuality = class_exists('\NextGen\Admin\QualityPresetManager')
            ? \NextGen\Admin\QualityPresetManager::getQuality($format)
            : (($format === 'avif') ? (int) $this->config->get('avif_quality', 75) : (int) $this->config->get('webp_quality', 82));

        $conversionOptions = array_merge([
            'quality'               => $presetQuality,
            'speed'                 => (int) $this->config->get('avif_speed', 6),
            'png_lossless'          => (bool) $this->config->get('png_lossless', false),
            'keep_larger_converted' => (bool) $this->config->get('keep_larger_converted', false),
            'mime_type'             => $mimeType,
        ], $options);

        $attachmentId = (int) ($options['attachment_id'] ?? 0);

        // 7. Atomic Execution using temporary file
        $tempPath = $outputPath . '.tmp.' . bin2hex(random_bytes(4));

        $result = $engine->convert($sourcePath, $tempPath, $conversionOptions, $format);

        // Fallback: If primary engine failed, try secondary engine
        if (!$result->isSuccess() && count($this->engines) > 1) {
            foreach ($this->engines as $fallbackEngine) {
                if ($fallbackEngine->getEngineName() !== $engine->getEngineName() && $fallbackEngine->supportsFormat($format)) {
                    $result = $fallbackEngine->convert($sourcePath, $tempPath, $conversionOptions, $format);
                    if ($result->isSuccess()) {
                        break;
                    }
                }
            }
        }

        if (!$result->isSuccess()) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
            if ($attachmentId > 0 && class_exists('\NextGen\Admin\FailedQueueManager')) {
                \NextGen\Admin\FailedQueueManager::recordFailure($attachmentId, $format, $result->getErrorCode(), $result->getErrorMessage(), $sourcePath);
            }
            return $result;
        }

        $convertedSize = (int) @filesize($tempPath);

        // 8. Negative Compression Guard
        if (!$conversionOptions['keep_larger_converted'] && $convertedSize >= $originalSize) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
            return ConversionResult::failure(
                $sourcePath,
                'skipped_larger',
                "Converted {$format} size ({$convertedSize} B) is larger than or equal to original ({$originalSize} B). Discarded.",
                $result->getEngine(),
                $originalSize
            );
        }

        // 9. Atomic rename
        $renamed = @rename($tempPath, $outputPath);
        if (!$renamed) {
            $copied = @copy($tempPath, $outputPath);
            @unlink($tempPath);
            if (!$copied) {
                $failResult = ConversionResult::failure($sourcePath, 'file_write_failed', 'Failed to move converted temporary file to destination path.', $result->getEngine(), $originalSize);
                if ($attachmentId > 0 && class_exists('\NextGen\Admin\FailedQueueManager')) {
                    \NextGen\Admin\FailedQueueManager::recordFailure($attachmentId, $format, 'file_write_failed', 'Failed to write output file.', $sourcePath);
                }
                return $failResult;
            }
        }

        @chmod($outputPath, 0644);

        // 10. Record Statistics and Resolve Failed Queue
        if ($attachmentId > 0) {
            if (class_exists('\NextGen\Admin\StatsManager')) {
                \NextGen\Admin\StatsManager::recordConversion($attachmentId, $format, $originalSize, $convertedSize, $result->getEngine(), (int) $conversionOptions['quality']);
            }
            if (class_exists('\NextGen\Admin\FailedQueueManager')) {
                \NextGen\Admin\FailedQueueManager::markSucceeded($attachmentId, $format);
            }
        }

        return ConversionResult::success(
            $sourcePath,
            $outputPath,
            $originalSize,
            $convertedSize,
            $result->getDurationMs(),
            $result->getEngine()
        );
    }
}

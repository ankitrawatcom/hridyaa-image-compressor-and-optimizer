<?php
/**
 * Conversion Result Value Object.
 *
 * Encapsulates the complete outcome of an image conversion operation.
 *
 * @package NextGen\Converter
 */

namespace NextGen\Converter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ConversionResult {

    private bool $success;
    private string $sourcePath;
    private string $outputPath;
    private int $originalSize;
    private int $convertedSize;
    private float $durationMs;
    private string $engine;
    private ?string $errorCode;
    private ?string $errorMessage;

    /**
     * Constructor.
     */
    public function __construct(
        bool $success,
        string $sourcePath,
        string $outputPath = '',
        int $originalSize = 0,
        int $convertedSize = 0,
        float $durationMs = 0.0,
        string $engine = 'none',
        ?string $errorCode = null,
        ?string $errorMessage = null
    ) {
        $this->success = $success;
        $this->sourcePath = $sourcePath;
        $this->outputPath = $outputPath;
        $this->originalSize = $originalSize;
        $this->convertedSize = $convertedSize;
        $this->durationMs = $durationMs;
        $this->engine = $engine;
        $this->errorCode = $errorCode ?? ($success ? 'success' : 'unknown_error');
        $this->errorMessage = $errorMessage;
    }

    /**
     * Factory for successful conversion.
     */
    public static function success(
        string $sourcePath,
        string $outputPath,
        int $originalSize,
        int $convertedSize,
        float $durationMs,
        string $engine
    ): self {
        return new self(
            true,
            $sourcePath,
            $outputPath,
            $originalSize,
            $convertedSize,
            $durationMs,
            $engine,
            'success',
            null
        );
    }

    /**
     * Factory for failed or skipped conversion.
     */
    public static function failure(
        string $sourcePath,
        string $errorCode,
        ?string $errorMessage = null,
        string $engine = 'none',
        int $originalSize = 0
    ): self {
        return new self(
            false,
            $sourcePath,
            '',
            $originalSize,
            0,
            0.0,
            $engine,
            $errorCode,
            $errorMessage
        );
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getSourcePath(): string {
        return $this->sourcePath;
    }

    public function getOutputPath(): string {
        return $this->outputPath;
    }

    public function getOriginalSize(): int {
        return $this->originalSize;
    }

    public function getConvertedSize(): int {
        return $this->convertedSize;
    }

    public function getDurationMs(): float {
        return $this->durationMs;
    }

    public function getEngine(): string {
        return $this->engine;
    }

    public function getErrorCode(): ?string {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string {
        return $this->errorMessage;
    }

    /**
     * Get saved bytes (original - converted).
     *
     * @return int
     */
    public function getSavedBytes(): int {
        if (!$this->success || $this->originalSize <= 0 || $this->convertedSize <= 0) {
            return 0;
        }
        return max(0, $this->originalSize - $this->convertedSize);
    }

    /**
     * Get savings percentage.
     *
     * @return float Percentage saved (e.g. 35.4%).
     */
    public function getSavingsPercentage(): float {
        if (!$this->success || $this->originalSize <= 0 || $this->convertedSize <= 0) {
            return 0.0;
        }
        if ($this->originalSize <= $this->convertedSize) {
            return 0.0;
        }
        return round((($this->originalSize - $this->convertedSize) / $this->originalSize) * 100, 1);
    }

    /**
     * Export to associative array.
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'success'            => $this->success,
            'source_path'        => $this->sourcePath,
            'output_path'        => $this->outputPath,
            'original_size'      => $this->originalSize,
            'converted_size'     => $this->convertedSize,
            'saved_bytes'        => $this->getSavedBytes(),
            'savings_percentage' => $this->getSavingsPercentage(),
            'duration_ms'        => $this->durationMs,
            'engine'             => $this->engine,
            'error_code'         => $this->errorCode,
            'error_message'      => $this->errorMessage,
        ];
    }
}

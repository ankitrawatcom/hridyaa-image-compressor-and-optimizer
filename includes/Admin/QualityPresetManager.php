<?php
/**
 * Quality Preset Manager for NextGen Image Optimizer.
 *
 * Provides cross-codec compression presets (High, Balanced, Aggressive)
 * and seamless backward-compatible settings migration from v1.0.0.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class QualityPresetManager {

    public const PRESET_HIGH       = 'high';
    public const PRESET_BALANCED   = 'balanced';
    public const PRESET_AGGRESSIVE = 'aggressive';

    private const PRESETS = [
        self::PRESET_HIGH => [
            'name'         => 'High Quality',
            'description'  => 'Maximum visual fidelity (Photography, Art, Portfolios).',
            'webp_quality' => 90,
            'avif_quality' => 85,
            'avg_savings'  => '35%–45%',
        ],
        self::PRESET_BALANCED => [
            'name'         => 'Balanced (Recommended)',
            'description'  => 'Optimal balance of speed and clarity (Blogs, Stores, Business).',
            'webp_quality' => 82,
            'avif_quality' => 75,
            'avg_savings'  => '55%–65%',
        ],
        self::PRESET_AGGRESSIVE => [
            'name'         => 'Aggressive',
            'description'  => 'Maximum byte reduction (High-Traffic, News, Mobile-First).',
            'webp_quality' => 75,
            'avif_quality' => 65,
            'avg_savings'  => '70%–80%',
        ],
    ];

    /**
     * Get all available preset definitions.
     *
     * @return array<string, array{name:string, description:string, webp_quality:int, avif_quality:int, avg_savings:string}>
     */
    public static function getPresets(): array {
        return self::PRESETS;
    }

    /**
     * Get currently active compression preset identifier.
     *
     * @return string 'high' | 'balanced' | 'aggressive'
     */
    public static function getActivePreset(): string {
        $preset = (string) get_option('nextgen_preset', self::PRESET_BALANCED);
        if (!isset(self::PRESETS[$preset])) {
            return self::PRESET_BALANCED;
        }
        return $preset;
    }

    /**
     * Normalize and validate a candidate preset string.
     *
     * @param string $preset
     * @return string Valid preset identifier ('high', 'balanced', 'aggressive').
     */
    public static function normalizePreset(string $preset): string {
        return isset(self::PRESETS[$preset]) ? $preset : self::PRESET_BALANCED;
    }

    /**
     * Get numeric encoder quality for a target format under a specified preset.
     *
     * @param string $format 'webp' or 'avif'
     * @param string|null $preset Optional preset identifier (defaults to active preset)
     * @return int
     */
    public static function getQuality(string $format, ?string $preset = null): int {
        $selectedPreset = $preset ?? self::getActivePreset();
        if (!isset(self::PRESETS[$selectedPreset])) {
            $selectedPreset = self::PRESET_BALANCED;
        }

        $format = strtolower($format);
        if ($format === 'avif') {
            return (int) (self::PRESETS[$selectedPreset]['avif_quality'] ?? 75);
        }

        return (int) (self::PRESETS[$selectedPreset]['webp_quality'] ?? 82);
    }

    /**
     * Migrate settings from v1.0.0 to v1.1.0 idempotently without destructive recompression.
     *
     * @return void
     */
    public static function migrateSettings(): void {
        $existingPreset = get_option('nextgen_preset', null);
        if ($existingPreset !== null) {
            return; // Already migrated
        }

        $oldQuality = (int) get_option('nextgen_quality', 82);
        if ($oldQuality >= 88) {
            $newPreset = self::PRESET_HIGH;
        } elseif ($oldQuality <= 76) {
            $newPreset = self::PRESET_AGGRESSIVE;
        } else {
            $newPreset = self::PRESET_BALANCED;
        }

        update_option('nextgen_preset', $newPreset);
        update_option('nextgen_webp_quality', self::PRESETS[$newPreset]['webp_quality']);
        update_option('nextgen_avif_quality', self::PRESETS[$newPreset]['avif_quality']);
    }
}

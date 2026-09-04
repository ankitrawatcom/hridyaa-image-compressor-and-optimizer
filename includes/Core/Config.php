<?php
/**
 * Configuration & Settings Management.
 *
 * @package NextGen\Core
 */

namespace NextGen\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Config {

    /**
     * Option key in wp_options.
     */
    public const OPTION_NAME = 'nextgen_image_optimizer_options';

    /**
     * Default settings.
     */
    private const DEFAULTS = [
        'optimization_format'  => 'avif_webp', // 'webp', 'avif_webp', 'avif'
        'webp_quality'         => 82,
        'avif_quality'         => 68,  // Calibrated for AVIF efficiency (Q=68 matches JPEG Q=85-90)
        'avif_speed'           => 6,   // AVIF CPU effort (0-10, 6 is optimal balance for servers)
        'auto_convert_uploads' => true,
        'auto_convert_avif'    => true,
        'convert_thumbnails'   => true,
        'keep_larger_converted'=> false,
        'delivery_enabled'     => true,
        'png_lossless'         => false,
        'max_image_dimension'  => 4096, // Maximum width/height in px to guard memory
    ];

    /**
     * Cached options in memory.
     *
     * @var array|null
     */
    private ?array $options = null;

    /**
     * Get all options.
     *
     * @return array
     */
    public function getOptions(): array {
        if ($this->options === null) {
            $saved = get_option(self::OPTION_NAME, []);
            if (!is_array($saved)) {
                $saved = [];
            }
            $this->options = wp_parse_args($saved, self::DEFAULTS);
        }
        return $this->options;
    }

    /**
     * Get a single setting by key.
     *
     * @param string $key Setting key.
     * @param mixed $default Fallback value if not found.
     * @return mixed
     */
    public function get(string $key, $default = null) {
        $options = $this->getOptions();
        return $options[$key] ?? ($default ?? self::DEFAULTS[$key] ?? null);
    }

    /**
     * Update settings.
     *
     * @param array $newSettings Array of new settings to merge.
     * @return bool True if updated or unchanged.
     */
    public function updateOptions(array $newSettings): bool {
        $current = $this->getOptions();
        $sanitized = $this->sanitizeOptions($newSettings, $current);
        $updated = update_option(self::OPTION_NAME, $sanitized);
        $this->options = $sanitized;
        return $updated;
    }

    /**
     * Sanitize settings array strictly.
     *
     * @param array $input Raw input data.
     * @param array $current Current settings.
     * @return array Sanitized settings.
     */
    public function sanitizeOptions(array $input, array $current = []): array {
        $sanitized = $current ?: self::DEFAULTS;

        if (isset($input['webp_quality'])) {
            $quality = (int) $input['webp_quality'];
            $sanitized['webp_quality'] = max(10, min(100, $quality));
        }

        if (isset($input['avif_quality'])) {
            $avifQuality = (int) $input['avif_quality'];
            $sanitized['avif_quality'] = max(10, min(100, $avifQuality));
        }

        if (isset($input['avif_speed'])) {
            $speed = (int) $input['avif_speed'];
            $sanitized['avif_speed'] = max(0, min(10, $speed));
        }

        if (isset($input['auto_convert_uploads'])) {
            $sanitized['auto_convert_uploads'] = (bool) $input['auto_convert_uploads'];
        }

        if (isset($input['auto_convert_avif'])) {
            $sanitized['auto_convert_avif'] = (bool) $input['auto_convert_avif'];
        }

        if (isset($input['convert_thumbnails'])) {
            $sanitized['convert_thumbnails'] = (bool) $input['convert_thumbnails'];
        }

        if (isset($input['keep_larger_converted'])) {
            $sanitized['keep_larger_converted'] = (bool) $input['keep_larger_converted'];
        }

        if (isset($input['delivery_enabled'])) {
            $sanitized['delivery_enabled'] = (bool) $input['delivery_enabled'];
        }

        if (isset($input['png_lossless'])) {
            $sanitized['png_lossless'] = (bool) $input['png_lossless'];
        }

        if (isset($input['max_image_dimension'])) {
            $dim = (int) $input['max_image_dimension'];
            $sanitized['max_image_dimension'] = max(500, min(10000, $dim));
        }

        return $sanitized;
    }

    /**
     * Reset options to defaults.
     *
     * @return bool
     */
    public function resetToDefaults(): bool {
        $this->options = self::DEFAULTS;
        return update_option(self::OPTION_NAME, self::DEFAULTS);
    }
}

<?php
/**
 * Feature Entitlement & Capability Abstraction.
 *
 * Provides a clean internal abstraction for feature gates and commercial entitlement.
 *
 * @package NextGen\Core
 */

namespace NextGen\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Features {

    /**
     * Check if AVIF conversion feature is enabled.
     *
     * In the Free plugin, AVIF is DISABLED by default ($enabled = false).
     * Pro plugin enables AVIF only upon valid cryptographic entitlement.
     *
     * @return bool
     */
    public static function isAvifEnabled(): bool {
        $enabled = false;

        if (defined('NEXTGEN_ENABLE_AVIF')) {
            $enabled = (bool) NEXTGEN_ENABLE_AVIF;
        }

        if (function_exists('apply_filters')) {
            $enabled = (bool) apply_filters('nextgen_enable_avif', $enabled);
        }

        return $enabled;
    }

    /**
     * Check if WebP conversion feature is enabled (always free).
     *
     * @return bool
     */
    public static function isWebpEnabled(): bool {
        return true;
    }
}

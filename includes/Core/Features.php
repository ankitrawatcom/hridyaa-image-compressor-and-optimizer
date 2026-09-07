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

        if (function_exists('apply_filters')) {
            $enabled = (bool) apply_filters('nextgen_enable_avif', $enabled);
        }

        if (defined('NEXTGEN_ENABLE_AVIF')) {
            $enabled = $enabled || (bool) NEXTGEN_ENABLE_AVIF;
        }

        return $enabled;
    }

    /**
     * Check if Hridyaa Pro is active and cryptographically verified.
     *
     * Queries authoritative commercial entitlement state directly.
     *
     * @return bool True if Pro license is active and cryptographically verified.
     */
    public static function isProActive(): bool {
        $active = false;

        if (function_exists('apply_filters')) {
            $active = (bool) apply_filters('nextgen_pro_is_active', false);
        }

        // Backward compatibility fallback if ProEntitlement only hooks nextgen_enable_avif
        if (!$active && function_exists('apply_filters')) {
            $active = (bool) apply_filters('nextgen_enable_avif', false);
        }

        return $active;
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

<?php
/**
 * System Capability Detector.
 *
 * Reliably probes and verifies image processing extensions, WebP and AVIF encoding/decoding support.
 *
 * @package NextGen\Support
 */

namespace NextGen\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SystemDetector {

    /**
     * Cache transient key.
     */
    private const TRANSIENT_KEY = 'nextgen_system_capabilities';

    /**
     * Cache TTL (24 hours).
     */
    private const CACHE_TTL = 86400;

    /**
     * Runtime capability cache.
     *
     * @var array|null
     */
    private ?array $capabilities = null;

    /**
     * Get system capabilities (cached).
     *
     * @param bool $forceRefresh Force re-probing.
     * @return array
     */
    public function getCapabilities(bool $forceRefresh = false): array {
        if (!$forceRefresh && $this->capabilities !== null) {
            return $this->capabilities;
        }

        if (!$forceRefresh && function_exists('get_transient')) {
            $cached = get_transient(self::TRANSIENT_KEY);
            if (is_array($cached)) {
                $this->capabilities = $cached;
                return $this->capabilities;
            }
        }

        $this->capabilities = $this->probeCapabilities();

        if (function_exists('set_transient')) {
            set_transient(self::TRANSIENT_KEY, $this->capabilities, self::CACHE_TTL);
        }

        return $this->capabilities;
    }

    /**
     * Probe system capabilities comprehensively.
     *
     * @return array
     */
    public function probeCapabilities(): array {
        $gd = $this->probeGd();
        $imagick = $this->probeImagick();

        $primaryWebpEngine = 'none';
        $webpSupported = false;

        if ($imagick['webp_encode']) {
            $primaryWebpEngine = 'imagick';
            $webpSupported = true;
        } elseif ($gd['webp_encode']) {
            $primaryWebpEngine = 'gd';
            $webpSupported = true;
        }

        $primaryAvifEngine = 'none';
        $avifSupported = false;

        if ($imagick['avif_encode']) {
            $primaryAvifEngine = 'imagick';
            $avifSupported = true;
        } elseif ($gd['avif_encode']) {
            $primaryAvifEngine = 'gd';
            $avifSupported = true;
        }

        return [
            'php_version'         => PHP_VERSION,
            'webp_supported'      => $webpSupported,
            'primary_engine'      => $primaryWebpEngine,
            'primary_webp_engine' => $primaryWebpEngine,
            'avif_supported'      => $avifSupported,
            'primary_avif_engine' => $primaryAvifEngine,
            'gd'                  => $gd,
            'imagick'             => $imagick,
            'memory_limit'        => $this->getMemoryLimit(),
            'max_execution_time'  => (int) ini_get('max_execution_time'),
            'probed_at'           => time(),
        ];
    }

    /**
     * Probe GD extension for WebP and AVIF.
     *
     * @return array
     */
    private function probeGd(): array {
        $loaded = extension_loaded('gd') && function_exists('gd_info');

        if (!$loaded) {
            return [
                'installed'   => false,
                'version'     => null,
                'webp_decode' => false,
                'webp_encode' => false,
                'avif_decode' => false,
                'avif_encode' => false,
                'formats'     => [],
                'status'      => 'Extension not loaded',
            ];
        }

        $info = gd_info();
        $version = $info['GD Version'] ?? 'unknown';
        $formats = [];

        if (!empty($info['JPEG Support'])) {
            $formats[] = 'JPEG';
        }
        if (!empty($info['PNG Support'])) {
            $formats[] = 'PNG';
        }
        if (!empty($info['GIF Read Support'])) {
            $formats[] = 'GIF';
        }

        $webpDecode = !empty($info['WebP Support']) && function_exists('imagecreatefromwebp');
        $webpEncode = !empty($info['WebP Support']) && function_exists('imagewebp');

        // Micro-probe WebP
        if ($webpEncode) {
            $webpEncode = $this->verifyGdWebpEncoding();
            if ($webpEncode) {
                $formats[] = 'WEBP';
            }
        }

        $avifDecode = !empty($info['AVIF Support']) && function_exists('imagecreatefromavif');
        $avifEncode = !empty($info['AVIF Support']) && function_exists('imageavif');

        // Micro-probe AVIF
        if ($avifEncode) {
            $avifEncode = $this->verifyGdAvifEncoding();
            if ($avifEncode) {
                $formats[] = 'AVIF';
            }
        }

        $statusParts = [];
        if ($webpEncode) {
            $statusParts[] = 'WebP Ready';
        }
        if ($avifEncode) {
            $statusParts[] = 'AVIF Ready';
        }

        $status = !empty($statusParts) ? implode(' & ', $statusParts) : 'WebP/AVIF unsupported';

        return [
            'installed'   => true,
            'version'     => $version,
            'webp_decode' => $webpDecode,
            'webp_encode' => $webpEncode,
            'avif_decode' => $avifDecode,
            'avif_encode' => $avifEncode,
            'formats'     => $formats,
            'status'      => $status,
        ];
    }

    /**
     * Probe ImageMagick extension for WebP and AVIF.
     *
     * @return array
     */
    private function probeImagick(): array {
        $loaded = extension_loaded('imagick') && class_exists('\Imagick');

        if (!$loaded) {
            return [
                'installed'   => false,
                'version'     => null,
                'webp_decode' => false,
                'webp_encode' => false,
                'avif_decode' => false,
                'avif_encode' => false,
                'formats'     => [],
                'status'      => 'Extension not loaded',
            ];
        }

        $version = 'unknown';
        $formats = ['JPEG', 'PNG', 'GIF'];
        $webpDecode = false;
        $webpEncode = false;
        $avifDecode = false;
        $avifEncode = false;

        try {
            $imagick = new \Imagick();
            $versionInfo = $imagick->getVersion();
            $version = $versionInfo['versionString'] ?? 'unknown';

            // Check WebP
            $webpFormats = \Imagick::queryFormats('WEBP');
            $hasWebp = !empty($webpFormats) && in_array('WEBP', array_map('strtoupper', $webpFormats), true);
            if ($hasWebp) {
                $webpEncode = $this->verifyImagickWebpEncoding();
                $webpDecode = $webpEncode;
                if ($webpEncode) {
                    $formats[] = 'WEBP';
                }
            }

            // Check AVIF
            $avifFormats = \Imagick::queryFormats('AVIF');
            $hasAvif = !empty($avifFormats) && in_array('AVIF', array_map('strtoupper', $avifFormats), true);
            if ($hasAvif) {
                $avifEncode = $this->verifyImagickAvifEncoding();
                $avifDecode = $avifEncode;
                if ($avifEncode) {
                    $formats[] = 'AVIF';
                }
            }

        } catch (\Throwable $e) {
            return [
                'installed'   => true,
                'version'     => $version,
                'webp_decode' => false,
                'webp_encode' => false,
                'avif_decode' => false,
                'avif_encode' => false,
                'formats'     => [],
                'status'      => 'Error: ' . $e->getMessage(),
            ];
        }

        $statusParts = [];
        if ($webpEncode) {
            $statusParts[] = 'WebP Ready';
        }
        if ($avifEncode) {
            $statusParts[] = 'AVIF Ready';
        }

        $status = !empty($statusParts) ? implode(' & ', $statusParts) : 'WebP/AVIF delegate missing';

        return [
            'installed'   => true,
            'version'     => $version,
            'webp_decode' => $webpDecode,
            'webp_encode' => $webpEncode,
            'avif_decode' => $avifDecode,
            'avif_encode' => $avifEncode,
            'formats'     => $formats,
            'status'      => $status,
        ];
    }

    /**
     * Non-destructive GD WebP micro-probe.
     *
     * @return bool
     */
    private function verifyGdWebpEncoding(): bool {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
            return false;
        }

        try {
            $img = @imagecreatetruecolor(1, 1);
            if (!$img) {
                return false;
            }

            ob_start();
            $success = @imagewebp($img, null, 80);
            $data = ob_get_clean();

            if (PHP_VERSION_ID < 80000 && is_resource($img)) {
                @imagedestroy($img);
            }

            if (!$success || empty($data)) {
                return false;
            }

            // Check RIFF header and WEBP signature
            return substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Non-destructive GD AVIF micro-probe.
     *
     * @return bool
     */
    private function verifyGdAvifEncoding(): bool {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imageavif')) {
            return false;
        }

        try {
            $img = @imagecreatetruecolor(1, 1);
            if (!$img) {
                return false;
            }

            ob_start();
            $success = @imageavif($img, null, 68);
            $data = ob_get_clean();

            if (PHP_VERSION_ID < 80000 && is_resource($img)) {
                @imagedestroy($img);
            }

            if (!$success || empty($data) || strlen($data) < 12) {
                return false;
            }

            // Check ISOBMFF ftyp box with 'avif' brand
            $ftyp = substr($data, 4, 8);
            return strpos($ftyp, 'ftyp') === 0 && strpos($data, 'avif') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Non-destructive Imagick WebP micro-probe.
     *
     * @return bool
     */
    private function verifyImagickWebpEncoding(): bool {
        if (!class_exists('\Imagick') || !class_exists('\ImagickPixel')) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->newImage(1, 1, new \ImagickPixel('white'));
            $imagick->setImageFormat('webp');
            $data = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

            if (empty($data)) {
                return false;
            }

            return substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Non-destructive Imagick AVIF micro-probe.
     *
     * @return bool
     */
    private function verifyImagickAvifEncoding(): bool {
        if (!class_exists('\Imagick') || !class_exists('\ImagickPixel')) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->newImage(1, 1, new \ImagickPixel('white'));
            $imagick->setImageFormat('avif');
            $data = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();

            if (empty($data) || strlen($data) < 12) {
                return false;
            }

            return strpos($data, 'ftyp') !== false && strpos($data, 'avif') !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Read effective memory limit in human-readable and byte form.
     *
     * @return array
     */
    private function getMemoryLimit(): array {
        $ini = ini_get('memory_limit');
        if (!$ini || $ini === '-1') {
            return ['raw' => -1, 'formatted' => 'Unlimited'];
        }

        $unit = strtolower(substr($ini, -1));
        $val = (int) $ini;
        switch ($unit) {
            case 'g':
                $bytes = $val * 1024 * 1024 * 1024;
                break;
            case 'm':
                $bytes = $val * 1024 * 1024;
                break;
            case 'k':
                $bytes = $val * 1024;
                break;
            default:
                $bytes = $val;
                break;
        }

        return [
            'raw'       => $bytes,
            'formatted' => $ini,
        ];
    }

    /**
     * Clear cached capabilities transient.
     *
     * @return void
     */
    public function clearCache(): void {
        $this->capabilities = null;
        if (function_exists('delete_transient')) {
            delete_transient(self::TRANSIENT_KEY);
        }
    }
}

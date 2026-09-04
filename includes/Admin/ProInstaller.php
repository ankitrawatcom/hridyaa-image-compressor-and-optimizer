<?php
/**
 * Pro Installer & Secure Activation Engine.
 *
 * Implements Model B: Authenticated, cryptographically verified automatic download,
 * installation, and activation of the commercial NextGen Image Optimizer Pro component.
 *
 * @package NextGen\Admin
 */

namespace NextGen\Admin;

use Exception;

if ( ! defined( 'ABSPATH' ) && ! defined( 'NEXTGEN_TESTING' ) ) {
    exit;
}

class ProInstaller {

    /**
     * Bundled Ed25519 Public Key for Signature Verification.
     * PUBLIC key only — zero private keys exist in the WordPress client.
     */
    public const PUBLIC_KEY_B64 = 'XKv0MD15tz29OEYc09N4i5oJiztVwkGlTAplxc7i2WA=';

    public const OPTION_INSTALLATION_ID = 'nextgen_pro_installation_id';
    public const PRO_PLUGIN_SLUG = 'nextgen-image-optimizer-pro/nextgen-image-optimizer-pro.php';

    private string $apiBaseUrl;
    private ?string $overridePublicKey = null;
    /** @var callable|null */
    private $customHttpHandler = null;

    public function __construct(?string $apiBaseUrl = null, ?string $overridePublicKey = null, ?callable $customHttpHandler = null) {
        if ($apiBaseUrl === null) {
            if (defined('NEXTGEN_LICENSE_API_URL') && !empty(NEXTGEN_LICENSE_API_URL)) {
                $apiBaseUrl = NEXTGEN_LICENSE_API_URL;
            } elseif (function_exists('apply_filters')) {
                $apiBaseUrl = (string) apply_filters('nextgen_license_api_url', 'https://license.ankitrawat.com');
            } else {
                $apiBaseUrl = 'https://license.ankitrawat.com';
            }
        }
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->overridePublicKey = $overridePublicKey;
        $this->customHttpHandler = $customHttpHandler;
    }

    /**
     * Execute end-to-end license authentication, Pro package download, verification, installation, and activation.
     *
     * @param string $licenseKey
     * @return array [bool 'success', string 'code', string 'message']
     */
    public function activateAndInstall(string $licenseKey): array {
        $cleanKey = trim($licenseKey);
        if (empty($cleanKey)) {
            return [
                'success' => false,
                'code'    => 'empty_key',
                'message' => 'Please enter a valid Pro license key.',
            ];
        }

        if (!preg_match('/^NGPRO-[0-9A-Za-z]{4}-[0-9A-Za-z]{4}-[0-9A-Za-z]{4}-[0-9A-Za-z]{4}$/', $cleanKey)) {
            return [
                'success' => false,
                'code'    => 'malformed_key',
                'message' => 'The license key format is invalid. Please check your purchase confirmation email.',
            ];
        }

        $domain = $this->getDomain();
        $installationId = $this->getInstallationId();
        $isStaging = $this->isStagingDomain($domain);

        // 1. Authenticate with Remote License Backend
        $authResponse = $this->post('/v1/license/activate', [
            'license_key'     => $cleanKey,
            'installation_id' => $installationId,
            'domain'          => $domain,
            'is_staging'      => $isStaging,
        ]);

        if (!$authResponse['success']) {
            $msg = $this->mapErrorMessage($authResponse['code'] ?? 'unknown_error', $authResponse['message'] ?? '');
            return [
                'success' => false,
                'code'    => $authResponse['code'] ?? 'auth_failed',
                'message' => $msg,
            ];
        }

        $data = $authResponse['data'] ?? [];
        $payload = $data['payload'] ?? null;
        $signature = $data['signature'] ?? '';

        if (!is_array($payload) || empty($signature)) {
            return [
                'success' => false,
                'code'    => 'invalid_server_response',
                'message' => 'License server returned an invalid response structure.',
            ];
        }

        // Check if server returned inactive or error status
        if (($payload['status'] ?? '') !== 'active' || isset($payload['error']) || isset($payload['error_code'])) {
            $errCode = (string) ($payload['error_code'] ?? ($payload['error'] ?? 'license_inactive'));
            $errMsg = $this->mapErrorMessage($errCode, (string) ($payload['message'] ?? ''));
            return [
                'success' => false,
                'code'    => $errCode,
                'message' => $errMsg,
            ];
        }

        // 2. Cryptographic Ed25519 Signature Verification
        if (!$this->verifySignature($payload, $signature)) {
            return [
                'success' => false,
                'code'    => 'signature_invalid',
                'message' => 'Security Error: Cryptographic signature verification failed from the licensing server.',
            ];
        }

        // 3. Check if Pro is already installed on filesystem
        $proFile = self::PRO_PLUGIN_SLUG;
        $proInstalled = false;
        if (defined('WP_PLUGIN_DIR')) {
            $proAbsPath = WP_PLUGIN_DIR . '/' . $proFile;
            $proInstalled = file_exists($proAbsPath) && is_file($proAbsPath);
        }

        if ($proInstalled) {
            if (function_exists('activate_plugin') && function_exists('is_plugin_active') && !is_plugin_active($proFile)) {
                $actResult = activate_plugin($proFile);
                if (is_wp_error($actResult)) {
                    return [
                        'success' => false,
                        'code'    => 'activation_failed',
                        'message' => 'Pro component is installed, but activation failed: ' . $actResult->get_error_message(),
                    ];
                }
            }

            $this->saveLicenseState($cleanKey, $payload, $signature);
            return [
                'success' => true,
                'code'    => 'activated_existing',
                'message' => 'Hridyaa Image Compressor and Optimizer Pro activated successfully!',
            ];
        }

        // 4. Download and Install Pro Package
        $downloadToken = (string) ($data['download_token'] ?? '');
        $expectedSha256 = strtolower(trim((string) ($data['package_sha256'] ?? '')));

        if (empty($downloadToken) || empty($expectedSha256)) {
            return [
                'success' => false,
                'code'    => 'missing_download_auth',
                'message' => 'License was verified, but download authorization was not provided by the server. Please contact support.',
            ];
        }

        $downloadUrl = $this->apiBaseUrl . '/v1/download/pro?token=' . rawurlencode($downloadToken);

        $tempFile = null;
        if ($this->customHttpHandler !== null) {
            $downloadResult = ($this->customHttpHandler)('DOWNLOAD', ['url' => $downloadUrl]);
            if (is_array($downloadResult) && isset($downloadResult['temp_file'])) {
                $tempFile = $downloadResult['temp_file'];
            } elseif (is_string($downloadResult)) {
                $tempFile = $downloadResult;
            }
        } elseif (function_exists('download_url')) {
            if (!function_exists('wp_tempnam') && defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/file.php')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $tempFile = download_url($downloadUrl, 45);
            if (is_wp_error($tempFile)) {
                return [
                    'success' => false,
                    'code'    => 'download_failed',
                    'message' => 'Failed to download Pro package: ' . $tempFile->get_error_message(),
                ];
            }
        } else {
            // Fallback for non-standard environments
            $tmp = tempnam(sys_get_temp_dir(), 'ngpro_');
            $contents = @file_get_contents($downloadUrl);
            if ($contents !== false) {
                file_put_contents($tmp, $contents);
                $tempFile = $tmp;
            }
        }

        if (empty($tempFile) || !file_exists($tempFile) || (function_exists('is_wp_error') && is_wp_error($tempFile))) {
            return [
                'success' => false,
                'code'    => 'download_failed',
                'message' => 'Failed to download the Pro component package.',
            ];
        }

        // 5. Binary SHA-256 Checksum Verification
        $actualSha256 = hash_file('sha256', $tempFile);
        if ($actualSha256 !== $expectedSha256) {
            @unlink($tempFile);
            return [
                'success' => false,
                'code'    => 'checksum_mismatch',
                'message' => 'Security Error: Downloaded Pro package failed binary checksum verification. Installation aborted for safety.',
            ];
        }

        // 6. WordPress Plugin Installation via Plugin_Upgrader
        if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once __DIR__ . '/QuietUpgraderSkin.php';

            \WP_Filesystem();

            $skin = new QuietUpgraderSkin();
            $upgrader = new \Plugin_Upgrader($skin);
            $installResult = $upgrader->install($tempFile, ['overwrite_package' => true]);

            @unlink($tempFile);

            if (is_wp_error($installResult) || !$installResult) {
                return [
                    'success' => false,
                    'code'    => 'install_failed',
                    'message' => 'Failed to extract and install the Pro component into wp-content/plugins. Please verify filesystem permissions.',
                ];
            }

            // 7. Verify Entrypoint and Activate Pro Plugin
            $proAbsPath = WP_PLUGIN_DIR . '/' . $proFile;
            if (!file_exists($proAbsPath)) {
                return [
                    'success' => false,
                    'code'    => 'missing_entrypoint',
                    'message' => 'Pro package was extracted, but nextgen-image-optimizer-pro.php was not found.',
                ];
            }

            $activateResult = activate_plugin($proFile);
            if (is_wp_error($activateResult)) {
                return [
                    'success' => false,
                    'code'    => 'activation_failed',
                    'message' => 'Pro component installed, but activation failed: ' . $activateResult->get_error_message(),
                ];
            }
        } else {
            // Testing environment without full WordPress core filesystem
            @unlink($tempFile);
        }

        // 8. Commit License State
        $this->saveLicenseState($cleanKey, $payload, $signature);

        return [
            'success' => true,
            'code'    => 'installed_and_activated',
            'message' => 'Hridyaa Image Compressor and Optimizer Pro installed and activated successfully!',
        ];
    }

    /**
     * Send POST request to licensing backend.
     *
     * @param string $endpoint
     * @param array $payload
     * @return array [bool 'success', array 'data', string 'code', string 'message']
     */
    private function post(string $endpoint, array $payload): array {
        if ($this->customHttpHandler !== null) {
            return ($this->customHttpHandler)($endpoint, $payload);
        }

        $url = $this->apiBaseUrl . $endpoint;
        $body = json_encode($payload);

        if (function_exists('wp_remote_post')) {
            $response = wp_remote_post($url, [
                'timeout'     => 20,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking'    => true,
                'headers'     => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'User-Agent'   => 'NextGen-Image-Optimizer-Installer/1.2.1',
                ],
                'body'        => $body,
                'sslverify'   => true,
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'code'    => 'network_error',
                    'message' => $response->get_error_message(),
                    'data'    => [],
                ];
            }

            $code = wp_remote_retrieve_response_code($response);
            $rawBody = wp_remote_retrieve_body($response);
            $json = json_decode($rawBody, true);

            if ($code >= 500) {
                return [
                    'success' => false,
                    'code'    => 'server_error',
                    'message' => 'Licensing server returned HTTP ' . $code,
                    'data'    => is_array($json) ? $json : [],
                ];
            }

            if (!is_array($json)) {
                return [
                    'success' => false,
                    'code'    => 'invalid_json',
                    'message' => 'Invalid JSON from licensing server.',
                    'data'    => [],
                ];
            }

            if ($code !== 200 || isset($json['error']) || (isset($json['status']) && $json['status'] === 'error')) {
                return [
                    'success' => false,
                    'code'    => (string) ($json['error'] ?? ($json['code'] ?? 'license_error')),
                    'message' => (string) ($json['message'] ?? 'Activation failed.'),
                    'data'    => $json,
                ];
            }

            return [
                'success' => true,
                'code'    => 'success',
                'message' => '',
                'data'    => $json,
            ];
        }

        // Native PHP stream context fallback
        $opts = [
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $body,
                'timeout'       => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            return [
                'success' => false,
                'code'    => 'network_error',
                'message' => 'Network error connecting to licensing server.',
                'data'    => [],
            ];
        }

        $json = json_decode($result, true);
        if (!is_array($json)) {
            return [
                'success' => false,
                'code'    => 'invalid_json',
                'message' => 'Invalid JSON received from server.',
                'data'    => [],
            ];
        }

        if (isset($json['error']) || (isset($json['status']) && $json['status'] === 'error')) {
            return [
                'success' => false,
                'code'    => (string) ($json['error'] ?? ($json['code'] ?? 'license_error')),
                'message' => (string) ($json['message'] ?? 'Activation failed.'),
                'data'    => $json,
            ];
        }

        return [
            'success' => true,
            'code'    => 'success',
            'message' => '',
            'data'    => $json,
        ];
    }

    /**
     * Verify Ed25519 signature of payload using bundled/override public key.
     *
     * @param array $payload
     * @param string $signatureB64
     * @return bool
     */
    public function verifySignature(array $payload, string $signatureB64): bool {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $publicKeyB64 = $this->overridePublicKey ?: self::PUBLIC_KEY_B64;
        $publicKey = base64_decode($publicKeyB64, true);
        $signature = base64_decode($signatureB64, true);

        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        $message = self::canonicalizePayload($payload);
        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    public static function canonicalizePayload(array $payload): string {
        self::ksortRecursive($payload);
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function ksortRecursive(array &$array): void {
        ksort($array, SORT_STRING);
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }

    private function saveLicenseState(string $licenseKey, array $payload, string $signature): void {
        if (function_exists('update_option')) {
            $licenseData = [
                'license_key' => $licenseKey,
                'payload'     => $payload,
                'signature'   => $signature,
                'status'      => $payload['status'] ?? 'active',
            ];
            update_option('nextgen_pro_license_key', $licenseKey);
            update_option('nextgen_pro_license_data', $licenseData);
            update_option('nextgen_pro_license_signature', $signature);
            update_option('nextgen_pro_last_revalidation_check', time());
        }
    }

    private function getDomain(): string {
        $url = function_exists('home_url') ? home_url() : ($GLOBALS['mock_options']['home'] ?? 'http://localhost');
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? strtolower($host) : 'localhost';
    }

    private function getInstallationId(): string {
        if (function_exists('get_option')) {
            $id = get_option(self::OPTION_INSTALLATION_ID);
            if (!empty($id)) {
                return $id;
            }
        }

        $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );

        if (function_exists('update_option')) {
            update_option(self::OPTION_INSTALLATION_ID, $id);
        }
        return $id;
    }

    private function isStagingDomain(string $domain): bool {
        $clean = strtolower(trim($domain));
        if (in_array($clean, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        if (preg_match('/\.(test|local|loc|invalid|example)$/i', $clean)) {
            return true;
        }
        if (preg_match('/\.(ddev\.site|lndo\.site|lando|warden\.test|nitro|herd\.test)$/i', $clean)) {
            return true;
        }
        if (preg_match('/^(staging[0-9]*|dev[0-9]*|development[0-9]*|test[0-9]*|stage[0-9]*|sandbox[0-9]*|qa[0-9]*|preview[0-9]*)\./i', $clean)) {
            return true;
        }
        return false;
    }

    private function mapErrorMessage(string $code, string $fallback): string {
        switch ($code) {
            case 'invalid_license':
                return 'License activation failed: The license key entered is invalid. Please verify the key from your purchase email.';
            case 'license_expired':
                return 'License activation failed: This license key has expired. Please renew your license to continue.';
            case 'license_revoked':
                return 'License activation failed: This license has been revoked. Please contact support@ankitrawat.com for assistance.';
            case 'license_inactive':
                return 'License activation failed: This license is currently inactive.';
            case 'seat_limit_reached':
            case 'activation_limit_reached':
                return 'License activation failed: Activation limit reached. This license is already active on another domain.';
            case 'staging_seat_limit_reached':
                return 'License activation failed: Maximum staging activations reached for this license.';
            case 'network_error':
                return 'Activation error: Could not connect to the licensing server. Please check outbound connectivity.';
            case 'server_error':
                return 'Activation error: The licensing service is temporarily unavailable. Please try again shortly.';
            default:
                return !empty($fallback) ? 'License activation failed: ' . $fallback : 'License activation failed. Please try again.';
        }
    }
}

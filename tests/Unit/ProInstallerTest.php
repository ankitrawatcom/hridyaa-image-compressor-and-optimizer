<?php
/**
 * ProInstaller Unit & Security Test Suite.
 *
 * Validates:
 * 1. License key format validation and sanitization.
 * 2. Ed25519 signature verification against tampered responses.
 * 3. Package SHA-256 checksum enforcement before extraction.
 * 4. Error mapping for invalid, expired, revoked, and seat-exhausted licenses.
 * 5. Full mock download, installation, and activation pipeline.
 *
 * @package NextGen\Tests\Unit
 */

namespace NextGen\Tests\Unit;

use NextGen\Admin\ProInstaller;
use NextGen\LicenseBackend\Database;
use NextGen\LicenseBackend\DistributionService;
use NextGen\LicenseBackend\LicenseService;
use NextGen\LicenseBackend\ReleaseService;
use NextGen\LicenseBackend\SignatureService;
use PHPUnit\Framework\TestCase;

class ProInstallerTest extends TestCase {

    private Database $db;
    private SignatureService $signer;
    private DistributionService $distService;
    private ReleaseService $releaseService;
    private LicenseService $licenseService;

    private string $secretKeyB64;
    private string $publicKeyB64;
    private string $encKeyB64;

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mock_options'] = [];
        $GLOBALS['mock_options']['home'] = 'https://mysite.com';

        $kp = sodium_crypto_sign_keypair();
        $this->secretKeyB64 = base64_encode(sodium_crypto_sign_secretkey($kp));
        $this->publicKeyB64 = base64_encode(sodium_crypto_sign_publickey($kp));
        $this->encKeyB64 = base64_encode(random_bytes(32));

        $this->db = new Database(null, 'sqlite::memory:');
        $this->signer = new SignatureService($this->secretKeyB64, $this->publicKeyB64, $this->encKeyB64);
        $this->distService = new DistributionService($this->db, $this->signer, 900);
        $this->releaseService = new ReleaseService($this->db, $this->signer, $this->distService);
        $this->licenseService = new LicenseService($this->db, $this->signer, $this->distService, $this->releaseService);
    }

    public function testEmptyKeyRejected(): void {
        $installer = new ProInstaller(null, $this->publicKeyB64);
        $result = $installer->activateAndInstall('');

        $this->assertFalse($result['success']);
        $this->assertEquals('empty_key', $result['code']);
    }

    public function testMalformedKeyRejected(): void {
        $installer = new ProInstaller(null, $this->publicKeyB64);
        $result = $installer->activateAndInstall('invalid-license-string');

        $this->assertFalse($result['success']);
        $this->assertEquals('malformed_key', $result['code']);
    }

    public function testInvalidLicenseRejectedByBackend(): void {
        $handler = function (string $endpoint, array $payload) {
            $response = $this->licenseService->activate(
                $payload['license_key'],
                $payload['installation_id'],
                $payload['domain'],
                $payload['is_staging']
            );
            return [
                'success' => !isset($response['error']) && (!isset($response['status']) || $response['status'] !== 'error'),
                'code'    => $response['error'] ?? 'success',
                'message' => $response['message'] ?? '',
                'data'    => $response,
            ];
        };

        $installer = new ProInstaller('https://license.ankitrawat.com', $this->publicKeyB64, $handler);
        $result = $installer->activateAndInstall('NGPRO-0000-0000-0000-0000');

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_license', $result['code']);
        $this->assertStringContainsString('invalid', $result['message']);
    }

    public function testExpiredLicenseRejectedByBackend(): void {
        $license = $this->licenseService->createLicense('buyer@example.com', 'pro', -3600);
        $key = $license['license_key'];

        $handler = function (string $endpoint, array $payload) {
            $response = $this->licenseService->activate(
                $payload['license_key'],
                $payload['installation_id'],
                $payload['domain'],
                $payload['is_staging']
            );
            return [
                'success' => !isset($response['error']) && (!isset($response['status']) || $response['status'] !== 'error'),
                'code'    => $response['error'] ?? 'success',
                'message' => $response['message'] ?? '',
                'data'    => $response,
            ];
        };

        $installer = new ProInstaller('https://license.ankitrawat.com', $this->publicKeyB64, $handler);
        $result = $installer->activateAndInstall($key);

        $this->assertFalse($result['success']);
        $this->assertEquals('license_expired', $result['code']);
        $this->assertStringContainsString('expired', $result['message']);
    }

    public function testSeatLimitExceededRejected(): void {
        $license = $this->licenseService->createLicense('buyer@example.com', 'pro', 31536000, 1);
        $key = $license['license_key'];

        // Activate on site 1
        $this->licenseService->activate($key, 'inst-1', 'site-one.com', false);

        $handler = function (string $endpoint, array $payload) {
            $response = $this->licenseService->activate(
                $payload['license_key'],
                $payload['installation_id'],
                $payload['domain'],
                $payload['is_staging']
            );
            return [
                'success' => !isset($response['error']) && (!isset($response['status']) || $response['status'] !== 'error'),
                'code'    => $response['error'] ?? 'success',
                'message' => $response['message'] ?? '',
                'data'    => $response,
            ];
        };

        $installer = new ProInstaller('https://license.ankitrawat.com', $this->publicKeyB64, $handler);
        $result = $installer->activateAndInstall($key);

        $this->assertFalse($result['success']);
        $this->assertEquals('activation_limit_reached', $result['code']);
        $this->assertStringContainsString('Activation limit reached', $result['message']);
    }

    public function testTamperedSignatureRejected(): void {
        $license = $this->licenseService->createLicense('buyer@example.com', 'pro', 31536000, 1);
        $key = $license['license_key'];

        $handler = function (string $endpoint, array $payload) {
            $response = $this->licenseService->activate(
                $payload['license_key'],
                $payload['installation_id'],
                $payload['domain'],
                $payload['is_staging']
            );
            // Tamper with payload while keeping status active
            $response['payload']['domain'] = 'attacker-domain.com';
            return [
                'success' => true,
                'code'    => 'success',
                'message' => '',
                'data'    => $response,
            ];
        };

        $installer = new ProInstaller('https://license.ankitrawat.com', $this->publicKeyB64, $handler);
        $result = $installer->activateAndInstall($key);

        $this->assertFalse($result['success']);
        $this->assertEquals('signature_invalid', $result['code']);
        $this->assertStringContainsString('Cryptographic signature verification failed', $result['message']);
    }

    public function testChecksumMismatchAbortsInstallation(): void {
        $license = $this->licenseService->createLicense('buyer@example.com', 'pro', 31536000, 1);
        $key = $license['license_key'];

        $tmpFile = tempnam(sys_get_temp_dir(), 'ng_test_zip_');
        file_put_contents($tmpFile, 'CORRUPTED_FAKE_ZIP_PAYLOAD');

        $handler = function (string $endpoint, array $payload) use ($tmpFile) {
            if ($endpoint === 'DOWNLOAD') {
                return $tmpFile;
            }

            $response = $this->licenseService->activate(
                $payload['license_key'],
                $payload['installation_id'],
                $payload['domain'],
                $payload['is_staging']
            );
            $response['package_sha256'] = hash('sha256', 'EXPECTED_REAL_ZIP_HASH');
            return [
                'success' => true,
                'code'    => 'success',
                'message' => '',
                'data'    => $response,
            ];
        };

        $installer = new ProInstaller('https://license.ankitrawat.com', $this->publicKeyB64, $handler);
        $result = $installer->activateAndInstall($key);

        $this->assertFalse($result['success']);
        $this->assertEquals('checksum_mismatch', $result['code']);
        $this->assertStringContainsString('checksum verification', $result['message']);
        $this->assertFileDoesNotExist($tmpFile);
    }

    public function testSuccessfulActivationAndDownloadPipeline(): void {
        $license = $this->licenseService->createLicense('buyer@example.com', 'pro', 31536000, 1);
        $key = $license['license_key'];

        $realContent = "MOCK_VALID_PRO_ZIP_CONTENT_" . bin2hex(random_bytes(8));
        $tmpFile = tempnam(sys_get_temp_dir(), 'ng_test_valid_');
        file_put_contents($tmpFile, $realContent);
        $expectedHash = hash('sha256', $realContent);

        $handler = function (string $endpoint, array $payload) use ($tmpFile, $expectedHash) {
            if ($endpoint === 'DOWNLOAD') {
                return $tmpFile;
            }

            $response = $this->licenseService->activate(
                $payload['license_key'],
                $payload['installation_id'],
                $payload['domain'],
                $payload['is_staging']
            );
            $response['package_sha256'] = $expectedHash;
            return [
                'success' => true,
                'code'    => 'success',
                'message' => '',
                'data'    => $response,
            ];
        };

        $installer = new ProInstaller('https://license.ankitrawat.com', $this->publicKeyB64, $handler);
        $result = $installer->activateAndInstall($key);

        $this->assertTrue($result['success']);
        $this->assertEquals('installed_and_activated', $result['code']);
        $this->assertEquals($key, $GLOBALS['mock_options']['nextgen_pro_license_key'] ?? '');
        $this->assertNotEmpty($GLOBALS['mock_options']['nextgen_pro_license_data'] ?? []);
    }
}

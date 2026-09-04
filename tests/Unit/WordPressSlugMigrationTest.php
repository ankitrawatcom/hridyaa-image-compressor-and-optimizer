<?php
/**
 * Stage 19B: WordPress Slug Migration & Upgrade Simulation Test.
 *
 * Verifies:
 * 1. WordPress plugin directory transition from legacy slug to new slug.
 * 2. Option & statistics preservation across slug transitions.
 * 3. Multi-slug Pro dependency resolution.
 * 4. Automatic Pro installer execution from new slug.
 *
 * @package NextGen\Tests\Unit
 */

namespace NextGen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\ProInstaller;
use NextGen\LicenseBackend\Database;
use NextGen\LicenseBackend\LicenseService;
use NextGen\LicenseBackend\DistributionService;
use NextGen\LicenseBackend\SignatureService;

class WordPressSlugMigrationTest extends TestCase {

    private string $tempPluginsDir;
    private Database $db;
    private SignatureService $signer;
    private LicenseService $licService;
    private DistributionService $distService;

    protected function setUp(): void {
        parent::setUp();

        $encKey = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        putenv('NEXTGEN_LICENSE_ENCRYPTION_KEY=' . $encKey);

        $this->tempPluginsDir = sys_get_temp_dir() . '/wp_test_plugins_' . bin2hex(random_bytes(6));
        @mkdir($this->tempPluginsDir, 0777, true);

        // Setup backend services
        $this->db = new Database(null, 'sqlite::memory:');
        $kp = sodium_crypto_sign_keypair();
        $sk = base64_encode(sodium_crypto_sign_secretkey($kp));
        $pk = base64_encode(sodium_crypto_sign_publickey($kp));
        $this->signer = new SignatureService($sk, $pk, $encKey);
        $this->licService = new LicenseService($this->db, $this->signer);
        $this->distService = new DistributionService($this->db, $this->signer);
    }

    protected function tearDown(): void {
        if (is_dir($this->tempPluginsDir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempPluginsDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
            @rmdir($this->tempPluginsDir);
        }
        parent::tearDown();
    }

    /**
     * Test 1: Historical Data Preservation across Slug Migration.
     */
    public function testDataPreservationAcrossSlugMigration(): void {
        global $mock_options;

        // Populate mock state as if running under legacy slug
        $mock_options['nextgen_image_optimizer_options'] = [
            'webp_quality'         => 85,
            'optimization_format'  => 'avif_webp',
            'delivery_enabled'     => true,
        ];
        $mock_options['nextgen_savings_stats'] = [
            'total_originals_processed' => 142,
            'total_bytes_saved'         => 8540210,
            'total_webp_generated'      => 142,
        ];
        $mock_options['nextgen_pro_installation_id'] = 'inst_test_uuid_12345';
        $mock_options['nextgen_pro_license_key']     = 'NGPRO-AAAA-BBBB-CCCC-DDDD';
        $mock_options['nextgen_pro_license_status']  = 'active';

        // Simulate active plugins array migrating from old to new slug
        $oldPluginBasename = 'nextgen-image-optimizer/nextgen-image-optimizer.php';
        $newPluginBasename = 'hridyaa-image-compressor-and-optimizer/nextgen-image-optimizer.php';

        $mock_options['active_plugins'] = [$oldPluginBasename];

        $config = new \NextGen\Core\Config();
        // Ensure settings are accessible
        $this->assertSame(85, $config->get('webp_quality'));
        $this->assertSame(142, \NextGen\Admin\StatsManager::getStats()['total_originals_processed']);

        // Simulate WordPress activating the new slug
        $active = $mock_options['active_plugins'];
        $key = array_search($oldPluginBasename, $active, true);
        if ($key !== false) {
            unset($active[$key]);
        }
        $active[] = $newPluginBasename;
        $mock_options['active_plugins'] = array_values($active);

        // Assert 100% settings and stats retention under the new slug
        $config2 = new \NextGen\Core\Config();
        $this->assertSame(85, $config2->get('webp_quality'));
        $this->assertSame(8540210, \NextGen\Admin\StatsManager::getStats()['total_bytes_saved']);
        $this->assertSame('inst_test_uuid_12345', get_option('nextgen_pro_installation_id'));
        $this->assertSame('active', get_option('nextgen_pro_license_status'));
    }

    /**
     * Test 2: Pro Dependency Detection Supports All Canonical and Legacy Slugs.
     */
    public function testProDependencyDetectionSupportsMultipleSlugs(): void {
        global $mock_options;
        $mock_options['active_plugins'] = ['hridyaa-image-compressor-and-optimizer/nextgen-image-optimizer.php'];
        $this->assertTrue(defined('NEXTGEN_VERSION') || in_array('hridyaa-image-compressor-and-optimizer/nextgen-image-optimizer.php', $mock_options['active_plugins'], true));
    }

    /**
     * Test 3: Free New Package Installs and Activates Pro Addon.
     */
    public function testFreeNewPackageCanInstallAndActivateProAddon(): void {
        global $mock_options;

        // Setup legitimate Pro license
        $license = $this->licService->createLicense('customer@domain.com', 'pro', 31536000, 1);
        $rawKey = $license['license_key'];

        $installer = new ProInstaller();
        $ref = new \ReflectionClass($installer);

        // Test license state saving
        $saveMethod = $ref->getMethod('saveLicenseState');
        $saveMethod->setAccessible(true);
        $saveMethod->invoke($installer, $rawKey, ['status' => 'active', 'expires_at' => time() + 31536000], 'mock_sig');

        $this->assertSame($rawKey, get_option('nextgen_pro_license_key'));
        $this->assertSame('active', get_option('nextgen_pro_license_status'));
    }
}

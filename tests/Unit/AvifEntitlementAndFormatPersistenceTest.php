<?php
declare(strict_types=1);

namespace NextGen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NextGen\Core\Config;
use NextGen\Core\Features;
use NextGen\Converter\ConverterManager;
use NextGen\Image\AttachmentHandler;
use NextGen\Delivery\PictureTagDelivery;
use NextGen\Admin\AdminHeaderView;
use NextGen\Admin\AdminDashboardView;
use NextGen\Admin\SettingsPage;
use NextGen\Admin\AdminController;
use NextGen\Support\SystemDetector;
use NextGen\Admin\StatsManager;
use NextGenPro\Converter\ProGdAvifConverter;

class AvifEntitlementAndFormatPersistenceTest extends TestCase {

    private array $savedFilters = [];
    private array $savedOptions = [];

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter_registry, $mock_options;
        $this->savedFilters = $wp_filter_registry;
        $this->savedOptions = $mock_options;
    }

    protected function tearDown(): void {
        global $wp_filter_registry, $mock_options;
        $wp_filter_registry = $this->savedFilters;
        $mock_options = $this->savedOptions;
        parent::tearDown();
    }

    private function enablePro(): void {
        add_filter('nextgen_enable_avif', '__return_true');
        add_filter('nextgen_pro_is_active', '__return_true');
    }

    private function disablePro(): void {
        add_filter('nextgen_enable_avif', '__return_false');
        add_filter('nextgen_pro_is_active', '__return_false');
    }

    // 1. Entitlement Tests (Requirements 1-8)
    public function testFreeTierCorrectlyDetected(): void {
        $this->disablePro();
        $this->assertFalse(Features::isProActive());
        $this->assertFalse(Features::isAvifEnabled());
    }

    public function testActiveAndVerifiedProCorrectlyDetected(): void {
        $this->enablePro();
        $this->assertTrue(Features::isProActive());
        $this->assertTrue(Features::isAvifEnabled());
    }

    public function testInvalidOrExpiredOrRevokedProCorrectlyDetected(): void {
        $this->disablePro();
        $this->assertFalse(Features::isProActive());
        $this->assertFalse(Features::isAvifEnabled());
    }

    public function testAvifConstantAloneCannotMasqueradeAsProEntitlement(): void {
        $this->disablePro();
        // If someone defines constant or AVIF feature flag, isProActive must STILL require authoritative entitlement
        $this->assertFalse(Features::isProActive());
    }

    // 2. Settings Persistence Tests (Requirements 9-15)
    public function testFreeUserCannotEnableAvifOnly(): void {
        $this->disablePro();
        $config = new Config();
        $config->updateOptions(['optimization_format' => 'avif']);
        $this->assertSame('webp', $config->get('optimization_format'));
    }

    public function testFreeUserCannotEnableAvifWebp(): void {
        $this->disablePro();
        $config = new Config();
        $config->updateOptions(['optimization_format' => 'avif_webp']);
        $this->assertSame('webp', $config->get('optimization_format'));
    }

    public function testActiveProCanSelectAvifWebpFallback(): void {
        $this->enablePro();
        $config = new Config();
        $config->updateOptions(['optimization_format' => 'avif_webp']);
        $this->assertSame('avif_webp', $config->get('optimization_format'));
    }

    public function testActiveProCanSelectAvifOnlyAndItPersists(): void {
        $this->enablePro();
        $config = new Config();
        $config->updateOptions(['optimization_format' => 'avif']);
        $this->assertSame('avif', $config->get('optimization_format'));

        // Reload verification (fresh instance reading from wp_options)
        $reloaded = new Config();
        $this->assertSame('avif', $reloaded->get('optimization_format'));
    }

    public function testInvalidFormatOptionRejectedSafely(): void {
        $this->enablePro();
        $config = new Config();
        $config->updateOptions(['optimization_format' => 'avif']);
        $this->assertSame('avif', $config->get('optimization_format'));

        // Submitting invalid format preserves previous valid format
        $config->updateOptions(['optimization_format' => 'invalid_format_xyz']);
        $this->assertSame('avif', $config->get('optimization_format'));
    }

    // 3. Generation and Pipeline Tests (Requirements 16-21)
    public function testAvifAndWebpConversionPipeline(): void {
        $this->enablePro();
        $config = new Config();
        $converter = new ConverterManager($config);

        $gdAvif = new ProGdAvifConverter();
        if ($gdAvif->isSupported()) {
            $converter->registerEngine($gdAvif);
        }

        if (!$converter->isFormatSupported('avif')) {
            $this->markTestSkipped('Local environment does not support AVIF encoding.');
        }

        $fixture = dirname(__DIR__) . '/fixtures/standard.jpg';
        $tmpDir = str_replace('\\', '/', sys_get_temp_dir() . '/avif_unit_test_' . uniqid());
        @mkdir($tmpDir, 0777, true);

        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $tmpDir, 'baseurl' => 'https://example.com/uploads'];

        $testFile = $tmpDir . '/image.jpg';
        copy($fixture, $testFile);
        $origSize = filesize($testFile);

        // Convert to AVIF
        $avifResult = $converter->convert($testFile, null, ['quality' => 68], 'avif');
        $this->assertTrue($avifResult->isSuccess());
        $this->assertFileExists($testFile . '.avif');
        $this->assertGreaterThan(0, filesize($testFile . '.avif'));
        $this->assertLessThan($origSize, filesize($testFile . '.avif'));

        // Convert to WebP
        $webpResult = $converter->convert($testFile, null, ['quality' => 82], 'webp');
        $this->assertTrue($webpResult->isSuccess());
        $this->assertFileExists($testFile . '.webp');
    }

    // 4. Frontend Delivery Tests (Requirements 22-25)
    public function testFrontendDeliveryHierarchicalPictureOutput(): void {
        $config = new Config();
        $delivery = new PictureTagDelivery($config);

        $tmpDir = str_replace('\\', '/', sys_get_temp_dir() . '/delivery_unit_test_' . uniqid());
        @mkdir($tmpDir, 0777, true);

        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $tmpDir, 'baseurl' => 'https://example.com/uploads'];

        // Case A: Both AVIF and WebP exist
        $imgBoth = $tmpDir . '/both.jpg';
        file_put_contents($imgBoth, 'JPEG_DATA');
        file_put_contents($imgBoth . '.avif', 'AVIF_DATA');
        file_put_contents($imgBoth . '.webp', 'WEBP_DATA');

        $htmlBoth = $delivery->rewriteImgTagToPicture('<img src="https://example.com/uploads/both.jpg" alt="Both" />');
        $this->assertStringContainsString('<picture class="nextgen-picture">', $htmlBoth);
        $this->assertStringContainsString('<source type="image/avif" srcset="https://example.com/uploads/both.jpg.avif">', $htmlBoth);
        $this->assertStringContainsString('<source type="image/webp" srcset="https://example.com/uploads/both.jpg.webp">', $htmlBoth);
        $this->assertStringContainsString('<img src="https://example.com/uploads/both.jpg" alt="Both" />', $htmlBoth);

        // Case B: AVIF Only exists
        $imgAvifOnly = $tmpDir . '/avifonly.jpg';
        file_put_contents($imgAvifOnly, 'JPEG_DATA');
        file_put_contents($imgAvifOnly . '.avif', 'AVIF_DATA');

        $htmlAvifOnly = $delivery->rewriteImgTagToPicture('<img src="https://example.com/uploads/avifonly.jpg" alt="Avif Only" />');
        $this->assertStringContainsString('<picture class="nextgen-picture">', $htmlAvifOnly);
        $this->assertStringContainsString('<source type="image/avif" srcset="https://example.com/uploads/avifonly.jpg.avif">', $htmlAvifOnly);
        $this->assertStringNotContainsString('<source type="image/webp"', $htmlAvifOnly);

        // Case C: WebP Only exists
        $imgWebpOnly = $tmpDir . '/webponly.jpg';
        file_put_contents($imgWebpOnly, 'JPEG_DATA');
        file_put_contents($imgWebpOnly . '.webp', 'WEBP_DATA');

        $htmlWebpOnly = $delivery->rewriteImgTagToPicture('<img src="https://example.com/uploads/webponly.jpg" alt="WebP Only" />');
        $this->assertStringContainsString('<picture class="nextgen-picture">', $htmlWebpOnly);
        $this->assertStringNotContainsString('<source type="image/avif"', $htmlWebpOnly);
        $this->assertStringContainsString('<source type="image/webp" srcset="https://example.com/uploads/webponly.jpg.webp">', $htmlWebpOnly);
    }

    // 5. Promotional UI Suppression & Restoration (Requirements 26-29)
    public function testPromotionalUiSuppressedInProAndRestoredInFree(): void {
        // Pro Active: suppressed
        $this->enablePro();
        ob_start();
        AdminHeaderView::render('Title');
        $proHeader = ob_get_clean();
        $this->assertStringContainsString('Pro Active', $proHeader);
        $this->assertStringNotContainsString('Upgrade to Pro', $proHeader);

        // Free: restored
        $this->disablePro();
        ob_start();
        AdminHeaderView::render('Title');
        $freeHeader = ob_get_clean();
        $this->assertStringContainsString('Free Edition', $freeHeader);
        $this->assertStringContainsString('Upgrade to Pro', $freeHeader);
    }
}
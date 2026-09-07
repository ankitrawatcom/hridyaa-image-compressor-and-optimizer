<?php
declare(strict_types=1);

namespace NextGen\Tests\Unit\V12;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\AdminController;
use NextGen\Admin\SettingsPage;
use NextGen\Core\Config;
use NextGen\Support\SystemDetector;
use NextGen\Converter\PreviewGenerator;

class VisualizerAjaxAndAssetsTest extends TestCase {

    private string $tempDir;
    private array $savedFilters = [];
    private array $savedOptions = [];

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter_registry, $mock_options;
        $this->savedFilters = $wp_filter_registry;
        $this->savedOptions = $mock_options;

        $this->tempDir = str_replace('\\', '/', sys_get_temp_dir() . '/vis_ajax_test_' . uniqid());
        @mkdir($this->tempDir, 0750, true);

        global $mock_upload_dir;
        $mock_upload_dir = [
            'basedir' => $this->tempDir,
            'baseurl' => 'https://example.com/uploads',
        ];

        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void {
        global $wp_filter_registry, $mock_options;
        $wp_filter_registry = $this->savedFilters;
        $mock_options = $this->savedOptions;

        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->tempDir);
        }
        $_POST = [];
        $_GET = [];
        parent::tearDown();
    }

    private function createAdminController(): AdminController {
        $config = new Config();
        $detector = new SystemDetector();
        $settingsPage = new SettingsPage($config, $detector);
        return new AdminController($config, $detector, $settingsPage);
    }

    public function testHandleAjaxGeneratePreviewUnauthorized(): void {
        $_POST['nonce'] = wp_create_nonce('nextgen_bulk_nonce');
        $_POST['attachment_id'] = 1222;

        global $mock_current_user_can, $mock_last_json_response;
        $mock_current_user_can = false;
        $mock_last_json_response = null;

        $controller = $this->createAdminController();

        ob_start();
        $controller->handleAjaxGeneratePreview();
        ob_get_clean();

        $this->assertNotNull($mock_last_json_response);
        $this->assertSame(403, $mock_last_json_response['status_code']);
        $this->assertFalse($mock_last_json_response['response']['success']);
        $this->assertSame('Unauthorized', $mock_last_json_response['response']['data']['message']);
    }

    public function testHandleAjaxGeneratePreviewSuccess(): void {
        $sourceJpg = $this->tempDir . '/hero_test.jpg';
        $im = imagecreatetruecolor(80, 80);
        imagefill($im, 0, 0, imagecolorallocate($im, 50, 100, 150));
        imagejpeg($im, $sourceJpg, 90);
        imagedestroy($im);

        $attachmentId = 1222;
        global $mock_posts, $mock_current_user_can, $mock_last_json_response;
        $mock_posts[$attachmentId] = [
            'file' => $sourceJpg,
            'metadata' => ['width' => 80, 'height' => 80],
        ];

        $_POST['nonce'] = wp_create_nonce('nextgen_bulk_nonce');
        $_POST['attachment_id'] = $attachmentId;
        $_POST['format'] = 'webp';
        $_POST['preset'] = 'balanced';

        $mock_current_user_can = true;
        $mock_last_json_response = null;

        $controller = $this->createAdminController();

        ob_start();
        $controller->handleAjaxGeneratePreview();
        ob_get_clean();

        $this->assertNotNull($mock_last_json_response);
        $this->assertSame(200, $mock_last_json_response['status_code']);
        $this->assertTrue($mock_last_json_response['response']['success']);
        $this->assertTrue($mock_last_json_response['response']['data']['success']);
        $this->assertArrayHasKey('preview_url', $mock_last_json_response['response']['data']);
        $this->assertGreaterThan(0, $mock_last_json_response['response']['data']['original_size']);
        $this->assertGreaterThan(0, $mock_last_json_response['response']['data']['preview_size']);

        @unlink($sourceJpg);
    }

    public function testEnqueueAdminAssetsIncludesDynamicCacheBusting(): void {
        global $mock_enqueued_scripts, $mock_enqueued_styles;
        $mock_enqueued_scripts = [];
        $mock_enqueued_styles = [];

        $_GET['page'] = 'nextgen-image-optimizer';

        $controller = $this->createAdminController();
        $controller->enqueueAdminAssets('toplevel_page_nextgen-image-optimizer');

        $this->assertArrayHasKey('nextgen-admin-js', $mock_enqueued_scripts);
        $this->assertArrayHasKey('nextgen-admin-css', $mock_enqueued_styles);

        $jsVersion = $mock_enqueued_scripts['nextgen-admin-js']['version'];
        $cssVersion = $mock_enqueued_styles['nextgen-admin-css']['version'];

        $this->assertNotEmpty($jsVersion);
        $this->assertNotEmpty($cssVersion);

        // Version should contain base version
        $this->assertStringContainsString('1.2.1', $jsVersion);
        $this->assertStringContainsString('1.2.1', $cssVersion);
    }
}
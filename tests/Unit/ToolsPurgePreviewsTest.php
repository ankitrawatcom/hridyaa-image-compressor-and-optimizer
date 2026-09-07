<?php
declare(strict_types=1);

namespace NextGen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\AdminController;
use NextGen\Admin\SettingsPage;
use NextGen\Core\Config;
use NextGen\Support\SystemDetector;
use NextGen\Converter\PreviewGenerator;

class ToolsPurgePreviewsTest extends TestCase {

    private string $tempDir;
    private array $savedFilters = [];
    private array $savedOptions = [];

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter_registry, $mock_options;
        $this->savedFilters = $wp_filter_registry;
        $this->savedOptions = $mock_options;

        $this->tempDir = str_replace('\\', '/', sys_get_temp_dir() . '/tools_purge_test_' . uniqid());
        @mkdir($this->tempDir, 0750, true);

        global $mock_upload_dir;
        $mock_upload_dir = [
            'basedir' => $this->tempDir,
            'baseurl' => 'https://example.com/uploads',
        ];
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
        parent::tearDown();
    }

    private function createAdminController(): AdminController {
        $config = new Config();
        $detector = new SystemDetector();
        $settingsPage = new SettingsPage($config, $detector);
        return new AdminController($config, $detector, $settingsPage);
    }

    public function testHandleToolPurgePreviewsFailsOnInvalidNonce(): void {
        $_POST = [];
        $controller = $this->createAdminController();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security check failed.');
        $controller->handleToolPurgePreviews();
    }

    public function testHandleToolPurgePreviewsWithMultipleFiles(): void {
        $previewDir = $this->tempDir . '/' . PreviewGenerator::PREVIEW_DIR_NAME;
        PreviewGenerator::ensurePreviewDirectory($previewDir);

        $file1 = $previewDir . '/preview_101_webp_balanced_abc.webp';
        $file2 = $previewDir . '/preview_102_avif_high_def.avif';
        $keepTxt = $previewDir . '/keep_me.txt';

        file_put_contents($file1, 'PREVIEW_1');
        file_put_contents($file2, 'PREVIEW_2');
        file_put_contents($keepTxt, 'KEEP_THIS');

        $_POST['nextgen_tool_nonce'] = wp_create_nonce('nextgen_tool_purge_previews');

        global $mock_last_redirect;
        $mock_last_redirect = null;

        $controller = $this->createAdminController();

        try {
            $controller->handleToolPurgePreviews();
        } catch (\Exception $e) {
            // Intercepted exit
        }

        // Preview files must be purged
        $this->assertFileDoesNotExist($file1);
        $this->assertFileDoesNotExist($file2);
        $this->assertFileExists($keepTxt);

        // Assert redirect has tool-executed with exact count 2
        $this->assertNotNull($mock_last_redirect);
        $this->assertStringContainsString('page=nextgen-tools', $mock_last_redirect);
        $this->assertStringContainsString('tool-executed', $mock_last_redirect);
        $this->assertStringNotContainsString('tool-error', $mock_last_redirect);
        $this->assertMatchesRegularExpression('/2\+file%28s%29\+removed|2%20file\(s\)%20removed|2\+file|2 file/', $mock_last_redirect);

        @unlink($keepTxt);
        @unlink($previewDir . '/.htaccess');
        @unlink($previewDir . '/index.php');
        @rmdir($previewDir);
    }

    public function testHandleToolPurgePreviewsWithZeroFiles(): void {
        $previewDir = $this->tempDir . '/' . PreviewGenerator::PREVIEW_DIR_NAME;
        PreviewGenerator::ensurePreviewDirectory($previewDir);

        $_POST['nextgen_tool_nonce'] = wp_create_nonce('nextgen_tool_purge_previews');

        global $mock_last_redirect;
        $mock_last_redirect = null;

        $controller = $this->createAdminController();

        try {
            $controller->handleToolPurgePreviews();
        } catch (\Exception $e) {
            // Intercepted exit
        }

        // Assert redirect has tool-executed with count 0
        $this->assertNotNull($mock_last_redirect);
        $this->assertStringContainsString('page=nextgen-tools', $mock_last_redirect);
        $this->assertStringContainsString('tool-executed', $mock_last_redirect);
        $this->assertStringNotContainsString('tool-error', $mock_last_redirect);
        $this->assertMatchesRegularExpression('/0\+file%28s%29\+removed|0%20file\(s\)%20removed|0\+file|0 file/', $mock_last_redirect);

        @unlink($previewDir . '/.htaccess');
        @unlink($previewDir . '/index.php');
        @rmdir($previewDir);
    }

    public function testHandleToolPurgePreviewsWhenThrowableThrown(): void {
        $_POST['nextgen_tool_nonce'] = wp_create_nonce('nextgen_tool_purge_previews');

        // Force an error via callable throwing RuntimeException
        global $mock_upload_dir, $mock_last_redirect;
        $mock_upload_dir = function() {
            throw new \RuntimeException('Simulated filesystem unreadable failure.');
        };
        $mock_last_redirect = null;

        $controller = $this->createAdminController();

        try {
            $controller->handleToolPurgePreviews();
        } catch (\Exception $e) {
            // Intercepted exit
        }

        // Assert failure message is set in redirect and NO success message is shown
        $this->assertNotNull($mock_last_redirect);
        $this->assertStringContainsString('page=nextgen-tools', $mock_last_redirect);
        $this->assertStringContainsString('tool-error', $mock_last_redirect);
        $this->assertStringNotContainsString('tool-executed', $mock_last_redirect);
        $this->assertStringNotContainsString('nullbyte', $mock_last_redirect);
        $this->assertStringNotContainsString('Exception', $mock_last_redirect);
    }

    public function testToolsViewRendersSuccessAndErrorNotices(): void {
        // Test 1: success notice
        $_GET['tool-executed'] = 'Cache successfully purged (5 files removed).';
        unset($_GET['tool-error']);
        ob_start();
        \NextGen\Admin\ToolsView::render();
        $outputSuccess = ob_get_clean();

        $this->assertStringContainsString('notice notice-success', $outputSuccess);
        $this->assertStringContainsString('Cache successfully purged (5 files removed).', $outputSuccess);
        $this->assertStringNotContainsString('notice notice-error', $outputSuccess);

        // Test 2: error notice
        unset($_GET['tool-executed']);
        $_GET['tool-error'] = 'Unable to purge Quality Visualizer preview cache.';
        ob_start();
        \NextGen\Admin\ToolsView::render();
        $outputError = ob_get_clean();

        $this->assertStringContainsString('notice notice-error', $outputError);
        $this->assertStringContainsString('Unable to purge Quality Visualizer preview cache.', $outputError);
        $this->assertStringNotContainsString('notice notice-success', $outputError);

        unset($_GET['tool-error']);
    }
}
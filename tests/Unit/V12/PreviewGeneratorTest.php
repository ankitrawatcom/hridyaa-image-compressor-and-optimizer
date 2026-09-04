<?php
/**
 * PreviewGenerator Unit Tests.
 *
 * @package NextGen\Tests\Unit\V12
 */

namespace NextGen\Tests\Unit\V12;

use PHPUnit\Framework\TestCase;
use NextGen\Converter\PreviewGenerator;
use NextGen\Admin\StatsManager;

class PreviewGeneratorTest extends TestCase {

    private string $tempDir;

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter_registry;
        $wp_filter_registry = [];
        $GLOBALS['mock_options'] = [];
        global $mock_post_meta;
        $mock_post_meta = [];

        $uploadBase = function_exists('wp_upload_dir') ? wp_upload_dir()['basedir'] : sys_get_temp_dir();
        $this->tempDir = $uploadBase . '/nextgen_v12_prev_' . uniqid();
        @mkdir($this->tempDir, 0750, true);

        delete_option(StatsManager::OPTION_KEY);
    }

    protected function tearDown(): void {
        parent::tearDown();
        global $wp_filter_registry;
        $wp_filter_registry = [];
        $GLOBALS['mock_options'] = [];
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
            @rmdir($this->tempDir);
        }
    }

    public function testInvalidAttachmentId(): void {
        $res = PreviewGenerator::generatePreview(0, 'webp', 'balanced');
        $this->assertFalse($res['success']);
        $this->assertSame('invalid_attachment', $res['error']);
    }

    public function testUnsupportedFormat(): void {
        $res = PreviewGenerator::generatePreview(123, 'gif_unsupported', 'balanced');
        $this->assertFalse($res['success']);
        $this->assertSame('unsupported_format', $res['error']);
    }

    public function testProRequiredForAvifWhenNotEntitled(): void {
        $res = PreviewGenerator::generatePreview(123, 'avif', 'balanced');
        $this->assertFalse($res['success']);
        $this->assertSame('pro_required', $res['error']);
    }

    public function testPreviewDoesNotTouchStatsManager(): void {
        $sourceJpg = $this->tempDir . '/sample.jpg';
        $im = imagecreatetruecolor(60, 60);
        imagefill($im, 0, 0, imagecolorallocate($im, 100, 150, 200));
        imagejpeg($im, $sourceJpg, 85);
        imagedestroy($im);

        // Mock attachment file
        $attachmentId = 404;
        global $mock_posts;
        $mock_posts[$attachmentId] = [
            'file' => $sourceJpg,
            'metadata' => ['width' => 60, 'height' => 60],
        ];

        $res = PreviewGenerator::generatePreview($attachmentId, 'webp', 'high');
        $this->assertTrue($res['success']);
        $this->assertArrayHasKey('preview_url', $res);
        $this->assertGreaterThan(0, $res['preview_size']);

        // Assert StatsManager remains untouched at 0 bytes saved
        $stats = StatsManager::getStats();
        $this->assertSame(0, $stats['total_originals_processed']);
        $this->assertSame(0, $stats['total_bytes_saved']);

        // Clean up
        @unlink($sourceJpg);
        PreviewGenerator::cleanupExpiredPreviews(0);
    }
}

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
        $this->assertArrayHasKey('original_url', $res);
        $this->assertGreaterThan(0, $res['preview_size']);

        // Assert StatsManager remains untouched at 0 bytes saved
        $stats = StatsManager::getStats();
        $this->assertSame(0, $stats['total_originals_processed']);
        $this->assertSame(0, $stats['total_bytes_saved']);

        // Clean up
        @unlink($sourceJpg);
        PreviewGenerator::cleanupExpiredPreviews(0);
    }

    public function testCleanupWhenDirectoryDoesNotExist(): void {
        $nonExistentDir = sys_get_temp_dir() . '/nextgen_nonexistent_' . uniqid();
        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $nonExistentDir, 'baseurl' => 'https://example.com/uploads'];

        $purged = PreviewGenerator::cleanupExpiredPreviews(0);
        $this->assertSame(0, $purged);

        $purgedAlias = PreviewGenerator::cleanOldPreviews(0);
        $this->assertSame(0, $purgedAlias);
    }

    public function testCleanupWhenDirectoryIsEmpty(): void {
        $baseDir = sys_get_temp_dir() . '/nextgen_empty_test_' . uniqid();
        $previewDir = $baseDir . '/' . PreviewGenerator::PREVIEW_DIR_NAME;
        @mkdir($previewDir, 0750, true);

        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $baseDir, 'baseurl' => 'https://example.com/uploads'];

        $purged = PreviewGenerator::cleanupExpiredPreviews(0);
        $this->assertSame(0, $purged);

        @rmdir($previewDir);
        @rmdir($baseDir);
    }

    public function testCleanupSingleAndMultipleFiles(): void {
        $baseDir = sys_get_temp_dir() . '/nextgen_multi_test_' . uniqid();
        $previewDir = $baseDir . '/' . PreviewGenerator::PREVIEW_DIR_NAME;
        @mkdir($previewDir, 0750, true);

        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $baseDir, 'baseurl' => 'https://example.com/uploads'];

        $file1 = $previewDir . '/preview_1_webp_balanced_123456.webp';
        $file2 = $previewDir . '/preview_2_avif_high_654321.avif';
        file_put_contents($file1, 'PREVIEW_DATA_1');
        file_put_contents($file2, 'PREVIEW_DATA_2');

        $this->assertFileExists($file1);
        $this->assertFileExists($file2);

        $purged = PreviewGenerator::cleanupExpiredPreviews(0);
        $this->assertSame(2, $purged);
        $this->assertFileDoesNotExist($file1);
        $this->assertFileDoesNotExist($file2);

        @rmdir($previewDir);
        @rmdir($baseDir);
    }

    public function testCleanupPreservesHtaccessIndexPhpAndSubdirs(): void {
        $baseDir = sys_get_temp_dir() . '/nextgen_preserve_test_' . uniqid();
        $previewDir = $baseDir . '/' . PreviewGenerator::PREVIEW_DIR_NAME;
        PreviewGenerator::ensurePreviewDirectory($previewDir);

        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $baseDir, 'baseurl' => 'https://example.com/uploads'];

        $previewFile = $previewDir . '/preview_99_webp_balanced_abc123.webp';
        $otherFile = $previewDir . '/other_custom_file.txt';
        $subDir = $previewDir . '/nested_folder';

        file_put_contents($previewFile, 'PREVIEW_DATA');
        file_put_contents($otherFile, 'CUSTOM_DATA');
        @mkdir($subDir, 0750, true);

        $this->assertFileExists($previewDir . '/.htaccess');
        $this->assertFileExists($previewDir . '/index.php');
        $this->assertFileExists($otherFile);
        $this->assertDirectoryExists($subDir);

        $purged = PreviewGenerator::cleanupExpiredPreviews(0);
        $this->assertSame(1, $purged);

        // Preview file deleted
        $this->assertFileDoesNotExist($previewFile);

        // Security files and subdirectories preserved
        $this->assertFileExists($previewDir . '/.htaccess');
        $this->assertFileExists($previewDir . '/index.php');
        $this->assertFileExists($otherFile);
        $this->assertDirectoryExists($subDir);

        @unlink($otherFile);
        @unlink($previewDir . '/.htaccess');
        @unlink($previewDir . '/index.php');
        @rmdir($subDir);
        @rmdir($previewDir);
        @rmdir($baseDir);
    }

    public function testCleanupInvokedTwiceIsIdempotent(): void {
        $baseDir = sys_get_temp_dir() . '/nextgen_idempotent_test_' . uniqid();
        $previewDir = $baseDir . '/' . PreviewGenerator::PREVIEW_DIR_NAME;
        @mkdir($previewDir, 0750, true);

        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $baseDir, 'baseurl' => 'https://example.com/uploads'];

        $previewFile = $previewDir . '/preview_50_webp_balanced_xyz.webp';
        file_put_contents($previewFile, 'DATA');

        $firstRun = PreviewGenerator::cleanupExpiredPreviews(0);
        $this->assertSame(1, $firstRun);

        $secondRun = PreviewGenerator::cleanupExpiredPreviews(0);
        $this->assertSame(0, $secondRun);

        @rmdir($previewDir);
        @rmdir($baseDir);
    }

    public function testCleanOldPreviewsAliasWorks(): void {
        $baseDir = sys_get_temp_dir() . '/nextgen_alias_test_' . uniqid();
        $previewDir = $baseDir . '/' . PreviewGenerator::PREVIEW_DIR_NAME;
        @mkdir($previewDir, 0750, true);

        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $baseDir, 'baseurl' => 'https://example.com/uploads'];

        $previewFile = $previewDir . '/preview_77_webp_high_alias.webp';
        file_put_contents($previewFile, 'DATA_ALIAS');

        $purged = PreviewGenerator::cleanOldPreviews(0);
        $this->assertSame(1, $purged);
        $this->assertFileDoesNotExist($previewFile);

        @rmdir($previewDir);
        @rmdir($baseDir);
    }

    public function testCleanupExpiredPreviewsWithAgeFiltering(): void {
        $baseDir = sys_get_temp_dir() . '/nextgen_age_test_' . uniqid();
        $previewDir = $baseDir . '/' . PreviewGenerator::PREVIEW_DIR_NAME;
        @mkdir($previewDir, 0750, true);

        global $mock_upload_dir;
        $mock_upload_dir = ['basedir' => $baseDir, 'baseurl' => 'https://example.com/uploads'];

        $oldFile = $previewDir . '/preview_old_webp_balanced_111.webp';
        $newFile = $previewDir . '/preview_new_webp_balanced_222.webp';

        file_put_contents($oldFile, 'OLD_DATA');
        file_put_contents($newFile, 'NEW_DATA');

        // Set oldFile mtime to 3 hours ago (10800 seconds)
        touch($oldFile, time() - 10800);
        // Set newFile mtime to now
        touch($newFile, time());

        // Cleanup files older than 2 hours (7200 seconds)
        $purged = PreviewGenerator::cleanupExpiredPreviews(7200);
        $this->assertSame(1, $purged);

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($newFile);

        // Clean remaining
        @unlink($newFile);
        @rmdir($previewDir);
        @rmdir($baseDir);
    }
}

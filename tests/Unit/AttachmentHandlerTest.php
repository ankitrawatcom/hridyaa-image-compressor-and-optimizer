<?php
/**
 * Attachment Handler & Media Lifecycle Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Converter\ConverterManager;
use NextGen\Core\Config;
use NextGen\Image\AttachmentHandler;
use NextGen\Storage\MetadataManager;
use PHPUnit\Framework\TestCase;

class AttachmentHandlerTest extends TestCase {

    private string $tempDir;
    private Config $config;
    private ConverterManager $converter;
    private AttachmentHandler $handler;

    protected function setUp(): void {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/nextgen_media_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        $GLOBALS['mock_upload_dir']['basedir'] = str_replace('\\', '/', $this->tempDir);
        $GLOBALS['mock_posts'] = [];
        $GLOBALS['mock_post_meta'] = [];

        $this->config = new Config();
        $this->converter = new ConverterManager($this->config);
        $this->handler = new AttachmentHandler($this->config, $this->converter);
    }

    protected function tearDown(): void {
        parent::tearDown();
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function testProcessAttachmentAndThumbnails(): void {
        // 1. Create main image
        $mainFile = $this->tempDir . '/landscape.jpg';
        $im = imagecreatetruecolor(800, 600);
        imagefilledrectangle($im, 0, 0, 799, 599, imagecolorallocate($im, 50, 100, 150));
        imagejpeg($im, $mainFile, 90);

        // 2. Create intermediate thumbnail
        $thumbFile = $this->tempDir . '/landscape-300x200.jpg';
        $imThumb = imagecreatetruecolor(300, 200);
        imagefilledrectangle($imThumb, 0, 0, 299, 199, imagecolorallocate($imThumb, 50, 100, 150));
        imagejpeg($imThumb, $thumbFile, 85);

        $attachmentId = 42;
        $GLOBALS['mock_posts'][$attachmentId] = [
            'file' => $mainFile,
            'metadata' => [
                'width' => 800,
                'height' => 600,
                'file' => 'landscape.jpg',
                'sizes' => [
                    'medium' => [
                        'file' => 'landscape-300x200.jpg',
                        'width' => 300,
                        'height' => 200,
                        'mime-type' => 'image/jpeg',
                    ]
                ]
            ]
        ];

        $result = $this->handler->processAttachment($attachmentId);

        $this->assertEquals('completed', $result['status']);
        $this->assertFileExists($mainFile . '.webp');
        $this->assertFileExists($thumbFile . '.webp');
        $this->assertFileExists($mainFile); // Original preserved!
        $this->assertFileExists($thumbFile); // Original thumb preserved!

        $savedMeta = MetadataManager::getAttachmentData($attachmentId);
        $this->assertNotEmpty($savedMeta);
        $this->assertEquals('completed', $savedMeta['status']);
        $this->assertArrayHasKey('full', $savedMeta['sizes']);
        $this->assertArrayHasKey('medium', $savedMeta['sizes']);
    }

    public function testSafeDeletionRemovesWebpDerivativesOnly(): void {
        $mainFile = $this->tempDir . '/photo_to_delete.jpg';
        $webpMain = $mainFile . '.webp';
        $thumbFile = $this->tempDir . '/photo_to_delete-150x150.jpg';
        $webpThumb = $thumbFile . '.webp';

        file_put_contents($mainFile, 'ORIGINAL_JPEG_CONTENT');
        file_put_contents($webpMain, 'WEBP_DERIVATIVE_CONTENT');
        file_put_contents($thumbFile, 'ORIGINAL_THUMB_CONTENT');
        file_put_contents($webpThumb, 'WEBP_THUMB_DERIVATIVE_CONTENT');

        $attachmentId = 99;
        $GLOBALS['mock_posts'][$attachmentId] = [
            'file' => $mainFile,
            'metadata' => [
                'file' => 'photo_to_delete.jpg',
                'sizes' => [
                    'thumbnail' => ['file' => 'photo_to_delete-150x150.jpg']
                ]
            ]
        ];

        $this->handler->handleAttachmentDeletion($attachmentId);

        // WebP derivatives must be deleted
        $this->assertFileDoesNotExist($webpMain);
        $this->assertFileDoesNotExist($webpThumb);

        // Original files must remain intact (WordPress core handles original deletion separately)
        $this->assertFileExists($mainFile);
        $this->assertFileExists($thumbFile);
    }
}

<?php
/**
 * AVIF & Multi-Format Lifecycle Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Converter\ConverterManager;
use NextGen\Core\Config;
use NextGen\Image\AttachmentHandler;
use NextGen\Storage\MetadataManager;
use PHPUnit\Framework\TestCase;

class AvifLifecycleTest extends TestCase {

    private string $tempDir;
    private Config $config;
    private ConverterManager $converter;
    private AttachmentHandler $handler;

    protected function setUp(): void {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/nextgen_avif_life_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        $GLOBALS['mock_upload_dir']['basedir'] = str_replace('\\', '/', $this->tempDir);
        $GLOBALS['mock_posts'] = [];
        $GLOBALS['mock_post_meta'] = [];

        $this->config = new Config();
        $this->converter = new ConverterManager($this->config);
        $proGd = new \NextGenPro\Converter\ProGdAvifConverter();
        if ($proGd->isSupported()) {
            $this->converter->registerEngine($proGd);
        }
        $proImagick = new \NextGenPro\Converter\ProImagickAvifConverter();
        if ($proImagick->isSupported()) {
            $this->converter->registerEngine($proImagick);
        }
        add_filter('nextgen_enable_avif', '__return_true');
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

    public function testMultiFormatUploadGeneratesWebpAndAvif(): void {
        if (!$this->converter->isFormatSupported('avif')) {
            $this->markTestSkipped('AVIF encoding not supported in current environment.');
        }

        $fullPath = $this->tempDir . '/landscape.jpg';
        $im = imagecreatetruecolor(400, 300);
        for ($x = 0; $x < 400; $x += 10) {
            for ($y = 0; $y < 300; $y += 10) {
                $color = imagecolorallocate($im, ($x * 2) % 255, ($y * 2) % 255, ($x + $y) % 255);
                imagefilledrectangle($im, $x, $y, $x + 9, $y + 9, $color);
            }
        }
        imagejpeg($im, $fullPath, 90);

        $attachmentId = 101;
        $metadata = [
            'width'  => 400,
            'height' => 300,
            'file'   => 'landscape.jpg',
            'sizes'  => [],
        ];
        $GLOBALS['mock_posts'][$attachmentId] = [
            'file'     => $fullPath,
            'metadata' => $metadata,
        ];

        $result = $this->handler->processAttachment($attachmentId, $metadata);

        $this->assertEquals('completed', $result['status']);
        $this->assertFileExists($fullPath . '.webp');
        $this->assertFileExists($fullPath . '.avif');

        // Check metadata structure
        $savedMeta = MetadataManager::getAttachmentData($attachmentId);
        $this->assertNotNull($savedMeta);
        $this->assertEquals('completed', $savedMeta['status']);
        $this->assertArrayHasKey('formats', $savedMeta);
        $this->assertArrayHasKey('webp', $savedMeta['formats']);
        $this->assertArrayHasKey('avif', $savedMeta['formats']);
    }

    public function testDeletionRemovesWebpAndAvifWhilePreservingOriginal(): void {
        $fullPath = $this->tempDir . '/delete_target.jpg';
        $webpPath = $fullPath . '.webp';
        $avifPath = $fullPath . '.avif';

        file_put_contents($fullPath, 'ORIGINAL_JPEG_DATA');
        file_put_contents($webpPath, 'WEBP_DERIVATIVE_DATA');
        file_put_contents($avifPath, 'AVIF_DERIVATIVE_DATA');

        $attachmentId = 102;
        $GLOBALS['mock_posts'][$attachmentId] = [
            'file'     => $fullPath,
            'metadata' => [
                'file'  => 'delete_target.jpg',
                'sizes' => [],
            ],
        ];

        $this->handler->handleAttachmentDeletion($attachmentId);

        // Derivatives must be removed
        $this->assertFileDoesNotExist($webpPath);
        $this->assertFileDoesNotExist($avifPath);

        // Original file must remain untouched
        $this->assertFileExists($fullPath);
    }
}

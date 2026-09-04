<?php
/**
 * Delivery Layer Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Core\Config;
use NextGen\Delivery\PictureTagDelivery;
use PHPUnit\Framework\TestCase;

class DeliveryTest extends TestCase {

    private string $tempDir;
    private Config $config;
    private PictureTagDelivery $delivery;

    protected function setUp(): void {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/nextgen_deliv_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        $GLOBALS['mock_upload_dir'] = [
            'basedir' => str_replace('\\', '/', $this->tempDir),
            'baseurl' => 'https://example.com/wp-content/uploads',
        ];

        $this->config = new Config();
        $this->delivery = new PictureTagDelivery($this->config);
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

    public function testRewritesImgToPictureWhenWebpExists(): void {
        $sourceFile = $this->tempDir . '/hero.jpg';
        $webpFile = $sourceFile . '.webp';

        file_put_contents($sourceFile, 'JPEG_BYTES');
        file_put_contents($webpFile, 'WEBP_BYTES');

        $html = '<img src="https://example.com/wp-content/uploads/hero.jpg" class="aligncenter size-full" alt="Hero Banner" width="800" height="400" loading="lazy">';
        $output = $this->delivery->filterContent($html);

        $this->assertStringStartsWith('<picture class="nextgen-picture">', $output);
        $this->assertStringContainsString('<source type="image/webp" srcset="https://example.com/wp-content/uploads/hero.jpg.webp">', $output);
        $this->assertStringContainsString($html, $output);
        $this->assertStringEndsWith('</picture>', $output);
    }

    public function testLeavesImgUntouchedWhenWebpMissing(): void {
        $sourceFile = $this->tempDir . '/unconverted.jpg';
        file_put_contents($sourceFile, 'JPEG_BYTES');

        $html = '<img src="https://example.com/wp-content/uploads/unconverted.jpg" class="size-full" alt="Unconverted">';
        $output = $this->delivery->filterContent($html);

        $this->assertEquals($html, $output);
        $this->assertStringNotContainsString('<picture', $output);
    }

    public function testDoesNotNestExistingPictureTags(): void {
        $sourceFile = $this->tempDir . '/already_picture.jpg';
        $webpFile = $sourceFile . '.webp';

        file_put_contents($sourceFile, 'JPEG_BYTES');
        file_put_contents($webpFile, 'WEBP_BYTES');

        $existingPicture = '<picture><source srcset="custom.webp"><img src="https://example.com/wp-content/uploads/already_picture.jpg" alt="Already"></picture>';
        $output = $this->delivery->filterContent($existingPicture);

        $this->assertEquals($existingPicture, $output);
    }

    public function testDeliveryHandlesQueryStringsAndFragments(): void {
        $sourceFile = $this->tempDir . '/banner.jpg';
        $webpFile = $sourceFile . '.webp';

        file_put_contents($sourceFile, 'JPEG_BYTES');
        file_put_contents($webpFile, 'WEBP_BYTES');

        $html = '<img src="https://example.com/wp-content/uploads/banner.jpg?v=1.2.3#main" class="wp-image-55" alt="Banner">';
        $output = $this->delivery->filterContent($html);

        $this->assertStringStartsWith('<picture class="nextgen-picture">', $output);
        $this->assertStringContainsString('srcset="https://example.com/wp-content/uploads/banner.jpg.webp?v=1.2.3#main"', $output);
        $this->assertStringContainsString($html, $output);
    }

    public function testDeliveryHandlesResponsiveSrcsetWithQueryStrings(): void {
        $fullFile = $this->tempDir . '/responsive.jpg';
        $thumbFile = $this->tempDir . '/responsive-300x200.jpg';
        file_put_contents($fullFile, 'FULL_BYTES');
        file_put_contents($fullFile . '.webp', 'FULL_WEBP');
        file_put_contents($thumbFile, 'THUMB_BYTES');
        file_put_contents($thumbFile . '.webp', 'THUMB_WEBP');

        $html = '<img src="https://example.com/wp-content/uploads/responsive.jpg?ver=1" srcset="https://example.com/wp-content/uploads/responsive-300x200.jpg?ver=1 300w, https://example.com/wp-content/uploads/responsive.jpg?ver=1 800w" sizes="(max-width: 300px) 100vw, 300px" alt="Responsive">';
        $output = $this->delivery->filterContent($html);

        $this->assertStringStartsWith('<picture class="nextgen-picture">', $output);
        $this->assertStringContainsString('srcset="https://example.com/wp-content/uploads/responsive-300x200.jpg.webp?ver=1 300w, https://example.com/wp-content/uploads/responsive.jpg.webp?ver=1 800w"', $output);
        $this->assertStringContainsString('sizes="(max-width: 300px) 100vw, 300px"', $output);
    }
}

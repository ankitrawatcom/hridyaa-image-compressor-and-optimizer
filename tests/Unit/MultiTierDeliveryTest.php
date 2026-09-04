<?php
/**
 * Multi-Tier HTML <picture> Delivery Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Core\Config;
use NextGen\Delivery\PictureTagDelivery;
use PHPUnit\Framework\TestCase;

class MultiTierDeliveryTest extends TestCase {

    private string $tempDir;
    private Config $config;
    private PictureTagDelivery $delivery;

    protected function setUp(): void {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/nextgen_multideliv_test_' . uniqid();
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

    public function testFullHierarchyAvifAndWebp(): void {
        $sourceFile = $this->tempDir . '/hero.jpg';
        $avifFile = $sourceFile . '.avif';
        $webpFile = $sourceFile . '.webp';

        file_put_contents($sourceFile, 'JPEG_DATA');
        file_put_contents($avifFile, 'AVIF_DATA');
        file_put_contents($webpFile, 'WEBP_DATA');

        $html = '<img src="https://example.com/wp-content/uploads/hero.jpg" class="size-full wp-image-1" alt="Hero Banner">';
        $output = $this->delivery->filterContent($html);

        $this->assertStringStartsWith('<picture class="nextgen-picture">', $output);
        $this->assertStringEndsWith('</picture>', $output);

        // Verify hierarchy: AVIF appears before WebP
        $avifPos = strpos($output, '<source type="image/avif" srcset="https://example.com/wp-content/uploads/hero.jpg.avif">');
        $webpPos = strpos($output, '<source type="image/webp" srcset="https://example.com/wp-content/uploads/hero.jpg.webp">');
        $imgPos = strpos($output, $html);

        $this->assertNotFalse($avifPos);
        $this->assertNotFalse($webpPos);
        $this->assertNotFalse($imgPos);

        $this->assertLessThan($webpPos, $avifPos, 'AVIF source must appear before WebP source in <picture> tag');
        $this->assertLessThan($imgPos, $webpPos, 'WebP source must appear before fallback <img> tag');
    }

    public function testWebpOnlyDelivery(): void {
        $sourceFile = $this->tempDir . '/only_webp.jpg';
        $webpFile = $sourceFile . '.webp';

        file_put_contents($sourceFile, 'JPEG_DATA');
        file_put_contents($webpFile, 'WEBP_DATA');

        $html = '<img src="https://example.com/wp-content/uploads/only_webp.jpg" alt="WebP Only">';
        $output = $this->delivery->filterContent($html);

        $this->assertStringStartsWith('<picture class="nextgen-picture">', $output);
        $this->assertStringNotContainsString('type="image/avif"', $output);
        $this->assertStringContainsString('<source type="image/webp" srcset="https://example.com/wp-content/uploads/only_webp.jpg.webp">', $output);
        $this->assertStringContainsString($html, $output);
    }

    public function testAvifOnlyDelivery(): void {
        $sourceFile = $this->tempDir . '/only_avif.jpg';
        $avifFile = $sourceFile . '.avif';

        file_put_contents($sourceFile, 'JPEG_DATA');
        file_put_contents($avifFile, 'AVIF_DATA');

        $html = '<img src="https://example.com/wp-content/uploads/only_avif.jpg" alt="AVIF Only">';
        $output = $this->delivery->filterContent($html);

        $this->assertStringStartsWith('<picture class="nextgen-picture">', $output);
        $this->assertStringContainsString('<source type="image/avif" srcset="https://example.com/wp-content/uploads/only_avif.jpg.avif">', $output);
        $this->assertStringNotContainsString('type="image/webp"', $output);
        $this->assertStringContainsString($html, $output);
    }

    public function testNeitherDerivativeExistsLeavesImgUntouched(): void {
        $sourceFile = $this->tempDir . '/unconverted.jpg';
        file_put_contents($sourceFile, 'JPEG_DATA');

        $html = '<img src="https://example.com/wp-content/uploads/unconverted.jpg" alt="Unconverted">';
        $output = $this->delivery->filterContent($html);

        $this->assertEquals($html, $output);
        $this->assertStringNotContainsString('<picture', $output);
    }

    public function testMultiTierDeliveryWithQueryStrings(): void {
        $sourceFile = $this->tempDir . '/versioned.jpg';
        file_put_contents($sourceFile, 'JPEG_DATA');
        file_put_contents($sourceFile . '.avif', 'AVIF_DATA');
        file_put_contents($sourceFile . '.webp', 'WEBP_DATA');

        $html = '<img src="https://example.com/wp-content/uploads/versioned.jpg?v=2.0#top" class="alignnone" alt="Versioned">';
        $output = $this->delivery->filterContent($html);

        $this->assertStringStartsWith('<picture class="nextgen-picture">', $output);
        $this->assertStringContainsString('type="image/avif" srcset="https://example.com/wp-content/uploads/versioned.jpg.avif?v=2.0#top"', $output);
        $this->assertStringContainsString('type="image/webp" srcset="https://example.com/wp-content/uploads/versioned.jpg.webp?v=2.0#top"', $output);
        $this->assertStringContainsString($html, $output);
    }

    public function testMultiTierResponsiveSrcset(): void {
        $full = $this->tempDir . '/responsive.jpg';
        $thumb = $this->tempDir . '/responsive-300x200.jpg';

        file_put_contents($full, 'FULL_JPEG');
        file_put_contents($full . '.avif', 'FULL_AVIF');
        file_put_contents($full . '.webp', 'FULL_WEBP');

        file_put_contents($thumb, 'THUMB_JPEG');
        file_put_contents($thumb . '.avif', 'THUMB_AVIF');
        file_put_contents($thumb . '.webp', 'THUMB_WEBP');

        $html = '<img src="https://example.com/wp-content/uploads/responsive.jpg" srcset="https://example.com/wp-content/uploads/responsive-300x200.jpg 300w, https://example.com/wp-content/uploads/responsive.jpg 800w" sizes="(max-width: 300px) 100vw, 300px" alt="Responsive">';
        $output = $this->delivery->filterContent($html);

        $this->assertStringStartsWith('<picture class="nextgen-picture">', $output);
        $this->assertStringContainsString('<source type="image/avif" srcset="https://example.com/wp-content/uploads/responsive-300x200.jpg.avif 300w, https://example.com/wp-content/uploads/responsive.jpg.avif 800w" sizes="(max-width: 300px) 100vw, 300px">', $output);
        $this->assertStringContainsString('<source type="image/webp" srcset="https://example.com/wp-content/uploads/responsive-300x200.jpg.webp 300w, https://example.com/wp-content/uploads/responsive.jpg.webp 800w" sizes="(max-width: 300px) 100vw, 300px">', $output);
    }
}

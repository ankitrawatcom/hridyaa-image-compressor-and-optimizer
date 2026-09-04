<?php
/**
 * Image Converter Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Converter\ConverterManager;
use NextGen\Converter\GdConverter;
use NextGen\Core\Config;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase {

    private string $tempDir;
    private Config $config;
    private ConverterManager $manager;

    protected function setUp(): void {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/nextgen_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        $GLOBALS['mock_upload_dir']['basedir'] = str_replace('\\', '/', $this->tempDir);

        $this->config = new Config();
        $this->manager = new ConverterManager($this->config);
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

    public function testJpegToWebpConversion(): void {
        $jpegPath = $this->tempDir . '/test.jpg';
        $im = imagecreatetruecolor(400, 300);
        for ($x = 0; $x < 400; $x += 10) {
            for ($y = 0; $y < 300; $y += 10) {
                $color = imagecolorallocate($im, ($x * 2) % 255, ($y * 2) % 255, ($x + $y) % 255);
                imagefilledrectangle($im, $x, $y, $x + 9, $y + 9, $color);
            }
        }
        imagejpeg($im, $jpegPath, 90);

        $result = $this->manager->convert($jpegPath);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('success', $result->getErrorCode());
        $this->assertFileExists($result->getOutputPath());
        $this->assertStringEndsWith('.webp', $result->getOutputPath());
        $this->assertGreaterThan(0, $result->getConvertedSize());
    }

    public function testPngToWebpConversionWithTransparency(): void {
        $pngPath = $this->tempDir . '/transparent.png';
        $im = imagecreatetruecolor(300, 300);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, 299, 299, $transparent);
        for ($i = 0; $i < 50; $i++) {
            $color = imagecolorallocatealpha($im, rand(50, 255), rand(50, 255), rand(50, 255), rand(0, 100));
            imagefilledellipse($im, rand(30, 270), rand(30, 270), rand(20, 80), rand(20, 80), $color);
        }
        imagepng($im, $pngPath);

        // Convert with keep_larger_converted = true to test transparency conversion fidelity
        $result = $this->manager->convert($pngPath, null, ['keep_larger_converted' => true]);

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($result->getOutputPath());
        $this->assertGreaterThan(0, $result->getConvertedSize());
    }

    public function testStaticGifToWebpConversion(): void {
        $gifPath = $this->tempDir . '/static.gif';
        $im = imagecreatetruecolor(200, 200);
        for ($x = 0; $x < 200; $x += 20) {
            $color = imagecolorallocate($im, $x % 255, ($x * 2) % 255, 150);
            imagefilledrectangle($im, $x, 0, $x + 19, 199, $color);
        }
        imagegif($im, $gifPath);

        $result = $this->manager->convert($gifPath, null, ['keep_larger_converted' => true]);

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($result->getOutputPath());
    }

    public function testMissingFileReturnsFailure(): void {
        $missingPath = $this->tempDir . '/does_not_exist.jpg';
        $result = $this->manager->convert($missingPath);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('file_not_found', $result->getErrorCode());
    }

    public function testCorruptFileReturnsFailure(): void {
        $corruptPath = $this->tempDir . '/corrupt.jpg';
        file_put_contents($corruptPath, 'THIS_IS_NOT_A_REAL_IMAGE_DATA');

        $result = $this->manager->convert($corruptPath);

        $this->assertFalse($result->isSuccess());
        $this->assertContains($result->getErrorCode(), ['unsupported_format', 'invalid_image_data']);
    }

    public function testNegativeCompressionGuard(): void {
        // Create a 2x2 image where WebP header will be larger than low-quality JPEG
        $this->config->updateOptions(['keep_larger_converted' => false]);

        $jpegPath = $this->tempDir . '/small.jpg';
        $im = imagecreatetruecolor(2, 2);
        imagefilledrectangle($im, 0, 0, 1, 1, imagecolorallocate($im, 10, 10, 10));
        imagejpeg($im, $jpegPath, 10);

        $origSize = filesize($jpegPath);

        $result = $this->manager->convert($jpegPath, null, ['quality' => 100]);

        if ($result->getConvertedSize() >= $origSize) {
            $this->assertFalse($result->isSuccess());
            $this->assertEquals('skipped_larger', $result->getErrorCode());
            $this->assertFileDoesNotExist($result->getOutputPath());
        } else {
            $this->assertTrue($result->isSuccess());
        }
    }

    public function testDuplicateConversionReturnsCachedSuccess(): void {
        $jpegPath = $this->tempDir . '/duplicate_test.jpg';
        $im = imagecreatetruecolor(200, 200);
        for ($x = 0; $x < 200; $x += 10) {
            $color = imagecolorallocate($im, $x % 255, 100, 200);
            imagefilledrectangle($im, $x, 0, $x + 9, 199, $color);
        }
        imagejpeg($im, $jpegPath, 80);

        // First conversion
        $first = $this->manager->convert($jpegPath);
        $this->assertTrue($first->isSuccess());

        // Second conversion without force
        $second = $this->manager->convert($jpegPath);
        $this->assertTrue($second->isSuccess());
        $this->assertEquals('cached', $second->getEngine());
    }

    public function testStaleWebpReconvertedWhenSourceIsNewer(): void {
        $jpegPath = $this->tempDir . '/crop_test.jpg';
        $im = imagecreatetruecolor(200, 200);
        imagefilledrectangle($im, 0, 0, 199, 199, imagecolorallocate($im, 200, 100, 50));
        imagejpeg($im, $jpegPath, 80);

        // 1. First conversion at T0
        $t0 = time() - 200;
        touch($jpegPath, $t0);
        $first = $this->manager->convert($jpegPath);
        $this->assertTrue($first->isSuccess());
        touch($first->getOutputPath(), $t0 + 10);

        // 2. Simulate source modification (crop/rotate in WP Media Editor at T1)
        $t1 = time() - 50;
        touch($jpegPath, $t1); // Source is now NEWER than existing WebP ($t1 > $t0 + 10)

        // 3. Conversion without force must detect stale WebP and re-encode
        $second = $this->manager->convert($jpegPath);
        $this->assertTrue($second->isSuccess());
        $this->assertNotEquals('cached', $second->getEngine()); // Re-encoded with active engine (gd/imagick)
    }
}

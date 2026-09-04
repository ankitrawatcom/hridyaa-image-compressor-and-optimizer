<?php
/**
 * AVIF Converter Engine Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Converter\ConverterManager;
use NextGen\Core\Config;
use PHPUnit\Framework\TestCase;

class AvifConverterTest extends TestCase {

    private string $tempDir;
    private Config $config;
    private ConverterManager $manager;

    protected function setUp(): void {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/nextgen_avif_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        $GLOBALS['mock_upload_dir']['basedir'] = str_replace('\\', '/', $this->tempDir);

        $this->config = new Config();
        $this->manager = new ConverterManager($this->config);
        $proGd = new \NextGenPro\Converter\ProGdAvifConverter();
        if ($proGd->isSupported()) {
            $this->manager->registerEngine($proGd);
        }
        $proImagick = new \NextGenPro\Converter\ProImagickAvifConverter();
        if ($proImagick->isSupported()) {
            $this->manager->registerEngine($proImagick);
        }
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

    public function testJpegToAvifConversion(): void {
        if (!$this->manager->isFormatSupported('avif')) {
            $this->markTestSkipped('AVIF encoding not supported in current environment.');
        }

        $jpegPath = $this->tempDir . '/sample.jpg';
        $im = imagecreatetruecolor(400, 300);
        for ($x = 0; $x < 400; $x += 10) {
            for ($y = 0; $y < 300; $y += 10) {
                $color = imagecolorallocate($im, ($x * 2) % 255, ($y * 2) % 255, ($x + $y) % 255);
                imagefilledrectangle($im, $x, $y, $x + 9, $y + 9, $color);
            }
        }
        imagejpeg($im, $jpegPath, 90);

        $result = $this->manager->convert($jpegPath, null, [], 'avif');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('success', $result->getErrorCode());
        $this->assertFileExists($result->getOutputPath());
        $this->assertStringEndsWith('.avif', $result->getOutputPath());
        $this->assertGreaterThan(0, $result->getConvertedSize());

        // Validate AVIF binary header signature
        $header = file_get_contents($result->getOutputPath(), false, null, 0, 16);
        $this->assertStringContainsString('ftyp', $header);
        $this->assertStringContainsString('avif', $header);
    }

    public function testPngWithTransparencyToAvif(): void {
        if (!$this->manager->isFormatSupported('avif')) {
            $this->markTestSkipped('AVIF encoding not supported in current environment.');
        }

        $pngPath = $this->tempDir . '/transparent.png';
        $im = imagecreatetruecolor(300, 300);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, 299, 299, $transparent);
        for ($i = 0; $i < 40; $i++) {
            $color = imagecolorallocatealpha($im, rand(50, 255), rand(50, 255), rand(50, 255), rand(0, 100));
            imagefilledellipse($im, rand(30, 270), rand(30, 270), rand(20, 80), rand(20, 80), $color);
        }
        imagepng($im, $pngPath);

        $result = $this->manager->convert($pngPath, null, ['keep_larger_converted' => true], 'avif');

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($result->getOutputPath());
        $this->assertStringEndsWith('.avif', $result->getOutputPath());
    }

    public function testStaticGifToAvif(): void {
        if (!$this->manager->isFormatSupported('avif')) {
            $this->markTestSkipped('AVIF encoding not supported in current environment.');
        }

        $gifPath = $this->tempDir . '/static.gif';
        $im = imagecreatetruecolor(200, 200);
        for ($x = 0; $x < 200; $x += 20) {
            $color = imagecolorallocate($im, $x % 255, ($x * 2) % 255, 150);
            imagefilledrectangle($im, $x, 0, $x + 19, 199, $color);
        }
        imagegif($im, $gifPath);

        $result = $this->manager->convert($gifPath, null, ['keep_larger_converted' => true], 'avif');

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($result->getOutputPath());
    }

    public function testDuplicateAvifReturnsCachedSuccess(): void {
        if (!$this->manager->isFormatSupported('avif')) {
            $this->markTestSkipped('AVIF encoding not supported in current environment.');
        }

        $jpegPath = $this->tempDir . '/duplicate_avif.jpg';
        $im = imagecreatetruecolor(200, 200);
        for ($x = 0; $x < 200; $x += 10) {
            $color = imagecolorallocate($im, $x % 255, 100, 200);
            imagefilledrectangle($im, $x, 0, $x + 9, 199, $color);
        }
        imagejpeg($im, $jpegPath, 80);

        // First conversion
        $first = $this->manager->convert($jpegPath, null, [], 'avif');
        $this->assertTrue($first->isSuccess());

        // Second conversion without force
        $second = $this->manager->convert($jpegPath, null, [], 'avif');
        $this->assertTrue($second->isSuccess());
        $this->assertEquals('cached', $second->getEngine());
    }

    public function testStaleAvifReconvertedWhenSourceIsNewer(): void {
        if (!$this->manager->isFormatSupported('avif')) {
            $this->markTestSkipped('AVIF encoding not supported in current environment.');
        }

        $jpegPath = $this->tempDir . '/stale_avif_test.jpg';
        $im = imagecreatetruecolor(200, 200);
        imagefilledrectangle($im, 0, 0, 199, 199, imagecolorallocate($im, 200, 100, 50));
        imagejpeg($im, $jpegPath, 80);

        // 1. Initial conversion at T0
        $t0 = time() - 200;
        touch($jpegPath, $t0);
        $first = $this->manager->convert($jpegPath, null, [], 'avif');
        $this->assertTrue($first->isSuccess());
        touch($first->getOutputPath(), $t0 + 10);

        // 2. Modify source image at T1
        $t1 = time() - 50;
        touch($jpegPath, $t1);

        // 3. Conversion must detect stale AVIF and re-encode
        $second = $this->manager->convert($jpegPath, null, [], 'avif');
        $this->assertTrue($second->isSuccess());
        $this->assertNotEquals('cached', $second->getEngine());
    }
}

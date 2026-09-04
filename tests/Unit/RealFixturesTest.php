<?php
/**
 * Real Image Fixtures & Multi-Format Encoding Integration Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Converter\ConverterManager;
use NextGen\Core\Config;
use NextGen\Image\FilenameHelper;
use PHPUnit\Framework\TestCase;

class RealFixturesTest extends TestCase {

    private string $fixturesDir;
    private Config $config;
    private ConverterManager $manager;

    protected function setUp(): void {
        parent::setUp();
        $rawDir = dirname(__DIR__) . '/fixtures';
        if (!is_dir($rawDir)) {
            mkdir($rawDir, 0777, true);
        }
        $this->fixturesDir = FilenameHelper::normalizePath((string) realpath($rawDir));

        $this->createFixturesIfMissing();

        $GLOBALS['mock_upload_dir']['basedir'] = $this->fixturesDir;

        $this->config = new Config();
        $this->manager = new ConverterManager($this->config);
    }

    private function createFixturesIfMissing(): void {
        // 1. Real Standard JPEG fixture
        $jpgPath = $this->fixturesDir . '/standard.jpg';
        if (!file_exists($jpgPath)) {
            $im = imagecreatetruecolor(600, 400);
            for ($x = 0; $x < 600; $x += 20) {
                for ($y = 0; $y < 400; $y += 20) {
                    $color = imagecolorallocate($im, ($x * 3) % 255, ($y * 2) % 255, ($x + $y) % 255);
                    imagefilledrectangle($im, $x, $y, $x + 19, $y + 19, $color);
                }
            }
            imagejpeg($im, $jpgPath, 85);
        }

        // 2. Real Transparent PNG fixture
        $pngPath = $this->fixturesDir . '/transparent.png';
        if (!file_exists($pngPath)) {
            $im = imagecreatetruecolor(400, 400);
            imagealphablending($im, false);
            imagesavealpha($im, true);
            $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
            imagefilledrectangle($im, 0, 0, 399, 399, $transparent);
            for ($i = 0; $i < 30; $i++) {
                $color = imagecolorallocatealpha($im, rand(100, 255), rand(100, 255), rand(100, 255), rand(10, 80));
                imagefilledellipse($im, rand(50, 350), rand(50, 350), rand(40, 100), rand(40, 100), $color);
            }
            imagepng($im, $pngPath);
        }

        // 3. Corrupt image fixture
        $corruptPath = $this->fixturesDir . '/corrupt.jpg';
        if (!file_exists($corruptPath)) {
            file_put_contents($corruptPath, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00TRUNCATED_CORRUPT_BYTES");
        }

        // 4. Tiny image fixture
        $tinyPath = $this->fixturesDir . '/tiny.jpg';
        if (!file_exists($tinyPath)) {
            $im = imagecreatetruecolor(2, 2);
            imagefilledrectangle($im, 0, 0, 1, 1, imagecolorallocate($im, 128, 128, 128));
            imagejpeg($im, $tinyPath, 5);
        }
    }

    public function testRealJpegToWebpAndAvif(): void {
        $jpgPath = $this->fixturesDir . '/standard.jpg';

        // WebP
        $webpResult = $this->manager->convert($jpgPath, null, ['force' => true], 'webp');
        $this->assertTrue($webpResult->isSuccess());
        $this->assertFileExists($webpResult->getOutputPath());
        $this->assertStringEndsWith('.webp', $webpResult->getOutputPath());

        // AVIF
        if ($this->manager->isFormatSupported('avif')) {
            $avifResult = $this->manager->convert($jpgPath, null, ['force' => true], 'avif');
            $this->assertTrue($avifResult->isSuccess());
            $this->assertFileExists($avifResult->getOutputPath());
            $this->assertStringEndsWith('.avif', $avifResult->getOutputPath());
            $this->assertGreaterThan(0, $avifResult->getConvertedSize());
        }
    }

    public function testRealTransparentPngToWebpAndAvif(): void {
        $pngPath = $this->fixturesDir . '/transparent.png';

        // WebP
        $webpResult = $this->manager->convert($pngPath, null, ['force' => true, 'keep_larger_converted' => true], 'webp');
        $this->assertTrue($webpResult->isSuccess());
        $this->assertFileExists($webpResult->getOutputPath());

        // AVIF
        if ($this->manager->isFormatSupported('avif')) {
            $avifResult = $this->manager->convert($pngPath, null, ['force' => true, 'keep_larger_converted' => true], 'avif');
            $this->assertTrue($avifResult->isSuccess());
            $this->assertFileExists($avifResult->getOutputPath());
        }
    }

    public function testCorruptedFixtureSafelyFailsWithoutCrashing(): void {
        $corruptPath = $this->fixturesDir . '/corrupt.jpg';
        $result = $this->manager->convert($corruptPath, null, ['force' => true], 'webp');

        $this->assertFalse($result->isSuccess());
        $this->assertContains($result->getErrorCode(), ['invalid_image_data', 'corrupt_image', 'unsupported_format']);
    }
}

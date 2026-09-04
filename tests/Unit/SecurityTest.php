<?php
/**
 * Security & Input Validation Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Image\ImageValidator;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {

    private string $tempDir;

    protected function setUp(): void {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/nextgen_sec_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        $GLOBALS['mock_upload_dir']['basedir'] = str_replace('\\', '/', $this->tempDir);
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

    public function testPathTraversalSequencesRejected(): void {
        $maliciousPath = $this->tempDir . '/../../../../etc/passwd';
        $this->assertFalse(ImageValidator::isPathSafe($maliciousPath, $this->tempDir));

        $windowsTraversal = $this->tempDir . '/..\\..\\windows\\system32';
        $this->assertFalse(ImageValidator::isPathSafe($windowsTraversal, $this->tempDir));
    }

    public function testExtensionSpoofingRejected(): void {
        // PHP file disguised with .jpg extension
        $fakeJpg = $this->tempDir . '/shell.jpg';
        file_put_contents($fakeJpg, '<?php echo "malicious code"; ?>');

        [$isValid, $reason, $mimeType] = ImageValidator::validate($fakeJpg);

        $this->assertFalse($isValid);
        $this->assertNotEquals('image/jpeg', $mimeType);
    }

    public function testDecompressionBombRejected(): void {
        $bombPath = $this->tempDir . '/huge_dimension.jpg';
        $im = imagecreatetruecolor(5000, 5000); // 25,000,000 pixels
        imagefilledrectangle($im, 0, 0, 4999, 4999, imagecolorallocate($im, 10, 10, 10));
        imagejpeg($im, $bombPath, 50);

        // Limit maxDimension to 4096
        [$isValid, $reason] = ImageValidator::validate($bombPath, 4096);

        $this->assertFalse($isValid);
        $this->assertEquals('decompression_bomb_guard', $reason);
    }
}

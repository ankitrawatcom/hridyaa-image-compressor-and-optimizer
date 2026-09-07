<?php
/**
 * Stage 19: Complete Rebrand Verification Tests.
 *
 * @package NextGen\Tests\Unit
 */

namespace NextGen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NextGen\LicenseBackend\EmailService;

class RebrandVerificationTest extends TestCase {

    private string $projectRoot;

    protected function setUp(): void {
        parent::setUp();
        $this->projectRoot = dirname(__DIR__, 2);
    }

    public function testBasePluginHeaderHasRebrandedNameAndMetadata(): void {
        $baseFile = $this->projectRoot . '/nextgen-image-optimizer.php';
        $this->assertFileExists($baseFile);
        $content = file_get_contents($baseFile);

        $this->assertMatchesRegularExpression('/Plugin Name:\s+Hridyaa Image Compressor and Optimizer/i', $content);
        $this->assertMatchesRegularExpression('/Author:\s+Ankit Rawat/i', $content);
        $this->assertMatchesRegularExpression('/Text Domain:\s+hridyaa-image-compressor-and-optimizer/i', $content);
        $this->assertStringContainsString('Compress and optimize WordPress images, convert to WebP and AVIF', $content);
    }

    public function testProPluginHeaderHasRebrandedNameAndMetadata(): void {
        $siblingPro = dirname(__DIR__, 2) . '/hridyaa-image-compressor-and-optimizer-pro';
        $proFile = file_exists("$siblingPro/nextgen-image-optimizer-pro.php") 
            ? "$siblingPro/nextgen-image-optimizer-pro.php" 
            : $this->projectRoot . '/nextgen-image-optimizer-pro/nextgen-image-optimizer-pro.php';
        
        if (!file_exists($proFile)) {
            $this->markTestSkipped('Pro plugin file not present in Free standalone environment');
        }
        $content = file_get_contents($proFile);

        $this->assertMatchesRegularExpression('/Plugin Name:\s+Hridyaa Image Compressor and Optimizer Pro/i', $content);
        $this->assertMatchesRegularExpression('/Author:\s+Ankit Rawat/i', $content);
        $this->assertMatchesRegularExpression('/Text Domain:\s+hridyaa-image-compressor-and-optimizer-pro/i', $content);
        $this->assertStringContainsString('Commercial AVIF Pro Engine & Premium Optimizer Addon for Hridyaa Image Compressor and Optimizer', $content);
    }

    public function testReadmeHasRebrandedTitleAndDescription(): void {
        $readmeFile = $this->projectRoot . '/readme.txt';
        $this->assertFileExists($readmeFile);
        $content = file_get_contents($readmeFile);

        $this->assertStringStartsWith('=== Hridyaa Image Compressor and Optimizer ===', trim($content));
        $this->assertStringContainsString('Compress and optimize WordPress images, convert to WebP and AVIF, and reduce image sizes for faster page loads.', $content);
        $this->assertStringContainsString('**Hridyaa Image Compressor and Optimizer**', $content);
    }

    public function testEmailServiceDefaultsToNewBranding(): void {
        $emailService = new EmailService();
        $ref = new \ReflectionClass($emailService);
        $fromNameProp = $ref->getProperty('fromName');
        $fromNameProp->setAccessible(true);
        $this->assertSame('Hridyaa Image Compressor and Optimizer', $fromNameProp->getValue($emailService));
    }

    public function testInternalBackwardCompatibilityIdentifiersPreserved(): void {
        $baseContent = file_get_contents($this->projectRoot . '/nextgen-image-optimizer.php');
        $this->assertStringContainsString("define('NEXTGEN_VERSION', '1.2.1')", $baseContent);

        $siblingPro = dirname(__DIR__, 2) . '/hridyaa-image-compressor-and-optimizer-pro';
        $proFile = file_exists("$siblingPro/nextgen-image-optimizer-pro.php") 
            ? "$siblingPro/nextgen-image-optimizer-pro.php" 
            : $this->projectRoot . '/nextgen-image-optimizer-pro/nextgen-image-optimizer-pro.php';

        if (file_exists($proFile)) {
            $proContent = file_get_contents($proFile);
            $this->assertStringContainsString("define('NEXTGEN_PRO_VERSION', '1.2.1')", $proContent);
        }
    }

    public function testFreeReleaseZipContainsNewSlugDirectory(): void {
        $distDir = dirname(__DIR__, 2) . '/WebP-AVIF/dist';
        $zipPath = file_exists("$distDir/hridyaa-image-compressor-and-optimizer-v1.2.1.zip")
            ? "$distDir/hridyaa-image-compressor-and-optimizer-v1.2.1.zip"
            : $this->projectRoot . '/dist/hridyaa-image-compressor-and-optimizer-v1.2.1.zip';

        if (!file_exists($zipPath)) {
            $this->markTestSkipped('Release ZIP not present in standalone repository test environment');
        }

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath));
        $hasSlugRoot = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (strpos($stat['name'], 'hridyaa-image-compressor-and-optimizer/') === 0) {
                $hasSlugRoot = true;
                break;
            }
        }
        $zip->close();
        $this->assertTrue($hasSlugRoot, 'Free release package must use hridyaa-image-compressor-and-optimizer/ root folder');
    }
}

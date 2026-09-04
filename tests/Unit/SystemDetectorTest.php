<?php
/**
 * System Detector Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Support\SystemDetector;
use PHPUnit\Framework\TestCase;

class SystemDetectorTest extends TestCase {

    public function testProbeCapabilitiesReturnsExpectedStructure(): void {
        $detector = new SystemDetector();
        $caps = $detector->probeCapabilities();

        $this->assertIsArray($caps);
        $this->assertArrayHasKey('php_version', $caps);
        $this->assertArrayHasKey('webp_supported', $caps);
        $this->assertArrayHasKey('primary_engine', $caps);
        $this->assertArrayHasKey('gd', $caps);
        $this->assertArrayHasKey('imagick', $caps);
        $this->assertArrayHasKey('memory_limit', $caps);
        $this->assertIsBool($caps['webp_supported']);
    }

    public function testGdDetectionMatchesEnvironment(): void {
        $detector = new SystemDetector();
        $caps = $detector->getCapabilities(true);

        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $this->assertTrue($caps['gd']['installed']);
            $this->assertTrue($caps['gd']['webp_encode']);
            $this->assertTrue($caps['webp_supported']);
        }
    }

    public function testTransientCachingAndClear(): void {
        $detector = new SystemDetector();
        $caps1 = $detector->getCapabilities(true);
        $this->assertNotEmpty($caps1);

        $detector->clearCache();
        $caps2 = $detector->getCapabilities(false);
        $this->assertNotEmpty($caps2);
    }
}

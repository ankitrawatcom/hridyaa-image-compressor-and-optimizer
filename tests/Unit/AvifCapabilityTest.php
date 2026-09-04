<?php
/**
 * AVIF Capability Detector Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Support\SystemDetector;
use PHPUnit\Framework\TestCase;

class AvifCapabilityTest extends TestCase {

    public function testProbeCapabilitiesDetectsAvif(): void {
        $detector = new SystemDetector();
        $caps = $detector->probeCapabilities();

        $this->assertIsArray($caps);
        $this->assertArrayHasKey('avif_supported', $caps);
        $this->assertArrayHasKey('primary_avif_engine', $caps);
        $this->assertIsBool($caps['avif_supported']);

        if (function_exists('imageavif')) {
            $this->assertTrue($caps['avif_supported']);
            $this->assertEquals('gd', $caps['primary_avif_engine']);
            $this->assertTrue($caps['gd']['avif_encode']);
            $this->assertContains('AVIF', $caps['gd']['formats']);
        }
    }

    public function testTransientCachingIncludesAvifCapabilities(): void {
        $detector = new SystemDetector();
        $detector->clearCache();

        $caps = $detector->getCapabilities(true);
        $this->assertArrayHasKey('avif_supported', $caps);

        $cached = $detector->getCapabilities(false);
        $this->assertEquals($caps['avif_supported'], $cached['avif_supported']);
    }
}

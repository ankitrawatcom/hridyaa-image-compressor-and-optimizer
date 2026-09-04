<?php
/**
 * QualityPresetManager Unit Test.
 *
 * @package NextGen\Tests\Unit\V11
 */

namespace NextGen\Tests\Unit\V11;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\QualityPresetManager;

class QualityPresetManagerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        delete_option('nextgen_preset');
        delete_option('nextgen_quality');
        delete_option('nextgen_webp_quality');
        delete_option('nextgen_avif_quality');
    }

    public function testPresetDefinitions(): void {
        $presets = QualityPresetManager::getPresets();
        $this->assertArrayHasKey('high', $presets);
        $this->assertArrayHasKey('balanced', $presets);
        $this->assertArrayHasKey('aggressive', $presets);

        // High
        $this->assertSame(90, $presets['high']['webp_quality']);
        $this->assertSame(85, $presets['high']['avif_quality']);

        // Balanced
        $this->assertSame(82, $presets['balanced']['webp_quality']);
        $this->assertSame(75, $presets['balanced']['avif_quality']);

        // Aggressive
        $this->assertSame(75, $presets['aggressive']['webp_quality']);
        $this->assertSame(65, $presets['aggressive']['avif_quality']);
    }

    public function testActivePresetDefaultsToBalanced(): void {
        $this->assertSame('balanced', QualityPresetManager::getActivePreset());
        $this->assertSame(82, QualityPresetManager::getQuality('webp'));
        $this->assertSame(75, QualityPresetManager::getQuality('avif'));
    }

    public function testGetQualityWithExplicitPreset(): void {
        $this->assertSame(90, QualityPresetManager::getQuality('webp', 'high'));
        $this->assertSame(85, QualityPresetManager::getQuality('avif', 'high'));

        $this->assertSame(75, QualityPresetManager::getQuality('webp', 'aggressive'));
        $this->assertSame(65, QualityPresetManager::getQuality('avif', 'aggressive'));
    }

    public function testSettingsMigrationFromV10(): void {
        // Mock v1.0.0 option state with custom quality = 90
        update_option('nextgen_quality', 90);
        QualityPresetManager::migrateSettings();

        $this->assertSame('high', get_option('nextgen_preset'));
        $this->assertSame(90, get_option('nextgen_webp_quality'));
        $this->assertSame(85, get_option('nextgen_avif_quality'));

        // Idempotent check
        QualityPresetManager::migrateSettings();
        $this->assertSame('high', get_option('nextgen_preset'));
    }
}

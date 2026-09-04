<?php
/**
 * StatsManager Unit Test.
 *
 * @package NextGen\Tests\Unit\V11
 */

namespace NextGen\Tests\Unit\V11;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\StatsManager;

class StatsManagerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $mock_post_meta;
        $mock_post_meta = [];
        delete_option(StatsManager::OPTION_KEY);
    }

    public function testDefaultStats(): void {
        $stats = StatsManager::getStats();
        $this->assertSame(0, $stats['total_originals_processed']);
        $this->assertSame(0, $stats['total_webp_generated']);
        $this->assertSame(0, $stats['total_avif_generated']);
        $this->assertSame(0, $stats['total_bytes_saved']);
        $this->assertSame(0.0, $stats['percentage_saved']);
    }

    public function testRecordConversionAndSavings(): void {
        // Original: 100,000 bytes -> WebP: 40,000 bytes (Saved 60,000 bytes)
        StatsManager::recordConversion(101, 'webp', 100000, 40000, 'imagick', 82);

        $stats = StatsManager::getStats();
        $this->assertSame(1, $stats['total_originals_processed']);
        $this->assertSame(1, $stats['total_webp_generated']);
        $this->assertSame(0, $stats['total_avif_generated']);
        $this->assertSame(100000, $stats['total_original_bytes']);
        $this->assertSame(40000, $stats['total_webp_bytes']);
        $this->assertSame(60000, $stats['total_bytes_saved']);
        $this->assertSame(60.0, $stats['percentage_saved']);

        // Now record AVIF for same attachment: Original: 100,000 -> AVIF: 30,000 (Saved 70,000 bytes)
        StatsManager::recordConversion(101, 'avif', 100000, 30000, 'imagick', 75);

        $statsAfterAvif = StatsManager::getStats();
        $this->assertSame(1, $statsAfterAvif['total_originals_processed']);
        $this->assertSame(1, $statsAfterAvif['total_webp_generated']);
        $this->assertSame(1, $statsAfterAvif['total_avif_generated']);
        $this->assertSame(30000, $statsAfterAvif['total_avif_bytes']);
        $this->assertSame(130000, $statsAfterAvif['total_bytes_saved']);
    }

    public function testNegativeCompressionGuardDiscard(): void {
        // Derivative larger than original (100k -> 110k)
        StatsManager::recordConversion(102, 'webp', 100000, 110000, 'gd', 82);

        $stats = StatsManager::getStats();
        $this->assertSame(0, $stats['total_originals_processed']);
        $this->assertSame(0, $stats['total_bytes_saved']);
    }

    public function testRecordDeletion(): void {
        StatsManager::recordConversion(201, 'webp', 100000, 50000, 'imagick', 82);
        $this->assertSame(1, StatsManager::getStats()['total_originals_processed']);

        StatsManager::recordDeletion(201);
        $statsAfterDelete = StatsManager::getStats();
        $this->assertSame(0, $statsAfterDelete['total_originals_processed']);
        $this->assertSame(0, $statsAfterDelete['total_webp_generated']);
        $this->assertSame(0, $statsAfterDelete['total_bytes_saved']);
    }

    public function testRecalculateAllStats(): void {
        $recalculated = StatsManager::recalculateAllStats();
        $this->assertArrayHasKey('total_originals_processed', $recalculated);
        $this->assertArrayHasKey('last_reconciled_at', $recalculated);
    }
}

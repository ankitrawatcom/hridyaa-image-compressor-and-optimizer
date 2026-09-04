<?php
/**
 * Bulk Queue Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Queue\QueueManager;
use NextGen\Storage\MetadataManager;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mock_posts'] = [
            101 => ['file' => '/uploads/img1.jpg'],
            102 => ['file' => '/uploads/img2.jpg'],
            103 => ['file' => '/uploads/img3.png'],
        ];
        $GLOBALS['mock_post_meta'] = [];
    }

    public function testGetPendingAttachmentIds(): void {
        $pending = QueueManager::getPendingAttachmentIds();
        $this->assertCount(3, $pending);
        $this->assertContains(101, $pending);
        $this->assertContains(102, $pending);
        $this->assertContains(103, $pending);

        // Mark 101 as completed
        MetadataManager::saveAttachmentData(101, ['status' => 'completed']);

        $pendingAfter = QueueManager::getPendingAttachmentIds();
        $this->assertCount(2, $pendingAfter);
        $this->assertNotContains(101, $pendingAfter);
    }

    public function testResetAllMetadata(): void {
        MetadataManager::saveAttachmentData(101, ['status' => 'completed']);
        MetadataManager::saveAttachmentData(102, ['status' => 'completed']);

        $reset = QueueManager::resetAllMetadata();
        $this->assertGreaterThanOrEqual(1, $reset);
        $this->assertEmpty($GLOBALS['mock_post_meta']);
    }
}

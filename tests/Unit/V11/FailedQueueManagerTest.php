<?php
/**
 * FailedQueueManager Unit Test.
 *
 * @package NextGen\Tests\Unit\V11
 */

namespace NextGen\Tests\Unit\V11;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\FailedQueueManager;

class FailedQueueManagerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        FailedQueueManager::clearQueue();
        FailedQueueManager::releaseLock();
    }

    public function testRecordFailureAndRetrieve(): void {
        FailedQueueManager::recordFailure(501, 'webp', 'timeout_interrupted', 'Execution timed out', '2026/08/hero.jpg');

        $items = FailedQueueManager::getFailedItems();
        $this->assertCount(1, $items);
        $this->assertSame(501, $items[0]['attachment_id']);
        $this->assertSame('webp', $items[0]['format']);
        $this->assertSame('timeout_interrupted', $items[0]['failure_category']);
        $this->assertSame(FailedQueueManager::STATE_FAILED, $items[0]['state']);
        $this->assertSame(0, $items[0]['retry_count']);
    }

    public function testMaxRetriesTransitionToPermanentlyFailed(): void {
        FailedQueueManager::recordFailure(502, 'avif', 'memory_limit_exhausted', 'OOM'); // attempt 0
        FailedQueueManager::recordFailure(502, 'avif', 'memory_limit_exhausted', 'OOM'); // attempt 1
        FailedQueueManager::recordFailure(502, 'avif', 'memory_limit_exhausted', 'OOM'); // attempt 2
        FailedQueueManager::recordFailure(502, 'avif', 'memory_limit_exhausted', 'OOM'); // attempt 3

        $items = FailedQueueManager::getFailedItems();
        $this->assertCount(1, $items);
        $this->assertSame(3, $items[0]['retry_count']);
        $this->assertSame(FailedQueueManager::STATE_PERMANENTLY_FAILED, $items[0]['state']);
    }

    public function testCorruptSourceTransitionsDirectlyToPermanentlyFailed(): void {
        FailedQueueManager::recordFailure(503, 'webp', 'corrupt_source', 'Header invalid');

        $items = FailedQueueManager::getFailedItems();
        $this->assertCount(1, $items);
        $this->assertSame(FailedQueueManager::STATE_PERMANENTLY_FAILED, $items[0]['state']);
    }

    public function testMarkSucceededRemovesItem(): void {
        FailedQueueManager::recordFailure(504, 'webp', 'generic', 'Temporary error');
        $this->assertCount(1, FailedQueueManager::getFailedItems());

        FailedQueueManager::markSucceeded(504, 'webp');
        $this->assertCount(0, FailedQueueManager::getFailedItems());
    }

    public function testWorkerLockingAndConcurrency(): void {
        $this->assertTrue(FailedQueueManager::acquireLock());
        // Second lock attempt must fail
        $this->assertFalse(FailedQueueManager::acquireLock());

        FailedQueueManager::releaseLock();
        $this->assertTrue(FailedQueueManager::acquireLock());
    }
}

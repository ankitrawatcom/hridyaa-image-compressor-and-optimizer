<?php
/**
 * BackgroundBatchWorker Unit Tests.
 *
 * @package NextGen\Tests\Unit\V12
 */

namespace NextGen\Tests\Unit\V12;

use PHPUnit\Framework\TestCase;
use NextGen\Batch\BackgroundBatchWorker;
use NextGen\Admin\QualityPresetManager;

class BackgroundBatchWorkerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        delete_option(BackgroundBatchWorker::OPTION_KEY);
        delete_transient(BackgroundBatchWorker::LOCK_KEY);
        QualityPresetManager::migrateSettings();
    }

    protected function tearDown(): void {
        parent::tearDown();
        delete_option(BackgroundBatchWorker::OPTION_KEY);
        delete_transient(BackgroundBatchWorker::LOCK_KEY);
    }

    public function testInitialDefaultState(): void {
        $state = BackgroundBatchWorker::getState();
        $this->assertSame(BackgroundBatchWorker::STATE_IDLE, $state['status']);
        $this->assertSame(0, $state['total_attachments']);
        $this->assertSame(0, $state['processed_count']);
        $this->assertSame(0, $state['last_attachment_id']);
    }

    public function testStartPauseResumeCancelLifecycle(): void {
        $this->assertTrue(BackgroundBatchWorker::start('webp', 'high'));
        $state = BackgroundBatchWorker::getState();
        $this->assertSame(BackgroundBatchWorker::STATE_RUNNING, $state['status']);
        $this->assertSame('webp', $state['target_format']);
        $this->assertSame('high', $state['quality_preset']);

        // Cannot start when already running
        $this->assertFalse(BackgroundBatchWorker::start('avif', 'balanced'));

        // Pause
        $this->assertTrue(BackgroundBatchWorker::pause());
        $this->assertSame(BackgroundBatchWorker::STATE_PAUSED, BackgroundBatchWorker::getState()['status']);

        // Resume
        $this->assertTrue(BackgroundBatchWorker::resume());
        $this->assertSame(BackgroundBatchWorker::STATE_RUNNING, BackgroundBatchWorker::getState()['status']);

        // Cancel
        $this->assertTrue(BackgroundBatchWorker::cancel());
        $this->assertSame(BackgroundBatchWorker::STATE_CANCELLED, BackgroundBatchWorker::getState()['status']);
    }

    public function testLockAcquisitionAndMutualExclusion(): void {
        $this->assertTrue(BackgroundBatchWorker::acquireLock());
        $this->assertFalse(BackgroundBatchWorker::acquireLock(), "Second lock acquisition must fail");

        BackgroundBatchWorker::releaseLock();
        $this->assertTrue(BackgroundBatchWorker::acquireLock());
        BackgroundBatchWorker::releaseLock();
    }

    public function testProcessTickWhenNotRunning(): void {
        $res = BackgroundBatchWorker::processCronTick();
        $this->assertSame('not_running', $res['status']);
    }
}

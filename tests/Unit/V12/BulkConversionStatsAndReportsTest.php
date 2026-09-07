<?php
declare(strict_types=1);

namespace NextGen\Tests\Unit\V12;

use PHPUnit\Framework\TestCase;
use NextGen\Core\Config;
use NextGen\Core\Features;
use NextGen\Image\AttachmentHandler;
use NextGen\Converter\ConverterManager;
use NextGen\Storage\MetadataManager;
use NextGen\Admin\StatsManager;
use NextGen\Admin\ReportsView;
use NextGen\Queue\QueueManager;
use NextGen\Delivery\PictureTagDelivery;
use NextGenPro\Converter\ProGdAvifConverter;

class BulkConversionStatsAndReportsTest extends TestCase {

    private string $tempDir;
    private array $savedFilters = [];
    private array $savedOptions = [];

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter_registry, $mock_options, $mock_post_meta, $mock_posts;
        $this->savedFilters = $wp_filter_registry;
        $this->savedOptions = $mock_options;
        $mock_post_meta = [];
        $mock_posts = [];

        $this->tempDir = str_replace('\\', '/', sys_get_temp_dir() . '/bulk_stats_test_' . uniqid());
        @mkdir($this->tempDir, 0750, true);

        global $mock_upload_dir;
        $mock_upload_dir = [
            'basedir' => $this->tempDir,
            'baseurl' => 'https://example.com/uploads',
        ];
    }

    protected function tearDown(): void {
        global $wp_filter_registry, $mock_options, $mock_post_meta, $mock_posts;
        $wp_filter_registry = $this->savedFilters;
        $mock_options = $this->savedOptions;
        $mock_post_meta = [];
        $mock_posts = [];

        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testAttachmentHandlerUpdatesStatsManagerForWebp(): void {
        $sourceJpg = $this->tempDir . '/test_webp.jpg';
        $im = imagecreatetruecolor(100, 100);
        imagefill($im, 0, 0, imagecolorallocate($im, 100, 150, 200));
        imagejpeg($im, $sourceJpg, 90);
        imagedestroy($im);

        $attachmentId = 701;
        global $mock_posts;
        $mock_posts[$attachmentId] = (object) [
            'ID' => $attachmentId,
            'post_title' => 'Test WebP Attachment',
            'post_mime_type' => 'image/jpeg',
            'file' => $sourceJpg,
            'guid' => 'https://example.com/uploads/test_webp.jpg',
        ];

        $config = new Config();
        $config->updateOptions(['optimization_format' => 'webp']);
        $converter = new ConverterManager($config);
        $handler = new AttachmentHandler($config, $converter);

        $result = $handler->processAttachment($attachmentId);
        $this->assertSame('completed', $result['status']);

        $stats = StatsManager::getStats();
        $this->assertSame(1, $stats['total_originals_processed']);
        $this->assertSame(1, $stats['total_webp_generated']);
        $this->assertSame(0, $stats['total_avif_generated']);
        $this->assertGreaterThan(0, $stats['total_bytes_saved']);

        $meta = get_post_meta($attachmentId, StatsManager::META_KEY, true);
        $this->assertIsArray($meta);
        $this->assertTrue($meta['webp']['generated']);
    }

    public function testAttachmentHandlerUpdatesStatsManagerForAvifOnly(): void {
        add_filter('nextgen_enable_avif', '__return_true');
        add_filter('nextgen_pro_is_active', '__return_true');

        $sourceJpg = $this->tempDir . '/test_avif.jpg';
        $im = imagecreatetruecolor(100, 100);
        imagefill($im, 0, 0, imagecolorallocate($im, 50, 100, 150));
        imagejpeg($im, $sourceJpg, 90);
        imagedestroy($im);

        $attachmentId = 702;
        global $mock_posts;
        $mock_posts[$attachmentId] = (object) [
            'ID' => $attachmentId,
            'post_title' => 'Test AVIF Attachment',
            'post_mime_type' => 'image/jpeg',
            'file' => $sourceJpg,
            'guid' => 'https://example.com/uploads/test_avif.jpg',
        ];

        $config = new Config();
        $config->updateOptions(['optimization_format' => 'avif', 'auto_convert_avif' => true]);
        $converter = new ConverterManager($config);
        if (class_exists('\NextGenPro\Converter\ProGdAvifConverter')) {
            $converter->registerEngine(new ProGdAvifConverter());
        }

        $handler = new AttachmentHandler($config, $converter);
        $result = $handler->processAttachment($attachmentId);
        $this->assertSame('completed', $result['status']);

        $stats = StatsManager::getStats();
        $this->assertSame(1, $stats['total_originals_processed']);
        $this->assertSame(0, $stats['total_webp_generated']);
        $this->assertSame(1, $stats['total_avif_generated']);
        $this->assertGreaterThan(0, $stats['total_bytes_saved']);

        $meta = get_post_meta($attachmentId, StatsManager::META_KEY, true);
        $this->assertIsArray($meta);
        $this->assertTrue($meta['avif']['generated']);
    }

    public function testResetAllMetadataClearsStatsManagerAndBothMetaKeys(): void {
        StatsManager::recordConversion(801, 'webp', 10000, 5000, 'gd', 82);
        MetadataManager::saveAttachmentData(801, ['status' => 'completed']);

        $this->assertSame(1, StatsManager::getStats()['total_originals_processed']);

        global $mock_post_meta;
        $mock_post_meta[801][MetadataManager::META_KEY] = ['status' => 'completed'];
        $mock_post_meta[801][StatsManager::META_KEY] = ['original_size' => 10000];

        QueueManager::resetAllMetadata();

        $this->assertSame(0, StatsManager::getStats()['total_originals_processed']);
        $this->assertSame(0, StatsManager::getStats()['total_bytes_saved']);
    }

    public function testReportsViewTruthfulZeroRenderingWhenOnlyAvifGenerated(): void {
        StatsManager::recordConversion(901, 'avif', 10000, 4000, 'pro_gd_avif', 65);
        add_filter('nextgen_pro_is_active', '__return_true');

        ob_start();
        ReportsView::render();
        $html = ob_get_clean();

        // WebP format row should be truthful zero
        $this->assertStringContainsString('0 B (0 derivatives)', $html);
        $this->assertStringContainsString('0 B (0%)', $html);

        // AVIF format row should reflect actual derivatives
        $this->assertStringContainsString('1 derivatives', $html);
    }
}
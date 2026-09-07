<?php
/**
 * Automated Stats Reconciliation and Historical Metadata Test.
 *
 * @package NextGen\Tests\Unit\V12
 */

namespace NextGen\Tests\Unit\V12;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\StatsManager;
use NextGen\Admin\ReportsView;
use NextGen\Admin\AdminDashboardView;
use NextGen\Admin\AdminController;
use NextGen\Admin\SettingsPage;
use NextGen\Core\Config;
use NextGen\Support\SystemDetector;
use NextGen\Storage\MetadataManager;

class StatsReconciliationAutoAndHistoricalTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $mock_options, $mock_post_meta, $wpdb;
        $mock_options = [];
        $mock_post_meta = [];
    }

    public function testLazyAutoReconciliationWhenStatsOptionIsEmpty(): void {
        global $wpdb, $mock_options, $mock_post_meta;

        // Populate 3 posts with historical _nextgen_webp_data (e.g. from Hotfix 7 AVIF conversions)
        for ($i = 101; $i <= 103; $i++) {
            $mock_post_meta[$i] = [
                MetadataManager::META_KEY => [
                    'status'               => 'completed',
                    'attachment_id'        => $i,
                    'total_original_bytes' => 200000,
                    'total_saved_bytes'    => 150000,
                    'sizes'                => [
                        'full' => [
                            'status'         => 'success',
                            'original_size'  => 200000,
                            'converted_size' => 50000,
                            'saved_bytes'    => 150000,
                        ]
                    ],
                    'formats'              => [
                        'avif' => [
                            'sizes'       => [
                                'full' => [
                                    'status'         => 'success',
                                    'original_size'  => 200000,
                                    'converted_size' => 50000,
                                    'saved_bytes'    => 150000,
                                ]
                            ],
                            'saved_bytes' => 150000,
                            'status'      => 'completed',
                        ]
                    ],
                    'processed_at'         => time() - 3600,
                ]
            ];
        }

        // Configure mock wpdb to return postmeta rows
        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public function prepare($query, $a1 = null, $a2 = null, $a3 = null) { return $query; }
            public function get_results($query) {
                global $mock_post_meta;
                $rows = [];
                foreach ($mock_post_meta as $pId => $metaArr) {
                    foreach ($metaArr as $k => $v) {
                        $rows[] = (object) [
                            'post_id'    => $pId,
                            'meta_key'   => $k,
                            'meta_value' => serialize($v),
                        ];
                    }
                }
                return $rows;
            }
            public function get_var($query) {
                global $mock_post_meta;
                return count($mock_post_meta);
            }
            public function query($query) { return 1; }
        };

        // Initially nextgen_savings_stats has 0
        $mock_options[StatsManager::OPTION_KEY] = StatsManager::getDefaultStats();

        // Calling getStats(true) must lazily trigger reconciliation
        $stats = StatsManager::getStats(true);

        $this->assertEquals(3, $stats['total_originals_processed']);
        $this->assertEquals(3, $stats['total_avif_generated']);
        $this->assertEquals(0, $stats['total_webp_generated']);
        $this->assertEquals(600000, $stats['total_original_bytes']);
        $this->assertEquals(150000, $stats['total_avif_bytes']);
        $this->assertEquals(450000, $stats['total_bytes_saved']);
        $this->assertEquals(75.0, $stats['percentage_saved']);

        // Verify option was persisted in database
        $savedOption = get_option(StatsManager::OPTION_KEY);
        $this->assertEquals(450000, $savedOption['total_bytes_saved']);
        $this->assertEquals(3, $savedOption['total_originals_processed']);
    }

    public function testReportsViewAutoReconcilesAndRendersCorrectly(): void {
        global $wpdb, $mock_options, $mock_post_meta;

        $mock_post_meta[501] = [
            MetadataManager::META_KEY => [
                'status'               => 'completed',
                'attachment_id'        => 501,
                'total_original_bytes' => 100000,
                'total_saved_bytes'    => 80000,
                'formats'              => [
                    'avif' => [
                        'sizes'       => ['full' => ['converted_size' => 20000]],
                        'saved_bytes' => 80000,
                        'status'      => 'completed',
                    ]
                ],
            ]
        ];

        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public function prepare($query, $a1 = null, $a2 = null, $a3 = null) { return $query; }
            public function get_results($query) {
                global $mock_post_meta;
                $rows = [];
                foreach ($mock_post_meta as $pId => $metaArr) {
                    foreach ($metaArr as $k => $v) {
                        $rows[] = (object) [
                            'post_id'    => $pId,
                            'meta_key'   => $k,
                            'meta_value' => serialize($v),
                        ];
                    }
                }
                return $rows;
            }
            public function get_var($query) { return 1; }
            public function query($query) { return 1; }
        };

        $mock_options[StatsManager::OPTION_KEY] = StatsManager::getDefaultStats();

        add_filter('nextgen_pro_is_active', '__return_true');
        ob_start();
        ReportsView::render();
        $html = ob_get_clean();

        $this->assertStringContainsString('Media Library Compression Summary', $html);
        $this->assertStringContainsString('Optimized Originals', $html);
        $this->assertStringContainsString('Total Storage Saved', $html);
        $this->assertStringContainsString('78.13 KB', $html);
    }

    public function testToolsReconcileStatsAction(): void {
        global $wpdb, $mock_options, $mock_post_meta;

        $mock_post_meta[777] = [
            StatsManager::META_KEY => [
                'original_size' => 50000,
                'webp'          => [
                    'generated' => true,
                    'size'      => 15000,
                    'saved'     => 35000,
                    'timestamp' => time(),
                ]
            ]
        ];

        $wpdb = new class {
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public function prepare($query, $a1 = null, $a2 = null, $a3 = null) { return $query; }
            public function get_results($query) {
                global $mock_post_meta;
                $rows = [];
                foreach ($mock_post_meta as $pId => $metaArr) {
                    foreach ($metaArr as $k => $v) {
                        $rows[] = (object) [
                            'post_id'    => $pId,
                            'meta_key'   => $k,
                            'meta_value' => serialize($v),
                        ];
                    }
                }
                return $rows;
            }
            public function get_var($query) { return 1; }
            public function query($query) { return 1; }
        };

        $config = new Config();
        $detector = new SystemDetector();
        $settings = new SettingsPage($config, $detector);
        $controller = new AdminController($config, $detector, $settings);

        $_POST['nextgen_tool_nonce'] = wp_create_nonce('nextgen_tool_reconcile_stats');
        $_REQUEST['nextgen_tool_nonce'] = $_POST['nextgen_tool_nonce'];

        $controller->handleToolReconcileStats();

        $stats = StatsManager::getStats(false);
        $this->assertEquals(1, $stats['total_originals_processed']);
        $this->assertEquals(1, $stats['total_webp_generated']);
        $this->assertEquals(35000, $stats['total_bytes_saved']);
    }
}
<?php
/**
 * ComparisonSliderView Unit Tests.
 *
 * @package NextGen\Tests\Unit\V12
 */

namespace NextGen\Tests\Unit\V12;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\ComparisonSliderView;

class ComparisonSliderViewTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter_registry;
        $wp_filter_registry = [];
        $GLOBALS['mock_options'] = [];
    }

    protected function tearDown(): void {
        parent::tearDown();
        global $wp_filter_registry;
        $wp_filter_registry = [];
        $GLOBALS['mock_options'] = [];
    }

    public function testRenderHtmlOutput(): void {
        $html = ComparisonSliderView::render();
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('nextgen-comparison-card', $html);
        $this->assertStringContainsString('nextgen-split-container', $html);
        $this->assertStringContainsString('nextgen-split-handle', $html);
        $this->assertStringContainsString('PRO', $html);
    }

    public function testRenderPopulatesSampleAttachments(): void {
        global $mock_posts;
        $mock_posts = [
            501 => (object) [
                'ID' => 501,
                'post_title' => 'Sample Hero Image',
                'guid' => 'https://example.com/wp-content/uploads/hero.jpg',
                'file' => '/tmp/hero.jpg',
            ],
            502 => (object) [
                'ID' => 502,
                'post_title' => 'Product Shot',
                'guid' => 'https://example.com/wp-content/uploads/product.png',
                'file' => '/tmp/product.png',
            ],
        ];

        $html = ComparisonSliderView::render(501);
        $this->assertStringContainsString('value="501"', $html);
        $this->assertStringContainsString('Sample Hero Image', $html);
        $this->assertStringContainsString('value="502"', $html);
        $this->assertStringContainsString('Product Shot', $html);
        $this->assertStringContainsString('selected', $html);
    }

    public function testVisualizerSampleDiscoveryAcrossMetadataStates(): void {
        global $mock_posts, $mock_post_meta;

        // Scenario 1: Attachment with full conversion metadata
        $mock_posts[601] = (object) [
            'ID' => 601,
            'post_title' => 'Image With Meta',
            'post_mime_type' => 'image/jpeg',
            'guid' => 'https://example.com/uploads/with_meta.jpg',
            'file' => '/tmp/with_meta.jpg',
        ];
        $mock_post_meta[601] = [
            '_nextgen_conversion_data' => ['webp' => ['status' => 'completed']],
        ];

        // Scenario 2: Attachment without conversion metadata (unconverted)
        $mock_posts[602] = (object) [
            'ID' => 602,
            'post_title' => 'Image Without Meta',
            'post_mime_type' => 'image/png',
            'guid' => 'https://example.com/uploads/without_meta.png',
            'file' => '/tmp/without_meta.png',
        ];

        // Scenario 3: Attachment after metadata reset
        $mock_posts[603] = (object) [
            'ID' => 603,
            'post_title' => 'Post Reset Image',
            'post_mime_type' => 'image/jpeg',
            'guid' => 'https://example.com/uploads/post_reset.jpg',
            'file' => '/tmp/post_reset.jpg',
        ];
        // Simulate reset: postmeta explicitly empty
        unset($mock_post_meta[603]);

        // Scenario 4: Attachment with no conversion derivative file on disk
        $mock_posts[604] = (object) [
            'ID' => 604,
            'post_title' => 'Fresh Upload No Derivatives',
            'post_mime_type' => 'image/gif',
            'guid' => 'https://example.com/uploads/fresh.gif',
            'file' => '/tmp/fresh.gif',
        ];

        // Scenario 5: Valid JPEG image attachment
        $mock_posts[605] = (object) [
            'ID' => 605,
            'post_title' => 'Valid JPEG',
            'post_mime_type' => 'image/jpeg',
            'guid' => 'https://example.com/uploads/valid.jpeg',
            'file' => '/tmp/valid.jpeg',
        ];

        // Scenario 6: Unsupported attachments (PDF, Audio) -> must be excluded
        $mock_posts[606] = (object) [
            'ID' => 606,
            'post_title' => 'Invoice Document PDF',
            'post_mime_type' => 'application/pdf',
            'guid' => 'https://example.com/uploads/doc.pdf',
            'file' => '/tmp/doc.pdf',
        ];
        $mock_posts[607] = (object) [
            'ID' => 607,
            'post_title' => 'Podcast Audio Track',
            'post_mime_type' => 'audio/mpeg',
            'guid' => 'https://example.com/uploads/audio.mp3',
            'file' => '/tmp/audio.mp3',
        ];

        $html = ComparisonSliderView::render();

        // 1. With meta: present
        $this->assertStringContainsString('value="601"', $html);
        $this->assertStringContainsString('Image With Meta', $html);

        // 2. Without meta: present
        $this->assertStringContainsString('value="602"', $html);
        $this->assertStringContainsString('Image Without Meta', $html);

        // 3. Post-reset: present (proves metadata reset does NOT break visualizer discovery)
        $this->assertStringContainsString('value="603"', $html);
        $this->assertStringContainsString('Post Reset Image', $html);

        // 4. No derivatives: present
        $this->assertStringContainsString('value="604"', $html);
        $this->assertStringContainsString('Fresh Upload No Derivatives', $html);

        // 5. Valid JPEG: present
        $this->assertStringContainsString('value="605"', $html);
        $this->assertStringContainsString('Valid JPEG', $html);

        // 6. Unsupported PDF and MP3: excluded
        $this->assertStringNotContainsString('value="606"', $html);
        $this->assertStringNotContainsString('Invoice Document PDF', $html);
        $this->assertStringNotContainsString('value="607"', $html);
        $this->assertStringNotContainsString('Podcast Audio Track', $html);
    }
}

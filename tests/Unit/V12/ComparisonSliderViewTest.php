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
}

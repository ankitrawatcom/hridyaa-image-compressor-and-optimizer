<?php
declare(strict_types=1);

namespace NextGen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NextGen\Core\Features;
use NextGen\Core\Config;
use NextGen\Admin\AdminHeaderView;
use NextGen\Admin\AdminDashboardView;
use NextGen\Admin\SettingsPage;
use NextGen\Admin\DiagnosticsView;
use NextGen\Admin\HelpView;
use NextGen\Admin\ReportsView;
use NextGen\Admin\ToolsView;
use NextGen\Admin\AdminController;
use NextGen\Admin\StatsManager;
use NextGen\Support\SystemDetector;

class ProUpsellSuppressionTest extends TestCase {

    private array $savedFilters = [];

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter_registry, $mock_registered_submenus;
        $this->savedFilters = $wp_filter_registry;
        $mock_registered_submenus = [];
    }

    protected function tearDown(): void {
        global $wp_filter_registry, $mock_registered_submenus;
        $wp_filter_registry = $this->savedFilters;
        $mock_registered_submenus = [];
        parent::tearDown();
    }

    private function enableProActive(): void {
        add_filter('nextgen_enable_avif', '__return_true');
    }

    private function disablePro(): void {
        add_filter('nextgen_enable_avif', '__return_false');
    }

    public function testFeaturesIsProActiveReflectsFilter(): void {
        $this->disablePro();
        $this->assertFalse(Features::isProActive());
        $this->assertFalse(Features::isAvifEnabled());

        $this->enableProActive();
        $this->assertTrue(Features::isProActive());
        $this->assertTrue(Features::isAvifEnabled());
    }

    public function testAdminHeaderViewShowsUpsellInFreeTier(): void {
        $this->disablePro();
        ob_start();
        AdminHeaderView::render('Test Title', 'Test Subtitle');
        $output = ob_get_clean();

        $this->assertStringContainsString('Free Edition', $output);
        $this->assertStringContainsString('Upgrade to Pro', $output);
        $this->assertStringContainsString(AdminHeaderView::PRO_URL, $output);
        $this->assertStringNotContainsString('Pro Active', $output);
    }

    public function testAdminHeaderViewSuppressesUpsellWhenProActive(): void {
        $this->enableProActive();
        ob_start();
        AdminHeaderView::render('Test Title', 'Test Subtitle');
        $output = ob_get_clean();

        $this->assertStringContainsString('Pro Active', $output);
        $this->assertStringNotContainsString('Free Edition', $output);
        $this->assertStringNotContainsString('Upgrade to Pro', $output);
        $this->assertStringNotContainsString(AdminHeaderView::PRO_URL, $output);
    }

    public function testAdminDashboardViewShowsUpsellAndComparisonInFreeTier(): void {
        $this->disablePro();
        $detector = new SystemDetector();

        ob_start();
        AdminDashboardView::render($detector);
        $output = ob_get_clean();

        $this->assertStringContainsString('Free vs Pro Edition Comparison', $output);
        $this->assertStringContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringContainsString('Upgrade to Pro Now ★', $output);
        $this->assertStringContainsString('Make Your Site Even Faster', $output);
    }

    public function testAdminDashboardViewSuppressesUpsellAndComparisonWhenProActive(): void {
        $this->enableProActive();
        $detector = new SystemDetector();

        ob_start();
        AdminDashboardView::render($detector);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('Free vs Pro Edition Comparison', $output);
        $this->assertStringNotContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringNotContainsString('Upgrade to Pro Now ★', $output);
        $this->assertStringNotContainsString('Make Your Site Even Faster', $output);
    }

    public function testSettingsPageShowsUpsellInFreeTier(): void {
        $this->disablePro();
        $config = new Config();
        $detector = new SystemDetector();
        $settingsPage = new SettingsPage($config, $detector);

        ob_start();
        $settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringContainsString('Upgrade to Pro ★', $output);
        $this->assertStringContainsString('Activate Pro License', $output);
    }

    public function testSettingsPageSuppressesUpsellWhenProActive(): void {
        $this->enableProActive();
        $config = new Config();
        $detector = new SystemDetector();
        $settingsPage = new SettingsPage($config, $detector);

        ob_start();
        $settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringNotContainsString('Upgrade to Pro ★', $output);
        $this->assertStringNotContainsString('Activate Pro License', $output);
    }

    public function testDiagnosticsViewShowsUpsellInFreeTier(): void {
        $this->disablePro();
        $detector = new SystemDetector();

        ob_start();
        DiagnosticsView::render($detector);
        $output = ob_get_clean();

        $this->assertStringContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringContainsString('Unlock AVIF Codec in Pro', $output);
    }

    public function testDiagnosticsViewSuppressesUpsellWhenProActive(): void {
        $this->enableProActive();
        $detector = new SystemDetector();

        ob_start();
        DiagnosticsView::render($detector);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringNotContainsString('Unlock AVIF Codec in Pro', $output);
    }

    public function testHelpViewShowsUpsellInFreeTier(): void {
        $this->disablePro();

        ob_start();
        HelpView::render();
        $output = ob_get_clean();

        $this->assertStringContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringContainsString('Make Your Images Even Smaller', $output);
    }

    public function testHelpViewSuppressesUpsellWhenProActive(): void {
        $this->enableProActive();

        ob_start();
        HelpView::render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringNotContainsString('Make Your Images Even Smaller', $output);
    }

    public function testReportsViewShowsUpsellInFreeTier(): void {
        $this->disablePro();

        ob_start();
        ReportsView::render();
        $output = ob_get_clean();

        $this->assertStringContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringContainsString('NextGen Image Optimizer Pro', $output);
        $this->assertStringContainsString('Upgrade to Pro Now', $output);
        $this->assertStringContainsString('Unlock with Pro', $output);
        $this->assertStringContainsString('Available in Pro', $output);
    }

    public function testReportsViewSuppressesUpsellWhenProActive(): void {
        $this->enableProActive();

        ob_start();
        ReportsView::render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringNotContainsString('NextGen Image Optimizer Pro', $output);
        $this->assertStringNotContainsString('Upgrade to Pro Now', $output);
        $this->assertStringNotContainsString('Unlock with Pro', $output);
        $this->assertStringNotContainsString('Available in Pro', $output);
    }

    public function testReportsViewAvifRowWithZeroDataWhenProActive(): void {
        $this->enableProActive();
        // Ensure stats have 0 AVIF
        update_option(StatsManager::OPTION_KEY, [
            'total_avif_generated' => 0,
            'total_avif_bytes' => 0,
        ]);

        ob_start();
        ReportsView::render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('Unlock with Pro', $output);
        $this->assertStringNotContainsString('Available in Pro', $output);
        $this->assertStringContainsString('0 B (0 derivatives)', $output);
        $this->assertStringContainsString('0 B (0%)', $output);
    }

    public function testReportsViewAvifRowWithDataWhenProActive(): void {
        $this->enableProActive();
        update_option(StatsManager::OPTION_KEY, [
            'total_original_bytes' => 1000000,
            'total_avif_generated' => 25,
            'total_avif_bytes' => 400000,
        ]);

        ob_start();
        ReportsView::render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('Unlock with Pro', $output);
        $this->assertStringNotContainsString('Available in Pro', $output);
        $this->assertStringContainsString('25 derivatives', $output);
    }

    public function testToolsViewShowsUpsellInFreeTier(): void {
        $this->disablePro();

        ob_start();
        ToolsView::render();
        $output = ob_get_clean();

        $this->assertStringContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringContainsString('Need AVIF Support?', $output);
        $this->assertStringContainsString('Explore Pro Edition', $output);
    }

    public function testToolsViewSuppressesUpsellWhenProActive(): void {
        $this->enableProActive();

        ob_start();
        ToolsView::render();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('nextgen-card-pro-upsell', $output);
        $this->assertStringNotContainsString('Need AVIF Support?', $output);
        $this->assertStringNotContainsString('Explore Pro Edition', $output);
    }

    public function testAdminControllerSubmenuRegistrationSuppression(): void {
        global $mock_registered_submenus;
        $detector = new SystemDetector();
        $config = new Config();
        $settingsPage = new SettingsPage($config, $detector);

        // 1. In Free tier: should register "Upgrade to Pro ★" submenu
        $this->disablePro();
        $mock_registered_submenus = [];
        $controller = new AdminController($config, $detector, $settingsPage);
        $controller->registerAdminMenu();

        $proSubmenuFound = false;
        foreach ($mock_registered_submenus as $sub) {
            if (strpos($sub['menu_title'], 'Upgrade to Pro ★') !== false) {
                $proSubmenuFound = true;
                break;
            }
        }
        $this->assertTrue($proSubmenuFound, 'Free tier must register Upgrade to Pro submenu item');

        // 2. When Pro is Active: should NOT register "Upgrade to Pro ★" submenu
        $this->enableProActive();
        $mock_registered_submenus = [];
        $controllerPro = new AdminController($config, $detector, $settingsPage);
        $controllerPro->registerAdminMenu();

        $proSubmenuFoundPro = false;
        foreach ($mock_registered_submenus as $sub) {
            if (strpos($sub['menu_title'], 'Upgrade to Pro ★') !== false) {
                $proSubmenuFoundPro = true;
                break;
            }
        }
        $this->assertFalse($proSubmenuFoundPro, 'Pro Active tier must suppress Upgrade to Pro submenu item');
    }

    public function testPromotionalUIInstantReactivationOnProExpiration(): void {
        // Step 1: Active Pro suppresses UI
        $this->enableProActive();
        $this->assertTrue(Features::isProActive());
        ob_start();
        AdminHeaderView::render('Title');
        $output = ob_get_clean();
        $this->assertStringContainsString('Pro Active', $output);
        $this->assertStringNotContainsString('Upgrade to Pro', $output);

        // Step 2: License expires/revoked -> immediately returns to promotional UI
        $this->disablePro();
        $this->assertFalse(Features::isProActive());
        ob_start();
        AdminHeaderView::render('Title');
        $output = ob_get_clean();
        $this->assertStringContainsString('Free Edition', $output);
        $this->assertStringContainsString('Upgrade to Pro', $output);
    }
}
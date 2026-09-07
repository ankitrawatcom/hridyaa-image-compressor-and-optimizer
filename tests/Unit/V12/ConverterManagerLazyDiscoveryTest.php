<?php
/**
 * ConverterManager Lazy Discovery & Lifecycle Timing Regression Test.
 *
 * @package NextGen\Tests\Unit\V12
 */

namespace NextGen\Tests\Unit\V12;

use PHPUnit\Framework\TestCase;
use NextGen\Core\Config;
use NextGen\Core\Features;
use NextGen\Converter\ConverterManager;
use NextGen\Converter\ConverterInterface;
use NextGen\Converter\ConversionResult;
use NextGen\Image\AttachmentHandler;
use NextGen\Storage\MetadataManager;

class ConverterManagerLazyDiscoveryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        global $wp_filter_registry, $mock_options, $mock_post_meta;
        $wp_filter_registry = [];
        $mock_options = [];
        $mock_post_meta = [];
    }

    public function testLazyConverterRegistrationWhenProHooksRegisterAfterConstruction(): void {
        $config = new Config();
        
        // 1. Instantiate ConverterManager BEFORE any third-party/Pro hooks exist
        $manager = new ConverterManager($config);

        // At this point, AVIF format is not supported
        $this->assertFalse($manager->isFormatSupported('avif'));

        // 2. Later during bootstrap, a Pro plugin attaches to 'nextgen_register_converters'
        $mockAvifEngine = new class implements ConverterInterface {
            public function isSupported(): bool { return true; }
            public function supportsFormat(string $format): bool { return $format === 'avif'; }
            public function getEngineName(): string { return 'mock_lazy_avif'; }
            public function convert(string $sourcePath, ?string $outputPath = null, array $options = [], string $format = 'webp'): ConversionResult {
                return ConversionResult::success($sourcePath, $outputPath ?: $sourcePath . '.avif', 1000, 300, 70.0, 'mock_lazy_avif');
            }
        };

        add_action('nextgen_register_converters', function ($mgr) use ($mockAvifEngine) {
            $mgr->registerEngine($mockAvifEngine);
        });

        // 3. Manager must lazily discover and register the new engine on subsequent query
        $this->assertTrue($manager->isFormatSupported('avif'));
        $engine = $manager->getActiveEngine('avif');
        $this->assertNotNull($engine);
        $this->assertEquals('mock_lazy_avif', $engine->getEngineName());
    }
}
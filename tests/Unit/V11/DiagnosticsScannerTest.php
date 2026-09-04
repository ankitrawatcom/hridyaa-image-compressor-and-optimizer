<?php
/**
 * DiagnosticsScanner Unit Test.
 *
 * @package NextGen\Tests\Unit\V11
 */

namespace NextGen\Tests\Unit\V11;

use PHPUnit\Framework\TestCase;
use NextGen\Admin\DiagnosticsScanner;

class DiagnosticsScannerTest extends TestCase {

    public function testRunFullAuditReturnsExpectedKeys(): void {
        $audit = DiagnosticsScanner::runFullAudit();

        $this->assertArrayHasKey('php_version', $audit);
        $this->assertArrayHasKey('memory_limit', $audit);
        $this->assertArrayHasKey('execution_time', $audit);
        $this->assertArrayHasKey('gd_driver', $audit);
        $this->assertArrayHasKey('imagick_driver', $audit);
        $this->assertArrayHasKey('uploads_writable', $audit);
        $this->assertArrayHasKey('wordpress_version', $audit);
    }

    public function testStatusClassifications(): void {
        $audit = DiagnosticsScanner::runFullAudit();

        $validStatuses = [
            DiagnosticsScanner::PASS,
            DiagnosticsScanner::WARNING,
            DiagnosticsScanner::FAIL,
            DiagnosticsScanner::UNKNOWN,
        ];

        foreach ($audit as $key => $check) {
            $this->assertContains($check['status'], $validStatuses, "Check {$key} must have valid classification");
            $this->assertNotEmpty($check['label']);
            $this->assertNotEmpty($check['message']);
        }
    }

    public function testGetFormattedMarkdownReport(): void {
        $report = DiagnosticsScanner::getFormattedMarkdownReport();

        $this->assertMatchesRegularExpression('/(Hridyaa Image Compressor|Image Optimizer|NextGen) System & (Codec|AVIF) Capability Report/', $report);
        $this->assertStringContainsString('PHP Version', $report);
        $this->assertStringContainsString('Generated locally', $report);

        // Security check: no exposed secret keywords
        $this->assertStringNotContainsString('password', strtolower($report));
        $this->assertStringNotContainsString('private key', strtolower($report));
    }
}

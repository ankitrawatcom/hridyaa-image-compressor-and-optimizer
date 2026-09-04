<?php
/**
 * Filename Helper Unit Tests.
 */

namespace NextGen\Tests\Unit;

use NextGen\Image\FilenameHelper;
use PHPUnit\Framework\TestCase;

class FilenameHelperTest extends TestCase {

    public function testStandardFilename(): void {
        $source = '/var/www/uploads/2026/08/hero.jpg';
        $expected = '/var/www/uploads/2026/08/hero.jpg.webp';
        $this->assertEquals($expected, FilenameHelper::generateWebpPath($source));
    }

    public function testUppercaseExtension(): void {
        $source = 'C:/uploads/BANNER.JPEG';
        $expected = 'C:/uploads/BANNER.JPEG.webp';
        $this->assertEquals($expected, FilenameHelper::generateWebpPath($source));
    }

    public function testMultipleDotsInFilename(): void {
        $source = '/uploads/photo.v1.2.backup.png';
        $expected = '/uploads/photo.v1.2.backup.png.webp';
        $this->assertEquals($expected, FilenameHelper::generateWebpPath($source));
    }

    public function testUnicodeCharactersInFilename(): void {
        $source = '/uploads/café-über-ñoño.jpg';
        $expected = '/uploads/café-über-ñoño.jpg.webp';
        $this->assertEquals($expected, FilenameHelper::generateWebpPath($source));
    }

    public function testSpacesAndSpecialSymbols(): void {
        $source = '/uploads/my photo (1) & [archive].png';
        $expected = '/uploads/my photo (1) & [archive].png.webp';
        $this->assertEquals($expected, FilenameHelper::generateWebpPath($source));
    }

    public function testFormatStringInMiddleOfName(): void {
        $source = '/uploads/jpg_parser_jpeg_dump_png.jpg';
        $expected = '/uploads/jpg_parser_jpeg_dump_png.jpg.webp';
        $this->assertEquals($expected, FilenameHelper::generateWebpPath($source));
    }

    public function testDoesNotDoubleAppendWebp(): void {
        $source = '/uploads/already.webp';
        $this->assertEquals('/uploads/already.webp', FilenameHelper::generateWebpPath($source));
    }

    public function testIsNextGenWebpDerivative(): void {
        $this->assertTrue(FilenameHelper::isNextGenWebpDerivative('/uploads/photo.jpg.webp'));
        $this->assertTrue(FilenameHelper::isNextGenWebpDerivative('/uploads/photo.jpeg.webp'));
        $this->assertTrue(FilenameHelper::isNextGenWebpDerivative('/uploads/photo.png.webp'));
        $this->assertTrue(FilenameHelper::isNextGenWebpDerivative('/uploads/photo.gif.webp'));
        $this->assertFalse(FilenameHelper::isNextGenWebpDerivative('/uploads/photo.webp'));
        $this->assertFalse(FilenameHelper::isNextGenWebpDerivative('/uploads/photo.jpg'));
    }

    public function testGetSourcePathFromWebp(): void {
        $webp = '/uploads/photo.jpg.webp';
        $this->assertEquals('/uploads/photo.jpg', FilenameHelper::getSourcePathFromWebp($webp));

        $unrelated = '/uploads/photo.webp';
        $this->assertEquals('/uploads/photo.webp', FilenameHelper::getSourcePathFromWebp($unrelated));
    }

    public function testGenerateWebpUrlPreservesQueryAndFragment(): void {
        $url = 'https://example.com/wp-content/uploads/hero.jpg?v=1.2.3#header';
        $expected = 'https://example.com/wp-content/uploads/hero.jpg.webp?v=1.2.3#header';
        $this->assertEquals($expected, FilenameHelper::generateWebpUrl($url));

        $protocolRelative = '//example.com/uploads/photo.png?ver=10';
        $expectedRelative = '//example.com/uploads/photo.png.webp?ver=10';
        $this->assertEquals($expectedRelative, FilenameHelper::generateWebpUrl($protocolRelative));
    }
}

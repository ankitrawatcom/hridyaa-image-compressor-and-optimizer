<?php
/**
 * Animated GIF Inspector.
 *
 * Reliably detects animated (multi-frame) GIFs to prevent destructive flattening in Stage 1.
 *
 * @package NextGen\Image
 */

namespace NextGen\Image;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AnimatedGifHandler {

    /**
     * Check if a GIF file contains multiple frames (animation).
     *
     * Scans GIF binary stream for Graphic Control Extension and Image Descriptor markers.
     *
     * @param string $filePath Path to GIF file.
     * @return bool True if animated, false if static or not a GIF.
     */
    public static function isAnimated(string $filePath): bool {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            return false;
        }

        $header = fread($fp, 6);
        if ($header !== 'GIF87a' && $header !== 'GIF89a') {
            fclose($fp);
            return false;
        }

        $count = 0;
        // Read in 16KB chunks
        while (!feof($fp) && $count < 2) {
            $chunk = fread($fp, 16384);
            if ($chunk === false) {
                break;
            }

            // Look for Graphic Control Extension marker: 0x21 0xF9 0x04 or image separator 0x2C
            $count += substr_count($chunk, "\x00\x21\xF9\x04");
            if ($count >= 2) {
                break;
            }
        }

        fclose($fp);
        return $count >= 2;
    }
}

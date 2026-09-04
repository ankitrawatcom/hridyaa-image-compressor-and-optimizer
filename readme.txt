=== Hridyaa Image Compressor and Optimizer ===
Contributors: ankitrawat
Donate link: https://ankitrawat.com
Tags: webp, avif, image optimizer, image compression, speed, performance, core web vitals
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compress and optimize WordPress images, convert to WebP and AVIF, and reduce image sizes for faster page loads.

== Description ==

**Hridyaa Image Compressor and Optimizer** automatically converts your WordPress images (JPEG, PNG, GIF) and all generated thumbnail sizes into modern, lightweight **WebP** and **AVIF** formats.

By serving modern image formats to your site visitors, you dramatically reduce page weight, accelerate load times, improve Google PageSpeed scores, and boost Core Web Vitals (Largest Contentful Paint - LCP).

All conversion runs directly on your server using PHP GD or ImageMagick. There are zero third-party cloud API dependencies, zero remote image uploads, and zero monthly image processing quotas.

### Key Features (100% Free)

* **100% Local & On-Server:** All image encoding runs directly on your server with PHP GD or ImageMagick. Your images never leave your host.
* **100% Free & Unlimited:** No monthly credit limits, no subscription paywalls for WebP, and no artificial restrictions.
* **Non-Destructive:** Your original images are permanently preserved. The plugin generates separate companion files.
* **Automatic Upload Optimization:** Converts newly uploaded media and all intermediate thumbnail sizes automatically.
* **Interactive Bulk Converter:** Optimize your entire existing Media Library with a live, resumable AJAX progress tool with pause and throttle controls.
* **Headless Background WP-Cron Worker:** Run bulk optimization asynchronously in the background without needing an open browser tab.
* **HTML5 Picture Tag Delivery:** Seamlessly delivers modern image formats with native browser fallback for legacy clients.
* **Negative Compression Guard:** Discards derivatives if they are larger than the original to prevent wasting disk space.
* **Compression Presets:** Select between High Quality, Balanced (Recommended), and Aggressive compression profiles.
* **Visual Quality Split-View Slider:** Preview compression quality on your own media files before applying settings site-wide.
* **System & Codec Diagnostics:** Built-in server diagnostics report to verify GD, ImageMagick, and memory configuration.
* **Failed Conversion Queue:** Automatic failure tracking, error categorization, and single-click retry engine for resilient processing.

### Privacy & Data Safety

Hridyaa Image Compressor and Optimizer processes all media entirely within your WordPress installation. It does not communicate with external servers, does not collect telemetry, and does not transmit any user or media data off-site.

== More Information ==

Official product page:
https://ankitrawat.com/products/hridyaa-image-compressor-and-optimizer/

== Installation ==

1. Upload the `hridyaa-image-compressor-and-optimizer` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Image Compressor -> Dashboard** to review system diagnostics and configure your preferences.
4. (Optional) Run the Bulk Converter to optimize existing images in your Media Library.

== Frequently Asked Questions ==

= Does this plugin overwrite or delete my original images? =
No. Your original JPEG, PNG, and GIF images are strictly preserved. The plugin creates separate companion `.webp` files alongside your originals.

= What image libraries are required on the server? =
The plugin requires either the PHP GD extension (`ext-gd`) with WebP/AVIF support or the ImageMagick extension (`ext-imagick`). The plugin automatically detects and selects the best available engine on your server.

= How does frontend delivery work? =
When enabled, the plugin rewrites standard `<img>` tags in your post content and featured images into standard HTML5 `<picture>` tags containing `<source type="image/webp">` alongside the original `<img>` fallback.

= Can I run bulk optimization without keeping my browser open? =
Yes. In addition to the interactive AJAX bulk converter, NextGen includes a headless WP-Cron background worker that processes batches continuously on scheduled ticks.

= What happens if a converted WebP image is larger than the original? =
The plugin includes a Negative Compression Guard. If a converted WebP file ends up larger than or equal to the original image, the derivative is automatically discarded to save disk space.

= Is AVIF format supported? =
The free version includes complete WebP optimization. AVIF format support is available as an optional commercial add-on for servers running PHP 8.1+ with AVIF-enabled GD or ImageMagick.

= What happens when I deactivate or delete the plugin? =
Deactivating the plugin stops WebP conversion and picture tag delivery. Deleting the plugin through the WordPress admin runs `uninstall.php`, which cleans up all options, transients, and conversion metadata while leaving your original media files untouched.

== Screenshots ==

1. Overview & Savings Dashboard showing optimized images, disk storage saved, and compression metrics.
2. Settings & Bulk Media Library Converter with real-time progress and throttling controls.
3. Server & Codec Capability Diagnostics scanner.
4. Interactive Visual Split-View Image Comparison Slider.

== Changelog ==

= 1.2.0 =
* Added headless background WP-Cron batch worker with transient locking and time-budget enforcement.
* Added interactive visual quality comparison slider for side-by-side inspection.
* Added server diagnostics scanner and copyable markdown report.
* Added failed conversion queue with categorized failure logging and retry engine.
* Added cross-codec compression presets (High, Balanced, Aggressive).
* Enhanced strict negative compression protection.

= 1.1.0 =
* Added multi-tier HTML5 picture tag delivery engine.
* Added thumbnail size conversion controls.
* Added animated GIF detection to prevent flattening animations.
* Added O(1) bounded aggregate storage for optimization statistics.

= 1.0.0 =
* Initial release of NextGen Image Optimizer Free WebP Engine.

== Upgrade Notice ==

= 1.2.0 =
Version 1.2.0 adds background WP-Cron optimization, visual comparison slider, system diagnostics, and failed queue recovery. Upgrading is fully backward compatible.

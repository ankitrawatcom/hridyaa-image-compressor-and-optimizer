<?php
/**
 * HTML <picture> Tag Multi-Tier Delivery Engine.
 *
 * Safely rewrites <img> tags to HTML5 <picture> tags with hierarchical AVIF and WebP sources.
 * Hierarchy: AVIF -> WebP -> Original <img>.
 *
 * @package NextGen\Delivery
 */

namespace NextGen\Delivery;

use NextGen\Core\Config;
use NextGen\Image\FilenameHelper;
use NextGen\Image\ImageValidator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PictureTagDelivery implements DeliveryInterface {

    /**
     * Configuration manager.
     *
     * @var Config
     */
    private Config $config;

    /**
     * WordPress uploads directory info.
     *
     * @var array|null
     */
    private ?array $uploadDir = null;

    /**
     * Constructor.
     */
    public function __construct(Config $config) {
        $this->config = $config;
    }

    /**
     * Register WordPress delivery hooks.
     *
     * @return void
     */
    public function registerHooks(): void {
        if (!$this->config->get('delivery_enabled', true)) {
            return;
        }

        // Native WordPress 5.5+ image tag filter
        add_filter('wp_content_img_tag', [$this, 'filterImageTag'], 10, 3);

        // Fallback filter for content rendered outside standard wp_content_img_tag
        add_filter('the_content', [$this, 'filterContent'], 99);
        add_filter('post_thumbnail_html', [$this, 'filterPostThumbnail'], 99, 5);
        add_filter('woocommerce_single_product_image_thumbnail_html', [$this, 'filterWooCommerceThumbnail'], 99, 2);
    }

    /**
     * Filter a single <img> HTML tag via native wp_content_img_tag filter.
     *
     * @param string $html Original <img> tag HTML.
     * @param string $context Context (e.g. 'the_content').
     * @param int $attachmentId Attachment ID (0 if unknown).
     * @return string Modified <picture> or original <img> tag.
     */
    public function filterImageTag(string $html, string $context = '', int $attachmentId = 0): string {
        if (empty($html) || $this->shouldSkipDelivery()) {
            return $html;
        }

        return $this->rewriteImgTagToPicture($html);
    }

    /**
     * Filter post content.
     *
     * @param string $content Post HTML content.
     * @return string
     */
    public function filterContent(string $content): string {
        if (empty($content) || $this->shouldSkipDelivery()) {
            return $content;
        }

        // Avoid rewriting images already wrapped in <picture>
        return preg_replace_callback(
            '/(?:<picture[^>]*>.*?<\/picture>)|(<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>)/is',
            function ($matches) {
                // If matched a <picture> block, return as is
                if (empty($matches[1])) {
                    return $matches[0];
                }
                return $this->rewriteImgTagToPicture($matches[1]);
            },
            $content
        );
    }

    /**
     * Filter post thumbnail HTML.
     *
     * @param string|mixed $html Post thumbnail HTML.
     * @param int|mixed $post_id Post ID.
     * @param int|mixed $post_thumbnail_id Thumbnail ID.
     * @param string|array|mixed $size Size.
     * @param string|array|mixed $attr Attributes.
     * @return string|mixed
     */
    public function filterPostThumbnail($html, $post_id = null, $post_thumbnail_id = null, $size = null, $attr = null) {
        if (!is_string($html) || empty($html) || $this->shouldSkipDelivery()) {
            return $html;
        }
        return $this->filterContent($html);
    }

    /**
     * Filter WooCommerce product thumbnail HTML.
     *
     * @param string|mixed $html Thumbnail HTML.
     * @param int|mixed $post_thumbnail_id Thumbnail ID.
     * @return string|mixed
     */
    public function filterWooCommerceThumbnail($html, $post_thumbnail_id = null) {
        if (!is_string($html) || empty($html) || $this->shouldSkipDelivery()) {
            return $html;
        }
        return $this->filterContent($html);
    }

    /**
     * Rewrite a single <img> tag string to <picture> if AVIF or WebP derivatives exist.
     *
     * @param string $imgTag HTML <img> tag.
     * @return string
     */
    public function rewriteImgTagToPicture(string $imgTag): string {
        // Extract src attribute
        if (!preg_match('/\ssrc=["\']([^"\']+)["\']/i', $imgTag, $srcMatches)) {
            return $imgTag;
        }

        $srcUrl = $srcMatches[1];

        // Check if clean URL path (without query string) is a supported format
        $cleanPath = strtok($srcUrl, '?#');
        if (!preg_match('/\.(jpe?g|png|gif)$/i', $cleanPath)) {
            return $imgTag;
        }

        // Check if image exists on local filesystem
        $localPath = $this->urlToLocalPath($srcUrl);
        if (!$localPath) {
            return $imgTag;
        }

        $avifPath = FilenameHelper::generateAvifPath($localPath);
        $webpPath = FilenameHelper::generateWebpPath($localPath);

        $hasAvif = file_exists($avifPath) && filesize($avifPath) > 0;
        $hasWebp = file_exists($webpPath) && filesize($webpPath) > 0;

        // If neither modern format exists, leave original <img> untouched
        if (!$hasAvif && !$hasWebp) {
            return $imgTag;
        }

        // Extract original srcset and sizes attributes if present
        $originalSrcset = '';
        if (preg_match('/\ssrcset=["\']([^"\']+)["\']/i', $imgTag, $srcsetMatches)) {
            $originalSrcset = $srcsetMatches[1];
        }

        $sizesAttr = '';
        if (preg_match('/\ssizes=["\']([^"\']+)["\']/i', $imgTag, $sizesMatches)) {
            $sizesAttr = ' sizes="' . esc_attr($sizesMatches[1]) . '"';
        }

        $sourceTags = [];

        // 1. Top Tier: AVIF Source
        if ($hasAvif) {
            $avifSrcset = '';
            if (!empty($originalSrcset)) {
                $avifSrcset = $this->convertSrcsetToFormat($originalSrcset, 'avif');
            }
            if (empty($avifSrcset)) {
                $avifSrcset = esc_url(FilenameHelper::generateAvifUrl($srcUrl));
            }
            $sourceTags[] = sprintf('<source type="image/avif" srcset="%s"%s>', esc_attr($avifSrcset), $sizesAttr);
        }

        // 2. Second Tier: WebP Source
        if ($hasWebp) {
            $webpSrcset = '';
            if (!empty($originalSrcset)) {
                $webpSrcset = $this->convertSrcsetToFormat($originalSrcset, 'webp');
            }
            if (empty($webpSrcset)) {
                $webpSrcset = esc_url(FilenameHelper::generateWebpUrl($srcUrl));
            }
            $sourceTags[] = sprintf('<source type="image/webp" srcset="%s"%s>', esc_attr($webpSrcset), $sizesAttr);
        }

        return sprintf(
            '<picture class="nextgen-picture">%s%s</picture>',
            implode('', $sourceTags),
            $imgTag
        );
    }

    /**
     * Convert an <img> srcset string to corresponding format candidates.
     *
     * @param string $srcset Original srcset string.
     * @param string $format Target format ('webp' or 'avif').
     * @return string
     */
    private function convertSrcsetToFormat(string $srcset, string $format = 'webp'): string {
        $sources = explode(',', $srcset);
        $convertedSources = [];

        foreach ($sources as $source) {
            $source = trim($source);
            if (empty($source)) {
                continue;
            }

            $parts = preg_split('/\s+/', $source, 2);
            $url = $parts[0] ?? '';
            $descriptor = isset($parts[1]) ? ' ' . $parts[1] : '';

            $cleanPath = strtok($url, '?#');
            if (empty($cleanPath) || !preg_match('/\.(jpe?g|png|gif)$/i', $cleanPath)) {
                continue;
            }

            $localPath = $this->urlToLocalPath($url);
            if ($localPath) {
                $derivativePath = FilenameHelper::generateDerivativePath($localPath, $format);
                if (file_exists($derivativePath) && filesize($derivativePath) > 0) {
                    $derivativeUrl = FilenameHelper::generateDerivativeUrl($url, $format);
                    $convertedSources[] = esc_url($derivativeUrl) . $descriptor;
                }
            }
        }

        return implode(', ', $convertedSources);
    }

    /**
     * Map a WordPress upload URL to absolute local filesystem path.
     * Strips query strings and URL fragments prior to resolution.
     *
     * @param string $url URL of image.
     * @return string|null Local path or null if outside uploads.
     */
    private function urlToLocalPath(string $url): ?string {
        if ($this->uploadDir === null) {
            $this->uploadDir = function_exists('wp_upload_dir') ? wp_upload_dir() : ['baseurl' => '', 'basedir' => ''];
        }

        $baseUrl = $this->uploadDir['baseurl'];
        $baseDir = $this->uploadDir['basedir'];

        if (empty($baseUrl) || empty($baseDir)) {
            return null;
        }

        // Strip query string and fragment for filesystem resolution
        $cleanUrl = strtok($url, '?#');

        // Normalize schemes (http vs https)
        $cleanUrl = preg_replace('#^https?:#', '', $cleanUrl);
        $cleanBaseUrl = preg_replace('#^https?:#', '', $baseUrl);

        if (strpos($cleanUrl, $cleanBaseUrl) !== 0) {
            return null;
        }

        $relativePath = substr($cleanUrl, strlen($cleanBaseUrl));
        if (strpos($relativePath, '..') !== false) {
            return null;
        }

        $localPath = $baseDir . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!ImageValidator::isPathSafe($localPath, $baseDir)) {
            return null;
        }

        return file_exists($localPath) ? $localPath : null;
    }

    /**
     * Check if delivery should be skipped in current request context.
     *
     * @return bool
     */
    private function shouldSkipDelivery(): bool {
        if (function_exists('is_admin') && is_admin()) {
            return true;
        }
        if (function_exists('is_feed') && is_feed()) {
            return true;
        }
        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return true;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        return false;
    }
}

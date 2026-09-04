<?php
/**
 * Delivery Layer Interface.
 *
 * Provides clean boundary abstraction for frontend image delivery.
 *
 * @package NextGen\Delivery
 */

namespace NextGen\Delivery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface DeliveryInterface {

    /**
     * Register WordPress frontend filters.
     *
     * @return void
     */
    public function registerHooks(): void;

    /**
     * Filter a single <img> HTML tag via native wp_content_img_tag.
     *
     * @param string $html Original <img> tag HTML.
     * @param string $context Context (e.g. 'the_content').
     * @param int $attachmentId Attachment ID (0 if unknown).
     * @return string Modified <picture> or untouched <img> tag.
     */
    public function filterImageTag(string $html, string $context = '', int $attachmentId = 0): string;

    /**
     * Filter page post content.
     *
     * @param string $content Post HTML content.
     * @return string
     */
    public function filterContent(string $content): string;
}

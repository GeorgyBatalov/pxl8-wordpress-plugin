<?php
/**
 * URL Rewriter - Replace WordPress URLs with PXL8 URLs
 *
 * @package Pxl8\WordPress\Media
 */

namespace Pxl8\WordPress\Media;

use Pxl8\WordPress\Storage\AttachmentMeta;
use Pxl8\WordPress\Storage\Options;
use Pxl8\WordPress\Diagnostics\Logger;

class UrlRewriter {
    /**
     * @var Options
     */
    private $options;

    /**
     * @var AttachmentMeta
     */
    private $attachmentMeta;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param Options $options
     * @param AttachmentMeta $attachmentMeta
     * @param Logger $logger
     */
    public function __construct(
        Options $options,
        AttachmentMeta $attachmentMeta,
        Logger $logger
    ) {
        $this->options = $options;
        $this->attachmentMeta = $attachmentMeta;
        $this->logger = $logger;
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Register WordPress filters for URL rewriting
        add_filter('wp_get_attachment_url', [$this, 'rewriteAttachmentUrl'], 10, 2);
        add_filter('wp_get_attachment_image_src', [$this, 'rewriteImageSrc'], 10, 4);
        add_filter('wp_calculate_image_srcset', [$this, 'rewriteSrcset'], 10, 5);
    }

    /**
     * Rewrite attachment URL (wp_get_attachment_url filter)
     *
     * @param string $url Original URL
     * @param int $attachmentId
     * @return string PXL8 URL or original URL (if not applicable)
     */
    public function rewriteAttachmentUrl($url, $attachmentId) {
        // 1. Check if plugin is enabled
        if (!$this->options->isEnabled()) {
            return $url;
        }

        // 2. Check if attachment is an image
        if (!wp_attachment_is_image($attachmentId)) {
            return $url;
        }

        // 3. Check if attachment was successfully processed
        if (!$this->attachmentMeta->isSuccess($attachmentId)) {
            return $url;
        }

        // 4. Get imageId
        $imageId = $this->attachmentMeta->getImageId($attachmentId);
        if (empty($imageId)) {
            return $url;
        }

        // 5. Build PXL8 URL
        $pxl8Url = $this->buildPxl8Url($imageId, null, null);

        $this->logger->info('Rewrite attachment URL', [
            'attachment_id' => $attachmentId,
            'original_url' => $url,
            'pxl8_url' => $pxl8Url,
        ]);

        return $pxl8Url;
    }

    /**
     * Rewrite image src (wp_get_attachment_image_src filter)
     *
     * @param array|false $image Array of image data (url, width, height, is_intermediate)
     * @param int $attachmentId
     * @param string|array $size
     * @param bool $icon
     * @return array|false
     */
    public function rewriteImageSrc($image, $attachmentId, $size, $icon) {
        // Return false if no image data
        if (!$image) {
            return $image;
        }

        // 1. Check if plugin is enabled
        if (!$this->options->isEnabled()) {
            return $image;
        }

        // 2. Check if attachment was successfully processed
        if (!$this->attachmentMeta->isSuccess($attachmentId)) {
            return $image;
        }

        // 3. Get imageId
        $imageId = $this->attachmentMeta->getImageId($attachmentId);
        if (empty($imageId)) {
            return $image;
        }

        // 4. Extract width/height from $image array
        $width = isset($image[1]) ? $image[1] : null;
        $height = isset($image[2]) ? $image[2] : null;

        // 5. Build PXL8 URL with dimensions
        $pxl8Url = $this->buildPxl8Url($imageId, $width, $height);

        $this->logger->info('Rewrite image src', [
            'attachment_id' => $attachmentId,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'pxl8_url' => $pxl8Url,
        ]);

        // 6. Replace URL in array
        $image[0] = $pxl8Url;

        return $image;
    }

    /**
     * Rewrite srcset (wp_calculate_image_srcset filter)
     *
     * @param array $sources Array of srcset sources
     * @param array $size_array Array of width and height values
     * @param string $image_src The src value
     * @param array $image_meta The image metadata
     * @param int $attachmentId
     * @return array
     */
    public function rewriteSrcset($sources, $size_array, $image_src, $image_meta, $attachmentId) {
        // 1. Check if plugin is enabled
        if (!$this->options->isEnabled()) {
            return $sources;
        }

        // 2. Check if attachment was successfully processed
        if (!$this->attachmentMeta->isSuccess($attachmentId)) {
            return $sources;
        }

        // 3. Get imageId
        $imageId = $this->attachmentMeta->getImageId($attachmentId);
        if (empty($imageId)) {
            return $sources;
        }

        // 4. Rewrite each source URL
        foreach ($sources as $width => &$source) {
            $source['url'] = $this->buildPxl8Url($imageId, $width, null);
        }

        $this->logger->info('Rewrite srcset', [
            'attachment_id' => $attachmentId,
            'source_count' => count($sources),
        ]);

        return $sources;
    }

    /**
     * Build PXL8 URL
     *
     * @param string $imageId
     * @param int|null $width
     * @param int|null $height
     * @return string
     */
    private function buildPxl8Url($imageId, $width = null, $height = null) {
        $baseUrl = $this->options->getBaseUrl();
        $url = rtrim($baseUrl, '/') . '/' . $imageId;

        $params = [];

        // Width
        if ($width) {
            $params['w'] = $width;
        }

        // Height
        if ($height) {
            $params['h'] = $height;
        }

        // Fit (default: cover)
        $params['fit'] = $this->options->getDefaultFit();

        // Format (default: auto)
        $params['format'] = $this->options->getDefaultFormat();

        // Quality (default: 85)
        $params['quality'] = $this->options->getDefaultQuality();

        // Build query string
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }
}

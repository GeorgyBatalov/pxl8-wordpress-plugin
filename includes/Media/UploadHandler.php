<?php
/**
 * Upload Handler - Auto-optimize on upload
 *
 * @package Pxl8\WordPress\Media
 */

namespace Pxl8\WordPress\Media;

use Pxl8\WordPress\Sdk\ClientFactory;
use Pxl8\WordPress\Storage\AttachmentMeta;
use Pxl8\WordPress\Storage\Options;
use Pxl8\WordPress\Diagnostics\Logger;

class UploadHandler {
    /**
     * @var Options
     */
    private $options;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

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
     * @param ClientFactory $clientFactory
     * @param AttachmentMeta $attachmentMeta
     * @param Logger $logger
     */
    public function __construct(
        Options $options,
        ClientFactory $clientFactory,
        AttachmentMeta $attachmentMeta,
        Logger $logger
    ) {
        $this->options = $options;
        $this->clientFactory = $clientFactory;
        $this->attachmentMeta = $attachmentMeta;
        $this->logger = $logger;
    }

    /**
     * Initialize hooks
     */
    public function init() {
        add_filter('wp_generate_attachment_metadata', [$this, 'handleUpload'], 10, 2);
    }

    /**
     * Handle upload - called via wp_generate_attachment_metadata hook
     *
     * @param array $metadata
     * @param int $attachmentId
     * @return array (unmodified metadata - don't break WordPress)
     */
    public function handleUpload($metadata, $attachmentId) {
        // 1. Check if auto-optimize is enabled
        if (!$this->options->isAutoOptimizeEnabled()) {
            $this->logger->info('Auto-optimize disabled, skipping upload', [
                'attachment_id' => $attachmentId,
            ]);
            return $metadata;
        }

        // 2. Check if attachment is an image
        if (!wp_attachment_is_image($attachmentId)) {
            $this->logger->info('Attachment is not an image, skipping', [
                'attachment_id' => $attachmentId,
            ]);
            return $metadata;
        }

        // 3. Check if already processed (has _pxl8_image_id)
        if ($this->attachmentMeta->isProcessed($attachmentId)) {
            $this->logger->info('Attachment already processed, skipping', [
                'attachment_id' => $attachmentId,
                'image_id' => $this->attachmentMeta->getImageId($attachmentId),
            ]);
            return $metadata;
        }

        // 4. Get file path
        $filePath = get_attached_file($attachmentId);
        if (!file_exists($filePath)) {
            $this->logger->error('File does not exist', [
                'attachment_id' => $attachmentId,
                'file_path' => $filePath,
            ]);
            return $metadata;
        }

        // 5. Check idempotency (best-effort)
        $newHash = 'sha256:' . hash_file('sha256', $filePath);
        $existingHash = $this->attachmentMeta->getSourceHash($attachmentId);

        if ($existingHash && $existingHash === $newHash) {
            $this->logger->info('File hash matches existing, skipping duplicate upload', [
                'attachment_id' => $attachmentId,
                'hash' => $newHash,
            ]);
            return $metadata;
        }

        // 6. Upload to PXL8
        try {
            $this->logger->info('Starting PXL8 upload', [
                'attachment_id' => $attachmentId,
                'file_path' => $filePath,
                'file_size' => filesize($filePath),
            ]);

            $client = $this->clientFactory->create();
            $response = $client->upload($filePath);

            // 7. Store metadata on success
            $this->attachmentMeta->setImageId($attachmentId, $response['imageId']);
            $this->attachmentMeta->setStatus($attachmentId, 'ok');
            $this->attachmentMeta->setUploadedAt($attachmentId);
            $this->attachmentMeta->setLastSyncAt($attachmentId);
            $this->attachmentMeta->setSourceHash($attachmentId, $newHash);

            $this->logger->info('PXL8 upload succeeded', [
                'attachment_id' => $attachmentId,
                'image_id' => $response['imageId'],
                'size' => $response['size'] ?? null,
                'format' => $response['format'] ?? null,
            ]);

        } catch (\Exception $e) {
            // 8. Handle error (NON-FATAL - don't break WordPress upload)
            $this->attachmentMeta->setStatus($attachmentId, 'failed');
            $this->attachmentMeta->setLastError($attachmentId, $e->getMessage());

            $this->logger->error('PXL8 upload failed', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            // IMPORTANT: Don't throw exception - return metadata unmodified
            // This allows WordPress upload to continue normally
            // Original URL will remain active (URL rewriting skips failed uploads)
        }

        // 9. Always return unmodified metadata (don't break WordPress)
        return $metadata;
    }
}

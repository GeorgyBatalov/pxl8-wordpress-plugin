<?php
/**
 * Attachment Metadata Management
 *
 * @package Pxl8\WordPress\Storage
 */

namespace Pxl8\WordPress\Storage;

class AttachmentMeta {
    /**
     * Get PXL8 image ID
     *
     * @param int $attachmentId
     * @return string|null
     */
    public function getImageId($attachmentId) {
        $imageId = get_post_meta($attachmentId, '_pxl8_image_id', true);
        return !empty($imageId) ? $imageId : null;
    }

    /**
     * Set PXL8 image ID
     *
     * @param int $attachmentId
     * @param string $imageId
     * @return bool
     */
    public function setImageId($attachmentId, $imageId) {
        return update_post_meta($attachmentId, '_pxl8_image_id', $imageId);
    }

    /**
     * Get status (ok, pending, failed)
     *
     * @param int $attachmentId
     * @return string|null
     */
    public function getStatus($attachmentId) {
        $status = get_post_meta($attachmentId, '_pxl8_status', true);
        return !empty($status) ? $status : null;
    }

    /**
     * Set status
     *
     * @param int $attachmentId
     * @param string $status (ok, pending, failed)
     * @return bool
     */
    public function setStatus($attachmentId, $status) {
        return update_post_meta($attachmentId, '_pxl8_status', $status);
    }

    /**
     * Get last error message
     *
     * @param int $attachmentId
     * @return string|null
     */
    public function getLastError($attachmentId) {
        $error = get_post_meta($attachmentId, '_pxl8_last_error', true);
        return !empty($error) ? $error : null;
    }

    /**
     * Set last error message
     *
     * @param int $attachmentId
     * @param string $error
     * @return bool
     */
    public function setLastError($attachmentId, $error) {
        // Truncate to 255 chars
        $error = substr($error, 0, 255);
        return update_post_meta($attachmentId, '_pxl8_last_error', $error);
    }

    /**
     * Get uploaded timestamp
     *
     * @param int $attachmentId
     * @return int|null
     */
    public function getUploadedAt($attachmentId) {
        $timestamp = get_post_meta($attachmentId, '_pxl8_uploaded_at', true);
        return !empty($timestamp) ? (int) $timestamp : null;
    }

    /**
     * Set uploaded timestamp
     *
     * @param int $attachmentId
     * @param int|null $timestamp (null = current time)
     * @return bool
     */
    public function setUploadedAt($attachmentId, $timestamp = null) {
        $timestamp = $timestamp ?? time();
        return update_post_meta($attachmentId, '_pxl8_uploaded_at', $timestamp);
    }

    /**
     * Get last sync timestamp
     *
     * @param int $attachmentId
     * @return int|null
     */
    public function getLastSyncAt($attachmentId) {
        $timestamp = get_post_meta($attachmentId, '_pxl8_last_sync_at', true);
        return !empty($timestamp) ? (int) $timestamp : null;
    }

    /**
     * Set last sync timestamp
     *
     * @param int $attachmentId
     * @param int|null $timestamp (null = current time)
     * @return bool
     */
    public function setLastSyncAt($attachmentId, $timestamp = null) {
        $timestamp = $timestamp ?? time();
        return update_post_meta($attachmentId, '_pxl8_last_sync_at', $timestamp);
    }

    /**
     * Get source file hash (for idempotency)
     *
     * @param int $attachmentId
     * @return string|null
     */
    public function getSourceHash($attachmentId) {
        $hash = get_post_meta($attachmentId, '_pxl8_source_hash', true);
        return !empty($hash) ? $hash : null;
    }

    /**
     * Set source file hash
     *
     * @param int $attachmentId
     * @param string $hash (format: "sha256:...")
     * @return bool
     */
    public function setSourceHash($attachmentId, $hash) {
        return update_post_meta($attachmentId, '_pxl8_source_hash', $hash);
    }

    /**
     * Check if attachment is already processed
     *
     * @param int $attachmentId
     * @return bool
     */
    public function isProcessed($attachmentId) {
        $imageId = $this->getImageId($attachmentId);
        return !empty($imageId);
    }

    /**
     * Check if attachment upload succeeded
     *
     * @param int $attachmentId
     * @return bool
     */
    public function isSuccess($attachmentId) {
        return $this->getStatus($attachmentId) === 'ok';
    }

    /**
     * Check if attachment upload failed
     *
     * @param int $attachmentId
     * @return bool
     */
    public function isFailed($attachmentId) {
        return $this->getStatus($attachmentId) === 'failed';
    }

    /**
     * Clear all PXL8 metadata for attachment
     *
     * @param int $attachmentId
     * @return bool
     */
    public function clear($attachmentId) {
        delete_post_meta($attachmentId, '_pxl8_image_id');
        delete_post_meta($attachmentId, '_pxl8_status');
        delete_post_meta($attachmentId, '_pxl8_last_error');
        delete_post_meta($attachmentId, '_pxl8_uploaded_at');
        delete_post_meta($attachmentId, '_pxl8_last_sync_at');
        delete_post_meta($attachmentId, '_pxl8_source_hash');
        return true;
    }
}

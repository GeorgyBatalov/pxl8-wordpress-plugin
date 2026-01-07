<?php
/**
 * Uninstall Handler - Clean up all PXL8 data when plugin is deleted
 *
 * This file is executed when the plugin is uninstalled via WordPress admin.
 * It removes all PXL8 options and attachment metadata from the database.
 *
 * @package Pxl8\WordPress
 */

// Exit if not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete all PXL8 options
 */
function pxl8_delete_options() {
    delete_option('pxl8_base_url');
    delete_option('pxl8_api_key');
    delete_option('pxl8_enabled');
    delete_option('pxl8_auto_optimize');
    delete_option('pxl8_default_quality');
    delete_option('pxl8_default_format');
    delete_option('pxl8_default_fit');
}

/**
 * Delete all PXL8 transients
 */
function pxl8_delete_transients() {
    delete_transient('pxl8_quota_cache');
}

/**
 * Delete all PXL8 attachment metadata
 */
function pxl8_delete_attachment_metadata() {
    global $wpdb;

    // Get all attachment IDs
    $attachment_ids = $wpdb->get_col("
        SELECT ID
        FROM {$wpdb->posts}
        WHERE post_type = 'attachment'
    ");

    if (empty($attachment_ids)) {
        return;
    }

    // Delete all PXL8 metadata for each attachment
    $meta_keys = [
        '_pxl8_image_id',
        '_pxl8_status',
        '_pxl8_last_error',
        '_pxl8_uploaded_at',
        '_pxl8_last_sync_at',
        '_pxl8_source_hash',
    ];

    foreach ($meta_keys as $meta_key) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $meta_key
            )
        );
    }
}

/**
 * Main uninstall routine
 */
function pxl8_uninstall() {
    // 1. Delete options
    pxl8_delete_options();

    // 2. Delete transients
    pxl8_delete_transients();

    // 3. Delete attachment metadata
    pxl8_delete_attachment_metadata();

    // Log uninstall (if WP_DEBUG_LOG enabled)
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[PXL8] Plugin uninstalled - all data deleted');
    }
}

// Run uninstall
pxl8_uninstall();

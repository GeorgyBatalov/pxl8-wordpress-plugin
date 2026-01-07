<?php
/**
 * Plugin Name: PXL8 Image Optimization
 * Plugin URI: https://pxl8.ru
 * Description: Automatic image optimization with PXL8 CDN - on-the-fly transformations, edge caching, and responsive images
 * Version: 1.0.0
 * Author: PXL8 Team
 * Author URI: https://pxl8.ru
 * License: MIT
 * Text Domain: pxl8
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('PXL8_VERSION', '1.0.0');
define('PXL8_PLUGIN_FILE', __FILE__);
define('PXL8_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PXL8_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload Composer dependencies
if (file_exists(PXL8_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once PXL8_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize plugin
add_action('plugins_loaded', 'pxl8_init');

function pxl8_init() {
    // Check dependencies
    if (!class_exists('Pxl8\\Pxl8Client')) {
        add_action('admin_notices', 'pxl8_missing_dependencies_notice');
        return;
    }

    // Initialize plugin class
    require_once PXL8_PLUGIN_DIR . 'includes/Plugin.php';

    $plugin = new \Pxl8\WordPress\Plugin();
    $plugin->init();
}

function pxl8_missing_dependencies_notice() {
    ?>
    <div class="notice notice-error">
        <p><strong>PXL8:</strong> Composer dependencies are missing. Please run <code>composer install</code> in the plugin directory.</p>
    </div>
    <?php
}

// Activation hook
register_activation_hook(__FILE__, 'pxl8_activate');

function pxl8_activate() {
    // Set default options
    add_option('pxl8_base_url', 'https://img.pxl8.ru');
    add_option('pxl8_enabled', false);
    add_option('pxl8_auto_optimize', false); // OFF by default (prevents unexpected quota consumption)
    add_option('pxl8_default_quality', 85);
    add_option('pxl8_default_format', 'auto');
    add_option('pxl8_default_fit', 'cover');
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'pxl8_deactivate');

function pxl8_deactivate() {
    // Clear transient cache
    delete_transient('pxl8_usage_data');

    // Unschedule cron jobs (if any)
    wp_clear_scheduled_hook('pxl8_background_upload');
}

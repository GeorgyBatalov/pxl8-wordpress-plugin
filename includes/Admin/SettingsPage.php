<?php
/**
 * Settings Page
 *
 * @package Pxl8\WordPress\Admin
 */

namespace Pxl8\WordPress\Admin;

use Pxl8\WordPress\Sdk\ClientFactory;
use Pxl8\WordPress\Storage\Options;

class SettingsPage {
    /**
     * @var Options
     */
    private $options;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * Constructor
     *
     * @param Options $options
     * @param ClientFactory $clientFactory
     */
    public function __construct(Options $options, ClientFactory $clientFactory) {
        $this->options = $options;
        $this->clientFactory = $clientFactory;
    }

    /**
     * Initialize settings page
     */
    public function init() {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);

        // AJAX handlers
        add_action('wp_ajax_pxl8_test_connection', [$this, 'handleTestConnection']);
    }

    /**
     * Add settings page to WordPress admin menu
     */
    public function addSettingsPage() {
        add_options_page(
            'PXL8 Settings',
            'PXL8',
            'manage_options',
            'pxl8',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Register plugin settings
     */
    public function registerSettings() {
        register_setting('pxl8_settings', 'pxl8_base_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://img.pxl8.ru',
        ]);

        register_setting('pxl8_settings', 'pxl8_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting('pxl8_settings', 'pxl8_enabled', [
            'type' => 'boolean',
            'default' => false,
        ]);

        register_setting('pxl8_settings', 'pxl8_auto_optimize', [
            'type' => 'boolean',
            'default' => false, // OFF by default (v1.0.1 spec)
        ]);

        register_setting('pxl8_settings', 'pxl8_default_quality', [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitizeQuality'],
            'default' => 85,
        ]);

        register_setting('pxl8_settings', 'pxl8_default_format', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitizeFormat'],
            'default' => 'auto',
        ]);

        register_setting('pxl8_settings', 'pxl8_default_fit', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitizeFit'],
            'default' => 'cover',
        ]);
    }

    /**
     * Sanitize quality value (1-100)
     */
    public function sanitizeQuality($value) {
        $value = (int) $value;
        return max(1, min(100, $value));
    }

    /**
     * Sanitize format value
     */
    public function sanitizeFormat($value) {
        $allowed = ['auto', 'webp', 'avif', 'jpg', 'png'];
        return in_array($value, $allowed, true) ? $value : 'auto';
    }

    /**
     * Sanitize fit mode value
     */
    public function sanitizeFit($value) {
        $allowed = ['cover', 'contain', 'fill', 'crop'];
        return in_array($value, $allowed, true) ? $value : 'cover';
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current values
        $baseUrl = $this->options->getBaseUrl();
        $apiKey = $this->options->getApiKey();
        $enabled = $this->options->isEnabled();
        $autoOptimize = $this->options->isAutoOptimizeEnabled();
        $quality = $this->options->getDefaultQuality();
        $format = $this->options->getDefaultFormat();
        $fit = $this->options->getDefaultFit();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (!$this->clientFactory->isSdkAvailable()): ?>
                <div class="notice notice-error">
                    <p><strong>PXL8 SDK not found.</strong> Please run <code>composer install</code> in the plugin directory.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('pxl8_settings'); ?>

                <table class="form-table">
                    <!-- Base URL -->
                    <tr>
                        <th scope="row">
                            <label for="pxl8_base_url">Base URL</label>
                        </th>
                        <td>
                            <input type="url"
                                   id="pxl8_base_url"
                                   name="pxl8_base_url"
                                   value="<?php echo esc_attr($baseUrl); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description">
                                Your tenant CNAME (e.g., https://img.example.com)
                            </p>
                        </td>
                    </tr>

                    <!-- API Key -->
                    <tr>
                        <th scope="row">
                            <label for="pxl8_api_key">API Key</label>
                        </th>
                        <td>
                            <input type="password"
                                   id="pxl8_api_key"
                                   name="pxl8_api_key"
                                   value="<?php echo esc_attr($apiKey); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description">
                                Get your API key from PXL8 dashboard
                            </p>
                        </td>
                    </tr>

                    <!-- Test Connection Button -->
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button type="button" id="pxl8-test-connection" class="button">
                                Test Connection
                            </button>
                            <span id="pxl8-test-result"></span>
                        </td>
                    </tr>

                    <!-- Enable Plugin -->
                    <tr>
                        <th scope="row">Enable Plugin</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="pxl8_enabled"
                                       value="1"
                                       <?php checked($enabled, true); ?>>
                                Enable PXL8 image optimization
                            </label>
                        </td>
                    </tr>

                    <!-- Auto-Optimize -->
                    <tr>
                        <th scope="row">Auto-Optimize on Upload</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="pxl8_auto_optimize"
                                       value="1"
                                       <?php checked($autoOptimize, true); ?>>
                                Automatically optimize images on upload
                            </label>
                            <p class="description">
                                <strong>⚠️ Warning:</strong> May consume quota. Enable only if you understand the implications.
                            </p>
                        </td>
                    </tr>

                    <!-- Default Quality -->
                    <tr>
                        <th scope="row">
                            <label for="pxl8_default_quality">Default Quality</label>
                        </th>
                        <td>
                            <input type="number"
                                   id="pxl8_default_quality"
                                   name="pxl8_default_quality"
                                   value="<?php echo esc_attr($quality); ?>"
                                   min="1"
                                   max="100"
                                   step="1"
                                   class="small-text">
                            <p class="description">Image quality (1-100). Default: 85</p>
                        </td>
                    </tr>

                    <!-- Default Format -->
                    <tr>
                        <th scope="row">
                            <label for="pxl8_default_format">Default Format</label>
                        </th>
                        <td>
                            <select id="pxl8_default_format" name="pxl8_default_format">
                                <option value="auto" <?php selected($format, 'auto'); ?>>Auto</option>
                                <option value="webp" <?php selected($format, 'webp'); ?>>WebP</option>
                                <option value="avif" <?php selected($format, 'avif'); ?>>AVIF</option>
                                <option value="jpg" <?php selected($format, 'jpg'); ?>>JPEG</option>
                                <option value="png" <?php selected($format, 'png'); ?>>PNG</option>
                            </select>
                            <p class="description">Output format for optimized images</p>
                        </td>
                    </tr>

                    <!-- Default Fit -->
                    <tr>
                        <th scope="row">
                            <label for="pxl8_default_fit">Default Fit Mode</label>
                        </th>
                        <td>
                            <select id="pxl8_default_fit" name="pxl8_default_fit">
                                <option value="cover" <?php selected($fit, 'cover'); ?>>Cover</option>
                                <option value="contain" <?php selected($fit, 'contain'); ?>>Contain</option>
                                <option value="fill" <?php selected($fit, 'fill'); ?>>Fill</option>
                                <option value="crop" <?php selected($fit, 'crop'); ?>>Crop</option>
                            </select>
                            <p class="description">How images are resized</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle Test Connection AJAX request
     */
    public function handleTestConnection() {
        // Verify nonce
        check_ajax_referer('pxl8_admin_nonce', 'nonce');

        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Get credentials from POST
        $baseUrl = isset($_POST['base_url']) ? sanitize_text_field($_POST['base_url']) : '';
        $apiKey = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        // Validate inputs
        if (empty($baseUrl) || empty($apiKey)) {
            wp_send_json_error(['message' => 'Base URL and API Key are required']);
        }

        // Test connection
        try {
            $client = $this->clientFactory->create($apiKey, $baseUrl);
            $tenant = $client->getTenant();

            wp_send_json_success([
                'message' => sprintf('✅ Connected! Tenant: %s', $tenant['name'] ?? 'Unknown')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => sprintf('❌ Connection failed: %s', $e->getMessage())
            ]);
        }
    }
}

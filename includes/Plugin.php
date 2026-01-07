<?php
/**
 * Main Plugin Class
 *
 * @package Pxl8\WordPress
 */

namespace Pxl8\WordPress;

use Pxl8\WordPress\Admin\SettingsPage;
use Pxl8\WordPress\Admin\QuotaWidget;
use Pxl8\WordPress\Sdk\ClientFactory;
use Pxl8\WordPress\Storage\Options;
use Pxl8\WordPress\Storage\AttachmentMeta;
use Pxl8\WordPress\Diagnostics\Logger;
use Pxl8\WordPress\Media\UploadHandler;
use Pxl8\WordPress\Media\UrlRewriter;

class Plugin {
    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var SettingsPage
     */
    private $settingsPage;

    /**
     * @var QuotaWidget
     */
    private $quotaWidget;

    /**
     * @var AttachmentMeta
     */
    private $attachmentMeta;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var UploadHandler
     */
    private $uploadHandler;

    /**
     * @var UrlRewriter
     */
    private $urlRewriter;

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize core components
        $this->options = new Options();
        $this->clientFactory = new ClientFactory($this->options);
        $this->logger = new Logger();
        $this->attachmentMeta = new AttachmentMeta();

        // Initialize admin components
        if (is_admin()) {
            $this->settingsPage = new SettingsPage($this->options, $this->clientFactory);
            $this->settingsPage->init();

            // Initialize Day 4 components (quota widget)
            $this->quotaWidget = new QuotaWidget(
                $this->options,
                $this->clientFactory,
                $this->logger
            );
            $this->quotaWidget->init();
        }

        // Initialize Day 2 components (upload handler)
        $this->uploadHandler = new UploadHandler(
            $this->options,
            $this->clientFactory,
            $this->attachmentMeta,
            $this->logger
        );
        $this->uploadHandler->init();

        // Initialize Day 3 components (URL rewriter)
        $this->urlRewriter = new UrlRewriter(
            $this->options,
            $this->attachmentMeta,
            $this->logger
        );
        $this->urlRewriter->init();

        // Register hooks
        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks() {
        // Admin hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Note: UploadHandler, UrlRewriter, and QuotaWidget register their own hooks in init()
    }

    /**
     * Enqueue admin assets (CSS, JS)
     */
    public function enqueueAdminAssets($hook) {
        // Load on all admin pages (settings page + dashboard for quota widget)
        // Enqueue admin CSS
        wp_enqueue_style(
            'pxl8-admin',
            PXL8_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PXL8_VERSION
        );

        // Enqueue admin JS
        wp_enqueue_script(
            'pxl8-admin',
            PXL8_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            PXL8_VERSION,
            true
        );

        // Pass AJAX URL and nonce to JavaScript
        wp_localize_script('pxl8-admin', 'pxl8_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pxl8_admin_nonce'),
        ]);
    }
}

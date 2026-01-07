<?php
/**
 * PXL8 SDK Client Factory
 *
 * @package Pxl8\WordPress\Sdk
 */

namespace Pxl8\WordPress\Sdk;

use Pxl8\WordPress\Storage\Options;

class ClientFactory {
    /**
     * @var Options
     */
    private $options;

    /**
     * Constructor
     *
     * @param Options $options
     */
    public function __construct(Options $options) {
        $this->options = $options;
    }

    /**
     * Create PXL8 client instance
     *
     * @param string|null $apiKey Override API key (for Test Connection)
     * @param string|null $baseUrl Override base URL (for Test Connection)
     * @return \Pxl8\Pxl8Client
     * @throws \Exception
     */
    public function create($apiKey = null, $baseUrl = null) {
        // Get credentials from options or use overrides
        $apiKey = $apiKey ?? $this->options->getApiKey();
        $baseUrl = $baseUrl ?? $this->options->getBaseUrl();

        // Validate API key
        if (empty($apiKey)) {
            throw new \Exception('PXL8 API key not configured');
        }

        // Validate base URL
        if (empty($baseUrl)) {
            throw new \Exception('PXL8 base URL not configured');
        }

        // Check if SDK class exists
        if (!class_exists('\\Pxl8\\Pxl8Client')) {
            throw new \Exception('PXL8 SDK not found. Please run: composer install');
        }

        // Create and return client
        return new \Pxl8\Pxl8Client($apiKey, [
            'baseUrl' => $baseUrl,
            'maxRetries' => 3,
            'retryDelay' => 1000, // 1 second
        ]);
    }

    /**
     * Check if SDK is available
     *
     * @return bool
     */
    public function isSdkAvailable() {
        return class_exists('\\Pxl8\\Pxl8Client');
    }
}

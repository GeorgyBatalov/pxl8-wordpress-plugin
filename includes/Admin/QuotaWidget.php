<?php
/**
 * Quota Widget - Dashboard widget showing PXL8 quota usage
 *
 * @package Pxl8\WordPress\Admin
 */

namespace Pxl8\WordPress\Admin;

use Pxl8\WordPress\Sdk\ClientFactory;
use Pxl8\WordPress\Storage\Options;
use Pxl8\WordPress\Diagnostics\Logger;

class QuotaWidget {
    /**
     * @var Options
     */
    private $options;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Transient cache key
     */
    const TRANSIENT_KEY = 'pxl8_quota_cache';

    /**
     * Cache duration (5 minutes)
     */
    const CACHE_DURATION = 300;

    /**
     * Constructor
     *
     * @param Options $options
     * @param ClientFactory $clientFactory
     * @param Logger $logger
     */
    public function __construct(
        Options $options,
        ClientFactory $clientFactory,
        Logger $logger
    ) {
        $this->options = $options;
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'registerDashboardWidget']);

        // AJAX handler for refresh quota
        add_action('wp_ajax_pxl8_refresh_quota', [$this, 'handleRefreshQuota']);
    }

    /**
     * Register dashboard widget
     */
    public function registerDashboardWidget() {
        // Only show if plugin is enabled and API key is configured
        if (!$this->options->isEnabled() || empty($this->options->getApiKey())) {
            return;
        }

        wp_add_dashboard_widget(
            'pxl8_quota_widget',
            'PXL8 Quota Usage',
            [$this, 'renderDashboardWidget']
        );
    }

    /**
     * Render dashboard widget
     */
    public function renderDashboardWidget() {
        // Get quota data (from cache or API)
        $quota = $this->getQuotaData();

        if ($quota === null) {
            echo '<div class="pxl8-quota-widget">';
            echo '<p style="color: #dc3232;">❌ Failed to fetch quota data. Check API key and connection.</p>';
            echo '<button type="button" class="button button-secondary" id="pxl8-refresh-quota">Retry</button>';
            echo '</div>';
            return;
        }

        // Render quota usage
        echo '<div class="pxl8-quota-widget">';

        // Storage
        $this->renderQuotaItem(
            'Storage',
            $quota['storage_used'] ?? 0,
            $quota['storage_limit'] ?? 0,
            'MB'
        );

        // Bandwidth
        $this->renderQuotaItem(
            'Bandwidth',
            $quota['bandwidth_used'] ?? 0,
            $quota['bandwidth_limit'] ?? 0,
            'MB'
        );

        // Requests
        $this->renderQuotaItem(
            'Requests',
            $quota['requests_used'] ?? 0,
            $quota['requests_limit'] ?? 0,
            ''
        );

        // Refresh button
        echo '<div style="margin-top: 15px;">';
        echo '<button type="button" class="button button-secondary" id="pxl8-refresh-quota">Refresh Quota</button>';
        echo '<span id="pxl8-quota-status" style="margin-left: 10px;"></span>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render single quota item (progress bar)
     *
     * @param string $label
     * @param int|float $used
     * @param int|float $limit
     * @param string $unit
     */
    private function renderQuotaItem($label, $used, $limit, $unit) {
        // Calculate percentage
        $percentage = $limit > 0 ? ($used / $limit) * 100 : 0;
        $percentage = min($percentage, 100); // Cap at 100%

        // Determine color (green < 80%, orange 80-95%, red > 95%)
        $color = '#46b450'; // Green
        if ($percentage >= 95) {
            $color = '#dc3232'; // Red
        } elseif ($percentage >= 80) {
            $color = '#f56e28'; // Orange
        }

        // Format numbers
        $usedFormatted = $this->formatNumber($used);
        $limitFormatted = $this->formatNumber($limit);

        echo '<div class="pxl8-quota-item" style="margin-bottom: 15px;">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">';
        echo '<strong>' . esc_html($label) . '</strong>';
        echo '<span style="font-size: 12px; color: #666;">' . esc_html($usedFormatted) . ' / ' . esc_html($limitFormatted) . ' ' . esc_html($unit) . ' (' . number_format($percentage, 1) . '%)</span>';
        echo '</div>';
        echo '<div class="pxl8-progress-bar" style="width: 100%; height: 20px; background-color: #f0f0f0; border-radius: 3px; overflow: hidden;">';
        echo '<div class="pxl8-progress-fill" style="width: ' . esc_attr($percentage) . '%; height: 100%; background-color: ' . esc_attr($color) . '; transition: width 0.3s ease;"></div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Format number (add thousands separator)
     *
     * @param int|float $number
     * @return string
     */
    private function formatNumber($number) {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 2) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000, 2) . 'K';
        }
        return number_format($number, 0);
    }

    /**
     * Get quota data (from cache or API)
     *
     * @param bool $forceRefresh Force API call (skip cache)
     * @return array|null
     */
    private function getQuotaData($forceRefresh = false) {
        // Try to get from cache
        if (!$forceRefresh) {
            $cached = get_transient(self::TRANSIENT_KEY);
            if ($cached !== false) {
                $this->logger->info('Quota data retrieved from cache');
                return $cached;
            }
        }

        // Fetch from API
        try {
            $this->logger->info('Fetching quota data from PXL8 API');

            $client = $this->clientFactory->create();
            $tenant = $client->getTenant();

            // Extract quota data
            $quotaData = [
                'storage_used' => $tenant['storageUsed'] ?? 0,
                'storage_limit' => $tenant['storageLimit'] ?? 0,
                'bandwidth_used' => $tenant['bandwidthUsed'] ?? 0,
                'bandwidth_limit' => $tenant['bandwidthLimit'] ?? 0,
                'requests_used' => $tenant['requestsUsed'] ?? 0,
                'requests_limit' => $tenant['requestsLimit'] ?? 0,
            ];

            // Store in cache
            set_transient(self::TRANSIENT_KEY, $quotaData, self::CACHE_DURATION);

            $this->logger->info('Quota data fetched successfully', [
                'storage_used' => $quotaData['storage_used'],
                'bandwidth_used' => $quotaData['bandwidth_used'],
                'requests_used' => $quotaData['requests_used'],
            ]);

            return $quotaData;

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch quota data', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Handle refresh quota AJAX request
     */
    public function handleRefreshQuota() {
        // Check nonce
        check_ajax_referer('pxl8_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Force refresh quota data
        $quota = $this->getQuotaData(true);

        if ($quota === null) {
            wp_send_json_error(['message' => 'Failed to fetch quota data']);
        }

        wp_send_json_success([
            'message' => '✅ Quota refreshed successfully',
            'quota' => $quota,
        ]);
    }
}

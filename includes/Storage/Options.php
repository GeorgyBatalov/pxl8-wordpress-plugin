<?php
/**
 * WordPress Options Wrapper
 *
 * @package Pxl8\WordPress\Storage
 */

namespace Pxl8\WordPress\Storage;

class Options {
    /**
     * Get option value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null) {
        return get_option($key, $default);
    }

    /**
     * Set option value
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set($key, $value) {
        return update_option($key, $value);
    }

    /**
     * Delete option
     *
     * @param string $key
     * @return bool
     */
    public function delete($key) {
        return delete_option($key);
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl() {
        return $this->get('pxl8_base_url', 'https://img.pxl8.ru');
    }

    /**
     * Get API key
     *
     * @return string
     */
    public function getApiKey() {
        return $this->get('pxl8_api_key', '');
    }

    /**
     * Check if plugin is enabled
     *
     * @return bool
     */
    public function isEnabled() {
        return (bool) $this->get('pxl8_enabled', false);
    }

    /**
     * Check if auto-optimize is enabled
     *
     * @return bool
     */
    public function isAutoOptimizeEnabled() {
        return (bool) $this->get('pxl8_auto_optimize', false);
    }

    /**
     * Get default quality
     *
     * @return int
     */
    public function getDefaultQuality() {
        return (int) $this->get('pxl8_default_quality', 85);
    }

    /**
     * Get default format
     *
     * @return string
     */
    public function getDefaultFormat() {
        return $this->get('pxl8_default_format', 'auto');
    }

    /**
     * Get default fit mode
     *
     * @return string
     */
    public function getDefaultFit() {
        return $this->get('pxl8_default_fit', 'cover');
    }
}

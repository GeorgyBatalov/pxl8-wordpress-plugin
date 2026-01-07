<?php
/**
 * Error Logger
 *
 * @package Pxl8\WordPress\Diagnostics
 */

namespace Pxl8\WordPress\Diagnostics;

class Logger {
    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, $context = []) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $formatted = $this->formatMessage('ERROR', $message, $context);
        error_log($formatted);
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    public function info($message, $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $formatted = $this->formatMessage('INFO', $message, $context);
        error_log($formatted);
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     */
    public function warning($message, $context = []) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $formatted = $this->formatMessage('WARNING', $message, $context);
        error_log($formatted);
    }

    /**
     * Format log message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    private function formatMessage($level, $message, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = '';

        if (!empty($context)) {
            // Never log API keys
            if (isset($context['api_key'])) {
                $context['api_key'] = '[REDACTED]';
            }

            $contextStr = ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return sprintf('[%s] [PXL8] [%s] %s%s', $timestamp, $level, $message, $contextStr);
    }
}

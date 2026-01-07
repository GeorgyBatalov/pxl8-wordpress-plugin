/**
 * PXL8 Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Test Connection button (Settings page)
        $('#pxl8-test-connection').on('click', function() {
            var $button = $(this);
            var $result = $('#pxl8-test-result');

            // Get current values
            var baseUrl = $('#pxl8_base_url').val();
            var apiKey = $('#pxl8_api_key').val();

            // Validate inputs
            if (!baseUrl || !apiKey) {
                $result.html('<span style="color: #dc3232;">❌ Please enter Base URL and API Key</span>');
                return;
            }

            // Disable button and show loading
            $button.prop('disabled', true).text('Testing...');
            $result.html('<span style="color: #0073aa;">⏳ Connecting...</span>');

            // Send AJAX request
            $.ajax({
                url: pxl8_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pxl8_test_connection',
                    nonce: pxl8_admin.nonce,
                    base_url: baseUrl,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: #46b450; font-weight: bold;">' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: #dc3232; font-weight: bold;">' + response.data.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $result.html('<span style="color: #dc3232;">❌ Network error: ' + error + '</span>');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        });

        // Refresh Quota button (Dashboard widget)
        $(document).on('click', '#pxl8-refresh-quota', function() {
            var $button = $(this);
            var $status = $('#pxl8-quota-status');

            // Disable button and show loading
            $button.prop('disabled', true).text('Refreshing...');
            $status.html('<span style="color: #0073aa;">⏳ Fetching quota...</span>');

            // Send AJAX request
            $.ajax({
                url: pxl8_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'pxl8_refresh_quota',
                    nonce: pxl8_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450; font-weight: bold;">' + response.data.message + '</span>');

                        // Reload page after 1 second to show updated quota
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $status.html('<span style="color: #dc3232; font-weight: bold;">❌ ' + response.data.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('<span style="color: #dc3232;">❌ Network error: ' + error + '</span>');
                },
                complete: function() {
                    // Re-enable button after 1 second
                    setTimeout(function() {
                        $button.prop('disabled', false).text('Refresh Quota');
                    }, 1000);
                }
            });
        });
    });
})(jQuery);

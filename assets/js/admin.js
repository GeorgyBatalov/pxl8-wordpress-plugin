/**
 * PXL8 Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Test Connection button
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
    });
})(jQuery);

jQuery(document).ready(function($) {
    $('#wc-oe-send-invoice-btn').on('click', function() {
        const $btn = $(this);
        const orderId = $btn.data('order-id');
        const retailerId = $('#wc-oe-invoice-retailer').val();
        const $result = $('#wc-oe-invoice-result');
        
        if (!retailerId) {
            $result.removeClass('success').addClass('error').text('Please select a retailer.').show();
            return;
        }
        
        $btn.prop('disabled', true).text('Sending...');
        $result.hide();
        
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_send_invoice',
            order_id: orderId,
            retailer_id: retailerId,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            $btn.prop('disabled', false).text('Send Invoice');
            if (response.success) {
                $result.removeClass('error').addClass('success').text(response.data).show();
            } else {
                $result.removeClass('success').addClass('error').text(response.data || 'Failed to send.').show();
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Send Invoice');
            $result.removeClass('success').addClass('error').text('Network error.').show();
        });
    });
});

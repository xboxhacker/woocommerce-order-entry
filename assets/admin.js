jQuery(document).ready(function($) {
    let productLineCounter = 1;
    let searchTimeout;
    
    // Load saved customer
    $('#saved-customers').on('change', function() {
        const customerId = $(this).val();
        if (!customerId) return;
        
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_load_customer',
            customer_id: customerId,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            if (response.success) {
                const customer = response.data;
                $('#customer-name').val(customer.name);
                $('#customer-email').val(customer.email);
                $('#customer-phone').val(customer.phone);
                $('#customer-address1').val(customer.address_1);
                $('#customer-address2').val(customer.address_2);
                $('#customer-city').val(customer.city);
                $('#customer-state').val(customer.state);
                $('#customer-zip').val(customer.postcode);
                $('#customer-country').val(customer.country);
                $('#customer-po').val(customer.po_number);
            }
        });
    });
    
    // Parse customer information
    $('#parse-customer').on('click', function() {
        const customerText = $('#paste-customer').val();
        if (!customerText.trim()) return;
        
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_parse_customer',
            customer_text: customerText,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            if (response.success) {
                const data = response.data;
                if (data.name) $('#customer-name').val(data.name);
                if (data.email) $('#customer-email').val(data.email);
                if (data.phone) $('#customer-phone').val(data.phone);
                if (data.address_1) $('#customer-address1').val(data.address_1);
                if (data.address_2) $('#customer-address2').val(data.address_2);
                if (data.city) $('#customer-city').val(data.city);
                if (data.state) $('#customer-state').val(data.state);
                if (data.postcode) $('#customer-zip').val(data.postcode);
                if (data.country) $('#customer-country').val(data.country);
            }
        });
    });
    
    // Product search functionality with spinner
    $(document).on('input', '.product-sku', function() {
        const $input = $(this);
        const $container = $input.closest('.product-search-container');
        const $spinner = $container.find('.search-spinner');
        const $results = $input.closest('.wc-oe-field').find('.product-search-results');
        const searchTerm = $input.val();
        
        clearTimeout(searchTimeout);
        
        // Hide spinner and results if search term is too short
        if (searchTerm.length < 2) {
            $spinner.removeClass('show');
            $results.empty().hide();
            return;
        }
        
        // Show spinner immediately
        $spinner.addClass('show');
        
        searchTimeout = setTimeout(function() {
            $.post(wc_oe_ajax.ajax_url, {
                action: 'wc_oe_search_products',
                search_term: searchTerm,
                nonce: wc_oe_ajax.nonce
            }, function(response) {
                // Hide spinner
                $spinner.removeClass('show');
                
                $results.empty();
                
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(product) {
                        const $item = $('<div class="product-search-item">')
                            .text(product.sku + ' - ' + product.name + ' ($' + product.price + ')')
                            .data('product', product);
                        $results.append($item);
                    });
                    $results.show();
                } else {
                    $results.hide();
                }
            }).fail(function() {
                // Hide spinner on error
                $spinner.removeClass('show');
            });
        }, 300);
    });
    
    // Select product from search results
    $(document).on('click', '.product-search-item', function() {
        const product = $(this).data('product');
        const $productLine = $(this).closest('.product-line');
        
        $productLine.find('.product-sku').val(product.sku);
        $productLine.find('.product-description').val(product.name);
        $productLine.find('.product-price').val(product.price);
        $productLine.find('.product-id').val(product.id);
        
        $(this).parent().empty().hide();
    });
    
    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.product-sku, .product-search-results').length) {
            $('.product-search-results').empty().hide();
        }
    });
    
    // Add product line
    $('#add-product-line').on('click', function() {
        productLineCounter++;
        
        const $newLine = $('.product-line:first').clone();
        $newLine.attr('data-line', productLineCounter);
        $newLine.find('.product-line-header h4').text('Product Line ' + productLineCounter);
        $newLine.find('input').val('');
        $newLine.find('.product-quantity').val('1');
        $newLine.find('.product-search-results').empty().hide();
        $newLine.find('.search-spinner').removeClass('show');
        
        $('#product-lines-container').append($newLine);
    });
    
    // Remove product line
    $('#remove-product-line').on('click', function() {
        const $lines = $('.product-line');
        if ($lines.length > 1) {
            $lines.last().remove();
            productLineCounter--;
        } else {
            alert('Cannot remove all product lines. At least one line is required.');
        }
    });
    
    // Save customer
    function saveCustomer() {
        const customerData = {
            action: 'wc_oe_save_customer',
            name: $('#customer-name').val(),
            email: $('#customer-email').val(),
            phone: $('#customer-phone').val(),
            address_1: $('#customer-address1').val(),
            address_2: $('#customer-address2').val(),
            city: $('#customer-city').val(),
            state: $('#customer-state').val(),
            postcode: $('#customer-zip').val(),
            country: $('#customer-country').val(),
            po_number: $('#customer-po').val(),
            nonce: wc_oe_ajax.nonce
        };
        
        return $.post(wc_oe_ajax.ajax_url, customerData);
    }
    
    // Submit order
    $('#submit-order').on('click', function() {
        const $button = $(this);
        const $result = $('#order-result');
        
        // Validate required fields
        if (!$('#customer-name').val() || !$('#customer-email').val()) {
            $result.removeClass('success').addClass('error').text('Please fill in required customer fields.').show();
            return;
        }
        
        // Validate product lines
        let validLines = true;
        const productLines = [];
        
        $('.product-line').each(function() {
            const quantity = $(this).find('.product-quantity').val();
            const productId = $(this).find('.product-id').val();
            const price = $(this).find('.product-price').val();
            
            if (!quantity || !productId || !price) {
                validLines = false;
                return false;
            }
            
            productLines.push({
                quantity: quantity,
                product_id: productId,
                price: price
            });
        });
        
        if (!validLines) {
            $result.removeClass('success').addClass('error').text('Please complete all product lines.').show();
            return;
        }
        
        $button.prop('disabled', true).text('Creating Order...');
        $result.hide();
        
        // Save customer if checkbox is checked
        const saveCustomerPromise = $('#save-customer').is(':checked') ? saveCustomer() : $.Deferred().resolve();
        
        saveCustomerPromise.always(function() {
            // Create order
            const orderData = {
                action: 'wc_oe_create_order',
                name: $('#customer-name').val(),
                email: $('#customer-email').val(),
                phone: $('#customer-phone').val(),
                address_1: $('#customer-address1').val(),
                address_2: $('#customer-address2').val(),
                city: $('#customer-city').val(),
                state: $('#customer-state').val(),
                postcode: $('#customer-zip').val(),
                country: $('#customer-country').val(),
                po_number: $('#customer-po').val(),
                product_lines: productLines,
                nonce: wc_oe_ajax.nonce
            };
            
            $.post(wc_oe_ajax.ajax_url, orderData, function(response) {
                $button.prop('disabled', false).text('Create Order');
                
                if (response.success) {
                    $result.removeClass('error').addClass('success')
                        .html('Order created successfully! <a href="' + response.data.order_url + '" class="order-link" target="_blank">View Order #' + response.data.order_id + '</a>')
                        .show();
                    
                    // Reset form
                    resetForm();
                } else {
                    $result.removeClass('success').addClass('error').text('Error: ' + response.data).show();
                }
            }).fail(function() {
                $button.prop('disabled', false).text('Create Order');
                $result.removeClass('success').addClass('error').text('Network error occurred.').show();
            });
        });
    });
    
    function resetForm() {
        // Reset customer form
        $('#customer-name, #customer-email, #customer-phone, #customer-address1, #customer-address2, #customer-city, #customer-state, #customer-zip, #customer-po').val('');
        $('#customer-country').val('US'); // Reset to US default
        $('#save-customer').prop('checked', false);
        $('#paste-customer').val('');
        $('#saved-customers').val('');
        
        // Reset product lines
        $('.product-line:not(:first)').remove();
        $('.product-line:first input').val('');
        $('.product-line:first .product-quantity').val('1');
        $('.product-search-results').empty().hide();
        $('.search-spinner').removeClass('show');
        productLineCounter = 1;
    }
});
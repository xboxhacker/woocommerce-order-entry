jQuery(document).ready(function($) {
    let productLineCounter = 1;
    let searchTimeout;
    let selectedLiveRate = null;
    
    /**
     * Set state/province select from pasted text or saved value (2-letter or full name).
     */
    function setCustomerState(value) {
        const $sel = $('#customer-state');
        if (!$sel.length) return;
        if (value === undefined || value === null || String(value).trim() === '') {
            $sel.val('');
            return;
        }
        let v = String(value).trim();
        const upper = v.toUpperCase();
        if ($sel.find('option').filter(function() { return $(this).val() === upper; }).length) {
            $sel.val(upper);
            return;
        }
        const map = (typeof wc_oe_ajax !== 'undefined' && wc_oe_ajax.state_name_to_abbr) ? wc_oe_ajax.state_name_to_abbr : {};
        const key = v.toLowerCase();
        if (map[key]) {
            $sel.val(map[key]);
            return;
        }
        $sel.val('');
    }
    
    /**
     * Apply parsed customer fields from server (always set when key present).
     */
    function applyParsedCustomerData(data) {
        if (!data || typeof data !== 'object') return;
        if (Object.prototype.hasOwnProperty.call(data, 'name')) $('#customer-name').val(data.name || '');
        if (Object.prototype.hasOwnProperty.call(data, 'email')) $('#customer-email').val(data.email || '');
        if (Object.prototype.hasOwnProperty.call(data, 'phone')) $('#customer-phone').val(data.phone || '');
        if (Object.prototype.hasOwnProperty.call(data, 'address_1')) $('#customer-address1').val(data.address_1 || '');
        if (Object.prototype.hasOwnProperty.call(data, 'address_2')) $('#customer-address2').val(data.address_2 || '');
        if (Object.prototype.hasOwnProperty.call(data, 'city')) $('#customer-city').val(data.city || '');
        if (Object.prototype.hasOwnProperty.call(data, 'postcode')) $('#customer-zip').val(data.postcode || '');
        if (Object.prototype.hasOwnProperty.call(data, 'country')) $('#customer-country').val(data.country || 'US');
        if (Object.prototype.hasOwnProperty.call(data, 'state')) setCustomerState(data.state);
    }

    function customerOptionLabel(name, email) {
        const cleanName = (name || '').trim();
        const cleanEmail = (email || '').trim();
        return cleanName + (cleanEmail ? ' (' + cleanEmail + ')' : '');
    }

    function getCombinedPackageWeight() {
        const lbsRaw = $('#pkg-weight-lbs').val();
        const ozRaw = $('#pkg-weight-oz').val();
        const lbs = parseInt(lbsRaw, 10);
        const oz = parseInt(ozRaw, 10);
        const safeLbs = isNaN(lbs) || lbs < 0 ? 0 : lbs;
        const safeOz = isNaN(oz) || oz < 0 ? 0 : oz;
        return safeLbs + (safeOz / 16);
    }

    function setWeightFromPounds(totalPounds) {
        const weight = parseFloat(totalPounds);
        if (isNaN(weight) || weight <= 0) {
            $('#pkg-weight-lbs').val('');
            $('#pkg-weight-oz').val('');
            return;
        }
        const totalOz = Math.round(weight * 16);
        const lbs = Math.floor(totalOz / 16);
        const oz = totalOz % 16;
        $('#pkg-weight-lbs').val(lbs);
        $('#pkg-weight-oz').val(oz);
    }

    $('#pkg-weight-oz').on('change blur', function() {
        const raw = parseInt($(this).val(), 10);
        if (isNaN(raw) || raw < 0) {
            $(this).val('');
            return;
        }
        if (raw > 15) {
            $(this).val(15);
            return;
        }
        $(this).val(raw);
    });

    function setLiveRatesMessage(message, isError) {
        const $box = $('#live-rates-result');
        if (!$box.length) return;
        if (!message) {
            $box.removeClass('error success').empty().hide();
            return;
        }
        $box.removeClass('error success').addClass(isError ? 'error' : 'success').text(message).show();
    }

    function clearLiveRatesSelection() {
        selectedLiveRate = null;
        const $select = $('#live-rate-select');
        $select.empty().append($('<option></option>').val('').text('Get live rates first...')).prop('disabled', true);
    }

    function applyLiveRate(rate) {
        if (!rate || typeof rate !== 'object') return;
        selectedLiveRate = rate;
        const adjustedRaw = (rate.adjusted_amount !== undefined && rate.adjusted_amount !== null) ? rate.adjusted_amount : rate.amount;
        if (adjustedRaw !== undefined && adjustedRaw !== null) {
            const amount = parseFloat(adjustedRaw);
            if (!isNaN(amount)) {
                $('#shipping-amount').val(amount.toFixed(2));
            }
        }
        const liveText = rate.live_amount_display || rate.amount_display || '$0.00';
        const adjustedText = rate.adjusted_amount_display || rate.amount_display || '$0.00';
        setLiveRatesMessage('Applied ' + rate.label + ' (Live ' + liveText + ', Adjusted ' + adjustedText + ') to Shipping Amount.', false);
    }
    
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
                setCustomerState(customer.state);
                $('#customer-zip').val(customer.postcode);
                $('#customer-country').val(customer.country);
                $('#customer-po').val(customer.po_number);
                $('#order-retailer').val(customer.retailer_id || '');
                $('#shipping-amount').val(customer.shipping_amount !== undefined && customer.shipping_amount !== null ? customer.shipping_amount : '0');
                setWeightFromPounds(customer.package_weight || 0);
                $('#pkg-length').val(customer.package_length || '');
                $('#pkg-width').val(customer.package_width || '');
                $('#pkg-height').val(customer.package_height || '');
                refreshShippingRequirementHint();
                selectedLiveRate = null;
                setLiveRatesMessage('', false);
                $('#wc-oe-po-dup-msg').hide().text('');
            }
        });
    });
    
    function runParseCustomer() {
        const customerText = $('#paste-customer').val();
        if (!customerText.trim()) return;
        
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_parse_customer',
            customer_text: customerText,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            if (response.success) {
                applyParsedCustomerData(response.data);
            }
        });
    }
    
    // Parse customer information
    $('#parse-customer').on('click', function() {
        runParseCustomer();
    });
    
    function checkPoDuplicate() {
        const po = $('#customer-po').val().trim();
        const $msg = $('#wc-oe-po-dup-msg');
        $msg.hide().text('');
        if (!po) return;
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_check_po_duplicate',
            po_number: po,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            if (response.success && response.data && response.data.duplicate && response.data.message) {
                $msg.text(response.data.message).show();
            }
        });
    }
    
    $('#customer-po').on('blur', function() {
        checkPoDuplicate();
    });
    
    $('#customer-po').on('input', function() {
        $('#wc-oe-po-dup-msg').hide().text('');
    });

    $('#get-live-rates').on('click', function() {
        const city = ($('#customer-city').val() || '').trim();
        const state = $('#customer-state').val() || '';
        const postcode = ($('#customer-zip').val() || '').trim();
        const country = $('#customer-country').val() || 'US';
        const weight = getCombinedPackageWeight();
        const length = parseFloat($('#pkg-length').val());
        const width = parseFloat($('#pkg-width').val());
        const height = parseFloat($('#pkg-height').val());
        const $btn = $(this);

        if (!city || !state || !postcode || !country) {
            setLiveRatesMessage('Enter city, state/province, postal code, and country before requesting rates.', true);
            return;
        }
        if (isNaN(weight) || weight <= 0 || isNaN(length) || length <= 0 || isNaN(width) || width <= 0 || isNaN(height) || height <= 0) {
            setLiveRatesMessage('Enter valid package weight, length, width, and height values greater than zero.', true);
            return;
        }

        clearLiveRatesSelection();
        setLiveRatesMessage('Fetching live rates...', false);
        $btn.prop('disabled', true);

        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_get_live_rates',
            city: city,
            state: state,
            postcode: postcode,
            country: country,
            weight: weight,
            length: length,
            width: width,
            height: height,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            $btn.prop('disabled', false);
            if (!response.success || !response.data || !response.data.rates || !response.data.rates.length) {
                setLiveRatesMessage((response && response.data) ? response.data : 'No rates returned.', true);
                return;
            }

            const rates = response.data.rates;
            const $select = $('#live-rate-select');
            $select.empty();
            rates.forEach(function(rate, idx) {
                const liveText = rate.live_amount_display || rate.amount_display || '$0.00';
                const adjustedText = rate.adjusted_amount_display || rate.amount_display || '$0.00';
                const text = rate.label + ' - Live ' + liveText + ' / Adjusted ' + adjustedText;
                const $opt = $('<option></option>').val(rate.id || ('rate-' + idx)).text(text).data('rate', rate);
                if (idx === 0) {
                    $opt.prop('selected', true);
                }
                $select.append($opt);
            });
            $select.prop('disabled', false);
            setLiveRatesMessage('Loaded ' + rates.length + ' live rate option(s).', false);
            applyLiveRate(rates[0]);
        }).fail(function() {
            $btn.prop('disabled', false);
            setLiveRatesMessage('Failed to fetch rates. Please try again.', true);
        });
    });

    $('#live-rate-select').on('change', function() {
        const $selected = $(this).find('option:selected');
        const rate = $selected.data('rate');
        if (rate) {
            applyLiveRate(rate);
        }
    });

    $('#customer-city, #customer-state, #customer-zip, #customer-country, #pkg-weight-lbs, #pkg-weight-oz, #pkg-length, #pkg-width, #pkg-height').on('change input', function() {
        selectedLiveRate = null;
    });
    
    // Parse automatically after paste (user can still edit before submitting)
    $('#paste-customer').on('paste', function() {
        const $ta = $(this);
        setTimeout(function() {
            if ($ta.val().trim().length > 10) {
                runParseCustomer();
            }
        }, 50);
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
        const p = parseFloat(product.price);
        $productLine.find('.product-price').val(isNaN(p) ? '0.00' : p.toFixed(2));
        $productLine.find('.product-id').val(product.id);
        
        $(this).parent().empty().hide();
    });
    
    function productPriceIsZero(val) {
        if (val === '' || val === null || val === undefined) return true;
        const n = parseFloat(String(val).replace(/,/g, ''));
        return !isNaN(n) && n === 0;
    }
    
    $(document).on('focus', '.product-price', function() {
        const $el = $(this);
        if (productPriceIsZero($el.val())) {
            $el.val('');
        }
    });
    
    $(document).on('blur', '.product-price', function() {
        const $el = $(this);
        let v = $el.val();
        if (v === '' || v === null || String(v).trim() === '') {
            $el.val('0.00');
            return;
        }
        const p = parseFloat(String(v).replace(/,/g, ''));
        if (!isNaN(p)) {
            $el.val(p.toFixed(2));
        }
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
        $newLine.find('.product-price').val('0.00');
        $newLine.find('.product-search-results').empty().hide();
        $newLine.find('.search-spinner').removeClass('show');
        
        $('#product-lines-container').append($newLine);
    });
    
    // Remove product line (at least one line kept for adding products)
    $('#remove-product-line').on('click', function() {
        const $lines = $('.product-line');
        if ($lines.length > 1) {
            $lines.last().remove();
            productLineCounter--;
        }
    });
    
    // Save customer
    function saveCustomer() {
        const customerData = {
            action: 'wc_oe_save_customer',
            customer_id: $('#saved-customers').val() || 0,
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
            retailer_id: $('#order-retailer').val() || 0,
            shipping_amount: $('#shipping-amount').val() || 0,
            package_weight: getCombinedPackageWeight() || 0,
            package_length: $('#pkg-length').val() || 0,
            package_width: $('#pkg-width').val() || 0,
            package_height: $('#pkg-height').val() || 0,
            nonce: wc_oe_ajax.nonce
        };
        
        return $.post(wc_oe_ajax.ajax_url, customerData);
    }

    $('#update-saved-customer').on('click', function() {
        const selectedId = $('#saved-customers').val();
        const $msg = $('#saved-customer-result');
        if (!selectedId) {
            $msg.removeClass('success').addClass('error').text('Select a saved customer first.').show();
            return;
        }
        saveCustomer().done(function(response) {
            if (!response || !response.success || !response.data) {
                $msg.removeClass('success').addClass('error').text((response && response.data) ? response.data : 'Failed to update customer.').show();
                return;
            }
            const label = customerOptionLabel(response.data.name, response.data.email);
            $('#saved-customers option[value="' + selectedId + '"]').text(label);
            $msg.removeClass('error').addClass('success').text(response.data.message || 'Customer updated.').show();
        }).fail(function() {
            $msg.removeClass('success').addClass('error').text('Network error while updating customer.').show();
        });
    });

    $('#delete-saved-customer').on('click', function() {
        const selectedId = $('#saved-customers').val();
        const $msg = $('#saved-customer-result');
        if (!selectedId) {
            $msg.removeClass('success').addClass('error').text('Select a saved customer first.').show();
            return;
        }
        if (!confirm('Delete this saved customer?')) return;
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_delete_customer',
            customer_id: selectedId,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            if (!response.success) {
                $msg.removeClass('success').addClass('error').text((response && response.data) ? response.data : 'Failed to delete customer.').show();
                return;
            }
            $('#saved-customers option[value="' + selectedId + '"]').remove();
            $('#saved-customers').val('');
            $msg.removeClass('error').addClass('success').text('Customer deleted.').show();
        }).fail(function() {
            $msg.removeClass('success').addClass('error').text('Network error while deleting customer.').show();
        });
    });
    
    // Submit order
    $('#submit-order').on('click', function() {
        const $button = $(this);
        const $result = $('#order-result');
        
        // Validate required fields (only name is required)
        if (!$('#customer-name').val().trim()) {
            $result.removeClass('success').addClass('error').text('Please enter customer name.').show();
            return;
        }
        
        // Collect product lines that have all fields filled (products section is optional)
        const productLines = [];
        let validLines = true;
        
        $('.product-line').each(function() {
            const quantity = $(this).find('.product-quantity').val();
            const productId = $(this).find('.product-id').val();
            const description = $(this).find('.product-description').val();
            const price = $(this).find('.product-price').val();
            const hasProduct = productId || (description && description.trim());
            const hasAny = quantity || productId || description || price;
            const hasAll = quantity && hasProduct && price;
            
            if (hasAny && !hasAll) {
                validLines = false;
                return false;
            }
            if (hasAll) {
                productLines.push({
                    quantity: quantity,
                    product_id: productId,
                    description: description ? description.trim() : '',
                    price: price
                });
            }
        });
        
        if (!validLines) {
            $result.removeClass('success').addClass('error').text('Please complete any product line you started (quantity, product, and price).').show();
            return;
        }
        
        const retailerId = $('#order-retailer').val();
        const shippingRaw = $('#shipping-amount').val();
        const shippingStr = shippingRaw === undefined || shippingRaw === null ? '' : String(shippingRaw).trim();
        if (retailerId) {
            if (shippingStr === '') {
                $result.removeClass('success').addClass('error').text('Shipping amount is required when a retailer is selected.').show();
                return;
            }
            const shippingNum = parseFloat(shippingStr);
            if (isNaN(shippingNum)) {
                $result.removeClass('success').addClass('error').text('Enter a valid shipping amount.').show();
                return;
            }
            if (shippingNum < 0) {
                $result.removeClass('success').addClass('error').text('Shipping amount cannot be negative.').show();
                return;
            }
        }
        
        // Server validates PO; clear any stale blur warning so it cannot block submit.
        $('#wc-oe-po-dup-msg').hide().text('');
        
        $button.prop('disabled', true).text('Creating Order...');
        $result.hide();
        
        const shouldSaveCustomer = $('#save-customer').is(':checked');
        let savedCustomerData = null;
        // Save customer if checkbox is checked
        const saveCustomerPromise = shouldSaveCustomer ? saveCustomer() : $.Deferred().resolve();
        
        saveCustomerPromise.done(function(saveResponse) {
            if (saveResponse && saveResponse.success && saveResponse.data) {
                savedCustomerData = saveResponse.data;
            }
        }).always(function() {
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
                shipping_amount: $('#shipping-amount').val() || 0,
                retailer_id: $('#order-retailer').val() || 0,
                shipping_quote_id: selectedLiveRate ? (selectedLiveRate.id || '') : '',
                shipping_quote_carrier: selectedLiveRate ? (selectedLiveRate.carrier || '') : '',
                shipping_quote_service: selectedLiveRate ? (selectedLiveRate.service || '') : '',
                shipping_quote_amount: selectedLiveRate ? (selectedLiveRate.adjusted_amount || selectedLiveRate.amount || 0) : 0,
                package_weight: getCombinedPackageWeight() || 0,
                package_length: $('#pkg-length').val() || 0,
                package_width: $('#pkg-width').val() || 0,
                package_height: $('#pkg-height').val() || 0,
                nonce: wc_oe_ajax.nonce
            };
            
            $.post(wc_oe_ajax.ajax_url, orderData, function(response) {
                $button.prop('disabled', false).text('Create Order');
                
                if (response.success) {
                    if (savedCustomerData) {
                        const customerId = String(savedCustomerData.id);
                        const label = customerOptionLabel(savedCustomerData.name, savedCustomerData.email);
                        const $existing = $('#saved-customers option[value="' + customerId + '"]');
                        if ($existing.length) {
                            $existing.text(label);
                        } else {
                            $('#saved-customers').append($('<option></option>').val(customerId).text(label));
                        }
                    }
                    const msg = 'Order created successfully! <a href="' + response.data.order_url + '" class="order-link" target="_blank">View Order #' + response.data.order_id + '</a>';
                    if (shouldSaveCustomer && !savedCustomerData) {
                        // Keep order creation successful but warn that customer profile was not saved.
                        $result.removeClass('error').addClass('success').html(msg + '<br><span class="email-error">Order was created, but saved customer update failed.</span>').show();
                    } else {
                        $result.removeClass('error').addClass('success').html(msg).show();
                    }
                    
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
    
    // New Order Entry button - reset form and scroll to top
    $('#wc-oe-new-order-btn').on('click', function() {
        resetForm();
        $('#order-result').hide();
        $('html, body').animate({ scrollTop: 0 }, 200);
    });
    
    function resetForm() {
        // Reset customer form
        $('#customer-name, #customer-email, #customer-phone, #customer-address1, #customer-address2, #customer-city, #customer-zip, #customer-po').val('');
        $('#customer-country').val('US'); // Reset to US default
        setCustomerState('');
        $('#save-customer').prop('checked', false);
        $('#paste-customer').val('');
        $('#saved-customers').val('');
        $('#saved-customer-result').hide().removeClass('success error').text('');
        $('#wc-oe-po-dup-msg').hide().text('');
        $('#pkg-weight-lbs, #pkg-weight-oz, #pkg-length, #pkg-width, #pkg-height').val('');
        clearLiveRatesSelection();
        setLiveRatesMessage('', false);

        // Reset product lines
        $('.product-line:not(:first)').remove();
        $('.product-line:first input').val('');
        $('.product-line:first .product-quantity').val('1');
        $('.product-line:first .product-price').val('0.00');
        $('.product-search-results').empty().hide();
        $('.search-spinner').removeClass('show');
        productLineCounter = 1;
        
        // Reset shipping and retailer
        $('#shipping-amount').val('0');
        $('#order-retailer').val('');
    }
    
    // Add retailer
    $('#add-retailer').on('click', function() {
        const name = $('#new-retailer-name').val().trim();
        const email = $('#new-retailer-email').val().trim();
        if (!name || !email) {
            alert('Please enter retailer name and email.');
            return;
        }
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_save_retailer',
            name: name,
            email: email,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            if (response.success) {
                const $item = $('<div class="wc-oe-retailer-item" data-id="' + response.data.id + '"></div>');
                $item.append($('<span></span>').text(name + ' (' + email + ')'));
                $item.append($('<button type="button" class="button-link-delete delete-retailer">Delete</button>'));
                $('.wc-oe-retailers-list .wc-oe-no-retailers').remove();
                $('.wc-oe-retailers-list').append($item);
                $('#new-retailer-name, #new-retailer-email').val('');
            } else {
                alert(response.data || 'Failed to add retailer.');
            }
        });
    });
    
    // Send test invoice
    $('#send-test-invoice').on('click', function() {
        const email = $('#test-invoice-email').val().trim();
        const $btn = $(this);
        const $result = $('#test-invoice-result');
        if (!email) {
            $result.removeClass('success').addClass('error').text('Please enter an email address.').show();
            return;
        }
        $btn.prop('disabled', true);
        $result.hide();
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_send_test_invoice',
            email: email,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                $result.removeClass('error').addClass('success').text(response.data).show();
            } else {
                $result.removeClass('success').addClass('error').text(response.data || 'Failed to send.').show();
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $result.removeClass('success').addClass('error').text('Network error.').show();
        });
    });
    
    // Delete retailer
    function refreshShippingRequirementHint() {
        const show = !!$('#order-retailer').val();
        $('.wc-oe-shipping-req-hint').toggle(show);
    }
    $('#order-retailer').on('change', refreshShippingRequirementHint);
    refreshShippingRequirementHint();
    
    $(document).on('click', '.delete-retailer', function() {
        const $item = $(this).closest('.wc-oe-retailer-item');
        const id = $item.data('id');
        if (!confirm('Delete this retailer?')) return;
        $.post(wc_oe_ajax.ajax_url, {
            action: 'wc_oe_delete_retailer',
            retailer_id: id,
            nonce: wc_oe_ajax.nonce
        }, function(response) {
            if (response.success) {
                $item.remove();
                if ($('.wc-oe-retailer-item').length === 0) {
                    $('.wc-oe-retailers-list').append('<p class="wc-oe-no-retailers">No retailers yet. Add one above.</p>');
                }
            } else {
                alert(response.data || 'Failed to delete retailer.');
            }
        });
    });
});
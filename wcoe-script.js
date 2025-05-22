jQuery(document).ready(function($) {
    // Helper function to check if a string is an email
    function isEmail(str) {
        return /\S+@\S+\.\S+/.test(str);
    }

    // Helper function to check if a string is a phone number
    function isPhone(str) {
        var digits = str.replace(/\D/g, '');
        return digits.length === 10;
    }

    // Handle automatic paste parsing
    $('#paste_info').on('paste', function() {
        setTimeout(function() {
            var paste = $('#paste_info').val().trim();
            var lines = paste.split('\n').map(line => line.trim()).filter(line => line !== '');

            if (lines.length >= 3) {
                var name = lines[0];
                var address = lines[1];
                var address2 = '';
                var cityStateZipIndex = 2;

                if (!lines[2].includes(',')) {
                    address2 = lines[2];
                    cityStateZipIndex = 3;
                }

                var cityStateZip = lines[cityStateZipIndex] || '';
                var cszParts = cityStateZip.split(',');
                if (cszParts.length === 2) {
                    var city = cszParts[0].trim();
                    var stateZip = cszParts[1].trim().split(' ');
                    var state = stateZip[0];
                    var zip = stateZip.slice(1).join(' ');

                    $('#name').val(name);
                    $('#address').val(address);
                    $('#address2').val(address2);
                    $('#city').val(city);
                    $('#state').val(state);
                    $('#zip').val(zip);

                    var email = '';
                    var phone = '';
                    for (var i = cityStateZipIndex + 1; i < lines.length; i++) {
                        var line = lines[i];
                        if (!email && isEmail(line)) {
                            email = line;
                        } else if (!phone && isPhone(line)) {
                            phone = line;
                        }
                    }

                    if (email) $('#email').val(email);
                    if (phone) $('#phone').val(phone);
                }
            }
        }, 100);
    });

    // Handle customer selection
    $('#customer_select').change(function() {
        var customer_id = $(this).val();
        if (customer_id) {
            var nonce = $('#wcoe_ajax_nonce').val();
            $.post(ajaxurl, {
                action: 'wcoe_get_customer_details',
                customer_id: customer_id,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#name').val(data.name);
                    $('#address').val(data.address_1);
                    $('#address2').val(data.address_2);
                    $('#city').val(data.city);
                    $('#state').val(data.state);
                    $('#zip').val(data.zip);
                    $('#country').val(data.country || 'US');
                    $('#email').val(data.email);
                    $('#phone').val(data.phone);
                }
            });
        }
    });

    // Product lines functionality
    var lineIndex = 1;

    function calculateTotal() {
        var total = 0;
        $('.product-line').each(function() {
            var quantity = parseInt($(this).find('input[name$="[quantity]"]').val()) || 0;
            var price = parseFloat($(this).find('input[name$="[price]"]').val()) || 0;
            total += quantity * price;
        });
        var shippingCost = parseFloat($('#shipping_cost').val()) || 0;
        total += shippingCost;
        $('#total-price').text(total.toFixed(2));
    }

    calculateTotal();

    $(document).on('change', 'input[name$="[quantity]"], input[name$="[price]"]', function() {
        calculateTotal();
    });

    $('#shipping_cost').on('input', function() {
        calculateTotal();
    });

    $('#add-product-line').click(function() {
        var newRow = $('.product-line:first').clone();
        newRow.find('input').each(function() {
            var name = $(this).attr('name').replace('[0]', '[' + lineIndex + ']');
            $(this).attr('name', name);
        });
        newRow.find('input[name$="[quantity]"]').val(1);
        newRow.find('input[name$="[sku]"]').val('');
        newRow.find('input[name$="[description]"]').val('');
        newRow.find('input[name$="[price]"]').val('');
        $('#product-lines-table tbody').append(newRow);
        lineIndex++;
    });

    $('#remove-product-line').click(function() {
        var rows = $('.product-line');
        if (rows.length > 1) {
            rows.last().remove();
            calculateTotal();
        }
    });
});

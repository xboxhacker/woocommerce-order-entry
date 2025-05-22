<?php
/*
Plugin Name: WooCommerce Order Entry
Description: A plugin to manually enter orders in WooCommerce with customer management, automatic paste parsing, and dynamic product lines.
Version: 1.11
Author: William Hare & Grok3.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register custom post type for customers
function wcoe_register_customer_post_type() {
    register_post_type('wcoe_customer', array(
        'labels' => array(
            'name' => __('Customers', 'woocommerce-order-entry'),
            'singular_name' => __('Customer', 'woocommerce-order-entry'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'woocommerce',
        'supports' => array('title'),
    ));
}
add_action('init', 'wcoe_register_customer_post_type');

// Add admin menu page
function wcoe_add_admin_menu() {
    add_submenu_page(
        'woocommerce',
        'Order Entry',
        'Order Entry',
        'manage_woocommerce',
        'wcoe-order-entry',
        'wcoe_order_entry_page'
    );
}
add_action('admin_menu', 'wcoe_add_admin_menu');

// Enqueue scripts and styles
function wcoe_enqueue_scripts($hook) {
    if ($hook !== 'woocommerce_page_wcoe-order-entry') {
        return;
    }
    wp_enqueue_script('wcoe-script', plugins_url('/wcoe-script.js', __FILE__), array('jquery'), '1.11', true);
    wp_enqueue_style('wcoe-style', plugins_url('/wcoe-style.css', __FILE__), array(), '1.11');
}
add_action('admin_enqueue_scripts', 'wcoe_enqueue_scripts');

// Get or create generic product
function wcoe_get_generic_product_id() {
    $product = get_posts(array(
        'post_type' => 'product',
        'meta_key' => '_is_generic_product',
        'meta_value' => 'yes',
        'numberposts' => 1,
    ));
    if (!empty($product)) {
        return $product[0]->ID;
    } else {
        $product_id = wp_insert_post(array(
            'post_type' => 'product',
            'post_title' => 'Generic Product',
            'post_status' => 'publish',
        ));
        if ($product_id) {
            wp_set_object_terms($product_id, 'simple', 'product_type');
            update_post_meta($product_id, '_price', 0);
            update_post_meta($product_id, '_visibility', 'hidden');
            update_post_meta($product_id, '_is_generic_product', 'yes');
            return $product_id;
        }
        return 0;
    }
}

// AJAX handler for customer details
add_action('wp_ajax_wcoe_get_customer_details', 'wcoe_ajax_get_customer_details');
function wcoe_ajax_get_customer_details() {
    check_ajax_referer('wcoe_ajax_nonce', 'nonce');
    $customer_id = intval($_POST['customer_id']);
    $customer = get_post($customer_id);
    if ($customer && $customer->post_type == 'wcoe_customer') {
        $data = array(
            'name' => $customer->post_title,
            'address_1' => get_post_meta($customer_id, '_address_1', true),
            'address_2' => get_post_meta($customer_id, '_address_2', true),
            'city' => get_post_meta($customer_id, '_city', true),
            'state' => get_post_meta($customer_id, '_state', true),
            'zip' => get_post_meta($customer_id, '_zip', true),
            'email' => get_post_meta($customer_id, '_email', true),
            'phone' => get_post_meta($customer_id, '_phone', true),
        );
        wp_send_json_success($data);
    } else {
        wp_send_json_error();
    }
}

// Render the order entry page
function wcoe_order_entry_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('You do not have permission to access this page.');
    }

    $message = '';
    if (isset($_POST['submit_order'])) {
        $name = sanitize_text_field($_POST['name']);
        $address = sanitize_text_field($_POST['address']);
        $address2 = sanitize_text_field($_POST['address2']);
        $city = sanitize_text_field($_POST['city']);
        $state = sanitize_text_field($_POST['state']);
        $zip = sanitize_text_field($_POST['zip']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $po_number = sanitize_text_field($_POST['po_number']);
        $shipping_method = sanitize_text_field($_POST['shipping_method']);
        $shipping_cost = isset($_POST['shipping_cost']) ? floatval($_POST['shipping_cost']) : 0;

        // Save customer if checkbox is checked
        if (isset($_POST['save_customer']) && $_POST['save_customer'] == 'on') {
            $address_hash = md5($address . $address2 . $city . $state . $zip);
            $existing = get_posts(array(
                'post_type' => 'wcoe_customer',
                'meta_key' => '_address_hash',
                'meta_value' => $address_hash,
                'numberposts' => 1,
            ));
            if (empty($existing)) {
                $customer_id = wp_insert_post(array(
                    'post_type' => 'wcoe_customer',
                    'post_title' => $name,
                    'post_status' => 'publish',
                ));
                if ($customer_id) {
                    update_post_meta($customer_id, '_address_1', $address);
                    update_post_meta($customer_id, '_address_2', $address2);
                    update_post_meta($customer_id, '_city', $city);
                    update_post_meta($customer_id, '_state', $state);
                    update_post_meta($customer_id, '_zip', $zip);
                    update_post_meta($customer_id, '_email', $email);
                    update_post_meta($customer_id, '_phone', $phone);
                    update_post_meta($customer_id, '_address_hash', $address_hash);
                }
            }
        }

        // Create the order
        $order = wc_create_order();
        $order->set_billing_first_name($name);
        $order->set_billing_address_1($address);
        $order->set_billing_address_2($address2);
        $order->set_billing_city($city);
        $order->set_billing_state($state);
        $order->set_billing_postcode($zip);
        $order->set_billing_email($email);
        $order->set_billing_phone($phone);
        $order->set_shipping_first_name($name);
        $order->set_shipping_address_1($address);
        $order->set_shipping_address_2($address2);
        $order->set_shipping_city($city);
        $order->set_shipping_state($state);
        $order->set_shipping_postcode($zip);

        // Add PO number if provided
        if (!empty($po_number)) {
            $order->update_meta_data('_po_number', $po_number);
        }

        // Add product lines
        $generic_product = wc_get_product(wcoe_get_generic_product_id());
        if (isset($_POST['product_lines']) && is_array($_POST['product_lines'])) {
            foreach ($_POST['product_lines'] as $line) {
                $quantity = isset($line['quantity']) ? intval($line['quantity']) : 1;
                $sku = isset($line['sku']) ? sanitize_text_field($line['sku']) : '';
                $description = !empty($line['description']) ? sanitize_text_field($line['description']) : 'Generic Item';
                $price = isset($line['price']) ? floatval($line['price']) : 0;

                $item_id = $order->add_product($generic_product, $quantity, array(
                    'subtotal' => $price * $quantity,
                    'total' => $price * $quantity,
                ));

                if ($item_id) {
                    $item = $order->get_item($item_id);
                    if ($item instanceof WC_Order_Item_Product) {
                        $item->set_name($description);
                        if (!empty($sku)) {
                            $item->add_meta_data('SKU', $sku, true);
                        }
                        $item->save();
                    }
                }
            }
        }

        // Add shipping
        $shipping_item = new WC_Order_Item_Shipping();
        $shipping_item->set_method_id($shipping_method);
        $shipping_item->set_method_title($shipping_method);
        $shipping_item->set_total($shipping_cost);
        $order->add_item($shipping_item);

        $order->calculate_totals();
        $order->set_status('processing');
        $order->save();

        $message = sprintf('Order #%d created successfully!', $order->get_id());
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Order Entry', 'woocommerce-order-entry'); ?></h1>
        <?php if ($message) : ?>
            <div class="notice notice-success"><p><?php echo $message; ?></p></div>
        <?php endif; ?>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th><label for="paste_info">Paste Customer Info</label></th>
                    <td>
                        <textarea id="paste_info" rows="6" cols="50" placeholder="Paste customer info here. Example:
John Doe
123 Main St
Apt 4B
Springfield, IL 62701
john.doe@example.com
555-123-4567"></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="customer_select"><?php _e('Select Customer', 'woocommerce-order-entry'); ?></label></th>
                    <td>
                        <select id="customer_select" name="customer_select">
                            <option value=""><?php _e('Select a customer', 'woocommerce-order-entry'); ?></option>
                            <?php
                            $customers = get_posts(array(
                                'post_type' => 'wcoe_customer',
                                'numberposts' => -1,
                            ));
                            foreach ($customers as $customer) {
                                echo '<option value="' . $customer->ID . '">' . esc_html($customer->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="name"><?php _e('Name', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="text" id="name" name="name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="address"><?php _e('Address', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="text" id="address" name="address" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="address2"><?php _e('Address 2', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="text" id="address2" name="address2" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="city"><?php _e('City', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="text" id="city" name="city" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="state"><?php _e('State', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="text" id="state" name="state" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="zip"><?php _e('Zip', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="text" id="zip" name="zip" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="email"><?php _e('Email', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="email" id="email" name="email" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="phone"><?php _e('Phone Number', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="tel" id="phone" name="phone" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="po_number"><?php _e('PO Number', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="text" id="po_number" name="po_number" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="save_customer"><?php _e('Save this customer', 'woocommerce-order-entry'); ?></label></th>
                    <td><input type="checkbox" id="save_customer" name="save_customer"></td>
                </tr>
                <tr>
                    <th><?php _e('Shipping Method', 'woocommerce-order-entry'); ?></th>
                    <td>
                        <label><input type="radio" name="shipping_method" value="UPS" checked> UPS</label>
                        <label><input type="radio" name="shipping_method" value="USPS"> USPS</label>
                    </td>
                </tr>
            </table>

            <h2>Product Lines</h2>
            <table id="product-lines-table">
                <thead>
                    <tr>
                        <th>QTY</th>
                        <th>SKU</th>
                        <th>DESCRIPTION</th>
                        <th>PRICE</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="product-line">
                        <td><input type="number" name="product_lines[0][quantity]" min="1" value="1" required></td>
                        <td><input type="text" name="product_lines[0][sku]"></td>
                        <td><input type="text" name="product_lines[0][description]"></td>
                        <td><input type="number" name="product_lines[0][price]" step="0.01" required></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" id="add-product-line">Add More</button>
            <button type="button" id="remove-product-line">Remove Last Line</button>
            <p>
                <label for="shipping_cost">Shipping Cost</label>
                <input type="number" id="shipping_cost" name="shipping_cost" step="0.01" value="0.00">
            </p>
            <p><strong>Total Price:</strong> <span id="total-price">0.00</span></p>

            <input type="hidden" id="wcoe_ajax_nonce" value="<?php echo wp_create_nonce('wcoe_ajax_nonce'); ?>">
            <?php submit_button('Submit Order', 'primary', 'submit_order'); ?>
        </form>
    </div>
    <?php
}
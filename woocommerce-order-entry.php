<?php
/**
 * Plugin Name: WooCommerce Order Entry
 * Plugin URI: https://github.com/xboxhacker/woocommerce-order-entry
 * Description: A plugin to manually create WooCommerce orders with customer information and product selection.
 * Version: 1.1.4
 * Author: xboxhacker
 * License: GPL v2 or later
 * Text Domain: wc-order-entry
 * Requires at least: 5.0
 * Tested up to: 6.3
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_OE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_OE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_OE_VERSION', '1.1.4');

class WC_Order_Entry {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin functionality
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wc_oe_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_wc_oe_save_customer', array($this, 'ajax_save_customer'));
        add_action('wp_ajax_wc_oe_load_customer', array($this, 'ajax_load_customer'));
        add_action('wp_ajax_wc_oe_parse_customer', array($this, 'ajax_parse_customer'));
        add_action('wp_ajax_wc_oe_create_order', array($this, 'ajax_create_order'));
        
        // Add PO number display and search functionality
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_po_number_in_order'));
        add_filter('woocommerce_shop_order_search_fields', array($this, 'add_po_number_to_search'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_po_number_in_order_list'), 10, 2);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_po_number_column'));
        
        // Create database table for saved customers
        $this->create_customers_table();
    }
    
    public function activate() {
        // Activation tasks
        $this->create_customers_table();
    }
    
    public function deactivate() {
        // Deactivation tasks
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>WooCommerce Order Entry</strong> requires WooCommerce to be installed and active.</p></div>';
    }
    
    public function create_customers_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_oe_customers';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50),
            address_1 varchar(255),
            address_2 varchar(255),
            city varchar(100),
            state varchar(100),
            postcode varchar(20),
            country varchar(5) DEFAULT 'US',
            po_number varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Order Entry',
            'Order Entry',
            'manage_woocommerce',
            'wc-order-entry',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'woocommerce_page_wc-order-entry') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('wc-oe-admin', WC_OE_PLUGIN_URL . 'assets/admin.js', array('jquery'), WC_OE_VERSION, true);
        wp_enqueue_style('wc-oe-admin', WC_OE_PLUGIN_URL . 'assets/admin.css', array(), WC_OE_VERSION);
        
        wp_localize_script('wc-oe-admin', 'wc_oe_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_oe_nonce')
        ));
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Get saved customers
        $table_name = $wpdb->prefix . 'wc_oe_customers';
        $customers = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
        
        include WC_OE_PLUGIN_PATH . 'templates/admin-page.php';
    }
    
    // Display PO number in order details page
    public function display_po_number_in_order($order) {
        $po_number = $order->get_meta('_po_number');
        if (!empty($po_number)) {
            echo '<div class="address">';
            echo '<p><strong>' . __('PO Number:', 'wc-order-entry') . '</strong> ' . esc_html($po_number) . '</p>';
            echo '</div>';
        }
    }
    
    // Add PO number to order search fields
    public function add_po_number_to_search($search_fields) {
        $search_fields[] = '_po_number';
        return $search_fields;
    }
    
    // Add PO number column to orders list
    public function add_po_number_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_total') {
                $new_columns['po_number'] = __('PO Number', 'wc-order-entry');
            }
        }
        return $new_columns;
    }
    
    // Display PO number in orders list
    public function display_po_number_in_order_list($column, $post_id) {
        if ($column === 'po_number') {
            $order = wc_get_order($post_id);
            if ($order) {
                $po_number = $order->get_meta('_po_number');
                echo !empty($po_number) ? esc_html($po_number) : '—';
            }
        }
    }
    
    public function ajax_search_products() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        $search_term = sanitize_text_field($_POST['search_term']);
        $results = array();
        
        // Search for simple products and parent products
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            's' => $search_term,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_sku',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_sku',
                    'value' => '',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $products = get_posts($args);
        
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if ($wc_product && $wc_product->get_sku()) {
                $results[] = array(
                    'id' => $product->ID,
                    'sku' => $wc_product->get_sku(),
                    'name' => $product->post_title,
                    'price' => $wc_product->get_price()
                );
            }
        }
        
        // Search for product variations
        $variation_args = array(
            'post_type' => 'product_variation',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'meta_query' => array(
                array(
                    'key' => '_sku',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                )
            )
        );
        
        $variations = get_posts($variation_args);
        
        foreach ($variations as $variation) {
            $wc_variation = wc_get_product($variation->ID);
            if ($wc_variation && $wc_variation->get_sku()) {
                $parent_product = wc_get_product($wc_variation->get_parent_id());
                $variation_name = $parent_product ? $parent_product->get_name() : 'Product';
                
                // Get variation attributes for display
                $attributes = $wc_variation->get_variation_attributes();
                $attribute_names = array();
                foreach ($attributes as $attr_key => $attr_value) {
                    if (!empty($attr_value)) {
                        $attribute_names[] = $attr_value;
                    }
                }
                
                if (!empty($attribute_names)) {
                    $variation_name .= ' - ' . implode(', ', $attribute_names);
                }
                
                $results[] = array(
                    'id' => $variation->ID,
                    'sku' => $wc_variation->get_sku(),
                    'name' => $variation_name,
                    'price' => $wc_variation->get_price()
                );
            }
        }
        
        // Also search by product name/title if no SKU matches found
        if (empty($results)) {
            $name_args = array(
                'post_type' => array('product', 'product_variation'),
                'post_status' => 'publish',
                'posts_per_page' => 10,
                's' => $search_term
            );
            
            $name_products = get_posts($name_args);
            
            foreach ($name_products as $product) {
                $wc_product = wc_get_product($product->ID);
                if ($wc_product) {
                    $product_name = $product->post_title;
                    
                    // Handle variations
                    if ($product->post_type === 'product_variation') {
                        $parent_product = wc_get_product($wc_product->get_parent_id());
                        if ($parent_product) {
                            $product_name = $parent_product->get_name();
                            $attributes = $wc_product->get_variation_attributes();
                            $attribute_names = array();
                            foreach ($attributes as $attr_key => $attr_value) {
                                if (!empty($attr_value)) {
                                    $attribute_names[] = $attr_value;
                                }
                            }
                            if (!empty($attribute_names)) {
                                $product_name .= ' - ' . implode(', ', $attribute_names);
                            }
                        }
                    }
                    
                    $results[] = array(
                        'id' => $product->ID,
                        'sku' => $wc_product->get_sku() ?: 'No SKU',
                        'name' => $product_name,
                        'price' => $wc_product->get_price()
                    );
                }
            }
        }
        
        // Remove duplicates and limit results
        $unique_results = array();
        $seen_ids = array();
        
        foreach ($results as $result) {
            if (!in_array($result['id'], $seen_ids)) {
                $unique_results[] = $result;
                $seen_ids[] = $result['id'];
            }
        }
        
        // Limit to 10 results
        $unique_results = array_slice($unique_results, 0, 10);
        
        wp_send_json_success($unique_results);
    }
    
    public function ajax_save_customer() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_oe_customers';
        
        $customer_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'address_1' => sanitize_text_field($_POST['address_1']),
            'address_2' => sanitize_text_field($_POST['address_2']),
            'city' => sanitize_text_field($_POST['city']),
            'state' => sanitize_text_field($_POST['state']),
            'postcode' => sanitize_text_field($_POST['postcode']),
            'country' => sanitize_text_field($_POST['country']),
            'po_number' => sanitize_text_field($_POST['po_number'])
        );
        
        $result = $wpdb->insert($table_name, $customer_data);
        
        if ($result) {
            wp_send_json_success('Customer saved successfully');
        } else {
            wp_send_json_error('Failed to save customer');
        }
    }
    
    public function ajax_load_customer() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_oe_customers';
        
        $customer_id = intval($_POST['customer_id']);
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $customer_id));
        
        if ($customer) {
            wp_send_json_success($customer);
        } else {
            wp_send_json_error('Customer not found');
        }
    }
    
    public function ajax_parse_customer() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        $customer_text = sanitize_textarea_field($_POST['customer_text']);
        
        // Enhanced parsing logic for formatted addresses
        $lines = array_filter(array_map('trim', explode("\n", $customer_text)));
        $parsed_data = array();
        
        foreach ($lines as $index => $line) {
            if (empty($line)) continue;
            
            // Email detection
            if (filter_var($line, FILTER_VALIDATE_EMAIL)) {
                $parsed_data['email'] = $line;
            }
            // Phone detection (enhanced pattern)
            elseif (preg_match('/^[\(\)\d\s\-\.\+]{10,}$/', $line)) {
                $parsed_data['phone'] = $line;
            }
            // City, State Zip pattern (e.g., "City, KS 66219" or "City, ON K1A 0A9")
            elseif (preg_match('/^([^,]+),\s*([A-Z]{2})\s+([A-Z0-9\s]+)$/', $line, $matches)) {
                $parsed_data['city'] = trim($matches[1]);
                $parsed_data['state'] = $matches[2];
                $parsed_data['postcode'] = trim($matches[3]);
                
                // Determine country based on postal code format
                if (preg_match('/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/', trim($matches[3]))) {
                    // Canadian postal code format
                    $parsed_data['country'] = 'CA';
                } else {
                    // US zip code format
                    $parsed_data['country'] = 'US';
                }
            }
            // Street address pattern (contains numbers and common street indicators)
            elseif (preg_match('/\d+.*\b(street|st|avenue|ave|road|rd|lane|ln|drive|dr|court|ct|circle|cir|boulevard|blvd|way|place|pl)\.?/i', $line)) {
                if (!isset($parsed_data['address_1'])) {
                    $parsed_data['address_1'] = $line;
                } else {
                    $parsed_data['address_2'] = $line;
                }
            }
            // First line is typically the name
            elseif ($index === 0 && !isset($parsed_data['name'])) {
                $parsed_data['name'] = $line;
            }
            // Standalone zip code (US format)
            elseif (preg_match('/^\d{5}(-\d{4})?$/', $line)) {
                $parsed_data['postcode'] = $line;
                $parsed_data['country'] = 'US';
            }
            // Standalone postal code (Canadian format)
            elseif (preg_match('/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/', $line)) {
                $parsed_data['postcode'] = $line;
                $parsed_data['country'] = 'CA';
            }
            // If no specific pattern matches and we don't have a city yet, treat as city
            elseif (!isset($parsed_data['city']) && !preg_match('/\d/', $line)) {
                $parsed_data['city'] = $line;
            }
        }
        
        wp_send_json_success($parsed_data);
    }
    
    public function ajax_create_order() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        try {
            // Create new WooCommerce order
            $order = wc_create_order();
            
            // Set billing information
            $order->set_billing_first_name(sanitize_text_field($_POST['name']));
            $order->set_billing_email(sanitize_email($_POST['email']));
            $order->set_billing_phone(sanitize_text_field($_POST['phone']));
            $order->set_billing_address_1(sanitize_text_field($_POST['address_1']));
            $order->set_billing_address_2(sanitize_text_field($_POST['address_2']));
            $order->set_billing_city(sanitize_text_field($_POST['city']));
            $order->set_billing_state(sanitize_text_field($_POST['state']));
            $order->set_billing_postcode(sanitize_text_field($_POST['postcode']));
            $order->set_billing_country(sanitize_text_field($_POST['country']));
            
            // Set shipping same as billing
            $order->set_shipping_first_name(sanitize_text_field($_POST['name']));
            $order->set_shipping_address_1(sanitize_text_field($_POST['address_1']));
            $order->set_shipping_address_2(sanitize_text_field($_POST['address_2']));
            $order->set_shipping_city(sanitize_text_field($_POST['city']));
            $order->set_shipping_state(sanitize_text_field($_POST['state']));
            $order->set_shipping_postcode(sanitize_text_field($_POST['postcode']));
            $order->set_shipping_country(sanitize_text_field($_POST['country']));
            
            // Add products
            $product_lines = $_POST['product_lines'];
            foreach ($product_lines as $line) {
                $product_id = intval($line['product_id']);
                $quantity = intval($line['quantity']);
                $price = floatval($line['price']);
                
                $product = wc_get_product($product_id);
                if ($product) {
                    $item = new WC_Order_Item_Product();
                    $item->set_product($product);
                    $item->set_quantity($quantity);
                    $item->set_subtotal($price * $quantity);
                    $item->set_total($price * $quantity);
                    $order->add_item($item);
                }
            }
            
            // Add PO number as meta with underscore prefix for searchability
            if (!empty($_POST['po_number'])) {
                $po_number = sanitize_text_field($_POST['po_number']);
                $order->add_meta_data('_po_number', $po_number);
                $order->add_meta_data('PO Number', $po_number); // For display purposes
            }
            
            // Calculate totals and set status
            $order->calculate_totals();
            $order->set_status('processing');
            $order->save();
            
            wp_send_json_success(array(
                'order_id' => $order->get_id(),
                'order_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to create order: ' . $e->getMessage());
        }
    }
}

// Initialize the plugin
new WC_Order_Entry();
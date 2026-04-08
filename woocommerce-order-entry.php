<?php
/**
 * Plugin Name: WooCommerce Order Entry
 * Plugin URI: https://github.com/xboxhacker/woocommerce-order-entry
 * Description: A plugin to manually create WooCommerce orders with customer information and product selection.
 * Version: 1.4.5
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
define('WC_OE_VERSION', '1.4.5');

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
        add_action('wp_ajax_wc_oe_delete_customer', array($this, 'ajax_delete_customer'));
        add_action('wp_ajax_wc_oe_parse_customer', array($this, 'ajax_parse_customer'));
        add_action('wp_ajax_wc_oe_create_order', array($this, 'ajax_create_order'));
        add_action('wp_ajax_wc_oe_check_po_duplicate', array($this, 'ajax_check_po_duplicate'));
        add_action('wp_ajax_wc_oe_get_live_rates', array($this, 'ajax_get_live_rates'));
        add_action('wp_ajax_wc_oe_save_retailer', array($this, 'ajax_save_retailer'));
        add_action('wp_ajax_wc_oe_delete_retailer', array($this, 'ajax_delete_retailer'));
        add_action('wp_ajax_wc_oe_send_invoice', array($this, 'ajax_send_invoice'));
        add_action('wp_ajax_wc_oe_send_test_invoice', array($this, 'ajax_send_test_invoice'));
        
        // Add PO number display and search functionality
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_po_number_in_order'));
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_send_invoice_button'));
        add_filter('woocommerce_shop_order_search_fields', array($this, 'add_po_number_to_search'));
        add_filter('woocommerce_order_table_search_query_meta_keys', array($this, 'add_po_number_to_search'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_po_number_in_order_list'), 10, 2);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_po_number_column'));
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_hpos_columns'));
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'display_hpos_column'), 10, 2);
        
        // Send Invoice from orders list (row + bulk)
        add_filter('woocommerce_admin_order_actions', array($this, 'add_send_invoice_row_action'), 10, 2);
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_send_invoice_bulk_action'));
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'add_send_invoice_bulk_action'));
        add_action('admin_action_wc_oe_send_invoice_from_list', array($this, 'handle_send_invoice_from_list'));
        add_action('admin_action_wc_oe_send_invoice_bulk', array($this, 'handle_send_invoice_bulk'));
        add_action('admin_action_wc_oe_view_invoice', array($this, 'handle_view_invoice'));
        add_action('admin_action_wc_oe_download_invoice', array($this, 'handle_download_invoice'));
        add_action('admin_action_wc_oe_view_packing_slip', array($this, 'handle_view_packing_slip'));
        add_action('admin_action_wc_oe_download_packing_slip', array($this, 'handle_download_packing_slip'));
        add_action('admin_notices', array($this, 'orders_list_invoice_notices'));
        
        // Create database tables
        $this->create_customers_table();
        $this->create_retailers_table();
    }
    
    public function activate() {
        // Activation tasks
        $this->create_customers_table();
        $this->create_retailers_table();
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
            retailer_id mediumint(9) DEFAULT 0,
            shipping_amount decimal(10,2) DEFAULT 0.00,
            package_weight decimal(10,2) DEFAULT 0.00,
            package_length decimal(10,2) DEFAULT 0.00,
            package_width decimal(10,2) DEFAULT 0.00,
            package_height decimal(10,2) DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function create_retailers_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_oe_retailers';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get or create the placeholder product used for custom (description-only) lines.
     * Veeqo and other fulfillment integrations require product line items; fee items are not synced.
     * This product is private and hidden from the shop.
     *
     * @return WC_Product_Simple|null
     */
    private function get_or_create_custom_product() {
        $sku = 'WC-OE-CUSTOM';
        $existing = wc_get_product_id_by_sku($sku);
        if ($existing) {
            $product = wc_get_product($existing);
            return $product ?: null;
        }
        $product = new WC_Product_Simple();
        $product->set_name(__('Custom Item', 'wc-order-entry'));
        $product->set_sku($sku);
        $product->set_status('private');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(false);
        $product->save();
        return $product;
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
        if ($hook === 'woocommerce_page_wc-order-entry') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('wc-oe-admin', WC_OE_PLUGIN_URL . 'assets/admin.js', array('jquery'), WC_OE_VERSION, true);
            wp_enqueue_style('wc-oe-admin', WC_OE_PLUGIN_URL . 'assets/admin.css', array(), WC_OE_VERSION);
            wp_localize_script('wc-oe-admin', 'wc_oe_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_oe_nonce'),
                'state_name_to_abbr' => array_merge(
                    $this->get_us_state_name_to_abbr(),
                    $this->get_ca_province_name_to_abbr()
                ),
            ));
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_order_page = ($screen && isset($screen->post_type) && $screen->post_type === 'shop_order');
        if (!$is_order_page && in_array($hook, array('post.php', 'post-new.php'))) {
            global $post;
            $is_order_page = ($post && isset($post->post_type) && $post->post_type === 'shop_order');
        }
        if (!$is_order_page && $screen && strpos($screen->id, 'wc-orders') !== false) {
            $is_order_page = true;
        }
        if ($is_order_page) {
            wp_enqueue_script('wc-oe-order', WC_OE_PLUGIN_URL . 'assets/order-edit.js', array('jquery'), WC_OE_VERSION, true);
            wp_localize_script('wc-oe-order', 'wc_oe_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_oe_nonce')
            ));
        }
    }
    
    public function admin_page() {
        global $wpdb;
        
        // Get saved customers
        $customers_table = $wpdb->prefix . 'wc_oe_customers';
        $customers = $wpdb->get_results("SELECT * FROM $customers_table ORDER BY name ASC");
        
        // Get saved retailers (for billing/invoice PDF - NOT the drop-ship customer)
        $retailers_table = $wpdb->prefix . 'wc_oe_retailers';
        $retailers = $wpdb->get_results("SELECT * FROM $retailers_table ORDER BY name ASC");
        
        $wc_states_us = is_array(WC()->countries->get_states('US')) ? WC()->countries->get_states('US') : array();
        $wc_states_ca = is_array(WC()->countries->get_states('CA')) ? WC()->countries->get_states('CA') : array();
        
        include WC_OE_PLUGIN_PATH . 'templates/admin-page.php';
    }
    
    // Display PO number and shipping charge in order details page
    public function display_po_number_in_order($order) {
        $po_number = $order->get_meta('_po_number');
        $shipping_charge = $order->get_meta('_shipping_charge');
        $shipping_carrier = $order->get_meta('_wc_oe_shipping_carrier');
        $shipping_service = $order->get_meta('_wc_oe_shipping_service');
        $shipping_quote_amount = $order->get_meta('_wc_oe_shipping_quote_amount');
        if (!empty($po_number) || !empty($shipping_charge) || !empty($shipping_carrier) || !empty($shipping_service)) {
            echo '<div class="address">';
            if (!empty($po_number)) {
                echo '<p><strong>' . __('PO Number:', 'wc-order-entry') . '</strong> ' . esc_html($po_number) . '</p>';
            }
            if (!empty($shipping_charge)) {
                echo '<p><strong>' . __('Shipping Charge:', 'wc-order-entry') . '</strong> ' . wc_price($shipping_charge) . '</p>';
            }
            if (!empty($shipping_carrier) || !empty($shipping_service)) {
                $service_label = trim($shipping_carrier . (!empty($shipping_service) ? ' - ' . $shipping_service : ''));
                echo '<p><strong>' . __('Live Shipping Service:', 'wc-order-entry') . '</strong> ' . esc_html($service_label) . '</p>';
            }
            if (!empty($shipping_quote_amount)) {
                echo '<p><strong>' . __('Live Shipping Quote:', 'wc-order-entry') . '</strong> ' . wc_price(floatval($shipping_quote_amount)) . '</p>';
            }
            echo '</div>';
        }
    }
    
    // Display Send Invoice button on order edit page
    public function display_send_invoice_button($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        global $wpdb;
        $retailers_table = $wpdb->prefix . 'wc_oe_retailers';
        $retailers = $wpdb->get_results("SELECT * FROM $retailers_table ORDER BY name ASC");
        $order_id = $order->get_id();
        $view_url = wp_nonce_url(admin_url('admin.php?action=wc_oe_view_invoice&order_id=' . $order_id), 'wc_oe_view_invoice_' . $order_id);
        $download_url = wp_nonce_url(admin_url('admin.php?action=wc_oe_download_invoice&order_id=' . $order_id), 'wc_oe_download_invoice_' . $order_id);
        $view_packing_slip_url = wp_nonce_url(admin_url('admin.php?action=wc_oe_view_packing_slip&order_id=' . $order_id), 'wc_oe_view_packing_slip_' . $order_id);
        $download_packing_slip_url = wp_nonce_url(admin_url('admin.php?action=wc_oe_download_packing_slip&order_id=' . $order_id), 'wc_oe_download_packing_slip_' . $order_id);
        ?>
        <div class="wc-oe-send-invoice-box" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
            <h4 style="margin-top: 0;"><?php echo esc_html__('Invoice', 'wc-order-entry'); ?></h4>
            <?php
            $sent_at = $order->get_meta('_wc_oe_invoice_sent_at');
            if (!empty($sent_at)) :
                $sent_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sent_at));
            ?>
            <p style="margin-bottom: 10px; padding: 8px 12px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;">
                <strong>✓</strong> <?php echo esc_html(sprintf(__('Invoice PDF sent on %s', 'wc-order-entry'), $sent_date)); ?>
            </p>
            <?php endif; ?>
            <p style="margin-bottom: 10px;">
                <a href="<?php echo esc_url($view_url); ?>" target="_blank" class="button"><?php echo esc_html__('View Invoice', 'wc-order-entry'); ?></a>
                <a href="<?php echo esc_url($download_url); ?>" class="button"><?php echo esc_html__('Download PDF', 'wc-order-entry'); ?></a>
            </p>
            <h4 style="margin-top: 14px;"><?php echo esc_html__('Packing Slip', 'wc-order-entry'); ?></h4>
            <p style="margin-bottom: 10px;">
                <a href="<?php echo esc_url($view_packing_slip_url); ?>" target="_blank" class="button"><?php echo esc_html__('View Packing Slip', 'wc-order-entry'); ?></a>
                <a href="<?php echo esc_url($download_packing_slip_url); ?>" class="button"><?php echo esc_html__('Download Packing Slip PDF', 'wc-order-entry'); ?></a>
            </p>
            <?php if (!empty($retailers)) : ?>
            <p style="margin-bottom: 10px; color: #666;"><?php echo esc_html__('Send the invoice to the retailer. The drop-ship customer will NOT receive this.', 'wc-order-entry'); ?></p>
            <p>
                <select id="wc-oe-invoice-retailer" style="min-width: 250px;">
                    <option value=""><?php echo esc_html__('Select retailer...', 'wc-order-entry'); ?></option>
                    <?php 
                    $stored_retailer_id = $order->get_meta('_wc_oe_retailer_id');
                    foreach ($retailers as $r): ?>
                        <option value="<?php echo esc_attr($r->id); ?>" <?php selected($stored_retailer_id, $r->id); ?>><?php echo esc_html($r->name . ' (' . $r->email . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="wc-oe-send-invoice-btn" class="button button-primary" data-order-id="<?php echo esc_attr($order_id); ?>">
                    <?php echo esc_html__('Send Invoice', 'wc-order-entry'); ?>
                </button>
            </p>
            <?php endif; ?>
            <div id="wc-oe-invoice-result" style="margin-top: 10px; padding: 8px; border-radius: 3px;"></div>
            <style>
                #wc-oe-invoice-result.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                #wc-oe-invoice-result.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            </style>
        </div>
        <?php
    }
    
    // Add PO number to order search fields (legacy CPT + HPOS admin list search).
    public function add_po_number_to_search($search_fields) {
        if (! is_array($search_fields)) {
            $search_fields = array();
        }
        $search_fields[] = '_po_number';
        $search_fields[] = 'PO Number';
        return array_values(array_unique($search_fields));
    }
    
    /**
     * Normalize PO for duplicate checks and storage: trim, strip leading #.
     *
     * @param string $po Raw input.
     * @return string
     */
    private function normalize_po_number($po) {
        $po = sanitize_text_field((string) $po);
        $po = trim($po);
        if ($po === '') {
            return '';
        }
        if (strpos($po, '#') === 0) {
            $po = trim(substr($po, 1));
        }
        return $po;
    }

    /**
     * Parse dollar-formatted strings like "$12.34" into float.
     *
     * @param mixed $value
     * @return float
     */
    private function parse_money_value($value) {
        if (is_numeric($value)) {
            return floatval($value);
        }
        $clean = preg_replace('/[^0-9\.\-]/', '', (string) $value);
        return is_numeric($clean) ? floatval($clean) : 0.0;
    }
    
    // Add PO number and Invoice columns to orders list
    public function add_po_number_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_total') {
                $new_columns['po_number'] = __('PO Number', 'wc-order-entry');
                $new_columns['wc_oe_invoice'] = __('Invoice', 'wc-order-entry');
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
        if ($column === 'wc_oe_invoice') {
            $order = wc_get_order($post_id);
            if ($order) {
                $this->render_send_invoice_link($order);
            }
        }
    }
    
    /**
     * Add Send Invoice row action to orders list (only when order has retailer).
     */
    public function add_send_invoice_row_action($actions, $order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return $actions;
        }
        $retailer_id = $order->get_meta('_wc_oe_retailer_id');
        if (empty($retailer_id)) {
            return $actions;
        }
        $url = wp_nonce_url(
            admin_url('admin.php?action=wc_oe_send_invoice_from_list&order_id=' . $order->get_id()),
            'wc_oe_send_invoice_' . $order->get_id()
        );
        $actions['wc_oe_send_invoice'] = array(
            'url'    => $url,
            'name'   => __('Send Invoice', 'wc-order-entry'),
            'action' => 'wc_oe_send_invoice',
        );
        return $actions;
    }
    
    /**
     * Add Send Invoice bulk action.
     */
    public function add_send_invoice_bulk_action($actions) {
        $actions['wc_oe_send_invoice_bulk'] = __('Send Invoice', 'wc-order-entry');
        return $actions;
    }
    
    /**
     * Handle single Send Invoice from orders list.
     */
    public function handle_send_invoice_from_list() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {
            wp_die(__('Invalid order.', 'wc-order-entry'));
        }
        check_admin_referer('wc_oe_send_invoice_' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found.', 'wc-order-entry'));
        }
        
        $retailer_id = $order->get_meta('_wc_oe_retailer_id');
        if (empty($retailer_id)) {
            $redirect = $this->get_orders_list_url();
            wp_redirect(add_query_arg('wc_oe_error', urlencode(__('No retailer assigned. Edit the order to assign one.', 'wc-order-entry')), $redirect));
            exit;
        }
        
        global $wpdb;
        $retailers_table = $wpdb->prefix . 'wc_oe_retailers';
        $retailer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $retailers_table WHERE id = %d", $retailer_id));
        if (!$retailer || empty($retailer->email)) {
            $redirect = $this->get_orders_list_url();
            wp_redirect(add_query_arg('wc_oe_error', urlencode(__('Retailer not found.', 'wc-order-entry')), $redirect));
            exit;
        }
        
        $invoice_data = $this->build_invoice_data_from_order($order);
        $tracking = $this->get_order_tracking($order);
        $po_number = $order->get_meta('_po_number');
        $total = $invoice_data['total'];
        $sent = $this->send_invoice_email($retailer->email, $invoice_data, $order_id, $tracking, $po_number, $total);
        
        $redirect = $this->get_orders_list_url();
        if ($sent) {
            wp_redirect(add_query_arg('wc_oe_sent', 1, $redirect));
        } else {
            wp_redirect(add_query_arg('wc_oe_error', urlencode(__('Failed to send invoice.', 'wc-order-entry')), $redirect));
        }
        exit;
    }
    
    /**
     * Handle bulk Send Invoice.
     */
    public function handle_send_invoice_bulk() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'wc-order-entry'));
        }
        if (!empty($_REQUEST['_wpnonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-orders')) {
            wp_die(__('Security check failed.', 'wc-order-entry'));
        }
        
        $order_ids = array();
        if (!empty($_REQUEST['order_ids']) && is_array($_REQUEST['order_ids'])) {
            $order_ids = array_map('intval', $_REQUEST['order_ids']);
        } elseif (!empty($_REQUEST['ids']) && is_string($_REQUEST['ids'])) {
            $order_ids = array_map('intval', explode(',', $_REQUEST['ids']));
        } elseif (!empty($_REQUEST['post']) && is_array($_REQUEST['post'])) {
            $order_ids = array_map('intval', $_REQUEST['post']);
        }
        
        if (empty($order_ids)) {
            wp_redirect($this->get_orders_list_url());
            exit;
        }
        
        $sent_count = 0;
        $skipped = 0;
        global $wpdb;
        $retailers_table = $wpdb->prefix . 'wc_oe_retailers';
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }
            $retailer_id = $order->get_meta('_wc_oe_retailer_id');
            if (empty($retailer_id)) {
                $skipped++;
                continue;
            }
            $retailer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $retailers_table WHERE id = %d", $retailer_id));
            if (!$retailer || empty($retailer->email)) {
                $skipped++;
                continue;
            }
            $invoice_data = $this->build_invoice_data_from_order($order);
            $tracking = $this->get_order_tracking($order);
            $po_number = $order->get_meta('_po_number');
            $total = $invoice_data['total'];
            if ($this->send_invoice_email($retailer->email, $invoice_data, $order_id, $tracking, $po_number, $total)) {
                $sent_count++;
            }
        }
        
        $redirect = $this->get_orders_list_url();
        $args = array('wc_oe_sent' => $sent_count);
        if ($skipped > 0) {
            $args['wc_oe_skipped'] = $skipped;
        }
        wp_redirect(add_query_arg($args, $redirect));
        exit;
    }
    
    /**
     * Admin notices after Send Invoice from orders list.
     */
    public function orders_list_invoice_notices() {
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'shop_order') === false && strpos($screen->id, 'wc-orders') === false)) {
            return;
        }
        if (!empty($_GET['wc_oe_sent'])) {
            $count = intval($_GET['wc_oe_sent']);
            $msg = $count === 1
                ? __('Invoice sent successfully.', 'wc-order-entry')
                : sprintf(__('%d invoices sent successfully.', 'wc-order-entry'), $count);
            if (!empty($_GET['wc_oe_skipped'])) {
                $msg .= ' ' . sprintf(__('%d orders skipped (no retailer assigned).', 'wc-order-entry'), intval($_GET['wc_oe_skipped']));
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
        if (!empty($_GET['wc_oe_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['wc_oe_error']))) . '</p></div>';
        }
    }
    
    /**
     * Get orders list URL (HPOS or legacy).
     */
    private function get_orders_list_url() {
        if (function_exists('wc_get_container')) {
            try {
                $controller = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class);
                if ($controller && $controller->custom_orders_table_usage_is_enabled()) {
                    return admin_url('admin.php?page=wc-orders&status=all');
                }
            } catch (Exception $e) {
                // HPOS not available, use legacy URL
            }
        }
        return admin_url('edit.php?post_type=shop_order');
    }
    
    /**
     * Add columns to HPOS orders list.
     */
    public function add_hpos_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_total') {
                $new_columns['po_number'] = __('PO Number', 'wc-order-entry');
                $new_columns['wc_oe_invoice'] = __('Invoice', 'wc-order-entry');
            }
        }
        return $new_columns;
    }
    
    /**
     * Display HPOS orders list column content.
     */
    public function display_hpos_column($column, $order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        if ($column === 'po_number') {
            $po_number = $order->get_meta('_po_number');
            echo !empty($po_number) ? esc_html($po_number) : '—';
        }
        if ($column === 'wc_oe_invoice') {
            $this->render_send_invoice_link($order);
        }
    }
    
    /**
     * Render Send Invoice link for order (used in custom column).
     */
    private function render_send_invoice_link($order) {
        $retailer_id = $order->get_meta('_wc_oe_retailer_id');
        if (empty($retailer_id)) {
            echo '<span style="color:#999;">—</span>';
            return;
        }
        $sent_at = $order->get_meta('_wc_oe_invoice_sent_at');
        $url = wp_nonce_url(
            admin_url('admin.php?action=wc_oe_send_invoice_from_list&order_id=' . $order->get_id()),
            'wc_oe_send_invoice_' . $order->get_id()
        );
        if (!empty($sent_at)) {
            $sent_date = date_i18n(get_option('date_format'), strtotime($sent_at));
            echo '<span style="color:#155724; padding: 2px 6px; background: #d4edda; border-radius: 3px;">✓ ' . esc_html(sprintf(__('Sent %s', 'wc-order-entry'), $sent_date)) . '</span>';
            echo ' <a href="' . esc_url($url) . '" class="button button-small" style="margin-left: 4px;">' . esc_html__('Send again', 'wc-order-entry') . '</a>';
        } else {
            echo '<a href="' . esc_url($url) . '" class="button button-small">' . esc_html__('Send', 'wc-order-entry') . '</a>';
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
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        $customer_data = array(
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'address_1' => isset($_POST['address_1']) ? sanitize_text_field(wp_unslash($_POST['address_1'])) : '',
            'address_2' => isset($_POST['address_2']) ? sanitize_text_field(wp_unslash($_POST['address_2'])) : '',
            'city' => isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '',
            'state' => isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '',
            'postcode' => isset($_POST['postcode']) ? sanitize_text_field(wp_unslash($_POST['postcode'])) : '',
            'country' => isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : 'US',
            'po_number' => isset($_POST['po_number']) ? sanitize_text_field(wp_unslash($_POST['po_number'])) : '',
            'retailer_id' => isset($_POST['retailer_id']) ? intval($_POST['retailer_id']) : 0,
            'shipping_amount' => isset($_POST['shipping_amount']) ? floatval($_POST['shipping_amount']) : 0,
            'package_weight' => isset($_POST['package_weight']) ? floatval($_POST['package_weight']) : 0,
            'package_length' => isset($_POST['package_length']) ? floatval($_POST['package_length']) : 0,
            'package_width' => isset($_POST['package_width']) ? floatval($_POST['package_width']) : 0,
            'package_height' => isset($_POST['package_height']) ? floatval($_POST['package_height']) : 0,
        );

        if (empty($customer_data['name'])) {
            wp_send_json_error(__('Customer name is required.', 'wc-order-entry'));
        }

        if ($customer_id > 0) {
            $result = $wpdb->update($table_name, $customer_data, array('id' => $customer_id));
            if ($result !== false) {
                wp_send_json_success(array(
                    'id' => $customer_id,
                    'updated' => true,
                    'name' => $customer_data['name'],
                    'email' => $customer_data['email'],
                    'message' => __('Customer updated successfully.', 'wc-order-entry'),
                ));
                return;
            }
            wp_send_json_error(__('Failed to update customer.', 'wc-order-entry'));
            return;
        }

        $result = $wpdb->insert($table_name, $customer_data);

        if ($result) {
            wp_send_json_success(array(
                'id' => intval($wpdb->insert_id),
                'updated' => false,
                'name' => $customer_data['name'],
                'email' => $customer_data['email'],
                'message' => __('Customer saved successfully.', 'wc-order-entry'),
            ));
        } else {
            wp_send_json_error(__('Failed to save customer.', 'wc-order-entry'));
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

    public function ajax_delete_customer() {
        check_ajax_referer('wc_oe_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_oe_customers';
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

        if ($customer_id <= 0) {
            wp_send_json_error(__('Invalid customer.', 'wc-order-entry'));
        }

        $result = $wpdb->delete($table_name, array('id' => $customer_id));
        if ($result !== false && $result > 0) {
            wp_send_json_success(__('Customer deleted.', 'wc-order-entry'));
        }

        wp_send_json_error(__('Failed to delete customer.', 'wc-order-entry'));
    }
    
    public function ajax_parse_customer() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        $customer_text = isset($_POST['customer_text']) ? wp_unslash($_POST['customer_text']) : '';
        $parsed_data = $this->parse_customer_address_text($customer_text);
        
        wp_send_json_success($parsed_data);
    }

    /**
     * Get live shipping rates via Live Shipping Rates for WooCommerce plugin.
     * Uses destination + manually entered package dimensions/weight.
     */
    public function ajax_get_live_rates() {
        check_ajax_referer('wc_oe_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions.', 'wc-order-entry'));
        }

        if (!function_exists('lsrwc_fetch_ups_rates') || !function_exists('lsrwc_fetch_usps_rates')) {
            wp_send_json_error(__('Live Shipping Rates plugin is not active.', 'wc-order-entry'));
        }

        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
        $state = isset($_POST['state']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['state']))) : '';
        $postcode = isset($_POST['postcode']) ? sanitize_text_field(wp_unslash($_POST['postcode'])) : '';
        $country = isset($_POST['country']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['country']))) : 'US';
        $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
        $length = isset($_POST['length']) ? floatval($_POST['length']) : 0;
        $width = isset($_POST['width']) ? floatval($_POST['width']) : 0;
        $height = isset($_POST['height']) ? floatval($_POST['height']) : 0;

        if (empty($city) || empty($state) || empty($postcode) || empty($country)) {
            wp_send_json_error(__('City, state/province, postal code, and country are required.', 'wc-order-entry'));
        }
        if ($weight <= 0 || $length <= 0 || $width <= 0 || $height <= 0) {
            wp_send_json_error(__('Weight, length, width, and height must be greater than zero.', 'wc-order-entry'));
        }

        $rates = array();
        $country = in_array($country, array('US', 'CA'), true) ? $country : 'US';

        // Canada: UPS International. US/default: UPS Ground + USPS Ground Advantage.
        if ($country === 'CA') {
            $ups_international = lsrwc_fetch_ups_rates($city, $state, $postcode, $country, $weight, $length, $width, $height, '11');
            foreach ($ups_international as $service => $amounts) {
                $original_amount = $this->parse_money_value(isset($amounts['original']) ? $amounts['original'] : 0);
                $adjusted_amount = $this->parse_money_value(isset($amounts['adjusted']) ? $amounts['adjusted'] : 0);
                if ($adjusted_amount > 0) {
                    $rates[] = array(
                        'id' => sanitize_title('ups-' . $service),
                        'carrier' => 'UPS',
                        'service' => $service,
                        'label' => 'UPS - ' . $service,
                        'amount' => $adjusted_amount,
                        'amount_display' => '$' . number_format($adjusted_amount, 2),
                        'live_amount' => $original_amount,
                        'live_amount_display' => '$' . number_format($original_amount, 2),
                        'adjusted_amount' => $adjusted_amount,
                        'adjusted_amount_display' => '$' . number_format($adjusted_amount, 2),
                    );
                }
            }
        } else {
            $ups = lsrwc_fetch_ups_rates($city, $state, $postcode, $country, $weight, $length, $width, $height, '03');
            foreach ($ups as $service => $amounts) {
                $original_amount = $this->parse_money_value(isset($amounts['original']) ? $amounts['original'] : 0);
                $adjusted_amount = $this->parse_money_value(isset($amounts['adjusted']) ? $amounts['adjusted'] : 0);
                if ($adjusted_amount > 0) {
                    $rates[] = array(
                        'id' => sanitize_title('ups-' . $service),
                        'carrier' => 'UPS',
                        'service' => $service,
                        'label' => 'UPS - ' . $service,
                        'amount' => $adjusted_amount,
                        'amount_display' => '$' . number_format($adjusted_amount, 2),
                        'live_amount' => $original_amount,
                        'live_amount_display' => '$' . number_format($original_amount, 2),
                        'adjusted_amount' => $adjusted_amount,
                        'adjusted_amount_display' => '$' . number_format($adjusted_amount, 2),
                    );
                }
            }

            $usps = lsrwc_fetch_usps_rates($city, $state, $postcode, $weight, $length, $width, $height);
            foreach ($usps as $service => $amounts) {
                $original_amount = $this->parse_money_value(isset($amounts['original']) ? $amounts['original'] : 0);
                $adjusted_amount = $this->parse_money_value(isset($amounts['adjusted']) ? $amounts['adjusted'] : 0);
                if ($adjusted_amount > 0) {
                    $rates[] = array(
                        'id' => sanitize_title('usps-' . $service),
                        'carrier' => 'USPS',
                        'service' => $service,
                        'label' => 'USPS - ' . $service,
                        'amount' => $adjusted_amount,
                        'amount_display' => '$' . number_format($adjusted_amount, 2),
                        'live_amount' => $original_amount,
                        'live_amount_display' => '$' . number_format($original_amount, 2),
                        'adjusted_amount' => $adjusted_amount,
                        'adjusted_amount_display' => '$' . number_format($adjusted_amount, 2),
                    );
                }
            }
        }

        if (empty($rates)) {
            wp_send_json_error(__('No live shipping rates were returned. Check credentials and destination/package details.', 'wc-order-entry'));
        }

        usort($rates, function ($a, $b) {
            $left = isset($a['amount']) ? floatval($a['amount']) : 0;
            $right = isset($b['amount']) ? floatval($b['amount']) : 0;
            if ($left === $right) {
                return 0;
            }
            return ($left < $right) ? -1 : 1;
        });

        wp_send_json_success(array(
            'rates' => $rates,
            'recommended' => $rates[0],
        ));
    }
    
    /**
     * Map full US state names to 2-letter codes (lowercase keys).
     *
     * @return array<string, string>
     */
    private function get_us_state_name_to_abbr() {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = array(
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
            'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
            'district of columbia' => 'DC', 'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI',
            'idaho' => 'ID', 'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA',
            'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME',
            'maryland' => 'MD', 'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN',
            'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE',
            'nevada' => 'NV', 'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM',
            'new york' => 'NY', 'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH',
            'oklahoma' => 'OK', 'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI',
            'south carolina' => 'SC', 'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX',
            'utah' => 'UT', 'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA',
            'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY',
        );
        return $map;
    }
    
    /**
     * Map Canadian province/territory full names to 2-letter codes.
     *
     * @return array<string, string>
     */
    private function get_ca_province_name_to_abbr() {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = array(
            'alberta' => 'AB', 'british columbia' => 'BC', 'manitoba' => 'MB', 'new brunswick' => 'NB',
            'newfoundland and labrador' => 'NL', 'northwest territories' => 'NT', 'nova scotia' => 'NS',
            'nunavut' => 'NU', 'ontario' => 'ON', 'prince edward island' => 'PE', 'quebec' => 'QC',
            'saskatchewan' => 'SK', 'yukon' => 'YT',
        );
        return $map;
    }
    
    /**
     * @param string $name
     * @return string|null
     */
    private function normalize_state_to_abbr($name, $country_hint = '') {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z]{2}$/', $name)) {
            return strtoupper($name);
        }
        $key = strtolower($name);
        $us = $this->get_us_state_name_to_abbr();
        if (isset($us[ $key ])) {
            return $us[ $key ];
        }
        $ca = $this->get_ca_province_name_to_abbr();
        if (isset($ca[ $key ])) {
            return $ca[ $key ];
        }
        return null;
    }
    
    /**
     * Replace US ZIP+4 (12345-6789) with same-length spaces so phone regex does not match
     * the xxxxx-xxxx segment as xxx-xxxx (e.g. 119-8032 inside 43119-8032).
     *
     * @param string $text
     * @return string
     */
    private function mask_us_zip_plus4_for_phone_scan($text) {
        return preg_replace_callback('/\b\d{5}-\d{4}\b/', function ($m) {
            return str_repeat(' ', strlen($m[0]));
        }, $text);
    }
    
    /**
     * Extract first phone from text, ignoring digit patterns inside US ZIP+4 codes.
     *
     * @param string $text
     * @return string|null
     */
    private function extract_phone_from_text($text) {
        $masked = $this->mask_us_zip_plus4_for_phone_scan($text);
        if (! preg_match('/(?:\+?\d{1,3}[-.\s])?(?:\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}\b/', $masked, $ph, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $offset = $ph[0][1];
        $len = strlen($ph[0][0]);
        return trim(substr($text, $offset, $len));
    }
    
    /**
     * Try to parse City, State ZIP from one line (US and CA).
     *
     * @return array<string, string>|null
     */
    private function try_parse_city_state_zip_line($line) {
        $line = trim($line);
        if ($line === '') {
            return null;
        }
        
        // Canadian: City, ST A1A 1A1
        if (preg_match('/^(.+?),\s*([A-Za-z]{2})\s+([A-Za-z]\d[A-Za-z]\s?\d[A-Za-z]\d)\s*$/u', $line, $m)) {
            return array(
                'city' => trim($m[1]),
                'state' => strtoupper($m[2]),
                'postcode' => strtoupper(str_replace(' ', '', $m[3])),
                'country' => 'CA',
            );
        }
        
        // City, Full State/Province Name 12345 (before 2-letter so e.g. Maryland parses correctly)
        if (preg_match('/^(.+?),\s*([A-Za-z][A-Za-z\s]+)\s+(\d{5}(?:-\d{4})?)\s*$/u', $line, $m)) {
            $state_label = trim($m[2]);
            $abbr = $this->normalize_state_to_abbr($state_label);
            if ($abbr) {
                $ca_map = $this->get_ca_province_name_to_abbr();
                $country = isset($ca_map[ strtolower($state_label) ]) ? 'CA' : 'US';
                return array(
                    'city' => trim($m[1]),
                    'state' => $abbr,
                    'postcode' => $m[3],
                    'country' => $country,
                );
            }
        }
        
        // US: City, ST 12345 or 12345-6789 (two-letter state)
        if (preg_match('/^(.+?),\s*([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)\s*$/u', $line, $m)) {
            return array(
                'city' => trim($m[1]),
                'state' => strtoupper($m[2]),
                'postcode' => $m[3],
                'country' => 'US',
            );
        }
        
        // US: City ST 12345 (no comma before state)
        if (preg_match('/^(.+?)\s+([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)\s*$/u', $line, $m)) {
            $city = trim($m[1]);
            $st = strtoupper($m[2]);
            // Avoid treating "Suite 100 90210" incorrectly: city part should not be only digits
            if (strlen($city) > 1 && ! preg_match('/^\d+$/', $city)) {
                return array(
                    'city' => $city,
                    'state' => $st,
                    'postcode' => $m[3],
                    'country' => 'US',
                );
            }
        }
        
        return null;
    }
    
    /**
     * Single pasted line with commas: "Name, Street, City, ST ZIP" etc.
     *
     * @return array<string, string>
     */
    private function parse_comma_separated_address($line) {
        $out = array();
        $parts = array_map('trim', explode(',', $line));
        $parts = array_values(array_filter($parts, function ($p) {
            return $p !== '';
        }));
        if (count($parts) < 3) {
            return $out;
        }
        
        $last = array_pop($parts);
        // Last segment "ST ZIP"
        if (preg_match('/^([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)$/', $last, $lm)) {
            $out['state'] = strtoupper($lm[1]);
            $out['postcode'] = $lm[2];
            $out['country'] = 'US';
        } elseif (preg_match('/^([A-Za-z]{2})\s+([A-Za-z]\d[A-Za-z]\s?\d[A-Za-z]\d)$/u', $last, $lm)) {
            $out['state'] = strtoupper($lm[1]);
            $out['postcode'] = strtoupper(str_replace(' ', '', $lm[2]));
            $out['country'] = 'CA';
        } else {
            // Put back and abort structured parse
            $parts[] = $last;
            return array();
        }
        
        if (empty($parts)) {
            return $out;
        }
        
        $city = array_pop($parts);
        $out['city'] = $city;
        
        if (empty($parts)) {
            return $out;
        }
        
        if (count($parts) >= 2) {
            $out['name'] = array_shift($parts);
            $out['address_1'] = implode(', ', $parts);
        } else {
            $out['address_1'] = $parts[0];
        }
        
        return $out;
    }
    
    /**
     * @param string $raw
     * @return array<string, string>
     */
    private function parse_customer_address_text($raw) {
        $parsed_data = array();
        $raw = trim(str_replace(array("\r\n", "\r"), "\n", $raw));
        if ($raw === '') {
            return $parsed_data;
        }
        
        // Tab-separated pasted rows → treat as lines
        if (strpos($raw, "\t") !== false && strpos($raw, "\n") === false) {
            $raw = str_replace("\t", "\n", $raw);
        }
        
        $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), function ($l) {
            return $l !== '';
        }));
        
        // One long comma-separated address
        if (count($lines) === 1 && substr_count($lines[0], ',') >= 2) {
            $comma = $this->parse_comma_separated_address($lines[0]);
            if (! empty($comma)) {
                $blob = $lines[0];
                if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $blob, $em)) {
                    $comma['email'] = $em[0];
                }
                $phone_extract = $this->extract_phone_from_text($blob);
                if ($phone_extract !== null) {
                    $comma['phone'] = $phone_extract;
                }
                return array_merge($parsed_data, $comma);
            }
        }
        
        $used = array();
        
        // Full-text email (first match)
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $raw, $em)) {
            $parsed_data['email'] = $em[0];
        }
        
        // Full-text phone (skip false positives inside US ZIP+4)
        $phone_extract = $this->extract_phone_from_text($raw);
        if ($phone_extract !== null) {
            $parsed_data['phone'] = $phone_extract;
        }
        
        // Scan each line for city/state/zip (prefer last matching line for city line)
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (! empty($used[ $i ])) {
                continue;
            }
            $c = $this->try_parse_city_state_zip_line($lines[ $i ]);
            if ($c) {
                $parsed_data['city'] = $c['city'];
                $parsed_data['state'] = $c['state'];
                $parsed_data['postcode'] = $c['postcode'];
                $parsed_data['country'] = $c['country'];
                $used[ $i ] = true;
                break;
            }
        }
        
        // Fallback: city/state/zip anywhere in blob (multiline paste)
        if (! isset($parsed_data['city']) && preg_match('/\b([^,\n]+),\s*([A-Z]{2})\s+(\d{5}(?:-\d{4})?)\b/i', $raw, $blob)) {
            $parsed_data['city'] = trim($blob[1]);
            $parsed_data['state'] = strtoupper($blob[2]);
            $parsed_data['postcode'] = $blob[3];
            $parsed_data['country'] = 'US';
        }
        
        foreach ($lines as $index => $line) {
            if (! empty($used[ $index ])) {
                continue;
            }
            if (filter_var($line, FILTER_VALIDATE_EMAIL)) {
                $parsed_data['email'] = $line;
                $used[ $index ] = true;
                continue;
            }
            if (preg_match('/^(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}$/', preg_replace('/\s+/', ' ', $line)) ||
                preg_match('/^\d{10}$/', preg_replace('/\D/', '', $line))) {
                $parsed_data['phone'] = $line;
                $used[ $index ] = true;
                continue;
            }
            if (preg_match('/^\d{5}(-\d{4})?$/', $line)) {
                if (empty($parsed_data['postcode'])) {
                    $parsed_data['postcode'] = $line;
                    $parsed_data['country'] = 'US';
                }
                $used[ $index ] = true;
                continue;
            }
            if (preg_match('/^[A-Za-z]\d[A-Za-z]\s?\d[A-Za-z]\d$/', $line)) {
                if (empty($parsed_data['postcode'])) {
                    $parsed_data['postcode'] = strtoupper(str_replace(' ', '', $line));
                    $parsed_data['country'] = 'CA';
                }
                $used[ $index ] = true;
                continue;
            }
        }
        
        // Street lines: start with digits, not already used
        foreach ($lines as $index => $line) {
            if (! empty($used[ $index ])) {
                continue;
            }
            if (preg_match('/^\d{1,6}\s+\S+/', $line) &&
                ! preg_match('/^\d{5}(-\d{4})?$/', $line) &&
                ! preg_match('/^\d{10}$/', preg_replace('/\D/', '', $line))) {
                if (empty($parsed_data['address_1'])) {
                    $parsed_data['address_1'] = $line;
                } elseif (empty($parsed_data['address_2']) && $parsed_data['address_1'] !== $line) {
                    $parsed_data['address_2'] = $line;
                }
                $used[ $index ] = true;
            }
        }
        
        // Street with PO Box etc.
        foreach ($lines as $index => $line) {
            if (! empty($used[ $index ])) {
                continue;
            }
            if (preg_match('/\b(p\.?\s*o\.?\s*box|pob)\b/i', $line)) {
                if (empty($parsed_data['address_1'])) {
                    $parsed_data['address_1'] = $line;
                } else {
                    $parsed_data['address_2'] = $line;
                }
                $used[ $index ] = true;
            }
        }
        
        // Name: first unused line that looks like a person/company name
        foreach ($lines as $index => $line) {
            if (! empty($used[ $index ])) {
                continue;
            }
            if (empty($parsed_data['name']) && strlen($line) > 1 && strlen($line) < 120) {
                $parsed_data['name'] = $line;
                $used[ $index ] = true;
                break;
            }
        }
        
        // Remaining single token lines as address if still missing
        foreach ($lines as $index => $line) {
            if (! empty($used[ $index ])) {
                continue;
            }
            if (empty($parsed_data['address_1']) && strlen($line) > 3) {
                $parsed_data['address_1'] = $line;
                $used[ $index ] = true;
            }
        }
        
        return $parsed_data;
    }
    
    public function ajax_save_retailer() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_oe_retailers';
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        
        if (empty($name) || empty($email)) {
            wp_send_json_error('Name and email are required.');
        }
        
        $result = $wpdb->insert($table_name, array(
            'name' => $name,
            'email' => $email
        ));
        
        if ($result) {
            wp_send_json_success(array('id' => $wpdb->insert_id));
        } else {
            wp_send_json_error('Failed to save retailer.');
        }
    }
    
    public function ajax_delete_retailer() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_oe_retailers';
        $id = intval($_POST['retailer_id']);
        
        $result = $wpdb->delete($table_name, array('id' => $id));
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete retailer.');
        }
    }
    
    public function ajax_send_test_invoice() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        if (empty($email)) {
            wp_send_json_error(__('Please enter a valid email address.', 'wc-order-entry'));
        }
        
        $invoice_data = array(
            'customer' => array(
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '(555) 123-4567',
                'address_1' => '123 Main Street',
                'address_2' => 'Suite 100',
                'city' => 'Kansas City',
                'state' => 'KS',
                'postcode' => '66219',
                'country' => 'US',
                'po_number' => 'PO-12345'
            ),
            'products' => array(
                array('name' => 'Sample Product A', 'quantity' => 2, 'price' => 29.99, 'total' => 59.98),
                array('name' => 'Sample Product B', 'quantity' => 1, 'price' => 15.50, 'total' => 15.50)
            ),
            'shipping_amount' => 12.99,
            'subtotal' => 75.48,
            'total' => 88.47
        );
        $tracking = array(
            array('carrier' => 'USPS', 'number' => '9400111899562537864123'),
            array('carrier' => 'FedEx', 'number' => '123456789012')
        );
        $sent = $this->send_invoice_email($email, $invoice_data, 99999, $tracking, 'PO-12345', 88.47, true);
        
        if ($sent) {
            wp_send_json_success(sprintf(__('Test invoice sent to %s. Check your inbox.', 'wc-order-entry'), $email));
        } else {
            wp_send_json_error(__('Failed to send. Check your mail configuration.', 'wc-order-entry'));
        }
    }
    
    public function ajax_send_invoice() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $retailer_id = intval($_POST['retailer_id']);
        
        if (!$order_id || !$retailer_id) {
            wp_send_json_error(__('Order and retailer are required.', 'wc-order-entry'));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(__('Order not found.', 'wc-order-entry'));
        }
        
        global $wpdb;
        $retailers_table = $wpdb->prefix . 'wc_oe_retailers';
        $retailer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $retailers_table WHERE id = %d", $retailer_id));
        
        if (!$retailer || empty($retailer->email)) {
            wp_send_json_error(__('Retailer not found.', 'wc-order-entry'));
        }
        
        $invoice_data = $this->build_invoice_data_from_order($order);
        $tracking = $this->get_order_tracking($order);
        $po_number = $order->get_meta('_po_number');
        $total = $invoice_data['total'];
        $sent = $this->send_invoice_email($retailer->email, $invoice_data, $order_id, $tracking, $po_number, $total);
        
        if ($sent) {
            $order = wc_get_order($order_id);
            $sent_at = $order ? $order->get_meta('_wc_oe_invoice_sent_at') : '';
            $msg = __('Invoice PDF sent to retailer.', 'wc-order-entry');
            if (!empty($sent_at)) {
                $msg .= ' ' . sprintf(__('Sent on %s.', 'wc-order-entry'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sent_at)));
            }
            wp_send_json_success($msg);
        } else {
            wp_send_json_error(__('Failed to send invoice. Check your mail configuration.', 'wc-order-entry'));
        }
    }
    
    /**
     * View invoice (HTML in new tab).
     */
    public function handle_view_invoice() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {
            wp_die(__('Invalid order.', 'wc-order-entry'));
        }
        check_admin_referer('wc_oe_view_invoice_' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found.', 'wc-order-entry'));
        }
        
        $invoice_data = $this->build_invoice_data_from_order($order);
        $tracking = $this->get_order_tracking($order);
        $html = $this->generate_invoice_html($invoice_data, $order_id, $tracking);
        $full_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . esc_attr(sprintf(__('Invoice #%d', 'wc-order-entry'), $order_id)) . '</title></head><body>' . $html . '</body></html>';
        echo $full_html;
        exit;
    }
    
    /**
     * Download invoice (PDF).
     */
    public function handle_download_invoice() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {
            wp_die(__('Invalid order.', 'wc-order-entry'));
        }
        check_admin_referer('wc_oe_download_invoice_' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found.', 'wc-order-entry'));
        }
        
        $invoice_data = $this->build_invoice_data_from_order($order);
        $tracking = $this->get_order_tracking($order);
        $pdf_path = $this->generate_invoice_pdf($invoice_data, $order_id, $tracking);
        
        if (!$pdf_path || !file_exists($pdf_path)) {
            wp_die(__('PDF generation failed. Dompdf may not be installed.', 'wc-order-entry'));
        }
        
        $filename = 'invoice-' . $order_id . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdf_path));
        readfile($pdf_path);
        @unlink($pdf_path);
        exit;
    }

    /**
     * View packing slip (HTML in new tab).
     */
    public function handle_view_packing_slip() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {
            wp_die(__('Invalid order.', 'wc-order-entry'));
        }
        check_admin_referer('wc_oe_view_packing_slip_' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found.', 'wc-order-entry'));
        }

        $packing_slip_data = $this->build_packing_slip_data_from_order($order);
        $html = $this->generate_packing_slip_html($packing_slip_data, $order_id);
        // Print preview + browser print: force 4×6 in page (matches PDF / label stock).
        $packing_slip_doc_css = '
            @page { size: 4in 6in; margin: 0.1in; }
            html { box-sizing: border-box; }
            *, *::before, *::after { box-sizing: inherit; }
            @media screen {
                html { min-height: 100%; background: #e8e8e8; }
                body {
                    margin: 0;
                    padding: 16px;
                    display: flex;
                    justify-content: center;
                    align-items: flex-start;
                    background: #e8e8e8;
                }
                .wc-oe-packing-slip-label {
                    background: #fff;
                    box-shadow: 0 2px 10px rgba(0,0,0,.12);
                    min-height: 6in;
                }
            }
            @media print {
                html, body {
                    width: 4in !important;
                    min-height: 6in !important;
                    margin: 0 auto !important;
                    padding: 0 !important;
                    background: #fff !important;
                }
                body { display: block !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .wc-oe-packing-slip-label {
                    box-shadow: none !important;
                    min-height: auto !important;
                    margin: 0 !important;
                }
            }
        ';
        $full_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>' . $packing_slip_doc_css . '</style><title>' . esc_attr(sprintf(__('Packing Slip #%d', 'wc-order-entry'), $order_id)) . '</title></head><body>' . $html . '</body></html>';
        echo $full_html;
        exit;
    }

    /**
     * Download packing slip (PDF).
     */
    public function handle_download_packing_slip() {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id) {
            wp_die(__('Invalid order.', 'wc-order-entry'));
        }
        check_admin_referer('wc_oe_download_packing_slip_' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found.', 'wc-order-entry'));
        }

        $packing_slip_data = $this->build_packing_slip_data_from_order($order);
        $pdf_path = $this->generate_packing_slip_pdf($packing_slip_data, $order_id);
        if (!$pdf_path || !file_exists($pdf_path)) {
            wp_die(__('Packing slip PDF generation failed. Dompdf may not be installed.', 'wc-order-entry'));
        }

        $filename = 'packing-slip-' . $order_id . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdf_path));
        readfile($pdf_path);
        @unlink($pdf_path);
        exit;
    }
    
    /**
     * Send invoice email to recipient. Includes body message, and PDF attachment if Dompdf is available.
     */
    private function send_invoice_email($to_email, $invoice_data, $order_id, $tracking, $po_number, $total, $is_test = false) {
        $currency = get_woocommerce_currency_symbol();
        $po_display = !empty($po_number) ? $po_number : __('N/A', 'wc-order-entry');
        $total_formatted = $currency . number_format($total, 2);
        
        $body_message = sprintf(
            __("Thank you for your order! Please find attached your invoice for PO#%s in the amount of %s.\n\nIf you have any questions about this invoice, please don't hesitate to reach out.", 'wc-order-entry'),
            $po_display,
            $total_formatted
        );
        
        $subject = sprintf(__('Invoice #%d - PO# %s', 'wc-order-entry'), $order_id, $po_display);
        if ($is_test) {
            $subject .= ' (' . __('Test', 'wc-order-entry') . ')';
        }
        
        $html = $this->generate_invoice_html($invoice_data, $order_id, $tracking);
        $full_html = '<p style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;">' . nl2br(esc_html($body_message)) . '</p><hr style="margin: 20px 0;"><p style="font-size: 12px; color: #666;">' . __('Invoice details below:', 'wc-order-entry') . '</p>' . $html;
        
        $attachments = array();
        $pdf_path = $this->generate_invoice_pdf($invoice_data, $order_id, $tracking);
        if ($pdf_path) {
            $attachments[] = $pdf_path;
        }
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        if (!$is_test) {
            $admin_email = get_option('admin_email');
            if (!empty($admin_email)) {
                $headers[] = 'Bcc: ' . $admin_email;
            }
        }
        $sent = wp_mail($to_email, $subject, $full_html, $headers, $attachments);
        
        if (!empty($pdf_path) && file_exists($pdf_path)) {
            @unlink($pdf_path);
        }
        
        if ($sent && !$is_test) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_wc_oe_invoice_sent_at', current_time('mysql'));
                $order->save();
            }
        }
        
        return $sent;
    }
    
    /**
     * Generate PDF from invoice data. Returns temp file path or false if Dompdf not available.
     */
    private function generate_invoice_pdf($invoice_data, $order_id, $tracking = array()) {
        $autoload = WC_OE_PLUGIN_PATH . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            return false;
        }
        require_once $autoload;
        
        if (!class_exists('Dompdf\Dompdf')) {
            return false;
        }
        
        $html = $this->generate_invoice_html($invoice_data, $order_id, $tracking);
        $full_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($full_html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdf_content = $dompdf->output();
            
            $temp_file = wp_tempnam('invoice-' . $order_id . '.pdf');
            if ($temp_file && file_put_contents($temp_file, $pdf_content)) {
                $pdf_file = preg_replace('/\.tmp$/i', '.pdf', $temp_file);
                if (rename($temp_file, $pdf_file)) {
                    return $pdf_file;
                }
                return $temp_file;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * Build packing slip data array from a WooCommerce order.
     * Includes shipping addresses and product lines only (no pricing).
     */
    private function build_packing_slip_data_from_order($order) {
        $ship_to_name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
        if ($ship_to_name === '') {
            $ship_to_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }

        $ship_to = array(
            'name' => $ship_to_name,
            'address_1' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
            'address_2' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
            'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
            'state' => $order->get_shipping_state() ?: $order->get_billing_state(),
            'postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
            'country' => $order->get_shipping_country() ?: $order->get_billing_country(),
        );

        // Ship-from company: always the WordPress site name (not the distributor/retailer).
        $ship_from_company = get_bloginfo('name');

        $default_country_state = get_option('woocommerce_default_country', 'US');
        $default_country_parts = explode(':', $default_country_state);
        $store_country = isset($default_country_parts[0]) ? $default_country_parts[0] : 'US';
        $store_state = isset($default_country_parts[1]) ? $default_country_parts[1] : '';
        $store_state_label = $store_state;
        $store_country_label = $store_country;
        if (function_exists('WC') && WC()) {
            $countries = WC()->countries;
            if ($countries) {
                $store_states = $countries->get_states($store_country);
                if (!empty($store_state) && is_array($store_states) && isset($store_states[$store_state])) {
                    $store_state_label = $store_states[$store_state];
                }
                if (isset($countries->countries[$store_country])) {
                    $store_country_label = $countries->countries[$store_country];
                }
            }
        }

        $ship_from = array(
            'company' => $ship_from_company,
            'address_1' => get_option('woocommerce_store_address', ''),
            'address_2' => get_option('woocommerce_store_address_2', ''),
            'city' => get_option('woocommerce_store_city', ''),
            'state' => $store_state_label,
            'postcode' => get_option('woocommerce_store_postcode', ''),
            'country' => $store_country_label,
        );

        $items = array();
        foreach ($order->get_items() as $item) {
            if (!$item->is_type('line_item')) {
                continue;
            }
            $qty = intval($item->get_quantity());
            if ($qty <= 0) {
                $qty = 1;
            }
            $items[] = array(
                'description' => $item->get_name(),
                'qty' => $qty,
            );
        }

        return array(
            'order_number' => $order->get_order_number(),
            'order_date' => $order->get_date_created(),
            'po_number' => $order->get_meta('_po_number'),
            'ship_to' => $ship_to,
            'ship_from' => $ship_from,
            'items' => $items,
        );
    }

    /**
     * Generate packing slip HTML (no prices/totals).
     * Sized for 4×6 in. thermal/shipping label (PDF uses same dimensions).
     */
    private function generate_packing_slip_html($data, $order_id) {
        $ship_to = $data['ship_to'];
        $ship_from = $data['ship_from'];
        $items = $data['items'];
        $po_number = $data['po_number'];
        $order_date = $data['order_date'] ? date_i18n(get_option('date_format'), $data['order_date']->getTimestamp()) : '';

        $ship_from_line = trim(
            $ship_from['company'] . ' · ' .
            $ship_from['address_1'] .
            (!empty($ship_from['address_2']) ? ' ' . $ship_from['address_2'] : '') . ', ' .
            trim($ship_from['city'] . ' ' . $ship_from['state'] . ' ' . $ship_from['postcode']) . ', ' .
            $ship_from['country']
        );

        ob_start();
        ?>
        <style type="text/css">
            .wc-oe-packing-slip-label { width: 4in; max-width: 4in; box-sizing: border-box; margin: 0 auto; padding: 0.1in 0.12in; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7.5pt; line-height: 1.15; color: #000; }
            .wc-oe-packing-slip-label .wc-oe-ps-title { font-size: 11pt; font-weight: 700; margin: 0 0 0.06in; letter-spacing: 0.02em; }
            .wc-oe-packing-slip-label .wc-oe-ps-meta-row { width: 100%; border-collapse: collapse; margin: 0 0 0.06in; font-size: 6.5pt; }
            .wc-oe-packing-slip-label .wc-oe-ps-meta-row td { vertical-align: top; padding: 0; }
            .wc-oe-packing-slip-label .wc-oe-ps-from { font-size: 6.5pt; color: #111; }
            .wc-oe-packing-slip-label .wc-oe-ps-ord { text-align: right; white-space: nowrap; }
            .wc-oe-packing-slip-label .wc-oe-ps-rule { border: none; border-top: 1px solid #222; margin: 0.06in 0; }
            .wc-oe-packing-slip-label .wc-oe-ps-shipto-h { font-size: 7pt; font-weight: 700; margin: 0 0 0.04in; text-transform: uppercase; }
            .wc-oe-packing-slip-label .wc-oe-ps-shipto p { margin: 0; font-size: 9pt; line-height: 1.2; }
            .wc-oe-packing-slip-label .wc-oe-ps-shipto .wc-oe-ps-name { font-weight: 700; font-size: 10pt; margin-bottom: 0.04in; }
            .wc-oe-packing-slip-label .wc-oe-ps-items { width: 100%; border-collapse: collapse; margin-top: 0.08in; font-size: 7pt; }
            .wc-oe-packing-slip-label .wc-oe-ps-items th { text-align: left; border-bottom: 1px solid #222; padding: 0.03in 0.04in 0.04in 0; font-weight: 700; }
            .wc-oe-packing-slip-label .wc-oe-ps-items th.wc-oe-ps-qty { text-align: right; width: 0.45in; }
            .wc-oe-packing-slip-label .wc-oe-ps-items td { padding: 0.03in 0.04in 0.03in 0; border-bottom: 1px solid #ccc; vertical-align: top; }
            .wc-oe-packing-slip-label .wc-oe-ps-items td.wc-oe-ps-qty { text-align: right; }
        </style>
        <div class="wc-oe-packing-slip-label">
            <div class="wc-oe-ps-title"><?php echo esc_html__('PACKING SLIP', 'wc-order-entry'); ?></div>
            <table class="wc-oe-ps-meta-row">
                <tr>
                    <td class="wc-oe-ps-from">
                        <strong><?php echo esc_html__('From:', 'wc-order-entry'); ?></strong>
                        <?php echo esc_html($ship_from_line); ?>
                    </td>
                    <td class="wc-oe-ps-ord">
                        <?php echo esc_html(sprintf(__('Ord %s', 'wc-order-entry'), $data['order_number'])); ?>
                        <?php if ($order_date !== '') : ?>
                            <br /><?php echo esc_html($order_date); ?>
                        <?php endif; ?>
                        <br /><?php echo esc_html(sprintf(__('PO: %s', 'wc-order-entry'), !empty($po_number) ? $po_number : __('N/A', 'wc-order-entry'))); ?>
                    </td>
                </tr>
            </table>
            <hr class="wc-oe-ps-rule" />
            <div class="wc-oe-ps-shipto">
                <div class="wc-oe-ps-shipto-h"><?php echo esc_html__('Ship To', 'wc-order-entry'); ?></div>
                <p class="wc-oe-ps-name"><?php echo esc_html($ship_to['name']); ?></p>
                <p><?php echo esc_html($ship_to['address_1']); ?></p>
                <?php if (!empty($ship_to['address_2'])) : ?><p><?php echo esc_html($ship_to['address_2']); ?></p><?php endif; ?>
                <p><?php echo esc_html(trim($ship_to['city'] . ', ' . $ship_to['state'] . ' ' . $ship_to['postcode'])); ?></p>
                <p><?php echo esc_html($ship_to['country']); ?></p>
            </div>
            <table class="wc-oe-ps-items">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Item', 'wc-order-entry'); ?></th>
                        <th class="wc-oe-ps-qty"><?php echo esc_html__('Qty', 'wc-order-entry'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($items)) : ?>
                    <?php foreach ($items as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row['description']); ?></td>
                            <td class="wc-oe-ps-qty"><?php echo esc_html($row['qty']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="2"><?php echo esc_html__('No line items.', 'wc-order-entry'); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate packing slip PDF from data.
     *
     * @return string|false
     */
    private function generate_packing_slip_pdf($data, $order_id) {
        $autoload = WC_OE_PLUGIN_PATH . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            return false;
        }
        require_once $autoload;

        if (!class_exists('Dompdf\Dompdf')) {
            return false;
        }

        $html = $this->generate_packing_slip_html($data, $order_id);
        // 4×6 in. label (72 pt per inch): 288 × 432 pt — standard shipping label size.
        $paper_4x6 = array(0, 0, 4 * 72, 6 * 72);
        $full_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>html,body{margin:0;padding:0;}</style></head><body>' . $html . '</body></html>';

        try {
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($full_html);
            $dompdf->setPaper($paper_4x6, 'portrait');
            $dompdf->render();
            $pdf_content = $dompdf->output();

            $temp_file = wp_tempnam('packing-slip-' . $order_id . '.pdf');
            if ($temp_file && file_put_contents($temp_file, $pdf_content)) {
                $pdf_file = preg_replace('/\.tmp$/i', '.pdf', $temp_file);
                if (rename($temp_file, $pdf_file)) {
                    return $pdf_file;
                }
                return $temp_file;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }
    
    /**
     * Get tracking info from order. Supports WooCommerce Shipment Tracking, Veeqo, and common meta keys.
     */
    private function get_order_tracking($order) {
        $items = array();
        
        // WooCommerce Shipment Tracking / Advanced Shipment Tracking
        $tracking_items = $order->get_meta('_wc_shipment_tracking_items');
        if (!empty($tracking_items) && is_array($tracking_items)) {
            foreach ($tracking_items as $item) {
                $tracking_number = isset($item['tracking_number']) ? $item['tracking_number'] : '';
                $provider = isset($item['tracking_provider']) ? $item['tracking_provider'] : (isset($item['custom_tracking_provider']) ? $item['custom_tracking_provider'] : '');
                if (!empty($tracking_number)) {
                    $items[] = array('carrier' => $provider, 'number' => $tracking_number);
                }
            }
        }
        
        // Veeqo and other plugins - direct meta
        if (empty($items)) {
            $tracking_number = $order->get_meta('_tracking_number');
            if (empty($tracking_number)) {
                $tracking_number = $order->get_meta('tracking_number');
            }
            $carrier = $order->get_meta('_tracking_provider');
            if (empty($carrier)) {
                $carrier = $order->get_meta('_carrier');
            }
            if (empty($carrier)) {
                $carrier = $order->get_meta('carrier');
            }
            if (!empty($tracking_number)) {
                $items[] = array('carrier' => $carrier ?: __('Carrier', 'wc-order-entry'), 'number' => $tracking_number);
            }
        }
        
        return $items;
    }
    
    /**
     * Whether a fee line is the Order Entry shipping charge (matches creation + English fallback).
     *
     * @param WC_Order_Item_Fee $item
     * @return bool
     */
    private function is_invoice_shipping_fee_item($item) {
        if (!$item->is_type('fee')) {
            return false;
        }
        $name = $item->get_name();
        if ($name === 'Shipping') {
            return true;
        }
        return $name === __('Shipping', 'wc-order-entry');
    }

    /**
     * Build invoice data array from a WooCommerce order.
     */
    private function build_invoice_data_from_order($order) {
        $customer = array(
            'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: $order->get_formatted_billing_full_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'address_1' => $order->get_billing_address_1(),
            'address_2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'po_number' => $order->get_meta('_po_number')
        );
        
        $products = array();
        $subtotal = 0;
        $shipping_amount = floatval($order->get_meta('_shipping_charge'));

        // Must include 'fee' — default get_items() is line_item only, so shipping fees were skipped.
        $line_and_fees = $order->get_items(array('line_item', 'fee'));
        foreach ($line_and_fees as $item) {
            if ($item->is_type('line_item')) {
                $name = $item->get_name();
                $qty = $item->get_quantity();
                $total = (float) $item->get_total();
                $price = $qty > 0 ? $total / $qty : 0;
                $subtotal += $total;
                $products[] = array('name' => $name, 'quantity' => $qty, 'price' => $price, 'total' => $total);
                continue;
            }
            if ($item->is_type('fee') && $this->is_invoice_shipping_fee_item($item)) {
                $shipping_amount = (float) $item->get_total();
            }
        }

        // Native WC shipping lines (orders not using the plugin fee).
        if ($shipping_amount <= 0) {
            $wc_ship = (float) $order->get_shipping_total();
            if ($wc_ship > 0) {
                $shipping_amount = $wc_ship;
            }
        }

        return array(
            'customer' => $customer,
            'products' => $products,
            'shipping_amount' => $shipping_amount,
            'subtotal' => $subtotal,
            'total' => $order->get_total()
        );
    }
    
    /**
     * Find existing real order IDs (legacy + HPOS) that already use a PO value.
     *
     * @param string $po
     * @return array<int>
     */
    private function get_order_ids_with_po_number($po) {
        global $wpdb;
        
        $norm = $this->normalize_po_number($po);
        if ($norm === '') {
            return array();
        }
        
        $meta_keys = array('_po_number', 'PO Number');
        $value_variants = array_unique(array($norm, '#' . $norm));
        $order_ids = array();
        
        // Legacy storage: wp_postmeta + wp_posts.
        $legacy_sql = $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key IN (%s, %s)
               AND pm.meta_value IN (%s, %s)
               AND p.post_type = 'shop_order'
               AND p.post_status NOT IN ('trash', 'auto-draft', 'draft')",
            $meta_keys[0],
            $meta_keys[1],
            $value_variants[0],
            $value_variants[1]
        );
        $legacy_ids = $wpdb->get_col($legacy_sql);
        if (!empty($legacy_ids)) {
            $order_ids = array_merge($order_ids, array_map('intval', $legacy_ids));
        }
        
        // HPOS storage: wp_wc_orders_meta + wp_wc_orders (if tables exist).
        $hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
        $hpos_orders_table = $wpdb->prefix . 'wc_orders';
        $has_hpos_tables = (
            $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $hpos_meta_table)) === $hpos_meta_table &&
            $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $hpos_orders_table)) === $hpos_orders_table
        );
        
        if ($has_hpos_tables) {
            $hpos_sql = $wpdb->prepare(
                "SELECT DISTINCT om.order_id
                 FROM {$hpos_meta_table} om
                 INNER JOIN {$hpos_orders_table} o ON o.id = om.order_id
                 WHERE om.meta_key IN (%s, %s)
                   AND om.meta_value IN (%s, %s)
                   AND o.type = 'shop_order'
                   AND o.status NOT IN ('trash', 'auto-draft', 'draft', 'checkout-draft')",
                $meta_keys[0],
                $meta_keys[1],
                $value_variants[0],
                $value_variants[1]
            );
            $hpos_ids = $wpdb->get_col($hpos_sql);
            if (!empty($hpos_ids)) {
                $order_ids = array_merge($order_ids, array_map('intval', $hpos_ids));
            }
        }
        
        return array_values(array_unique(array_filter($order_ids)));
    }
    
    /**
     * Find one existing order ID that already uses a PO number.
     *
     * @param string $po
     * @return int
     */
    private function find_duplicate_order_id_by_po_number($po) {
        $order_ids = $this->get_order_ids_with_po_number($po);
        if (empty($order_ids)) {
            return 0;
        }
        return intval($order_ids[0]);
    }
    
    /**
     * AJAX: check PO duplicate before submit (optional UX).
     */
    public function ajax_check_po_duplicate() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        $po_raw = isset($_POST['po_number']) ? wp_unslash($_POST['po_number']) : '';
        $po = $this->normalize_po_number($po_raw);
        if ($po === '') {
            wp_send_json_success(array('duplicate' => false));
        }
        
        $duplicate_order_id = $this->find_duplicate_order_id_by_po_number($po);
        if ($duplicate_order_id > 0) {
            wp_send_json_success(array(
                'duplicate' => true,
                'message' => sprintf(
                    /* translators: 1: PO number, 2: existing order id */
                    __('An order with PO number %1$s already exists (Order #%2$d).', 'wc-order-entry'),
                    $po,
                    $duplicate_order_id
                ),
            ));
        }
        
        wp_send_json_success(array('duplicate' => false));
    }
    
    public function ajax_create_order() {
        check_ajax_referer('wc_oe_nonce', 'nonce');
        
        $retailer_id = isset($_POST['retailer_id']) ? intval($_POST['retailer_id']) : 0;
        $shipping_raw = isset($_POST['shipping_amount']) ? wp_unslash($_POST['shipping_amount']) : '';
        $po_number = $this->normalize_po_number(isset($_POST['po_number']) ? wp_unslash($_POST['po_number']) : '');
        $shipping_quote_id = isset($_POST['shipping_quote_id']) ? sanitize_text_field(wp_unslash($_POST['shipping_quote_id'])) : '';
        $shipping_quote_carrier = isset($_POST['shipping_quote_carrier']) ? sanitize_text_field(wp_unslash($_POST['shipping_quote_carrier'])) : '';
        $shipping_quote_service = isset($_POST['shipping_quote_service']) ? sanitize_text_field(wp_unslash($_POST['shipping_quote_service'])) : '';
        $shipping_quote_amount = isset($_POST['shipping_quote_amount']) ? floatval($_POST['shipping_quote_amount']) : 0;
        $package_weight = isset($_POST['package_weight']) ? floatval($_POST['package_weight']) : 0;
        $package_length = isset($_POST['package_length']) ? floatval($_POST['package_length']) : 0;
        $package_width = isset($_POST['package_width']) ? floatval($_POST['package_width']) : 0;
        $package_height = isset($_POST['package_height']) ? floatval($_POST['package_height']) : 0;
        
        $duplicate_order_id = $this->find_duplicate_order_id_by_po_number($po_number);
        if ($po_number !== '' && $duplicate_order_id > 0) {
            wp_send_json_error(
                sprintf(
                    /* translators: 1: PO number, 2: existing order id */
                    __('An order with PO number %1$s already exists (Order #%2$d). Use a different PO number.', 'wc-order-entry'),
                    $po_number,
                    $duplicate_order_id
                )
            );
        }
        
        if ($retailer_id > 0) {
            if ($shipping_raw === '' || trim($shipping_raw) === '') {
                wp_send_json_error(__('Shipping amount is required when a retailer is selected.', 'wc-order-entry'));
            }
            if (! is_numeric(trim($shipping_raw))) {
                wp_send_json_error(__('Enter a valid shipping amount.', 'wc-order-entry'));
            }
            if (floatval($shipping_raw) < 0) {
                wp_send_json_error(__('Shipping amount cannot be negative.', 'wc-order-entry'));
            }
        }
        
        $shipping_amount = ($shipping_raw !== '' && trim($shipping_raw) !== '') ? floatval($shipping_raw) : 0;
        
        try {
            // Create new WooCommerce order
            $order = wc_create_order();
            
            // Set billing information (drop-ship customer - NOT the retailer)
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
            $product_lines = isset($_POST['product_lines']) ? $_POST['product_lines'] : array();
            foreach ($product_lines as $line) {
                $product_id = intval($line['product_id']);
                $quantity = intval($line['quantity']);
                $price = floatval($line['price']);
                $description = isset($line['description']) ? sanitize_text_field($line['description']) : '';
                $line_total = $price * $quantity;
                
                $product = wc_get_product($product_id);
                if ($product) {
                    $item = new WC_Order_Item_Product();
                    $item->set_product($product);
                    $item->set_quantity($quantity);
                    $item->set_subtotal($line_total);
                    $item->set_total($line_total);
                    $order->add_item($item);
                } elseif (!empty($description)) {
                    // Use product line item (not fee) so Veeqo and other fulfillment integrations can sync.
                    // Fee items lack product_id/sellable_id and cause "issues with their data" errors.
                    $custom_product = $this->get_or_create_custom_product();
                    if ($custom_product) {
                        $item = new WC_Order_Item_Product();
                        $item->set_product($custom_product);
                        $item->set_name($description);
                        $item->set_quantity($quantity);
                        $item->set_subtotal($line_total);
                        $item->set_total($line_total);
                        $order->add_item($item);
                    } else {
                        $fee = new WC_Order_Item_Fee();
                        $fee->set_name($description);
                        $fee->set_amount($line_total);
                        $fee->set_total($line_total);
                        $order->add_item($fee);
                    }
                }
            }
            
            // Add shipping as a fee (billing to retailer)
            if ($shipping_amount > 0) {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name(__('Shipping', 'wc-order-entry'));
                $fee->set_amount($shipping_amount);
                $fee->set_total($shipping_amount);
                $order->add_item($fee);
                $order->add_meta_data('_shipping_charge', $shipping_amount);
            }
            if (!empty($shipping_quote_carrier) || !empty($shipping_quote_service)) {
                $order->add_meta_data('_wc_oe_shipping_rate_id', $shipping_quote_id);
                $order->add_meta_data('_wc_oe_shipping_carrier', $shipping_quote_carrier);
                $order->add_meta_data('_wc_oe_shipping_service', $shipping_quote_service);
                $order->add_meta_data('_wc_oe_shipping_quote_amount', $shipping_quote_amount);
            }
            if ($package_weight > 0) {
                $order->add_meta_data('_wc_oe_package_weight', $package_weight);
            }
            if ($package_length > 0 && $package_width > 0 && $package_height > 0) {
                $order->add_meta_data('_wc_oe_package_length', $package_length);
                $order->add_meta_data('_wc_oe_package_width', $package_width);
                $order->add_meta_data('_wc_oe_package_height', $package_height);
            }
            
            // Add PO number as meta with underscore prefix for searchability
            if ($po_number !== '') {
                $order->add_meta_data('_po_number', $po_number);
                $order->add_meta_data('PO Number', $po_number); // For display purposes
            }
            
            // Store retailer for this order (for invoice sending)
            if ($retailer_id > 0) {
                $order->add_meta_data('_wc_oe_retailer_id', $retailer_id);
            }
            
            // Calculate totals (WooCommerce adds state sales tax when configured)
            $order->calculate_totals();
            
            // If tax checkbox was unchecked, remove tax
            $apply_tax = !empty($_POST['apply_tax']);
            if (!$apply_tax) {
                $order->remove_order_items('tax');
                $order->set_cart_tax(0);
                $order->set_shipping_tax(0);
                $order->set_total($order->get_total() - $order->get_total_tax());
            }
            
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
    
    /**
     * Generate HTML invoice (printable/save as PDF from browser).
     * Contains: customer info, products, shipping, tracking, total.
     * Sent to retailer only - NEVER to the drop-ship customer.
     */
    private function generate_invoice_html($data, $order_id, $tracking = array()) {
        $customer = $data['customer'];
        $products = $data['products'];
        $shipping = $data['shipping_amount'];
        $subtotal = $data['subtotal'];
        $total = $data['total'];
        
        $currency = get_woocommerce_currency_symbol();
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice #<?php echo esc_attr($order_id); ?></title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
                h1 { font-size: 18px; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .section { margin-bottom: 20px; }
                .section-title { font-weight: bold; font-size: 14px; margin-bottom: 8px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f5f5f5; font-weight: bold; }
                .text-right { text-align: right; }
                .totals { margin-top: 20px; }
                .invoice-totals { width: 100%; max-width: 340px; margin-left: auto; border-collapse: collapse; }
                .invoice-totals td { border: none; padding: 5px 0; }
                .invoice-totals .totals-label { text-align: right; padding-right: 15px; }
                .invoice-totals .totals-value { text-align: right; font-weight: bold; }
                .invoice-totals .total-due td { font-size: 16px; padding-top: 10px; border-top: 2px solid #333; }
                .address-block { white-space: pre-line; }
            </style>
        </head>
        <body>
            <div style="font-size: 20px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo esc_html(strtoupper(get_bloginfo('name'))); ?></div>
            <h1><?php echo esc_html__('Invoice', 'wc-order-entry'); ?> #<?php echo esc_html($order_id); ?></h1>
            
            <div class="section">
                <div class="section-title"><?php echo esc_html__('Drop-Ship Customer (Ship To)', 'wc-order-entry'); ?></div>
                <div class="address-block"><?php
                    echo esc_html($customer['name']) . "\n";
                    if (!empty($customer['email'])) echo esc_html($customer['email']) . "\n";
                    if (!empty($customer['phone'])) echo esc_html($customer['phone']) . "\n";
                    if (!empty($customer['address_1'])) echo esc_html($customer['address_1']) . "\n";
                    if (!empty($customer['address_2'])) echo esc_html($customer['address_2']) . "\n";
                    $city_state = trim(($customer['city'] ?? '') . ', ' . ($customer['state'] ?? '') . ' ' . ($customer['postcode'] ?? ''));
                    if (!empty($city_state)) echo esc_html($city_state) . "\n";
                    if (!empty($customer['country'])) echo esc_html($customer['country']) . "\n";
                    if (!empty($customer['po_number'])) echo esc_html__('PO Number:', 'wc-order-entry') . ' ' . esc_html($customer['po_number']);
                ?></div>
            </div>
            
            <div class="section">
                <div class="section-title"><?php echo esc_html__('Products', 'wc-order-entry'); ?></div>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Product', 'wc-order-entry'); ?></th>
                            <th class="text-right"><?php echo esc_html__('Qty', 'wc-order-entry'); ?></th>
                            <th class="text-right"><?php echo esc_html__('Price', 'wc-order-entry'); ?></th>
                            <th class="text-right"><?php echo esc_html__('Total', 'wc-order-entry'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?php echo esc_html($p['name']); ?></td>
                            <td class="text-right"><?php echo esc_html($p['quantity']); ?></td>
                            <td class="text-right"><?php echo $currency . number_format($p['price'], 2); ?></td>
                            <td class="text-right"><?php echo $currency . number_format($p['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($tracking)): ?>
            <div class="section">
                <div class="section-title"><?php echo esc_html__('Tracking Information', 'wc-order-entry'); ?></div>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Carrier', 'wc-order-entry'); ?></th>
                            <th><?php echo esc_html__('Tracking Number', 'wc-order-entry'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tracking as $t): ?>
                        <tr>
                            <td><?php echo esc_html($t['carrier']); ?></td>
                            <td><?php echo esc_html($t['number']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="section totals">
                <table class="invoice-totals">
                    <tr>
                        <td class="totals-label"><?php echo esc_html__('Subtotal:', 'wc-order-entry'); ?></td>
                        <td class="totals-value"><?php echo esc_html($currency . number_format($subtotal, 2)); ?></td>
                    </tr>
                    <tr>
                        <td class="totals-label"><?php echo esc_html__('Shipping:', 'wc-order-entry'); ?></td>
                        <td class="totals-value"><?php echo esc_html($currency . number_format($shipping, 2)); ?></td>
                    </tr>
                    <tr class="total-due">
                        <td class="totals-label"><?php echo esc_html__('Total Amount Due:', 'wc-order-entry'); ?></td>
                        <td class="totals-value"><?php echo esc_html($currency . number_format($total, 2)); ?></td>
                    </tr>
                </table>
            </div>
            
            <p style="margin-top: 30px; font-size: 11px; color: #666;"><?php echo esc_html__('This invoice is for the retailer. The drop-ship customer will NOT receive this email.', 'wc-order-entry'); ?></p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

// Initialize the plugin
new WC_Order_Entry();
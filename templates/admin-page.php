<?php
if (! isset($wc_states_us) || ! is_array($wc_states_us)) {
    $wc_states_us = array();
}
if (! isset($wc_states_ca) || ! is_array($wc_states_ca)) {
    $wc_states_ca = array();
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Order Entry', 'wc-order-entry'); ?></h1>
    <button type="button" id="wc-oe-new-order-btn" class="page-title-action"><?php echo esc_html__('New Order Entry', 'wc-order-entry'); ?></button>
    <hr class="wp-header-end">
    
    <div id="wc-oe-container">
        <!-- Customer Selection -->
        <div class="wc-oe-section">
            <h2>Customer Information</h2>
            
            <div class="wc-oe-row">
                <label for="saved-customers">Load Saved Customer:</label>
                <select id="saved-customers">
                    <option value="">Select a customer...</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo esc_attr($customer->id); ?>">
                            <?php echo esc_html($customer->name . (!empty($customer->email) ? ' (' . $customer->email . ')' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="update-saved-customer" class="button"><?php echo esc_html__('Update Selected', 'wc-order-entry'); ?></button>
                <button type="button" id="delete-saved-customer" class="button button-link-delete"><?php echo esc_html__('Delete Selected', 'wc-order-entry'); ?></button>
                <span id="saved-customer-result" class="wc-oe-inline-result" style="display:none;"></span>
            </div>
            
            <div class="wc-oe-row">
                <label for="paste-customer">Paste Customer Info:</label>
                <textarea id="paste-customer" placeholder="Paste customer information here..."></textarea>
                <button type="button" id="parse-customer">Parse & Fill</button>
            </div>
        </div>
        
        <!-- Customer Form -->
        <div class="wc-oe-section">
            <h3>Customer Details</h3>
            
            <div class="wc-oe-form-grid">
                <div class="wc-oe-field">
                    <label for="customer-name">Name *</label>
                    <input type="text" id="customer-name" required>
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-email">Email</label>
                    <input type="email" id="customer-email">
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-phone">Phone</label>
                    <input type="text" id="customer-phone">
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-po"><?php echo esc_html__('PO Number', 'wc-order-entry'); ?></label>
                    <input type="text" id="customer-po" autocomplete="off">
                    <p class="description wc-oe-po-dup-warning" id="wc-oe-po-dup-msg" style="display:none;color:#d63638;margin-top:6px;"></p>
                </div>
                
                <div class="wc-oe-field wc-oe-field-wide">
                    <label for="customer-address1">Address</label>
                    <input type="text" id="customer-address1">
                </div>
                
                <div class="wc-oe-field wc-oe-field-wide">
                    <label for="customer-address2">Additional Address Line</label>
                    <input type="text" id="customer-address2">
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-city">City</label>
                    <input type="text" id="customer-city">
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-state"><?php echo esc_html__('State / Province', 'wc-order-entry'); ?></label>
                    <select id="customer-state">
                        <option value=""><?php echo esc_html__('— Select —', 'wc-order-entry'); ?></option>
                        <?php if (! empty($wc_states_us)) : ?>
                            <optgroup label="<?php echo esc_attr__('United States', 'wc-order-entry'); ?>">
                                <?php foreach ($wc_states_us as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($code . ' — ' . $label); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (! empty($wc_states_ca)) : ?>
                            <optgroup label="<?php echo esc_attr__('Canada', 'wc-order-entry'); ?>">
                                <?php foreach ($wc_states_ca as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($code . ' — ' . $label); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-zip">Zip/Postal Code</label>
                    <input type="text" id="customer-zip">
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-country">Country</label>
                    <select id="customer-country">
                        <option value="US">United States</option>
                        <option value="CA">Canada</option>
                    </select>
                </div>
            </div>
            
            <div class="wc-oe-row wc-oe-checkbox-row">
                <label class="wc-oe-checkbox-label">
                    <input type="checkbox" id="save-customer" class="wc-oe-checkbox"> Save this customer for future use?
                </label>
            </div>
        </div>
        
        <!-- Product Lines -->
        <div class="wc-oe-section">
            <h3>Product Lines</h3>
            
            <div id="product-lines-container">
                <div class="product-line" data-line="1">
                    <div class="product-line-header">
                        <h4>Product Line 1</h4>
                    </div>
                    
                    <div class="product-line-fields">
                        <div class="wc-oe-field">
                            <label>Quantity</label>
                            <input type="number" class="product-quantity" min="1" value="1">
                        </div>
                        
                        <div class="wc-oe-field wc-oe-field-wide">
                            <label>SKU / Product Search</label>
                            <div class="product-search-container">
                                <input type="text" class="product-sku" placeholder="Start typing to search products...">
                                <div class="search-spinner"></div>
                            </div>
                            <div class="product-search-results"></div>
                            <input type="hidden" class="product-id">
                        </div>
                        
                        <div class="wc-oe-field wc-oe-field-wide">
                            <label>Description</label>
                            <input type="text" class="product-description">
                        </div>
                        
                        <div class="wc-oe-field">
                            <label>Price</label>
                            <input type="number" class="product-price" step="0.01" min="0" value="0.00" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="product-line-controls">
                <button type="button" id="add-product-line">Add Product Line</button>
                <button type="button" id="remove-product-line">Remove Last Line</button>
            </div>
        </div>
        
        <!-- Retailer (for invoicing) -->
        <div class="wc-oe-section">
            <h3>Retailer</h3>
            <p class="wc-oe-description"><?php echo esc_html__('Select the retailer for this order. Used when sending the invoice from the order edit page. If none selected, the invoice will not be emailed.', 'wc-order-entry'); ?></p>
            <div class="wc-oe-form-grid">
                <div class="wc-oe-field wc-oe-field-wide">
                    <label for="order-retailer"><?php echo esc_html__('Retailer', 'wc-order-entry'); ?></label>
                    <select id="order-retailer">
                        <option value=""><?php echo esc_html__('No retailer', 'wc-order-entry'); ?></option>
                        <?php foreach ($retailers as $retailer): ?>
                            <option value="<?php echo esc_attr($retailer->id); ?>"><?php echo esc_html($retailer->name . ' (' . $retailer->email . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Tax: Order Entry orders never apply sales tax (line items/fees marked non-taxable). -->
        <div class="wc-oe-section">
            <p class="wc-oe-description" style="margin:0;">
                <?php echo esc_html__('Sales tax is not calculated on orders created from this screen (even if WooCommerce tax settings are enabled). Other checkout paths are unchanged.', 'wc-order-entry'); ?>
            </p>
        </div>
        
        <!-- Shipping Charges -->
        <div class="wc-oe-section">
            <h3><?php echo esc_html__('Shipping Charges', 'wc-order-entry'); ?></h3>
            <p class="wc-oe-description"><?php echo esc_html__('Shipping cost to bill the retailer (for drop-ship orders). Required when a retailer is selected above; optional if no retailer.', 'wc-order-entry'); ?></p>
            <div class="wc-oe-form-grid">
                <div class="wc-oe-field">
                    <label for="shipping-amount"><?php echo esc_html__('Shipping Amount', 'wc-order-entry'); ?> <span class="wc-oe-shipping-req-hint" style="display:none;"><?php echo esc_html__('(required)', 'wc-order-entry'); ?></span></label>
                    <input type="number" id="shipping-amount" step="0.01" min="0" value="0" placeholder="0.00">
                </div>
                <div class="wc-oe-field">
                    <label for="pkg-weight-lbs"><?php echo esc_html__('Package Weight (lbs + oz)', 'wc-order-entry'); ?></label>
                    <div class="wc-oe-weight-split">
                        <input type="number" id="pkg-weight-lbs" step="1" min="0" placeholder="<?php echo esc_attr__('lbs', 'wc-order-entry'); ?>">
                        <input type="number" id="pkg-weight-oz" step="1" min="0" max="15" placeholder="<?php echo esc_attr__('oz (0-15)', 'wc-order-entry'); ?>">
                    </div>
                </div>
                <div class="wc-oe-field">
                    <label for="pkg-length"><?php echo esc_html__('Package Length (in)', 'wc-order-entry'); ?></label>
                    <input type="number" id="pkg-length" step="0.01" min="0" placeholder="e.g. 12">
                </div>
                <div class="wc-oe-field">
                    <label for="pkg-width"><?php echo esc_html__('Package Width (in)', 'wc-order-entry'); ?></label>
                    <input type="number" id="pkg-width" step="0.01" min="0" placeholder="e.g. 8">
                </div>
                <div class="wc-oe-field">
                    <label for="pkg-height"><?php echo esc_html__('Package Height (in)', 'wc-order-entry'); ?></label>
                    <input type="number" id="pkg-height" step="0.01" min="0" placeholder="e.g. 6">
                </div>
                <div class="wc-oe-field wc-oe-field-wide">
                    <button type="button" id="get-live-rates" class="button"><?php echo esc_html__('Get Live Rates', 'wc-order-entry'); ?></button>
                    <p class="description"><?php echo esc_html__('Uses Live Shipping plugin with destination + package values.', 'wc-order-entry'); ?></p>
                    <div id="live-rates-result" class="wc-oe-live-rates-result"></div>
                </div>
                <div class="wc-oe-field wc-oe-field-wide">
                    <label for="live-rate-select"><?php echo esc_html__('Live Rate Options', 'wc-order-entry'); ?></label>
                    <select id="live-rate-select" disabled>
                        <option value=""><?php echo esc_html__('Get live rates first...', 'wc-order-entry'); ?></option>
                    </select>
                    <p class="description"><?php echo esc_html__('Selecting an option auto-fills Shipping Amount.', 'wc-order-entry'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Manage Retailers -->
        <div class="wc-oe-section">
            <h3>Retailers</h3>
            <p class="wc-oe-description"><?php echo esc_html__('Invoices are sent from the order edit page (click "Send Invoice" after opening an order). The invoice includes customer info, products, shipping, tracking, and total. The drop-ship customer will NOT receive it.', 'wc-order-entry'); ?></p>
            
            <div class="wc-oe-row wc-oe-retailers-section">
                <h4><?php echo esc_html__('Manage Retailers', 'wc-order-entry'); ?></h4>
                <div class="wc-oe-retailers-add">
                    <input type="text" id="new-retailer-name" placeholder="<?php echo esc_attr__('Retailer name', 'wc-order-entry'); ?>">
                    <input type="email" id="new-retailer-email" placeholder="<?php echo esc_attr__('Retailer email', 'wc-order-entry'); ?>">
                    <button type="button" id="add-retailer" class="button"><?php echo esc_html__('Add Retailer', 'wc-order-entry'); ?></button>
                </div>
                <div class="wc-oe-retailers-list">
                    <?php foreach ($retailers as $retailer): ?>
                        <div class="wc-oe-retailer-item" data-id="<?php echo esc_attr($retailer->id); ?>">
                            <span><?php echo esc_html($retailer->name); ?> (<?php echo esc_html($retailer->email); ?>)</span>
                            <button type="button" class="button-link-delete delete-retailer"><?php echo esc_html__('Delete', 'wc-order-entry'); ?></button>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($retailers)): ?>
                        <p class="wc-oe-no-retailers"><?php echo esc_html__('No retailers yet. Add one above.', 'wc-order-entry'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Test Invoice -->
        <div class="wc-oe-section">
            <h3><?php echo esc_html__('Test Invoice', 'wc-order-entry'); ?></h3>
            <p class="wc-oe-description"><?php echo esc_html__('Send a sample invoice to any email to preview how it looks.', 'wc-order-entry'); ?></p>
            <div class="wc-oe-form-grid">
                <div class="wc-oe-field">
                    <label for="test-invoice-email"><?php echo esc_html__('Email address', 'wc-order-entry'); ?></label>
                    <input type="email" id="test-invoice-email" placeholder="you@example.com">
                </div>
                <div class="wc-oe-field" style="display: flex; align-items: flex-end;">
                    <button type="button" id="send-test-invoice" class="button"><?php echo esc_html__('Send Test Invoice', 'wc-order-entry'); ?></button>
                </div>
            </div>
            <div id="test-invoice-result" style="margin-top: 10px;"></div>
        </div>
        
        <!-- Submit Section -->
        <div class="wc-oe-section">
            <button type="button" id="submit-order" class="button-primary">Create Order</button>
            <div id="order-result"></div>
        </div>
        
        <footer class="wc-oe-footer" role="contentinfo">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: plugin version */
                        __('WooCommerce Order Entry — version %s', 'wc-order-entry'),
                        defined('WC_OE_VERSION') ? WC_OE_VERSION : ''
                    )
                );
                ?>
            </p>
        </footer>
    </div>
</div>
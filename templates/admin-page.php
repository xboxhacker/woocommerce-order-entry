<div class="wrap">
    <h1>Order Entry</h1>
    
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
                            <?php echo esc_html($customer->name . ' (' . $customer->email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                    <label for="customer-email">Email *</label>
                    <input type="email" id="customer-email" required>
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-phone">Phone</label>
                    <input type="text" id="customer-phone">
                </div>
                
                <div class="wc-oe-field">
                    <label for="customer-po">PO Number</label>
                    <input type="text" id="customer-po">
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
                    <label for="customer-state">State/Province</label>
                    <input type="text" id="customer-state">
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
                            <input type="number" class="product-price" step="0.01" min="0">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="product-line-controls">
                <button type="button" id="add-product-line">Add Product Line</button>
                <button type="button" id="remove-product-line">Remove Last Line</button>
            </div>
        </div>
        
        <!-- Submit Section -->
        <div class="wc-oe-section">
            <button type="button" id="submit-order" class="button-primary">Create Order</button>
            <div id="order-result"></div>
        </div>
    </div>
</div>
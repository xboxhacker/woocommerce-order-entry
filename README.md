# WooCommerce Order Entry Plugin

A comprehensive WordPress plugin that allows administrators to manually create WooCommerce orders through an intuitive GUI interface with a copy and paste customer address option. No need to type out the customer information.

## Features

### Customer Management
- Manual customer information entry with all required fields
- Save customers to database for future use
- Load previously saved customers from dropdown
- Parse customer information from pasted text with enhanced address parsing
- Automatic field population based on parsed data
- **Country selection between United States and Canada** (defaults to United States)

### Product Management
- Dynamic product line creation with quantity, SKU, description, and price
- Real-time product search by SKU or name
- Product selection from WooCommerce inventory
- Add/remove product lines with validation
- Automatic price population from selected products

### Order Processing
- One-click order creation
- Automatic order status set to "Processing"
- Direct link to created order for review
- Complete integration with WooCommerce order system
- **PO Number integration**: Display and search functionality in WooCommerce orders

## Installation

1. Upload the plugin files to `/wp-content/plugins/woocommerce-order-entry/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce → Order Entry to start creating orders

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.0 or higher

## Usage

### Creating an Order

1. **Customer Information**
   - Use the dropdown to select a saved customer, or
   - Paste customer information in the text area and click "Parse & Fill", or
   - Manually enter customer details

2. **Product Lines**
   - Start typing in the SKU field to search for products
   - Select products from the search results
   - Adjust quantity and pricing as needed
   - Add additional product lines using the "Add Product Line" button

3. **Submit Order**
   - Review all information
   - Check "Save this customer for future use?" if you want to store customer information
   - Click "Create Order" to submit

4. **Order Confirmation**
   - View the success message with order number
   - Click the order link to review the created order in WooCommerce

### Country Selection

The plugin supports two countries:
- **United States** (default selection)
- **Canada**

The country selection affects:
- Form labels (State/Province, Zip/Postal Code)
- Address parsing logic for postal codes
- Order data stored in WooCommerce

### PO Number Functionality

When a PO number is entered in the order form:
- **Display**: Appears in the order details page in the WooCommerce admin
- **Search**: Can be searched from the WooCommerce orders list
- **Column**: Shows in a dedicated "PO Number" column in the orders list
- **Storage**: Stored as order metadata for easy retrieval

### Enhanced Customer Text Parsing

The plugin can automatically parse customer information from pasted text. It recognizes:
- **Names**: First line typically contains the full name
- **Street Addresses**: Lines containing numbers and street indicators (St., Ave., Rd., etc.)
- **City/State/Zip**: Format like "City, ST 12345" (US) or "City, ON K1A 0A9" (Canada)
- **Email addresses**: Valid email format detection
- **Phone numbers**: Various phone number patterns
- **Postal codes**: 
  - US format: 12345 or 12345-6789
  - Canadian format: K1A 0A9 or K1A0A9

**Example Formats:**

*United States:*
```
John Doe
17700 Main St.
Kansas City, KS 66219
john.doe@email.com
(555) 123-4567
```

*Canada:*
```
Jane Smith
123 Maple Street
Toronto, ON M5V 3A8
jane.smith@email.com
(416) 555-0123
```

### Keyboard Shortcuts

- Tab through form fields for quick data entry
- Enter key activates product search
- Escape key closes search results

## Technical Details

### Database Schema

The plugin creates a custom table `wp_wc_oe_customers` to store saved customer information:

```sql
CREATE TABLE wp_wc_oe_customers (
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
);
```

### AJAX Endpoints

- `wc_oe_search_products` - Product search functionality
- `wc_oe_save_customer` - Save customer to database
- `wc_oe_load_customer` - Load saved customer data
- `wc_oe_parse_customer` - Parse customer information from text
- `wc_oe_create_order` - Create WooCommerce order

### WooCommerce Integration

The plugin extends WooCommerce functionality by:
- Adding PO number display in order details
- Including PO number in order search functionality
- Adding PO number column to orders list
- Storing PO number as searchable order metadata
- Supporting US and Canadian address formats

### File Structure

```
woocommerce-order-entry/
├── woocommerce-order-entry.php (Main plugin file)
├── templates/
│   └── admin-page.php (Admin interface template)
├── assets/
│   ├── admin.css (Styling)
│   └── admin.js (JavaScript functionality)
└── README.md
```

## Security Features

- Nonce verification for all AJAX requests
- Data sanitization and validation
- Capability checks for admin access
- SQL injection prevention with prepared statements

## Customization

### Extending Customer Parsing

The customer parsing logic can be enhanced by modifying the `ajax_parse_customer()` method. Current implementation includes enhanced patterns for:
- Name extraction from first line
- Street address detection with number patterns
- City/State/Zip parsing with regex for both US and Canadian formats
- Email and phone detection
- Automatic country detection based on postal code format
- Sequential field assignment

### Adding Custom Fields

To add custom fields to the customer form:

1. Update the database schema in `create_customers_table()`
2. Add form fields to `templates/admin-page.php`
3. Update the save/load customer AJAX handlers
4. Modify the JavaScript to handle new fields

### Styling Customization

The plugin includes responsive CSS that can be customized by:
- Modifying `assets/admin.css`
- Adding custom CSS through WordPress admin
- Using theme customizations

## Version History

### Version 1.1.3 (2025-07-28)
- **Added Country Selection**:
  - Country dropdown with United States and Canada options
  - United States set as default selection
  - Updated form labels (State/Province, Zip/Postal Code)
  - Enhanced parsing logic to detect Canadian postal codes
  - Automatic country detection in address parsing

### Version 1.1.2 (2025-07-28)
- **Enhanced PO Number Integration**:
  - PO number now displays in WooCommerce order details page
  - Added PO number column to WooCommerce orders list
  - Made PO number searchable in WooCommerce order search
  - Improved order metadata storage for better integration

### Version 1.1.1 (2025-07-25)
- Fixed checkbox styling to be appropriately sized
- Updated text to "Save this customer for future use?" (added question mark)
- Improved checkbox alignment and visual presentation

### Version 1.1.0 (2025-07-25)
- Enhanced address parsing for formatted customer information
- Improved regex patterns for city/state/zip detection
- Better street address recognition
- Updated plugin naming and directory structure

### Version 1.0.0
- Initial release
- Customer management functionality
- Product search and selection
- Order creation with WooCommerce integration
- Responsive admin interface

## Support

For support and feature requests, please visit the plugin repository or contact the development team.

## License

This plugin is licensed under GPL v2 or later.
# WooCommerce Order Entry Plugin

## Description

The WooCommerce Order Entry plugin enhances WooCommerce by allowing manual order entry with powerful features such as customer management, automatic parsing of pasted customer data, and dynamic product lines for flexible order creation.

### Key Features
- **Customer Management**: Save and reuse customer details for quick order processing.
- **Automatic Paste Parsing**: Paste customer info (e.g., name, address, email) into a textarea, and the plugin auto-populates the form.
- **Dynamic Product Lines**: Add or remove product lines with fields for QTY, SKU, DESCRIPTION, and PRICE.
- **PO Number Support**: Optionally include a Purchase Order (PO) number with each order.
- **Shipping Method & Cost**: Specify shipping methods (e.g., UPS, USPS) and costs, included in the order total.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/` directory on your WordPress server.
2. Navigate to the **Plugins** menu in your WordPress admin dashboard and activate the plugin.
3. Ensure WooCommerce is installed and active (required for functionality).

## Usage

### Entering Customer Information
- **Paste Customer Info**: Copy customer details into the textarea. The plugin will automatically parse and fill fields like name, address, city, state, ZIP, email, and phone.
- **Select Saved Customer**: Choose a previously saved customer from the dropdown to auto-fill their details.

### Adding Product Lines
- **Initial Product Line**: Begin with one product line containing fields for QTY, SKU, DESCRIPTION, and PRICE.
- **Add More**: Click the "Add More" button to append additional product lines as needed.
- **Remove Last Line**: Use the "Remove Last Line" button to delete the last product line (available when multiple lines exist).

### Submitting Orders
- Complete all required fields, including customer info, product lines, and optional fields like PO number and shipping cost.
- Click **Submit Order** to create the order in WooCommerce, set to "Processing" status.

## Troubleshooting
- **Product Table Display Issues**: If the product table looks misaligned, clear your browser cache or inspect for CSS conflicts using developer tools.
- **Parsing Errors**: Verify that pasted customer info matches the expected format (refer to the textarea placeholder). Phone and email parsing assumes standard US formats (e.g., 10-digit phone numbers).

## Support
For assistance, reach out via [support email or link].

## Dependencies
- WooCommerce (version X.X or higher)

## Changelog
### Version 1.11
- Enhanced product table layout with fixed column widths for improved readability.
- Improved parsing logic for better detection of customer email and phone numbers.

## Known Issues
- Phone number parsing is optimized for 10-digit US formats; international numbers may not parse correctly.

## License
This plugin is licensed under the [GPL2 license].

## Documentation
For in-depth guidance, visit [documentation link].
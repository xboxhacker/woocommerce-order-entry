# WooCommerce Order Entry

WordPress / WooCommerce plugin for manually creating drop-ship and retailer orders from a dedicated admin screen. Paste customer addresses, pick products, pull live shipping rates, create the order, then view/send invoices and packing slips from the order itself.

**Current version:** 1.5.8

## Features

### Customer management
- Paste customer info and auto-fill name, address, city/state/ZIP, phone, and email
- State/province is a **2-letter dropdown** (US + Canada, including **Puerto Rico / PR**)
- Secondary address lines (`Apt`, `Suite`, `Unit`, `#…`, etc.) parse into **Additional Address Line**
- Phone parsing strips labels like `Phone:` and keeps digits only
- ZIP+4 values are not mistaken for phone numbers
- Save / load / **update** / **delete** saved customers
- Saved customers also store retailer, shipping amount, and package dimensions/weight for reuse

### Product lines
- Search WooCommerce products by SKU or name
- Quantity, description, and price per line
- Price fields default to `0.00` and clear on focus for easy paste
- Custom description-only lines become real **product line items** (placeholder `WC-OE-CUSTOM`) so fulfillment tools like Veeqo can sync them

### Retailers & invoicing
- Manage retailers (name + email) used for billing, not the drop-ship customer
- Send invoice PDF to the selected retailer from the order edit page, list row action, or bulk action
- View Invoice / Download Invoice PDF
- Invoices are built **at view/send time** from the live order (edits are reflected)
- Invoice date is the date the invoice document is generated (not the original order date)
- Test invoice send from the Order Entry screen

### Packing slips (4×6)
- View Packing Slip / Download Packing Slip PDF on the order page
- Sized for standard **4×6 inch** shipping labels
- Shows ship-to, ship-from (site name + store address), order #, date, PO, items + qty
- No prices or totals

### Shipping
- Shipping amount field (required when a retailer is selected)
- Package weight as **lbs + oz** (combined for rate calculations)
- Package length / width / height
- **Get Live Rates** via the [Live Shipping Rates for WooCommerce](../live-shipping-rates-for-woocommerce) plugin (UPS / USPS)
- Selected live rate fills Shipping Amount and stores carrier/service metadata on the order
- Shipping is stored as a **Shipping fee** line on the order

### PO numbers
- Duplicate PO check (case-insensitive; trashed orders ignored)
- PO shown on the order details page and in the orders list
- Searchable in WooCommerce admin (legacy CPT + HPOS)

### Tax policy
- Orders created from this screen **never calculate or charge sales tax**, even if WooCommerce tax settings are enabled
- Storefront / normal checkout tax behavior is unchanged

## Installation

1. Upload the plugin to `/wp-content/plugins/woocommerce-order-entry/`
2. Activate it under **Plugins**
3. Open **WooCommerce → Order Entry**

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.0+
- Optional: **Live Shipping Rates for WooCommerce** for live UPS/USPS quotes
- Dompdf is included under `vendor/` for PDF invoices and packing slips

If you manage PHP deps with Composer:

```bash
cd wp-content/plugins/woocommerce-order-entry
composer install
```

Without Dompdf, invoice emails still send HTML (printable from the email client).

## Usage

### Create an order

1. **Customer** — load a saved customer, paste + Parse & Fill, or type fields manually  
2. **Products** — search SKU/name or enter custom description lines  
3. **Retailer** — optional; if selected, shipping amount is required  
4. **Shipping** — enter amount and package details; optionally Get Live Rates  
5. **Create Order** — order is created as Processing with no tax  

Optional: check **Save this customer for future use?** (or use **Update Selected** / **Delete Selected** on a loaded customer).

### Invoices & packing slips

On the WooCommerce order edit screen:

- **View Invoice** / **Download PDF**
- **View Packing Slip** / **Download Packing Slip PDF**
- **Send Invoice** to the retailer (drop-ship customer does not receive it)

Invoices and packing slips always rebuild from the current order data.

### Address paste examples

**United States (with apt):**
```
Delmar smith
40861 N Lakeshore Blvd
Apt 101
Marquette, MI 49855
Phone: 7344784595
```

**Canada:**
```
Jane Smith
123 Maple Street
Toronto, ON M5V 3A8
jane.smith@email.com
(416) 555-0123
```

## Technical details

### Database

**`{prefix}wc_oe_customers`**
- Contact/address fields, PO
- `retailer_id`, `shipping_amount`, `package_weight`, `package_length`, `package_width`, `package_height`

**`{prefix}wc_oe_retailers`**
- `name`, `email`

### Main AJAX actions

| Action | Purpose |
|--------|---------|
| `wc_oe_search_products` | Product search |
| `wc_oe_save_customer` | Create or update saved customer |
| `wc_oe_load_customer` | Load customer (+ shipping prefs) |
| `wc_oe_delete_customer` | Delete saved customer |
| `wc_oe_parse_customer` | Parse pasted address |
| `wc_oe_check_po_duplicate` | PO uniqueness check |
| `wc_oe_get_live_rates` | Live shipping quotes |
| `wc_oe_create_order` | Create WooCommerce order |
| `wc_oe_save_retailer` / `wc_oe_delete_retailer` | Retailer CRUD |
| `wc_oe_send_invoice` / `wc_oe_send_test_invoice` | Send invoice email |

### Notable order meta

| Key | Meaning |
|-----|---------|
| `_po_number` / `PO Number` | Purchase order |
| `_shipping_charge` | Billed shipping amount |
| `_wc_oe_retailer_id` | Assigned retailer |
| `_wc_oe_shipping_carrier` / `_wc_oe_shipping_service` | Live rate used |
| `_wc_oe_shipping_quote_amount` | Live quote amount |
| `_wc_oe_package_*` | Package weight/dims |
| `_wc_oe_no_tax` | Order Entry tax-free flag |
| `_wc_oe_invoice_sent_at` | Last invoice email time |

### File structure

```
woocommerce-order-entry/
├── woocommerce-order-entry.php
├── templates/admin-page.php
├── assets/
│   ├── admin.css
│   ├── admin.js
│   └── order-edit.js
├── vendor/                 (Dompdf + deps)
├── composer.json
└── README.md
```

## Security

- Nonce checks on AJAX and admin actions
- Capability checks (`manage_woocommerce` where appropriate)
- Sanitized / prepared database access
- Invoice emails go to retailers only (not the drop-ship customer)

## Version history

### 1.5.8
- Removed the `init` hook entirely so scanners no longer flag DB work on every page load
- Hooks register on `plugins_loaded`; schema upgrades stay on `admin_init` / activation only

### 1.5.7
- Schema upgrades no longer run `dbDelta` on every `init` / page load
- Dropped unused Dompdf DejaVu fonts; PDFs use built-in Helvetica (smaller plugin)

### 1.5.6
- Invoice HTML/PDF shows **Invoice Date** = date the invoice is generated

### 1.5.5
- Address parser: secondary lines (`Apt` / `Suite` / etc.) → Additional Address Line
- Phone parsing: strip `Phone:`-style labels; store digits only

### 1.5.3 – 1.5.4
- Hard no-tax policy for Order Entry (retail) orders
- Tax kill-switch during create + `_wc_oe_no_tax` safety net on recalculate
- Puerto Rico (`PR`) in state dropdown and parser map

### 1.5.1 – 1.5.2
- Invoices always rebuilt at view/send from live order data
- Fixed order total math when tax was incorrectly baked into totals
- Shipping fee naming and invoice totals consistency

### 1.4.x – 1.5.0
- Live shipping rates integration (lbs+oz package weight)
- Packing slip view/download (4×6 label size)
- Saved customer edit/delete; shipping prefs on save/load
- Ship-from uses site name (not dealer name)

### 1.3.x – 1.4.0
- Retailer shipping amount required when retailer selected
- PO duplicate validation (HPOS-safe)
- Custom lines as product items for Veeqo
- Invoice send from order page / list / bulk

### 1.1.x – 1.2.x
- US/Canada country support
- PO display, search, and list column
- Improved address parsing and UI polish

### 1.0.0
- Initial release: customer form, product search, order create

## License

GPL v2 or later.

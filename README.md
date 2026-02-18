# ANZ Worldline Payment Gateway for WooCommerce

A secure WooCommerce payment gateway plugin for ANZ Worldline Payment Solutions. Customers are redirected to a secure hosted checkout page to complete their payment.

## Features

- **Hosted Checkout** - Secure payment page hosted by ANZ Worldline (PCI DSS compliant)
- **Multiple Card Support** - Visa, Mastercard, and more
- **3D Secure 2.0** - Built-in support for frictionless and challenge flows
- **Authorize & Capture** - Choose between immediate capture or authorize-only
- **Fraud Prevention** - Configurable payment retry limits
- **Dual Environment** - Separate credentials for test and live modes
- **Transaction Logging** - Built-in admin log viewer
- **HPOS Compatible** - Supports WooCommerce High-Performance Order Storage

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- SSL certificate (HTTPS required)
- ANZ Worldline Merchant Account

## Installation

### Via Composer (Recommended)

1. Upload the plugin folder to `/wp-content/plugins/`
2. Navigate to the plugin directory:
   ```bash
   cd wp-content/plugins/anz-worldline-gateway
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Activate the plugin in WordPress admin

### Manual Installation

1. Download the plugin
2. Upload to `/wp-content/plugins/anz-worldline-gateway/`
3. Run `composer install` in the plugin directory
4. Activate via **Plugins** menu in WordPress

## Configuration

### 1. Access Settings

Navigate to **WooCommerce > Settings > Payments > ANZ Worldline**

### 2. Basic Settings

| Setting | Description |
|---------|-------------|
| **Enable/Disable** | Toggle the payment gateway on/off |
| **Title** | Payment method name shown at checkout (default: "Credit Card (ANZ Worldline)") |
| **Description** | Text shown below the payment method |
| **Test Mode** | Enable to use sandbox environment |

### 3. Payment Options

| Setting | Description |
|---------|-------------|
| **Payment Action** | Choose between "Authorize & Capture" (immediate charge) or "Authorize Only" (capture later) |
| **Max Payment Attempts** | Limit retry attempts (1-10) to reduce fraud and transaction costs |
| **Template Variant** | Custom hosted checkout template name (optional, provided by ANZ after uploading your template) |

### 4. Credentials

The plugin supports separate credentials for test and live environments:

**Live/Production Credentials:**
- Live Merchant ID (PSPID)
- Live API Key
- Live API Secret

**Test/Sandbox Credentials:**
- Test Merchant ID (PSPID)
- Test API Key
- Test API Secret

Get your credentials from the [ANZ Worldline Merchant Portal](https://merchant.anzworldline-solutions.com.au/).

## Payment Flow

1. Customer selects "Credit Card (ANZ Worldline)" at checkout
2. Customer clicks "Place Order"
3. Customer is redirected to ANZ Worldline hosted payment page
4. Customer enters card details and completes 3D Secure (if required)
5. Customer is redirected back to your store
6. Order status is updated based on payment result

## Status Codes

### Successful Transactions

| Code | Status | Description |
|------|--------|-------------|
| 5 | Authorized | Payment authorized (Authorize Only mode) |
| 9 | Captured | Payment completed (Authorize & Capture mode) |
| 8 | Refunded | Refund successful |

### Failed Transactions

| Code | Status | Description |
|------|--------|-------------|
| 2 | Declined | Transaction declined by issuer |
| 1 | Cancelled | Transaction cancelled by customer |
| 57/59 | Blocked | Rejected by fraud prevention |
| 83 | Refund Failed | Refund was rejected |

### Pending Transactions

| Code | Status | Description |
|------|--------|-------------|
| 51 | Pending | Authorization pending |
| 52 | Uncertain | Transaction outcome uncertain |
| 82 | Refund Uncertain | Refund status uncertain |

## Test Cards

Use these card numbers in test mode:

### Successful Payments (Visa)

| Card Number | 3D Secure Flow |
|-------------|----------------|
| 4330264936344675 | Frictionless (no challenge) |
| 4874970686672022 | Challenge flow |

### Successful Payments (Mastercard)

| Card Number | 3D Secure Flow |
|-------------|----------------|
| 5137009801943438 | Frictionless (no challenge) |
| 5130257474533310 | Challenge flow |

### Declined Payments

| Card Number | Result |
|-------------|--------|
| 5168645305790452 | Declined (frictionless) |
| 5144144373781246 | Declined (challenge) |

**CVV:** Use any 3 or 4 digit number
**Expiry:** Use any future date

For more test scenarios, see [ANZ Worldline Test Cases](https://docs.anzworldline-solutions.com.au/en/integration/how-to-integrate/test-cases/).

## Transaction Logs

### Viewing Logs

Navigate to **WooCommerce > ANZ Worldline Logs** to view transaction logs.

The log viewer shows:
- Timestamp
- Log level (INFO, WARNING, ERROR)
- Message
- Context data (order ID, payment ID, status codes)

### Log Retention

- Logs are stored in the WordPress database
- Maximum 100 entries retained (oldest automatically removed)
- Logs can be cleared manually via the "Clear All Logs" button

### WooCommerce Logs

Additional logs are written to WooCommerce log files:
```
wp-content/uploads/wc-logs/anz-worldline-*.log
```

Access via **WooCommerce > Status > Logs**

## Security Features

### Callback Verification

- **RETURNMAC Validation** - All payment callbacks are verified using a cryptographic token
- **Timing-Safe Comparison** - Prevents timing attacks on token verification

### Data Protection

- API credentials stored securely in WordPress options
- API keys/secrets masked in admin interface
- Sensitive data (redirect URLs, tokens) not logged
- Generic error messages shown to customers (detailed errors logged internally)

### Input Validation

- All user inputs sanitized
- All outputs escaped
- CSRF protection via WordPress nonces
- Capability checks on admin functions

## Customization

### Hosted Checkout Template (Variant)

You can customize the ANZ Worldline hosted checkout page to match your brand.

**Steps to use a custom template:**

1. **Create your template** using one of these methods:
   - Use the ANZ Worldline Template Builder in the Merchant Portal
   - Download and customize templates from [GitHub](https://github.com/Online-Payments/hostedcheckout-templates)

2. **Upload to ANZ Worldline:**
   - Contact ANZ Worldline support to upload your custom template
   - They will provide you with a **variant name**

3. **Configure in WordPress:**
   - Go to **WooCommerce > Settings > Payments > ANZ Worldline**
   - Enter your variant name in the **Template Variant** field
   - Save changes

**Example:**
```
Template Variant: [MyStoreCheckout]
```

The variant is applied via `hostedCheckoutSpecificInput.variant` in the API request.

### Payment Method Styling

The plugin includes custom CSS for prominent checkout display:
- Blue highlighted border
- Gradient background
- "Secure" badge indicator
- Large, visible radio button
- Card brand logos (Visa, Mastercard)

### Adding Payment Method Icons

Place logo images in:
```
/wp-content/plugins/anz-worldline-gateway/assets/images/
```

Supported files:
- `visa.png`
- `mastercard.png`

Recommended image size: 64-300px width for optimal retina display.

### Hooks & Filters

```php
// Customize gateway icon
add_filter('woocommerce_gateway_icon', function($icon, $gateway_id) {
    if ($gateway_id === 'anz_worldline') {
        // Customize icon HTML
    }
    return $icon;
}, 10, 2);
```

## Troubleshooting

### "Payment was not successful" Error

**Cause:** Usually indicates a status code mismatch or verification failure.

**Solution:**
1. Check the transaction logs for the actual status code
2. Verify your API credentials are correct
3. Ensure you're using the correct environment (test vs live)

### Payment Method Not Showing

**Cause:** Gateway disabled or credentials missing.

**Solution:**
1. Ensure the gateway is enabled in WooCommerce settings
2. Verify all required credentials are entered
3. Check that you're using the correct credentials for the current mode

### "Composer dependencies missing" Notice

**Solution:**
```bash
cd wp-content/plugins/anz-worldline-gateway
composer install
```

### RETURNMAC Verification Failed

**Cause:** Callback URL mismatch or session issue.

**Solution:**
1. Ensure your site URL is consistent (www vs non-www)
2. Check SSL certificate is valid
3. Clear any caching plugins during testing

## API Reference

### Endpoints Used

| Environment | Endpoint |
|-------------|----------|
| Test/Sandbox | `https://payment.preprod.anzworldline-solutions.com.au` |
| Production | `https://payment.anzworldline-solutions.com.au` |

### SDK

This plugin uses the official Worldline Online Payments PHP SDK:
- Package: `online-payments/sdk-php`
- Version: ^5.0

## Changelog

### 1.0.0 (2024)

**Initial Release**
- Hosted checkout integration
- Authorize & Capture / Authorize Only modes
- Payment retry limits
- Separate test/live credentials
- Transaction logging with admin viewer
- 3D Secure 2.0 support
- HPOS compatibility
- Custom checkout styling
- Security: RETURNMAC verification
- Security: Input sanitization & output escaping
- Visa & Mastercard logo display

## Support

### Documentation

- [ANZ Worldline Integration Guide](https://docs.anzworldline-solutions.com.au/)
- [API Reference](https://docs.anzworldline-solutions.com.au/en/integration/api-developer-guide/)
- [Test Cases](https://docs.anzworldline-solutions.com.au/en/integration/how-to-integrate/test-cases/)

### Contact

For ANZ Worldline account issues, contact ANZ Worldline support.

For plugin issues, check the transaction logs and WooCommerce logs for debugging information.

## License

GPL-2.0-or-later

## Credits

- ANZ Worldline Payment Solutions
- Worldline Online Payments PHP SDK

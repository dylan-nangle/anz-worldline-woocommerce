=== ANZ Worldline Payment Gateway ===
Contributors: yourcompany
Tags: woocommerce, payment, gateway, anz, worldline, credit card
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
WC requires at least: 5.0
WC tested up to: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments via ANZ Worldline Payment Solutions Hosted Checkout.

== Description ==

This plugin enables WooCommerce stores to accept payments through ANZ Worldline Payment Solutions using their secure Hosted Checkout Page.

**Features:**

* Secure hosted checkout - card details are entered on ANZ's PCI-compliant payment page
* Support for all major credit and debit cards
* Test mode for development and testing
* Automatic order status updates

**Requirements:**

* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* ANZ Worldline merchant account with API credentials

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Navigate to the plugin directory and run `composer install` to install dependencies
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to WooCommerce > Settings > Payments > ANZ Worldline
5. Enter your Merchant ID, API Key, and API Secret from the ANZ Worldline Merchant Portal
6. Enable test mode for initial testing
7. Enable the payment gateway

== Configuration ==

1. Log in to your ANZ Worldline Merchant Portal
2. Navigate to Business > Payment methods and ensure your desired payment methods are active
3. Go to your account settings to find your API Key and API Secret
4. Enter these credentials in the WooCommerce payment gateway settings

== Frequently Asked Questions ==

= Where do I get my API credentials? =

Log in to the ANZ Worldline Merchant Portal and navigate to your account settings to find your API Key and API Secret.

= How do I test the integration? =

Enable "Test Mode" in the gateway settings. This will use ANZ's test environment where you can use test card numbers without processing real transactions.

= What currencies are supported? =

The gateway supports all currencies available in your ANZ Worldline merchant account. AUD is the default for Australian merchants.

== Changelog ==

= 1.0.0 =
* Initial release
* Hosted Checkout Page integration
* Test and live mode support
* Automatic payment verification

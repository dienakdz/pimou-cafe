=== Coolbird Vietnam Address for WooCommerce ===
Contributors: coolbird
Donate link: https://github.com/coolbirdzik/coolbird-vietnam-address-for-woocommerce
Tags: woocommerce, vietnam, checkout, address
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Optimize WooCommerce checkout for the Vietnamese market with Province/City, District, Ward dropdown selectors.

== Description ==

This plugin replaces the default WooCommerce address fields with standard Vietnamese address selectors.

**Features:**
- Province/City, District, Ward dropdowns with complete administrative data
- Auto-load lists based on selected level
- Combine First & Last Name into a single "Full Name" field
- Add recipient phone number field
- Supports both classic checkout and Woo Checkout Blocks
- Optional VND currency display
- Shipping fee configuration by Province/City
- No new database tables, data stored in PHP arrays

**Requirements:**
- WordPress 5.0+
- WooCommerce 8.0+
- PHP 7.4+

== Installation ==

1. Download plugin or install from Plugins → Add New
2. Activate the plugin
3. Go to Settings → Vietnam Checkout to customize

== Frequently Asked Questions ==

= Is the plugin free? =
Yes. Free and open source under GPLv3.

= Is address data updated? =
Yes, regularly updated from official sources.

= Does the plugin slow down the website? =
No. Data is stored in PHP arrays, no database queries.

== Source Code & Development ==

This plugin includes minified JavaScript bundles for production use. The original human-readable source code is available in our public GitHub repository:

**Repository:** https://github.com/coolbirdzik/coolbird-vietnam-address-for-woocommerce

**Frontend Source:** Located in the `frontend/` directory
- Built with TypeScript, React, and Vite
- Source files: `frontend/src/`
- Entry points: `frontend/src/main-checkout.tsx`, `frontend/src/main-admin-order.tsx`, `frontend/src/main-admin-shipping.tsx`

**To build from source:**
1. Clone the repository
2. Run `cd frontend && npm install && npm run build`
3. Built assets will be in `assets/dist/`

== Changelog ==

= 1.0.0 - 2026-02-25 =
- Initial release
- Province/City, District, Ward selectors
- Checkout optimization and phone field addition

== Additional Information ==

- GitHub: https://github.com/coolbirdzik/coolbird-vietnam-address-for-woocommerce
- Developed by: Nguyen Tan Hung
- Does not send customer data externally

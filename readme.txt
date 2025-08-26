=== BabaProds ===
Contributors: meharumar
Tags: alibaba, wholesale, woocommerce, import, affiliate
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import products from Alibaba wholesale platform directly into your WooCommerce store with professional UI and affiliate integration.

== Description ==

BabaProds is a comprehensive WordPress plugin that seamlessly integrates with Alibaba's wholesale platform API, allowing you to import products directly into your WooCommerce store. With a professional, highly interactive UI and attractive animations, BabaProds makes wholesale product management effortless.

= Key Features =

* **Single Product Import**: Import individual products using Alibaba product URLs
* **Bulk Product Search**: Search and import multiple products by keyword with pagination
* **Professional UI**: Modern, responsive interface with smooth animations
* **Automatic Token Management**: Handles API authentication and token refresh automatically
* **WooCommerce Integration**: Creates external/affiliate products in WooCommerce
* **Image Import**: Automatically downloads and sets product images
* **Pricing Tiers**: Supports Alibaba's ladder pricing structure
* **Supplier Information**: Includes supplier details and contact information
* **Affiliate Links**: Generates affiliate links for commission tracking

= API Integration =

BabaProds uses Alibaba's official API with the following endpoints:
* Product Description API for detailed product information
* Product Search API for keyword-based product discovery
* Token Refresh API for maintaining authentication

= Requirements =

* WordPress 5.0 or higher
* WooCommerce plugin installed and activated
* PHP 7.4 or higher
* Valid Alibaba API credentials

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/babaprods` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to BabaProds > Settings to configure your API credentials
4. Start importing products from the main BabaProds page

== Configuration ==

1. Obtain your Alibaba API credentials (access_token, refresh_token, etc.)
2. Go to BabaProds > Settings
3. Paste your credentials in JSON format
4. Save the settings
5. The plugin will automatically handle token refresh when needed

== Usage ==

= Single Product Import =
1. Go to BabaProds in your WordPress admin
2. Click the "Single Product" tab
3. Paste an Alibaba product URL
4. Click "Fetch Product"
5. Review and edit the product details
6. Click "Add to Store" to import

= Bulk Product Search =
1. Click the "Search Products" tab
2. Enter a search keyword
3. Browse the results with pagination
4. Click on any product to view details
5. Import selected products to your store

== Frequently Asked Questions ==

= Do I need an Alibaba API account? =

Yes, you need valid Alibaba API credentials to use this plugin. Contact Alibaba to obtain your API access.

= Does this work with any WooCommerce theme? =

Yes, BabaProds creates standard WooCommerce external products that work with any theme.

= Can I customize the imported product data? =

Yes, you can edit all product details before importing, including title, description, pricing, and more.

= Are product images automatically imported? =

Yes, the plugin automatically downloads and sets product images from Alibaba.

== Screenshots ==

1. Main plugin interface with tabs for single product and bulk search
2. Single product import with detailed product information
3. Search results with pagination and product grid
4. Product details modal with editable fields
5. Settings page for API credential management

== Changelog ==

= 2.0 =
* Complete rewrite with modern architecture
* Professional UI with animations and responsive design
* Improved API handling and error management
* Better WooCommerce integration
* Enhanced image import functionality
* Automatic token refresh system

= 1.0 =
* Initial release

== Upgrade Notice ==

= 2.0 =
Major update with completely redesigned interface and improved functionality. Please backup your site before upgrading.

== Support ==

For support and documentation, visit: https://meharumar.codes

== License ==

This plugin is licensed under the GPLv2 or later license.

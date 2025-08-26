<?php
/**
 * Plugin Name: BabaProds
 * Description: Fetch products from Alibaba wholesale platform and add them to WooCommerce
 * Version: 2.0
 * Author: Mehar Umar
 * Author URI: https://meharumar.codes
 * Text Domain: babaprods
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BABAPRODS_VERSION', '2.0');
define('BABAPRODS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BABAPRODS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BABAPRODS_PLUGIN_FILE', __FILE__);

// Include required files
require_once BABAPRODS_PLUGIN_DIR . 'includes/class-babaprods-core.php';
require_once BABAPRODS_PLUGIN_DIR . 'includes/class-babaprods-api.php';
require_once BABAPRODS_PLUGIN_DIR . 'includes/class-babaprods-admin.php';
require_once BABAPRODS_PLUGIN_DIR . 'includes/class-babaprods-database.php';
require_once BABAPRODS_PLUGIN_DIR . 'includes/class-babaprods-woocommerce.php';

// Initialize the plugin
function babaprods_init() {
    new BabaProds_Core();
}
add_action('plugins_loaded', 'babaprods_init');

// Activation hook
register_activation_hook(__FILE__, array('BabaProds_Database', 'create_tables'));

// Deactivation hook
register_deactivation_hook(__FILE__, 'babaprods_deactivate');

function babaprods_deactivate() {
    // Clean up if needed
}

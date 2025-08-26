<?php
/**
 * Admin interface class
 */

if (!defined('ABSPATH')) {
    exit;
}

class BabaProds_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_babaprods_save_credentials', array($this, 'save_credentials'));
        add_action('wp_ajax_babaprods_add_to_store', array($this, 'add_to_store'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'BabaProds',
            'BabaProds',
            'manage_options',
            'babaprods',
            array($this, 'admin_page'),
            'dashicons-cart',
            30
        );
        
        add_submenu_page(
            'babaprods',
            'Settings',
            'Settings',
            'manage_options',
            'babaprods-settings',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('babaprods_settings', 'babaprods_credentials');
    }
    
    public function admin_page() {
        ?>
        <div class="wrap babaprods-wrap">
            <h1>BabaProds - Alibaba Product Importer</h1>
            
            <div class="babaprods-container">
                <div class="babaprods-tabs">
                    <button class="babaprods-tab-btn active" data-tab="single-product">Single Product</button>
                    <button class="babaprods-tab-btn" data-tab="search-products">Search Products</button>
                </div>
                
                <!-- Single Product Tab -->
                <div id="single-product" class="babaprods-tab-content active">
                    <div class="babaprods-card">
                        <h2>Import Single Product</h2>
                        <div class="babaprods-form-group">
                            <label for="product-url">Alibaba Product URL:</label>
                            <input type="url" id="product-url" placeholder="https://www.alibaba.com/product-detail/..." class="babaprods-input">
                            <button id="fetch-product" class="babaprods-btn babaprods-btn-primary">Fetch Product</button>
                        </div>
                        <div id="product-result" class="babaprods-result"></div>
                    </div>
                </div>
                
                <!-- Search Products Tab -->
                <div id="search-products" class="babaprods-tab-content">
                    <div class="babaprods-card">
                        <h2>Search Products</h2>
                        <div class="babaprods-form-group">
                            <label for="search-keyword">Search Keyword:</label>
                            <input type="text" id="search-keyword" placeholder="Enter keyword..." class="babaprods-input">
                            <button id="search-products-btn" class="babaprods-btn babaprods-btn-primary">Search</button>
                        </div>
                        <div id="search-results" class="babaprods-search-results"></div>
                        <div id="search-pagination" class="babaprods-pagination"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Modal -->
        <div id="product-modal" class="babaprods-modal">
            <div class="babaprods-modal-content">
                <span class="babaprods-close">&times;</span>
                <div id="modal-product-details"></div>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        $credentials = get_option('babaprods_credentials', array());
        ?>
        <div class="wrap babaprods-wrap">
            <h1>BabaProds Settings</h1>
            
            <div class="babaprods-container">
                <div class="babaprods-card">
                    <h2>API Credentials</h2>
                    <form id="credentials-form">
                        <div class="babaprods-form-group">
                            <label for="credentials-json">Paste your Alibaba API credentials (JSON format):</label>
                            <textarea id="credentials-json" rows="10" class="babaprods-textarea" placeholder='{"access_token": "...", "refresh_token": "...", ...}'><?php echo esc_textarea(json_encode($credentials, JSON_PRETTY_PRINT)); ?></textarea>
                        </div>
                        <button type="submit" class="babaprods-btn babaprods-btn-primary">Save Credentials</button>
                        <button type="button" id="refresh-token" class="babaprods-btn babaprods-btn-secondary">Refresh Token</button>
                    </form>
                    
                    <?php if (!empty($credentials)): ?>
                    <div class="babaprods-credentials-status">
                        <h3>Current Status</h3>
                        <p><strong>Account:</strong> <?php echo esc_html($credentials['account'] ?? 'N/A'); ?></p>
                        <p><strong>Country:</strong> <?php echo esc_html($credentials['country'] ?? 'N/A'); ?></p>
                        <p><strong>User ID:</strong> <?php echo esc_html($credentials['user_info']['user_id'] ?? 'N/A'); ?></p>
                        <p><strong>Expires:</strong> <?php echo isset($credentials['expires_in']) ? date('Y-m-d H:i:s', $credentials['expires_in']) : 'N/A'; ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function save_credentials() {
        check_ajax_referer('babaprods_admin_nonce', 'nonce');
        
        $credentials_json = stripslashes($_POST['credentials']);
        $credentials = json_decode($credentials_json, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($credentials['access_token'])) {
            if (isset($credentials['expires_in'])) {
                $credentials['expires_in'] = time() + $credentials['expires_in'];
            }
            
            update_option('babaprods_credentials', $credentials);
            wp_send_json_success('Credentials saved successfully');
        } else {
            wp_send_json_error('Invalid JSON format or missing access_token');
        }
    }
    
    public function add_to_store() {
        check_ajax_referer('babaprods_admin_nonce', 'nonce');
        
        $product_data = json_decode(stripslashes($_POST['product_data']), true);
        
        if (isset($product_data['images']) && is_array($product_data['images'])) {
            $product_data['images'] = array_filter($product_data['images'], function($url) {
                return !empty($url) && filter_var($url, FILTER_VALIDATE_URL);
            });
        }
        
        $woocommerce = new BabaProds_WooCommerce();
        $result = $woocommerce->create_product($product_data);
        
        if ($result) {
            wp_send_json_success('Product added to store successfully');
        } else {
            wp_send_json_error('Failed to add product to store');
        }
    }
}

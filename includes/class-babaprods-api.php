<?php
/**
 * Alibaba API handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class BabaProds_API {
    
    private $app_key = '';
    private $app_secret = '';
    private $base_url = 'https://openapi-api.alibaba.com/rest/2.0';
    private $groq_api_key = '';
    private $groq_api_url = 'https://api.groq.com/openai/v1/chat/completions';
    
    public function __construct() {
        add_action('wp_ajax_babaprods_fetch_product', array($this, 'ajax_fetch_product'));
        add_action('wp_ajax_babaprods_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_babaprods_refresh_token', array($this, 'ajax_refresh_token'));
        add_action('wp_ajax_babaprods_generate_category', array($this, 'ajax_generate_category'));
        add_action('wp_ajax_babaprods_convert_currency', array($this, 'ajax_convert_currency'));
        add_action('wp_ajax_babaprods_regenerate_description', array($this, 'ajax_regenerate_description'));
    }
    
    /**
     * Log debug information
     */
    private function log_debug($message, $data = null) {
        if (WP_DEBUG) {
            error_log('[BabaProds] ' . $message);
            if ($data) {
                error_log('[BabaProds] Data: ' . print_r($data, true));
            }
        }
    }
    
    /**
     * Get stored credentials
     */
    private function get_credentials() {
        return get_option('babaprods_credentials', array());
    }
    
    /**
     * Generate signature for API requests
     */
    private function generate_signature($params, $api_path = '') {
        // Remove sign parameter if present (should not be included in signature)
        unset($params['sign']);
        
        // Sort parameters alphabetically by key
        ksort($params);
        
        // Concatenate parameters as keyvalue (no = or & separators)
        $param_string = '';
        foreach ($params as $key => $value) {
            if (!empty($key) && !empty($value)) {
                $param_string .= $key . $value;
            }
        }
        
        // Prepend API path to the parameter string
        $string_to_sign = $api_path . $param_string;
        
        // Use HMAC-SHA256 with app_secret as key
        $signature = strtoupper(hash_hmac('sha256', $string_to_sign, $this->app_secret));
        
        $this->log_debug('Generating signature (Alibaba format)', array(
            'api_path' => $api_path,
            'params' => $params,
            'param_string' => $param_string,
            'string_to_sign' => $string_to_sign,
            'signature' => $signature
        ));
        
        return $signature;
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $params = array()) {
        $credentials = $this->get_credentials();
        
        $this->log_debug('Making API request', array(
            'endpoint' => $endpoint,
            'params' => $params,
            'has_access_token' => !empty($credentials['access_token'])
        ));
        
        if (empty($credentials['access_token'])) {
            $this->log_debug('No access token available');
            return array('error' => 'No access token available. Please configure your credentials in settings.');
        }
        
        // Check if token needs refresh
        if (isset($credentials['expires_in']) && time() > $credentials['expires_in']) {
            $this->log_debug('Token expired, attempting refresh');
            $refresh_result = $this->refresh_token();
            if (!$refresh_result) {
                return array('error' => 'Failed to refresh token. Please reconfigure your credentials.');
            }
            $credentials = $this->get_credentials();
        }
        
        $base_params = array(
            'app_key' => $this->app_key,
            'sign_method' => 'sha256',
            'access_token' => $credentials['access_token'],
            'timestamp' => time() * 1000
        );
        
        $all_params = array_merge($base_params, $params);
        $all_params['sign'] = $this->generate_signature($all_params, $endpoint);
        
        $url = $this->base_url . $endpoint . '?' . http_build_query($all_params);
        
        $this->log_debug('Request URL: ' . $url);
        
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_debug('API request failed: ' . $error_message);
            return array('error' => $error_message);
        }
        
        $body = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);
        
        $this->log_debug('API Response', array(
            'code' => $response_code,
            'body' => $body
        ));
        
        $decoded_response = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('JSON decode error: ' . json_last_error_msg());
            return array('error' => 'Invalid JSON response from API');
        }
        
        return $decoded_response;
    }
    
    /**
     * Refresh access token
     */
    public function refresh_token() {
        $credentials = $this->get_credentials();
        
        $this->log_debug('Attempting token refresh', array(
            'has_refresh_token' => !empty($credentials['refresh_token'])
        ));
        
        if (empty($credentials['refresh_token'])) {
            $this->log_debug('No refresh token available');
            return false;
        }
        
        $params = array(
            'refresh_token' => $credentials['refresh_token'],
            'app_key' => $this->app_key,
            'sign_method' => 'sha256',
            'timestamp' => time() * 1000
        );
        
        $refresh_endpoint = '/auth/token/refresh';
        $params['sign'] = $this->generate_signature($params, $refresh_endpoint);
        
        $url = 'https://openapi-api.alibaba.com/rest' . $refresh_endpoint . '?' . http_build_query($params);
        
        $this->log_debug('Token refresh URL: ' . $url);
        
        $response = wp_remote_get($url);
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $this->log_debug('Token refresh response: ' . $body);
            
            $data = json_decode($body, true);
            
            if (isset($data['access_token'])) {
                $data['expires_in'] = time() + $data['expires_in'];
                update_option('babaprods_credentials', $data);
                $this->log_debug('Token refreshed successfully');
                return true;
            } else {
                $this->log_debug('Token refresh failed - no access_token in response', $data);
            }
        } else {
            $this->log_debug('Token refresh request failed: ' . $response->get_error_message());
        }
        
        return false;
    }
    
    /**
     * Extract product ID from Alibaba URL
     */
    private function extract_product_id($url) {
        $this->log_debug('Extracting product ID from URL: ' . $url);
        
        // Pattern 1: _productId.html (original pattern)
        if (preg_match('/_(\d+)\.html/', $url, $matches)) {
            $this->log_debug('Product ID found (pattern 1): ' . $matches[1]);
            return $matches[1];
        }
        
        // Pattern 2: productId.html (your URL format)
        if (preg_match('/(\d{10,})\.html/', $url, $matches)) {
            $this->log_debug('Product ID found (pattern 2): ' . $matches[1]);
            return $matches[1];
        }
        
        // Pattern 3: productId= parameter
        if (preg_match('/productId=(\d+)/', $url, $matches)) {
            $this->log_debug('Product ID found (pattern 3): ' . $matches[1]);
            return $matches[1];
        }
        
        // Pattern 4: /product-detail/...productId_...
        if (preg_match('/product-detail\/[^_]*_(\d+)/', $url, $matches)) {
            $this->log_debug('Product ID found (pattern 4): ' . $matches[1]);
            return $matches[1];
        }
        
        $this->log_debug('No product ID found in URL');
        return false;
    }
    
    /**
     * Fetch single product data
     */
    public function fetch_product($product_url) {
        $this->log_debug('Fetching product from URL: ' . $product_url);
        
        $product_id = $this->extract_product_id($product_url);
        
        if (!$product_id) {
            $this->log_debug('Failed to extract product ID from URL');
            return array('error' => 'Invalid product URL. Could not extract product ID.');
        }
        
        $this->log_debug('Extracted product ID: ' . $product_id);
        
        $query_req = json_encode(array(
            'product_id' => intval($product_id),
            'country' => 'PK'
        ));
        
        $params = array(
            'query_req' => $query_req
        );
        
        $this->log_debug('Making product API request', $params);
        
        $response = $this->make_request('/eco/buyer/product/description', $params);
        
        $this->log_debug('Product API response', $response);
        
        if (isset($response['result']['result_data'])) {
            $product_data = $response['result']['result_data'];
            $product_data['affiliate_link'] = "https://offer.alibaba.com/cps/jnqilrll?bm=cps&src=saf&productId={$product_id}";
            
            if (isset($product_data['skus']) && is_array($product_data['skus'])) {
                foreach ($product_data['skus'] as &$sku) {
                    if (isset($sku['ladder_price']) && is_array($sku['ladder_price'])) {
                        foreach ($sku['ladder_price'] as &$price_tier) {
                            if (isset($price_tier['price'])) {
                                $usd_price = floatval($price_tier['price']);
                                $pkr_price = $this->convert_usd_to_pkr($usd_price);
                                $price_tier['price'] = $pkr_price;
                                $price_tier['currency'] = 'PKR';
                            }
                        }
                    }
                }
            }
            
            if (isset($product_data['images']) && is_array($product_data['images'])) {
                $product_data['images'] = array_map(array($this, 'fix_image_url'), $product_data['images']);
                $product_data['images'] = array_filter($product_data['images']); // Remove empty URLs
            }
            
            $this->log_debug('Product data processed successfully');
            return $product_data;
        }
        
        if (isset($response['error'])) {
            return $response;
        }
        
        if (isset($response['result']['error_code'])) {
            return array('error' => 'API Error: ' . $response['result']['error_message']);
        }
        
        return array('error' => 'Unexpected API response format', 'raw_response' => $response);
    }
    
    /**
     * Search products by keyword
     */
    public function search_products($keyword, $page = 1, $size = 50) {
        $this->log_debug('Searching products', array(
            'keyword' => $keyword,
            'page' => $page,
            'size' => $size
        ));
        
        $param0 = json_encode(array(
            'size' => $size,
            'index' => $page,
            'keyword' => $keyword
        ));
        
        $params = array(
            'param0' => $param0
        );
        
        $response = $this->make_request('/eco/buyer/product/search', $params);
        
        $this->log_debug('Search API response', $response);
        
        return $response;
    }
    
    private function convert_usd_to_pkr($usd_amount) {
        // Use a free exchange rate API
        $api_url = 'https://api.exchangerate-api.com/v4/latest/USD';
        
        $response = wp_remote_get($api_url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            // Fallback to approximate rate if API fails
            return round($usd_amount * 278, 2); // Approximate USD to PKR rate
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['rates']['PKR'])) {
            $pkr_rate = $data['rates']['PKR'];
            return round($usd_amount * $pkr_rate, 2);
        }
        
        // Fallback rate
        return round($usd_amount * 278, 2);
    }
    
    private function fix_image_url($image_url) {
        if (empty($image_url)) {
            return '';
        }
        
        // Remove any leading/trailing whitespace
        $image_url = trim($image_url);
        
        // Handle protocol-relative URLs (starting with //)
        if (strpos($image_url, '//') === 0) {
            $image_url = 'https:' . $image_url;
        }
        
        // Ensure HTTPS protocol
        $image_url = str_replace('http://', 'https://', $image_url);
        
        // Validate URL format
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        return $image_url;
    }
    
    public function generate_category($description) {
        $prompt = "Based on the following product description, generate a single, specific product category name that would be suitable for an e-commerce store. Return only the category name, nothing else. Make it concise and relevant.\n\nProduct Description: " . strip_tags($description);
        
        $data = array(
            'model' => 'llama3-8b-8192',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 50,
            'temperature' => 0.3
        );
        
        $response = wp_remote_post($this->groq_api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->groq_api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('error' => 'Failed to connect to category generation service');
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            $category = trim($result['choices'][0]['message']['content']);
            // Clean up the category name
            $category = preg_replace('/[^a-zA-Z0-9\s&-]/', '', $category);
            $category = trim($category);
            
            if (!empty($category)) {
                return array('category' => $category);
            }
        }
        
        return array('error' => 'Failed to generate category');
    }
    
    /**
     * AJAX handler for fetching single product
     */
    public function ajax_fetch_product() {
        check_ajax_referer('babaprods_admin_nonce', 'nonce');
        
        $product_url = sanitize_url($_POST['product_url']);
        
        if (empty($product_url)) {
            wp_send_json(array('error' => 'Product URL is required'));
            return;
        }
        
        $result = $this->fetch_product($product_url);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for searching products
     */
    public function ajax_search_products() {
        check_ajax_referer('babaprods_admin_nonce', 'nonce');
        
        $keyword = sanitize_text_field($_POST['keyword']);
        $page = intval($_POST['page']) ?: 1;
        $size = intval($_POST['size']) ?: 20;
        
        $result = $this->search_products($keyword, $page, $size);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for refreshing token
     */
    public function ajax_refresh_token() {
        check_ajax_referer('babaprods_admin_nonce', 'nonce');
        
        $result = $this->refresh_token();
        
        wp_send_json(array('success' => $result));
    }
    
    /**
     * AJAX handler for generating category
     */
    public function ajax_generate_category() {
        check_ajax_referer('babaprods_admin_nonce', 'nonce');
        
        $description = sanitize_textarea_field($_POST['description']);
        
        if (empty($description)) {
            wp_send_json(array('error' => 'Description is required'));
            return;
        }
        
        $result = $this->generate_category($description);
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for currency conversion
     */
    public function ajax_convert_currency() {
        check_ajax_referer('babaprods_admin_nonce', 'nonce');
        
        $usd_amount = floatval($_POST['amount']);
        
        if ($usd_amount <= 0) {
            wp_send_json(array('error' => 'Invalid amount'));
            return;
        }
        
        $pkr_amount = $this->convert_usd_to_pkr($usd_amount);
        wp_send_json(array('pkr_amount' => $pkr_amount));
    }
    
    /**
     * AJAX handler for regenerating description
     */
    public function ajax_regenerate_description() {
        check_ajax_referer('babaprods_admin_nonce', 'nonce');
        
        $description = sanitize_textarea_field($_POST['description']);
        
        if (empty($description)) {
            wp_send_json(array('error' => 'Description is required'));
            return;
        }
        
        $result = $this->regenerate_description($description);
        wp_send_json($result);
    }
    
    /**
     * Regenerate description using Groq API
     */
    public function regenerate_description($original_description) {
        $prompt = "You are a professional e-commerce copywriter. Rewrite the following product description to be comprehensive, engaging, and sales-focused for an online store. 

Requirements:
- Create a detailed, professional description (minimum 200-300 words)
- Use proper paragraph structure with clear sections
- Include key features, benefits, and specifications
- Write in a compelling, customer-focused tone
- Use HTML formatting for better presentation (headings, lists, paragraphs)
- Make it SEO-friendly and conversion-optimized
- Focus on value proposition and customer benefits

Original Description: " . strip_tags($original_description) . "

Please provide a well-structured, professional product description with HTML formatting:";
        
        $data = array(
            'model' => 'llama3-70b-8192', // Using more powerful model for better descriptions
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are an expert e-commerce copywriter specializing in creating compelling, detailed product descriptions that convert browsers into buyers. Always write comprehensive, well-formatted descriptions.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 1500, // Increased token limit for longer descriptions
            'temperature' => 0.7 // Slightly higher temperature for more creative writing
        );
        
        $response = wp_remote_post($this->groq_api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->groq_api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 45 // Increased timeout for longer processing
        ));
        
        if (is_wp_error($response)) {
            return array('error' => 'Failed to connect to description generation service');
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            $description = trim($result['choices'][0]['message']['content']);
            
            // Clean up any problematic formatting but keep HTML structure
            $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
            
            // Remove any markdown-style formatting that might have slipped through
            $description = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $description);
            $description = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $description);
            $description = preg_replace('/#{1,6}\s+(.+)/', '<h3>$1</h3>', $description);
            
            // Ensure proper paragraph formatting
            $description = wpautop($description);
            
            if (!empty($description)) {
                return array('description' => $description);
            }
        }
        
        return array('error' => 'Failed to regenerate description');
    }
}

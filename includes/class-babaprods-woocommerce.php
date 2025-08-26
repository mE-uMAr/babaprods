<?php
/**
 * WooCommerce integration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class BabaProds_WooCommerce {
    
    public function __construct() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('BabaProds requires WooCommerce to be installed and active.', 'babaprods'); ?></p>
        </div>
        <?php
    }
    
    public function create_product($product_data) {
        if (!class_exists('WC_Product_External')) {
            return false;
        }
        
        $product = new WC_Product_External();
        
        // Set basic product data
        $product->set_name($product_data['title']);
        $product->set_description($this->process_html_description($product_data['description']));
        $product->set_short_description($this->generate_short_description($product_data));
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        
        // Set external product data
        $product->set_product_url($product_data['affiliate_link']);
        $product->set_button_text('Buy on Alibaba');
        
        if (isset($product_data['skus'][0]['ladder_price'][0]['price'])) {
            $price = $product_data['skus'][0]['ladder_price'][0]['price'];
            $product->set_regular_price($price);
            $product->set_price($price);
        }
        
        // Set supplier information
        if (isset($product_data['supplier'])) {
            $product->update_meta_data('_supplier', $product_data['supplier']);
        }
        
        if (isset($product_data['category'])) {
            $this->set_product_category($product, $product_data['category']);
        }
        
        // Save the product
        $product_id = $product->save();
        
        if ($product_id) {
            $this->set_product_images($product_id, $product_data['images']);
            
            // Log the import
            BabaProds_Database::log_import($product_id, $product_data['product_id']);
            
            return $product_id;
        }
        
        return false;
    }
    
    private function process_html_description($description) {
        if (empty($description)) {
            return '';
        }
        
        // Convert HTML entities to characters
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        
        // If description contains HTML tags, preserve them for better formatting
        if (strip_tags($description) !== $description) {
            // Clean up and validate HTML
            $description = wp_kses($description, array(
                'p' => array(),
                'br' => array(),
                'strong' => array(),
                'b' => array(),
                'em' => array(),
                'i' => array(),
                'h1' => array(),
                'h2' => array(),
                'h3' => array(),
                'h4' => array(),
                'ul' => array(),
                'ol' => array(),
                'li' => array(),
                'div' => array('class' => array()),
                'span' => array('class' => array())
            ));
        } else {
            // For plain text, convert to paragraphs
            $description = wpautop($description);
        }
        
        // Clean up extra whitespace but preserve line breaks
        $description = preg_replace('/\s{3,}/', ' ', $description);
        $description = trim($description);
        
        return $description;
    }
    
    private function generate_short_description($product_data) {
        $short_desc = '';
        
        if (isset($product_data['supplier'])) {
            $short_desc .= 'Supplier: ' . $product_data['supplier'] . "\n";
        }
        
        if (isset($product_data['skus'][0]['ladder_price'])) {
            $prices = $product_data['skus'][0]['ladder_price'];
            $short_desc .= 'Price Range: PKR ' . $prices[0]['price'];
            if (count($prices) > 1) {
                $short_desc .= ' - PKR ' . end($prices)['price'];
            }
        }
        
        return $short_desc;
    }
    
    private function set_product_category($product, $category_name) {
        $term = get_term_by('name', $category_name, 'product_cat');
        
        if (!$term) {
            $term = wp_insert_term($category_name, 'product_cat');
            if (!is_wp_error($term)) {
                $term_id = $term['term_id'];
            }
        } else {
            $term_id = $term->term_id;
        }
        
        if (isset($term_id)) {
            $product->set_category_ids(array($term_id));
        }
    }
    
    private function set_product_images($product_id, $images) {
        if (empty($images) || !is_array($images)) {
            return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $gallery_ids = array();
        $featured_image_id = null;
        
        foreach ($images as $index => $image_url) {
            $fixed_url = $this->fix_image_url($image_url);
            
            if (empty($fixed_url) || !filter_var($fixed_url, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            // Download and upload image to WordPress media library
            $uploaded_image_id = $this->upload_image_from_url($fixed_url, $product_id);
            
            if ($uploaded_image_id) {
                if ($index === 0 && !$featured_image_id) {
                    // Set first valid image as featured image
                    $featured_image_id = $uploaded_image_id;
                    set_post_thumbnail($product_id, $featured_image_id);
                }
                
                // Add to gallery
                $gallery_ids[] = $uploaded_image_id;
            } else {
                // Fallback: store external URL if upload fails
                if ($index === 0 && !$featured_image_id) {
                    update_post_meta($product_id, '_thumbnail_external_url', $fixed_url);
                }
            }
        }
        
        // Set product image gallery
        if (!empty($gallery_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
        
        // Store original external URLs as backup
        update_post_meta($product_id, '_product_image_gallery_urls', array_map(array($this, 'fix_image_url'), $images));
    }
    
    /**
     * Upload image from URL to WordPress media library
     */
    private function upload_image_from_url($image_url, $product_id) {
        try {
            // Get image data
            $response = wp_remote_get($image_url, array(
                'timeout' => 30,
                'headers' => array(
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                )
            ));
            
            if (is_wp_error($response)) {
                error_log('[BabaProds] Failed to fetch image: ' . $response->get_error_message());
                return false;
            }
            
            $image_data = wp_remote_retrieve_body($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            
            if (empty($image_data)) {
                error_log('[BabaProds] Empty image data for URL: ' . $image_url);
                return false;
            }
            
            // Determine file extension from content type or URL
            $extension = 'jpg'; // default
            if (strpos($content_type, 'png') !== false) {
                $extension = 'png';
            } elseif (strpos($content_type, 'gif') !== false) {
                $extension = 'gif';
            } elseif (strpos($content_type, 'webp') !== false) {
                $extension = 'webp';
            } elseif (preg_match('/\.(png|jpg|jpeg|gif|webp)$/i', $image_url, $matches)) {
                $extension = strtolower($matches[1]);
                if ($extension === 'jpeg') $extension = 'jpg';
            }
            
            // Generate unique filename
            $filename = 'babaprods_' . $product_id . '_' . uniqid() . '.' . $extension;
            
            // Get upload directory
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $filename;
            $file_url = $upload_dir['url'] . '/' . $filename;
            
            // Save image file
            if (file_put_contents($file_path, $image_data) === false) {
                error_log('[BabaProds] Failed to save image file: ' . $file_path);
                return false;
            }
            
            // Prepare attachment data
            $attachment = array(
                'guid' => $file_url,
                'post_mime_type' => $content_type ?: 'image/' . $extension,
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' => $product_id
            );
            
            // Insert attachment
            $attachment_id = wp_insert_attachment($attachment, $file_path, $product_id);
            
            if (is_wp_error($attachment_id)) {
                error_log('[BabaProds] Failed to insert attachment: ' . $attachment_id->get_error_message());
                unlink($file_path); // Clean up file
                return false;
            }
            
            // Generate attachment metadata
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            
            error_log('[BabaProds] Successfully uploaded image: ' . $filename . ' (ID: ' . $attachment_id . ')');
            return $attachment_id;
            
        } catch (Exception $e) {
            error_log('[BabaProds] Exception uploading image: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fix image URL format
     */
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
}

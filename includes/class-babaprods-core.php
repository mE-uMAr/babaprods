<?php
/**
 * Core plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class BabaProds_Core {
    
    public function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    private function init_components() {
        new BabaProds_Admin();
        new BabaProds_API();
        new BabaProds_WooCommerce();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('babaprods', false, dirname(plugin_basename(BABAPRODS_PLUGIN_FILE)) . '/languages');
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('babaprods-frontend', BABAPRODS_PLUGIN_URL . 'assets/css/frontend.css', array(), BABAPRODS_VERSION);
        wp_enqueue_script('babaprods-frontend', BABAPRODS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), BABAPRODS_VERSION, true);
        
        wp_localize_script('babaprods-frontend', 'babaprods_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('babaprods_nonce')
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'babaprods') === false) {
            return;
        }
        
        wp_enqueue_style('babaprods-admin', BABAPRODS_PLUGIN_URL . 'assets/css/admin.css', array(), BABAPRODS_VERSION);
        wp_enqueue_script('babaprods-admin', BABAPRODS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), BABAPRODS_VERSION, true);
        
        wp_localize_script('babaprods-admin', 'babaprods_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('babaprods_admin_nonce')
        ));
    }
}

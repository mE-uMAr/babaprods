<?php
/**
 * Database operations class
 */

if (!defined('ABSPATH')) {
    exit;
}

class BabaProds_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'babaprods_imports';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            alibaba_product_id varchar(50) NOT NULL,
            import_date datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY alibaba_product_id (alibaba_product_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function log_import($product_id, $alibaba_product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'babaprods_imports';
        
        return $wpdb->insert(
            $table_name,
            array(
                'product_id' => $product_id,
                'alibaba_product_id' => $alibaba_product_id
            ),
            array('%d', '%s')
        );
    }
    
    public static function get_imports() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'babaprods_imports';
        
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY import_date DESC");
    }
}

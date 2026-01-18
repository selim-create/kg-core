<?php
namespace KG_Core\Database;

/**
 * ChildProfileSchema - Create child_profiles database table
 */
class ChildProfileSchema {
    
    /**
     * Create child_profiles table
     */
    public static function create_table() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            $prefix = $wpdb->prefix;
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Child Profiles Table
            $sql_child_profiles = "CREATE TABLE {$prefix}kg_child_profiles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid VARCHAR(36) NOT NULL UNIQUE,
                user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                birth_date DATE NOT NULL,
                gender ENUM('male', 'female', 'unspecified') DEFAULT 'unspecified',
                allergies JSON DEFAULT NULL,
                feeding_style ENUM('blw', 'puree', 'mixed') DEFAULT 'mixed',
                photo_id BIGINT UNSIGNED DEFAULT NULL,
                avatar_path VARCHAR(500) DEFAULT NULL,
                kvkk_consent BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_uuid (uuid),
                INDEX idx_user_id (user_id),
                INDEX idx_birth_date (birth_date)
            ) $charset_collate;";
            
            // Suppress dbDelta output
            @dbDelta($sql_child_profiles);
            
            return true;
        } catch ( \Exception $e ) {
            error_log( 'KG Core: Failed to create child_profiles table - ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Drop child_profiles table (for development/testing)
     */
    public static function drop_table() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $wpdb->query( "DROP TABLE IF EXISTS {$prefix}kg_child_profiles" );
    }
}

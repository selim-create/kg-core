<?php
namespace KG_Core\Database;

/**
 * UserConsentSchema - Create user_consents database table
 * 
 * Manages user consent records for KVKK and ETK compliance
 */
class UserConsentSchema {
    
    /**
     * Create user_consents table
     */
    public static function create_table() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            $prefix = $wpdb->prefix;
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // User Consents Table
            $sql_user_consents = "CREATE TABLE {$prefix}kg_user_consents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                consent_type ENUM('terms', 'marketing', 'sensitive_data', 'guardian_declaration') NOT NULL,
                consented BOOLEAN NOT NULL DEFAULT FALSE,
                consented_at TIMESTAMP NULL,
                revoked_at TIMESTAMP NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                version VARCHAR(20) NULL COMMENT 'Onaylanan döküman versiyonu',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_consents (user_id, consent_type),
                INDEX idx_consent_type (consent_type),
                INDEX idx_consented_at (consented_at)
            ) $charset_collate;";
            
            // Suppress dbDelta output
            @dbDelta($sql_user_consents);
            
            return true;
        } catch ( \Exception $e ) {
            error_log( 'KG Core: Failed to create user_consents table - ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Drop user_consents table (for development/testing)
     */
    public static function drop_table() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $wpdb->query( "DROP TABLE IF EXISTS {$prefix}kg_user_consents" );
    }
}

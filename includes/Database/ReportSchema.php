<?php
namespace KG_Core\Database;

/**
 * ReportSchema - Create reports database table for content reporting system
 */
class ReportSchema {
    
    /**
     * Create reports table
     */
    public static function create_table() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            $prefix = $wpdb->prefix;
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Reports Table
            $sql_reports = "CREATE TABLE {$prefix}kg_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                content_type ENUM('discussion', 'comment') NOT NULL,
                content_id BIGINT UNSIGNED NOT NULL,
                reason ENUM('spam', 'inappropriate', 'harassment', 'misinformation', 'other') NOT NULL,
                description TEXT NULL,
                status ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_content (content_type, content_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                UNIQUE KEY unique_user_content (user_id, content_type, content_id)
            ) $charset_collate;";
            
            // Suppress dbDelta output
            @dbDelta($sql_reports);
            
            return true;
        } catch ( \Exception $e ) {
            error_log( 'KG Core: Failed to create kg_reports table - ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Drop reports table (for development/testing)
     */
    public static function drop_table() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $wpdb->query( "DROP TABLE IF EXISTS {$prefix}kg_reports" );
    }
}

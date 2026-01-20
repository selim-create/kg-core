<?php
namespace KG_Core\Database;

/**
 * VotingSchema - Create discussion_votes database table for like/dislike system
 */
class VotingSchema {
    
    /**
     * Create discussion_votes table
     */
    public static function create_table() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            $prefix = $wpdb->prefix;
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Discussion Votes Table
            $sql_votes = "CREATE TABLE {$prefix}kg_discussion_votes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                discussion_id BIGINT UNSIGNED NULL,
                comment_id BIGINT UNSIGNED NULL,
                vote_type ENUM('like', 'dislike') NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_discussion_id (discussion_id),
                INDEX idx_comment_id (comment_id),
                UNIQUE KEY unique_user_discussion (user_id, discussion_id),
                UNIQUE KEY unique_user_comment (user_id, comment_id),
                CONSTRAINT chk_vote_target CHECK (
                    (discussion_id IS NOT NULL AND comment_id IS NULL) OR 
                    (discussion_id IS NULL AND comment_id IS NOT NULL)
                )
            ) $charset_collate;";
            
            // Suppress dbDelta output
            @dbDelta($sql_votes);
            
            return true;
        } catch ( \Exception $e ) {
            error_log( 'KG Core: Failed to create kg_discussion_votes table - ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Drop discussion_votes table (for development/testing)
     */
    public static function drop_table() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $wpdb->query( "DROP TABLE IF EXISTS {$prefix}kg_discussion_votes" );
    }
}

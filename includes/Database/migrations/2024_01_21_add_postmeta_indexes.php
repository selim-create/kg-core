<?php
namespace KG_Core\Database\Migrations;

/**
 * Migration: Add composite indexes to wp_postmeta table
 * 
 * Purpose: Optimize meta_query performance by adding composite indexes
 * for common query patterns like `meta_key + meta_value` and `meta_key + post_id`
 */
class AddPostmetaIndexes {
    
    /**
     * Run the migration (add indexes)
     */
    public function up() {
        global $wpdb;
        
        // Get existing indexes
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta}");
        $index_names = array_column($existing_indexes, 'Key_name');
        
        // Note: Index names and table names are hardcoded constants, not user input
        // DDL statements (CREATE INDEX) don't support parameterized queries in MySQL
        
        // Add meta_key + meta_value composite index
        if (!in_array('idx_kg_meta_key_value', $index_names)) {
            $result = $wpdb->query("CREATE INDEX idx_kg_meta_key_value ON {$wpdb->postmeta} (meta_key, meta_value(191))");
            if ($result === false) {
                error_log('Failed to create index idx_kg_meta_key_value: ' . $wpdb->last_error);
            }
        }
        
        // Add meta_key + post_id composite index
        if (!in_array('idx_kg_meta_key_post', $index_names)) {
            $result = $wpdb->query("CREATE INDEX idx_kg_meta_key_post ON {$wpdb->postmeta} (meta_key, post_id)");
            if ($result === false) {
                error_log('Failed to create index idx_kg_meta_key_post: ' . $wpdb->last_error);
            }
        }
        
        return true;
    }
    
    /**
     * Reverse the migration (remove indexes)
     */
    public function down() {
        global $wpdb;
        
        // Note: Index names and table names are hardcoded constants, not user input
        // DDL statements (DROP INDEX) don't support parameterized queries in MySQL
        
        // Drop indexes if they exist
        $wpdb->query("DROP INDEX IF EXISTS idx_kg_meta_key_value ON {$wpdb->postmeta}");
        $wpdb->query("DROP INDEX IF EXISTS idx_kg_meta_key_post ON {$wpdb->postmeta}");
        
        return true;
    }
}

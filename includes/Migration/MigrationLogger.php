<?php
namespace KG_Core\Migration;

/**
 * MigrationLogger - Log migration progress and errors
 */
class MigrationLogger {
    
    private $log_file;
    private $db_table;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/kg-migration-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $this->log_file = $log_dir . '/migration-' . date('Y-m-d') . '.log';
        
        // DB table name for migration status
        global $wpdb;
        $this->db_table = $wpdb->prefix . 'kg_migration_log';
        
        // Create table if doesn't exist
        $this->createTable();
    }
    
    /**
     * Create migration log table
     */
    private function createTable() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->db_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            blog_post_id bigint(20) NOT NULL,
            recipe_post_id bigint(20) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY blog_post_id (blog_post_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log message to file
     * 
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    public function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);
        $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
        
        // Write to file
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
        
        // Also log to WordPress error log for errors
        if ($level === 'ERROR') {
            error_log("KG Migration: {$message}");
        }
    }
    
    /**
     * Start migration for a post
     * 
     * @param int $blogPostId Blog post ID
     * @return int Log entry ID
     */
    public function startMigration($blogPostId) {
        global $wpdb;
        
        $wpdb->insert(
            $this->db_table,
            [
                'blog_post_id' => $blogPostId,
                'status' => 'in_progress',
                'started_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s']
        );
        
        $logId = $wpdb->insert_id;
        
        $this->log("Started migration for blog post ID: {$blogPostId}", 'info');
        
        return $logId;
    }
    
    /**
     * Mark migration as successful
     * 
     * @param int $blogPostId Blog post ID
     * @param int $recipePostId New recipe post ID
     * @param array $metadata Additional metadata
     */
    public function success($blogPostId, $recipePostId, $metadata = []) {
        global $wpdb;
        
        $wpdb->update(
            $this->db_table,
            [
                'recipe_post_id' => $recipePostId,
                'status' => 'success',
                'completed_at' => current_time('mysql'),
                'metadata' => json_encode($metadata)
            ],
            ['blog_post_id' => $blogPostId],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );
        
        $this->log("Successfully migrated blog post {$blogPostId} to recipe post {$recipePostId}", 'info');
    }
    
    /**
     * Mark migration as failed
     * 
     * @param int $blogPostId Blog post ID
     * @param string $errorMessage Error message
     */
    public function error($blogPostId, $errorMessage) {
        global $wpdb;
        
        $wpdb->update(
            $this->db_table,
            [
                'status' => 'failed',
                'completed_at' => current_time('mysql'),
                'error_message' => $errorMessage
            ],
            ['blog_post_id' => $blogPostId],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        $this->log("Failed to migrate blog post {$blogPostId}: {$errorMessage}", 'error');
    }
    
    /**
     * Get migration status
     * 
     * @return array Status counts
     */
    public function getStatus() {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT status, COUNT(*) as count
            FROM {$this->db_table}
            GROUP BY status
        ", ARRAY_A);
        
        $status = [
            'pending' => 0,
            'in_progress' => 0,
            'success' => 0,
            'failed' => 0
        ];
        
        foreach ($results as $row) {
            $status[$row['status']] = (int) $row['count'];
        }
        
        return $status;
    }
    
    /**
     * Get total recipe count
     * 
     * @return int Total recipes to migrate
     */
    public function getTotalCount() {
        $recipeIds = $this->getRecipeIds();
        return count($recipeIds);
    }
    
    /**
     * Get recipe IDs from JSON file
     * 
     * @return array Recipe post IDs
     */
    private function getRecipeIds() {
        $jsonFile = KG_CORE_PATH . 'data/recipe-ids.json';
        
        if (!file_exists($jsonFile)) {
            return [];
        }
        
        $jsonData = file_get_contents($jsonFile);
        $data = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['recipe_post_ids'])) {
            return [];
        }
        
        return $data['recipe_post_ids'];
    }
    
    /**
     * Get failed migrations
     * 
     * @param int $limit Number of results
     * @return array Failed migrations
     */
    public function getFailedMigrations($limit = 50) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT blog_post_id, error_message, completed_at
            FROM {$this->db_table}
            WHERE status = 'failed'
            ORDER BY completed_at DESC
            LIMIT %d
        ", $limit), ARRAY_A);
        
        return $results;
    }
    
    /**
     * Get recent migrations
     * 
     * @param int $limit Number of results
     * @return array Recent migrations
     */
    public function getRecentMigrations($limit = 50) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$this->db_table}
            ORDER BY started_at DESC
            LIMIT %d
        ", $limit), ARRAY_A);
        
        return $results;
    }
    
    /**
     * Check if post has been migrated
     * 
     * @param int $blogPostId Blog post ID
     * @return bool True if migrated
     */
    public function isMigrated($blogPostId) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare("
            SELECT status
            FROM {$this->db_table}
            WHERE blog_post_id = %d
            AND status = 'success'
        ", $blogPostId));
        
        return $result !== null;
    }
    
    /**
     * Get log entry for a specific blog post
     * 
     * @param int $blogPostId Blog post ID
     * @return array|null Log entry or null if not found
     */
    public function getLogEntry($blogPostId) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare("
            SELECT *
            FROM {$this->db_table}
            WHERE blog_post_id = %d
            ORDER BY started_at DESC
            LIMIT 1
        ", $blogPostId), ARRAY_A);
        
        return $result;
    }
    
    /**
     * Reset migration for a post
     * 
     * @param int $blogPostId Blog post ID
     */
    public function resetMigration($blogPostId) {
        global $wpdb;
        
        $wpdb->delete(
            $this->db_table,
            ['blog_post_id' => $blogPostId],
            ['%d']
        );
        
        $this->log("Reset migration for blog post ID: {$blogPostId}", 'info');
    }
}

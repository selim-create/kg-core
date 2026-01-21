<?php
namespace KG_Core\Models;

abstract class BaseModel {
    
    /**
     * Table name without prefix (override in child classes)
     */
    protected static $table = '';
    
    /**
     * Associated post type (override in child classes)
     */
    protected static $post_type = '';
    
    /**
     * Field type definitions for serialization (override in child classes)
     * Format: ['field_name' => 'type']
     * Types: 'json', 'boolean', 'int', 'float', 'string'
     */
    protected static $field_types = [];
    
    /**
     * Cache group name
     */
    protected static $cache_group = 'kg_models';
    
    /**
     * Cache expiration in seconds (1 hour)
     */
    protected static $cache_expiration = 3600;
    
    /**
     * Get full table name with prefix
     */
    public static function getTableName() {
        global $wpdb;
        return $wpdb->prefix . static::$table;
    }
    
    /**
     * Get meta by post ID
     */
    public static function get($post_id) {
        global $wpdb;
        
        $table = static::getTableName();
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE post_id = %d", $post_id),
            ARRAY_A
        );
        
        if (!$row) {
            return null;
        }
        
        return static::deserialize($row);
    }
    
    /**
     * Get meta with caching
     */
    public static function getWithCache($post_id) {
        $cache_key = static::getCacheKey($post_id);
        $cached = wp_cache_get($cache_key, static::$cache_group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $data = static::get($post_id);
        
        if ($data !== null) {
            wp_cache_set($cache_key, $data, static::$cache_group, static::$cache_expiration);
        }
        
        return $data;
    }
    
    /**
     * Save or update meta
     */
    public static function save($post_id, array $data) {
        global $wpdb;
        
        $table = static::getTableName();
        $data = static::sanitizeData($data);
        $data = static::serialize($data);
        
        $existing = static::exists($post_id);
        
        if ($existing) {
            $data['updated_at'] = current_time('mysql');
            $result = $wpdb->update($table, $data, ['post_id' => $post_id]);
        } else {
            $data['post_id'] = $post_id;
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
        }
        
        // Clear cache
        static::clearCache($post_id);
        
        return $result !== false;
    }
    
    /**
     * Delete meta by post ID
     */
    public static function delete($post_id) {
        global $wpdb;
        
        $table = static::getTableName();
        $result = $wpdb->delete($table, ['post_id' => $post_id], ['%d']);
        
        // Clear cache
        static::clearCache($post_id);
        
        return $result !== false;
    }
    
    /**
     * Check if record exists for post ID
     */
    public static function exists($post_id) {
        global $wpdb;
        
        $table = static::getTableName();
        
        return (bool) $wpdb->get_var(
            $wpdb->prepare("SELECT 1 FROM {$table} WHERE post_id = %d LIMIT 1", $post_id)
        );
    }
    
    /**
     * Get multiple records by post IDs (bulk fetch to prevent N+1)
     */
    public static function bulkGet(array $post_ids) {
        if (empty($post_ids)) {
            return [];
        }
        
        global $wpdb;
        
        $table = static::getTableName();
        $post_ids = array_map('intval', $post_ids);
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE post_id IN ({$placeholders})",
            $post_ids
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $indexed = [];
        foreach ($results as $row) {
            $indexed[$row['post_id']] = static::deserialize($row);
        }
        
        return $indexed;
    }
    
    /**
     * Get total count with optional filters
     */
    public static function count(array $where = []) {
        global $wpdb;
        
        $table = static::getTableName();
        
        $sql = "SELECT COUNT(*) FROM {$table}";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $field => $value) {
                $conditions[] = "{$field} = %s";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Serialize data for database storage
     */
    protected static function serialize(array $data) {
        foreach (static::$field_types as $field => $type) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            
            $value = $data[$field];
            
            switch ($type) {
                case 'json':
                    if (is_array($value) || is_object($value)) {
                        $data[$field] = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
                    } elseif (is_string($value) && !empty($value)) {
                        // Already JSON string, validate it
                        json_decode($value);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $data[$field] = '[]';
                        }
                    } else {
                        $data[$field] = null;
                    }
                    break;
                    
                case 'boolean':
                    $data[$field] = $value ? 1 : 0;
                    break;
                    
                case 'int':
                    $data[$field] = $value !== null && $value !== '' ? (int) $value : null;
                    break;
                    
                case 'float':
                    $data[$field] = $value !== null && $value !== '' ? (float) $value : null;
                    break;
            }
        }
        
        return $data;
    }
    
    /**
     * Deserialize data from database
     */
    protected static function deserialize(array $row) {
        foreach (static::$field_types as $field => $type) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            
            $value = $row[$field];
            
            switch ($type) {
                case 'json':
                    if (is_string($value) && !empty($value)) {
                        $decoded = json_decode($value, true);
                        $row[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
                    } else {
                        $row[$field] = [];
                    }
                    break;
                    
                case 'boolean':
                    $row[$field] = (bool) $value;
                    break;
                    
                case 'int':
                    $row[$field] = $value !== null ? (int) $value : null;
                    break;
                    
                case 'float':
                    $row[$field] = $value !== null ? (float) $value : null;
                    break;
            }
        }
        
        return $row;
    }
    
    /**
     * Sanitize input data
     */
    protected static function sanitizeData(array $data) {
        // Remove any fields that shouldn't be directly set
        unset($data['id'], $data['created_at'], $data['updated_at']);
        
        return $data;
    }
    
    /**
     * Get cache key for a post
     */
    protected static function getCacheKey($post_id) {
        return static::$table . '_' . $post_id;
    }
    
    /**
     * Clear cache for a post
     */
    public static function clearCache($post_id) {
        wp_cache_delete(static::getCacheKey($post_id), static::$cache_group);
    }
    
    /**
     * Clear all cache for this model
     */
    public static function clearAllCache() {
        wp_cache_flush();
    }
}

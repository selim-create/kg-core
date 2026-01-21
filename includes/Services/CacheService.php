<?php
namespace KG_Core\Services;

/**
 * CacheService - Performans için caching mekanizması
 * 
 * Stratejiler:
 * - Recipe detay: 1 saat TTL
 * - Recipe listesi: 5 dakika TTL
 * - Featured içerikler: 5 dakika TTL
 * - Kullanıcıya özel veriler: Cache'lenmez
 */
class CacheService {
    
    private const CACHE_GROUP = 'kg_core';
    private const RECIPE_TTL = 3600;        // 1 saat
    private const LIST_TTL = 300;           // 5 dakika
    private const FEATURED_TTL = 300;       // 5 dakika
    
    /**
     * Recipe cache'den getir
     */
    public static function get_recipe($post_id) {
        $cache_key = 'recipe_' . $post_id;
        
        // Önce object cache dene
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }
        
        // Object cache yoksa transient dene
        $cached = get_transient('kg_' . $cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        return null;
    }
    
    /**
     * Recipe cache'e yaz
     */
    public static function set_recipe($post_id, $data, $ttl = null) {
        $cache_key = 'recipe_' . $post_id;
        $ttl = $ttl ?? self::RECIPE_TTL;
        
        // Object cache'e yaz
        wp_cache_set($cache_key, $data, self::CACHE_GROUP, $ttl);
        
        // Transient'a da yaz (persistent cache yoksa fallback)
        set_transient('kg_' . $cache_key, $data, $ttl);
    }
    
    /**
     * Recipe cache'i temizle
     */
    public static function invalidate_recipe($post_id) {
        $cache_key = 'recipe_' . $post_id;
        
        wp_cache_delete($cache_key, self::CACHE_GROUP);
        delete_transient('kg_' . $cache_key);
        
        // İlgili listeleri de temizle
        self::invalidate_list('recipes');
    }
    
    /**
     * Liste cache'den getir
     */
    public static function get_list($type, $args_hash) {
        $cache_key = "list_{$type}_{$args_hash}";
        
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }
        
        $cached = get_transient('kg_' . $cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        return null;
    }
    
    /**
     * Liste cache'e yaz
     */
    public static function set_list($type, $args_hash, $data) {
        $cache_key = "list_{$type}_{$args_hash}";
        
        wp_cache_set($cache_key, $data, self::CACHE_GROUP, self::LIST_TTL);
        set_transient('kg_' . $cache_key, $data, self::LIST_TTL);
    }
    
    /**
     * Liste cache'i temizle
     */
    public static function invalidate_list($type) {
        global $wpdb;
        
        // Transient'ları temizle (pattern match)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_kg_list_' . $type . '%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_kg_list_' . $type . '%'
        ));
        
        // Object cache group flush (eğer destekleniyorsa)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }
    
    /**
     * Featured cache'den getir
     */
    public static function get_featured($type = 'all', $limit = 10) {
        $cache_key = "featured_{$type}_{$limit}";
        
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }
        
        $cached = get_transient('kg_' . $cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        return null;
    }
    
    /**
     * Featured cache'e yaz
     */
    public static function set_featured($data, $type = 'all', $limit = 10) {
        $cache_key = "featured_{$type}_{$limit}";
        
        wp_cache_set($cache_key, $data, self::CACHE_GROUP, self::FEATURED_TTL);
        set_transient('kg_' . $cache_key, $data, self::FEATURED_TTL);
    }
    
    /**
     * Featured cache'i temizle
     */
    public static function invalidate_featured() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_kg_featured_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_kg_featured_%'"
        );
    }
    
    /**
     * Tüm KG cache'i temizle
     */
    public static function flush_all() {
        global $wpdb;
        
        // Tüm KG transient'larını temizle
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_kg_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_kg_%'"
        );
        
        // Object cache flush
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }
    
    /**
     * Args'dan hash oluştur (cache key için)
     */
    public static function hash_args($args) {
        return md5(serialize($args));
    }
}

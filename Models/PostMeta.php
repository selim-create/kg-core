<?php
namespace KG_Core\Models;

class PostMeta extends BaseModel {
    
    protected static $table = 'kg_post_meta';
    protected static $post_type = 'post';
    
    protected static $field_types = [
        // Boolean fields
        'is_featured' => 'boolean',
        'is_sponsored' => 'boolean',
        'direct_redirect' => 'boolean',
        'has_discount' => 'boolean',
        'expert_approved' => 'boolean',
        
        // Integer fields
        'sponsor_logo_id' => 'int',
        'sponsor_light_logo_id' => 'int',
        'expert_user_id' => 'int',
    ];
    
    /**
     * Query posts with filters
     */
    public static function query(array $args = []) {
        global $wpdb;
        
        $table = static::getTableName();
        $posts_table = $wpdb->posts;
        
        $defaults = [
            'is_featured' => null,
            'is_sponsored' => null,
            'expert_approved' => null,
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'post_date',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ["p.post_type = 'post'", "p.post_status = 'publish'"];
        $params = [];
        
        if ($args['is_featured'] !== null) {
            $where[] = "m.is_featured = %d";
            $params[] = $args['is_featured'] ? 1 : 0;
        }
        
        if ($args['is_sponsored'] !== null) {
            $where[] = "m.is_sponsored = %d";
            $params[] = $args['is_sponsored'] ? 1 : 0;
        }
        
        if ($args['expert_approved'] !== null) {
            $where[] = "m.expert_approved = %d";
            $params[] = $args['expert_approved'] ? 1 : 0;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Whitelist allowed orderby columns
        $allowed_orderby = ['post_date', 'post_title', 'created_at', 'updated_at'];
        $orderby_field = $args['orderby'];
        if (in_array($orderby_field, ['post_date', 'post_title'])) {
            $orderby = "p.{$orderby_field}";
        } elseif (in_array($orderby_field, ['created_at', 'updated_at'])) {
            $orderby = "m.{$orderby_field}";
        } else {
            $orderby = 'p.post_date';
        }
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];
        
        $sql = "SELECT m.*, p.post_title, p.post_name, p.post_excerpt, p.post_author, p.post_date
                FROM {$table} m
                INNER JOIN {$posts_table} p ON m.post_id = p.ID
                WHERE {$where_clause}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";
        
        $query = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return array_map([static::class, 'deserialize'], $results);
    }
    
    /**
     * Get featured posts
     */
    public static function getFeatured($limit = 5) {
        return static::query([
            'is_featured' => true,
            'limit' => $limit,
        ]);
    }
    
    /**
     * Get sponsored posts
     */
    public static function getSponsored($limit = 10) {
        return static::query([
            'is_sponsored' => true,
            'limit' => $limit,
        ]);
    }
    
    /**
     * Get sponsor data formatted for API
     */
    public static function getSponsorData($post_id) {
        $meta = static::getWithCache($post_id);
        
        if (!$meta || !($meta['is_sponsored'] ?? false)) {
            return null;
        }
        
        $sponsor_logo_url = $meta['sponsor_logo_id'] ? wp_get_attachment_url($meta['sponsor_logo_id']) : null;
        $sponsor_light_logo_url = $meta['sponsor_light_logo_id'] ? wp_get_attachment_url($meta['sponsor_light_logo_id']) : null;
        
        return [
            'name' => $meta['sponsor_name'] ?? null,
            'url' => $meta['sponsor_url'] ?? null,
            'logo' => $sponsor_logo_url,
            'light_logo' => $sponsor_light_logo_url,
            'direct_redirect' => $meta['direct_redirect'] ?? false,
            'has_discount' => $meta['has_discount'] ?? false,
            'discount_text' => $meta['discount_text'] ?? null,
            'gam_impression_url' => $meta['gam_impression_url'] ?? null,
            'gam_click_url' => $meta['gam_click_url'] ?? null,
        ];
    }
}

<?php
namespace KG_Core\Models;

class RecipeMeta extends BaseModel {
    
    protected static $table = 'kg_recipe_meta';
    protected static $post_type = 'recipe';
    
    protected static $field_types = [
        // JSON fields
        'ingredients' => 'json',
        'instructions' => 'json',
        'substitutes' => 'json',
        'cross_sell' => 'json',
        'ratings_data' => 'json',
        
        // Boolean fields
        'freezable' => 'boolean',
        'is_featured' => 'boolean',
        'expert_approved' => 'boolean',
        
        // Integer fields
        'prep_time' => 'int',
        'cook_time' => 'int',
        'rating_count' => 'int',
        'base_rating_count' => 'int',
        'expert_user_id' => 'int',
        
        // Float fields
        'calories' => 'float',
        'protein' => 'float',
        'carbs' => 'float',
        'fat' => 'float',
        'fiber' => 'float',
        'sugar' => 'float',
        'sodium' => 'float',
        'rating' => 'float',
        'base_rating' => 'float',
    ];
    
    /**
     * Query recipes with filters
     */
    public static function query(array $args = []) {
        global $wpdb;
        
        $table = static::getTableName();
        $posts_table = $wpdb->posts;
        
        $defaults = [
            'is_featured' => null,
            'difficulty' => null,
            'expert_approved' => null,
            'min_rating' => null,
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ["p.post_type = 'recipe'", "p.post_status = 'publish'"];
        $params = [];
        
        if ($args['is_featured'] !== null) {
            $where[] = "m.is_featured = %d";
            $params[] = $args['is_featured'] ? 1 : 0;
        }
        
        if ($args['difficulty'] !== null) {
            $where[] = "m.difficulty = %s";
            $params[] = strtolower($args['difficulty']);
        }
        
        if ($args['expert_approved'] !== null) {
            $where[] = "m.expert_approved = %d";
            $params[] = $args['expert_approved'] ? 1 : 0;
        }
        
        if ($args['min_rating'] !== null) {
            $where[] = "m.rating >= %f";
            $params[] = (float) $args['min_rating'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Whitelist allowed orderby columns
        $allowed_orderby = ['created_at', 'updated_at', 'rating', 'rating_count', 'prep_time', 'cook_time'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];
        
        $sql = "SELECT m.*, p.post_title, p.post_name, p.post_excerpt, p.post_author, p.post_date
                FROM {$table} m
                INNER JOIN {$posts_table} p ON m.post_id = p.ID
                WHERE {$where_clause}
                ORDER BY m.{$orderby} {$order}
                LIMIT %d OFFSET %d";
        
        $query = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Deserialize each row
        return array_map([static::class, 'deserialize'], $results);
    }
    
    /**
     * Get featured recipes
     */
    public static function getFeatured($limit = 5) {
        return static::query([
            'is_featured' => true,
            'limit' => $limit,
            'orderby' => 'updated_at',
            'order' => 'DESC',
        ]);
    }
    
    /**
     * Update rating for a recipe
     */
    public static function updateRating($post_id, $new_rating, $user_id) {
        $meta = static::get($post_id);
        
        if (!$meta) {
            return false;
        }
        
        $ratings_data = $meta['ratings_data'] ?: [];
        $ratings_data[$user_id] = (float) $new_rating;
        
        // Calculate new average
        $base_rating = $meta['base_rating'] ?: 4.0;
        $base_count = $meta['base_rating_count'] ?: 50;
        
        $real_sum = array_sum($ratings_data);
        $real_count = count($ratings_data);
        
        $total_count = $base_count + $real_count;
        $weighted_sum = ($base_rating * $base_count) + $real_sum;
        $average = $total_count > 0 ? $weighted_sum / $total_count : 0;
        
        return static::save($post_id, [
            'ratings_data' => $ratings_data,
            'rating' => round($average, 2),
            'rating_count' => $total_count,
        ]);
    }
}

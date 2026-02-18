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
    
    /**
     * Format prep_time for display
     * @param int|null $minutes
     * @return string|null "30 dakika", "1 saat 30 dakika", etc.
     */
    public static function formatPrepTime($minutes) {
        if ($minutes === null || $minutes === '') {
            return null;
        }
        $minutes = intval($minutes);
        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            if ($mins > 0) {
                return "{$hours} saat {$mins} dakika";
            }
            return "{$hours} saat";
        }
        return "{$minutes} dakika";
    }
    
    /**
     * Format cook_time for display
     */
    public static function formatCookTime($minutes) {
        return self::formatPrepTime($minutes); // Same logic
    }
    
    /**
     * Format difficulty for display
     * @param string|null $difficulty "kolay", "orta", "zor"
     * @return string|null "Kolay", "Orta", "Zor"
     */
    public static function formatDifficulty($difficulty) {
        if (empty($difficulty)) {
            return null;
        }
        $map = [
            'kolay' => 'Kolay',
            'orta' => 'Orta',
            'zor' => 'Zor',
        ];
        return $map[strtolower($difficulty)] ?? ucfirst($difficulty);
    }
    
    /**
     * Format nutrition value with unit
     * @param float|null $value
     * @param string $unit "kcal", "g", "mg"
     * @return string|null "180 kcal", "6 g", etc.
     */
    public static function formatNutritionValue($value, $unit) {
        if ($value === null || $value === '') {
            return null;
        }
        // Remove trailing zeros: 6.00 -> 6, 6.50 -> 6.5
        $formatted = rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
        return "{$formatted} {$unit}";
    }
    
    /**
     * Get all nutrition values formatted
     * @param array $meta Recipe meta data
     * @return array Formatted nutrition values
     */
    public static function formatNutrition($meta) {
        return [
            'calories' => self::formatNutritionValue($meta['calories'] ?? null, 'kcal'),
            'protein' => self::formatNutritionValue($meta['protein'] ?? null, 'g'),
            'carbs' => self::formatNutritionValue($meta['carbs'] ?? null, 'g'),
            'fat' => self::formatNutritionValue($meta['fat'] ?? null, 'g'),
            'fiber' => self::formatNutritionValue($meta['fiber'] ?? null, 'g'),
            'sugar' => self::formatNutritionValue($meta['sugar'] ?? null, 'g'),
            'sodium' => self::formatNutritionValue($meta['sodium'] ?? null, 'mg'),
        ];
    }
    
    /**
     * Get full formatted recipe data (combines raw + formatted)
     * @param int $post_id
     * @return array|null
     */
    public static function getFormatted($post_id) {
        $meta = self::get($post_id);
        if (!$meta) {
            return null;
        }
        
        // Add formatted versions
        $meta['prep_time_formatted'] = self::formatPrepTime($meta['prep_time']);
        $meta['cook_time_formatted'] = self::formatCookTime($meta['cook_time']);
        $meta['difficulty_formatted'] = self::formatDifficulty($meta['difficulty']);
        $meta['nutrition_formatted'] = self::formatNutrition($meta);
        
        return $meta;
    }
}

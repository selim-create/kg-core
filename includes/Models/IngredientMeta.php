<?php
namespace KG_Core\Models;

class IngredientMeta extends BaseModel {
    
    protected static $table = 'kg_ingredient_meta';
    protected static $post_type = 'ingredient';
    
    protected static $field_types = [
        // JSON fields
        'season' => 'json',
        'prep_methods' => 'json',
        'prep_by_age' => 'json',
        'pairings' => 'json',
        'faq' => 'json',
        
        // Boolean fields
        'is_featured' => 'boolean',
        'expert_approved' => 'boolean',
        
        // Integer fields
        'start_age' => 'int',
        'expert_user_id' => 'int',
        
        // Float fields
        'calories_100g' => 'float',
        'protein_100g' => 'float',
        'carbs_100g' => 'float',
        'fat_100g' => 'float',
        'fiber_100g' => 'float',
        'sugar_100g' => 'float',
    ];
    
    /**
     * Allergy risk mapping (Turkish to English for DB)
     */
    private static $allergy_risk_map = [
        'Düşük' => 'low',
        'Orta' => 'medium',
        'Yüksek' => 'high',
        'low' => 'low',
        'medium' => 'medium',
        'high' => 'high',
    ];
    
    /**
     * Reverse allergy risk mapping (English to Turkish for display)
     */
    private static $allergy_risk_display = [
        'low' => 'Düşük',
        'medium' => 'Orta',
        'high' => 'Yüksek',
    ];
    
    /**
     * Override save to handle allergy_risk mapping
     */
    public static function save($post_id, array $data) {
        if (isset($data['allergy_risk'])) {
            $data['allergy_risk'] = self::$allergy_risk_map[$data['allergy_risk']] ?? $data['allergy_risk'];
        }
        
        return parent::save($post_id, $data);
    }
    
    /**
     * Override deserialize to map allergy_risk back to Turkish
     */
    protected static function deserialize(array $row) {
        $row = parent::deserialize($row);
        
        if (isset($row['allergy_risk']) && isset(self::$allergy_risk_display[$row['allergy_risk']])) {
            $row['allergy_risk_display'] = self::$allergy_risk_display[$row['allergy_risk']];
        }
        
        return $row;
    }
    
    /**
     * Query ingredients with filters
     */
    public static function query(array $args = []) {
        global $wpdb;
        
        $table = static::getTableName();
        $posts_table = $wpdb->posts;
        
        $defaults = [
            'is_featured' => null,
            'start_age' => null,
            'max_start_age' => null,
            'allergy_risk' => null,
            'expert_approved' => null,
            'search' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'post_title',
            'order' => 'ASC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ["p.post_type = 'ingredient'", "p.post_status = 'publish'"];
        $params = [];
        
        if ($args['is_featured'] !== null) {
            $where[] = "m.is_featured = %d";
            $params[] = $args['is_featured'] ? 1 : 0;
        }
        
        if ($args['start_age'] !== null) {
            $where[] = "m.start_age = %d";
            $params[] = (int) $args['start_age'];
        }
        
        if ($args['max_start_age'] !== null) {
            $where[] = "m.start_age <= %d";
            $params[] = (int) $args['max_start_age'];
        }
        
        if ($args['allergy_risk'] !== null) {
            $risk = self::$allergy_risk_map[$args['allergy_risk']] ?? $args['allergy_risk'];
            $where[] = "m.allergy_risk = %s";
            $params[] = $risk;
        }
        
        if ($args['expert_approved'] !== null) {
            $where[] = "m.expert_approved = %d";
            $params[] = $args['expert_approved'] ? 1 : 0;
        }
        
        if ($args['search'] !== null) {
            $where[] = "p.post_title LIKE %s";
            $params[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Whitelist allowed orderby columns
        $allowed_orderby = ['post_title', 'created_at', 'updated_at', 'start_age'];
        $orderby_field = $args['orderby'];
        if ($orderby_field === 'post_title') {
            $orderby = 'p.post_title';
        } elseif (in_array($orderby_field, $allowed_orderby)) {
            $orderby = "m.{$orderby_field}";
        } else {
            $orderby = 'p.post_title';
        }
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];
        
        $sql = "SELECT m.*, p.post_title, p.post_name, p.post_excerpt, p.post_author, p.post_date, p.post_content
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
     * Get featured ingredients
     */
    public static function getFeatured($limit = 5) {
        return static::query([
            'is_featured' => true,
            'limit' => $limit,
        ]);
    }
    
    /**
     * Get ingredients suitable for age
     */
    public static function getForAge($age_months, $limit = 20) {
        return static::query([
            'max_start_age' => $age_months,
            'limit' => $limit,
            'orderby' => 'post_title',
            'order' => 'ASC',
        ]);
    }
}

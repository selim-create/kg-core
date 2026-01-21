<?php
namespace KG_Core\Database;

use KG_Core\Models\RecipeMeta;
use KG_Core\Models\IngredientMeta;
use KG_Core\Models\PostMeta;

/**
 * DataMigration - Migrate wp_postmeta to custom tables
 * 
 * Migrates existing postmeta data to kg_recipe_meta, kg_ingredient_meta, and kg_post_meta tables
 */
class DataMigration {
    
    /**
     * Meta key mappings for Recipe
     */
    private static $recipe_mappings = [
        '_kg_prep_time' => 'prep_time',
        '_kg_cook_time' => 'cook_time',
        '_kg_serving_size' => 'serving_size',
        '_kg_difficulty' => 'difficulty',
        '_kg_freezable' => 'freezable',
        '_kg_storage_info' => 'storage_info',
        '_kg_is_featured' => 'is_featured',
        '_kg_video_url' => 'video_url',
        '_kg_special_notes' => 'special_notes',
        '_kg_calories' => 'calories',
        '_kg_protein' => 'protein',
        '_kg_carbs' => 'carbs',
        '_kg_fat' => 'fat',
        '_kg_fiber' => 'fiber',
        '_kg_sugar' => 'sugar',
        '_kg_sodium' => 'sodium',
        '_kg_vitamins' => 'vitamins',
        '_kg_minerals' => 'minerals',
        '_kg_expert_user_id' => 'expert_user_id',
        '_kg_expert_name' => 'expert_name',
        '_kg_expert_title' => 'expert_title',
        '_kg_expert_note' => 'expert_note',
        '_kg_expert_approved' => 'expert_approved',
        '_kg_ingredients' => 'ingredients',
        '_kg_instructions' => 'instructions',
        '_kg_substitutes' => 'substitutes',
        '_kg_cross_sell' => 'cross_sell',
        '_kg_rating' => 'rating',
        '_kg_rating_count' => 'rating_count',
        '_kg_base_rating' => 'base_rating',
        '_kg_base_rating_count' => 'base_rating_count',
        '_kg_ratings' => 'ratings_data',
    ];
    
    /**
     * Meta key mappings for Ingredient
     */
    private static $ingredient_mappings = [
        '_kg_start_age' => 'start_age',
        '_kg_allergy_risk' => 'allergy_risk',
        '_kg_is_featured' => 'is_featured',
        '_kg_season' => 'season',
        '_kg_ing_calories_100g' => 'calories_100g',
        '_kg_ing_protein_100g' => 'protein_100g',
        '_kg_ing_carbs_100g' => 'carbs_100g',
        '_kg_ing_fat_100g' => 'fat_100g',
        '_kg_ing_fiber_100g' => 'fiber_100g',
        '_kg_ing_sugar_100g' => 'sugar_100g',
        '_kg_ing_vitamins' => 'vitamins',
        '_kg_ing_minerals' => 'minerals',
        '_kg_cross_contamination' => 'cross_contamination',
        '_kg_allergy_symptoms' => 'allergy_symptoms',
        '_kg_alternatives' => 'alternatives',
        '_kg_benefits' => 'benefits',
        '_kg_storage_tips' => 'storage_tips',
        '_kg_preparation_tips' => 'preparation_tips',
        '_kg_selection_tips' => 'selection_tips',
        '_kg_pro_tips' => 'pro_tips',
        '_kg_prep_methods' => 'prep_methods',
        '_kg_prep_by_age' => 'prep_by_age',
        '_kg_pairings' => 'pairings',
        '_kg_faq' => 'faq',
        '_kg_expert_user_id' => 'expert_user_id',
        '_kg_expert_name' => 'expert_name',
        '_kg_expert_title' => 'expert_title',
        '_kg_expert_note' => 'expert_note',
        '_kg_expert_approved' => 'expert_approved',
    ];
    
    /**
     * Meta key mappings for Post
     */
    private static $post_mappings = [
        '_kg_is_featured' => 'is_featured',
        '_kg_is_sponsored' => 'is_sponsored',
        '_kg_sponsor_name' => 'sponsor_name',
        '_kg_sponsor_url' => 'sponsor_url',
        '_kg_sponsor_logo' => 'sponsor_logo_id',
        '_kg_sponsor_light_logo' => 'sponsor_light_logo_id',
        '_kg_direct_redirect' => 'direct_redirect',
        '_kg_gam_impression_url' => 'gam_impression_url',
        '_kg_gam_click_url' => 'gam_click_url',
        '_kg_has_discount' => 'has_discount',
        '_kg_discount_text' => 'discount_text',
        '_kg_expert_user_id' => 'expert_user_id',
        '_kg_expert_name' => 'expert_name',
        '_kg_expert_title' => 'expert_title',
        '_kg_expert_note' => 'expert_note',
        '_kg_expert_approved' => 'expert_approved',
    ];
    
    /**
     * Value mappings for difficulty field
     */
    private static $difficulty_mappings = [
        'Kolay' => 'kolay',
        'Orta' => 'orta',
        'Zor' => 'zor',
        'kolay' => 'kolay',
        'orta' => 'orta',
        'zor' => 'zor',
    ];
    
    /**
     * Value mappings for allergy_risk field
     */
    private static $allergy_risk_mappings = [
        'Düşük' => 'low',
        'Orta' => 'medium',
        'Yüksek' => 'high',
        'low' => 'low',
        'medium' => 'medium',
        'high' => 'high',
    ];
    
    /**
     * Type mappings for field conversion
     */
    private static $type_mappings = [
        'recipe' => [
            'prep_time' => 'int',
            'cook_time' => 'int',
            'rating_count' => 'int',
            'base_rating_count' => 'int',
            'expert_user_id' => 'int',
            'calories' => 'float',
            'protein' => 'float',
            'carbs' => 'float',
            'fat' => 'float',
            'fiber' => 'float',
            'sugar' => 'float',
            'sodium' => 'float',
            'rating' => 'float',
            'base_rating' => 'float',
            'freezable' => 'boolean',
            'is_featured' => 'boolean',
            'expert_approved' => 'boolean',
            'ingredients' => 'json',
            'instructions' => 'json',
            'substitutes' => 'json',
            'cross_sell' => 'json',
            'ratings_data' => 'json',
        ],
        'ingredient' => [
            'start_age' => 'int',
            'expert_user_id' => 'int',
            'calories_100g' => 'float',
            'protein_100g' => 'float',
            'carbs_100g' => 'float',
            'fat_100g' => 'float',
            'fiber_100g' => 'float',
            'sugar_100g' => 'float',
            'is_featured' => 'boolean',
            'expert_approved' => 'boolean',
            'season' => 'json',
            'prep_methods' => 'json',
            'prep_by_age' => 'json',
            'pairings' => 'json',
            'faq' => 'json',
        ],
        'post' => [
            'sponsor_logo_id' => 'int',
            'sponsor_light_logo_id' => 'int',
            'expert_user_id' => 'int',
            'is_featured' => 'boolean',
            'is_sponsored' => 'boolean',
            'direct_redirect' => 'boolean',
            'has_discount' => 'boolean',
            'expert_approved' => 'boolean',
        ],
    ];
    
    /**
     * Migrate all post types
     */
    public static function migrateAll() {
        $results = [
            'recipes' => self::migrateRecipes(),
            'ingredients' => self::migrateIngredients(),
            'posts' => self::migratePosts(),
        ];
        
        return $results;
    }
    
    /**
     * Migrate recipes
     */
    public static function migrateRecipes($batch_size = 50) {
        global $wpdb;
        
        $migrated = 0;
        $skipped = 0;
        $errors = [];
        $offset = 0;
        
        while (true) {
            // Get recipes in batches
            $recipes = get_posts([
                'post_type' => 'recipe',
                'post_status' => 'any',
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'fields' => 'ids',
            ]);
            
            if (empty($recipes)) {
                break;
            }
            
            foreach ($recipes as $post_id) {
                // Skip if already migrated
                if (RecipeMeta::exists($post_id)) {
                    $skipped++;
                    continue;
                }
                
                try {
                    $data = self::extractRecipeData($post_id);
                    
                    if (!empty($data)) {
                        $result = RecipeMeta::save($post_id, $data);
                        if (!$result) {
                            $errors[] = [
                                'post_id' => $post_id,
                                'error' => 'Failed to save recipe meta',
                            ];
                        } else {
                            $migrated++;
                        }
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'post_id' => $post_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            $offset += $batch_size;
        }
        
        return [
            'migrated' => $migrated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
    
    /**
     * Migrate ingredients
     */
    public static function migrateIngredients($batch_size = 50) {
        global $wpdb;
        
        $migrated = 0;
        $skipped = 0;
        $errors = [];
        $offset = 0;
        
        while (true) {
            // Get ingredients in batches
            $ingredients = get_posts([
                'post_type' => 'ingredient',
                'post_status' => 'any',
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'fields' => 'ids',
            ]);
            
            if (empty($ingredients)) {
                break;
            }
            
            foreach ($ingredients as $post_id) {
                // Skip if already migrated
                if (IngredientMeta::exists($post_id)) {
                    $skipped++;
                    continue;
                }
                
                try {
                    $data = self::extractIngredientData($post_id);
                    
                    if (!empty($data)) {
                        $result = IngredientMeta::save($post_id, $data);
                        if (!$result) {
                            $errors[] = [
                                'post_id' => $post_id,
                                'error' => 'Failed to save ingredient meta',
                            ];
                        } else {
                            $migrated++;
                        }
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'post_id' => $post_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            $offset += $batch_size;
        }
        
        return [
            'migrated' => $migrated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
    
    /**
     * Migrate posts
     */
    public static function migratePosts($batch_size = 50) {
        global $wpdb;
        
        $migrated = 0;
        $skipped = 0;
        $errors = [];
        $offset = 0;
        
        while (true) {
            // Get posts in batches
            $posts = get_posts([
                'post_type' => 'post',
                'post_status' => 'any',
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'fields' => 'ids',
            ]);
            
            if (empty($posts)) {
                break;
            }
            
            foreach ($posts as $post_id) {
                // Skip if already migrated
                if (PostMeta::exists($post_id)) {
                    $skipped++;
                    continue;
                }
                
                try {
                    $data = self::extractPostData($post_id);
                    
                    if (!empty($data)) {
                        $result = PostMeta::save($post_id, $data);
                        if (!$result) {
                            $errors[] = [
                                'post_id' => $post_id,
                                'error' => 'Failed to save post meta',
                            ];
                        } else {
                            $migrated++;
                        }
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'post_id' => $post_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            $offset += $batch_size;
        }
        
        return [
            'migrated' => $migrated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
    
    /**
     * Extract recipe data from postmeta
     */
    private static function extractRecipeData($post_id) {
        $data = [];
        
        foreach (self::$recipe_mappings as $meta_key => $field_name) {
            $value = get_post_meta($post_id, $meta_key, true);
            
            // Handle serialized data
            if (is_string($value)) {
                $unserialized = maybe_unserialize($value);
                if ($unserialized !== $value) {
                    $value = $unserialized;
                }
            }
            
            // Apply value mappings
            if ($field_name === 'difficulty' && isset(self::$difficulty_mappings[$value])) {
                $value = self::$difficulty_mappings[$value];
            }
            
            // Type conversion
            $value = self::convertType($field_name, $value, 'recipe');
            
            if ($value !== null && $value !== '') {
                $data[$field_name] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Extract ingredient data from postmeta
     */
    private static function extractIngredientData($post_id) {
        $data = [];
        
        foreach (self::$ingredient_mappings as $meta_key => $field_name) {
            $value = get_post_meta($post_id, $meta_key, true);
            
            // Handle serialized data
            if (is_string($value)) {
                $unserialized = maybe_unserialize($value);
                if ($unserialized !== $value) {
                    $value = $unserialized;
                }
            }
            
            // Apply value mappings
            if ($field_name === 'allergy_risk' && isset(self::$allergy_risk_mappings[$value])) {
                $value = self::$allergy_risk_mappings[$value];
            }
            
            // Type conversion
            $value = self::convertType($field_name, $value, 'ingredient');
            
            if ($value !== null && $value !== '') {
                $data[$field_name] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Extract post data from postmeta
     */
    private static function extractPostData($post_id) {
        $data = [];
        
        foreach (self::$post_mappings as $meta_key => $field_name) {
            $value = get_post_meta($post_id, $meta_key, true);
            
            // Handle serialized data
            if (is_string($value)) {
                $unserialized = maybe_unserialize($value);
                if ($unserialized !== $value) {
                    $value = $unserialized;
                }
            }
            
            // Type conversion
            $value = self::convertType($field_name, $value, 'post');
            
            if ($value !== null && $value !== '') {
                $data[$field_name] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Convert value to appropriate type
     */
    private static function convertType($field_name, $value, $post_type) {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Use class property for type mappings
        $type = self::$type_mappings[$post_type][$field_name] ?? 'string';
        
        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'int':
                // Special handling for time fields in recipes
                if ($post_type === 'recipe' && in_array($field_name, ['prep_time', 'cook_time'])) {
                    return self::parseTimeValue($value);
                }
                // Special handling for start_age in ingredients
                if ($post_type === 'ingredient' && $field_name === 'start_age') {
                    return self::parseNumericValue($value);
                }
                return self::parseNumericValue($value);
            case 'float':
                // Special handling for nutrition values
                if (in_array($field_name, ['calories', 'protein', 'carbs', 'fat', 'fiber', 'sugar', 'sodium', 
                                          'calories_100g', 'protein_100g', 'carbs_100g', 'fat_100g', 'fiber_100g', 'sugar_100g'])) {
                    return self::parseNutritionValue($value);
                }
                return self::parseNumericValue($value);
            case 'json':
                // Keep as-is, will be serialized by model
                return $value;
            default:
                return $value;
        }
    }
    
    /**
     * Parse time value from string (e.g., "30 dakika", "1 saat", "1.5 saat")
     */
    private static function parseTimeValue($value) {
        if (is_numeric($value)) {
            return intval($value);
        }
        
        if (!is_string($value)) {
            return null;
        }
        
        // "1 saat" or "1.5 saat" -> convert to minutes
        if (preg_match('/(\d+(?:\.\d+)?)\s*saat/iu', $value, $matches)) {
            return intval(floatval($matches[1]) * 60);
        }
        
        // "30 dakika" or "30 dk" -> extract number
        if (preg_match('/(\d+)/', $value, $matches)) {
            return intval($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Parse nutrition value from string (e.g., "180 kcal", "6 g", "200 mg")
     */
    private static function parseNutritionValue($value) {
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        if (!is_string($value)) {
            return null;
        }
        
        // "180 kcal", "6 g", "200 mg" -> extract number
        if (preg_match('/(\d+(?:[.,]\d+)?)/', $value, $matches)) {
            // Turkish decimal separator (comma) -> dot
            return floatval(str_replace(',', '.', $matches[1]));
        }
        
        return null;
    }
    
    /**
     * Parse generic numeric value from string
     */
    private static function parseNumericValue($value) {
        if (is_numeric($value)) {
            return intval($value);
        }
        
        if (!is_string($value)) {
            return null;
        }
        
        // Extract first number from string
        if (preg_match('/(\d+)/', $value, $matches)) {
            return intval($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Verify migration - find missing records
     */
    public static function verify($type = 'all') {
        $missing = [];
        
        if ($type === 'all' || $type === 'recipe') {
            $missing['recipe'] = self::verifyRecipes();
        }
        
        if ($type === 'all' || $type === 'ingredient') {
            $missing['ingredient'] = self::verifyIngredients();
        }
        
        if ($type === 'all' || $type === 'post') {
            $missing['post'] = self::verifyPosts();
        }
        
        return $missing;
    }
    
    /**
     * Verify recipes
     */
    private static function verifyRecipes() {
        global $wpdb;
        
        // Get post IDs that have _kg_ meta but don't have custom meta
        // Only count publish status posts with actual KG meta data
        $sql = "
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->prefix}kg_recipe_meta m ON p.ID = m.post_id 
            WHERE p.post_type = 'recipe' 
            AND p.post_status = 'publish'
            AND pm.meta_key LIKE '_kg_%'
            AND m.post_id IS NULL
        ";
        
        $results = $wpdb->get_col($sql);
        
        return array_map('intval', $results);
    }
    
    /**
     * Verify ingredients
     */
    private static function verifyIngredients() {
        global $wpdb;
        
        // Get post IDs that have _kg_ meta but don't have custom meta
        // Only count publish status posts with actual KG meta data
        $sql = "
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->prefix}kg_ingredient_meta m ON p.ID = m.post_id 
            WHERE p.post_type = 'ingredient' 
            AND p.post_status = 'publish'
            AND pm.meta_key LIKE '_kg_%'
            AND m.post_id IS NULL
        ";
        
        $results = $wpdb->get_col($sql);
        
        return array_map('intval', $results);
    }
    
    /**
     * Verify posts
     */
    private static function verifyPosts() {
        global $wpdb;
        
        // Get post IDs that have _kg_ meta but don't have custom meta
        // Only count publish status posts with actual KG meta data
        $sql = "
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->prefix}kg_post_meta m ON p.ID = m.post_id 
            WHERE p.post_type = 'post' 
            AND p.post_status = 'publish'
            AND pm.meta_key LIKE '_kg_%'
            AND m.post_id IS NULL
        ";
        
        $results = $wpdb->get_col($sql);
        
        return array_map('intval', $results);
    }
    
    /**
     * Rollback migration - truncate table
     */
    public static function rollback($type) {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        
        switch ($type) {
            case 'recipe':
                $wpdb->query("TRUNCATE TABLE {$prefix}kg_recipe_meta");
                break;
            case 'ingredient':
                $wpdb->query("TRUNCATE TABLE {$prefix}kg_ingredient_meta");
                break;
            case 'post':
                $wpdb->query("TRUNCATE TABLE {$prefix}kg_post_meta");
                break;
            case 'all':
                $wpdb->query("TRUNCATE TABLE {$prefix}kg_recipe_meta");
                $wpdb->query("TRUNCATE TABLE {$prefix}kg_ingredient_meta");
                $wpdb->query("TRUNCATE TABLE {$prefix}kg_post_meta");
                break;
        }
        
        return true;
    }
    
    /**
     * Migrate a single post by ID and type
     * 
     * @param int $post_id Post ID to migrate
     * @param string $post_type Post type (recipe, ingredient, post)
     * @return bool True on success, false on failure
     */
    public static function migrateSinglePost($post_id, $post_type) {
        try {
            $data = null;
            
            switch ($post_type) {
                case 'recipe':
                    // Skip if already migrated
                    if (RecipeMeta::exists($post_id)) {
                        return true;
                    }
                    
                    $data = self::extractRecipeData($post_id);
                    
                    if (!empty($data)) {
                        $result = RecipeMeta::save($post_id, $data);
                        if (!$result) {
                            error_log("Failed to save recipe meta for post {$post_id}");
                            return false;
                        }
                        return true;
                    }
                    break;
                    
                case 'ingredient':
                    // Skip if already migrated
                    if (IngredientMeta::exists($post_id)) {
                        return true;
                    }
                    
                    $data = self::extractIngredientData($post_id);
                    
                    if (!empty($data)) {
                        $result = IngredientMeta::save($post_id, $data);
                        if (!$result) {
                            error_log("Failed to save ingredient meta for post {$post_id}");
                            return false;
                        }
                        return true;
                    }
                    break;
                    
                case 'post':
                    // Skip if already migrated
                    if (PostMeta::exists($post_id)) {
                        return true;
                    }
                    
                    $data = self::extractPostData($post_id);
                    
                    if (!empty($data)) {
                        $result = PostMeta::save($post_id, $data);
                        if (!$result) {
                            error_log("Failed to save post meta for post {$post_id}");
                            return false;
                        }
                        return true;
                    }
                    break;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Error migrating {$post_type} {$post_id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Force migrate a single post (delete existing and re-migrate)
     * 
     * @param int $post_id Post ID to migrate
     * @param string $post_type Post type (recipe, ingredient, post)
     * @return bool True on success, false on failure
     */
    public static function forceMigrate($post_id, $post_type) {
        try {
            // Delete existing record first
            switch ($post_type) {
                case 'recipe':
                    RecipeMeta::delete($post_id);
                    break;
                case 'ingredient':
                    IngredientMeta::delete($post_id);
                    break;
                case 'post':
                    PostMeta::delete($post_id);
                    break;
                default:
                    return false;
            }
            
            // Now migrate
            return self::migrateSinglePost($post_id, $post_type);
        } catch (\Exception $e) {
            error_log("Error force migrating {$post_type} {$post_id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Force migrate multiple posts
     * 
     * @param array $post_ids Array of post IDs to migrate
     * @param string $post_type Post type (recipe, ingredient, post)
     * @return array Results with success/failed counts and error list
     */
    public static function forceMigrateMultiple(array $post_ids, $post_type) {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($post_ids as $post_id) {
            $result = self::forceMigrate($post_id, $post_type);
            if ($result === true) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $post_id;
            }
        }
        
        return $results;
    }
    
    /**
     * Force migrate all missing records for a post type
     * 
     * @param string $post_type Post type (recipe, ingredient, post)
     * @return array Results with total, migrated, failed counts and errors
     */
    public static function forceMigrateMissing($post_type) {
        global $wpdb;
        
        $table_map = [
            'recipe' => 'kg_recipe_meta',
            'ingredient' => 'kg_ingredient_meta',
            'post' => 'kg_post_meta',
        ];
        
        if (!isset($table_map[$post_type])) {
            return ['error' => 'Invalid post type'];
        }
        
        // Table name is from a whitelist, safe to use
        $table_name = $table_map[$post_type];
        $table = $wpdb->prefix . $table_name;
        
        // Find missing post IDs - using esc_sql for table name even though it's from whitelist
        $sql = $wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN " . esc_sql($table) . " m ON p.ID = m.post_id
            WHERE p.post_type = %s 
            AND p.post_status != 'trash'
            AND m.post_id IS NULL
        ", $post_type);
        
        $missing_ids = $wpdb->get_col($sql);
        
        if (empty($missing_ids)) {
            return [
                'total' => 0,
                'migrated' => 0,
                'failed' => 0,
                'message' => 'No missing records found'
            ];
        }
        
        $migrated = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($missing_ids as $post_id) {
            try {
                $result = self::forceMigrate($post_id, $post_type);
                if ($result === true) {
                    $migrated++;
                } else {
                    $failed++;
                    $errors[] = "Post {$post_id}: Migration returned false";
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Post {$post_id}: " . $e->getMessage();
            }
        }
        
        return [
            'total' => count($missing_ids),
            'migrated' => $migrated,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 20), // First 20 errors only
        ];
    }
}

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
                        RecipeMeta::save($post_id, $data);
                        $migrated++;
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
                        IngredientMeta::save($post_id, $data);
                        $migrated++;
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
                        PostMeta::save($post_id, $data);
                        $migrated++;
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
        
        // Get field types from respective model
        $field_types = [];
        if ($post_type === 'recipe') {
            $field_types = RecipeMeta::class::{'field_types'} ?? [];
        } elseif ($post_type === 'ingredient') {
            $field_types = IngredientMeta::class::{'field_types'} ?? [];
        } elseif ($post_type === 'post') {
            $field_types = PostMeta::class::{'field_types'} ?? [];
        }
        
        // Use reflection to get protected field_types
        try {
            $reflection = null;
            if ($post_type === 'recipe') {
                $reflection = new \ReflectionClass(RecipeMeta::class);
            } elseif ($post_type === 'ingredient') {
                $reflection = new \ReflectionClass(IngredientMeta::class);
            } elseif ($post_type === 'post') {
                $reflection = new \ReflectionClass(PostMeta::class);
            }
            
            if ($reflection) {
                $property = $reflection->getProperty('field_types');
                $property->setAccessible(true);
                $field_types = $property->getValue();
            }
        } catch (\Exception $e) {
            // Fallback to manual type detection
        }
        
        $type = $field_types[$field_name] ?? 'string';
        
        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'json':
                // Keep as-is, will be serialized by model
                return $value;
            default:
                return $value;
        }
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
        $recipes = get_posts([
            'post_type' => 'recipe',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        
        $missing = [];
        foreach ($recipes as $post_id) {
            if (!RecipeMeta::exists($post_id)) {
                $missing[] = $post_id;
            }
        }
        
        return $missing;
    }
    
    /**
     * Verify ingredients
     */
    private static function verifyIngredients() {
        $ingredients = get_posts([
            'post_type' => 'ingredient',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        
        $missing = [];
        foreach ($ingredients as $post_id) {
            if (!IngredientMeta::exists($post_id)) {
                $missing[] = $post_id;
            }
        }
        
        return $missing;
    }
    
    /**
     * Verify posts
     */
    private static function verifyPosts() {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        
        $missing = [];
        foreach ($posts as $post_id) {
            if (!PostMeta::exists($post_id)) {
                $missing[] = $post_id;
            }
        }
        
        return $missing;
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
}

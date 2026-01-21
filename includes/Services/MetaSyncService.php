<?php
namespace KG_Core\Services;

use KG_Core\Models\RecipeMeta;
use KG_Core\Models\IngredientMeta;
use KG_Core\Models\PostMeta;

/**
 * MetaSyncService - Dual-write synchronization service
 * 
 * Syncs wp_postmeta data to custom tables (kg_recipe_meta, kg_ingredient_meta, kg_post_meta)
 * Used in MetaBox save hooks to maintain data consistency during migration.
 */
class MetaSyncService {
    
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
     * Sync Recipe meta to custom table
     * 
     * @param int $post_id Recipe post ID
     * @param array $data Optional data to use instead of extracting from postmeta
     * @return bool True on success, false on failure
     */
    public static function syncRecipe( $post_id, $data = [] ) {
        try {
            // Extract data from postmeta if not provided
            if ( empty( $data ) ) {
                $data = self::extractRecipeFromPostMeta( $post_id );
            }
            
            // Only sync if we have data
            if ( empty( $data ) ) {
                return false;
            }
            
            // Save to custom table
            return RecipeMeta::save( $post_id, $data );
        } catch ( \Exception $e ) {
            error_log( 'KG Core MetaSyncService: Failed to sync recipe ' . $post_id . ': ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Sync Ingredient meta to custom table
     * 
     * @param int $post_id Ingredient post ID
     * @param array $data Optional data to use instead of extracting from postmeta
     * @return bool True on success, false on failure
     */
    public static function syncIngredient( $post_id, $data = [] ) {
        try {
            // Extract data from postmeta if not provided
            if ( empty( $data ) ) {
                $data = self::extractIngredientFromPostMeta( $post_id );
            }
            
            // Only sync if we have data
            if ( empty( $data ) ) {
                return false;
            }
            
            // Save to custom table
            return IngredientMeta::save( $post_id, $data );
        } catch ( \Exception $e ) {
            error_log( 'KG Core MetaSyncService: Failed to sync ingredient ' . $post_id . ': ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Sync Post meta to custom table
     * 
     * @param int $post_id Post ID
     * @param array $data Optional data to use instead of extracting from postmeta
     * @return bool True on success, false on failure
     */
    public static function syncPost( $post_id, $data = [] ) {
        try {
            // Extract data from postmeta if not provided
            if ( empty( $data ) ) {
                $data = self::extractPostFromPostMeta( $post_id );
            }
            
            // Only sync if we have data
            if ( empty( $data ) ) {
                return false;
            }
            
            // Save to custom table
            return PostMeta::save( $post_id, $data );
        } catch ( \Exception $e ) {
            error_log( 'KG Core MetaSyncService: Failed to sync post ' . $post_id . ': ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Extract Recipe data from wp_postmeta
     * 
     * @param int $post_id Recipe post ID
     * @return array Extracted data for custom table
     */
    public static function extractRecipeFromPostMeta( $post_id ) {
        $data = [];
        
        foreach ( self::$recipe_mappings as $meta_key => $field_name ) {
            $value = get_post_meta( $post_id, $meta_key, true );
            
            // Handle serialized data
            if ( is_string( $value ) ) {
                $unserialized = maybe_unserialize( $value );
                if ( $unserialized !== $value ) {
                    $value = $unserialized;
                }
            }
            
            // Apply value mappings for difficulty
            if ( $field_name === 'difficulty' && is_string( $value ) ) {
                $difficulty_map = [
                    'Kolay' => 'kolay',
                    'Orta' => 'orta',
                    'Zor' => 'zor',
                    'kolay' => 'kolay',
                    'orta' => 'orta',
                    'zor' => 'zor',
                ];
                if ( isset( $difficulty_map[ $value ] ) ) {
                    $value = $difficulty_map[ $value ];
                }
            }
            
            // Only include non-empty values
            if ( $value !== null && $value !== '' ) {
                $data[ $field_name ] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Extract Ingredient data from wp_postmeta
     * 
     * @param int $post_id Ingredient post ID
     * @return array Extracted data for custom table
     */
    public static function extractIngredientFromPostMeta( $post_id ) {
        $data = [];
        
        foreach ( self::$ingredient_mappings as $meta_key => $field_name ) {
            $value = get_post_meta( $post_id, $meta_key, true );
            
            // Handle serialized data
            if ( is_string( $value ) ) {
                $unserialized = maybe_unserialize( $value );
                if ( $unserialized !== $value ) {
                    $value = $unserialized;
                }
            }
            
            // Apply value mappings for allergy_risk
            if ( $field_name === 'allergy_risk' && is_string( $value ) ) {
                $allergy_risk_map = [
                    'Düşük' => 'low',
                    'Orta' => 'medium',
                    'Yüksek' => 'high',
                    'low' => 'low',
                    'medium' => 'medium',
                    'high' => 'high',
                ];
                if ( isset( $allergy_risk_map[ $value ] ) ) {
                    $value = $allergy_risk_map[ $value ];
                }
            }
            
            // Only include non-empty values
            if ( $value !== null && $value !== '' ) {
                $data[ $field_name ] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Extract Post data from wp_postmeta
     * 
     * @param int $post_id Post ID
     * @return array Extracted data for custom table
     */
    public static function extractPostFromPostMeta( $post_id ) {
        $data = [];
        
        foreach ( self::$post_mappings as $meta_key => $field_name ) {
            $value = get_post_meta( $post_id, $meta_key, true );
            
            // Handle serialized data
            if ( is_string( $value ) ) {
                $unserialized = maybe_unserialize( $value );
                if ( $unserialized !== $value ) {
                    $value = $unserialized;
                }
            }
            
            // Only include non-empty values
            if ( $value !== null && $value !== '' ) {
                $data[ $field_name ] = $value;
            }
        }
        
        return $data;
    }
}

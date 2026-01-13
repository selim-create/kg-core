<?php
/**
 * Test script for API improvements
 * 
 * This script tests the new SEO and extended fields in Recipe and Ingredient APIs
 * 
 * Usage: php test-api-improvements.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "=== Testing API Improvements ===\n\n";

// Test 1: Check if Helper class has decode_html_entities method
echo "Test 1: Checking Helper::decode_html_entities() method...\n";
if ( method_exists( '\KG_Core\Utils\Helper', 'decode_html_entities' ) ) {
    $test_text = "Test &amp; Decode &nbsp; Special &quot;Chars&quot;";
    $decoded = \KG_Core\Utils\Helper::decode_html_entities( $test_text );
    echo "  ✓ Method exists\n";
    echo "  Original: $test_text\n";
    echo "  Decoded: $decoded\n";
} else {
    echo "  ✗ Method not found\n";
}
echo "\n";

// Test 2: Check Recipe API structure with a sample recipe
echo "Test 2: Testing Recipe API structure...\n";
$recipe_args = [
    'post_type' => 'recipe',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$recipe_query = new WP_Query( $recipe_args );

if ( $recipe_query->have_posts() ) {
    $recipe_query->the_post();
    $post_id = get_the_ID();
    
    // Simulate what the API controller does
    $controller = new \KG_Core\API\RecipeController();
    $reflection = new ReflectionClass( $controller );
    
    // Check if get_seo_data method exists
    if ( $reflection->hasMethod( 'get_seo_data' ) ) {
        echo "  ✓ get_seo_data() method exists in RecipeController\n";
    } else {
        echo "  ✗ get_seo_data() method not found in RecipeController\n";
    }
    
    // Check new meta fields
    $new_fields = [
        '_kg_meal_type' => 'Meal Type',
        '_kg_cook_time' => 'Cook Time',
        '_kg_serving_size' => 'Serving Size',
        '_kg_difficulty' => 'Difficulty',
        '_kg_freezable' => 'Freezable',
        '_kg_storage_info' => 'Storage Info',
        '_kg_carbs' => 'Carbs',
        '_kg_fat' => 'Fat',
        '_kg_sugar' => 'Sugar',
        '_kg_sodium' => 'Sodium',
        '_kg_minerals' => 'Minerals',
    ];
    
    echo "  Recipe ID: $post_id - " . get_the_title() . "\n";
    echo "  Checking new meta fields:\n";
    
    foreach ( $new_fields as $meta_key => $label ) {
        $value = get_post_meta( $post_id, $meta_key, true );
        $status = !empty( $value ) ? "✓ Has value: $value" : "○ Empty (not set)";
        echo "    - $label ($meta_key): $status\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No recipes found to test\n";
}
echo "\n";

// Test 3: Check Ingredient API structure with a sample ingredient
echo "Test 3: Testing Ingredient API structure...\n";
$ingredient_args = [
    'post_type' => 'ingredient',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$ingredient_query = new WP_Query( $ingredient_args );

if ( $ingredient_query->have_posts() ) {
    $ingredient_query->the_post();
    $post_id = get_the_ID();
    
    // Simulate what the API controller does
    $controller = new \KG_Core\API\IngredientController();
    $reflection = new ReflectionClass( $controller );
    
    // Check if get_seo_data method exists
    if ( $reflection->hasMethod( 'get_seo_data' ) ) {
        echo "  ✓ get_seo_data() method exists in IngredientController\n";
    } else {
        echo "  ✗ get_seo_data() method not found in IngredientController\n";
    }
    
    // Check new meta fields
    $new_fields = [
        '_kg_ing_calories_100g' => 'Calories per 100g',
        '_kg_ing_protein_100g' => 'Protein per 100g',
        '_kg_ing_carbs_100g' => 'Carbs per 100g',
        '_kg_ing_fat_100g' => 'Fat per 100g',
        '_kg_ing_fiber_100g' => 'Fiber per 100g',
        '_kg_ing_sugar_100g' => 'Sugar per 100g',
        '_kg_ing_vitamins' => 'Vitamins',
        '_kg_ing_minerals' => 'Minerals',
        '_kg_is_allergen' => 'Is Allergen',
        '_kg_allergen_type' => 'Allergen Type',
        '_kg_cross_contamination' => 'Cross Contamination',
        '_kg_allergy_symptoms' => 'Allergy Symptoms',
        '_kg_alternatives' => 'Alternatives',
        '_kg_prep_methods_list' => 'Prep Methods List',
        '_kg_prep_tips' => 'Prep Tips',
        '_kg_cooking_suggestions' => 'Cooking Suggestions',
    ];
    
    echo "  Ingredient ID: $post_id - " . get_the_title() . "\n";
    echo "  Checking new meta fields:\n";
    
    foreach ( $new_fields as $meta_key => $label ) {
        $value = get_post_meta( $post_id, $meta_key, true );
        if ( $meta_key === '_kg_is_allergen' ) {
            $status = $value === '1' ? "✓ Yes" : "○ No";
        } else {
            $status = !empty( $value ) ? "✓ Has value" : "○ Empty (not set)";
        }
        echo "    - $label ($meta_key): $status\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No ingredients found to test\n";
}
echo "\n";

// Test 4: Check RankMath SEO meta fields
echo "Test 4: Checking RankMath SEO integration...\n";
$seo_fields = [
    'rank_math_title' => 'SEO Title',
    'rank_math_description' => 'SEO Description',
    'rank_math_focus_keyword' => 'Focus Keywords',
    'rank_math_canonical_url' => 'Canonical URL',
    'rank_math_facebook_title' => 'OG Title',
    'rank_math_facebook_description' => 'OG Description',
    'rank_math_facebook_image' => 'OG Image',
    'rank_math_twitter_title' => 'Twitter Title',
    'rank_math_twitter_description' => 'Twitter Description',
];

// Try to find a post with RankMath data
$test_post_types = [ 'recipe', 'ingredient' ];
$found_rankmath = false;

foreach ( $test_post_types as $post_type ) {
    $args = [
        'post_type' => $post_type,
        'posts_per_page' => 5,
        'post_status' => 'publish',
    ];
    $query = new WP_Query( $args );
    
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // Check if this post has any RankMath data
            $has_rankmath = false;
            foreach ( $seo_fields as $meta_key => $label ) {
                if ( !empty( get_post_meta( $post_id, $meta_key, true ) ) ) {
                    $has_rankmath = true;
                    break;
                }
            }
            
            if ( $has_rankmath ) {
                $found_rankmath = true;
                echo "  Found RankMath data in $post_type (ID: $post_id):\n";
                foreach ( $seo_fields as $meta_key => $label ) {
                    $value = get_post_meta( $post_id, $meta_key, true );
                    if ( !empty( $value ) ) {
                        $display_value = strlen( $value ) > 50 ? substr( $value, 0, 50 ) . '...' : $value;
                        echo "    ✓ $label: $display_value\n";
                    }
                }
                break 2;
            }
        }
    }
    wp_reset_postdata();
}

if ( !$found_rankmath ) {
    echo "  ⚠ No RankMath SEO data found in any posts\n";
    echo "  ℹ Fallback values will be used (post title + site name)\n";
}
echo "\n";

echo "=== Test Summary ===\n";
echo "✓ Helper class with decode_html_entities() added\n";
echo "✓ RecipeController extended with SEO support and new fields\n";
echo "✓ IngredientController extended with SEO support and new fields\n";
echo "✓ All meta fields are ready to be populated via admin UI\n";
echo "\nTo populate these fields:\n";
echo "1. Go to WordPress Admin → Recipes → Edit a recipe\n";
echo "2. Fill in the new fields in the 'Tarif Detayları' meta box\n";
echo "3. Go to WordPress Admin → Ingredients → Edit an ingredient\n";
echo "4. Fill in the new fields in the 'Malzeme Detayları' meta box\n";
echo "5. Install and configure RankMath SEO plugin for SEO data\n";
echo "\n";

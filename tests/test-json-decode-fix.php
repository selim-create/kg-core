<?php
/**
 * Test script for json_decode TypeError fix
 * 
 * This script validates that the API controllers properly handle
 * already-deserialized JSON data from the Model layer.
 * 
 * Usage: php test-json-decode-fix.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "=== Testing json_decode TypeError Fix ===\n\n";

$all_passed = true;

// Test 1: Verify BaseModel deserializes JSON fields
echo "Test 1: Verify BaseModel deserializes JSON fields...\n";
$recipe_args = [
    'post_type' => 'recipe',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$recipe_query = new WP_Query( $recipe_args );

if ( $recipe_query->have_posts() ) {
    $recipe_query->the_post();
    $post_id = get_the_ID();
    
    $recipe_meta = \KG_Core\Models\RecipeMeta::get($post_id);
    
    if ($recipe_meta) {
        // Check if ingredients is already an array
        if (isset($recipe_meta['ingredients'])) {
            if (is_array($recipe_meta['ingredients'])) {
                echo "  ✓ RecipeMeta::get() returns ingredients as array\n";
            } else {
                echo "  ✗ RecipeMeta::get() does NOT return ingredients as array (type: " . gettype($recipe_meta['ingredients']) . ")\n";
                $all_passed = false;
            }
        } else {
            echo "  ℹ No ingredients field found in recipe meta\n";
        }
        
        // Check if instructions is already an array
        if (isset($recipe_meta['instructions'])) {
            if (is_array($recipe_meta['instructions'])) {
                echo "  ✓ RecipeMeta::get() returns instructions as array\n";
            } else {
                echo "  ✗ RecipeMeta::get() does NOT return instructions as array (type: " . gettype($recipe_meta['instructions']) . ")\n";
                $all_passed = false;
            }
        } else {
            echo "  ℹ No instructions field found in recipe meta\n";
        }
        
        // Check if substitutes is already an array
        if (isset($recipe_meta['substitutes'])) {
            if (is_array($recipe_meta['substitutes'])) {
                echo "  ✓ RecipeMeta::get() returns substitutes as array\n";
            } else {
                echo "  ✗ RecipeMeta::get() does NOT return substitutes as array (type: " . gettype($recipe_meta['substitutes']) . ")\n";
                $all_passed = false;
            }
        } else {
            echo "  ℹ No substitutes field found in recipe meta\n";
        }
    } else {
        echo "  ℹ No custom table data found for recipe, skipping\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ℹ No published recipes found, skipping\n";
}
echo "\n";

// Test 2: Verify IngredientMeta deserializes JSON fields
echo "Test 2: Verify IngredientMeta deserializes JSON fields...\n";
$ingredient_args = [
    'post_type' => 'ingredient',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$ingredient_query = new WP_Query( $ingredient_args );

if ( $ingredient_query->have_posts() ) {
    $ingredient_query->the_post();
    $post_id = get_the_ID();
    
    $ingredient_meta = \KG_Core\Models\IngredientMeta::get($post_id);
    
    if ($ingredient_meta) {
        // Check if season is already an array
        if (isset($ingredient_meta['season'])) {
            if (is_array($ingredient_meta['season'])) {
                echo "  ✓ IngredientMeta::get() returns season as array\n";
            } else {
                echo "  ✗ IngredientMeta::get() does NOT return season as array (type: " . gettype($ingredient_meta['season']) . ")\n";
                $all_passed = false;
            }
        } else {
            echo "  ℹ No season field found in ingredient meta\n";
        }
    } else {
        echo "  ℹ No custom table data found for ingredient, skipping\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ℹ No published ingredients found, skipping\n";
}
echo "\n";

// Test 3: Verify RecipeController handles array data without json_decode error
echo "Test 3: Verify RecipeController handles array data...\n";
$recipe_args = [
    'post_type' => 'recipe',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$recipe_query = new WP_Query( $recipe_args );

if ( $recipe_query->have_posts() ) {
    $recipe_query->the_post();
    $post_id = get_the_ID();
    
    $controller = new \KG_Core\API\RecipeController();
    $reflection = new ReflectionClass( $controller );
    
    try {
        // Try to access the private prepare_recipe_data method
        $method = $reflection->getMethod( 'prepare_recipe_data' );
        $method->setAccessible( true );
        
        // Call with full_detail = true to test substitutes as well
        $result = $method->invoke( $controller, $post_id, true );
        
        // Check if result contains expected array fields
        if (isset($result['ingredients']) && is_array($result['ingredients'])) {
            echo "  ✓ prepare_recipe_data() returns ingredients as array without TypeError\n";
        } else {
            echo "  ℹ ingredients not found or not an array in result\n";
        }
        
        if (isset($result['instructions']) && is_array($result['instructions'])) {
            echo "  ✓ prepare_recipe_data() returns instructions as array without TypeError\n";
        } else {
            echo "  ℹ instructions not found or not an array in result\n";
        }
        
        if (isset($result['substitutes']) && is_array($result['substitutes'])) {
            echo "  ✓ prepare_recipe_data() returns substitutes as array without TypeError\n";
        } else {
            echo "  ℹ substitutes not found or not an array in result\n";
        }
        
    } catch (TypeError $e) {
        echo "  ✗ TypeError occurred in prepare_recipe_data(): " . $e->getMessage() . "\n";
        $all_passed = false;
    } catch (Exception $e) {
        echo "  ✗ Exception occurred: " . $e->getMessage() . "\n";
        $all_passed = false;
    }
    
    wp_reset_postdata();
} else {
    echo "  ℹ No published recipes found, skipping\n";
}
echo "\n";

// Test 4: Verify IngredientController handles array data without json_decode error
echo "Test 4: Verify IngredientController handles array data...\n";
$ingredient_args = [
    'post_type' => 'ingredient',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$ingredient_query = new WP_Query( $ingredient_args );

if ( $ingredient_query->have_posts() ) {
    $ingredient_query->the_post();
    $post_id = get_the_ID();
    
    $controller = new \KG_Core\API\IngredientController();
    $reflection = new ReflectionClass( $controller );
    
    try {
        // Try to access the private prepare_ingredient_data method
        $method = $reflection->getMethod( 'prepare_ingredient_data' );
        $method->setAccessible( true );
        
        $result = $method->invoke( $controller, $post_id, false );
        
        // Check if result contains expected array field
        if (isset($result['season']) && is_array($result['season'])) {
            echo "  ✓ prepare_ingredient_data() returns season as array without TypeError\n";
        } else {
            echo "  ℹ season not found or not an array in result\n";
        }
        
    } catch (TypeError $e) {
        echo "  ✗ TypeError occurred in prepare_ingredient_data(): " . $e->getMessage() . "\n";
        $all_passed = false;
    } catch (Exception $e) {
        echo "  ✗ Exception occurred: " . $e->getMessage() . "\n";
        $all_passed = false;
    }
    
    wp_reset_postdata();
} else {
    echo "  ℹ No published ingredients found, skipping\n";
}
echo "\n";

// Final summary
echo "=== Test Summary ===\n";
if ($all_passed) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed!\n";
    exit(1);
}

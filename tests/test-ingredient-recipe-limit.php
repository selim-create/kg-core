<?php
/**
 * Test script for Ingredient Recipe Limit Update
 * 
 * This script validates:
 * 1. The get_recipes_by_ingredient() method uses default limit of 6
 * 2. The /kg/v1/ingredients/{slug} endpoint returns up to 6 related recipes
 * 
 * Usage: php tests/test-ingredient-recipe-limit.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "=== Testing Ingredient Recipe Limit Update ===\n\n";

// Test 1: Verify the default limit parameter using reflection
echo "Test 1: Checking get_recipes_by_ingredient() default limit parameter...\n";
$controller = new \KG_Core\API\IngredientController();
$reflection = new ReflectionClass( $controller );

if ( $reflection->hasMethod( 'get_recipes_by_ingredient' ) ) {
    echo "  ✓ get_recipes_by_ingredient() method exists\n";
    
    $method = $reflection->getMethod( 'get_recipes_by_ingredient' );
    $parameters = $method->getParameters();
    
    foreach ( $parameters as $param ) {
        if ( $param->getName() === 'limit' ) {
            if ( $param->isDefaultValueAvailable() ) {
                $default_value = $param->getDefaultValue();
                echo "  Default limit value: $default_value\n";
                
                if ( $default_value === 6 ) {
                    echo "  ✓ Default limit is correctly set to 6\n";
                } else {
                    echo "  ✗ Default limit is $default_value, expected 6\n";
                }
            } else {
                echo "  ✗ No default value found for limit parameter\n";
            }
        }
    }
} else {
    echo "  ✗ get_recipes_by_ingredient() method not found\n";
}
echo "\n";

// Test 2: Test with an actual ingredient to verify related_recipes count
echo "Test 2: Testing related_recipes in ingredient detail endpoint...\n";

// Find an ingredient to test with
$ingredient_args = [
    'post_type' => 'ingredient',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$ingredient_query = new WP_Query( $ingredient_args );

if ( $ingredient_query->have_posts() ) {
    $ingredient_query->the_post();
    $ingredient_id = get_the_ID();
    $ingredient_slug = get_post_field( 'post_name', $ingredient_id );
    
    echo "  Testing with ingredient: " . get_the_title() . " (slug: $ingredient_slug)\n";
    
    // Simulate REST request
    $request = new WP_REST_Request( 'GET', "/kg/v1/ingredients/$ingredient_slug" );
    $response = $controller->get_ingredient_by_slug( $request );
    
    if ( $response instanceof WP_REST_Response ) {
        $data = $response->get_data();
        
        if ( isset( $data['related_recipes'] ) ) {
            $recipe_count = count( $data['related_recipes'] );
            echo "  ✓ related_recipes field exists\n";
            echo "  Number of related recipes returned: $recipe_count\n";
            
            if ( $recipe_count <= 6 ) {
                echo "  ✓ Recipe count ($recipe_count) is within the limit of 6\n";
            } else {
                echo "  ✗ Recipe count ($recipe_count) exceeds the limit of 6\n";
            }
            
            // Display sample recipes
            if ( $recipe_count > 0 ) {
                echo "  Sample recipes:\n";
                foreach ( array_slice( $data['related_recipes'], 0, 6 ) as $recipe ) {
                    echo "    - {$recipe['title']} (ID: {$recipe['id']})\n";
                }
            }
        } else {
            echo "  ○ No related_recipes field found (ingredient may not have related recipes)\n";
        }
    } else {
        echo "  ✗ Response is not a WP_REST_Response\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No ingredients found to test\n";
}
echo "\n";

// Test 3: Test the method directly with reflection to verify it respects the limit
echo "Test 3: Testing get_recipes_by_ingredient() method directly...\n";

$ingredient_query2 = new WP_Query( $ingredient_args );
if ( $ingredient_query2->have_posts() ) {
    $ingredient_query2->the_post();
    $ingredient_id = get_the_ID();
    
    echo "  Testing with ingredient ID: $ingredient_id - " . get_the_title() . "\n";
    
    // Use reflection to call private method
    $method = $reflection->getMethod( 'get_recipes_by_ingredient' );
    $method->setAccessible( true );
    
    // Test with default limit (should be 6)
    $recipes = $method->invoke( $controller, $ingredient_id );
    $recipe_count = count( $recipes );
    
    echo "  Recipes returned with default limit: $recipe_count\n";
    
    if ( $recipe_count <= 6 ) {
        echo "  ✓ Recipe count ($recipe_count) is within expected limit of 6\n";
    } else {
        echo "  ✗ Recipe count ($recipe_count) exceeds expected limit of 6\n";
    }
    
    // Test with custom limit to ensure parameter still works
    $recipes_custom = $method->invoke( $controller, $ingredient_id, 3 );
    $custom_count = count( $recipes_custom );
    
    echo "  Recipes returned with custom limit of 3: $custom_count\n";
    
    if ( $custom_count <= 3 ) {
        echo "  ✓ Custom limit parameter works correctly\n";
    } else {
        echo "  ✗ Custom limit parameter not working as expected\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No ingredients found to test\n";
}
echo "\n";

echo "=== Test Complete ===\n";

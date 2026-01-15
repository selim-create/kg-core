<?php
/**
 * Test script for Ingredient API Updates
 * 
 * This script validates:
 * 1. List API returns allergy_risk and season fields
 * 2. New /kg/v1/ingredient-categories endpoint works
 * 
 * Usage: php tests/test-ingredient-api-updates.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "=== Testing Ingredient API Updates ===\n\n";

// Test 1: Check if IngredientController has get_ingredient_categories method
echo "Test 1: Checking get_ingredient_categories() method exists...\n";
$controller = new \KG_Core\API\IngredientController();
$reflection = new ReflectionClass( $controller );

if ( $reflection->hasMethod( 'get_ingredient_categories' ) ) {
    echo "  ✓ get_ingredient_categories() method exists in IngredientController\n";
} else {
    echo "  ✗ get_ingredient_categories() method not found in IngredientController\n";
}
echo "\n";

// Test 2: Test the get_ingredient_categories method directly
echo "Test 2: Testing get_ingredient_categories() method...\n";
try {
    $request = new WP_REST_Request( 'GET', '/kg/v1/ingredient-categories' );
    $response = $controller->get_ingredient_categories( $request );
    
    if ( $response instanceof WP_REST_Response ) {
        $data = $response->get_data();
        echo "  ✓ Method returns WP_REST_Response\n";
        
        if ( isset( $data['terms'] ) && isset( $data['total'] ) ) {
            echo "  ✓ Response has 'terms' and 'total' keys\n";
            echo "  Total categories: " . $data['total'] . "\n";
            
            if ( $data['total'] > 0 ) {
                echo "  Sample categories:\n";
                foreach ( array_slice( $data['terms'], 0, 3 ) as $category ) {
                    echo "    - {$category['name']} (slug: {$category['slug']}, count: {$category['count']})\n";
                }
            }
        } else {
            echo "  ✗ Response missing 'terms' or 'total' keys\n";
        }
    } else {
        echo "  ✗ Method does not return WP_REST_Response\n";
    }
} catch ( Exception $e ) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Check if prepare_ingredient_data returns allergy_risk and season in list view
echo "Test 3: Testing prepare_ingredient_data() for list view (full_detail=false)...\n";
$ingredient_args = [
    'post_type' => 'ingredient',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$ingredient_query = new WP_Query( $ingredient_args );

if ( $ingredient_query->have_posts() ) {
    $ingredient_query->the_post();
    $post_id = get_the_ID();
    
    // Use reflection to access private method
    $method = $reflection->getMethod( 'prepare_ingredient_data' );
    $method->setAccessible( true );
    
    // Test with full_detail = false (list view)
    $list_data = $method->invoke( $controller, $post_id, false );
    
    echo "  Testing ingredient ID: $post_id - " . get_the_title() . "\n";
    
    // Check required fields in list view
    $required_fields = [
        'id',
        'name',
        'slug',
        'description',
        'image',
        'start_age',
        'category',
        'allergy_risk',
        'season',
    ];
    
    echo "  Checking required fields in list view:\n";
    $all_present = true;
    foreach ( $required_fields as $field ) {
        if ( array_key_exists( $field, $list_data ) ) {
            $value = $list_data[$field];
            if ( is_string( $value ) ) {
                $display_value = strlen( $value ) > 30 ? substr( $value, 0, 30 ) . '...' : $value;
            } else {
                $display_value = var_export( $value, true );
            }
            echo "    ✓ $field: $display_value\n";
        } else {
            echo "    ✗ $field: MISSING\n";
            $all_present = false;
        }
    }
    
    if ( $all_present ) {
        echo "  ✓ All required fields present in list view\n";
    } else {
        echo "  ✗ Some required fields missing in list view\n";
    }
    
    // Specifically check allergy_risk and season
    echo "\n  Specific checks for new fields:\n";
    if ( isset( $list_data['allergy_risk'] ) ) {
        echo "    ✓ allergy_risk is present: " . $list_data['allergy_risk'] . "\n";
    } else {
        echo "    ✗ allergy_risk is MISSING\n";
    }
    
    if ( isset( $list_data['season'] ) ) {
        echo "    ✓ season is present: " . $list_data['season'] . "\n";
    } else {
        echo "    ✗ season is MISSING\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No ingredients found to test\n";
}
echo "\n";

// Test 4: Test full detail view to ensure no regression
echo "Test 4: Testing prepare_ingredient_data() for detail view (full_detail=true)...\n";
$ingredient_query2 = new WP_Query( $ingredient_args );

if ( $ingredient_query2->have_posts() ) {
    $ingredient_query2->the_post();
    $post_id = get_the_ID();
    
    // Use reflection to access private method
    $method = $reflection->getMethod( 'prepare_ingredient_data' );
    $method->setAccessible( true );
    
    // Test with full_detail = true (detail view)
    $detail_data = $method->invoke( $controller, $post_id, true );
    
    echo "  Testing ingredient ID: $post_id - " . get_the_title() . "\n";
    
    // Check that allergy_risk and season are still present in detail view
    echo "  Checking allergy_risk and season in detail view:\n";
    if ( isset( $detail_data['allergy_risk'] ) ) {
        echo "    ✓ allergy_risk is present: " . $detail_data['allergy_risk'] . "\n";
    } else {
        echo "    ✗ allergy_risk is MISSING\n";
    }
    
    if ( isset( $detail_data['season'] ) ) {
        echo "    ✓ season is present: " . $detail_data['season'] . "\n";
    } else {
        echo "    ✗ season is MISSING\n";
    }
    
    // Check some detail-only fields
    $detail_only_fields = ['nutrition', 'allergen_info', 'seo'];
    echo "\n  Checking detail-only fields:\n";
    foreach ( $detail_only_fields as $field ) {
        if ( isset( $detail_data[$field] ) ) {
            echo "    ✓ $field is present\n";
        } else {
            echo "    ○ $field is missing (may not be set)\n";
        }
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No ingredients found to test\n";
}
echo "\n";

echo "=== Test Complete ===\n";

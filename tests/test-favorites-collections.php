<?php
/**
 * Test script for Favorites and Collections API
 * 
 * This script tests the new extended favorites system and collections API
 * 
 * Usage: php test-favorites-collections.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "=== Testing Favorites and Collections API ===\n\n";

// Test utilities
function make_api_request( $endpoint, $method = 'GET', $data = null, $token = null ) {
    $request = new WP_REST_Request( $method, $endpoint );
    
    if ( $token ) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }
    
    if ( $data ) {
        $request->set_body_params( $data );
    }
    
    $response = rest_do_request( $request );
    
    return [
        'status' => $response->get_status(),
        'data' => $response->get_data(),
    ];
}

// Setup: Create test user and get token
echo "Setting up test user...\n";
$test_email = 'test_' . time() . '@example.com';
$test_password = 'TestPass123';

$register_response = make_api_request( '/kg/v1/auth/register', 'POST', [
    'email' => $test_email,
    'password' => $test_password,
    'name' => 'Test User',
]);

if ( $register_response['status'] !== 201 ) {
    echo "✗ Failed to create test user\n";
    print_r( $register_response );
    exit( 1 );
}

$token = $register_response['data']['token'];
$user_id = $register_response['data']['user_id'];
echo "✓ Test user created (ID: $user_id)\n\n";

// Setup: Create test posts
echo "Creating test content...\n";

// Create a test recipe
$recipe_id = wp_insert_post( [
    'post_title' => 'Test Recipe for Favorites',
    'post_type' => 'recipe',
    'post_status' => 'publish',
    'post_content' => 'Test recipe content',
] );

// Create a test ingredient
$ingredient_id = wp_insert_post( [
    'post_title' => 'Test Ingredient',
    'post_type' => 'ingredient',
    'post_status' => 'publish',
] );

// Create a test post (blog post)
$post_id = wp_insert_post( [
    'post_title' => 'Test Blog Post',
    'post_type' => 'post',
    'post_status' => 'publish',
] );

// Create a test discussion
$discussion_id = wp_insert_post( [
    'post_title' => 'Test Discussion',
    'post_type' => 'discussion',
    'post_status' => 'publish',
] );

echo "✓ Created test recipe (ID: $recipe_id)\n";
echo "✓ Created test ingredient (ID: $ingredient_id)\n";
echo "✓ Created test post (ID: $post_id)\n";
echo "✓ Created test discussion (ID: $discussion_id)\n\n";

// ==================== FAVORITES TESTS ====================
echo "=== Testing Extended Favorites API ===\n\n";

// Test 1: Add recipe to favorites
echo "Test 1: Add recipe to favorites\n";
$response = make_api_request( '/kg/v1/user/favorites', 'POST', [
    'item_id' => $recipe_id,
    'item_type' => 'recipe',
], $token );

if ( $response['status'] === 201 && $response['data']['success'] === true ) {
    echo "  ✓ Recipe added to favorites\n";
} else {
    echo "  ✗ Failed to add recipe to favorites\n";
    print_r( $response );
}

// Test 2: Add ingredient to favorites
echo "Test 2: Add ingredient to favorites\n";
$response = make_api_request( '/kg/v1/user/favorites', 'POST', [
    'item_id' => $ingredient_id,
    'item_type' => 'ingredient',
], $token );

if ( $response['status'] === 201 && $response['data']['success'] === true ) {
    echo "  ✓ Ingredient added to favorites\n";
} else {
    echo "  ✗ Failed to add ingredient to favorites\n";
    print_r( $response );
}

// Test 3: Add post to favorites
echo "Test 3: Add post to favorites\n";
$response = make_api_request( '/kg/v1/user/favorites', 'POST', [
    'item_id' => $post_id,
    'item_type' => 'post',
], $token );

if ( $response['status'] === 201 && $response['data']['success'] === true ) {
    echo "  ✓ Post added to favorites\n";
} else {
    echo "  ✗ Failed to add post to favorites\n";
    print_r( $response );
}

// Test 4: Add discussion to favorites
echo "Test 4: Add discussion to favorites\n";
$response = make_api_request( '/kg/v1/user/favorites', 'POST', [
    'item_id' => $discussion_id,
    'item_type' => 'discussion',
], $token );

if ( $response['status'] === 201 && $response['data']['success'] === true ) {
    echo "  ✓ Discussion added to favorites\n";
} else {
    echo "  ✗ Failed to add discussion to favorites\n";
    print_r( $response );
}

// Test 5: Get all favorites
echo "Test 5: Get all favorites (type=all)\n";
$response = make_api_request( '/kg/v1/user/favorites?type=all', 'GET', null, $token );

if ( $response['status'] === 200 ) {
    $data = $response['data'];
    echo "  ✓ Retrieved all favorites\n";
    echo "  - Recipes: " . $data['counts']['recipes'] . "\n";
    echo "  - Ingredients: " . $data['counts']['ingredients'] . "\n";
    echo "  - Posts: " . $data['counts']['posts'] . "\n";
    echo "  - Discussions: " . $data['counts']['discussions'] . "\n";
    echo "  - Total: " . $data['counts']['all'] . "\n";
    
    if ( $data['counts']['all'] !== 4 ) {
        echo "  ✗ Expected 4 total favorites, got " . $data['counts']['all'] . "\n";
    }
} else {
    echo "  ✗ Failed to get all favorites\n";
    print_r( $response );
}

// Test 6: Get favorites filtered by type
echo "Test 6: Get favorites filtered by type (type=recipe)\n";
$response = make_api_request( '/kg/v1/user/favorites?type=recipe', 'GET', null, $token );

if ( $response['status'] === 200 ) {
    $data = $response['data'];
    if ( isset( $data['recipes'] ) && count( $data['recipes'] ) === 1 ) {
        echo "  ✓ Retrieved recipe favorites correctly\n";
        echo "  - Recipe title: " . $data['recipes'][0]['title'] . "\n";
    } else {
        echo "  ✗ Recipe favorites count mismatch\n";
    }
} else {
    echo "  ✗ Failed to get filtered favorites\n";
    print_r( $response );
}

// Test 7: Remove item from favorites
echo "Test 7: Remove recipe from favorites\n";
$response = make_api_request( "/kg/v1/user/favorites/$recipe_id?type=recipe", 'DELETE', null, $token );

if ( $response['status'] === 200 && $response['data']['success'] === true ) {
    echo "  ✓ Recipe removed from favorites\n";
    
    // Verify it's removed
    $verify = make_api_request( '/kg/v1/user/favorites?type=recipe', 'GET', null, $token );
    if ( count( $verify['data']['recipes'] ) === 0 ) {
        echo "  ✓ Verified recipe is removed\n";
    }
} else {
    echo "  ✗ Failed to remove recipe from favorites\n";
    print_r( $response );
}

// Test 8: Invalid item type
echo "Test 8: Test invalid item type\n";
$response = make_api_request( '/kg/v1/user/favorites', 'POST', [
    'item_id' => 999,
    'item_type' => 'invalid_type',
], $token );

if ( $response['status'] === 400 ) {
    echo "  ✓ Correctly rejected invalid item type\n";
} else {
    echo "  ✗ Should have rejected invalid item type\n";
}

// Test 9: Non-existent item
echo "Test 9: Test non-existent item\n";
$response = make_api_request( '/kg/v1/user/favorites', 'POST', [
    'item_id' => 999999,
    'item_type' => 'recipe',
], $token );

if ( $response['status'] === 404 ) {
    echo "  ✓ Correctly rejected non-existent item\n";
} else {
    echo "  ✗ Should have rejected non-existent item\n";
}

echo "\n";

// ==================== COLLECTIONS TESTS ====================
echo "=== Testing Collections API ===\n\n";

// Test 10: Create a collection
echo "Test 10: Create a new collection\n";
$response = make_api_request( '/kg/v1/user/collections', 'POST', [
    'name' => 'My Breakfast Collection',
    'icon' => 'mug-hot',
    'color' => 'orange',
], $token );

if ( $response['status'] === 201 ) {
    $collection_id = $response['data']['id'];
    echo "  ✓ Collection created (ID: $collection_id)\n";
    echo "  - Name: " . $response['data']['name'] . "\n";
    echo "  - Icon: " . $response['data']['icon'] . "\n";
    echo "  - Color: " . $response['data']['color'] . "\n";
} else {
    echo "  ✗ Failed to create collection\n";
    print_r( $response );
    exit( 1 );
}

// Test 11: Get all collections
echo "Test 11: Get all collections\n";
$response = make_api_request( '/kg/v1/user/collections', 'GET', null, $token );

if ( $response['status'] === 200 && count( $response['data'] ) === 1 ) {
    echo "  ✓ Retrieved collections list\n";
    echo "  - Count: " . count( $response['data'] ) . "\n";
} else {
    echo "  ✗ Failed to get collections\n";
    print_r( $response );
}

// Test 12: Get single collection
echo "Test 12: Get single collection details\n";
$response = make_api_request( "/kg/v1/user/collections/$collection_id", 'GET', null, $token );

if ( $response['status'] === 200 ) {
    echo "  ✓ Retrieved collection details\n";
    echo "  - Item count: " . $response['data']['item_count'] . "\n";
} else {
    echo "  ✗ Failed to get collection details\n";
    print_r( $response );
}

// Test 13: Update collection
echo "Test 13: Update collection\n";
$response = make_api_request( "/kg/v1/user/collections/$collection_id", 'PUT', [
    'name' => 'Updated Breakfast Collection',
    'icon' => 'sun',
    'color' => 'yellow',
], $token );

if ( $response['status'] === 200 ) {
    echo "  ✓ Collection updated\n";
    echo "  - New name: " . $response['data']['name'] . "\n";
    echo "  - New icon: " . $response['data']['icon'] . "\n";
} else {
    echo "  ✗ Failed to update collection\n";
    print_r( $response );
}

// Test 14: Add item to collection (using ingredient that's still in favorites)
echo "Test 14: Add item to collection\n";
$response = make_api_request( "/kg/v1/user/collections/$collection_id/items", 'POST', [
    'item_id' => $ingredient_id,
    'item_type' => 'ingredient',
], $token );

if ( $response['status'] === 201 && $response['data']['success'] === true ) {
    echo "  ✓ Item added to collection\n";
    echo "  - Item count: " . $response['data']['item_count'] . "\n";
} else {
    echo "  ✗ Failed to add item to collection\n";
    print_r( $response );
}

// Test 15: Add another item to collection
echo "Test 15: Add another item to collection\n";
$response = make_api_request( "/kg/v1/user/collections/$collection_id/items", 'POST', [
    'item_id' => $post_id,
    'item_type' => 'post',
], $token );

if ( $response['status'] === 201 ) {
    echo "  ✓ Second item added to collection\n";
    echo "  - Item count: " . $response['data']['item_count'] . "\n";
} else {
    echo "  ✗ Failed to add second item\n";
    print_r( $response );
}

// Test 16: Try adding duplicate item
echo "Test 16: Try adding duplicate item\n";
$response = make_api_request( "/kg/v1/user/collections/$collection_id/items", 'POST', [
    'item_id' => $ingredient_id,
    'item_type' => 'ingredient',
], $token );

if ( $response['status'] === 409 ) {
    echo "  ✓ Correctly rejected duplicate item\n";
} else {
    echo "  ✗ Should have rejected duplicate item\n";
    print_r( $response );
}

// Test 17: Get collection with items
echo "Test 17: Get collection with full item details\n";
$response = make_api_request( "/kg/v1/user/collections/$collection_id", 'GET', null, $token );

if ( $response['status'] === 200 ) {
    $data = $response['data'];
    echo "  ✓ Retrieved collection with items\n";
    echo "  - Item count: " . $data['item_count'] . "\n";
    if ( isset( $data['items'] ) && count( $data['items'] ) === 2 ) {
        echo "  ✓ Correct number of items\n";
        foreach ( $data['items'] as $item ) {
            echo "  - Item: " . $item['item_type'] . " (ID: " . $item['item_id'] . ")\n";
        }
    }
} else {
    echo "  ✗ Failed to get collection with items\n";
    print_r( $response );
}

// Test 18: Remove item from collection
echo "Test 18: Remove item from collection\n";
$response = make_api_request( "/kg/v1/user/collections/$collection_id/items/$ingredient_id?type=ingredient", 'DELETE', null, $token );

if ( $response['status'] === 200 && $response['data']['success'] === true ) {
    echo "  ✓ Item removed from collection\n";
    echo "  - Item count: " . $response['data']['item_count'] . "\n";
} else {
    echo "  ✗ Failed to remove item from collection\n";
    print_r( $response );
}

// Test 19: Test collection validation (invalid icon)
echo "Test 19: Test invalid icon validation\n";
$response = make_api_request( '/kg/v1/user/collections', 'POST', [
    'name' => 'Invalid Collection',
    'icon' => 'invalid-icon',
    'color' => 'blue',
], $token );

if ( $response['status'] === 400 ) {
    echo "  ✓ Correctly rejected invalid icon\n";
} else {
    echo "  ✗ Should have rejected invalid icon\n";
}

// Test 20: Test collection validation (invalid color)
echo "Test 20: Test invalid color validation\n";
$response = make_api_request( '/kg/v1/user/collections', 'POST', [
    'name' => 'Invalid Collection',
    'icon' => 'star',
    'color' => 'invalid-color',
], $token );

if ( $response['status'] === 400 ) {
    echo "  ✓ Correctly rejected invalid color\n";
} else {
    echo "  ✗ Should have rejected invalid color\n";
}

// Test 21: Delete collection
echo "Test 21: Delete collection\n";
$response = make_api_request( "/kg/v1/user/collections/$collection_id", 'DELETE', null, $token );

if ( $response['status'] === 200 && $response['data']['success'] === true ) {
    echo "  ✓ Collection deleted successfully\n";
    
    // Verify it's deleted
    $verify = make_api_request( '/kg/v1/user/collections', 'GET', null, $token );
    if ( count( $verify['data'] ) === 0 ) {
        echo "  ✓ Verified collection is deleted\n";
    }
} else {
    echo "  ✗ Failed to delete collection\n";
    print_r( $response );
}

echo "\n";

// ==================== MIGRATION TEST ====================
echo "=== Testing Legacy Favorites Migration ===\n\n";

// Create a new test user with legacy favorites
$legacy_email = 'legacy_' . time() . '@example.com';
$register_response = make_api_request( '/kg/v1/auth/register', 'POST', [
    'email' => $legacy_email,
    'password' => $test_password,
    'name' => 'Legacy User',
]);

$legacy_user_id = $register_response['data']['user_id'];
$legacy_token = $register_response['data']['token'];

// Manually set legacy favorites
update_user_meta( $legacy_user_id, '_kg_favorites', [ $recipe_id ] );

echo "Test 22: Migration of legacy favorites\n";
echo "  - Set legacy _kg_favorites with recipe ID: $recipe_id\n";

// Call the favorites endpoint to trigger migration
$response = make_api_request( '/kg/v1/user/favorites', 'GET', null, $legacy_token );

if ( $response['status'] === 200 ) {
    // Check if migrated
    $migrated = get_user_meta( $legacy_user_id, '_kg_favorites_migrated', true );
    $new_favorites = get_user_meta( $legacy_user_id, '_kg_favorite_recipes', true );
    
    if ( $migrated === '1' && is_array( $new_favorites ) && in_array( $recipe_id, $new_favorites ) ) {
        echo "  ✓ Legacy favorites migrated successfully\n";
        echo "  ✓ Migration flag set correctly\n";
        
        // Verify legacy data is preserved
        $legacy_favorites = get_user_meta( $legacy_user_id, '_kg_favorites', true );
        if ( is_array( $legacy_favorites ) && in_array( $recipe_id, $legacy_favorites ) ) {
            echo "  ✓ Legacy data preserved for backup\n";
        }
    } else {
        echo "  ✗ Migration did not complete correctly\n";
    }
} else {
    echo "  ✗ Failed to trigger migration\n";
    print_r( $response );
}

echo "\n";

// ==================== CLEANUP ====================
echo "=== Cleanup ===\n";
wp_delete_post( $recipe_id, true );
wp_delete_post( $ingredient_id, true );
wp_delete_post( $post_id, true );
wp_delete_post( $discussion_id, true );
wp_delete_user( $user_id );
wp_delete_user( $legacy_user_id );
echo "✓ Test data cleaned up\n\n";

echo "=== All Tests Completed ===\n";

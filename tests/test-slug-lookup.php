<?php
/**
 * Test script for Slug Lookup Endpoint, Frontend View Links, and Redirect
 * 
 * This script tests the new slug lookup endpoint and related functionality
 * 
 * Usage: php test-slug-lookup.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "=== Testing Slug Lookup & Frontend Features ===\n\n";

// Test 1: Check if LookupController class exists
echo "Test 1: Checking LookupController class...\n";
if ( class_exists( '\KG_Core\API\LookupController' ) ) {
    echo "  ✓ LookupController class exists\n";
    
    // Verify the controller is instantiated
    $controller = new \KG_Core\API\LookupController();
    echo "  ✓ LookupController instantiated successfully\n";
} else {
    echo "  ✗ LookupController class not found\n";
}
echo "\n";

// Test 2: Check if FrontendViewLinks class exists
echo "Test 2: Checking FrontendViewLinks class...\n";
if ( class_exists( '\KG_Core\Admin\FrontendViewLinks' ) ) {
    echo "  ✓ FrontendViewLinks class exists\n";
    
    // Verify the controller is instantiated
    $admin_links = new \KG_Core\Admin\FrontendViewLinks();
    echo "  ✓ FrontendViewLinks instantiated successfully\n";
} else {
    echo "  ✗ FrontendViewLinks class not found\n";
}
echo "\n";

// Test 3: Check if FrontendRedirect class exists
echo "Test 3: Checking FrontendRedirect class...\n";
if ( class_exists( '\KG_Core\Redirect\FrontendRedirect' ) ) {
    echo "  ✓ FrontendRedirect class exists\n";
} else {
    echo "  ✗ FrontendRedirect class not found\n";
}
echo "\n";

// Test 4: Test Lookup API endpoint with a real recipe
echo "Test 4: Testing Lookup API with a published recipe...\n";
$recipe_args = [
    'post_type' => 'recipe',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$recipe_query = new WP_Query( $recipe_args );

if ( $recipe_query->have_posts() ) {
    $recipe_query->the_post();
    $recipe_slug = get_post_field( 'post_name', get_the_ID() );
    $recipe_id = get_the_ID();
    
    echo "  Using recipe: '$recipe_slug' (ID: $recipe_id)\n";
    
    // Create a mock request
    $request = new WP_REST_Request( 'GET', '/kg/v1/lookup' );
    $request->set_query_params( [ 'slug' => $recipe_slug ] );
    
    // Call the lookup method
    $controller = new \KG_Core\API\LookupController();
    $response = $controller->lookup_slug( $request );
    
    if ( $response instanceof WP_REST_Response ) {
        $data = $response->get_data();
        
        echo "  Response data:\n";
        echo "    - found: " . ( $data['found'] ? 'true' : 'false' ) . "\n";
        echo "    - type: " . ( $data['type'] ?? 'null' ) . "\n";
        echo "    - slug: " . ( $data['slug'] ?? 'null' ) . "\n";
        echo "    - id: " . ( $data['id'] ?? 'null' ) . "\n";
        echo "    - redirect: " . ( $data['redirect'] ?? 'null' ) . "\n";
        
        if ( $data['found'] && $data['type'] === 'recipe' && $data['id'] == $recipe_id ) {
            echo "  ✓ Lookup returned correct recipe data\n";
        } else {
            echo "  ✗ Lookup did not return expected recipe data\n";
        }
    } else {
        echo "  ✗ Response is not a WP_REST_Response object\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No published recipes found, skipping test\n";
}
echo "\n";

// Test 5: Test Lookup API with a post
echo "Test 5: Testing Lookup API with a published post...\n";
$post_args = [
    'post_type' => 'post',
    'posts_per_page' => 1,
    'post_status' => 'publish',
];
$post_query = new WP_Query( $post_args );

if ( $post_query->have_posts() ) {
    $post_query->the_post();
    $post_slug = get_post_field( 'post_name', get_the_ID() );
    $post_id = get_the_ID();
    
    echo "  Using post: '$post_slug' (ID: $post_id)\n";
    
    // Create a mock request
    $request = new WP_REST_Request( 'GET', '/kg/v1/lookup' );
    $request->set_query_params( [ 'slug' => $post_slug ] );
    
    // Call the lookup method
    $controller = new \KG_Core\API\LookupController();
    $response = $controller->lookup_slug( $request );
    
    if ( $response instanceof WP_REST_Response ) {
        $data = $response->get_data();
        
        echo "  Response data:\n";
        echo "    - found: " . ( $data['found'] ? 'true' : 'false' ) . "\n";
        echo "    - type: " . ( $data['type'] ?? 'null' ) . "\n";
        echo "    - redirect: " . ( $data['redirect'] ?? 'null' ) . "\n";
        
        if ( $data['found'] && $data['type'] === 'post' && $data['id'] == $post_id ) {
            echo "  ✓ Lookup returned correct post data\n";
        } else {
            echo "  ✗ Lookup did not return expected post data\n";
        }
    } else {
        echo "  ✗ Response is not a WP_REST_Response object\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No published posts found, skipping test\n";
}
echo "\n";

// Test 6: Test Lookup API with a non-existent slug
echo "Test 6: Testing Lookup API with non-existent slug...\n";
$request = new WP_REST_Request( 'GET', '/kg/v1/lookup' );
$request->set_query_params( [ 'slug' => 'non-existent-slug-12345' ] );

$controller = new \KG_Core\API\LookupController();
$response = $controller->lookup_slug( $request );

if ( $response instanceof WP_REST_Response ) {
    $data = $response->get_data();
    
    if ( !$data['found'] && $data['type'] === null && $data['redirect'] === null ) {
        echo "  ✓ Lookup correctly returns 'not found' for non-existent slug\n";
    } else {
        echo "  ✗ Lookup did not return expected 'not found' response\n";
    }
} else {
    echo "  ✗ Response is not a WP_REST_Response object\n";
}
echo "\n";

// Test 7: Test FrontendViewLinks get_frontend_url method
echo "Test 7: Testing FrontendViewLinks get_frontend_url method...\n";
$frontend_links = new \KG_Core\Admin\FrontendViewLinks();

// Get a published recipe
$recipe_query = new WP_Query( [
    'post_type' => 'recipe',
    'posts_per_page' => 1,
    'post_status' => 'publish',
] );

if ( $recipe_query->have_posts() ) {
    $recipe_query->the_post();
    $post = get_post( get_the_ID() );
    
    $frontend_url = $frontend_links->get_frontend_url( $post );
    
    if ( $frontend_url && strpos( $frontend_url, '/tarifler/' ) !== false ) {
        echo "  ✓ get_frontend_url returned correct URL: $frontend_url\n";
    } else {
        echo "  ✗ get_frontend_url did not return expected URL\n";
        echo "    Got: $frontend_url\n";
    }
    
    wp_reset_postdata();
} else {
    echo "  ⚠ No published recipes found, skipping test\n";
}
echo "\n";

// Test 8: Check if REST API route is registered
echo "Test 8: Checking if /kg/v1/lookup route is registered...\n";
$routes = rest_get_server()->get_routes();

if ( isset( $routes['/kg/v1/lookup'] ) ) {
    echo "  ✓ /kg/v1/lookup route is registered\n";
    
    // Check if it accepts GET requests
    $route = $routes['/kg/v1/lookup'];
    if ( !empty( $route ) ) {
        $methods = array_keys( $route[0]['methods'] );
        if ( in_array( 'GET', $methods ) ) {
            echo "  ✓ Route accepts GET requests\n";
        } else {
            echo "  ✗ Route does not accept GET requests\n";
        }
    }
} else {
    echo "  ✗ /kg/v1/lookup route is not registered\n";
}
echo "\n";

echo "=== All Tests Completed ===\n";

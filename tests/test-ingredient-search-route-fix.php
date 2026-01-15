<?php
/**
 * Test script for Ingredient Search Route Priority Fix
 * 
 * This script validates that the ingredient API endpoints work correctly:
 * 1. GET /kg/v1/ingredients/search?q=xxx - Search ingredients
 * 2. GET /kg/v1/ingredients/{slug} - Get single ingredient by slug
 * 3. GET /kg/v1/ingredients - Get all ingredients
 * 
 * Usage: php test-ingredient-search-route-fix.php
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

echo "=== Testing Ingredient Search Route Priority Fix ===\n\n";

// Helper function to make REST API request
function test_rest_endpoint( $route, $args = [] ) {
    $request = new WP_REST_Request( 'GET', $route );
    foreach ( $args as $key => $value ) {
        $request->set_param( $key, $value );
    }
    
    $response = rest_do_request( $request );
    $server = rest_get_server();
    $data = $server->response_to_data( $response, false );
    
    return [
        'status' => $response->get_status(),
        'data' => $data,
        'response' => $response
    ];
}

// Test 1: Search endpoint with query parameter
echo "Test 1: GET /kg/v1/ingredients/search?q=havuç\n";
echo "-----------------------------------------------\n";
$result1 = test_rest_endpoint( '/kg/v1/ingredients/search', [ 'q' => 'havuç' ] );
echo "Status: " . $result1['status'] . "\n";
if ( $result1['status'] === 200 ) {
    if ( is_array( $result1['data'] ) ) {
        echo "✓ SUCCESS: Search endpoint returned results\n";
        echo "  Results count: " . count( $result1['data'] ) . "\n";
        if ( count( $result1['data'] ) > 0 && isset( $result1['data'][0]['name'] ) ) {
            echo "  First result: " . $result1['data'][0]['name'] . "\n";
        }
    } else {
        echo "✓ SUCCESS: Search endpoint responded (no matching ingredients)\n";
    }
} else {
    echo "✗ FAILED: Expected 200, got " . $result1['status'] . "\n";
    if ( isset( $result1['data']['code'] ) ) {
        echo "  Error: " . $result1['data']['code'] . " - " . $result1['data']['message'] . "\n";
    }
}
echo "\n";

// Test 2: Search endpoint without query parameter (should fail with 400)
echo "Test 2: GET /kg/v1/ingredients/search (without query parameter)\n";
echo "---------------------------------------------------------------\n";
$result2 = test_rest_endpoint( '/kg/v1/ingredients/search' );
echo "Status: " . $result2['status'] . "\n";
if ( $result2['status'] === 400 ) {
    echo "✓ SUCCESS: Search endpoint correctly requires 'q' parameter\n";
    if ( isset( $result2['data']['code'] ) ) {
        echo "  Error message: " . $result2['data']['message'] . "\n";
    }
} else {
    echo "✗ FAILED: Expected 400 (Bad Request), got " . $result2['status'] . "\n";
}
echo "\n";

// Test 3: Get all ingredients
echo "Test 3: GET /kg/v1/ingredients\n";
echo "------------------------------\n";
$result3 = test_rest_endpoint( '/kg/v1/ingredients' );
echo "Status: " . $result3['status'] . "\n";
if ( $result3['status'] === 200 ) {
    echo "✓ SUCCESS: Get all ingredients endpoint works\n";
    if ( isset( $result3['data']['ingredients'] ) ) {
        echo "  Total ingredients: " . $result3['data']['total'] . "\n";
        echo "  Returned ingredients: " . count( $result3['data']['ingredients'] ) . "\n";
    }
} else {
    echo "✗ FAILED: Expected 200, got " . $result3['status'] . "\n";
}
echo "\n";

// Test 4: Get ingredient by slug (need to find a valid slug first)
echo "Test 4: GET /kg/v1/ingredients/{slug}\n";
echo "--------------------------------------\n";
// Get a valid ingredient slug from the list
$list_result = test_rest_endpoint( '/kg/v1/ingredients', [ 'per_page' => 1 ] );
if ( $list_result['status'] === 200 && isset( $list_result['data']['ingredients'][0]['slug'] ) ) {
    $test_slug = $list_result['data']['ingredients'][0]['slug'];
    $test_name = $list_result['data']['ingredients'][0]['name'];
    echo "Testing with slug: $test_slug ($test_name)\n";
    
    $result4 = test_rest_endpoint( '/kg/v1/ingredients/' . $test_slug );
    echo "Status: " . $result4['status'] . "\n";
    if ( $result4['status'] === 200 ) {
        echo "✓ SUCCESS: Get ingredient by slug works\n";
        if ( isset( $result4['data']['name'] ) ) {
            echo "  Ingredient: " . $result4['data']['name'] . "\n";
            echo "  Has full details: " . ( isset( $result4['data']['benefits'] ) ? 'Yes' : 'No' ) . "\n";
        }
    } else {
        echo "✗ FAILED: Expected 200, got " . $result4['status'] . "\n";
        if ( isset( $result4['data']['code'] ) ) {
            echo "  Error: " . $result4['data']['code'] . " - " . $result4['data']['message'] . "\n";
        }
    }
} else {
    echo "⚠ SKIPPED: No ingredients found to test slug endpoint\n";
}
echo "\n";

// Test 5: Verify that 'search' keyword doesn't match slug route
echo "Test 5: Verify 'search' is not treated as a slug\n";
echo "------------------------------------------------\n";
echo "This is implicitly tested by Test 1 and Test 2.\n";
echo "If Test 1 succeeds and Test 2 validates the required parameter,\n";
echo "it means 'search' is correctly routed to the search endpoint.\n";
echo "\n";

// Summary
echo "=== Test Summary ===\n";
$total_tests = 4;
$passed_tests = 0;

if ( $result1['status'] === 200 ) $passed_tests++;
if ( $result2['status'] === 400 ) $passed_tests++;
if ( $result3['status'] === 200 ) $passed_tests++;
if ( isset( $result4 ) && $result4['status'] === 200 ) {
    $passed_tests++;
} else if ( !isset( $result4 ) ) {
    $total_tests = 3; // Reduce total if we couldn't test slug endpoint
}

echo "Passed: $passed_tests / $total_tests\n";
if ( $passed_tests === $total_tests ) {
    echo "✓ ALL TESTS PASSED!\n";
} else {
    echo "✗ Some tests failed. Please review the output above.\n";
}

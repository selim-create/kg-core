<?php
/**
 * Test script for additional Featured Content API fixes
 * Tests for ingredient URL and discussion meta key fixes
 */

echo "Testing Additional Featured Content Fixes\n";
echo "==========================================\n\n";

// Test 1: Verify ingredients have URL field
echo "Test 1: Checking ingredients have 'url' field...\n";
$featured_controller_path = __DIR__ . '/includes/API/FeaturedController.php';
$controller_content = file_get_contents( $featured_controller_path );

// Find the get_featured_ingredients method
$ingredients_start = strpos( $controller_content, 'private function get_featured_ingredients' );
if ( $ingredients_start === false ) {
    echo "❌ FAIL: get_featured_ingredients() method not found\n";
    exit(1);
}

// Extract the method content (approximately 2000 chars should cover the full method)
$ingredients_content = substr( $controller_content, $ingredients_start, 2000 );

// Check for URL field in the ingredients array
if ( strpos( $ingredients_content, "'url' => '/malzeme-rehberi/'" ) === false ) {
    echo "❌ FAIL: 'url' field not found in ingredients response\n";
    exit(1);
}

// Verify it's using the post slug (check for the concatenation pattern)
if ( strpos( $ingredients_content, "'/malzeme-rehberi/' ." ) === false && 
     strpos( $ingredients_content, "'/malzeme-rehberi/'." ) === false ) {
    echo "❌ FAIL: URL doesn't use post slug correctly\n";
    exit(1);
}

echo "✅ PASS: Ingredients include proper URL field with /malzeme-rehberi/{slug}\n\n";

// Test 2: Verify DiscussionAdmin uses _kg_is_featured meta key
echo "Test 2: Checking DiscussionAdmin uses correct meta key...\n";
$discussion_admin_path = __DIR__ . '/includes/Admin/DiscussionAdmin.php';

if ( !file_exists( $discussion_admin_path ) ) {
    echo "❌ FAIL: DiscussionAdmin.php not found\n";
    exit(1);
}

$discussion_content = file_get_contents( $discussion_admin_path );

// Check that old meta key is NOT used
if ( strpos( $discussion_content, "_is_featured_question" ) !== false ) {
    echo "❌ FAIL: Old meta key '_is_featured_question' still present in code\n";
    exit(1);
}

// Check for correct meta key in render_custom_columns
$columns_start = strpos( $discussion_content, 'public function render_custom_columns' );
if ( $columns_start === false ) {
    echo "❌ FAIL: render_custom_columns() method not found\n";
    exit(1);
}

$columns_content = substr( $discussion_content, $columns_start, 1500 );

if ( strpos( $columns_content, "'_kg_is_featured'" ) === false ) {
    echo "❌ FAIL: render_custom_columns() doesn't use '_kg_is_featured' meta key\n";
    exit(1);
}

echo "✅ PASS: DiscussionAdmin render_custom_columns() uses '_kg_is_featured'\n\n";

// Test 3: Verify ajax_feature_discussion uses correct meta key
echo "Test 3: Checking ajax_feature_discussion uses correct meta key...\n";

$ajax_start = strpos( $discussion_content, 'public function ajax_feature_discussion' );
if ( $ajax_start === false ) {
    echo "❌ FAIL: ajax_feature_discussion() method not found\n";
    exit(1);
}

$ajax_content = substr( $discussion_content, $ajax_start, 1000 );

if ( strpos( $ajax_content, "'_kg_is_featured'" ) === false ) {
    echo "❌ FAIL: ajax_feature_discussion() doesn't use '_kg_is_featured' meta key\n";
    exit(1);
}

echo "✅ PASS: DiscussionAdmin ajax_feature_discussion() uses '_kg_is_featured'\n\n";

// All tests passed
echo "==========================================\n";
echo "✅ ALL NEW TESTS PASSED!\n";
echo "==========================================\n\n";

echo "Summary of additional fixes:\n";
echo "1. ✅ Ingredients now include 'url' field with proper path\n";
echo "2. ✅ DiscussionAdmin uses '_kg_is_featured' instead of '_is_featured_question'\n";
echo "3. ✅ Discussion feature toggle works with correct meta key\n";

exit(0);

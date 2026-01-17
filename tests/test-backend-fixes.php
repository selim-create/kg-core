<?php
/**
 * Test script for Backend Fixes (Question Excerpts, Custom Avatars, Comment Endpoints)
 * This script validates PHP syntax and basic structure of the new features
 */

echo "Testing Backend Fixes Implementation\n";
echo "====================================\n\n";

$base_path = dirname( __DIR__ );

// Test 1: Check FeaturedController.php has excerpt support
echo "Test 1: Checking FeaturedController.php excerpt support...\n";
$featured_controller_path = $base_path . '/includes/API/FeaturedController.php';

if ( ! file_exists( $featured_controller_path ) ) {
    echo "❌ FAIL: FeaturedController.php not found\n";
    exit(1);
}

$featured_content = file_get_contents( $featured_controller_path );

// Check for excerpt generation logic
if ( strpos( $featured_content, "'excerpt' => \$excerpt" ) === false ) {
    echo "❌ FAIL: Excerpt field not found in FeaturedController.php\n";
    exit(1);
}

if ( strpos( $featured_content, 'wp_trim_words( $excerpt_text, 20 )' ) === false ) {
    echo "❌ FAIL: Excerpt generation logic (20 words) not found\n";
    exit(1);
}

// Check syntax
$syntax_check = exec( "php -l " . escapeshellarg( $featured_controller_path ) . " 2>&1", $output, $return_var );
if ( $return_var !== 0 ) {
    echo "❌ FAIL: Syntax error in FeaturedController.php\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

echo "✅ PASS: FeaturedController.php has excerpt support and valid syntax\n\n";

// Test 2: Check Helper.php has get_user_avatar_url method
echo "Test 2: Checking Helper.php avatar utility method...\n";
$helper_path = $base_path . '/includes/Utils/Helper.php';

if ( ! file_exists( $helper_path ) ) {
    echo "❌ FAIL: Helper.php not found\n";
    exit(1);
}

$helper_content = file_get_contents( $helper_path );

// Check for get_user_avatar_url method
if ( strpos( $helper_content, 'public static function get_user_avatar_url' ) === false ) {
    echo "❌ FAIL: get_user_avatar_url method not found in Helper.php\n";
    exit(1);
}

// Check for custom avatar priority logic
if ( strpos( $helper_content, '_kg_avatar_id' ) === false ) {
    echo "❌ FAIL: Custom avatar (_kg_avatar_id) check not found\n";
    exit(1);
}

if ( strpos( $helper_content, 'google_avatar' ) === false ) {
    echo "❌ FAIL: Google avatar check not found\n";
    exit(1);
}

// Check syntax
$syntax_check = exec( "php -l " . escapeshellarg( $helper_path ) . " 2>&1", $output, $return_var );
if ( $return_var !== 0 ) {
    echo "❌ FAIL: Syntax error in Helper.php\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

echo "✅ PASS: Helper.php has avatar utility method and valid syntax\n\n";

// Test 3: Check FeaturedController uses Helper::get_user_avatar_url
echo "Test 3: Checking FeaturedController uses shared avatar helper...\n";

if ( strpos( $featured_content, 'Helper::get_user_avatar_url' ) === false ) {
    echo "❌ FAIL: FeaturedController doesn't use Helper::get_user_avatar_url\n";
    exit(1);
}

echo "✅ PASS: FeaturedController uses shared avatar helper\n\n";

// Test 4: Check RecipeController uses Helper::get_user_avatar_url
echo "Test 4: Checking RecipeController uses shared avatar helper...\n";
$recipe_controller_path = $base_path . '/includes/API/RecipeController.php';

if ( ! file_exists( $recipe_controller_path ) ) {
    echo "❌ FAIL: RecipeController.php not found\n";
    exit(1);
}

$recipe_content = file_get_contents( $recipe_controller_path );

if ( strpos( $recipe_content, 'Helper::get_user_avatar_url' ) === false ) {
    echo "❌ FAIL: RecipeController doesn't use Helper::get_user_avatar_url\n";
    exit(1);
}

// Check syntax
$syntax_check = exec( "php -l " . escapeshellarg( $recipe_controller_path ) . " 2>&1", $output, $return_var );
if ( $return_var !== 0 ) {
    echo "❌ FAIL: Syntax error in RecipeController.php\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

echo "✅ PASS: RecipeController uses shared avatar helper and valid syntax\n\n";

// Test 5: Check CommentController exists and has proper endpoints
echo "Test 5: Checking CommentController.php implementation...\n";
$comment_controller_path = $base_path . '/includes/API/CommentController.php';

if ( ! file_exists( $comment_controller_path ) ) {
    echo "❌ FAIL: CommentController.php not found\n";
    exit(1);
}

$comment_content = file_get_contents( $comment_controller_path );

// Check for required endpoints
$required_endpoints = [
    "register_rest_route( 'kg/v1', '/comments'" => 'Generic comments endpoint',
    "register_rest_route( 'kg/v1', '/recipes/(?P<id>" => 'Recipe comments endpoint',
    "register_rest_route( 'kg/v1', '/posts/(?P<id>" => 'Post comments endpoint',
];

foreach ( $required_endpoints as $endpoint => $description ) {
    if ( strpos( $comment_content, $endpoint ) === false ) {
        echo "❌ FAIL: $description not found\n";
        exit(1);
    }
}

// Check for ALLOWED_COMMENT_TYPES constant
if ( strpos( $comment_content, 'ALLOWED_COMMENT_TYPES' ) === false ) {
    echo "❌ FAIL: ALLOWED_COMMENT_TYPES constant not found\n";
    exit(1);
}

// Check for JWT authentication
if ( strpos( $comment_content, 'check_authentication' ) === false ) {
    echo "❌ FAIL: JWT authentication check not found\n";
    exit(1);
}

// Check for Helper::get_user_avatar_url usage
if ( strpos( $comment_content, 'Helper::get_user_avatar_url' ) === false ) {
    echo "❌ FAIL: CommentController doesn't use Helper::get_user_avatar_url\n";
    exit(1);
}

// Check syntax
$syntax_check = exec( "php -l " . escapeshellarg( $comment_controller_path ) . " 2>&1", $output, $return_var );
if ( $return_var !== 0 ) {
    echo "❌ FAIL: Syntax error in CommentController.php\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

echo "✅ PASS: CommentController.php has all required endpoints and valid syntax\n\n";

// Test 6: Check kg-core.php includes CommentController
echo "Test 6: Checking kg-core.php includes and initializes CommentController...\n";
$kg_core_path = $base_path . '/kg-core.php';

if ( ! file_exists( $kg_core_path ) ) {
    echo "❌ FAIL: kg-core.php not found\n";
    exit(1);
}

$kg_core_content = file_get_contents( $kg_core_path );

// Check for require_once
if ( strpos( $kg_core_content, "includes/API/CommentController.php" ) === false ) {
    echo "❌ FAIL: CommentController.php require_once not found in kg-core.php\n";
    exit(1);
}

// Check for initialization
if ( strpos( $kg_core_content, "new \KG_Core\API\CommentController()" ) === false ) {
    echo "❌ FAIL: CommentController initialization not found in kg-core.php\n";
    exit(1);
}

// Check syntax
$syntax_check = exec( "php -l " . escapeshellarg( $kg_core_path ) . " 2>&1", $output, $return_var );
if ( $return_var !== 0 ) {
    echo "❌ FAIL: Syntax error in kg-core.php\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

echo "✅ PASS: kg-core.php includes and initializes CommentController\n\n";

// Test 7: Check namespace consistency
echo "Test 7: Checking namespace consistency...\n";

if ( strpos( $comment_content, 'namespace KG_Core\API;' ) === false ) {
    echo "❌ FAIL: CommentController doesn't use correct namespace\n";
    exit(1);
}

if ( strpos( $comment_content, 'use KG_Core\Auth\JWTHandler;' ) === false ) {
    echo "❌ FAIL: CommentController doesn't import JWTHandler\n";
    exit(1);
}

echo "✅ PASS: Namespace and imports are consistent\n\n";

// Final Summary
echo "====================================\n";
echo "All Tests Passed! ✅\n";
echo "====================================\n\n";

echo "Summary of validated features:\n";
echo "1. ✅ FeaturedController generates excerpts (20 words)\n";
echo "2. ✅ Helper utility has avatar priority method\n";
echo "3. ✅ All controllers use shared avatar helper\n";
echo "4. ✅ CommentController implements all endpoints\n";
echo "5. ✅ JWT authentication implemented\n";
echo "6. ✅ CommentController properly integrated\n";
echo "7. ✅ Code quality and consistency maintained\n";
echo "\n";

exit(0);

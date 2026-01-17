<?php
/**
 * Test script for CORS Handler improvements
 * This script validates the CORS implementation and all acceptance criteria
 */

echo "Testing CORS Handler Improvements\n";
echo "==================================\n\n";

$base_path = dirname( __DIR__ );

// Test 1: Check CORSHandler.php exists and has valid syntax
echo "Test 1: Checking CORSHandler.php syntax...\n";
$cors_handler_path = $base_path . '/includes/CORS/CORSHandler.php';

if ( ! file_exists( $cors_handler_path ) ) {
    echo "❌ FAIL: CORSHandler.php not found\n";
    exit(1);
}

$syntax_check = exec( "php -l " . escapeshellarg( $cors_handler_path ) . " 2>&1", $output, $return_var );
if ( $return_var !== 0 ) {
    echo "❌ FAIL: Syntax error in CORSHandler.php\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}

echo "✅ PASS: CORSHandler.php has valid syntax\n\n";

// Test 2: Check for is_allowed_origin method
echo "Test 2: Checking is_allowed_origin method exists...\n";
$cors_content = file_get_contents( $cors_handler_path );

if ( strpos( $cors_content, 'private function is_allowed_origin' ) === false ) {
    echo "❌ FAIL: is_allowed_origin method not found\n";
    exit(1);
}

if ( strpos( $cors_content, 'rtrim($origin' ) === false ) {
    echo "❌ FAIL: Trailing slash handling not found\n";
    exit(1);
}

echo "✅ PASS: is_allowed_origin method exists with trailing slash handling\n\n";

// Test 3: Check for HTTP_REFERER fallback in get_origin
echo "Test 3: Checking HTTP_REFERER fallback in get_origin...\n";

if ( strpos( $cors_content, 'HTTP_REFERER' ) === false ) {
    echo "❌ FAIL: HTTP_REFERER fallback not found\n";
    exit(1);
}

if ( strpos( $cors_content, 'wp_parse_url' ) === false ) {
    echo "❌ FAIL: wp_parse_url for referer parsing not found\n";
    exit(1);
}

echo "✅ PASS: HTTP_REFERER fallback implemented\n\n";

// Test 4: Check for send_cors_headers method
echo "Test 4: Checking send_cors_headers method exists...\n";

if ( strpos( $cors_content, 'public function send_cors_headers' ) === false ) {
    echo "❌ FAIL: send_cors_headers method not found\n";
    exit(1);
}

if ( strpos( $cors_content, "'/wp-json/'" ) === false ) {
    echo "❌ FAIL: REST API check in send_cors_headers not found\n";
    exit(1);
}

echo "✅ PASS: send_cors_headers method exists\n\n";

// Test 5: Check for PATCH method support
echo "Test 5: Checking PATCH method support...\n";

$patch_count = substr_count( $cors_content, 'OPTIONS, PATCH' );
if ( $patch_count < 3 ) {
    echo "❌ FAIL: PATCH method not found in all required places (found {$patch_count} times, expected at least 3)\n";
    exit(1);
}

echo "✅ PASS: PATCH method supported\n\n";

// Test 6: Check for additional headers
echo "Test 6: Checking additional headers (X-WP-Nonce, Cache-Control, Pragma)...\n";

if ( strpos( $cors_content, 'X-WP-Nonce' ) === false ) {
    echo "❌ FAIL: X-WP-Nonce header not found\n";
    exit(1);
}

if ( strpos( $cors_content, 'Cache-Control' ) === false ) {
    echo "❌ FAIL: Cache-Control header not found\n";
    exit(1);
}

if ( strpos( $cors_content, 'Pragma' ) === false ) {
    echo "❌ FAIL: Pragma header not found\n";
    exit(1);
}

if ( strpos( $cors_content, 'Vary: Origin' ) === false ) {
    echo "❌ FAIL: Vary: Origin header not found\n";
    exit(1);
}

echo "✅ PASS: All additional headers present\n\n";

// Test 7: Check for updated allowed origins
echo "Test 7: Checking allowed origins...\n";

if ( strpos( $cors_content, "'http://localhost:3000'" ) === false ) {
    echo "❌ FAIL: localhost:3000 not in allowed origins\n";
    exit(1);
}

if ( strpos( $cors_content, "'http://localhost:3002'" ) === false ) {
    echo "❌ FAIL: localhost:3002 not in allowed origins\n";
    exit(1);
}

if ( strpos( $cors_content, "'https://api.kidsgourmet.com.tr'" ) === false ) {
    echo "❌ FAIL: api.kidsgourmet.com.tr not in allowed origins\n";
    exit(1);
}

if ( strpos( $cors_content, "'https://kidsgourmet.com.tr'" ) === false ) {
    echo "❌ FAIL: kidsgourmet.com.tr not in allowed origins\n";
    exit(1);
}

echo "✅ PASS: All required origins present\n\n";

// Test 8: Check for early hook priorities
echo "Test 8: Checking hook priorities...\n";

if ( strpos( $cors_content, "add_action('rest_api_init', [\$this, 'add_cors_support'], 5)" ) === false ) {
    echo "❌ FAIL: rest_api_init priority not set to 5\n";
    exit(1);
}

if ( strpos( $cors_content, "add_action('init', [\$this, 'handle_preflight'], 1)" ) === false ) {
    echo "❌ FAIL: init priority not set to 1\n";
    exit(1);
}

if ( strpos( $cors_content, "add_action('send_headers', [\$this, 'send_cors_headers'], 1)" ) === false ) {
    echo "❌ FAIL: send_headers hook not added with priority 1\n";
    exit(1);
}

echo "✅ PASS: All hook priorities correctly set\n\n";

// Test 9: Check that JWT auth support is preserved
echo "Test 9: Checking JWT authentication support preserved...\n";

if ( strpos( $cors_content, 'enable_jwt_for_wp_endpoints' ) === false ) {
    echo "❌ FAIL: JWT auth support not found\n";
    exit(1);
}

if ( strpos( $cors_content, "'rest_authentication_errors'" ) === false ) {
    echo "❌ FAIL: rest_authentication_errors filter not found\n";
    exit(1);
}

echo "✅ PASS: JWT authentication support preserved\n\n";

// Test 10: Check that kg_core_allowed_origins filter is preserved
echo "Test 10: Checking kg_core_allowed_origins filter...\n";

if ( strpos( $cors_content, "apply_filters('kg_core_allowed_origins'" ) === false ) {
    echo "❌ FAIL: kg_core_allowed_origins filter not found\n";
    exit(1);
}

echo "✅ PASS: kg_core_allowed_origins filter preserved\n\n";

echo "========================================\n";
echo "All tests passed! ✅\n";
echo "========================================\n\n";

echo "Summary of improvements:\n";
echo "- ✅ Robust origin matching with trailing slash support\n";
echo "- ✅ HTTP_REFERER fallback for origin detection\n";
echo "- ✅ Earlier hook priorities (rest_api_init: 5, init: 1)\n";
echo "- ✅ New send_headers hook added (priority: 1)\n";
echo "- ✅ PATCH method support added\n";
echo "- ✅ Additional headers (X-WP-Nonce, Cache-Control, Pragma, Vary)\n";
echo "- ✅ New allowed origins (localhost:3002, api.kidsgourmet.com.tr)\n";
echo "- ✅ JWT authentication support preserved\n";
echo "- ✅ kg_core_allowed_origins filter preserved\n";

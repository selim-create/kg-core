<?php
/**
 * Test script for Vercel domain CORS support
 * This script validates that the Vercel domain is added to allowed origins
 */

echo "Testing Vercel Domain CORS Support\n";
echo "===================================\n\n";

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

// Test 2: Check for Vercel domain in allowed origins
echo "Test 2: Checking Vercel domain in allowed origins...\n";
$cors_content = file_get_contents( $cors_handler_path );

if ( strpos( $cors_content, "'https://kidsgourmet-web.vercel.app'" ) === false ) {
    echo "❌ FAIL: Vercel domain (https://kidsgourmet-web.vercel.app) not found in allowed origins\n";
    exit(1);
}

echo "✅ PASS: Vercel domain found in allowed origins\n\n";

// Test 3: Verify all existing origins are still present
echo "Test 3: Verifying all existing origins are still present...\n";

$required_origins = [
    "'http://localhost:3000'",
    "'http://localhost:3001'",
    "'http://localhost:3002'",
    "'https://kidsgourmet.com.tr'",
    "'https://www.kidsgourmet.com.tr'",
    "'https://api.kidsgourmet.com.tr'",
    "'https://kidsgourmet-web.vercel.app'",
];

$missing_origins = [];
foreach ( $required_origins as $origin ) {
    if ( strpos( $cors_content, $origin ) === false ) {
        $missing_origins[] = $origin;
    }
}

if ( ! empty( $missing_origins ) ) {
    echo "❌ FAIL: Missing origins: " . implode( ', ', $missing_origins ) . "\n";
    exit(1);
}

echo "✅ PASS: All required origins present (including Vercel domain)\n\n";

// Test 4: Verify get_allowed_origins method structure is intact
echo "Test 4: Verifying get_allowed_origins method structure...\n";

if ( strpos( $cors_content, 'private function get_allowed_origins' ) === false ) {
    echo "❌ FAIL: get_allowed_origins method not found\n";
    exit(1);
}

if ( strpos( $cors_content, "\$default_origins = [" ) === false ) {
    echo "❌ FAIL: \$default_origins array not found\n";
    exit(1);
}

if ( strpos( $cors_content, "apply_filters('kg_core_allowed_origins'" ) === false ) {
    echo "❌ FAIL: kg_core_allowed_origins filter not found\n";
    exit(1);
}

echo "✅ PASS: get_allowed_origins method structure intact\n\n";

echo "========================================\n";
echo "All tests passed! ✅\n";
echo "========================================\n\n";

echo "Summary:\n";
echo "- ✅ Vercel domain (https://kidsgourmet-web.vercel.app) added to allowed origins\n";
echo "- ✅ All existing origins preserved\n";
echo "- ✅ Method structure intact\n";
echo "- ✅ No syntax errors\n";

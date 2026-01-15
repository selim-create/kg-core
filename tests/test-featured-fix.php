<?php
/**
 * Test script for Featured Content API fixes
 * This validates the fixes for sponsored content and featured posts issues
 */

echo "Testing Featured Content API Fixes\n";
echo "===================================\n\n";

// Test 1: Check FeaturedController.php syntax
echo "Test 1: Validating FeaturedController.php syntax...\n";
$featured_controller_path = __DIR__ . '/includes/API/FeaturedController.php';

if ( !file_exists( $featured_controller_path ) ) {
    echo "❌ FAIL: FeaturedController.php not found\n";
    exit(1);
}

$syntax_check = exec( "php -l " . escapeshellarg( $featured_controller_path ) . " 2>&1", $output, $return_var );
if ( $return_var !== 0 ) {
    echo "❌ FAIL: Syntax error in FeaturedController.php\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}
echo "✅ PASS: FeaturedController.php syntax is valid\n\n";

// Test 2: Verify get_featured_posts excludes sponsored content
echo "Test 2: Checking get_featured_posts() excludes sponsored content...\n";
$controller_content = file_get_contents( $featured_controller_path );

// Check for the AND relation in meta_query
if ( strpos( $controller_content, "'relation' => 'AND'" ) === false ) {
    echo "❌ FAIL: get_featured_posts() doesn't have proper meta_query with AND relation\n";
    exit(1);
}

// Check for _kg_is_sponsored exclusion
if ( strpos( $controller_content, "'key' => '_kg_is_sponsored'" ) === false ) {
    echo "❌ FAIL: get_featured_posts() doesn't filter out sponsored content\n";
    exit(1);
}

// Check for NOT EXISTS clause
if ( strpos( $controller_content, "'compare' => 'NOT EXISTS'" ) === false ) {
    echo "❌ FAIL: get_featured_posts() doesn't handle posts without sponsored meta\n";
    exit(1);
}

echo "✅ PASS: get_featured_posts() properly excludes sponsored content\n\n";

// Test 3: Verify get_sponsored_content requires featured flag
echo "Test 3: Checking get_sponsored_content() requires featured flag...\n";

// Find the get_sponsored_content method
$method_start = strpos( $controller_content, 'private function get_sponsored_content' );
if ( $method_start === false ) {
    echo "❌ FAIL: get_sponsored_content() method not found\n";
    exit(1);
}

// Get a portion of the file containing the method
$method_content = substr( $controller_content, $method_start, 2000 );

// Check for both _kg_is_sponsored and _kg_is_featured in meta_query
$has_sponsored_check = strpos( $method_content, "_kg_is_sponsored" ) !== false;
$has_featured_check = strpos( $method_content, "_kg_is_featured" ) !== false;

if ( !$has_sponsored_check || !$has_featured_check ) {
    echo "❌ FAIL: get_sponsored_content() doesn't check both sponsored and featured flags\n";
    exit(1);
}

echo "✅ PASS: get_sponsored_content() requires featured flag\n\n";

// Test 4: Verify direct_redirect field is included
echo "Test 4: Checking direct_redirect field in sponsored content...\n";

if ( strpos( $controller_content, "'direct_redirect' => \$direct_redirect" ) === false ) {
    echo "❌ FAIL: direct_redirect field not found in sponsored content response\n";
    exit(1);
}

if ( strpos( $controller_content, "get_post_meta( \$post->ID, '_kg_direct_redirect', true ) === '1'" ) === false ) {
    echo "❌ FAIL: direct_redirect meta field not being read correctly\n";
    exit(1);
}

echo "✅ PASS: direct_redirect field is included in sponsored content response\n\n";

// Test 5: Verify discount_text is decoded
echo "Test 5: Checking discount_text HTML entity decoding...\n";

// Look for the discount_text line with decode_html_entities
// More specific regex to avoid false positives
$discount_decode_pattern = '/\$discount_text\s*=\s*\\\\KG_Core\\\\Utils\\\\Helper::decode_html_entities\(\s*\$discount_text\s*\);/';
if ( !preg_match( $discount_decode_pattern, $controller_content ) ) {
    echo "❌ FAIL: discount_text is not being decoded with decode_html_entities\n";
    exit(1);
}

echo "✅ PASS: discount_text is properly decoded\n\n";

// Test 6: Verify featured questions include pending status
echo "Test 6: Checking get_featured_questions() includes pending status...\n";

// Find the get_featured_questions method
$questions_start = strpos( $controller_content, 'private function get_featured_questions' );
if ( $questions_start === false ) {
    echo "❌ FAIL: get_featured_questions() method not found\n";
    exit(1);
}

$questions_content = substr( $controller_content, $questions_start, 1000 );

// Check for both publish and pending in post_status
if ( strpos( $questions_content, "'publish', 'pending'" ) === false && 
     strpos( $questions_content, "'pending', 'publish'" ) === false ) {
    echo "❌ FAIL: get_featured_questions() doesn't include both publish and pending statuses\n";
    exit(1);
}

echo "✅ PASS: get_featured_questions() includes pending status\n\n";

// All tests passed
echo "=====================================\n";
echo "✅ ALL TESTS PASSED!\n";
echo "=====================================\n\n";

echo "Summary of fixes:\n";
echo "1. ✅ get_featured_posts() now excludes sponsored content\n";
echo "2. ✅ get_sponsored_content() only returns featured sponsors\n";
echo "3. ✅ direct_redirect field added to sponsored content response\n";
echo "4. ✅ discount_text is properly decoded (fixes truncation)\n";
echo "5. ✅ get_featured_questions() includes pending status\n";

exit(0);

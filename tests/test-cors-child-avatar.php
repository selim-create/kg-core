<?php
/**
 * Static Code Analysis Test for CORS Child Avatar Upload Fix
 * 
 * Tests for:
 * - CORSHandler has Content-Disposition in allowed headers
 * - All CORS header locations are updated
 * - ChildProfileAvatarController has debug logging
 * 
 * @package KG_Core
 */

echo "=== CORS Child Avatar Upload Fix Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if CORSHandler file exists and has Content-Disposition header
echo "1. CORSHandler Content-Disposition Header\n";
$corsHandlerFile = $baseDir . '/includes/CORS/CORSHandler.php';

if (file_exists($corsHandlerFile)) {
    echo "   ✓ File exists: CORSHandler.php\n";
    $passed++;
    $content = file_get_contents($corsHandlerFile);
    
    // Check for Content-Disposition in Access-Control-Allow-Headers
    $headerPattern = '/Access-Control-Allow-Headers:.*Content-Disposition/';
    $matches = preg_match_all($headerPattern, $content);
    
    if ($matches >= 3) {
        echo "   ✓ Content-Disposition found in all 3 locations ({$matches} occurrences)\n";
        $passed++;
    } else {
        echo "   ✗ Content-Disposition found in only {$matches}/3 locations\n";
        $failed++;
    }
    
    // Check for X-Requested-With (should already be there)
    if (strpos($content, 'X-Requested-With') !== false) {
        echo "   ✓ X-Requested-With header present\n";
        $passed++;
    } else {
        echo "   ✗ X-Requested-With header missing\n";
        $failed++;
    }
    
    // Verify all required headers are present in each location
    $requiredHeaders = [
        'Authorization',
        'Content-Type',
        'Content-Disposition',
        'X-Requested-With',
        'X-WP-Nonce',
        'Cache-Control',
        'Pragma'
    ];
    
    $headerLine = '/Access-Control-Allow-Headers: Authorization, Content-Type, Content-Disposition, X-Requested-With, X-WP-Nonce, Cache-Control, Pragma/';
    $completeMatches = preg_match_all($headerLine, $content);
    
    if ($completeMatches >= 3) {
        echo "   ✓ Complete header list found in all locations\n";
        $passed++;
    } else {
        echo "   ✗ Complete header list found in only {$completeMatches}/3 locations\n";
        $failed++;
    }
    
} else {
    echo "   ✗ File not found: CORSHandler.php\n";
    $failed++;
}

echo "\n";

// Test 2: Check if ChildProfileAvatarController has debug logging
echo "2. ChildProfileAvatarController Debug Logging\n";
$avatarControllerFile = $baseDir . '/includes/API/ChildProfileAvatarController.php';

if (file_exists($avatarControllerFile)) {
    echo "   ✓ File exists: ChildProfileAvatarController.php\n";
    $passed++;
    $content = file_get_contents($avatarControllerFile);
    
    // Check for debug logging in upload_avatar method
    if (strpos($content, "error_log( 'Child Avatar Upload Request - Origin:") !== false) {
        echo "   ✓ Origin debug logging present\n";
        $passed++;
    } else {
        echo "   ✗ Origin debug logging missing\n";
        $failed++;
    }
    
    if (strpos($content, "error_log( 'Child Avatar Upload Request - Method:") !== false) {
        echo "   ✓ Method debug logging present\n";
        $passed++;
    } else {
        echo "   ✗ Method debug logging missing\n";
        $failed++;
    }
    
} else {
    echo "   ✗ File not found: ChildProfileAvatarController.php\n";
    $failed++;
}

echo "\n";

// Test 3: Verify CORS handler methods exist
echo "3. CORSHandler Methods\n";
if (file_exists($corsHandlerFile)) {
    $content = file_get_contents($corsHandlerFile);
    
    if (strpos($content, 'function add_cors_support') !== false) {
        echo "   ✓ Method exists: add_cors_support()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: add_cors_support()\n";
        $failed++;
    }
    
    if (strpos($content, 'function handle_preflight') !== false) {
        echo "   ✓ Method exists: handle_preflight()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: handle_preflight()\n";
        $failed++;
    }
    
    if (strpos($content, 'function send_cors_headers') !== false) {
        echo "   ✓ Method exists: send_cors_headers()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: send_cors_headers()\n";
        $failed++;
    }
}

echo "\n";

// Summary
echo "=== Summary ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n\n";

if ($failed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}

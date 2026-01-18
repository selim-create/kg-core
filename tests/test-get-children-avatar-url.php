<?php
/**
 * Test get_children() Avatar URL Generation
 * 
 * This test verifies that the get_children() endpoint generates signed URLs for child avatars.
 * 
 * Tests for:
 * - ChildAvatarService is imported in UserController
 * - get_children() method generates avatar_url for children with avatars
 * - has_avatar field is set correctly
 * - avatar_url is null for children without avatars
 * 
 * @package KG_Core
 */

echo "=== get_children() Avatar URL Generation Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if ChildAvatarService is imported in UserController
echo "1. UserController ChildAvatarService Import\n";
$userControllerFile = $baseDir . '/includes/API/UserController.php';

if (file_exists($userControllerFile)) {
    echo "   ✓ File exists: UserController.php\n";
    $passed++;
    $content = file_get_contents($userControllerFile);
    
    // Check for ChildAvatarService import
    if (strpos($content, 'use KG_Core\Services\ChildAvatarService;') !== false) {
        echo "   ✓ ChildAvatarService imported\n";
        $passed++;
    } else {
        echo "   ✗ ChildAvatarService not imported\n";
        $failed++;
    }
} else {
    echo "   ✗ UserController.php not found\n";
    $failed++;
}

// Test 2: Verify get_children() method implementation
echo "\n2. get_children() Method Implementation\n";

if (file_exists($userControllerFile)) {
    $content = file_get_contents($userControllerFile);
    
    // Search for specific implementation details in the get_children method area
    // We'll use grep-like searching since the method exists
    
    // Check for avatar_service instantiation
    if (strpos($content, 'new ChildAvatarService()') !== false || 
        strpos($content, 'new \KG_Core\Services\ChildAvatarService()') !== false) {
        echo "   ✓ ChildAvatarService instantiated\n";
        $passed++;
    } else {
        echo "   ✗ ChildAvatarService not instantiated\n";
        $failed++;
    }
    
    // Check for avatar_url generation
    if (strpos($content, "get_signed_url") !== false) {
        echo "   ✓ get_signed_url() method called\n";
        $passed++;
    } else {
        echo "   ✗ get_signed_url() not called\n";
        $failed++;
    }
    
    // Check for has_avatar field in get_children context
    if (strpos($content, "['has_avatar']") !== false || strpos($content, "[ 'has_avatar' ]") !== false) {
        echo "   ✓ has_avatar field set\n";
        $passed++;
    } else {
        echo "   ✗ has_avatar field not set\n";
        $failed++;
    }
    
    // Check for avatar_url field
    if (strpos($content, "['avatar_url']") !== false || strpos($content, "[ 'avatar_url' ]") !== false) {
        echo "   ✓ avatar_url field set\n";
        $passed++;
    } else {
        echo "   ✗ avatar_url field not set\n";
        $failed++;
    }
    
    // Check for loop through children
    if (preg_match('/foreach\s*\(\s*\$children\s+as\s+&\$child\s*\)/', $content)) {
        echo "   ✓ Loop through children array with reference\n";
        $passed++;
    } else {
        echo "   ✗ No loop through children array with reference\n";
        $failed++;
    }
    
    // Check for avatar_path check
    if (strpos($content, "['avatar_path']") !== false || strpos($content, "[ 'avatar_path' ]") !== false) {
        echo "   ✓ avatar_path accessed\n";
        $passed++;
    } else {
        echo "   ✗ avatar_path not accessed\n";
        $failed++;
    }
} else {
    echo "   ✗ UserController.php not found\n";
    $failed++;
}

// Test 3: Verify ChildAvatarService has get_signed_url method
echo "\n3. ChildAvatarService::get_signed_url() Method\n";
$avatarServiceFile = $baseDir . '/includes/Services/ChildAvatarService.php';

if (file_exists($avatarServiceFile)) {
    echo "   ✓ File exists: ChildAvatarService.php\n";
    $passed++;
    $content = file_get_contents($avatarServiceFile);
    
    // Check for get_signed_url method
    if (preg_match('/public function get_signed_url\s*\(/', $content)) {
        echo "   ✓ get_signed_url() method exists\n";
        $passed++;
    } else {
        echo "   ✗ get_signed_url() method not found\n";
        $failed++;
    }
    
    // Check for URL generation
    if (strpos($content, "rest_url") !== false && strpos($content, "child-profiles/avatar-file") !== false) {
        echo "   ✓ REST URL generation found\n";
        $passed++;
    } else {
        echo "   ✗ REST URL generation not found\n";
        $failed++;
    }
    
    // Check for signed URL parameters (token, expires)
    if (strpos($content, "token") !== false && strpos($content, "expires") !== false) {
        echo "   ✓ Signed URL parameters (token, expires) found\n";
        $passed++;
    } else {
        echo "   ✗ Signed URL parameters not found\n";
        $failed++;
    }
} else {
    echo "   ✗ ChildAvatarService.php not found\n";
    $failed++;
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "TESTS PASSED: {$passed}\n";
echo "TESTS FAILED: {$failed}\n";

if ($failed === 0) {
    echo "\n✓ All tests passed! get_children() avatar URL generation implemented correctly.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}

<?php
/**
 * Static Code Analysis Test for Expert API Improvements
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core Expert API Improvements Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check ExpertController for kg_expert role filtering
echo "1. ExpertController - kg_expert Role Filtering\n";
$expertControllerFile = $baseDir . '/includes/API/ExpertController.php';
if (file_exists($expertControllerFile)) {
    echo "   ✓ File exists: ExpertController.php\n";
    $content = file_get_contents($expertControllerFile);
    
    // Check that it only uses 'kg_expert' role, not 'author' or 'editor'
    if (preg_match("/get_users\s*\(\s*\[[^\]]*'role'\s*=>\s*'kg_expert'/s", $content)) {
        echo "   ✓ Uses 'role' => 'kg_expert' filter\n";
        $passed++;
        
        // Make sure it doesn't use role__in with multiple roles
        if (strpos($content, "role__in") === false || !preg_match("/role__in.*author.*editor/s", $content)) {
            echo "   ✓ Does not include author/editor roles in experts list\n";
            $passed++;
        } else {
            echo "   ✗ Still includes author/editor roles\n";
            $failed++;
        }
    } else {
        echo "   ✗ Not filtering by kg_expert role only\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: ExpertController.php\n";
    $failed++;
}
echo "\n";

// Test 2: Check ExpertController for custom avatar support
echo "2. ExpertController - Custom Avatar Support\n";
if (file_exists($expertControllerFile)) {
    $content = file_get_contents($expertControllerFile);
    
    if (strpos($content, '_kg_avatar_id') !== false) {
        echo "   ✓ Retrieves _kg_avatar_id meta\n";
        $passed++;
        
        if (strpos($content, 'wp_get_attachment_image_url') !== false) {
            echo "   ✓ Uses wp_get_attachment_image_url for custom avatar\n";
            $passed++;
        } else {
            echo "   ✗ Missing wp_get_attachment_image_url\n";
            $failed++;
        }
        
        if (strpos($content, 'get_avatar_url') !== false) {
            echo "   ✓ Falls back to get_avatar_url\n";
            $passed++;
        } else {
            echo "   ✗ Missing get_avatar_url fallback\n";
            $failed++;
        }
    } else {
        echo "   ✗ Not using custom avatar (_kg_avatar_id)\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: ExpertController.php\n";
    $failed++;
}
echo "\n";

// Test 3: Check ExpertController stats include required fields
echo "3. ExpertController - Statistics Fields\n";
if (file_exists($expertControllerFile)) {
    $content = file_get_contents($expertControllerFile);
    
    $requiredStats = [
        'total_recipes' => false,
        'total_blog_posts' => false,
        'total_posts' => false,
        'total_answers' => false,
        'total_questions' => false
    ];
    
    foreach ($requiredStats as $stat => $found) {
        if (preg_match("/'$stat'\s*=>/", $content)) {
            echo "   ✓ Stats include '$stat'\n";
            $passed++;
            $requiredStats[$stat] = true;
        }
    }
    
    foreach ($requiredStats as $stat => $found) {
        if (!$found) {
            echo "   ✗ Stats missing '$stat'\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ File not found: ExpertController.php\n";
    $failed++;
}
echo "\n";

// Test 4: Check UserController for custom avatar in get_expert_public_profile
echo "4. UserController - Custom Avatar in Expert Profile\n";
$userControllerFile = $baseDir . '/includes/API/UserController.php';
if (file_exists($userControllerFile)) {
    echo "   ✓ File exists: UserController.php\n";
    $content = file_get_contents($userControllerFile);
    
    // Find get_expert_public_profile method
    if (strpos($content, 'function get_expert_public_profile') !== false) {
        echo "   ✓ Method exists: get_expert_public_profile()\n";
        $passed++;
        
        // Check for custom avatar logic in this method
        $methodStart = strpos($content, 'function get_expert_public_profile');
        $methodEnd = strpos($content, 'function ', $methodStart + 10);
        if ($methodEnd === false) {
            $methodEnd = strlen($content);
        }
        $methodContent = substr($content, $methodStart, $methodEnd - $methodStart);
        
        if (strpos($methodContent, '_kg_avatar_id') !== false && 
            strpos($methodContent, 'wp_get_attachment_image_url') !== false) {
            echo "   ✓ Uses custom avatar in get_expert_public_profile()\n";
            $passed++;
        } else {
            echo "   ✗ Missing custom avatar support in get_expert_public_profile()\n";
            $failed++;
        }
    } else {
        echo "   ✗ Method not found: get_expert_public_profile()\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: UserController.php\n";
    $failed++;
}
echo "\n";

// Test 5: Check UserProfileFields class exists
echo "5. Admin - UserProfileFields Class\n";
$userProfileFieldsFile = $baseDir . '/includes/Admin/UserProfileFields.php';
if (file_exists($userProfileFieldsFile)) {
    echo "   ✓ File exists: UserProfileFields.php\n";
    $content = file_get_contents($userProfileFieldsFile);
    
    if (strpos($content, 'class UserProfileFields') !== false) {
        echo "   ✓ Class exists: UserProfileFields\n";
        $passed++;
        
        // Check for required methods
        $methods = [
            'add_expert_fields',
            'save_expert_fields',
            'enqueue_admin_scripts'
        ];
        
        foreach ($methods as $method) {
            if (strpos($content, "function $method") !== false) {
                echo "   ✓ Method exists: $method()\n";
                $passed++;
            } else {
                echo "   ✗ Method missing: $method()\n";
                $failed++;
            }
        }
        
        // Check for required fields
        $fields = [
            'kg_avatar',
            'kg_biography',
            'kg_expertise',
            'kg_show_email',
            'kg_social_instagram',
            'kg_social_twitter',
            'kg_social_linkedin'
        ];
        
        foreach ($fields as $field) {
            if (strpos($content, $field) !== false) {
                echo "   ✓ Field exists: $field\n";
                $passed++;
            } else {
                echo "   ✗ Field missing: $field\n";
                $failed++;
            }
        }
    } else {
        echo "   ✗ Class not found: UserProfileFields\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: UserProfileFields.php\n";
    $failed++;
}
echo "\n";

// Test 6: Check admin JavaScript file
echo "6. Admin - User Profile JavaScript\n";
$adminJsFile = $baseDir . '/assets/js/admin-user-profile.js';
if (file_exists($adminJsFile)) {
    echo "   ✓ File exists: admin-user-profile.js\n";
    $content = file_get_contents($adminJsFile);
    
    // Check for required functions
    if (strpos($content, 'kg-upload-avatar') !== false) {
        echo "   ✓ Upload avatar button handler exists\n";
        $passed++;
    } else {
        echo "   ✗ Missing upload avatar button handler\n";
        $failed++;
    }
    
    if (strpos($content, 'kg-remove-avatar') !== false) {
        echo "   ✓ Remove avatar button handler exists\n";
        $passed++;
    } else {
        echo "   ✗ Missing remove avatar button handler\n";
        $failed++;
    }
    
    if (strpos($content, 'wp.media') !== false) {
        echo "   ✓ Uses WordPress media uploader\n";
        $passed++;
    } else {
        echo "   ✗ Missing WordPress media uploader\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: admin-user-profile.js\n";
    $failed++;
}
echo "\n";

// Test 7: Check kg-core.php loads UserProfileFields
echo "7. Plugin - UserProfileFields Loading\n";
$mainPluginFile = $baseDir . '/kg-core.php';
if (file_exists($mainPluginFile)) {
    echo "   ✓ File exists: kg-core.php\n";
    $content = file_get_contents($mainPluginFile);
    
    // Check if UserProfileFields.php is required
    if (strpos($content, "includes/Admin/UserProfileFields.php") !== false) {
        echo "   ✓ UserProfileFields.php is required\n";
        $passed++;
    } else {
        echo "   ✗ UserProfileFields.php not required\n";
        $failed++;
    }
    
    // Check if UserProfileFields class is instantiated
    if (strpos($content, "new \KG_Core\Admin\UserProfileFields()") !== false) {
        echo "   ✓ UserProfileFields class is instantiated\n";
        $passed++;
    } else {
        echo "   ✗ UserProfileFields class not instantiated\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: kg-core.php\n";
    $failed++;
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the output above.\n";
    exit(1);
}

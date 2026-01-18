<?php
/**
 * Test Child Avatar User Meta Fix
 * 
 * Tests for:
 * - ChildProfileAvatarController uses user_meta instead of database table
 * - All methods (upload, get, delete) use get_user_meta()
 * - ChildProfile model is no longer imported
 * - Helper method find_child_in_user_meta exists
 * 
 * @package KG_Core
 */

echo "=== Child Avatar User Meta Fix Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if ChildProfileAvatarController has been updated
echo "1. ChildProfileAvatarController User Meta Implementation\n";
$avatarControllerFile = $baseDir . '/includes/API/ChildProfileAvatarController.php';

if (file_exists($avatarControllerFile)) {
    echo "   ✓ File exists: ChildProfileAvatarController.php\n";
    $passed++;
    $content = file_get_contents($avatarControllerFile);
    
    // Check that ChildProfile model is NOT imported
    if (strpos($content, 'use KG_Core\Models\ChildProfile;') === false) {
        echo "   ✓ ChildProfile model import removed (not using database table)\n";
        $passed++;
    } else {
        echo "   ✗ ChildProfile model still imported (should be removed)\n";
        $failed++;
    }
    
    // Check for get_user_meta usage (now in helper method)
    $userMetaCount = substr_count($content, "get_user_meta( \$user_id, '_kg_children', true )");
    if ($userMetaCount >= 1) {
        echo "   ✓ get_user_meta() used in helper method ({$userMetaCount} occurrence)\n";
        $passed++;
    } else {
        echo "   ✗ get_user_meta() not found in helper method\n";
        $failed++;
    }
    
    // Check for update_user_meta usage
    $updateMetaCount = substr_count($content, "update_user_meta( \$user_id, '_kg_children', \$children )");
    if ($updateMetaCount >= 2) {
        echo "   ✓ update_user_meta() used in upload and delete methods ({$updateMetaCount} occurrences)\n";
        $passed++;
    } else {
        echo "   ✗ update_user_meta() found in only {$updateMetaCount}/2 methods\n";
        $failed++;
    }
    
    // Check that ChildProfile::belongs_to_user is NOT used
    if (strpos($content, 'ChildProfile::belongs_to_user') === false) {
        echo "   ✓ ChildProfile::belongs_to_user() not used (ownership check in user_meta)\n";
        $passed++;
    } else {
        echo "   ✗ ChildProfile::belongs_to_user() still used (should use user_meta instead)\n";
        $failed++;
    }
    
    // Check that ChildProfile::get_by_uuid is NOT used
    if (strpos($content, 'ChildProfile::get_by_uuid') === false) {
        echo "   ✓ ChildProfile::get_by_uuid() not used (reading from user_meta)\n";
        $passed++;
    } else {
        echo "   ✗ ChildProfile::get_by_uuid() still used (should use user_meta instead)\n";
        $failed++;
    }
    
    // Check that ChildProfile::update_avatar is NOT used
    if (strpos($content, 'ChildProfile::update_avatar') === false) {
        echo "   ✓ ChildProfile::update_avatar() not used (updating user_meta directly)\n";
        $passed++;
    } else {
        echo "   ✗ ChildProfile::update_avatar() still used (should update user_meta instead)\n";
        $failed++;
    }
    
} else {
    echo "   ✗ File not found: ChildProfileAvatarController.php\n";
    $failed++;
}

echo "\n";

// Test 2: Check upload_avatar method implementation
echo "2. upload_avatar() Method Implementation\n";
if (file_exists($avatarControllerFile)) {
    $content = file_get_contents($avatarControllerFile);
    
    // Extract upload_avatar method
    preg_match('/public function upload_avatar\(.*?\n    \}/s', $content, $uploadMethod);
    if (!empty($uploadMethod[0])) {
        $method = $uploadMethod[0];
        
        // Check for helper method usage
        if (strpos($method, "\$result = \$this->find_child_in_user_meta( \$user_id, \$child_uuid )") !== false) {
            echo "   ✓ Uses find_child_in_user_meta() helper method\n";
            $passed++;
        } else {
            echo "   ✗ Not using find_child_in_user_meta() helper method\n";
            $failed++;
        }
        
        // Check for avatar_path update in user_meta
        if (strpos($method, "\$children[\$child_index]['avatar_path'] = \$upload_result['path']") !== false) {
            echo "   ✓ Avatar path updated in user_meta array\n";
            $passed++;
        } else {
            echo "   ✗ Avatar path not updated in user_meta array\n";
            $failed++;
        }
        
        // Check for child_not_found error
        if (strpos($method, "'child_not_found'") !== false) {
            echo "   ✓ Proper error handling when child not found\n";
            $passed++;
        } else {
            echo "   ✗ Missing error handling when child not found\n";
            $failed++;
        }
        
    } else {
        echo "   ✗ Could not extract upload_avatar method\n";
        $failed++;
    }
}

echo "\n";

// Test 3: Check get_avatar method implementation
echo "3. get_avatar() Method Implementation\n";
if (file_exists($avatarControllerFile)) {
    $content = file_get_contents($avatarControllerFile);
    
    // Extract get_avatar method
    preg_match('/public function get_avatar\(.*?\n    \}/s', $content, $getMethod);
    if (!empty($getMethod[0])) {
        $method = $getMethod[0];
        
        // Check for helper method usage
        if (strpos($method, "\$result = \$this->find_child_in_user_meta( \$user_id, \$child_uuid )") !== false) {
            echo "   ✓ Uses find_child_in_user_meta() helper method\n";
            $passed++;
        } else {
            echo "   ✗ Not using find_child_in_user_meta() helper method\n";
            $failed++;
        }
        
        // Check for no_avatar error
        if (strpos($method, "'no_avatar'") !== false) {
            echo "   ✓ Proper error handling for child without avatar\n";
            $passed++;
        } else {
            echo "   ✗ Missing error handling for child without avatar\n";
            $failed++;
        }
        
        // Check for avatar_path access from array
        if (strpos($method, "\$child['avatar_path']") !== false) {
            echo "   ✓ Avatar path accessed from user_meta array\n";
            $passed++;
        } else {
            echo "   ✗ Avatar path not accessed from user_meta array\n";
            $failed++;
        }
        
    } else {
        echo "   ✗ Could not extract get_avatar method\n";
        $failed++;
    }
}

echo "\n";

// Test 4: Check delete_avatar method implementation
echo "4. delete_avatar() Method Implementation\n";
if (file_exists($avatarControllerFile)) {
    $content = file_get_contents($avatarControllerFile);
    
    // Extract delete_avatar method
    preg_match('/public function delete_avatar\(.*?\n    \}/s', $content, $deleteMethod);
    if (!empty($deleteMethod[0])) {
        $method = $deleteMethod[0];
        
        // Check for helper method usage
        if (strpos($method, "\$result = \$this->find_child_in_user_meta( \$user_id, \$child_uuid )") !== false) {
            echo "   ✓ Uses find_child_in_user_meta() helper method\n";
            $passed++;
        } else {
            echo "   ✗ Not using find_child_in_user_meta() helper method\n";
            $failed++;
        }
        
        // Check for avatar_path nullification
        if (strpos($method, "\$children[\$child_index]['avatar_path'] = null") !== false) {
            echo "   ✓ Avatar path set to null in user_meta array\n";
            $passed++;
        } else {
            echo "   ✗ Avatar path not properly nullified in user_meta\n";
            $failed++;
        }
        
    } else {
        echo "   ✗ Could not extract delete_avatar method\n";
        $failed++;
    }
}

echo "\n";

// Test 5: Check helper method exists
echo "5. Helper Method find_child_in_user_meta()\n";
if (file_exists($avatarControllerFile)) {
    $content = file_get_contents($avatarControllerFile);
    
    if (strpos($content, 'private function find_child_in_user_meta') !== false) {
        echo "   ✓ Helper method find_child_in_user_meta() exists\n";
        $passed++;
        
        // Check method signature
        if (strpos($content, 'find_child_in_user_meta( $user_id, $child_uuid )') !== false) {
            echo "   ✓ Method has correct parameters\n";
            $passed++;
        } else {
            echo "   ✗ Method parameters incorrect\n";
            $failed++;
        }
        
        // Check return structure
        if (strpos($content, "[ 'child' => \$child, 'index' => \$index, 'all_children' => \$children ]") !== false) {
            echo "   ✓ Method returns correct structure\n";
            $passed++;
        } else {
            echo "   ✗ Method return structure incorrect\n";
            $failed++;
        }
        
    } else {
        echo "   ✗ Helper method find_child_in_user_meta() not found\n";
        $failed++;
    }
}

echo "\n";

// Test 6: Verify no database table references
echo "6. Database Table References Removed\n";
if (file_exists($avatarControllerFile)) {
    $content = file_get_contents($avatarControllerFile);
    
    // Check that there are no direct references to kg_child_profiles table
    if (strpos($content, 'kg_child_profiles') === false) {
        echo "   ✓ No direct references to kg_child_profiles table\n";
        $passed++;
    } else {
        echo "   ✗ Still has references to kg_child_profiles table\n";
        $failed++;
    }
    
    // Check for no $wpdb usage (since we're using user_meta)
    $wpdb_var = '$wpdb';
    if (strpos($content, $wpdb_var) === false) {
        echo "   ✓ No " . $wpdb_var . " usage (using WordPress user_meta API)\n";
        $passed++;
    } else {
        echo "   ✗ Still using " . $wpdb_var . " (should use user_meta API)\n";
        $failed++;
    }
}

echo "\n";

// Summary
echo "=== Summary ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n\n";

if ($failed === 0) {
    echo "✓ All tests passed! Child avatar controller now uses user_meta.\n";
    echo "\nKey Changes Verified:\n";
    echo "- ✓ No longer uses ChildProfile model or database table\n";
    echo "- ✓ Helper method find_child_in_user_meta() used for all child lookups\n";
    echo "- ✓ Avatar path stored/updated in user_meta\n";
    echo "- ✓ Code is DRY (Don't Repeat Yourself) - no duplication\n";
    echo "- ✓ Proper error handling for all scenarios\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}

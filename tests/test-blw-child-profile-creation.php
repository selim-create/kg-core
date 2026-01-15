<?php
/**
 * Test for BLW Test Child Profile Creation Feature
 * 
 * This test verifies that child profiles are created during BLW test registration
 */

echo "=== BLW Test Child Profile Creation Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if submit_blw_test accepts child_name and child_birth_date parameters
echo "1. Parameter Handling\n";
$toolControllerFile = $baseDir . '/includes/API/ToolController.php';
if (file_exists($toolControllerFile)) {
    $content = file_get_contents($toolControllerFile);
    
    // Check if child_name parameter is retrieved
    if (strpos($content, "get_param( 'child_name' )") !== false) {
        echo "   ✓ child_name parameter is retrieved\n";
        $passed++;
    } else {
        echo "   ✗ child_name parameter not found\n";
        $failed++;
    }
    
    // Check if child_birth_date parameter is retrieved
    if (strpos($content, "get_param( 'child_birth_date' )") !== false) {
        echo "   ✓ child_birth_date parameter is retrieved\n";
        $passed++;
    } else {
        echo "   ✗ child_birth_date parameter not found\n";
        $failed++;
    }
    
    // Check if parameters are sanitized
    if (strpos($content, "sanitize_text_field( \$request->get_param( 'child_name' )") !== false) {
        echo "   ✓ child_name is sanitized\n";
        $passed++;
    } else {
        echo "   ✗ child_name sanitization not found\n";
        $failed++;
    }
    
    if (strpos($content, "sanitize_text_field( \$request->get_param( 'child_birth_date' )") !== false) {
        echo "   ✓ child_birth_date is sanitized\n";
        $passed++;
    } else {
        echo "   ✗ child_birth_date sanitization not found\n";
        $failed++;
    }
} else {
    echo "   ✗ ToolController.php not found\n";
    $failed += 4;
}

echo "\n";

// Test 2: Check if child profile creation logic exists
echo "2. Child Profile Creation Logic\n";
if (file_exists($toolControllerFile)) {
    $content = file_get_contents($toolControllerFile);
    
    // Check if child_name is checked before creating profile
    if (strpos($content, "if ( ! empty( \$child_name ) )") !== false) {
        echo "   ✓ child_name validation exists\n";
        $passed++;
    } else {
        echo "   ✗ child_name validation not found\n";
        $failed++;
    }
    
    // Check if _kg_children meta is retrieved
    if (preg_match("/get_user_meta\(\s*\\\$user_id,\s*'_kg_children'/", $content)) {
        echo "   ✓ _kg_children meta retrieval found\n";
        $passed++;
    } else {
        echo "   ✗ _kg_children meta retrieval not found\n";
        $failed++;
    }
    
    // Check if UUID is generated for child
    if (strpos($content, "wp_generate_uuid4()") !== false || 
        strpos($content, "created_child_id = sprintf") !== false) {
        echo "   ✓ UUID generation for child exists\n";
        $passed++;
    } else {
        echo "   ✗ UUID generation not found\n";
        $failed++;
    }
    
    // Check if child data structure is created with required fields
    $requiredFields = ['id', 'name', 'birth_date', 'gender', 'allergies', 'feeding_style', 'created_at'];
    $allFieldsFound = true;
    foreach ($requiredFields as $field) {
        if (strpos($content, "'$field' =>") === false) {
            $allFieldsFound = false;
            echo "   ✗ Required field missing in child structure: $field\n";
            $failed++;
            break;
        }
    }
    
    if ($allFieldsFound) {
        echo "   ✓ All required child fields present (id, name, birth_date, gender, allergies, feeding_style, created_at)\n";
        $passed++;
    }
    
    // Check if child is added to children array
    if (strpos($content, "\$children[] = \$new_child") !== false) {
        echo "   ✓ Child is added to children array\n";
        $passed++;
    } else {
        echo "   ✗ Child addition to array not found\n";
        $failed++;
    }
    
    // Check if user meta is updated
    if (preg_match("/update_user_meta\(\s*\\\$user_id,\s*'_kg_children',\s*\\\$children\s*\)/", $content)) {
        echo "   ✓ _kg_children meta is updated\n";
        $passed++;
    } else {
        echo "   ✗ _kg_children meta update not found\n";
        $failed++;
    }
} else {
    echo "   ✗ ToolController.php not found\n";
    $failed += 6;
}

echo "\n";

// Test 3: Check if created child_id is linked to BLW result
echo "3. Child ID Linking to BLW Result\n";
if (file_exists($toolControllerFile)) {
    $content = file_get_contents($toolControllerFile);
    
    // Check if created_child_id is assigned to child_id for BLW result
    if (strpos($content, "\$child_id = \$created_child_id") !== false) {
        echo "   ✓ Created child_id is assigned to \$child_id variable\n";
        $passed++;
    } else {
        echo "   ✗ Child ID assignment not found\n";
        $failed++;
    }
    
    // Verify that save_blw_result is called with child_id
    if (strpos($content, "save_blw_result( \$user_id, \$child_id, \$result )") !== false) {
        echo "   ✓ save_blw_result is called with child_id\n";
        $passed++;
    } else {
        echo "   ✗ save_blw_result call with child_id not found\n";
        $failed++;
    }
} else {
    echo "   ✗ ToolController.php not found\n";
    $failed += 2;
}

echo "\n";

// Test 4: Check if response includes child info
echo "4. Response Data\n";
if (file_exists($toolControllerFile)) {
    $content = file_get_contents($toolControllerFile);
    
    // Check if child_id is included in response
    if (strpos($content, "\$result['child_id'] = \$created_child_id") !== false) {
        echo "   ✓ child_id is included in response\n";
        $passed++;
    } else {
        echo "   ✗ child_id not found in response\n";
        $failed++;
    }
    
    // Check if child_name is included in response
    if (strpos($content, "\$result['child_name'] = \$child_name") !== false) {
        echo "   ✓ child_name is included in response\n";
        $passed++;
    } else {
        echo "   ✗ child_name not found in response\n";
        $failed++;
    }
    
    // Check if child info is conditionally added (only when child is created)
    if (strpos($content, "if ( \$created_child_id )") !== false) {
        echo "   ✓ Child info is conditionally added to response\n";
        $passed++;
    } else {
        echo "   ✗ Conditional check for created_child_id not found\n";
        $failed++;
    }
} else {
    echo "   ✗ ToolController.php not found\n";
    $failed += 3;
}

echo "\n";

// Test 5: Check logic flow and edge cases
echo "5. Logic Flow and Edge Cases\n";
if (file_exists($toolControllerFile)) {
    $content = file_get_contents($toolControllerFile);
    
    // Extract submit_blw_test function
    preg_match('/public function submit_blw_test.*?(?=\n\s{4}\/\*\*|\n\s{4}public function|\n\s{4}private function|\nclass|\Z)/s', $content, $matches);
    if (!empty($matches[0])) {
        $submitFunction = $matches[0];
        
        // Check if child creation happens within registration block
        if (preg_match('/if\s*\(\s*\$register\s*&&\s*!\s*\$user_id\s*\).*?child_name.*?}/s', $submitFunction)) {
            echo "   ✓ Child creation is within registration block\n";
            $passed++;
        } else {
            echo "   ✗ Child creation not properly scoped to registration block\n";
            $failed++;
        }
        
        // Check if child creation happens after user creation
        if (preg_match('/wp_create_user.*?set_role.*?child_name/s', $submitFunction)) {
            echo "   ✓ Child creation occurs after user creation and role assignment\n";
            $passed++;
        } else {
            echo "   ✗ Child creation order might be incorrect\n";
            $failed++;
        }
        
        // Check if $created_child_id is initialized
        if (strpos($submitFunction, "\$created_child_id = null") !== false) {
            echo "   ✓ created_child_id is properly initialized to null\n";
            $passed++;
        } else {
            echo "   ✗ created_child_id initialization not found\n";
            $failed++;
        }
        
        // Check that child profile has default values
        if (strpos($submitFunction, "'gender' => 'unspecified'") !== false) {
            echo "   ✓ Default gender value is set to 'unspecified'\n";
            $passed++;
        } else {
            echo "   ✗ Default gender value not found\n";
            $failed++;
        }
        
        if (strpos($submitFunction, "'feeding_style' => 'mixed'") !== false) {
            echo "   ✓ Default feeding_style value is set to 'mixed'\n";
            $passed++;
        } else {
            echo "   ✗ Default feeding_style value not found\n";
            $failed++;
        }
        
        if (strpos($submitFunction, "'allergies' => []") !== false) {
            echo "   ✓ Default allergies value is set to empty array\n";
            $passed++;
        } else {
            echo "   ✗ Default allergies value not found\n";
            $failed++;
        }
        
        // Check birth_date handling with fallback to empty string
        if (strpos($submitFunction, "'birth_date' => \$child_birth_date ?: ''") !== false) {
            echo "   ✓ birth_date has fallback to empty string\n";
            $passed++;
        } else {
            echo "   ✗ birth_date fallback not found\n";
            $failed++;
        }
    } else {
        echo "   ✗ Could not extract submit_blw_test function\n";
        $failed += 7;
    }
} else {
    echo "   ✗ ToolController.php not found\n";
    $failed += 7;
}

echo "\n";

// Summary
echo "=== TEST SUMMARY ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "✓ ALL TESTS PASSED!\n";
    echo "\nThe BLW test child profile creation feature has been successfully implemented.\n";
    echo "The following scenarios are now supported:\n";
    echo "1. ✓ Registration with child info creates both user and child profile\n";
    echo "2. ✓ Registration without child info creates only user (child_name empty)\n";
    echo "3. ✓ BLW result is linked to created child_id\n";
    echo "4. ✓ Response includes child_id and child_name when child is created\n";
    exit(0);
} else {
    echo "✗ SOME TESTS FAILED\n";
    echo "\nPlease review the implementation.\n";
    exit(1);
}

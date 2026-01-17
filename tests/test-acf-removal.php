<?php
/**
 * Test for ACF Removal from Tool Post Type
 * 
 * This test verifies:
 * 1. Tool.php no longer uses ACF functions
 * 2. Native WordPress meta box is implemented
 * 3. ToolSeeder no longer uses ACF functions
 * 4. ToolController uses get_post_meta() directly
 */

echo "=== ACF Removal from Tool Post Type Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Tool.php - ACF Dependencies Removed
echo "1. Tool.php - ACF Dependencies Removed\n";
$toolFile = $baseDir . '/includes/PostTypes/Tool.php';
if (file_exists($toolFile)) {
    $content = file_get_contents($toolFile);
    
    // Check that ACF init hook is removed
    if (strpos($content, "add_action( 'acf/init'") === false) {
        echo "   ✓ ACF init hook removed\n";
        $passed++;
    } else {
        echo "   ✗ ACF init hook still exists\n";
        $failed++;
    }
    
    // Check that register_acf_fields method is removed
    if (strpos($content, 'register_acf_fields()') === false && 
        strpos($content, 'function register_acf_fields(') === false) {
        echo "   ✓ register_acf_fields() method removed\n";
        $passed++;
    } else {
        echo "   ✗ register_acf_fields() method still exists\n";
        $failed++;
    }
    
    // Check that acf_add_local_field_group is not used
    if (strpos($content, 'acf_add_local_field_group') === false) {
        echo "   ✓ acf_add_local_field_group not used\n";
        $passed++;
    } else {
        echo "   ✗ acf_add_local_field_group still used\n";
        $failed++;
    }
    
    // Check that get_field is not used
    if (strpos($content, "get_field('is_active'") === false) {
        echo "   ✓ get_field() not used in render_custom_columns\n";
        $passed++;
    } else {
        echo "   ✗ get_field() still used in render_custom_columns\n";
        $failed++;
    }
    
    // Check that get_post_meta is used instead
    if (strpos($content, "get_post_meta( \$post_id, '_kg_is_active'") !== false ||
        strpos($content, "get_post_meta(\$post_id, '_kg_is_active'") !== false) {
        echo "   ✓ get_post_meta() used for is_active field\n";
        $passed++;
    } else {
        echo "   ✗ get_post_meta() not used for is_active field\n";
        $failed++;
    }
} else {
    echo "   ✗ Tool.php file not found\n";
    $failed++;
}

// Test 2: Tool.php - Native Meta Box Implemented
echo "\n2. Tool.php - Native Meta Box Implemented\n";
if (file_exists($toolFile)) {
    $content = file_get_contents($toolFile);
    
    // Check for add_meta_boxes hook
    if (strpos($content, "add_action( 'add_meta_boxes'") !== false) {
        echo "   ✓ add_meta_boxes hook added\n";
        $passed++;
    } else {
        echo "   ✗ add_meta_boxes hook not found\n";
        $failed++;
    }
    
    // Check for save_post hook
    if (strpos($content, "add_action( 'save_post'") !== false) {
        echo "   ✓ save_post hook added\n";
        $passed++;
    } else {
        echo "   ✗ save_post hook not found\n";
        $failed++;
    }
    
    // Check for add_tool_meta_box method
    if (strpos($content, 'function add_tool_meta_box()') !== false) {
        echo "   ✓ add_tool_meta_box() method exists\n";
        $passed++;
    } else {
        echo "   ✗ add_tool_meta_box() method not found\n";
        $failed++;
    }
    
    // Check for render_tool_meta_box method
    if (strpos($content, 'function render_tool_meta_box(') !== false) {
        echo "   ✓ render_tool_meta_box() method exists\n";
        $passed++;
    } else {
        echo "   ✗ render_tool_meta_box() method not found\n";
        $failed++;
    }
    
    // Check for save_tool_meta method
    if (strpos($content, 'function save_tool_meta(') !== false) {
        echo "   ✓ save_tool_meta() method exists\n";
        $passed++;
    } else {
        echo "   ✗ save_tool_meta() method not found\n";
        $failed++;
    }
    
    // Check for nonce field in meta box
    if (strpos($content, 'wp_nonce_field') !== false) {
        echo "   ✓ Nonce field added for security\n";
        $passed++;
    } else {
        echo "   ✗ Nonce field not found\n";
        $failed++;
    }
    
    // Check for meta field form elements
    if (strpos($content, 'kg_tool_type') !== false &&
        strpos($content, 'kg_tool_icon') !== false &&
        strpos($content, 'kg_is_active') !== false &&
        strpos($content, 'kg_requires_auth') !== false) {
        echo "   ✓ All required meta fields present in form\n";
        $passed++;
    } else {
        echo "   ✗ Some meta fields missing from form\n";
        $failed++;
    }
}

// Test 3: ToolSeeder.php - ACF Calls Removed
echo "\n3. ToolSeeder.php - ACF Calls Removed\n";
$seederFile = $baseDir . '/includes/Admin/ToolSeeder.php';
if (file_exists($seederFile)) {
    $content = file_get_contents($seederFile);
    
    // Check that update_field is not used
    if (strpos($content, "update_field('tool_type'") === false &&
        strpos($content, "update_field('tool_icon'") === false &&
        strpos($content, "update_field('is_active'") === false &&
        strpos($content, "update_field('requires_auth'") === false) {
        echo "   ✓ ACF update_field() calls removed\n";
        $passed++;
    } else {
        echo "   ✗ ACF update_field() calls still present\n";
        $failed++;
    }
    
    // Check that function_exists check is removed
    if (strpos($content, "function_exists('update_field')") === false) {
        echo "   ✓ ACF function_exists check removed\n";
        $passed++;
    } else {
        echo "   ✗ ACF function_exists check still present\n";
        $failed++;
    }
    
    // Check that update_post_meta is still used
    if (strpos($content, "update_post_meta(\$result, '_kg_tool_type'") !== false) {
        echo "   ✓ update_post_meta() used for tool metadata\n";
        $passed++;
    } else {
        echo "   ✗ update_post_meta() not found for tool metadata\n";
        $failed++;
    }
} else {
    echo "   ✗ ToolSeeder.php file not found\n";
    $failed++;
}

// Test 4: ToolController.php - Direct get_post_meta Usage
echo "\n4. ToolController.php - Direct get_post_meta Usage\n";
$controllerFile = $baseDir . '/includes/API/ToolController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check that get_tool_field helper exists
    if (strpos($content, 'private function get_tool_field(') !== false) {
        echo "   ✓ get_tool_field() helper method exists\n";
        $passed++;
    } else {
        echo "   ✗ get_tool_field() helper method not found\n";
        $failed++;
    }
    
    // Check that ACF check is removed from helper
    if (strpos($content, "function_exists( 'get_field' )") === false) {
        echo "   ✓ ACF check removed from get_tool_field()\n";
        $passed++;
    } else {
        echo "   ✗ ACF check still present in get_tool_field()\n";
        $failed++;
    }
    
    // Check that helper uses get_post_meta directly
    if (strpos($content, "get_post_meta( \$post_id, '_kg_'") !== false) {
        echo "   ✓ Helper uses get_post_meta() with _kg_ prefix\n";
        $passed++;
    } else {
        echo "   ✗ Helper doesn't use get_post_meta() properly\n";
        $failed++;
    }
    
    // Check for backward compatibility fallback
    if (preg_match('/get_post_meta.*\$field_name.*true/', $content)) {
        echo "   ✓ Backward compatibility fallback present\n";
        $passed++;
    } else {
        echo "   ✗ Backward compatibility fallback missing\n";
        $failed++;
    }
} else {
    echo "   ✗ ToolController.php file not found\n";
    $failed++;
}

// Test 5: Meta Key Consistency
echo "\n5. Meta Key Consistency Check\n";
if (file_exists($toolFile) && file_exists($seederFile) && file_exists($controllerFile)) {
    $toolContent = file_get_contents($toolFile);
    $seederContent = file_get_contents($seederFile);
    $controllerContent = file_get_contents($controllerFile);
    
    $requiredMetaKeys = [
        '_kg_tool_type',
        '_kg_tool_icon',
        '_kg_is_active',
        '_kg_requires_auth'
    ];
    
    $allConsistent = true;
    foreach ($requiredMetaKeys as $metaKey) {
        $inTool = strpos($toolContent, $metaKey) !== false;
        $inSeeder = strpos($seederContent, $metaKey) !== false;
        
        if ($inTool && $inSeeder) {
            echo "   ✓ Meta key $metaKey consistent across files\n";
            $passed++;
        } else {
            echo "   ✗ Meta key $metaKey not consistent (Tool: " . ($inTool ? 'Yes' : 'No') . ", Seeder: " . ($inSeeder ? 'Yes' : 'No') . ")\n";
            $failed++;
            $allConsistent = false;
        }
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "Test Summary:\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✅ All tests passed! ACF has been successfully removed.\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed. Please review the issues above.\n";
    exit(1);
}

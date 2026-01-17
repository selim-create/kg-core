<?php
/**
 * Test for ToolController ACF get_field() Fix
 * 
 * This test verifies:
 * 1. ToolController no longer uses ACF's get_field() function directly
 * 2. Uses helper method get_tool_field() with proper fallback
 * 3. Helper method checks for ACF availability before using it
 * 4. All get_field() calls have been replaced
 */

echo "=== ToolController ACF get_field() Fix Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check that helper method exists
echo "1. Helper Method - get_tool_field() Existence\n";
$controllerFile = $baseDir . '/includes/API/ToolController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check that get_tool_field method exists
    if (strpos($content, 'private function get_tool_field(') !== false) {
        echo "   ✓ get_tool_field() helper method exists\n";
        $passed++;
    } else {
        echo "   ✗ get_tool_field() helper method not found\n";
        $failed++;
    }
    
    // Check that helper has ACF check
    if (strpos($content, "function_exists( 'get_field' )") !== false) {
        echo "   ✓ Helper checks for ACF availability\n";
        $passed++;
    } else {
        echo "   ✗ Helper doesn't check for ACF availability\n";
        $failed++;
    }
    
    // Check that helper falls back to get_post_meta
    if (strpos($content, "get_post_meta( \$post_id, '_kg_'") !== false) {
        echo "   ✓ Helper falls back to get_post_meta with _kg_ prefix\n";
        $passed++;
    } else {
        echo "   ✗ Helper doesn't fall back to get_post_meta properly\n";
        $failed++;
    }
    
} else {
    echo "   ✗ ToolController.php file not found\n";
    $failed += 3;
}

// Test 2: Check that direct get_field() calls have been replaced in get_tools()
echo "\n2. get_tools() Method - No Direct ACF Calls\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Extract get_tools method
    if (preg_match('/public function get_tools\(.*?\{(.*?)\n\s{4}\}/s', $content, $matches)) {
        $methodContent = $matches[1];
        
        // Check no direct get_field calls (except in helper)
        $directGetFieldCount = substr_count($methodContent, "get_field( '");
        if ($directGetFieldCount === 0) {
            echo "   ✓ No direct get_field() calls in get_tools()\n";
            $passed++;
        } else {
            echo "   ✗ Found $directGetFieldCount direct get_field() calls\n";
            $failed++;
        }
        
        // Check uses get_tool_field
        if (strpos($methodContent, "\$this->get_tool_field( 'is_active'") !== false) {
            echo "   ✓ Uses get_tool_field() for is_active\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() for is_active\n";
            $failed++;
        }
        
        if (strpos($methodContent, "\$this->get_tool_field( 'tool_type'") !== false) {
            echo "   ✓ Uses get_tool_field() for tool_type\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() for tool_type\n";
            $failed++;
        }
        
        if (strpos($methodContent, "\$this->get_tool_field( 'tool_icon'") !== false) {
            echo "   ✓ Uses get_tool_field() for tool_icon\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() for tool_icon\n";
            $failed++;
        }
        
        if (strpos($methodContent, "\$this->get_tool_field( 'requires_auth'") !== false) {
            echo "   ✓ Uses get_tool_field() for requires_auth\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() for requires_auth\n";
            $failed++;
        }
        
    } else {
        echo "   ✗ get_tools() method not found\n";
        $failed += 5;
    }
    
} else {
    $failed += 5;
}

// Test 3: Check that direct get_field() calls have been replaced in get_tool()
echo "\n3. get_tool() Method - No Direct ACF Calls\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Extract get_tool method
    if (preg_match('/public function get_tool\(.*?\{(.*?)\n\s{4}\}/s', $content, $matches)) {
        $methodContent = $matches[1];
        
        // Check no direct get_field calls
        $directGetFieldCount = substr_count($methodContent, "get_field( '");
        if ($directGetFieldCount === 0) {
            echo "   ✓ No direct get_field() calls in get_tool()\n";
            $passed++;
        } else {
            echo "   ✗ Found $directGetFieldCount direct get_field() calls\n";
            $failed++;
        }
        
        // Check uses get_tool_field
        if (strpos($methodContent, "\$this->get_tool_field(") !== false) {
            echo "   ✓ Uses get_tool_field() helper method\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() helper method\n";
            $failed++;
        }
        
    } else {
        echo "   ✗ get_tool() method not found\n";
        $failed += 2;
    }
    
} else {
    $failed += 2;
}

// Test 4: Check that direct get_field() calls have been replaced in get_blw_test_config()
echo "\n4. get_blw_test_config() Method - No Direct ACF Calls\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Extract get_blw_test_config method
    if (preg_match('/public function get_blw_test_config\(.*?\{(.*?)public function/s', $content, $matches)) {
        $methodContent = $matches[1];
        
        // Check no direct get_field calls
        $directGetFieldCount = substr_count($methodContent, "get_field( '");
        if ($directGetFieldCount === 0) {
            echo "   ✓ No direct get_field() calls in get_blw_test_config()\n";
            $passed++;
        } else {
            echo "   ✗ Found $directGetFieldCount direct get_field() calls\n";
            $failed++;
        }
        
        // Check uses get_tool_field for BLW fields
        if (strpos($methodContent, "\$this->get_tool_field( 'blw_questions'") !== false) {
            echo "   ✓ Uses get_tool_field() for blw_questions\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() for blw_questions\n";
            $failed++;
        }
        
        if (strpos($methodContent, "\$this->get_tool_field( 'result_buckets'") !== false) {
            echo "   ✓ Uses get_tool_field() for result_buckets\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() for result_buckets\n";
            $failed++;
        }
        
        if (strpos($methodContent, "\$this->get_tool_field( 'disclaimer_text'") !== false) {
            echo "   ✓ Uses get_tool_field() for disclaimer_text\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() for disclaimer_text\n";
            $failed++;
        }
        
        if (strpos($methodContent, "\$this->get_tool_field( 'emergency_text'") !== false) {
            echo "   ✓ Uses get_tool_field() for emergency_text\n";
            $passed++;
        } else {
            echo "   ✗ Doesn't use get_tool_field() for emergency_text\n";
            $failed++;
        }
        
    } else {
        echo "   ✗ get_blw_test_config() method not found\n";
        $failed += 5;
    }
    
} else {
    $failed += 5;
}

// Test 5: PHP Syntax Check
echo "\n5. PHP Syntax Check\n";
if (file_exists($controllerFile)) {
    exec("php -l " . escapeshellarg($controllerFile) . " 2>&1", $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✓ ToolController.php has no syntax errors\n";
        $passed++;
    } else {
        echo "   ✗ ToolController.php has syntax errors:\n";
        echo "     " . implode("\n     ", $output) . "\n";
        $failed++;
    }
} else {
    $failed++;
}

// Test 6: Consistency Check - Verify meta keys match ToolSeeder
echo "\n6. Consistency Check - Meta Keys Match ToolSeeder\n";
$seederFile = $baseDir . '/includes/Admin/ToolSeeder.php';
if (file_exists($seederFile)) {
    $seederContent = file_get_contents($seederFile);
    
    // Check if ToolSeeder uses the same meta keys
    $metaKeys = ['_kg_tool_type', '_kg_tool_icon', '_kg_is_active', '_kg_requires_auth'];
    $allKeysFound = true;
    
    foreach ($metaKeys as $key) {
        if (strpos($seederContent, "'$key'") === false) {
            echo "   ✗ ToolSeeder doesn't use '$key' meta key\n";
            $allKeysFound = false;
            $failed++;
        }
    }
    
    if ($allKeysFound) {
        echo "   ✓ All meta keys match between ToolController and ToolSeeder\n";
        $passed++;
    }
    
} else {
    echo "   ⚠ ToolSeeder.php file not found (skipping consistency check)\n";
}

// Test 7: Count total get_field usage (should only be in helper)
echo "\n7. Global Check - get_field() Only in Helper\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Count all get_field occurrences
    $totalGetFieldCount = substr_count($content, 'get_field(');
    
    if ($totalGetFieldCount === 1) {
        echo "   ✓ Only 1 get_field() call found (in helper method)\n";
        $passed++;
    } else {
        echo "   ✗ Found $totalGetFieldCount get_field() calls (expected 1)\n";
        $failed++;
    }
    
    // Verify it's protected by function_exists check
    if (strpos($content, "function_exists( 'get_field' )") !== false) {
        echo "   ✓ get_field() call is protected by function_exists check\n";
        $passed++;
    } else {
        echo "   ✗ get_field() call is not properly protected\n";
        $failed++;
    }
    
} else {
    $failed += 2;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed > 0) {
    echo "\nResult: FAILED ❌\n";
    echo "\nPlease review the failures above.\n";
    exit(1);
} else {
    echo "\nResult: PASSED ✅\n";
    echo "\nACF get_field() fix has been successfully implemented!\n";
    echo "\nAll endpoints should now work without requiring ACF plugin:\n";
    echo "  GET /wp-json/kg/v1/tools\n";
    echo "  GET /wp-json/kg/v1/tools/{slug}\n";
    echo "  GET /wp-json/kg/v1/tools/blw-test/config\n";
    exit(0);
}

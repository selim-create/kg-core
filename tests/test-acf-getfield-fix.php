<?php
/**
 * Test for ACF get_field() Fix
 * 
 * This test verifies:
 * 1. SponsoredToolController no longer uses ACF's get_field() function
 * 2. Uses WordPress native get_post_meta() instead
 * 3. Has fallback value 'fa-bath' when meta is empty
 */

echo "=== ACF get_field() Fix Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check that get_field() is NOT used for tool_icon
echo "1. SponsoredToolController - No ACF get_field() Usage\n";
$controllerFile = $baseDir . '/includes/API/SponsoredToolController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check that get_field( 'tool_icon' is NOT present
    if (strpos($content, "get_field( 'tool_icon'") === false) {
        echo "   ✓ ACF get_field('tool_icon') is NOT used (good!)\n";
        $passed++;
    } else {
        echo "   ✗ ACF get_field('tool_icon') is still being used\n";
        $failed++;
    }
    
    // Check that get_field is not used anywhere in the context of tool icon
    if (strpos($content, "get_field( 'tool_icon', \$tool->ID )") === false) {
        echo "   ✓ ACF get_field() for tool icon fully removed\n";
        $passed++;
    } else {
        echo "   ✗ ACF get_field() for tool icon still exists\n";
        $failed++;
    }
    
} else {
    echo "   ✗ SponsoredToolController.php file not found\n";
    $failed += 2;
}

// Test 2: Check that get_post_meta() is used correctly
echo "\n2. WordPress Native get_post_meta() Usage\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for get_post_meta with _kg_tool_icon
    if (strpos($content, "get_post_meta( \$tool->ID, '_kg_tool_icon', true )") !== false) {
        echo "   ✓ get_post_meta() with correct parameters is used\n";
        $passed++;
    } else {
        echo "   ✗ get_post_meta() with correct parameters not found\n";
        $failed++;
    }
    
    // Check for the meta key name
    if (strpos($content, "'_kg_tool_icon'") !== false) {
        echo "   ✓ Correct meta key '_kg_tool_icon' is used\n";
        $passed++;
    } else {
        echo "   ✗ Meta key '_kg_tool_icon' not found\n";
        $failed++;
    }
    
    // Check for single value retrieval (true parameter)
    if (preg_match("/get_post_meta\(\s*\\\$tool->ID,\s*'_kg_tool_icon',\s*true\s*\)/", $content)) {
        echo "   ✓ Single value retrieval (true parameter) is used\n";
        $passed++;
    } else {
        echo "   ✗ Single value retrieval (true parameter) not found\n";
        $failed++;
    }
    
} else {
    $failed += 3;
}

// Test 3: Check for fallback value
echo "\n3. Fallback Value 'fa-bath'\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for the ?: operator with fa-bath fallback
    if (strpos($content, "?: 'fa-bath'") !== false) {
        echo "   ✓ Fallback value 'fa-bath' exists\n";
        $passed++;
    } else {
        echo "   ✗ Fallback value 'fa-bath' not found\n";
        $failed++;
    }
    
    // Check the complete expression
    if (preg_match("/get_post_meta\(\s*\\\$tool->ID,\s*'_kg_tool_icon',\s*true\s*\)\s*\?:\s*'fa-bath'/", $content)) {
        echo "   ✓ Complete expression with fallback is correct\n";
        $passed++;
    } else {
        echo "   ✗ Complete expression with fallback is incorrect\n";
        $failed++;
    }
    
} else {
    $failed += 2;
}

// Test 4: Check location in get_bath_planner_config method
echo "\n4. Correct Location - get_bath_planner_config Method\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Extract the get_bath_planner_config method
    if (preg_match('/public function get_bath_planner_config.*?\{(.*?)\n\s{4}\}/s', $content, $matches)) {
        $methodContent = $matches[1];
        
        // Check if the fix is in this method
        if (strpos($methodContent, "get_post_meta( \$tool->ID, '_kg_tool_icon', true )") !== false) {
            echo "   ✓ Fix is in get_bath_planner_config method\n";
            $passed++;
        } else {
            echo "   ✗ Fix not found in get_bath_planner_config method\n";
            $failed++;
        }
        
        // Check if it's in the tool_info array
        if (preg_match("/'tool_info'\s*=>\s*\[(.*?)\]/s", $methodContent, $toolInfoMatches)) {
            if (strpos($toolInfoMatches[1], "'icon' =>") !== false) {
                echo "   ✓ Icon is in tool_info array\n";
                $passed++;
            } else {
                echo "   ✗ Icon not found in tool_info array\n";
                $failed++;
            }
        } else {
            echo "   ✗ tool_info array not found\n";
            $failed++;
        }
        
    } else {
        echo "   ✗ get_bath_planner_config method not found\n";
        $failed += 2;
    }
    
} else {
    $failed += 2;
}

// Test 5: PHP Syntax Check
echo "\n5. PHP Syntax Check\n";
if (file_exists($controllerFile)) {
    exec("php -l " . escapeshellarg($controllerFile) . " 2>&1", $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✓ SponsoredToolController.php has no syntax errors\n";
        $passed++;
    } else {
        echo "   ✗ SponsoredToolController.php has syntax errors:\n";
        echo "     " . implode("\n     ", $output) . "\n";
        $failed++;
    }
} else {
    $failed++;
}

// Test 6: Consistency Check - Verify meta key matches ToolSeeder
echo "\n6. Consistency Check - Meta Key Matches ToolSeeder\n";
$seederFile = $baseDir . '/includes/Admin/ToolSeeder.php';
if (file_exists($seederFile)) {
    $seederContent = file_get_contents($seederFile);
    
    // Check if ToolSeeder uses the same meta key
    if (strpos($seederContent, "'_kg_tool_icon'") !== false) {
        echo "   ✓ ToolSeeder uses '_kg_tool_icon' meta key\n";
        $passed++;
    } else {
        echo "   ✗ ToolSeeder doesn't use '_kg_tool_icon' meta key\n";
        $failed++;
    }
    
    // Check if ToolSeeder sets this meta field
    if (strpos($seederContent, "update_post_meta(\$result, '_kg_tool_icon'") !== false) {
        echo "   ✓ ToolSeeder sets '_kg_tool_icon' meta field\n";
        $passed++;
    } else {
        echo "   ✗ ToolSeeder doesn't set '_kg_tool_icon' meta field\n";
        $failed++;
    }
    
} else {
    echo "   ⚠ ToolSeeder.php file not found (skipping consistency check)\n";
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
    echo "\nThe endpoint should now work without requiring ACF plugin:\n";
    echo "  GET /wp-json/kg/v1/tools/bath-planner/config\n";
    exit(0);
}

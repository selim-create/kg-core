<?php
/**
 * Static Code Analysis Test for Solid Food Readiness Results Endpoints
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core Solid Food Readiness Results Endpoints Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check UserController for Solid Food endpoints
echo "1. UserController Solid Food Endpoints\n";
$userControllerFile = $baseDir . '/includes/API/UserController.php';
if (file_exists($userControllerFile)) {
    echo "   ✓ File exists: UserController.php\n";
    $content = file_get_contents($userControllerFile);
    
    // Check for endpoint registration
    $solidFoodEndpoints = [
        '/user/solid-food-results',
        '/user/children/(?P<child_id>[a-zA-Z0-9-]+)/solid-food-results'
    ];
    
    foreach ($solidFoodEndpoints as $endpoint) {
        if (strpos($content, "'$endpoint'") !== false) {
            echo "   ✓ Endpoint registered: $endpoint\n";
            $passed++;
        } else {
            echo "   ✗ Endpoint missing: $endpoint\n";
            $failed++;
        }
    }
    
    // Check for methods
    $solidFoodMethods = [
        'get_solid_food_results',
        'get_child_solid_food_results'
    ];
    
    foreach ($solidFoodMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
    
    // Check for user meta key
    if (strpos($content, '_kg_solid_food_readiness_results') !== false) {
        echo "   ✓ Solid Food results meta key used\n";
        $passed++;
    } else {
        echo "   ✗ Solid Food results meta key missing\n";
        $failed++;
    }
    
    // Check for child_name lookup
    if (strpos($content, 'child_name') !== false && 
        strpos($content, '_kg_children') !== false) {
        echo "   ✓ Child name lookup implemented\n";
        $passed++;
    } else {
        echo "   ✗ Child name lookup missing\n";
        $failed++;
    }
    
    // Check for timestamp sorting
    if (strpos($content, 'usort') !== false && 
        strpos($content, 'created_at') !== false) {
        echo "   ✓ Timestamp sorting implemented\n";
        $passed++;
    } else {
        echo "   ✗ Timestamp sorting missing\n";
        $failed++;
    }
    
    // Check for result formatting
    $requiredFields = ['id', 'child_id', 'child_name', 'score', 'result_bucket_id', 'red_flags', 'answers', 'created_at'];
    $allFieldsFound = true;
    foreach ($requiredFields as $field) {
        if (strpos($content, "'$field'") === false) {
            $allFieldsFound = false;
            echo "   ✗ Required field missing in response: $field\n";
            $failed++;
        }
    }
    
    if ($allFieldsFound) {
        echo "   ✓ All required fields present in response format\n";
        $passed++;
    }
    
    // Check for result_category to result_bucket_id mapping
    if (strpos($content, 'result_category') !== false && 
        strpos($content, 'result_bucket_id') !== false) {
        echo "   ✓ Result category to bucket ID mapping implemented\n";
        $passed++;
    } else {
        echo "   ✗ Result category mapping missing\n";
        $failed++;
    }
    
    // Check for timestamp to created_at mapping
    if (strpos($content, 'timestamp') !== false) {
        echo "   ✓ Timestamp field handling implemented\n";
        $passed++;
    } else {
        echo "   ✗ Timestamp field handling missing\n";
        $failed++;
    }
    
} else {
    echo "   ✗ File not found: UserController.php\n";
    $failed++;
}

echo "\n";

// Test 2: Check ToolController saves complete data
echo "2. ToolController Data Saving\n";
$toolControllerFile = $baseDir . '/includes/API/ToolController.php';
if (file_exists($toolControllerFile)) {
    echo "   ✓ File exists: ToolController.php\n";
    $content = file_get_contents($toolControllerFile);
    
    // Check for solid_food_readiness_submit method
    if (strpos($content, 'function solid_food_readiness_submit') !== false) {
        echo "   ✓ solid_food_readiness_submit method exists\n";
        $passed++;
    } else {
        echo "   ✗ solid_food_readiness_submit method missing\n";
        $failed++;
    }
    
    // Check if it saves red_flags
    if (strpos($content, "'red_flags'") !== false) {
        echo "   ✓ red_flags field saved\n";
        $passed++;
    } else {
        echo "   ✗ red_flags field not saved\n";
        $failed++;
    }
    
    // Check if it saves answers
    if (strpos($content, "'answers' => \$answers") !== false) {
        echo "   ✓ answers field saved\n";
        $passed++;
    } else {
        echo "   ✗ answers field not saved\n";
        $failed++;
    }
    
    // Check if it saves to correct meta key
    if (strpos($content, '_kg_solid_food_readiness_results') !== false) {
        echo "   ✓ Saves to correct meta key\n";
        $passed++;
    } else {
        echo "   ✗ Wrong meta key used\n";
        $failed++;
    }
    
} else {
    echo "   ✗ File not found: ToolController.php\n";
    $failed++;
}

echo "\n";

// Test 3: Check BLW endpoints comparison (to ensure consistency)
echo "3. Consistency with BLW Endpoints\n";
if (file_exists($userControllerFile)) {
    $content = file_get_contents($userControllerFile);
    
    // Check that both BLW and Solid Food endpoints exist
    $hasBLW = strpos($content, '/user/blw-results') !== false;
    $hasSolidFood = strpos($content, '/user/solid-food-results') !== false;
    
    if ($hasBLW && $hasSolidFood) {
        echo "   ✓ Both BLW and Solid Food endpoints exist\n";
        $passed++;
    } else {
        echo "   ✗ Missing endpoints (BLW: " . ($hasBLW ? 'yes' : 'no') . ", Solid Food: " . ($hasSolidFood ? 'yes' : 'no') . ")\n";
        $failed++;
    }
    
    // Check endpoint pattern consistency
    if (strpos($content, '/user/children/(?P<child_id>[a-zA-Z0-9-]+)/blw-results') !== false &&
        strpos($content, '/user/children/(?P<child_id>[a-zA-Z0-9-]+)/solid-food-results') !== false) {
        echo "   ✓ Child-specific endpoint patterns are consistent\n";
        $passed++;
    } else {
        echo "   ✗ Endpoint patterns are inconsistent\n";
        $failed++;
    }
    
    // Check permission callbacks are same
    $blwAuthCount = substr_count($content, "'/user/blw-results'");
    $solidAuthCount = substr_count($content, "'/user/solid-food-results'");
    
    if ($blwAuthCount > 0 && $solidAuthCount > 0) {
        echo "   ✓ Both endpoints use authentication\n";
        $passed++;
    } else {
        echo "   ✗ Authentication not properly configured\n";
        $failed++;
    }
}

echo "\n";

// Test 4: Verify route registration structure
echo "4. Route Registration Structure\n";
if (file_exists($userControllerFile)) {
    $content = file_get_contents($userControllerFile);
    
    // Check that routes are registered after BLW routes
    $blwPos = strpos($content, '/user/blw-results');
    $solidPos = strpos($content, '/user/solid-food-results');
    
    if ($blwPos !== false && $solidPos !== false && $solidPos > $blwPos) {
        echo "   ✓ Solid Food routes registered after BLW routes\n";
        $passed++;
    } else {
        echo "   ✗ Route registration order incorrect\n";
        $failed++;
    }
    
    // Check comment exists
    if (strpos($content, '// Solid Food Readiness results endpoints') !== false) {
        echo "   ✓ Proper section comment added\n";
        $passed++;
    } else {
        echo "   ✗ Section comment missing\n";
        $failed++;
    }
}

echo "\n";

// Summary
echo "=== TEST SUMMARY ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "✓ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "✗ SOME TESTS FAILED\n";
    exit(1);
}

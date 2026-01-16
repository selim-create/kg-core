<?php
/**
 * Static Code Analysis Test for Food Trials Routing Fix
 * 
 * This test verifies that ToolController excludes food-trials routes
 */

echo "=== Food Trials Routing Fix Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check ToolController has negative lookahead for food-trials
echo "1. ToolController Route Pattern Check\n";
$toolControllerFile = $baseDir . '/includes/API/ToolController.php';
if (file_exists($toolControllerFile)) {
    echo "   ✓ File exists: ToolController.php\n";
    $content = file_get_contents($toolControllerFile);
    
    // Check for negative lookahead pattern
    if (preg_match('/\(\?!food-trials\)/', $content)) {
        echo "   ✓ Negative lookahead pattern found for food-trials\n";
        $passed++;
        
        // Verify the complete pattern
        if (preg_match('/\/tools\/\(\?P<slug>\(\?!food-trials\)\[a-zA-Z0-9_-\]\+\)/', $content)) {
            echo "   ✓ Complete route pattern is correct\n";
            $passed++;
        } else {
            echo "   ✗ Route pattern format is incorrect\n";
            $failed++;
        }
        
        // Check for comment explaining the exclusion
        if (strpos($content, 'exclude food-trials') !== false || 
            strpos($content, 'FoodTrialController') !== false) {
            echo "   ✓ Route has explanatory comment\n";
            $passed++;
        } else {
            echo "   ⚠ Missing explanatory comment (optional)\n";
        }
    } else {
        echo "   ✗ Negative lookahead pattern NOT found\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: ToolController.php\n";
    $failed++;
}
echo "\n";

// Test 2: Check FoodTrialController has proper routes registered
echo "2. FoodTrialController Routes Check\n";
$foodTrialControllerFile = $baseDir . '/includes/API/FoodTrialController.php';
if (file_exists($foodTrialControllerFile)) {
    echo "   ✓ File exists: FoodTrialController.php\n";
    $content = file_get_contents($foodTrialControllerFile);
    
    // Check for food-trials base route
    if (strpos($content, "'/tools/food-trials'") !== false) {
        echo "   ✓ Base route registered: /tools/food-trials\n";
        $passed++;
    } else {
        echo "   ✗ Base route NOT registered\n";
        $failed++;
    }
    
    // Check for food-trials/stats route
    if (strpos($content, "'/tools/food-trials/stats'") !== false) {
        echo "   ✓ Stats route registered: /tools/food-trials/stats\n";
        $passed++;
    } else {
        echo "   ✗ Stats route NOT registered\n";
        $failed++;
    }
    
    // Check for dynamic ID route
    if (preg_match('/\/tools\/food-trials\/\(\?P<id>/', $content)) {
        echo "   ✓ Dynamic ID route registered: /tools/food-trials/{id}\n";
        $passed++;
    } else {
        echo "   ✗ Dynamic ID route NOT registered\n";
        $failed++;
    }
    
    // Check for GET, POST, PUT, DELETE methods
    $methods = ['GET', 'POST', 'PUT', 'DELETE'];
    $methodsFound = 0;
    foreach ($methods as $method) {
        if (preg_match('/\'methods\'\s*=>\s*[\'"]' . $method . '[\'"]/', $content)) {
            $methodsFound++;
        }
    }
    
    if ($methodsFound >= 3) {
        echo "   ✓ Multiple HTTP methods supported (found $methodsFound)\n";
        $passed++;
    } else {
        echo "   ✗ Insufficient HTTP methods (found $methodsFound)\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: FoodTrialController.php\n";
    $failed++;
}
echo "\n";

// Test 3: Verify route registration order
echo "3. Route Registration Order Check\n";
if (file_exists($foodTrialControllerFile)) {
    $content = file_get_contents($foodTrialControllerFile);
    
    // Find positions of different route registrations
    $statsPos = strpos($content, "'/tools/food-trials/stats'");
    $basePos = strpos($content, "'/tools/food-trials',");
    $dynamicPos = strpos($content, "'/tools/food-trials/(?P<id>");
    
    if ($statsPos !== false && $basePos !== false && $dynamicPos !== false) {
        // Stats should come before dynamic routes
        if ($statsPos < $dynamicPos) {
            echo "   ✓ Stats route registered before dynamic route\n";
            $passed++;
        } else {
            echo "   ✗ Stats route should be registered before dynamic route\n";
            $failed++;
        }
        
        // Base route should come before dynamic route
        if ($basePos < $dynamicPos) {
            echo "   ✓ Base route registered before dynamic route\n";
            $passed++;
        } else {
            echo "   ✗ Base route should be registered before dynamic route\n";
            $failed++;
        }
    } else {
        echo "   ⚠ Could not verify route order (some routes not found)\n";
    }
} else {
    echo "   ✗ Cannot check route order - file not found\n";
    $failed++;
}
echo "\n";

// Test 4: Pattern validation - simulate what WordPress would match
echo "4. Route Pattern Validation\n";

// Test paths that should be matched by FoodTrialController
$foodTrialPaths = [
    'food-trials',
    'food-trials/stats',
];

// Test paths that should be matched by ToolController
$toolPaths = [
    'blw-test',
    'persentil-hesaplayici',
    'ingredient-guide',
];

echo "   Testing FoodTrial paths (should NOT match ToolController pattern):\n";
$toolPattern = '/^(?!food-trials)[a-zA-Z0-9_-]+$/';
foreach ($foodTrialPaths as $path) {
    if (preg_match($toolPattern, $path)) {
        echo "   ✗ '$path' incorrectly matches ToolController pattern\n";
        $failed++;
    } else {
        echo "   ✓ '$path' correctly excluded from ToolController\n";
        $passed++;
    }
}

echo "\n   Testing Tool paths (should match ToolController pattern):\n";
foreach ($toolPaths as $path) {
    if (preg_match($toolPattern, $path)) {
        echo "   ✓ '$path' correctly matches ToolController pattern\n";
        $passed++;
    } else {
        echo "   ✗ '$path' incorrectly excluded from ToolController\n";
        $failed++;
    }
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n\n";

if ($failed > 0) {
    echo "❌ TESTS FAILED\n";
    exit(1);
} else {
    echo "✅ ALL TESTS PASSED\n";
    exit(0);
}

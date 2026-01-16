<?php
/**
 * Static Code Analysis Test for Food Trial Controller Fixes
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core Food Trial Controller Fixes Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check FoodTrialController file exists
echo "1. FoodTrialController File Check\n";
$foodTrialControllerFile = $baseDir . '/includes/API/FoodTrialController.php';
if (file_exists($foodTrialControllerFile)) {
    echo "   ✓ File exists: FoodTrialController.php\n";
    $content = file_get_contents($foodTrialControllerFile);
    $passed++;
} else {
    echo "   ✗ File not found: FoodTrialController.php\n";
    $failed++;
    exit(1);
}
echo "\n";

// Test 2: Check Route Registration Order
echo "2. Route Registration Order\n";

// Extract register_routes method
$routesPattern = '/function register_routes\(\).*?\n\s+\}/s';
if (preg_match($routesPattern, $content, $matches)) {
    $registerRoutesMethod = $matches[0];
    
    // Find positions of route registrations
    $statsPos = strpos($registerRoutesMethod, "'/tools/food-trials/stats'");
    $collectionGetPos = strpos($registerRoutesMethod, "'/tools/food-trials'");
    $dynamicIdPos = strpos($registerRoutesMethod, "'/tools/food-trials/(?P<id>");
    
    if ($statsPos !== false && $collectionGetPos !== false && $dynamicIdPos !== false) {
        // Stats should come BEFORE dynamic ID routes
        if ($statsPos < $dynamicIdPos) {
            echo "   ✓ /stats route registered before /{id} routes\n";
            $passed++;
        } else {
            echo "   ✗ /stats route should be before /{id} routes\n";
            $failed++;
        }
        
        // Collection routes should come before dynamic ID routes
        if ($collectionGetPos < $dynamicIdPos) {
            echo "   ✓ Collection routes registered before /{id} routes\n";
            $passed++;
        } else {
            echo "   ✗ Collection routes should be before /{id} routes\n";
            $failed++;
        }
    } else {
        echo "   ✗ Could not find all route registrations\n";
        $failed++;
    }
} else {
    echo "   ✗ Could not extract register_routes method\n";
    $failed++;
}
echo "\n";

// Test 3: Check Date Filtering Implementation
echo "3. Date Range Filtering\n";

// Check if get_food_trials method reads start_date and end_date
if (strpos($content, "get_param( 'start_date' )") !== false) {
    echo "   ✓ start_date parameter read\n";
    $passed++;
} else {
    echo "   ✗ start_date parameter not read\n";
    $failed++;
}

if (strpos($content, "get_param( 'end_date' )") !== false) {
    echo "   ✓ end_date parameter read\n";
    $passed++;
} else {
    echo "   ✗ end_date parameter not read\n";
    $failed++;
}

// Check for filter_trials_by_date_range method
if (strpos($content, 'function filter_trials_by_date_range') !== false) {
    echo "   ✓ Method exists: filter_trials_by_date_range()\n";
    $passed++;
    
    // Check if it uses strtotime
    if (strpos($content, 'strtotime') !== false) {
        echo "   ✓ Uses strtotime for date comparison\n";
        $passed++;
    } else {
        echo "   ✗ Missing strtotime for date comparison\n";
        $failed++;
    }
} else {
    echo "   ✗ Method missing: filter_trials_by_date_range()\n";
    $failed++;
}

// Check for mark_new_foods method
if (strpos($content, 'function mark_new_foods') !== false) {
    echo "   ✓ Method exists: mark_new_foods()\n";
    $passed++;
    
    // Check if it sets is_new flag
    if (preg_match("/\\['is_new'\\]/", $content)) {
        echo "   ✓ Sets is_new flag\n";
        $passed++;
    } else {
        echo "   ✗ Missing is_new flag\n";
        $failed++;
    }
} else {
    echo "   ✗ Method missing: mark_new_foods()\n";
    $failed++;
}
echo "\n";

// Test 4: Check Optional Ingredient ID
echo "4. Optional Ingredient ID Implementation\n";

// Check if ingredient_id validation is optional
if (preg_match('/if\s*\(\s*empty\s*\(\s*\$child_id\s*\)\s*\|\|\s*empty\s*\(\s*\$trial_date\s*\)\s*\|\|\s*empty\s*\(\s*\$result\s*\)\s*\)/', $content)) {
    echo "   ✓ ingredient_id is optional in validation\n";
    $passed++;
} else {
    echo "   ✗ ingredient_id should be optional\n";
    $failed++;
}

// Check if ingredient_name is accepted
if (strpos($content, "get_param( 'ingredient_name' )") !== false) {
    echo "   ✓ ingredient_name parameter accepted\n";
    $passed++;
} else {
    echo "   ✗ ingredient_name parameter not accepted\n";
    $failed++;
}

// Check for final_ingredient_name logic
if (strpos($content, 'final_ingredient_name') !== false) {
    echo "   ✓ Uses final_ingredient_name logic\n";
    $passed++;
} else {
    echo "   ✗ Missing final_ingredient_name logic\n";
    $failed++;
}

// Check for map_result_to_reaction method
if (strpos($content, 'function map_result_to_reaction') !== false) {
    echo "   ✓ Method exists: map_result_to_reaction()\n";
    $passed++;
    
    // Check if it maps to frontend format
    $reactionMappings = ['none', 'mild', 'moderate', 'severe'];
    $mappingCount = 0;
    foreach ($reactionMappings as $mapping) {
        if (strpos($content, "'$mapping'") !== false) {
            $mappingCount++;
        }
    }
    if ($mappingCount >= 3) {
        echo "   ✓ Maps results to frontend reaction format\n";
        $passed++;
    } else {
        echo "   ✗ Missing reaction mappings\n";
        $failed++;
    }
} else {
    echo "   ✗ Method missing: map_result_to_reaction()\n";
    $failed++;
}

// Check for is_new_food method
if (strpos($content, 'function is_new_food') !== false) {
    echo "   ✓ Method exists: is_new_food()\n";
    $passed++;
} else {
    echo "   ✗ Method missing: is_new_food()\n";
    $failed++;
}
echo "\n";

// Test 5: Check Response Fields
echo "5. Response Field Validation\n";

// Check if reaction field is added to response
if (preg_match("/'reaction'\s*=>/", $content)) {
    echo "   ✓ reaction field included in response\n";
    $passed++;
} else {
    echo "   ✗ reaction field missing in response\n";
    $failed++;
}

// Check if is_new field is added to response
if (preg_match("/'is_new'\s*=>/", $content)) {
    echo "   ✓ is_new field included in response\n";
    $passed++;
} else {
    echo "   ✗ is_new field missing in response\n";
    $failed++;
}
echo "\n";

// Summary
echo "=== Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    echo "\n❌ Some tests failed. Please review the implementation.\n";
    exit(1);
} else {
    echo "\n✅ All tests passed!\n";
    exit(0);
}

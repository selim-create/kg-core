<?php
/**
 * Test for 6 New Smart Assistant Tools
 * 
 * This test verifies the implementation of new smart assistant tool endpoints
 */

echo "=== KG Core Smart Assistant Tools Implementation Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if Service files exist
echo "1. Service Files Verification\n";
$serviceFiles = [
    'WaterCalculator.php',
    'AllergenPlanner.php',
    'FoodSuitabilityChecker.php',
    'SolidFoodReadinessChecker.php',
];

foreach ($serviceFiles as $file) {
    $path = $baseDir . '/includes/Services/' . $file;
    if (file_exists($path)) {
        echo "   ✓ Service exists: $file\n";
        
        // Check for namespace
        $content = file_get_contents($path);
        if (strpos($content, 'namespace KG_Core\Services;') !== false) {
            echo "   ✓ Namespace correct\n";
            $passed++;
        } else {
            echo "   ✗ Namespace missing\n";
            $failed++;
        }
    } else {
        echo "   ✗ Service missing: $file\n";
        $failed++;
    }
}

echo "\n";

// Test 2: Check FoodTrialController
echo "2. FoodTrialController Verification\n";
$controllerFile = $baseDir . '/includes/API/FoodTrialController.php';
if (file_exists($controllerFile)) {
    echo "   ✓ Controller exists: FoodTrialController.php\n";
    $content = file_get_contents($controllerFile);
    
    // Check for required methods
    $requiredMethods = [
        'register_routes',
        'get_food_trials',
        'add_food_trial',
        'update_food_trial',
        'delete_food_trial',
        'get_stats',
    ];
    
    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ Controller missing: FoodTrialController.php\n";
    $failed++;
}

echo "\n";

// Test 3: Check ToolController extensions
echo "3. ToolController Endpoint Extensions\n";
$toolControllerFile = $baseDir . '/includes/API/ToolController.php';
if (file_exists($toolControllerFile)) {
    echo "   ✓ Controller exists: ToolController.php\n";
    $content = file_get_contents($toolControllerFile);
    
    // Check for new endpoint methods
    $newMethods = [
        'ingredient_guide_check',
        'solid_food_readiness_config',
        'solid_food_readiness_submit',
        'food_check',
        'allergen_planner_config',
        'allergen_planner_generate',
        'water_calculator',
    ];
    
    foreach ($newMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
    
    // Check for route registrations
    $endpoints = [
        '/tools/ingredient-guide/check',
        '/tools/solid-food-readiness/config',
        '/tools/solid-food-readiness/submit',
        '/tools/food-check',
        '/tools/allergen-planner/config',
        '/tools/allergen-planner/generate',
        '/tools/water-calculator',
    ];
    
    foreach ($endpoints as $endpoint) {
        if (strpos($content, "'" . $endpoint . "'") !== false) {
            echo "   ✓ Endpoint registered: $endpoint\n";
            $passed++;
        } else {
            echo "   ✗ Endpoint missing: $endpoint\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ Controller missing: ToolController.php\n";
    $failed++;
}

echo "\n";

// Test 4: Check main plugin file includes
echo "4. Main Plugin File Verification\n";
$mainFile = $baseDir . '/kg-core.php';
if (file_exists($mainFile)) {
    echo "   ✓ Main file exists: kg-core.php\n";
    $content = file_get_contents($mainFile);
    
    // Check for service includes
    $serviceIncludes = [
        'WaterCalculator.php',
        'AllergenPlanner.php',
        'FoodSuitabilityChecker.php',
        'SolidFoodReadinessChecker.php',
    ];
    
    foreach ($serviceIncludes as $include) {
        if (strpos($content, $include) !== false) {
            echo "   ✓ Service included: $include\n";
            $passed++;
        } else {
            echo "   ✗ Service not included: $include\n";
            $failed++;
        }
    }
    
    // Check for FoodTrialController include
    if (strpos($content, 'FoodTrialController.php') !== false) {
        echo "   ✓ Controller included: FoodTrialController.php\n";
        $passed++;
    } else {
        echo "   ✗ Controller not included: FoodTrialController.php\n";
        $failed++;
    }
    
    // Check for initialization
    if (strpos($content, "new \\KG_Core\\API\\FoodTrialController()") !== false) {
        echo "   ✓ FoodTrialController initialized\n";
        $passed++;
    } else {
        echo "   ✗ FoodTrialController not initialized\n";
        $failed++;
    }
} else {
    echo "   ✗ Main file missing: kg-core.php\n";
    $failed++;
}

echo "\n";

// Test 5: Check WaterCalculator logic
echo "5. WaterCalculator Service Logic\n";
$waterCalcFile = $baseDir . '/includes/Services/WaterCalculator.php';
if (file_exists($waterCalcFile)) {
    $content = file_get_contents($waterCalcFile);
    
    // Check for Holliday-Segar formula implementation
    if (strpos($content, 'holliday_segar_formula') !== false) {
        echo "   ✓ Holliday-Segar formula method exists\n";
        $passed++;
        
        // Check formula logic
        if (strpos($content, '100') !== false && 
            strpos($content, '50') !== false && 
            strpos($content, '20') !== false) {
            echo "   ✓ Formula constants present (100, 50, 20)\n";
            $passed++;
        } else {
            echo "   ✗ Formula constants missing\n";
            $failed++;
        }
    } else {
        echo "   ✗ Holliday-Segar formula method missing\n";
        $failed++;
    }
    
    // Check for weather adjustment
    if (strpos($content, 'weather_adjustment') !== false) {
        echo "   ✓ Weather adjustment logic exists\n";
        $passed++;
    } else {
        echo "   ✗ Weather adjustment logic missing\n";
        $failed++;
    }
} else {
    echo "   ✗ WaterCalculator service missing\n";
    $failed++;
}

echo "\n";

// Test 6: Check AllergenPlanner templates
echo "6. AllergenPlanner Templates\n";
$allergenFile = $baseDir . '/includes/Services/AllergenPlanner.php';
if (file_exists($allergenFile)) {
    $content = file_get_contents($allergenFile);
    
    // Check for allergen templates
    $allergens = ['yumurta', 'sut', 'fistik', 'balik', 'buday', 'soya', 'findik', 'susam'];
    
    foreach ($allergens as $allergen) {
        if (strpos($content, "'" . $allergen . "'") !== false) {
            echo "   ✓ Allergen template exists: $allergen\n";
            $passed++;
        } else {
            echo "   ✗ Allergen template missing: $allergen\n";
            $failed++;
        }
    }
    
    // Check for WHO/AAP compliance
    if (strpos($content, 'WHO') !== false || strpos($content, 'AAP') !== false) {
        echo "   ✓ WHO/AAP standards referenced\n";
        $passed++;
    } else {
        echo "   ✗ WHO/AAP standards not referenced\n";
        $failed++;
    }
} else {
    echo "   ✗ AllergenPlanner service missing\n";
    $failed++;
}

echo "\n";

// Test 7: Check FoodSuitabilityChecker hardcoded rules
echo "7. FoodSuitabilityChecker Hardcoded Rules\n";
$foodCheckFile = $baseDir . '/includes/Services/FoodSuitabilityChecker.php';
if (file_exists($foodCheckFile)) {
    $content = file_get_contents($foodCheckFile);
    
    // Check for hardcoded rules
    $rules = ['bal', 'tam_findik_ceviz', 'tuz', 'seker', 'bogulma_riski'];
    
    foreach ($rules as $rule) {
        if (strpos($content, "'" . $rule . "'") !== false) {
            echo "   ✓ Hardcoded rule exists: $rule\n";
            $passed++;
        } else {
            echo "   ✗ Hardcoded rule missing: $rule\n";
            $failed++;
        }
    }
    
    // Check for age restrictions
    if (strpos($content, 'min_age_months') !== false) {
        echo "   ✓ Age restriction logic exists\n";
        $passed++;
    } else {
        echo "   ✗ Age restriction logic missing\n";
        $failed++;
    }
} else {
    echo "   ✗ FoodSuitabilityChecker service missing\n";
    $failed++;
}

echo "\n";

// Test 8: Check SolidFoodReadinessChecker questions
echo "8. SolidFoodReadinessChecker Questions\n";
$readinessFile = $baseDir . '/includes/Services/SolidFoodReadinessChecker.php';
if (file_exists($readinessFile)) {
    $content = file_get_contents($readinessFile);
    
    // Check for required questions
    $questions = [
        'q1_sitting',
        'q2_tongue_reflex',
        'q3_interest',
        'q4_hand_mouth',
        'q5_age',
        'q6_weight',
    ];
    
    foreach ($questions as $question) {
        if (strpos($content, "'" . $question . "'") !== false) {
            echo "   ✓ Question exists: $question\n";
            $passed++;
        } else {
            echo "   ✗ Question missing: $question\n";
            $failed++;
        }
    }
    
    // Check for result buckets
    $buckets = ['ready', 'almost_ready', 'not_yet'];
    foreach ($buckets as $bucket) {
        if (strpos($content, "'" . $bucket . "'") !== false) {
            echo "   ✓ Result bucket exists: $bucket\n";
            $passed++;
        } else {
            echo "   ✗ Result bucket missing: $bucket\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ SolidFoodReadinessChecker service missing\n";
    $failed++;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
echo "Success Rate: $percentage%\n";

if ($failed === 0) {
    echo "\n✓ All tests passed! Implementation is complete.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}

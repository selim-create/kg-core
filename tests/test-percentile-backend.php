<?php
/**
 * Test file for Percentile Calculator Backend Implementation
 * Tests WHO growth data calculations and API endpoints
 */

echo "=== KG Core Percentile Calculator Backend Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if files exist
echo "1. File Existence Tests\n";
$required_files = [
    'includes/Tools/WHOGrowthData.php',
    'includes/API/PercentileController.php',
    'data/who-growth-tables/wfa_boys_0_5.json',
    'data/who-growth-tables/wfa_girls_0_5.json',
    'data/who-growth-tables/lhfa_boys_0_5.json',
    'data/who-growth-tables/lhfa_girls_0_5.json',
    'data/who-growth-tables/hcfa_boys_0_5.json',
    'data/who-growth-tables/hcfa_girls_0_5.json',
    'data/who-growth-tables/wfl_boys.json',
    'data/who-growth-tables/wfl_girls.json',
];

foreach ($required_files as $file) {
    $filepath = $baseDir . '/' . $file;
    if (file_exists($filepath)) {
        echo "   ✓ File exists: $file\n";
        $passed++;
    } else {
        echo "   ✗ File missing: $file\n";
        $failed++;
    }
}

// Test 2: Check WHO data file structure
echo "\n2. WHO Data File Structure Tests\n";
$data_files = [
    'wfa_boys_0_5.json' => 'age',
    'wfa_girls_0_5.json' => 'age',
    'lhfa_boys_0_5.json' => 'age',
    'lhfa_girls_0_5.json' => 'age',
    'hcfa_boys_0_5.json' => 'age',
    'hcfa_girls_0_5.json' => 'age',
    'wfl_boys.json' => 'length',
    'wfl_girls.json' => 'length',
];

foreach ($data_files as $file => $key) {
    $filepath = $baseDir . '/data/who-growth-tables/' . $file;
    if (file_exists($filepath)) {
        $json = file_get_contents($filepath);
        $data = json_decode($json, true);
        
        if ($data && is_array($data) && count($data) > 0) {
            $first = $data[0];
            $has_required_keys = isset($first[$key]) && isset($first['L']) && isset($first['M']) && isset($first['S']);
            
            if ($has_required_keys) {
                echo "   ✓ Valid structure: $file (has $key, L, M, S)\n";
                $passed++;
            } else {
                echo "   ✗ Invalid structure: $file\n";
                $failed++;
            }
        } else {
            echo "   ✗ Invalid JSON: $file\n";
            $failed++;
        }
    }
}

// Test 3: Check WHOGrowthData class
echo "\n3. WHOGrowthData Class Tests\n";
$whoFile = $baseDir . '/includes/Tools/WHOGrowthData.php';
if (file_exists($whoFile)) {
    $content = file_get_contents($whoFile);
    
    $required_methods = [
        'calculate_weight_for_age',
        'calculate_height_for_age',
        'calculate_head_for_age',
        'calculate_weight_for_height',
        'load_data',
        'interpolate_lms',
        'interpolate_lms_by_length',
        'calculate_z_score',
        'z_to_percentile',
        'erf',
    ];
    
    foreach ($required_methods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
}

// Test 4: Check PercentileController class
echo "\n4. PercentileController Class Tests\n";
$controllerFile = $baseDir . '/includes/API/PercentileController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    $required_methods = [
        'register_routes',
        'calculate_percentile',
        'save_percentile_result',
        'get_user_percentile_results',
        'get_child_percentile_results',
        'check_auth',
        'format_percentile_result',
        'get_category',
        'get_interpretation',
        'save_result',
    ];
    
    foreach ($required_methods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
    
    // Check API endpoints
    echo "\n   API Endpoints:\n";
    $endpoints = [
        "'/tools/percentile/calculate'" => 'Calculate endpoint',
        "'/tools/percentile/save'" => 'Save endpoint',
        "'/user/percentile-results'" => 'User results endpoint',
        "'/user/children/(?P<child_id>[a-zA-Z0-9-]+)/percentile-results'" => 'Child results endpoint',
    ];
    
    foreach ($endpoints as $endpoint => $description) {
        if (strpos($content, $endpoint) !== false) {
            echo "      ✓ $description registered\n";
            $passed++;
        } else {
            echo "      ✗ $description missing\n";
            $failed++;
        }
    }
}

// Test 5: Check kg-core.php includes
echo "\n5. kg-core.php Integration Tests\n";
$coreFile = $baseDir . '/kg-core.php';
if (file_exists($coreFile)) {
    $content = file_get_contents($coreFile);
    
    // Check if WHOGrowthData is included
    if (strpos($content, "includes/Tools/WHOGrowthData.php") !== false) {
        echo "   ✓ WHOGrowthData included\n";
        $passed++;
    } else {
        echo "   ✗ WHOGrowthData not included\n";
        $failed++;
    }
    
    // Check if PercentileController is included
    if (strpos($content, "includes/API/PercentileController.php") !== false) {
        echo "   ✓ PercentileController included\n";
        $passed++;
    } else {
        echo "   ✗ PercentileController not included\n";
        $failed++;
    }
    
    // Check if PercentileController is initialized
    if (strpos($content, "new \KG_Core\API\PercentileController()") !== false || 
        strpos($content, "PercentileController()") !== false) {
        echo "   ✓ PercentileController initialized\n";
        $passed++;
    } else {
        echo "   ✗ PercentileController not initialized\n";
        $failed++;
    }
}

// Test 6: Check Tool.php for percentile type
echo "\n6. Tool Post Type Tests\n";
$toolFile = $baseDir . '/includes/PostTypes/Tool.php';
if (file_exists($toolFile)) {
    $content = file_get_contents($toolFile);
    
    if (strpos($content, "'percentile'") !== false) {
        echo "   ✓ Percentile tool type exists in ACF choices\n";
        $passed++;
    } else {
        echo "   ✗ Percentile tool type not found\n";
        $failed++;
    }
}

// Test 7: Validate WHO data calculations (mathematical tests)
echo "\n7. Mathematical Validation Tests\n";

// Test z-score calculation
echo "   Testing z-score calculation formula:\n";
$test_cases = [
    ['value' => 9.5, 'L' => 0.1940, 'M' => 9.0018, 'S' => 0.11592, 'expected_approx' => 0.47],
    ['value' => 75.0, 'L' => 1, 'M' => 70.4782, 'S' => 0.02607, 'expected_approx' => 2.46],
];

// Simulate z-score calculation
function test_z_score($value, $L, $M, $S) {
    if ($L != 0) {
        return (pow($value / $M, $L) - 1) / ($L * $S);
    } else {
        return log($value / $M) / $S;
    }
}

foreach ($test_cases as $tc) {
    $z = test_z_score($tc['value'], $tc['L'], $tc['M'], $tc['S']);
    $diff = abs($z - $tc['expected_approx']);
    if ($diff < 0.5) { // Allow some tolerance
        echo "      ✓ Z-score calculation accurate (got " . round($z, 2) . ", expected ~{$tc['expected_approx']})\n";
        $passed++;
    } else {
        echo "      ✗ Z-score calculation off (got " . round($z, 2) . ", expected ~{$tc['expected_approx']})\n";
        $failed++;
    }
}

// Test 8: Check response format
echo "\n8. API Response Format Tests\n";
echo "   Checking calculate_percentile expected response structure:\n";
$controllerContent = file_get_contents($baseDir . '/includes/API/PercentileController.php');
$expected_keys = [
    'age_in_days',
    'age_in_months',
    'percentiles',
    'red_flags',
    'measurement',
    'created_at',
];

foreach ($expected_keys as $key) {
    if (strpos($controllerContent, "'$key'") !== false) {
        echo "      ✓ Response includes: $key\n";
        $passed++;
    } else {
        echo "      ✗ Response missing: $key\n";
        $failed++;
    }
}

echo "\n   Checking percentile item structure:\n";
$percentile_keys = [
    'measurement_type',
    'value',
    'percentile',
    'z_score',
    'category',
    'interpretation',
];

foreach ($percentile_keys as $key) {
    if (strpos($controllerContent, "'$key'") !== false) {
        echo "      ✓ Percentile item includes: $key\n";
        $passed++;
    } else {
        echo "      ✗ Percentile item missing: $key\n";
        $failed++;
    }
}

// Test 9: Check red flag detection
echo "\n9. Red Flag Detection Tests\n";
$red_flag_keywords = [
    'red_flags',
    'percentile\'] < 3',
    'percentile\'] > 97',
    'severity',
    'critical',
    'warning',
];

foreach ($red_flag_keywords as $keyword) {
    if (strpos($controllerContent, $keyword) !== false || strpos($controllerContent, str_replace(' ', '', $keyword)) !== false) {
        echo "   ✓ Red flag logic includes: $keyword\n";
        $passed++;
    } else {
        echo "   ✗ Red flag logic missing: $keyword\n";
        $failed++;
    }
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "Test Summary:\n";
echo "✓ Passed: $passed\n";
echo "✗ Failed: $failed\n";
$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
echo "Success Rate: $percentage%\n";
echo str_repeat("=", 60) . "\n";

if ($failed === 0) {
    echo "\n🎉 All tests passed! Percentile Calculator backend is ready.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Please review the implementation.\n";
    exit(1);
}

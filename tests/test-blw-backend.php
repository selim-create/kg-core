<?php
/**
 * Static Code Analysis Test for BLW Test Backend Implementation
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core BLW Test Backend Implementation Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if Tool.php is extended
echo "1. Tool Post Type Extension\n";
$toolFile = $baseDir . '/includes/PostTypes/Tool.php';
if (file_exists($toolFile)) {
    echo "   ✓ File exists: Tool.php\n";
    $content = file_get_contents($toolFile);
    
    // Check for required methods
    $requiredMethods = [
        'register_post_type',
        'register_taxonomy',
        'register_acf_fields'
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
    
    // Check for taxonomy registration
    if (strpos($content, "register_taxonomy") !== false && strpos($content, "'tool_type'") !== false) {
        echo "   ✓ tool_type taxonomy registered\n";
        $passed++;
    } else {
        echo "   ✗ tool_type taxonomy not found\n";
        $failed++;
    }
    
    // Check for ACF fields
    $acfFields = [
        'tool_type',
        'tool_icon',
        'is_active',
        'requires_auth',
        'blw_questions',
        'result_buckets',
        'disclaimer_text',
        'emergency_text'
    ];
    
    foreach ($acfFields as $field) {
        if (strpos($content, "'$field'") !== false) {
            echo "   ✓ ACF field defined: $field\n";
            $passed++;
        } else {
            echo "   ✗ ACF field missing: $field\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ File not found: Tool.php\n";
    $failed++;
}

echo "\n";

// Test 2: Check if ToolController exists
echo "2. Tool Controller\n";
$toolControllerFile = $baseDir . '/includes/API/ToolController.php';
if (file_exists($toolControllerFile)) {
    echo "   ✓ File exists: ToolController.php\n";
    $content = file_get_contents($toolControllerFile);
    
    // Check namespace
    if (strpos($content, 'namespace KG_Core\API') !== false) {
        echo "   ✓ Correct namespace\n";
        $passed++;
    } else {
        echo "   ✗ Incorrect or missing namespace\n";
        $failed++;
    }
    
    // Check for required endpoints
    $requiredEndpoints = [
        '/tools',
        '/tools/(?P<slug>[a-zA-Z0-9_-]+)',
        '/tools/blw-test/config',
        '/tools/blw-test/submit'
    ];
    
    foreach ($requiredEndpoints as $endpoint) {
        if (strpos($content, "'$endpoint'") !== false) {
            echo "   ✓ Endpoint registered: $endpoint\n";
            $passed++;
        } else {
            echo "   ✗ Endpoint missing: $endpoint\n";
            $failed++;
        }
    }
    
    // Check for required methods
    $requiredMethods = [
        'get_tools',
        'get_tool',
        'get_blw_test_config',
        'submit_blw_test',
        'calculate_score',
        'save_blw_result',
        'get_default_blw_config'
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
    
    // Check for WHO standards in default config
    if (strpos($content, 'physical_readiness') !== false &&
        strpos($content, 'safety') !== false &&
        strpos($content, 'environment') !== false) {
        echo "   ✓ WHO standard categories implemented\n";
        $passed++;
    } else {
        echo "   ✗ WHO standard categories missing\n";
        $failed++;
    }
    
    // Check for scoring logic
    if (strpos($content, 'weighted_sum') !== false && strpos($content, 'total_weight') !== false) {
        echo "   ✓ Weighted scoring logic implemented\n";
        $passed++;
    } else {
        echo "   ✗ Weighted scoring logic missing\n";
        $failed++;
    }
    
    // Check for red flag handling
    if (strpos($content, 'red_flags') !== false && strpos($content, 'is_red_flag') !== false) {
        echo "   ✓ Red flag handling implemented\n";
        $passed++;
    } else {
        echo "   ✗ Red flag handling missing\n";
        $failed++;
    }
    
    // Check for result buckets
    $resultBuckets = ['ready', 'almost_ready', 'not_ready'];
    foreach ($resultBuckets as $bucket) {
        if (strpos($content, "'$bucket'") !== false) {
            echo "   ✓ Result bucket defined: $bucket\n";
            $passed++;
        } else {
            echo "   ✗ Result bucket missing: $bucket\n";
            $failed++;
        }
    }
    
    // Check for registration support
    if (strpos($content, '$register') !== false && strpos($content, 'wp_create_user') !== false) {
        echo "   ✓ Registration during test submission supported\n";
        $passed++;
    } else {
        echo "   ✗ Registration during test submission missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: ToolController.php\n";
    $failed++;
}

echo "\n";

// Test 3: Check UserController BLW endpoints
echo "3. UserController BLW Endpoints\n";
$userControllerFile = $baseDir . '/includes/API/UserController.php';
if (file_exists($userControllerFile)) {
    echo "   ✓ File exists: UserController.php\n";
    $content = file_get_contents($userControllerFile);
    
    // Check for BLW endpoints
    $blwEndpoints = [
        '/user/blw-results',
        '/user/children/(?P<child_id>[a-zA-Z0-9-]+)/blw-results'
    ];
    
    foreach ($blwEndpoints as $endpoint) {
        if (strpos($content, "'$endpoint'") !== false) {
            echo "   ✓ BLW endpoint registered: $endpoint\n";
            $passed++;
        } else {
            echo "   ✗ BLW endpoint missing: $endpoint\n";
            $failed++;
        }
    }
    
    // Check for BLW methods
    $blwMethods = [
        'get_blw_results',
        'get_child_blw_results'
    ];
    
    foreach ($blwMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ BLW method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ BLW method missing: $method()\n";
            $failed++;
        }
    }
    
    // Check for user meta key
    if (strpos($content, '_kg_blw_results') !== false) {
        echo "   ✓ BLW results meta key used\n";
        $passed++;
    } else {
        echo "   ✗ BLW results meta key missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: UserController.php\n";
    $failed++;
}

echo "\n";

// Test 4: Check main plugin file
echo "4. Main Plugin File (kg-core.php)\n";
$pluginFile = $baseDir . '/kg-core.php';
if (file_exists($pluginFile)) {
    echo "   ✓ File exists: kg-core.php\n";
    $content = file_get_contents($pluginFile);
    
    // Check if ToolController is required
    if (strpos($content, "require_once KG_CORE_PATH . 'includes/API/ToolController.php'") !== false ||
        strpos($content, "require_once KG_CORE_PATH . 'includes/API/ToolController.php'") !== false) {
        echo "   ✓ ToolController required\n";
        $passed++;
    } else {
        echo "   ✗ ToolController not required\n";
        $failed++;
    }
    
    // Check if ToolController is initialized
    if (strpos($content, 'new \KG_Core\API\ToolController()') !== false) {
        echo "   ✓ ToolController initialized\n";
        $passed++;
    } else {
        echo "   ✗ ToolController not initialized\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: kg-core.php\n";
    $failed++;
}

echo "\n";

// Test 5: Check default BLW configuration
echo "5. Default BLW Configuration\n";
if (file_exists($toolControllerFile)) {
    $content = file_get_contents($toolControllerFile);
    
    // Check for 10 questions
    $questionCount = substr_count($content, "'id' => 'q");
    if ($questionCount >= 10) {
        echo "   ✓ At least 10 questions defined (found: $questionCount)\n";
        $passed++;
    } else {
        echo "   ✗ Less than 10 questions defined (found: $questionCount)\n";
        $failed++;
    }
    
    // Check for weight distribution
    if (strpos($content, "'weight' => 80") !== false ||
        strpos($content, "'weight' => 75") !== false ||
        strpos($content, "'weight' => 70") !== false) {
        echo "   ✓ Physical readiness questions have higher weights\n";
        $passed++;
    } else {
        echo "   ✗ Weight distribution might be incorrect\n";
        $failed++;
    }
    
    // Check for 3 result buckets
    $bucketIds = ['ready', 'almost_ready', 'not_ready'];
    $allBucketsFound = true;
    foreach ($bucketIds as $bucketId) {
        if (strpos($content, "'id' => '$bucketId'") === false) {
            $allBucketsFound = false;
            break;
        }
    }
    
    if ($allBucketsFound) {
        echo "   ✓ All 3 result buckets defined (ready, almost_ready, not_ready)\n";
        $passed++;
    } else {
        echo "   ✗ Not all result buckets defined\n";
        $failed++;
    }
    
    // Check for score ranges
    if (strpos($content, "'min_score' => 80") !== false &&
        strpos($content, "'max_score' => 100") !== false &&
        strpos($content, "'min_score' => 55") !== false) {
        echo "   ✓ Score ranges correctly defined\n";
        $passed++;
    } else {
        echo "   ✗ Score ranges might be incorrect\n";
        $failed++;
    }
    
    // Check for action items and next steps
    if (strpos($content, "'action_items'") !== false &&
        strpos($content, "'next_steps'") !== false) {
        echo "   ✓ Action items and next steps included in results\n";
        $passed++;
    } else {
        echo "   ✗ Action items or next steps missing\n";
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

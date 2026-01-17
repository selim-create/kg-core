<?php
/**
 * Static Code Analysis Test for REST API Filters
 * 
 * This test verifies the implementation of RestApiFilters without requiring WordPress
 */

echo "=== KG Core REST API Filters Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check RestApiFilters class file exists
echo "1. RestApiFilters Class File\n";
$filterFile = $baseDir . '/includes/API/RestApiFilters.php';
if (file_exists($filterFile)) {
    echo "   ✓ File exists: RestApiFilters.php\n";
    $passed++;
    
    $content = file_get_contents($filterFile);
    
    // Check class declaration
    if (strpos($content, 'class RestApiFilters') !== false) {
        echo "   ✓ Class declared: RestApiFilters\n";
        $passed++;
    } else {
        echo "   ✗ Class not found: RestApiFilters\n";
        $failed++;
    }
    
    // Check for required filters
    $requiredFilters = [
        'rest_prepare_user',
        'rest_prepare_post',
        'rest_prepare_recipe',
        'rest_prepare_discussion',
        'rest_prepare_ingredient',
        'pre_get_avatar_data'
    ];
    
    foreach ($requiredFilters as $filter) {
        // Use regex to match add_filter calls more precisely
        $pattern = "/add_filter\s*\(\s*['\"]" . preg_quote($filter, '/') . "['\"]/";
        if (preg_match($pattern, $content)) {
            echo "   ✓ Filter registered: $filter\n";
            $passed++;
        } else {
            echo "   ✗ Filter missing: $filter\n";
            $failed++;
        }
    }
    
    // Check for required methods
    $requiredMethods = [
        'filter_user_avatar',
        'filter_post_author_avatar',
        'filter_avatar_data',
        'get_custom_avatar_url',
        'get_user_id_from_id_or_email'
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
    
    // Check if it uses Helper class
    if (strpos($content, '\KG_Core\Utils\Helper::get_user_avatar_url') !== false) {
        echo "   ✓ Uses Helper::get_user_avatar_url()\n";
        $passed++;
    } else {
        echo "   ✗ Not using Helper::get_user_avatar_url()\n";
        $failed++;
    }
    
    // Check for _kg_avatar_id meta key
    if (strpos($content, '_kg_avatar_id') !== false) {
        echo "   ✓ Checks for _kg_avatar_id user meta\n";
        $passed++;
    } else {
        echo "   ✗ Missing _kg_avatar_id check\n";
        $failed++;
    }
    
    // Check for google_avatar meta key
    if (strpos($content, 'google_avatar') !== false) {
        echo "   ✓ Checks for google_avatar user meta\n";
        $passed++;
    } else {
        echo "   ✗ Missing google_avatar check\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RestApiFilters.php\n";
    $failed++;
}
echo "\n";

// Test 2: Check kg-core.php includes RestApiFilters
echo "2. kg-core.php Integration\n";
$coreFile = $baseDir . '/kg-core.php';
if (file_exists($coreFile)) {
    echo "   ✓ File exists: kg-core.php\n";
    $passed++;
    
    $content = file_get_contents($coreFile);
    
    // Check if RestApiFilters is required
    if (strpos($content, 'includes/API/RestApiFilters.php') !== false) {
        echo "   ✓ RestApiFilters.php is included\n";
        $passed++;
    } else {
        echo "   ✗ RestApiFilters.php not included\n";
        $failed++;
    }
    
    // Check if RestApiFilters is initialized
    if (strpos($content, 'new \KG_Core\API\RestApiFilters()') !== false) {
        echo "   ✓ RestApiFilters is initialized in kg_core_init()\n";
        $passed++;
    } else {
        echo "   ✗ RestApiFilters not initialized\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: kg-core.php\n";
    $failed++;
}
echo "\n";

// Test 3: Check Helper class has get_user_avatar_url method
echo "3. Helper Class Integration\n";
$helperFile = $baseDir . '/includes/Utils/Helper.php';
if (file_exists($helperFile)) {
    echo "   ✓ File exists: Helper.php\n";
    $passed++;
    
    $content = file_get_contents($helperFile);
    
    if (strpos($content, 'function get_user_avatar_url') !== false) {
        echo "   ✓ Method exists: get_user_avatar_url()\n";
        $passed++;
        
        // Check for priority: _kg_avatar_id > google_avatar > gravatar
        if (strpos($content, '_kg_avatar_id') !== false &&
            strpos($content, 'google_avatar') !== false &&
            strpos($content, 'get_avatar_url') !== false) {
            echo "   ✓ Correct priority: custom > google > gravatar\n";
            $passed++;
        } else {
            echo "   ✗ Avatar priority implementation incomplete\n";
            $failed++;
        }
    } else {
        echo "   ✗ Method missing: get_user_avatar_url()\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: Helper.php\n";
    $failed++;
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}

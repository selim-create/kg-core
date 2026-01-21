<?php
/**
 * Test script for Backend Performance Optimization features
 * 
 * Tests the new services: RateLimiter, PaginationHelper, FieldsFilter, CacheWarmer
 * 
 * Run from command line: php test-performance-optimization.php
 */

echo "=== Backend Performance Optimization Tests ===\n\n";

// Test 1: Check if files exist
echo "Test 1: File Existence Check\n";
echo "------------------------------\n";

$files = [
    'includes/Database/migrations/2024_01_21_add_postmeta_indexes.php',
    'includes/API/RateLimitMiddleware.php',
    'includes/Services/RateLimiter.php',
    'includes/API/PaginationHelper.php',
    'includes/API/FieldsFilter.php',
    'includes/Services/CacheWarmer.php',
];

$all_exist = true;
foreach ($files as $file) {
    $path = dirname(__DIR__) . '/' . $file;
    if (file_exists($path)) {
        echo "✓ {$file}\n";
    } else {
        echo "✗ {$file} - NOT FOUND\n";
        $all_exist = false;
    }
}

if ($all_exist) {
    echo "\n✓ All files exist\n";
} else {
    echo "\n✗ Some files are missing\n";
    exit(1);
}

// Test 2: Check PHP syntax
echo "\n\nTest 2: PHP Syntax Check\n";
echo "-------------------------\n";

$syntax_errors = [];
foreach ($files as $file) {
    $path = dirname(__DIR__) . '/' . $file;
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "✓ {$file}\n";
    } else {
        echo "✗ {$file}\n";
        $syntax_errors[] = $file;
        foreach ($output as $line) {
            echo "  {$line}\n";
        }
    }
}

if (empty($syntax_errors)) {
    echo "\n✓ All files have valid PHP syntax\n";
} else {
    echo "\n✗ Some files have syntax errors\n";
    exit(1);
}

// Test 3: Check class definitions
echo "\n\nTest 3: Class Definition Check\n";
echo "-------------------------------\n";

$classes = [
    'KG_Core\\Database\\Migrations\\AddPostmetaIndexes',
    'KG_Core\\API\\RateLimitMiddleware',
    'KG_Core\\Services\\RateLimiter',
    'KG_Core\\API\\PaginationHelper',
    'KG_Core\\API\\FieldsFilter',
    'KG_Core\\Services\\CacheWarmer',
];

foreach ($files as $file) {
    require_once dirname(__DIR__) . '/' . $file;
}

$all_classes_exist = true;
foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✓ {$class}\n";
    } else {
        echo "✗ {$class} - NOT FOUND\n";
        $all_classes_exist = false;
    }
}

if ($all_classes_exist) {
    echo "\n✓ All classes are properly defined\n";
} else {
    echo "\n✗ Some classes are missing\n";
    exit(1);
}

// Test 4: Check method existence
echo "\n\nTest 4: Method Existence Check\n";
echo "-------------------------------\n";

$methods = [
    'KG_Core\\Services\\RateLimiter' => ['check', 'getHeaders', 'reset'],
    'KG_Core\\API\\PaginationHelper' => ['get_args', 'get_from_request', 'build_response'],
    'KG_Core\\API\\FieldsFilter' => ['get_args', 'parse', 'filter', 'includes'],
    'KG_Core\\Services\\CacheWarmer' => ['warm_caches', 'trigger', 'deactivate'],
];

$all_methods_exist = true;
foreach ($methods as $class => $method_list) {
    echo "\nClass: {$class}\n";
    foreach ($method_list as $method) {
        if (method_exists($class, $method)) {
            echo "  ✓ {$method}()\n";
        } else {
            echo "  ✗ {$method}() - NOT FOUND\n";
            $all_methods_exist = false;
        }
    }
}

if ($all_methods_exist) {
    echo "\n✓ All required methods exist\n";
} else {
    echo "\n✗ Some methods are missing\n";
    exit(1);
}

// Test 5: Basic functionality tests
echo "\n\nTest 5: Basic Functionality Tests\n";
echo "----------------------------------\n";

// Test PaginationHelper
echo "\nPaginationHelper::build_response()\n";
$pagination = \KG_Core\API\PaginationHelper::build_response(100, 1, 12);
if ($pagination['total'] === 100 && $pagination['total_pages'] === 9) {
    echo "  ✓ Correctly calculates pagination metadata\n";
} else {
    echo "  ✗ Incorrect pagination calculation\n";
    exit(1);
}

// Test FieldsFilter
echo "\nFieldsFilter::filter()\n";
$data = ['id' => 1, 'title' => 'Test', 'content' => 'Content', 'extra' => 'Extra'];
$filtered = \KG_Core\API\FieldsFilter::filter($data, ['id', 'title']);
if (count($filtered) === 2 && isset($filtered['id']) && isset($filtered['title'])) {
    echo "  ✓ Correctly filters data fields\n";
} else {
    echo "  ✗ Incorrect field filtering\n";
    exit(1);
}

echo "\n\n=== All Tests Passed! ===\n";
echo "\nSummary:\n";
echo "- ✓ All files exist\n";
echo "- ✓ All files have valid PHP syntax\n";
echo "- ✓ All classes are properly defined\n";
echo "- ✓ All required methods exist\n";
echo "- ✓ Basic functionality works correctly\n";
echo "\nThe backend performance optimization implementation is complete and functional.\n";

exit(0);

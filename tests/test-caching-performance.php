<?php
/**
 * Test: Performance Optimization - Caching and Query Optimization
 * 
 * This test validates:
 * 1. CacheService functionality (get/set/invalidate)
 * 2. CacheInvalidator hooks
 * 3. RecipeController caching
 * 4. FeaturedController caching
 * 5. Bulk meta/term fetching optimization
 * 
 * Run: php tests/test-caching-performance.php
 */

// WordPress environment bootstrap
require_once dirname(__DIR__) . '/wp-load.php';

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

echo "=== Testing Performance Optimization: Caching + Query Optimization ===\n\n";

// Test counter for tracking
$tests_passed = 0;
$tests_failed = 0;

function test_assert($condition, $test_name) {
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo "✓ PASS: $test_name\n";
        $tests_passed++;
        return true;
    } else {
        echo "✗ FAIL: $test_name\n";
        $tests_failed++;
        return false;
    }
}

echo "1. Testing CacheService\n";
echo "----------------------------\n";

// Test 1.1: CacheService class exists
test_assert(
    class_exists('KG_Core\Services\CacheService'),
    'CacheService class exists'
);

// Test 1.2: Recipe cache set/get
$test_data = ['id' => 123, 'title' => 'Test Recipe', 'cached_at' => time()];
\KG_Core\Services\CacheService::set_recipe(123, $test_data, 60);
$cached_data = \KG_Core\Services\CacheService::get_recipe(123);
test_assert(
    $cached_data !== null && $cached_data['id'] === 123,
    'Recipe cache set/get works'
);

// Test 1.3: Recipe cache invalidation
\KG_Core\Services\CacheService::invalidate_recipe(123);
$cached_data = \KG_Core\Services\CacheService::get_recipe(123);
test_assert(
    $cached_data === null,
    'Recipe cache invalidation works'
);

// Test 1.4: List cache set/get
$list_data = ['items' => [1, 2, 3], 'total' => 3];
$args_hash = \KG_Core\Services\CacheService::hash_args(['page' => 1, 'per_page' => 12]);
\KG_Core\Services\CacheService::set_list('recipes', $args_hash, $list_data);
$cached_list = \KG_Core\Services\CacheService::get_list('recipes', $args_hash);
test_assert(
    $cached_list !== null && count($cached_list['items']) === 3,
    'List cache set/get works'
);

// Test 1.5: Featured cache set/get
$featured_data = [['id' => 1, 'title' => 'Featured Recipe']];
\KG_Core\Services\CacheService::set_featured($featured_data, 'recipe', 5);
$cached_featured = \KG_Core\Services\CacheService::get_featured('recipe', 5);
test_assert(
    $cached_featured !== null && count($cached_featured) === 1,
    'Featured cache set/get works'
);

echo "\n2. Testing CacheInvalidator\n";
echo "----------------------------\n";

// Test 2.1: CacheInvalidator class exists
test_assert(
    class_exists('KG_Core\Services\CacheInvalidator'),
    'CacheInvalidator class exists'
);

// Test 2.2: Recipe save hook invalidates cache
// Create a test recipe
$recipe_id = wp_insert_post([
    'post_type' => 'recipe',
    'post_title' => 'Cache Test Recipe',
    'post_status' => 'publish',
    'post_content' => 'Test content'
]);

if ($recipe_id && !is_wp_error($recipe_id)) {
    // Cache the recipe
    \KG_Core\Services\CacheService::set_recipe($recipe_id, ['id' => $recipe_id, 'title' => 'Test']);
    
    // Update the recipe (should trigger cache invalidation)
    wp_update_post([
        'ID' => $recipe_id,
        'post_title' => 'Updated Cache Test Recipe'
    ]);
    
    // Cache should be invalidated
    $cached_after_update = \KG_Core\Services\CacheService::get_recipe($recipe_id);
    test_assert(
        $cached_after_update === null,
        'Recipe cache invalidated on post save'
    );
    
    // Clean up
    wp_delete_post($recipe_id, true);
} else {
    echo "! SKIP: Could not create test recipe for cache invalidation test\n";
}

echo "\n3. Testing RecipeController Optimizations\n";
echo "----------------------------\n";

// Test 3.1: RecipeController uses CacheService
$reflection = new ReflectionClass('KG_Core\API\RecipeController');
$source = file_get_contents($reflection->getFileName());
test_assert(
    strpos($source, 'use KG_Core\Services\CacheService') !== false,
    'RecipeController imports CacheService'
);

test_assert(
    strpos($source, 'CacheService::get_recipe') !== false,
    'RecipeController uses recipe caching'
);

test_assert(
    strpos($source, 'CacheService::get_list') !== false,
    'RecipeController uses list caching'
);

test_assert(
    strpos($source, 'update_meta_cache') !== false,
    'RecipeController uses bulk meta fetching'
);

test_assert(
    strpos($source, 'update_object_term_cache') !== false,
    'RecipeController uses bulk term fetching'
);

test_assert(
    strpos($source, 'do_action(\'kg_recipe_rated\'') !== false,
    'RecipeController triggers cache invalidation on rating'
);

echo "\n4. Testing FeaturedController Optimizations\n";
echo "----------------------------\n";

// Test 4.1: FeaturedController uses CacheService
$reflection = new ReflectionClass('KG_Core\API\FeaturedController');
$source = file_get_contents($reflection->getFileName());
test_assert(
    strpos($source, 'use KG_Core\Services\CacheService') !== false,
    'FeaturedController imports CacheService'
);

test_assert(
    strpos($source, 'CacheService::get_featured') !== false,
    'FeaturedController uses featured caching'
);

test_assert(
    strpos($source, 'update_meta_cache') !== false,
    'FeaturedController uses bulk meta fetching'
);

echo "\n5. Testing Integration\n";
echo "----------------------------\n";

// Test 5.1: CacheInvalidator is initialized
global $wp_filter;
$has_hooks = false;
if (isset($wp_filter['save_post_recipe'])) {
    foreach ($wp_filter['save_post_recipe']->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function']) && 
                is_object($callback['function'][0]) && 
                get_class($callback['function'][0]) === 'KG_Core\Services\CacheInvalidator') {
                $has_hooks = true;
                break 2;
            }
        }
    }
}
test_assert(
    $has_hooks,
    'CacheInvalidator hooks are registered'
);

// Test 5.2: Cache hash generates consistent results
$args1 = ['page' => 1, 'per_page' => 12, 'orderby' => 'date'];
$args2 = ['page' => 1, 'per_page' => 12, 'orderby' => 'date'];
$hash1 = \KG_Core\Services\CacheService::hash_args($args1);
$hash2 = \KG_Core\Services\CacheService::hash_args($args2);
test_assert(
    $hash1 === $hash2,
    'Cache hash is consistent for same arguments'
);

// Test 5.3: Different args produce different hashes
$args3 = ['page' => 2, 'per_page' => 12, 'orderby' => 'date'];
$hash3 = \KG_Core\Services\CacheService::hash_args($args3);
test_assert(
    $hash1 !== $hash3,
    'Cache hash differs for different arguments'
);

echo "\n6. Testing Cache Cleanup\n";
echo "----------------------------\n";

// Test 6.1: Flush all caches
\KG_Core\Services\CacheService::set_recipe(999, ['test' => 'data']);
\KG_Core\Services\CacheService::flush_all();
$flushed = \KG_Core\Services\CacheService::get_recipe(999);
test_assert(
    $flushed === null,
    'flush_all() clears all KG caches'
);

echo "\n";
echo "=== Test Summary ===\n";
echo "Total tests run: " . ($tests_passed + $tests_failed) . "\n";
echo "Passed: $tests_passed ✓\n";
echo "Failed: $tests_failed ✗\n";

if ($tests_failed === 0) {
    echo "\n🎉 All tests passed! Caching implementation is working correctly.\n";
    exit(0);
} else {
    echo "\n⚠ Some tests failed. Please review the implementation.\n";
    exit(1);
}

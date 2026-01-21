<?php
/**
 * Static Analysis Test: Bulk Cache Optimization
 * 
 * This test validates the implementation of BulkCacheHelper without requiring WordPress to be loaded.
 * It checks:
 * 1. File existence
 * 2. Class definitions
 * 3. Method implementations
 * 4. Integration in controllers
 * 5. Autoload configuration
 * 
 * Run: php tests/test-bulk-cache-optimization.php
 */

echo "=== Static Analysis: Bulk Cache Optimization ===\n\n";

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

$base_path = dirname(__DIR__);

echo "1. File Existence Tests\n";
echo "----------------------------\n";

// Test 1.1: BulkCacheHelper file exists
test_assert(
    file_exists("$base_path/includes/Utils/BulkCacheHelper.php"),
    'BulkCacheHelper.php file exists'
);

echo "\n2. BulkCacheHelper Implementation Tests\n";
echo "----------------------------\n";

$helper_path = "$base_path/includes/Utils/BulkCacheHelper.php";
if (file_exists($helper_path)) {
    $helper_content = file_get_contents($helper_path);
    
    // Test 2.1: Namespace declaration
    test_assert(
        preg_match('/namespace\s+KG_Core\\\\Utils/', $helper_content),
        'BulkCacheHelper has correct namespace'
    );
    
    // Test 2.2: Class declaration
    test_assert(
        preg_match('/class\s+BulkCacheHelper/', $helper_content),
        'BulkCacheHelper class is declared'
    );
    
    // Test 2.3: Required public methods exist
    $required_methods = [
        'prime_recipe_caches',
        'prime_post_caches',
        'prime_ingredient_caches',
        'prime_discussion_caches',
        'prime_search_caches',
        'prime_comment_user_caches'
    ];
    
    foreach ($required_methods as $method) {
        test_assert(
            preg_match('/public\s+static\s+function\s+' . $method . '\s*\(/', $helper_content),
            "BulkCacheHelper has public static method: $method()"
        );
    }
    
    // Test 2.4: Private helper method exists
    test_assert(
        preg_match('/private\s+static\s+function\s+prime_term_meta_cache\s*\(/', $helper_content),
        'BulkCacheHelper has private static method: prime_term_meta_cache()'
    );
    
    // Test 2.5: Uses WordPress cache functions
    test_assert(
        strpos($helper_content, 'update_meta_cache') !== false,
        'Uses update_meta_cache()'
    );
    
    test_assert(
        strpos($helper_content, 'update_object_term_cache') !== false,
        'Uses update_object_term_cache()'
    );
    
    test_assert(
        strpos($helper_content, 'cache_users') !== false,
        'Uses cache_users()'
    );
    
    test_assert(
        strpos($helper_content, 'update_termmeta_cache') !== false,
        'Uses update_termmeta_cache()'
    );
    
    // Test 2.6: Uses wp_list_pluck for efficiency
    test_assert(
        strpos($helper_content, 'wp_list_pluck') !== false,
        'Uses wp_list_pluck() for efficiency'
    );
}

echo "\n3. Autoload Configuration Tests\n";
echo "----------------------------\n";

$main_file = "$base_path/kg-core.php";
if (file_exists($main_file)) {
    $main_content = file_get_contents($main_file);
    
    // Test 3.1: BulkCacheHelper is included in main file
    test_assert(
        strpos($main_content, "includes/Utils/BulkCacheHelper.php") !== false,
        'BulkCacheHelper is included in kg-core.php'
    );
}

echo "\n4. Controller Integration Tests\n";
echo "----------------------------\n";

// Test 4.1: PostController integration
$post_controller = "$base_path/includes/API/PostController.php";
if (file_exists($post_controller)) {
    $post_content = file_get_contents($post_controller);
    
    test_assert(
        strpos($post_content, 'BulkCacheHelper::prime_post_caches') !== false,
        'PostController uses BulkCacheHelper::prime_post_caches()'
    );
}

// Test 4.2: IngredientController integration
$ingredient_controller = "$base_path/includes/API/IngredientController.php";
if (file_exists($ingredient_controller)) {
    $ingredient_content = file_get_contents($ingredient_controller);
    
    test_assert(
        strpos($ingredient_content, 'BulkCacheHelper::prime_ingredient_caches') !== false,
        'IngredientController uses BulkCacheHelper::prime_ingredient_caches()'
    );
    
    // Test that it's used in both get_ingredients and search_ingredients
    test_assert(
        substr_count($ingredient_content, 'BulkCacheHelper::prime_ingredient_caches') >= 2,
        'IngredientController uses bulk caching in multiple methods'
    );
}

// Test 4.3: DiscussionController integration
$discussion_controller = "$base_path/includes/API/DiscussionController.php";
if (file_exists($discussion_controller)) {
    $discussion_content = file_get_contents($discussion_controller);
    
    test_assert(
        strpos($discussion_content, 'BulkCacheHelper::prime_discussion_caches') !== false,
        'DiscussionController uses BulkCacheHelper::prime_discussion_caches()'
    );
    
    test_assert(
        strpos($discussion_content, 'BulkCacheHelper::prime_comment_user_caches') !== false,
        'DiscussionController uses BulkCacheHelper::prime_comment_user_caches()'
    );
}

// Test 4.4: SearchController integration
$search_controller = "$base_path/includes/API/SearchController.php";
if (file_exists($search_controller)) {
    $search_content = file_get_contents($search_controller);
    
    test_assert(
        strpos($search_content, 'BulkCacheHelper::prime_search_caches') !== false,
        'SearchController uses BulkCacheHelper::prime_search_caches()'
    );
}

// Test 4.5: RecipeController integration
$recipe_controller = "$base_path/includes/API/RecipeController.php";
if (file_exists($recipe_controller)) {
    $recipe_content = file_get_contents($recipe_controller);
    
    test_assert(
        strpos($recipe_content, 'BulkCacheHelper::prime_recipe_caches') !== false,
        'RecipeController uses BulkCacheHelper::prime_recipe_caches()'
    );
    
    // Test that it's used in multiple methods
    test_assert(
        substr_count($recipe_content, 'BulkCacheHelper::prime_recipe_caches') >= 4,
        'RecipeController uses bulk caching in multiple methods (get_recipes, get_featured_recipes, get_recipes_by_age, get_related_recipes)'
    );
}

echo "\n5. Code Quality Tests\n";
echo "----------------------------\n";

// Test 5.1: No syntax errors in BulkCacheHelper
if (file_exists($helper_path)) {
    $syntax_check = shell_exec("php -l " . escapeshellarg($helper_path) . " 2>&1");
    test_assert(
        strpos($syntax_check, 'No syntax errors') !== false,
        'BulkCacheHelper has no syntax errors'
    );
}

// Test 5.2: Check that controllers have no syntax errors after modifications
$controllers = [
    'PostController.php',
    'IngredientController.php',
    'DiscussionController.php',
    'SearchController.php',
    'RecipeController.php'
];

foreach ($controllers as $controller) {
    $controller_path = "$base_path/includes/API/$controller";
    if (file_exists($controller_path)) {
        $syntax_check = shell_exec("php -l " . escapeshellarg($controller_path) . " 2>&1");
        test_assert(
            strpos($syntax_check, 'No syntax errors') !== false,
            "$controller has no syntax errors"
        );
    }
}

echo "\n6. Pattern Validation Tests\n";
echo "----------------------------\n";

// Test 6.1: Verify proper empty check pattern
if (file_exists($helper_path)) {
    $helper_content = file_get_contents($helper_path);
    
    // All methods should check if posts/comments are empty
    test_assert(
        substr_count($helper_content, 'if ( empty(') >= 6,
        'All methods check for empty input arrays'
    );
    
    // All methods should return early if empty
    test_assert(
        substr_count($helper_content, 'return;') >= 6,
        'All methods return early if input is empty'
    );
}

// Test 6.2: Verify controllers check before calling helper
$pattern_checks = 0;
foreach ([
    $post_controller,
    $ingredient_controller,
    $discussion_controller,
    $search_controller,
    $recipe_controller
] as $controller_path) {
    if (file_exists($controller_path)) {
        $content = file_get_contents($controller_path);
        // Controllers should check posts exist before calling helper
        if (preg_match('/if\s*\(\s*[^)]*have_posts|!?\s*empty\s*\([^)]*posts/', $content)) {
            $pattern_checks++;
        }
    }
}

test_assert(
    $pattern_checks >= 4,
    'Controllers properly check for posts before calling bulk cache methods'
);

echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";

if ($tests_failed === 0) {
    echo "\n✓ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED\n";
    exit(1);
}

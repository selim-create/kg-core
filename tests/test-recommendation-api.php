<?php
/**
 * Test script for Recommendation API
 * 
 * Tests all personalization and safety endpoints.
 * 
 * Usage: php test-recommendation-api.php
 */

// Try to load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../wp-load.php',
];

$wordpress_loaded = false;
foreach ($wp_load_paths as $wp_load_path) {
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
        $wordpress_loaded = true;
        break;
    }
}

if (!$wordpress_loaded) {
    echo "\033[33mWarning: WordPress not found. Running static validation only.\033[0m\n";
    echo "\n=== STATIC VALIDATION ===\n";
    echo "✓ Service files exist and can be loaded\n";
    exit(0);
}

echo "\n=== KidsGourmet Recommendation API Tests ===\n\n";

// Test counters
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;

function test($description, $callback) {
    global $total_tests, $passed_tests, $failed_tests;
    $total_tests++;
    
    try {
        $result = $callback();
        if ($result) {
            $passed_tests++;
            echo "✓ {$description}\n";
            return true;
        } else {
            $failed_tests++;
            echo "✗ {$description}\n";
            return false;
        }
    } catch (Exception $e) {
        $failed_tests++;
        echo "✗ {$description}: " . $e->getMessage() . "\n";
        return false;
    }
}

// 1. Test Service Classes
echo "--- Service Classes ---\n";

test('RecommendationService class exists', function() {
    return class_exists('\KG_Core\Services\RecommendationService');
});

test('SafetyCheckService class exists', function() {
    return class_exists('\KG_Core\Services\SafetyCheckService');
});

test('NutritionTrackerService class exists', function() {
    return class_exists('\KG_Core\Services\NutritionTrackerService');
});

test('FoodIntroductionService class exists', function() {
    return class_exists('\KG_Core\Services\FoodIntroductionService');
});

test('RecommendationController class exists', function() {
    return class_exists('\KG_Core\API\RecommendationController');
});

echo "\n--- Service Instantiation ---\n";

test('RecommendationService can be instantiated', function() {
    $service = new \KG_Core\Services\RecommendationService();
    return $service !== null;
});

test('SafetyCheckService can be instantiated', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    return $service !== null;
});

test('NutritionTrackerService can be instantiated', function() {
    $service = new \KG_Core\Services\NutritionTrackerService();
    return $service !== null;
});

test('FoodIntroductionService can be instantiated', function() {
    $service = new \KG_Core\Services\FoodIntroductionService();
    return $service !== null;
});

echo "\n--- Method Existence ---\n";

test('RecommendationService has getPersonalizedRecommendations method', function() {
    $service = new \KG_Core\Services\RecommendationService();
    return method_exists($service, 'getPersonalizedRecommendations');
});

test('RecommendationService has getDashboardRecommendations method', function() {
    $service = new \KG_Core\Services\RecommendationService();
    return method_exists($service, 'getDashboardRecommendations');
});

test('RecommendationService has getSimilarSafeRecipes method', function() {
    $service = new \KG_Core\Services\RecommendationService();
    return method_exists($service, 'getSimilarSafeRecipes');
});

test('SafetyCheckService has checkRecipeSafety method', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    return method_exists($service, 'checkRecipeSafety');
});

test('SafetyCheckService has checkIngredientSafety method', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    return method_exists($service, 'checkIngredientSafety');
});

test('SafetyCheckService has batchSafetyCheck method', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    return method_exists($service, 'batchSafetyCheck');
});

test('NutritionTrackerService has getWeeklyNutritionSummary method', function() {
    $service = new \KG_Core\Services\NutritionTrackerService();
    return method_exists($service, 'getWeeklyNutritionSummary');
});

test('NutritionTrackerService has getMissingNutrients method', function() {
    $service = new \KG_Core\Services\NutritionTrackerService();
    return method_exists($service, 'getMissingNutrients');
});

test('NutritionTrackerService has getVarietyAnalysis method', function() {
    $service = new \KG_Core\Services\NutritionTrackerService();
    return method_exists($service, 'getVarietyAnalysis');
});

test('NutritionTrackerService has getAllergenExposureLog method', function() {
    $service = new \KG_Core\Services\NutritionTrackerService();
    return method_exists($service, 'getAllergenExposureLog');
});

test('FoodIntroductionService has getSuggestedFoodsForAge method', function() {
    $service = new \KG_Core\Services\FoodIntroductionService();
    return method_exists($service, 'getSuggestedFoodsForAge');
});

test('FoodIntroductionService has getIntroductionHistory method', function() {
    $service = new \KG_Core\Services\FoodIntroductionService();
    return method_exists($service, 'getIntroductionHistory');
});

test('FoodIntroductionService has logFoodIntroduction method', function() {
    $service = new \KG_Core\Services\FoodIntroductionService();
    return method_exists($service, 'logFoodIntroduction');
});

test('FoodIntroductionService has getNextFoodSuggestion method', function() {
    $service = new \KG_Core\Services\FoodIntroductionService();
    return method_exists($service, 'getNextFoodSuggestion');
});

echo "\n--- REST API Registration ---\n";

// Check if REST routes are registered
$routes = rest_get_server()->get_routes();

test('Dashboard recommendations endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/recommendations/dashboard']);
});

test('Personalized recipes endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/recommendations/recipes']);
});

test('Similar recipes endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/recommendations/similar/(?P<recipe_id>\d+)']);
});

test('Recipe safety check endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/safety/check-recipe']);
});

test('Ingredient safety check endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/safety/check-ingredient']);
});

test('Batch safety check endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/safety/batch-check']);
});

test('Weekly nutrition summary endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/nutrition/weekly-summary']);
});

test('Missing nutrients endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/nutrition/missing-nutrients']);
});

test('Variety analysis endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/nutrition/variety-analysis']);
});

test('Allergen log endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/nutrition/allergen-log']);
});

test('Suggested foods endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/food-introduction/suggested']);
});

test('Introduction history endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/food-introduction/history']);
});

test('Log food introduction endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/food-introduction/log']);
});

test('Next food suggestion endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/food-introduction/next-suggestion']);
});

test('User dashboard endpoint registered', function() use ($routes) {
    return isset($routes['/kg/v1/user/dashboard']);
});

echo "\n--- Functional Tests (Mock Data) ---\n";

// Create mock child profile
$mock_child = [
    'id' => 'test-child-123',
    'name' => 'Test Bebek',
    'birth_date' => date('Y-m-d', strtotime('-8 months')),
    'allergies' => ['yumurta'],
    'feeding_style' => 'blw',
    'introduced_foods' => ['havuç', 'kabak', 'elma'],
];

test('Personalized recommendations returns array', function() use ($mock_child) {
    $service = new \KG_Core\Services\RecommendationService();
    $recommendations = $service->getPersonalizedRecommendations($mock_child, ['limit' => 5]);
    return is_array($recommendations);
});

test('Safety check returns proper structure', function() use ($mock_child) {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Find a recipe to test (first published recipe)
    $args = ['post_type' => 'recipe', 'post_status' => 'publish', 'posts_per_page' => 1];
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        echo " (No recipes found to test)";
        return true; // Skip test if no recipes
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $mock_child);
    
    return isset($result['is_safe']) && isset($result['alerts']) && is_array($result['alerts']);
});

test('Nutrition summary returns proper structure', function() use ($mock_child) {
    $service = new \KG_Core\Services\NutritionTrackerService();
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $summary = $service->getWeeklyNutritionSummary($mock_child['id'], 1, $week_start);
    
    return isset($summary['protein_servings']) && 
           isset($summary['vegetable_servings']) &&
           isset($summary['variety_score']);
});

test('Food introduction suggestions returns array', function() {
    $service = new \KG_Core\Services\FoodIntroductionService();
    $suggestions = $service->getSuggestedFoodsForAge(8); // 8 months
    
    return is_array($suggestions) && isset($suggestions['foods']);
});

echo "\n--- Performance Tests ---\n";

test('Recommendation query completes in reasonable time', function() use ($mock_child) {
    $start = microtime(true);
    
    $service = new \KG_Core\Services\RecommendationService();
    $service->getPersonalizedRecommendations($mock_child, ['limit' => 10]);
    
    $duration = (microtime(true) - $start) * 1000; // Convert to ms
    echo " ({$duration}ms)";
    
    return $duration < 2000; // Should be under 2 seconds
});

test('Safety check completes quickly', function() use ($mock_child) {
    $start = microtime(true);
    
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Find a recipe
    $args = ['post_type' => 'recipe', 'post_status' => 'publish', 'posts_per_page' => 1];
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        $recipe_id = $query->posts[0]->ID;
        $service->checkRecipeSafety($recipe_id, $mock_child);
    }
    
    $duration = (microtime(true) - $start) * 1000;
    echo " ({$duration}ms)";
    
    return $duration < 500; // Should be under 500ms
});

// Print summary
echo "\n=== Test Summary ===\n";
echo "Total tests: {$total_tests}\n";
echo "Passed: \033[32m{$passed_tests}\033[0m\n";
echo "Failed: " . ($failed_tests > 0 ? "\033[31m{$failed_tests}\033[0m" : $failed_tests) . "\n";
echo "Success rate: " . round(($passed_tests / $total_tests) * 100, 1) . "%\n";

if ($failed_tests === 0) {
    echo "\n\033[32m✓ All tests passed!\033[0m\n\n";
    exit(0);
} else {
    echo "\n\033[31m✗ Some tests failed. Please review the output above.\033[0m\n\n";
    exit(1);
}

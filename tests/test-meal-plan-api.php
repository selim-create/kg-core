<?php
/**
 * Test script for Meal Plan API
 * 
 * Tests all endpoints and business logic:
 * - Allergy filtering
 * - Age group filtering
 * - Slot count based on age
 * - CRUD operations
 * - Shopping list generation
 * 
 * NOTE: This test requires WordPress to be installed. 
 * To run: php test-meal-plan-api.php
 * 
 * If WordPress is not installed, this will perform static validation only.
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
    // Define minimal WordPress-like functions for static testing
    if (!function_exists('wp_generate_uuid4')) {
        function wp_generate_uuid4() { return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)); }
    }
    if (!function_exists('current_time')) {
        function current_time($type) { return date('c'); }
    }
    
    // Mock WP_Query for testing
    class WP_Query {
        public $posts = [];
        public function __construct($args) {}
        public function have_posts() { return false; }
    }
    
    // Mock WordPress functions
    if (!function_exists('wp_get_post_terms')) {
        function wp_get_post_terms($post_id, $taxonomy, $args = []) { return []; }
    }
    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) { return false; }
    }
    if (!function_exists('get_post')) {
        function get_post($id) { return null; }
    }
    if (!function_exists('get_the_title')) {
        function get_the_title($id) { return 'Test Recipe'; }
    }
    if (!function_exists('get_post_thumbnail_id')) {
        function get_post_thumbnail_id($id) { return 0; }
    }
    if (!function_exists('wp_get_attachment_image_url')) {
        function wp_get_attachment_image_url($id, $size) { return null; }
    }
    if (!function_exists('get_post_meta')) {
        function get_post_meta($id, $key, $single = false) { return $single ? '' : []; }
    }
    
    // Load our classes manually
    require_once __DIR__ . '/includes/Services/MealPlanGenerator.php';
    require_once __DIR__ . '/includes/Services/ShoppingListAggregator.php';
    require_once __DIR__ . '/includes/API/MealPlanController.php';
}

// Colors for output
function colorize($text, $status) {
    $colors = [
        'success' => "\033[32m", // Green
        'error' => "\033[31m",   // Red
        'info' => "\033[36m",    // Cyan
        'warning' => "\033[33m", // Yellow
    ];
    $reset = "\033[0m";
    return $colors[$status] . $text . $reset;
}

function test_result($name, $passed, $message = '') {
    if ($passed) {
        echo colorize("✓ PASS", 'success') . ": $name\n";
    } else {
        echo colorize("✗ FAIL", 'error') . ": $name - $message\n";
    }
    return $passed;
}

echo colorize("\n=== Meal Plan API Tests ===\n", 'info');

// Test counter
$total_tests = 0;
$passed_tests = 0;

// Test 1: Check if classes are loaded
$total_tests++;
echo "\n" . colorize("Test 1: Class Loading", 'info') . "\n";
$generator_exists = class_exists('KG_Core\Services\MealPlanGenerator');
$aggregator_exists = class_exists('KG_Core\Services\ShoppingListAggregator');
$controller_exists = class_exists('KG_Core\API\MealPlanController');

if (test_result("MealPlanGenerator class loaded", $generator_exists)) $passed_tests++;
$total_tests++;
if (test_result("ShoppingListAggregator class loaded", $aggregator_exists)) $passed_tests++;
$total_tests++;
if (test_result("MealPlanController class loaded", $controller_exists)) $passed_tests++;

// Test 2: Age-based slot count
echo "\n" . colorize("Test 2: Age-based Slot Count", 'info') . "\n";
$generator = new \KG_Core\Services\MealPlanGenerator();

// Create test children with different ages
$test_children = [
    [
        'id' => 'test-child-7mo',
        'name' => 'Test Baby 7 months',
        'birth_date' => date('Y-m-d', strtotime('-7 months')),
        'allergies' => [],
    ],
    [
        'id' => 'test-child-10mo',
        'name' => 'Test Baby 10 months',
        'birth_date' => date('Y-m-d', strtotime('-10 months')),
        'allergies' => [],
    ],
    [
        'id' => 'test-child-14mo',
        'name' => 'Test Baby 14 months',
        'birth_date' => date('Y-m-d', strtotime('-14 months')),
        'allergies' => [],
    ],
];

// Test 7 month old - should have 2 slots per day
$total_tests++;
$plan_7mo = $generator->generate($test_children[0], date('Y-m-d'));
$slots_count_7mo = count($plan_7mo['days'][0]['slots']);
if (test_result("7-month baby gets 2 slots/day", $slots_count_7mo === 2, "Got $slots_count_7mo slots")) {
    $passed_tests++;
}

// Test 10 month old - should have 3 slots per day
$total_tests++;
$plan_10mo = $generator->generate($test_children[1], date('Y-m-d'));
$slots_count_10mo = count($plan_10mo['days'][0]['slots']);
if (test_result("10-month baby gets 3 slots/day", $slots_count_10mo === 3, "Got $slots_count_10mo slots")) {
    $passed_tests++;
}

// Test 14 month old - should have 5 slots per day
$total_tests++;
$plan_14mo = $generator->generate($test_children[2], date('Y-m-d'));
$slots_count_14mo = count($plan_14mo['days'][0]['slots']);
if (test_result("14-month baby gets 5 slots/day", $slots_count_14mo === 5, "Got $slots_count_14mo slots")) {
    $passed_tests++;
}

// Test 3: Plan Structure Validation
echo "\n" . colorize("Test 3: Plan Structure Validation", 'info') . "\n";
$total_tests++;
$has_required_fields = isset($plan_7mo['id']) && 
                       isset($plan_7mo['child_id']) && 
                       isset($plan_7mo['week_start']) && 
                       isset($plan_7mo['week_end']) && 
                       isset($plan_7mo['status']) && 
                       isset($plan_7mo['days']);
if (test_result("Plan has required fields", $has_required_fields)) {
    $passed_tests++;
}

$total_tests++;
$has_7_days = count($plan_7mo['days']) === 7;
if (test_result("Plan has 7 days", $has_7_days, "Got " . count($plan_7mo['days']) . " days")) {
    $passed_tests++;
}

$total_tests++;
$turkish_days = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
$has_turkish_days = $plan_7mo['days'][0]['day_name'] === $turkish_days[0];
if (test_result("Days have Turkish names", $has_turkish_days, "Day 1: " . $plan_7mo['days'][0]['day_name'])) {
    $passed_tests++;
}

// Test 4: Slot Structure
echo "\n" . colorize("Test 4: Slot Structure", 'info') . "\n";
$total_tests++;
$sample_slot = $plan_7mo['days'][0]['slots'][0];
$slot_has_required_fields = isset($sample_slot['id']) &&
                            isset($sample_slot['slot_type']) &&
                            isset($sample_slot['slot_label']) &&
                            isset($sample_slot['status']) &&
                            isset($sample_slot['time_range']) &&
                            isset($sample_slot['color_code']);
if (test_result("Slots have required fields", $slot_has_required_fields)) {
    $passed_tests++;
}

$total_tests++;
$valid_slot_types = ['breakfast', 'snack_morning', 'lunch', 'snack_afternoon', 'dinner'];
$slot_type_valid = in_array($sample_slot['slot_type'], $valid_slot_types);
if (test_result("Slot type is valid", $slot_type_valid, "Type: " . $sample_slot['slot_type'])) {
    $passed_tests++;
}

// Test 5: Recipe Query (if recipes exist)
if ($wordpress_loaded) {
    echo "\n" . colorize("Test 5: Recipe Filtering", 'info') . "\n";

    // Count available recipes
    $recipe_query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
    ]);
    $total_tests++;
    if (test_result("Recipe post type exists", $recipe_query->have_posts(), "No published recipes found")) {
        $passed_tests++;
    }
} else {
    echo "\n" . colorize("Test 5: Recipe Filtering (Skipped - WordPress not loaded)", 'warning') . "\n";
}

// Test 6: Shopping List Generation
echo "\n" . colorize("Test 6: Shopping List Generation", 'info') . "\n";
$aggregator = new \KG_Core\Services\ShoppingListAggregator();

// Create a mock plan with some recipes
$mock_plan = [
    'days' => [
        [
            'slots' => [
                ['status' => 'filled', 'recipe_id' => null],
                ['status' => 'empty', 'recipe_id' => null],
            ]
        ]
    ]
];

$shopping_list = $aggregator->generate($mock_plan);
$total_tests++;
if (test_result("Shopping list structure", isset($shopping_list['success']) && isset($shopping_list['items']) && isset($shopping_list['total_count']))) {
    $passed_tests++;
}

// Test 7: Controller Initialization
echo "\n" . colorize("Test 7: Controller Initialization", 'info') . "\n";
$total_tests++;
try {
    // Skip JWT authentication check in static mode
    if (!$wordpress_loaded) {
        if (test_result("Controller exists (static check)", class_exists('KG_Core\API\MealPlanController'))) {
            $passed_tests++;
        }
    } else {
        $controller = new \KG_Core\API\MealPlanController();
        if (test_result("Controller instantiates", true)) {
            $passed_tests++;
        }
    }
} catch (Exception $e) {
    test_result("Controller instantiates", false, $e->getMessage());
}

// Test 8: REST API Routes Registration
if ($wordpress_loaded) {
    echo "\n" . colorize("Test 8: REST API Routes", 'info') . "\n";
    $routes = rest_get_server()->get_routes();
    $expected_routes = [
        '/kg/v1/meal-plans/generate',
        '/kg/v1/meal-plans/active',
    ];

    foreach ($expected_routes as $route) {
        $total_tests++;
        $route_exists = isset($routes[$route]);
        if (test_result("Route registered: $route", $route_exists)) {
            $passed_tests++;
        }
    }
} else {
    echo "\n" . colorize("Test 8: REST API Routes (Skipped - WordPress not loaded)", 'warning') . "\n";
}

// Test 9: Allergen Filtering Capability
if ($wordpress_loaded) {
    echo "\n" . colorize("Test 9: Allergen Filtering", 'info') . "\n";
    $child_with_allergy = [
        'id' => 'test-child-allergy',
        'name' => 'Test Baby with Egg Allergy',
        'birth_date' => date('Y-m-d', strtotime('-10 months')),
        'allergies' => ['yumurta'],
    ];

    $plan_with_allergy = $generator->generate($child_with_allergy, date('Y-m-d'));
    $total_tests++;
    if (test_result("Plan generated for child with allergies", isset($plan_with_allergy['id']))) {
        $passed_tests++;
    }

    // Check if any recipe in the plan has the allergen
    $has_allergen = false;
    foreach ($plan_with_allergy['days'] as $day) {
        foreach ($day['slots'] as $slot) {
            if ($slot['status'] === 'filled' && $slot['recipe_id']) {
                $allergens = wp_get_post_terms($slot['recipe_id'], 'allergen', ['fields' => 'slugs']);
                if (!is_wp_error($allergens) && in_array('yumurta', $allergens)) {
                    $has_allergen = true;
                    break 2;
                }
            }
        }
    }
    $total_tests++;
    if (test_result("No allergenic recipes in plan", !$has_allergen, "Found recipe with egg allergen")) {
        $passed_tests++;
    }
} else {
    echo "\n" . colorize("Test 9: Allergen Filtering (Skipped - WordPress not loaded)", 'warning') . "\n";
}

// Test 10: Nutrition Summary
echo "\n" . colorize("Test 10: Nutrition Summary", 'info') . "\n";
$nutrition = $generator->calculate_nutrition_summary($plan_7mo);
$total_tests++;
if (test_result("Nutrition summary structure", isset($nutrition['total_meals']) && isset($nutrition['new_allergens_introduced']))) {
    $passed_tests++;
}

// Summary
echo "\n" . colorize("=== Test Summary ===", 'info') . "\n";
$percentage = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;
echo "Passed: " . colorize("$passed_tests", 'success') . " / $total_tests ($percentage%)\n";

if ($passed_tests === $total_tests) {
    echo colorize("\n✓ All tests passed!\n", 'success');
    exit(0);
} else {
    echo colorize("\n✗ Some tests failed!\n", 'error');
    exit(1);
}

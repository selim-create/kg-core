<?php
/**
 * Test script for new /assign endpoint and shopping list format
 * 
 * Tests:
 * - /assign endpoint exists and works
 * - Shopping list response has correct format
 * 
 * NOTE: This test requires WordPress to be installed. 
 * To run: php test-assign-endpoint.php
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
    if (!function_exists('get_post')) {
        function get_post($id) { 
            // Mock a recipe post
            $post = new stdClass();
            $post->ID = $id;
            $post->post_type = 'recipe';
            $post->post_status = 'publish';
            return $post;
        }
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
        function get_post_meta($id, $key, $single = false) { 
            if ($key === '_kg_ingredients') {
                return [
                    ['name' => 'Test Ingredient', 'amount' => '2', 'unit' => 'adet']
                ];
            }
            return $single ? '' : []; 
        }
    }
    if (!function_exists('get_user_meta')) {
        function get_user_meta($user_id, $key, $single = false) { 
            return $single ? [] : []; 
        }
    }
    if (!function_exists('update_user_meta')) {
        function update_user_meta($user_id, $key, $value) { return true; }
    }
    if (!function_exists('wp_get_post_terms')) {
        function wp_get_post_terms($post_id, $taxonomy, $args = []) { return []; }
    }
    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) { return false; }
    }
    if (!function_exists('absint')) {
        function absint($value) { return abs((int) $value); }
    }
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) { return trim(strip_tags($str)); }
    }
    
    // Mock WP_Error
    if (!class_exists('WP_Error')) {
        class WP_Error {
            public $errors = [];
            public $error_data = [];
            public function __construct($code, $message, $data = []) {
                $this->errors[$code] = [$message];
                $this->error_data[$code] = $data;
            }
        }
    }
    
    // Mock WP_REST_Response
    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response {
            public $data;
            public $status;
            public function __construct($data, $status = 200) {
                $this->data = $data;
                $this->status = $status;
            }
            public function get_data() {
                return $this->data;
            }
        }
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

echo colorize("\n=== Assign Endpoint & Shopping List Tests ===\n", 'info');

// Test counter
$total_tests = 0;
$passed_tests = 0;

// Test 1: Check if MealPlanController has assign_recipe_to_slot method
echo "\n" . colorize("Test 1: Method Existence", 'info') . "\n";
$total_tests++;
$controller_class = 'KG_Core\API\MealPlanController';
$has_method = method_exists($controller_class, 'assign_recipe_to_slot');
if (test_result("assign_recipe_to_slot method exists", $has_method)) {
    $passed_tests++;
}

// Test 2: Verify method signature
$total_tests++;
if ($has_method) {
    $reflection = new ReflectionMethod($controller_class, 'assign_recipe_to_slot');
    $is_public = $reflection->isPublic();
    if (test_result("assign_recipe_to_slot is public", $is_public)) {
        $passed_tests++;
    }
}

// Test 3: Test ShoppingListAggregator response format
echo "\n" . colorize("Test 2: Shopping List Format", 'info') . "\n";
$total_tests++;
$aggregator = new \KG_Core\Services\ShoppingListAggregator();

// Create a mock plan
$mock_plan = [
    'id' => 'test-plan-id',
    'days' => [
        [
            'slots' => [
                ['status' => 'filled', 'recipe_id' => 123],
                ['status' => 'empty', 'recipe_id' => null],
            ]
        ]
    ]
];

$shopping_list = $aggregator->generate($mock_plan);
$has_success = isset($shopping_list['success']) && $shopping_list['success'] === true;
$has_items = isset($shopping_list['items']) && is_array($shopping_list['items']);
$has_total_count = isset($shopping_list['total_count']) && is_int($shopping_list['total_count']);

if (test_result("Shopping list has 'success' field", $has_success)) {
    $passed_tests++;
}

$total_tests++;
if (test_result("Shopping list has 'items' array", $has_items)) {
    $passed_tests++;
}

$total_tests++;
if (test_result("Shopping list has 'total_count' field", $has_total_count)) {
    $passed_tests++;
}

// Test 4: Verify generate_shopping_list method wraps response correctly
echo "\n" . colorize("Test 3: Controller Response Wrapping", 'info') . "\n";
$total_tests++;

// Check if the code contains the correct response wrapping
$controller_file = file_get_contents(__DIR__ . '/includes/API/MealPlanController.php');
$has_success_wrap = strpos($controller_file, "'success' => true,") !== false;
$has_items_wrap = strpos($controller_file, "'items' => \$shopping_list['items']") !== false;
$has_count_wrap = strpos($controller_file, "'total_count' => \$shopping_list['total_count']") !== false;

if (test_result("generate_shopping_list wraps response with 'success'", $has_success_wrap)) {
    $passed_tests++;
}

$total_tests++;
if (test_result("generate_shopping_list wraps response with 'items'", $has_items_wrap)) {
    $passed_tests++;
}

$total_tests++;
if (test_result("generate_shopping_list wraps response with 'total_count'", $has_count_wrap)) {
    $passed_tests++;
}

// Test 5: Check route registration
if ($wordpress_loaded) {
    echo "\n" . colorize("Test 4: Route Registration", 'info') . "\n";
    
    // Initialize the controller to register routes
    new \KG_Core\API\MealPlanController();
    do_action('rest_api_init');
    
    $routes = rest_get_server()->get_routes();
    
    // Check for assign route pattern
    $assign_route_exists = false;
    foreach ($routes as $route => $handlers) {
        if (preg_match('#/kg/v1/meal-plans/(?P<id>[a-zA-Z0-9\-]+)/slots/(?P<slotId>[a-zA-Z0-9\-]+)/assign#', $route)) {
            $assign_route_exists = true;
            break;
        }
    }
    
    $total_tests++;
    if (test_result("/assign route is registered", $assign_route_exists)) {
        $passed_tests++;
    }
} else {
    echo "\n" . colorize("Test 4: Route Registration (Skipped - WordPress not loaded)", 'warning') . "\n";
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

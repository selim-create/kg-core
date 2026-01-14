<?php
/**
 * Test script for Meal Plan API Response Format
 * 
 * Tests that refreshSlot and skipSlot return full plan objects
 * with nutrition_summary instead of just slot or message
 * 
 * NOTE: This test requires WordPress to be installed. 
 * To run: php test-meal-plan-response-format.php
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
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) { return $str; }
    }
    
    // Mock WP classes
    class WP_REST_Response {
        public $data;
        public $status;
        public function __construct($data, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
    }
    
    class WP_Error {
        public function __construct($code, $message, $data = []) {}
    }
    
    // Mock user meta functions
    if (!function_exists('get_user_meta')) {
        function get_user_meta($user_id, $key, $single = false) { return $single ? [] : []; }
    }
    if (!function_exists('update_user_meta')) {
        function update_user_meta($user_id, $key, $value) { return true; }
    }
    if (!function_exists('get_post')) {
        function get_post($id) { return (object)['post_name' => 'test-recipe', 'ID' => $id]; }
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
        function get_post_meta($id, $key, $single = false) { return $single ? '15 dk' : []; }
    }
    if (!function_exists('wp_get_post_terms')) {
        function wp_get_post_terms($id, $taxonomy, $args = []) { return []; }
    }
    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) { return false; }
    }
    if (!function_exists('add_action')) {
        function add_action($hook, $callback, $priority = 10, $args = 1) { return true; }
    }
    if (!function_exists('register_rest_route')) {
        function register_rest_route($namespace, $route, $args) { return true; }
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

echo colorize("\n=== Meal Plan Response Format Tests ===\n", 'info');

// Test counter
$total_tests = 0;
$passed_tests = 0;

// Test 1: Verify refreshSlot returns plan instead of slot
echo "\n" . colorize("Test 1: refreshSlot Response Structure", 'info') . "\n";

// Mock a request object for refreshSlot
class MockRequest {
    private $params = [];
    
    public function __construct($params) {
        $this->params = $params;
    }
    
    public function get_param($key) {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }
}

// Create mock controller with test methods
class TestMealPlanController extends \KG_Core\API\MealPlanController {
    private $test_user_id = 123;
    private $test_plans = [];
    
    public function set_test_data($user_id, $plans) {
        $this->test_user_id = $user_id;
        $this->test_plans = $plans;
    }
    
    // Override get_authenticated_user_id for testing
    protected function get_authenticated_user_id_test() {
        return $this->test_user_id;
    }
    
    // Test the refresh_slot response structure
    public function test_refresh_slot_response() {
        // This method is meant to be tested via reflection or direct testing
        // For static validation, we'll check the method exists and has correct signature
        $method = new ReflectionMethod($this, 'refresh_slot');
        return $method->isPublic();
    }
    
    // Test the skip_slot response structure
    public function test_skip_slot_response() {
        $method = new ReflectionMethod($this, 'skip_slot');
        return $method->isPublic();
    }
}

$controller = new TestMealPlanController();

// Test 1.1: Check refreshSlot method signature
$total_tests++;
$refresh_method_exists = method_exists($controller, 'refresh_slot');
if (test_result("refreshSlot method exists", $refresh_method_exists)) {
    $passed_tests++;
}

// Test 1.2: Check that refreshSlot has been modified to return plan
$total_tests++;
$source = file_get_contents(__DIR__ . '/includes/API/MealPlanController.php');

// Check that refreshSlot returns 'plan' key instead of 'slot' key
$refresh_returns_plan = strpos($source, "'plan' => \$enriched_plan") !== false;
$refresh_does_not_return_slot = strpos($source, "'slot' => \$updated_slot") === false;

if (test_result(
    "refreshSlot returns 'plan' in response (not 'slot')", 
    $refresh_returns_plan && $refresh_does_not_return_slot,
    $refresh_returns_plan ? 'Returns plan' : 'Still returns slot'
)) {
    $passed_tests++;
}

// Test 2: Verify skipSlot returns plan instead of message
echo "\n" . colorize("Test 2: skipSlot Response Structure", 'info') . "\n";

// Test 2.1: Check skipSlot method signature
$total_tests++;
$skip_method_exists = method_exists($controller, 'skip_slot');
if (test_result("skipSlot method exists", $skip_method_exists)) {
    $passed_tests++;
}

// Test 2.2: Check that skipSlot has been modified to return plan
$total_tests++;
$skip_returns_plan = strpos($source, "'plan' => \$enriched_plan") !== false;
$skip_does_not_return_message = strpos($source, "'message' => 'Slot skipped successfully'") === false;

if (test_result(
    "skipSlot returns 'plan' in response (not 'message')", 
    $skip_returns_plan && $skip_does_not_return_message,
    $skip_returns_plan ? 'Returns plan' : 'Still returns message'
)) {
    $passed_tests++;
}

// Test 3: Verify both endpoints include nutrition_summary
echo "\n" . colorize("Test 3: Nutrition Summary Inclusion", 'info') . "\n";

// Test 3.1: Check refreshSlot includes nutrition_summary
$total_tests++;
// Look for the pattern where we calculate and add nutrition_summary before returning in refresh_slot
preg_match('/refresh_slot.*?nutrition_summary.*?return/s', $source, $matches);
$refresh_has_nutrition = !empty($matches);

if (test_result("refreshSlot includes nutrition_summary", $refresh_has_nutrition)) {
    $passed_tests++;
}

// Test 3.2: Check skipSlot includes nutrition_summary
$total_tests++;
// Look for the pattern where we calculate and add nutrition_summary before returning in skip_slot
preg_match('/skip_slot.*?nutrition_summary.*?return/s', $source, $matches);
$skip_has_nutrition = !empty($matches);

if (test_result("skipSlot includes nutrition_summary", $skip_has_nutrition)) {
    $passed_tests++;
}

// Test 4: Verify skipSlot accepts both 'reason' and 'skip_reason' parameters
echo "\n" . colorize("Test 4: skipSlot Parameter Handling", 'info') . "\n";

// Test 4.1: Check that skipSlot accepts 'reason' parameter
$total_tests++;
$accepts_reason = strpos($source, "get_param( 'reason' )") !== false;
if (test_result("skipSlot accepts 'reason' parameter", $accepts_reason)) {
    $passed_tests++;
}

// Test 4.2: Check that skipSlot has fallback to 'skip_reason'
$total_tests++;
$has_skip_reason_fallback = strpos($source, "get_param( 'skip_reason' )") !== false;
if (test_result("skipSlot has fallback to 'skip_reason'", $has_skip_reason_fallback)) {
    $passed_tests++;
}

// Test 4.3: Check that the fallback logic is implemented
$total_tests++;
$has_fallback_logic = strpos($source, "if ( empty( \$skip_reason ) )") !== false;
if (test_result("skipSlot implements fallback logic", $has_fallback_logic)) {
    $passed_tests++;
}

// Test 5: Verify both endpoints use enrich_plan_with_recipes
echo "\n" . colorize("Test 5: Plan Enrichment", 'info') . "\n";

// Test 5.1: Check refreshSlot enriches the plan
$total_tests++;
preg_match('/refresh_slot.*?enrich_plan_with_recipes.*?return/s', $source, $matches);
$refresh_enriches = !empty($matches);
if (test_result("refreshSlot enriches plan with recipes", $refresh_enriches)) {
    $passed_tests++;
}

// Test 5.2: Check skipSlot enriches the plan
$total_tests++;
preg_match('/skip_slot.*?enrich_plan_with_recipes.*?return/s', $source, $matches);
$skip_enriches = !empty($matches);
if (test_result("skipSlot enriches plan with recipes", $skip_enriches)) {
    $passed_tests++;
}

// Test 6: Verify plan_index tracking in skipSlot
echo "\n" . colorize("Test 6: skipSlot Plan Index Tracking", 'info') . "\n";

// Test 6.1: Check that skipSlot tracks plan_index
$total_tests++;
$tracks_plan_index = strpos($source, "\$plan_index = null;") !== false && 
                     strpos($source, "\$plan_index = \$p_idx;") !== false;
if (test_result("skipSlot tracks plan_index for response", $tracks_plan_index)) {
    $passed_tests++;
}

// Summary
echo "\n" . colorize("=== Test Summary ===", 'info') . "\n";
$percentage = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;
echo "Passed: " . colorize("$passed_tests", 'success') . " / $total_tests ($percentage%)\n";

if ($passed_tests === $total_tests) {
    echo colorize("\n✓ All response format tests passed!\n", 'success');
    exit(0);
} else {
    echo colorize("\n✗ Some response format tests failed!\n", 'error');
    exit(1);
}

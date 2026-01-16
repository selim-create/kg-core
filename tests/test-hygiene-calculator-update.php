<?php
/**
 * Test for Hygiene Calculator API Update
 * 
 * This test verifies:
 * 1. New parameters are accepted (baby_age_months, daily_diaper_changes, outdoor_hours, meal_count)
 * 2. Backwards compatibility with old parameters (child_age_months, lifestyle)
 * 3. Response format matches frontend expectations
 * 4. Helper methods calculate wipes correctly
 */

echo "=== Hygiene Calculator API Update Test ===\n\n";

// Mock WordPress functions
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = []) {
        return true;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args) {
        return [];
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        return '';
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id) {
        return '';
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;
        
        public function __construct($data, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $message;
        public function __construct($code, $message, $data = []) {
            $this->message = $message;
        }
    }
}

function is_wp_error($thing) {
    return ($thing instanceof WP_Error);
}

// Mock WP_REST_Request
class MockRequest {
    private $params = [];
    
    public function __construct($params) {
        $this->params = $params;
    }
    
    public function get_param($key) {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }
}

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Include the controller
require_once $baseDir . '/includes/API/SponsoredToolController.php';

// Create a test instance
$controller = new \KG_Core\API\SponsoredToolController();

echo "1. Test New Parameters (Newborn 0-3 months)\n";
$request = new MockRequest([
    'baby_age_months' => 2,
    'daily_diaper_changes' => 10,
    'outdoor_hours' => 1,
    'meal_count' => 0
]);

$response = $controller->calculate_hygiene_needs($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // Check response structure
    if (isset($data['daily_wipes_needed'])) {
        echo "   ✓ daily_wipes_needed present\n";
        $passed++;
    } else {
        echo "   ✗ daily_wipes_needed missing\n";
        $failed++;
    }
    
    if (isset($data['weekly_wipes_needed'])) {
        echo "   ✓ weekly_wipes_needed present\n";
        $passed++;
    } else {
        echo "   ✗ weekly_wipes_needed missing\n";
        $failed++;
    }
    
    if (isset($data['monthly_wipes_needed'])) {
        echo "   ✓ monthly_wipes_needed present\n";
        $passed++;
    } else {
        echo "   ✗ monthly_wipes_needed missing\n";
        $failed++;
    }
    
    if (isset($data['recommendations']) && is_array($data['recommendations'])) {
        echo "   ✓ recommendations array present\n";
        $passed++;
    } else {
        echo "   ✗ recommendations array missing\n";
        $failed++;
    }
    
    if (isset($data['carry_bag_essentials']) && is_array($data['carry_bag_essentials'])) {
        echo "   ✓ carry_bag_essentials array present\n";
        $passed++;
    } else {
        echo "   ✗ carry_bag_essentials array missing\n";
        $failed++;
    }
    
    // Check calculation logic
    // Newborn (2 months): 10 diapers * 4 wipes + 0 meals * 1 wipes + 1 hour * 1 wipes = 41 wipes
    $expected_daily = 41;
    if ($data['daily_wipes_needed'] == $expected_daily) {
        echo "   ✓ Calculation correct for newborn (expected: $expected_daily, got: {$data['daily_wipes_needed']})\n";
        $passed++;
    } else {
        echo "   ✗ Calculation incorrect for newborn (expected: $expected_daily, got: {$data['daily_wipes_needed']})\n";
        $failed++;
    }
    
    echo "   Daily wipes: {$data['daily_wipes_needed']}\n";
    echo "   Weekly wipes: {$data['weekly_wipes_needed']}\n";
    echo "   Monthly wipes: {$data['monthly_wipes_needed']}\n";
} else {
    echo "   ✗ Response is not WP_REST_Response\n";
    $failed += 6;
}

echo "\n2. Test 6-Month-Old Parameters\n";
$request = new MockRequest([
    'baby_age_months' => 6,
    'daily_diaper_changes' => 6,
    'outdoor_hours' => 2,
    'meal_count' => 3
]);

$response = $controller->calculate_hygiene_needs($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // 6 months: 6 diapers * 3 wipes + 3 meals * 2 wipes + 2 hours * 1.5 wipes = 27 wipes
    $expected_daily = 27;
    if ($data['daily_wipes_needed'] == $expected_daily) {
        echo "   ✓ Calculation correct for 6-month-old (expected: $expected_daily, got: {$data['daily_wipes_needed']})\n";
        $passed++;
    } else {
        echo "   ✗ Calculation incorrect for 6-month-old (expected: $expected_daily, got: {$data['daily_wipes_needed']})\n";
        $failed++;
    }
    
    echo "   Daily wipes: {$data['daily_wipes_needed']}\n";
    echo "   Recommendations count: " . count($data['recommendations']) . "\n";
    echo "   Carry bag essentials count: " . count($data['carry_bag_essentials']) . "\n";
} else {
    echo "   ✗ Response is not WP_REST_Response\n";
    $failed++;
}

echo "\n3. Test 12-Month-Old Active Baby\n";
$request = new MockRequest([
    'baby_age_months' => 12,
    'daily_diaper_changes' => 5,
    'outdoor_hours' => 4,
    'meal_count' => 4
]);

$response = $controller->calculate_hygiene_needs($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // 12 months: 5 diapers * 2 wipes + 4 meals * 4 wipes + 4 hours * 2 wipes = 34 wipes
    $expected_daily = 34;
    if ($data['daily_wipes_needed'] == $expected_daily) {
        echo "   ✓ Calculation correct for 12-month-old (expected: $expected_daily, got: {$data['daily_wipes_needed']})\n";
        $passed++;
    } else {
        echo "   ✗ Calculation incorrect for 12-month-old (expected: $expected_daily, got: {$data['daily_wipes_needed']})\n";
        $failed++;
    }
    
    echo "   Daily wipes: {$data['daily_wipes_needed']}\n";
    echo "   Weekly wipes: {$data['weekly_wipes_needed']}\n";
    echo "   Monthly wipes: {$data['monthly_wipes_needed']}\n";
} else {
    echo "   ✗ Response is not WP_REST_Response\n";
    $failed++;
}

echo "\n4. Test Backwards Compatibility (child_age_months parameter)\n";
$request = new MockRequest([
    'child_age_months' => 6,
    'lifestyle' => 'moderate'
]);

$response = $controller->calculate_hygiene_needs($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    if (isset($data['daily_wipes_needed'])) {
        echo "   ✓ Still works with old parameter name (child_age_months)\n";
        $passed++;
    } else {
        echo "   ✗ Backwards compatibility broken\n";
        $failed++;
    }
    
    echo "   Daily wipes: {$data['daily_wipes_needed']}\n";
} else {
    echo "   ✗ Response is not WP_REST_Response\n";
    $failed++;
}

echo "\n5. Test Invalid Age Validation\n";
$request = new MockRequest([
    'baby_age_months' => 40,
    'daily_diaper_changes' => 6
]);

$response = $controller->calculate_hygiene_needs($request);

if ($response instanceof WP_Error) {
    echo "   ✓ Age validation works (rejects age > 36)\n";
    $passed++;
} else {
    echo "   ✗ Age validation not working\n";
    $failed++;
}

echo "\n6. Test Default Values\n";
$request = new MockRequest([
    'baby_age_months' => 8
]);

$response = $controller->calculate_hygiene_needs($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // With defaults: 6 diapers * 3 wipes + 3 meals * 2 wipes + 2 hours * 1.5 wipes = 27 wipes
    $expected_daily = 27;
    if ($data['daily_wipes_needed'] == $expected_daily) {
        echo "   ✓ Default values applied correctly (expected: $expected_daily, got: {$data['daily_wipes_needed']})\n";
        $passed++;
    } else {
        echo "   ✗ Default values not applied correctly (expected: $expected_daily, got: {$data['daily_wipes_needed']})\n";
        $failed++;
    }
} else {
    echo "   ✗ Response is not WP_REST_Response\n";
    $failed++;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed.\n";
    exit(1);
}

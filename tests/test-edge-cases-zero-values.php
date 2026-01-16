<?php
/**
 * Edge case tests for 0 values
 * Verify that parameter validation correctly handles null vs 0
 */

echo "=== Edge Case Tests: 0 Values ===\n\n";

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

require_once $baseDir . '/includes/API/SponsoredToolController.php';
$controller = new \KG_Core\API\SponsoredToolController();

// Test 1: daily_changes = 0 should be treated as 0, not auto-calculated
echo "1. Test daily_changes = 0 (should NOT auto-calculate)\n";
$request = new MockRequest([
    'baby_weight_kg' => 5.0,
    'baby_age_months' => 6,
    'daily_changes' => 0  // Explicitly 0
]);

$response = $controller->calculate_diaper_needs($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    if ($data['daily_count'] === 0) {
        echo "   ✓ daily_count correctly preserved as 0 (not auto-calculated)\n";
        $passed++;
    } else {
        echo "   ✗ daily_count should be 0, got: " . $data['daily_count'] . "\n";
        $failed++;
    }
} else {
    echo "   ✗ Response error\n";
    $failed++;
}

// Test 2: change_frequency = 0 should be treated as 0, not defaulted
echo "\n2. Test change_frequency = 0 (should NOT default to 3)\n";
$request = new MockRequest([
    'change_frequency' => 0,  // Explicitly 0
    'night_diaper_hours' => 8,
    'humidity_level' => 'normal',
    'has_diarrhea' => false
]);

$response = $controller->assess_rash_risk($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // With change_frequency = 0, risk should be low (no risk from change frequency)
    if ($data['risk_score'] === 0) {
        echo "   ✓ risk_score is 0 (change_frequency=0 not defaulted to 3)\n";
        $passed++;
    } else {
        echo "   ✗ risk_score should be 0, got: " . $data['risk_score'] . "\n";
        $failed++;
    }
} else {
    echo "   ✗ Response error\n";
    $failed++;
}

// Test 3: night_diaper_hours = 0 should be treated as 0, not defaulted
echo "\n3. Test night_diaper_hours = 0 (should NOT default to 8)\n";
$request = new MockRequest([
    'change_frequency' => 3,
    'night_diaper_hours' => 0,  // Explicitly 0
    'humidity_level' => 'normal',
    'has_diarrhea' => false
]);

$response = $controller->assess_rash_risk($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // With night_diaper_hours = 0, risk should be low (no night diaper risk)
    if ($data['risk_score'] === 0) {
        echo "   ✓ risk_score is 0 (night_diaper_hours=0 not defaulted to 8)\n";
        $passed++;
    } else {
        echo "   ✗ risk_score should be 0, got: " . $data['risk_score'] . "\n";
        $failed++;
    }
} else {
    echo "   ✗ Response error\n";
    $failed++;
}

// Test 4: Missing parameters should use defaults
echo "\n4. Test missing parameters (should use defaults)\n";
$request = new MockRequest([
    'humidity_level' => 'normal',
    'has_diarrhea' => false
    // change_frequency and night_diaper_hours not provided
]);

$response = $controller->assess_rash_risk($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // Defaults: change_frequency=3, night_diaper_hours=8 should result in low risk
    if ($data['risk_level'] === 'low') {
        echo "   ✓ Defaults applied correctly, risk_level is low\n";
        $passed++;
    } else {
        echo "   ✗ risk_level should be low with defaults, got: " . $data['risk_level'] . "\n";
        $failed++;
    }
} else {
    echo "   ✗ Response error\n";
    $failed++;
}

echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed > 0) {
    echo "\nResult: FAILED ❌\n";
    exit(1);
} else {
    echo "\nResult: PASSED ✅\n";
    exit(0);
}

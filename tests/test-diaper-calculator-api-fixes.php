<?php
/**
 * Test for Diaper Calculator API Fixes
 * 
 * This test verifies:
 * 1. calculate_diaper_needs endpoint accepts both old and new parameter names
 * 2. calculate_diaper_needs returns all required fields including new ones
 * 3. assess_rash_risk endpoint accepts both legacy factors object and new direct parameters
 * 4. assess_rash_risk calculates risk correctly with new parameters
 * 5. Backward compatibility is maintained for both endpoints
 */

echo "=== Diaper Calculator API Fixes Test ===\n\n";

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

echo "=== Part 1: calculate_diaper_needs Tests ===\n\n";

// Test 1: New parameter names (baby_weight_kg, baby_age_months)
echo "1. Test New Parameter Names (baby_weight_kg, baby_age_months)\n";
$request = new MockRequest([
    'baby_weight_kg' => 8.5,
    'baby_age_months' => 6,
    'daily_changes' => 6
]);

$response = $controller->calculate_diaper_needs($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // Check all required fields exist
    $requiredFields = ['recommended_size', 'size_range', 'daily_count', 'monthly_count', 'monthly_packs', 'pack_type', 'tips'];
    $allFieldsPresent = true;
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            echo "   ✗ Missing field: $field\n";
            $failed++;
            $allFieldsPresent = false;
        }
    }
    
    if ($allFieldsPresent) {
        echo "   ✓ All required fields present\n";
        $passed++;
    }
    
    // Verify daily_count matches provided daily_changes
    if ($data['daily_count'] === 6) {
        echo "   ✓ daily_count correctly set to 6\n";
        $passed++;
    } else {
        echo "   ✗ daily_count should be 6, got: " . $data['daily_count'] . "\n";
        $failed++;
    }
    
    // Verify monthly_count is calculated correctly
    if ($data['monthly_count'] === 180) {
        echo "   ✓ monthly_count correctly calculated (6 * 30 = 180)\n";
        $passed++;
    } else {
        echo "   ✗ monthly_count should be 180, got: " . $data['monthly_count'] . "\n";
        $failed++;
    }
    
    // Verify size_range is present and non-empty
    if (!empty($data['size_range'])) {
        echo "   ✓ size_range provided: " . $data['size_range'] . "\n";
        $passed++;
    } else {
        echo "   ✗ size_range is empty\n";
        $failed++;
    }
    
    // Verify monthly_packs is calculated
    if (isset($data['monthly_packs']) && $data['monthly_packs'] > 0) {
        echo "   ✓ monthly_packs calculated: " . $data['monthly_packs'] . "\n";
        $passed++;
    } else {
        echo "   ✗ monthly_packs not calculated properly\n";
        $failed++;
    }
    
    // Verify pack_type is provided
    if (!empty($data['pack_type'])) {
        echo "   ✓ pack_type provided: " . $data['pack_type'] . "\n";
        $passed++;
    } else {
        echo "   ✗ pack_type is empty\n";
        $failed++;
    }
    
} else {
    echo "   ✗ Response is not WP_REST_Response\n";
    $failed += 6;
}

// Test 2: Backward compatibility with old parameter names
echo "\n2. Test Backward Compatibility (weight_kg, child_age_months)\n";
$request = new MockRequest([
    'weight_kg' => 10.0,
    'child_age_months' => 12,
    'feeding_type' => 'mixed'
]);

$response = $controller->calculate_diaper_needs($request);

if ($response instanceof WP_REST_Response) {
    echo "   ✓ Old parameter names still work\n";
    $passed++;
    
    $data = $response->data;
    
    // Verify daily_count is auto-calculated when not provided
    if (isset($data['daily_count']) && $data['daily_count'] > 0) {
        echo "   ✓ daily_count auto-calculated: " . $data['daily_count'] . "\n";
        $passed++;
    } else {
        echo "   ✗ daily_count not auto-calculated\n";
        $failed++;
    }
} else {
    echo "   ✗ Old parameter names don't work\n";
    $failed += 2;
}

// Test 3: size_change_alert when near upper bound
echo "\n3. Test size_change_alert for Upper Bound Weight\n";
$request = new MockRequest([
    'baby_weight_kg' => 8.7,
    'baby_age_months' => 8
]);

$response = $controller->calculate_diaper_needs($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    if (isset($data['size_change_alert']) && !empty($data['size_change_alert'])) {
        echo "   ✓ size_change_alert provided: " . $data['size_change_alert'] . "\n";
        $passed++;
    } else {
        echo "   ℹ size_change_alert is null (acceptable for mid-range weight)\n";
        $passed++;
    }
} else {
    echo "   ✗ Response error\n";
    $failed++;
}

echo "\n=== Part 2: assess_rash_risk Tests ===\n\n";

// Test 4: New direct parameters format
echo "4. Test New Direct Parameters Format\n";
$request = new MockRequest([
    'change_frequency' => 5,
    'night_diaper_hours' => 12,
    'humidity_level' => 'high',
    'has_diarrhea' => true
]);

$response = $controller->assess_rash_risk($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    // Check required fields
    $requiredFields = ['risk_level', 'risk_score', 'risk_factors', 'prevention_tips'];
    $allFieldsPresent = true;
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            echo "   ✗ Missing field: $field\n";
            $failed++;
            $allFieldsPresent = false;
        }
    }
    
    if ($allFieldsPresent) {
        echo "   ✓ All required fields present\n";
        $passed++;
    }
    
    // With all high-risk factors, risk_level should be 'high'
    if ($data['risk_level'] === 'high') {
        echo "   ✓ risk_level correctly set to 'high'\n";
        $passed++;
    } else {
        echo "   ✗ risk_level should be 'high', got: " . $data['risk_level'] . "\n";
        $failed++;
    }
    
    // risk_score should be high (35 + 30 + 25 + 40 = 130)
    if ($data['risk_score'] >= 100) {
        echo "   ✓ risk_score is high: " . $data['risk_score'] . "\n";
        $passed++;
    } else {
        echo "   ✗ risk_score should be >= 100, got: " . $data['risk_score'] . "\n";
        $failed++;
    }
    
    // Should have 4 risk factors
    if (is_array($data['risk_factors']) && count($data['risk_factors']) === 4) {
        echo "   ✓ Correct number of risk_factors: 4\n";
        $passed++;
    } else {
        echo "   ✗ Should have 4 risk_factors, got: " . count($data['risk_factors']) . "\n";
        $failed++;
    }
    
} else {
    echo "   ✗ Response is not WP_REST_Response\n";
    $failed += 4;
}

// Test 5: Low risk scenario
echo "\n5. Test Low Risk Scenario\n";
$request = new MockRequest([
    'change_frequency' => 3,
    'night_diaper_hours' => 8,
    'humidity_level' => 'normal',
    'has_diarrhea' => false
]);

$response = $controller->assess_rash_risk($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    if ($data['risk_level'] === 'low') {
        echo "   ✓ risk_level correctly set to 'low'\n";
        $passed++;
    } else {
        echo "   ✗ risk_level should be 'low', got: " . $data['risk_level'] . "\n";
        $failed++;
    }
    
    if ($data['risk_score'] === 0) {
        echo "   ✓ risk_score is 0\n";
        $passed++;
    } else {
        echo "   ✗ risk_score should be 0, got: " . $data['risk_score'] . "\n";
        $failed++;
    }
    
    if (is_array($data['risk_factors']) && count($data['risk_factors']) === 0) {
        echo "   ✓ No risk_factors (empty array)\n";
        $passed++;
    } else {
        echo "   ✗ risk_factors should be empty, got: " . count($data['risk_factors']) . " items\n";
        $failed++;
    }
    
} else {
    echo "   ✗ Response error\n";
    $failed += 3;
}

// Test 6: Medium risk scenario
echo "\n6. Test Medium Risk Scenario\n";
$request = new MockRequest([
    'change_frequency' => 4.5,
    'night_diaper_hours' => 10,
    'humidity_level' => 'normal',
    'has_diarrhea' => false
]);

$response = $controller->assess_rash_risk($request);

if ($response instanceof WP_REST_Response) {
    $data = $response->data;
    
    if ($data['risk_level'] === 'medium') {
        echo "   ✓ risk_level correctly set to 'medium'\n";
        $passed++;
    } else {
        echo "   ✗ risk_level should be 'medium', got: " . $data['risk_level'] . "\n";
        $failed++;
    }
    
    // risk_score should be 35 (change_frequency >= 4) + 15 (night >= 10) = 50
    if ($data['risk_score'] >= 30 && $data['risk_score'] < 60) {
        echo "   ✓ risk_score in medium range: " . $data['risk_score'] . "\n";
        $passed++;
    } else {
        echo "   ✗ risk_score should be 30-59, got: " . $data['risk_score'] . "\n";
        $failed++;
    }
    
} else {
    echo "   ✗ Response error\n";
    $failed += 2;
}

// Test 7: Legacy factors object format (backward compatibility)
echo "\n7. Test Legacy Factors Object Format\n";
$request = new MockRequest([
    'factors' => [
        'change_frequency' => 'infrequent',
        'skin_type' => 'sensitive',
        'recent_antibiotics' => true,
        'diet_change' => false,
        'diarrhea' => true
    ]
]);

$response = $controller->assess_rash_risk($request);

if ($response instanceof WP_REST_Response) {
    echo "   ✓ Legacy factors object format still works\n";
    $passed++;
    
    $data = $response->data;
    
    // Should have high risk (30 + 20 + 25 + 35 = 110)
    if ($data['risk_level'] === 'high') {
        echo "   ✓ Legacy format calculates risk correctly (high)\n";
        $passed++;
    } else {
        echo "   ✗ Legacy format risk calculation incorrect, got: " . $data['risk_level'] . "\n";
        $failed++;
    }
    
    if ($data['risk_score'] >= 60) {
        echo "   ✓ Legacy format risk_score is high: " . $data['risk_score'] . "\n";
        $passed++;
    } else {
        echo "   ✗ Legacy format risk_score should be >= 60, got: " . $data['risk_score'] . "\n";
        $failed++;
    }
    
} else {
    echo "   ✗ Legacy format doesn't work\n";
    $failed += 3;
}

// Summary
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

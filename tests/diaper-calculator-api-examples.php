<?php
/**
 * Integration Examples for Diaper Calculator API
 * 
 * This file demonstrates how to use the updated diaper calculator endpoints
 * with both old and new parameter formats.
 */

echo "=== Diaper Calculator API Integration Examples ===\n\n";

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
require_once $baseDir . '/includes/API/SponsoredToolController.php';

$controller = new \KG_Core\API\SponsoredToolController();

echo "Example 1: Calculate Diaper Needs (New Frontend Format)\n";
echo "POST /kg/v1/tools/diaper-calculator/calculate\n";
echo "Body: {\n";
echo "  \"baby_weight_kg\": 8.5,\n";
echo "  \"baby_age_months\": 6,\n";
echo "  \"daily_changes\": 6\n";
echo "}\n\n";

$request = new MockRequest([
    'baby_weight_kg' => 8.5,
    'baby_age_months' => 6,
    'daily_changes' => 6
]);

$response = $controller->calculate_diaper_needs($request);
echo "Response:\n";
print_r($response->data);
echo "\n---\n\n";

echo "Example 2: Calculate Diaper Needs (Old Backend Format - Still Works!)\n";
echo "POST /kg/v1/tools/diaper-calculator/calculate\n";
echo "Body: {\n";
echo "  \"weight_kg\": 10.5,\n";
echo "  \"child_age_months\": 12,\n";
echo "  \"feeding_type\": \"mixed\"\n";
echo "}\n\n";

$request = new MockRequest([
    'weight_kg' => 10.5,
    'child_age_months' => 12,
    'feeding_type' => 'mixed'
]);

$response = $controller->calculate_diaper_needs($request);
echo "Response:\n";
print_r($response->data);
echo "\n---\n\n";

echo "Example 3: Assess Rash Risk (New Frontend Format)\n";
echo "POST /kg/v1/tools/diaper-calculator/rash-risk\n";
echo "Body: {\n";
echo "  \"change_frequency\": 3,\n";
echo "  \"night_diaper_hours\": 10,\n";
echo "  \"humidity_level\": \"normal\",\n";
echo "  \"has_diarrhea\": false\n";
echo "}\n\n";

$request = new MockRequest([
    'change_frequency' => 3,
    'night_diaper_hours' => 10,
    'humidity_level' => 'normal',
    'has_diarrhea' => false
]);

$response = $controller->assess_rash_risk($request);
echo "Response:\n";
print_r($response->data);
echo "\n---\n\n";

echo "Example 4: Assess Rash Risk - High Risk Scenario\n";
echo "POST /kg/v1/tools/diaper-calculator/rash-risk\n";
echo "Body: {\n";
echo "  \"change_frequency\": 5,\n";
echo "  \"night_diaper_hours\": 12,\n";
echo "  \"humidity_level\": \"high\",\n";
echo "  \"has_diarrhea\": true\n";
echo "}\n\n";

$request = new MockRequest([
    'change_frequency' => 5,
    'night_diaper_hours' => 12,
    'humidity_level' => 'high',
    'has_diarrhea' => true
]);

$response = $controller->assess_rash_risk($request);
echo "Response:\n";
print_r($response->data);
echo "\n---\n\n";

echo "Example 5: Assess Rash Risk (Old Backend Format - Still Works!)\n";
echo "POST /kg/v1/tools/diaper-calculator/rash-risk\n";
echo "Body: {\n";
echo "  \"factors\": {\n";
echo "    \"change_frequency\": \"infrequent\",\n";
echo "    \"skin_type\": \"sensitive\",\n";
echo "    \"recent_antibiotics\": false,\n";
echo "    \"diet_change\": false,\n";
echo "    \"diarrhea\": false\n";
echo "  }\n";
echo "}\n\n";

$request = new MockRequest([
    'factors' => [
        'change_frequency' => 'infrequent',
        'skin_type' => 'sensitive',
        'recent_antibiotics' => false,
        'diet_change' => false,
        'diarrhea' => false
    ]
]);

$response = $controller->assess_rash_risk($request);
echo "Response:\n";
print_r($response->data);
echo "\n---\n\n";

echo "=== Integration Examples Complete ===\n";

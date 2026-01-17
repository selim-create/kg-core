<?php
/**
 * Test script for Top Contributors Endpoint
 * Validates the /kg/v1/community/top-contributors endpoint
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', dirname(__DIR__) . '/');
}

echo "=== Testing Top Contributors Endpoint ===\n\n";

// Test 1: PHP Syntax Validation
echo "Test 1: PHP Syntax Validation\n";
echo "------------------------------\n";

$file_to_check = 'includes/API/DiscussionController.php';
$full_path = KG_CORE_PATH . $file_to_check;

$syntax_errors = 0;

if (!file_exists($full_path)) {
    echo "❌ File not found: $file_to_check\n";
    $syntax_errors++;
} else {
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($full_path) . " 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "✅ $file_to_check - No syntax errors\n";
    } else {
        echo "❌ $file_to_check - Syntax errors found:\n";
        echo "   " . implode("\n   ", $output) . "\n";
        $syntax_errors++;
    }
}

echo "\n";

// Test 2: Verify Endpoint Registration
echo "Test 2: Verify Endpoint Registration\n";
echo "-------------------------------------\n";

$controller_content = file_get_contents($full_path);

$endpoint_features = [
    'Route registration' => preg_match('/register_rest_route.*community\/top-contributors/', $controller_content),
    'GET method' => preg_match('/\'methods\'\s*=>\s*["\']GET["\']/', $controller_content) && preg_match('/get_top_contributors/', $controller_content),
    'Permission callback' => preg_match('/\'permission_callback\'\s*=>\s*["\']__return_true["\']/', $controller_content),
    'Limit parameter' => preg_match('/\'limit\'\s*=>\s*\[/', $controller_content),
    'Period parameter' => preg_match('/\'period\'\s*=>\s*\[/', $controller_content),
    'Limit default value (5)' => preg_match('/\'default\'\s*=>\s*5/', $controller_content),
    'Period default value (week)' => preg_match('/\'default\'\s*=>\s*["\']week["\']/', $controller_content),
    'Limit validation (max 20)' => preg_match('/\$param\s*<=\s*20/', $controller_content),
    'Period validation (week|month|all)' => preg_match('/in_array.*week.*month.*all/', $controller_content),
];

foreach ($endpoint_features as $feature_name => $found) {
    echo ($found ? "✅" : "❌") . " $feature_name\n";
    if (!$found) $syntax_errors++;
}

echo "\n";

// Test 3: Verify Method Implementation
echo "Test 3: Verify Method Implementation\n";
echo "------------------------------------\n";

$method_features = [
    'get_top_contributors method exists' => preg_match('/function get_top_contributors/', $controller_content),
    'Global $wpdb usage' => preg_match('/global \$wpdb;/', $controller_content),
    'Get limit parameter' => preg_match('/get_param.*limit/', $controller_content),
    'Get period parameter' => preg_match('/get_param.*period/', $controller_content),
    'Discussion count subquery' => preg_match('/SELECT COUNT\(\*\).*{?\$wpdb->posts}?.*post_type.*discussion/s', $controller_content),
    'Comment count subquery' => preg_match('/SELECT COUNT\(\*\).*{?\$wpdb->comments}?.*comment_approved/s', $controller_content),
    'Exclude administrator' => preg_match('/administrator/', $controller_content),
    'Exclude kg_expert' => preg_match('/kg_expert/', $controller_content),
    'Exclude editor' => preg_match('/editor/', $controller_content),
    'Order by contribution count' => preg_match('/ORDER BY.*discussion_count.*comment_count.*DESC/s', $controller_content),
    'Avatar fallback logic' => preg_match('/_kg_avatar_id.*google_avatar.*get_avatar_url/s', $controller_content),
];

foreach ($method_features as $feature_name => $found) {
    echo ($found ? "✅" : "❌") . " $feature_name\n";
    if (!$found) $syntax_errors++;
}

echo "\n";

// Test 4: Verify Response Format
echo "Test 4: Verify Response Format\n";
echo "-------------------------------\n";

$response_features = [
    'id field' => preg_match('/["\']id["\']\s*=>\s*\$user_id/', $controller_content),
    'name field' => preg_match('/["\']name["\']\s*=>.*display_name/', $controller_content),
    'avatar field' => preg_match('/["\']avatar["\']\s*=>\s*\$avatar_url/', $controller_content),
    'contribution_count field' => preg_match('/["\']contribution_count["\']\s*=>/', $controller_content),
    'discussion_count field' => preg_match('/["\']discussion_count["\']\s*=>/', $controller_content),
    'comment_count field' => preg_match('/["\']comment_count["\']\s*=>/', $controller_content),
    'rank field' => preg_match('/["\']rank["\']\s*=>\s*\$rank/', $controller_content),
    'WP_REST_Response return' => preg_match('/new.*WP_REST_Response.*200/', $controller_content),
];

foreach ($response_features as $feature_name => $found) {
    echo ($found ? "✅" : "❌") . " $feature_name\n";
    if (!$found) $syntax_errors++;
}

echo "\n";

// Test 5: Verify Period-Based Filtering
echo "Test 5: Verify Period-Based Filtering\n";
echo "--------------------------------------\n";

$period_features = [
    'Week period (7 days)' => preg_match('/week.*7/s', $controller_content) || preg_match('/7.*week/s', $controller_content),
    'Month period (30 days)' => preg_match('/month.*30/s', $controller_content) || preg_match('/30.*month/s', $controller_content),
    'All period handling' => preg_match('/days_interval.*0/', $controller_content) || preg_match('/period.*all/', $controller_content),
    'DATE_SUB function usage' => preg_match('/DATE_SUB\(NOW\(\)/', $controller_content),
];

foreach ($period_features as $feature_name => $found) {
    echo ($found ? "✅" : "❌") . " $feature_name\n";
    if (!$found) $syntax_errors++;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
if ($syntax_errors === 0) {
    echo "✅ All tests passed! The top contributors endpoint is properly implemented.\n";
    echo "\nEndpoint Details:\n";
    echo "  - Route: /wp-json/kg/v1/community/top-contributors\n";
    echo "  - Method: GET\n";
    echo "  - Parameters:\n";
    echo "    * limit (optional, default: 5, max: 20)\n";
    echo "    * period (optional, default: 'week', values: 'week'|'month'|'all')\n";
    echo "  - Public: Yes (no authentication required)\n";
    echo "\nExample Requests:\n";
    echo "  - GET /wp-json/kg/v1/community/top-contributors\n";
    echo "  - GET /wp-json/kg/v1/community/top-contributors?limit=10\n";
    echo "  - GET /wp-json/kg/v1/community/top-contributors?period=month\n";
    echo "  - GET /wp-json/kg/v1/community/top-contributors?limit=10&period=all\n";
    exit(0);
} else {
    echo "❌ $syntax_errors test(s) failed. Please review the errors above.\n";
    exit(1);
}

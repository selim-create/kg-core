<?php
/**
 * Test script for Taxonomy Slug Fixes
 * 
 * Verifies that the taxonomy slugs are correctly mapped
 * according to the WordPress database values.
 * 
 * To run: php test-taxonomy-slug-fixes.php
 */

// Load the MealPlanGenerator class
require_once __DIR__ . '/includes/Services/MealPlanGenerator.php';

// Mock WordPress functions for static testing
if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() { 
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), 
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, 
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)); 
    }
}
if (!function_exists('current_time')) {
    function current_time($type) { return date('c'); }
}

class WP_Query {
    public $posts = [];
    public function __construct($args) {}
    public function have_posts() { return false; }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id, $taxonomy, $args = []) { return []; }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return false; }
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

echo colorize("\n=== Taxonomy Slug Fix Tests ===\n", 'info');

$total_tests = 0;
$passed_tests = 0;

// Test 1: AGE_GROUP_MAPPING constant values
echo "\n" . colorize("Test 1: AGE_GROUP_MAPPING Constant", 'info') . "\n";

$expected_mapping = [
    '0-6' => '0-6-ay-sadece-sut',
    '6-8' => '6-8-ay-baslangic',
    '9-11' => '9-11-ay-kesif',
    '12-24' => '12-24-ay-gecis',
    '24+' => '2-yas-ve-uzeri',
];

$reflection = new ReflectionClass('KG_Core\Services\MealPlanGenerator');
$actual_mapping = $reflection->getConstant('AGE_GROUP_MAPPING');

foreach ($expected_mapping as $key => $expected_slug) {
    $total_tests++;
    $has_key = isset($actual_mapping[$key]);
    $correct_value = $has_key && $actual_mapping[$key] === $expected_slug;
    
    if (test_result("Age group '$key' maps to '$expected_slug'", 
                   $correct_value, 
                   $has_key ? "Got: {$actual_mapping[$key]}" : "Key not found")) {
        $passed_tests++;
    }
}

// Test that old incorrect keys are removed
$old_keys = ['19-36', '36+'];
foreach ($old_keys as $old_key) {
    $total_tests++;
    $removed = !isset($actual_mapping[$old_key]);
    if (test_result("Old key '$old_key' removed from mapping", $removed, "Key still exists")) {
        $passed_tests++;
    }
}

// Test 2: SLOT_TYPES for snacks
echo "\n" . colorize("Test 2: SLOT_TYPES Snack Slugs", 'info') . "\n";

$slot_types = $reflection->getConstant('SLOT_TYPES');

// Check snack_morning
$total_tests++;
$morning_slug = $slot_types['snack_morning']['meal_type_slug'];
$morning_correct = $morning_slug === 'ara-ogun';
if (test_result("snack_morning uses 'ara-ogun'", $morning_correct, "Got: $morning_slug")) {
    $passed_tests++;
}

$total_tests++;
$morning_has_fallback = isset($slot_types['snack_morning']['fallback_slugs']) && 
                        in_array('atistirmalik', $slot_types['snack_morning']['fallback_slugs']);
if (test_result("snack_morning has 'atistirmalik' fallback", $morning_has_fallback)) {
    $passed_tests++;
}

// Check snack_afternoon
$total_tests++;
$afternoon_slug = $slot_types['snack_afternoon']['meal_type_slug'];
$afternoon_correct = $afternoon_slug === 'atistirmalik';
if (test_result("snack_afternoon uses 'atistirmalik'", $afternoon_correct, "Got: $afternoon_slug")) {
    $passed_tests++;
}

$total_tests++;
$afternoon_has_fallback = isset($slot_types['snack_afternoon']['fallback_slugs']) && 
                          in_array('ara-ogun', $slot_types['snack_afternoon']['fallback_slugs']);
if (test_result("snack_afternoon has 'ara-ogun' fallback", $afternoon_has_fallback)) {
    $passed_tests++;
}

// Test 3: get_age_group_for_months function
echo "\n" . colorize("Test 3: get_age_group_for_months Function", 'info') . "\n";

$generator = new KG_Core\Services\MealPlanGenerator();

// Use reflection to access private method
$method = new ReflectionMethod('KG_Core\Services\MealPlanGenerator', 'get_age_group_for_months');
$method->setAccessible(true);

$test_cases = [
    ['months' => 5, 'expected' => '0-6-ay-sadece-sut', 'description' => '5 months'],
    ['months' => 7, 'expected' => '6-8-ay-baslangic', 'description' => '7 months'],
    ['months' => 10, 'expected' => '9-11-ay-kesif', 'description' => '10 months (FIXED)'],
    ['months' => 15, 'expected' => '12-24-ay-gecis', 'description' => '15 months (FIXED)'],
    ['months' => 20, 'expected' => '12-24-ay-gecis', 'description' => '20 months (FIXED)'],
    ['months' => 24, 'expected' => '12-24-ay-gecis', 'description' => '24 months (boundary)'],
    ['months' => 25, 'expected' => '2-yas-ve-uzeri', 'description' => '25 months (FIXED)'],
    ['months' => 36, 'expected' => '2-yas-ve-uzeri', 'description' => '36 months (FIXED)'],
    ['months' => 48, 'expected' => '2-yas-ve-uzeri', 'description' => '48 months (FIXED)'],
];

foreach ($test_cases as $test_case) {
    $total_tests++;
    $actual = $method->invoke($generator, $test_case['months']);
    $correct = $actual === $test_case['expected'];
    
    if (test_result("{$test_case['description']} returns '{$test_case['expected']}'", 
                   $correct, 
                   "Got: $actual")) {
        $passed_tests++;
    }
}

// Test 4: Verify fallback support exists
echo "\n" . colorize("Test 4: Fallback Support in get_recipe_for_slot", 'info') . "\n";

$get_recipe_method = new ReflectionMethod('KG_Core\Services\MealPlanGenerator', 'get_recipe_for_slot');
$get_recipe_method->setAccessible(true);

// Check method signature includes fallback_slugs parameter
$params = $get_recipe_method->getParameters();
$total_tests++;
$has_fallback_param = false;
foreach ($params as $param) {
    if ($param->getName() === 'fallback_slugs') {
        $has_fallback_param = true;
        break;
    }
}
if (test_result("get_recipe_for_slot accepts fallback_slugs parameter", $has_fallback_param)) {
    $passed_tests++;
}

// Check that query_recipe helper method exists
$total_tests++;
$has_query_recipe = $reflection->hasMethod('query_recipe');
if (test_result("query_recipe helper method exists", $has_query_recipe)) {
    $passed_tests++;
}

// Summary
echo "\n" . colorize("=== Test Summary ===", 'info') . "\n";
$percentage = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0;
echo "Passed: " . colorize("$passed_tests", 'success') . " / $total_tests ($percentage%)\n";

if ($passed_tests === $total_tests) {
    echo colorize("\n✓ All taxonomy slug fixes verified!\n", 'success');
    exit(0);
} else {
    echo colorize("\n✗ Some tests failed!\n", 'error');
    exit(1);
}

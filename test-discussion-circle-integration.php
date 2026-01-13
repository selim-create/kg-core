<?php
/**
 * Test script for Discussion & CommunityCircle integration
 * Validates syntax fixes and class loading
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/');
}

echo "=== Testing Discussion & CommunityCircle Integration ===\n\n";

// Test 1: PHP Syntax Validation
echo "Test 1: PHP Syntax Validation\n";
echo "------------------------------\n";

$files_to_check = [
    'includes/Taxonomies/CommunityCircle.php',
    'includes/Admin/DiscussionAdmin.php',
    'includes/API/DiscussionController.php',
    'includes/PostTypes/Discussion.php',
    'includes/API/UserController.php',
    'kg-core.php'
];

$syntax_errors = 0;
foreach ($files_to_check as $file) {
    $full_path = KG_CORE_PATH . $file;
    if (!file_exists($full_path)) {
        echo "❌ File not found: $file\n";
        $syntax_errors++;
        continue;
    }
    
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($full_path) . " 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "✅ $file - No syntax errors\n";
    } else {
        echo "❌ $file - Syntax errors found:\n";
        echo "   " . implode("\n   ", $output) . "\n";
        $syntax_errors++;
    }
}

echo "\n";

// Test 2: Verify specific syntax fixes
echo "Test 2: Verify Specific Syntax Fixes\n";
echo "-------------------------------------\n";

// Check CommunityCircle.php fixes
$community_circle_content = file_get_contents(KG_CORE_PATH . 'includes/Taxonomies/CommunityCircle.php');
$fixes = [
    'CommunityCircle.php Line 1' => preg_match('/^<\?php\s*\n/', $community_circle_content),
    'CommunityCircle.php Line 192' => !preg_match('/<\?\s+php/', $community_circle_content),
];

// Check DiscussionAdmin.php fixes
$discussion_admin_content = file_get_contents(KG_CORE_PATH . 'includes/Admin/DiscussionAdmin.php');
$fixes['DiscussionAdmin.php Line 1'] = preg_match('/^<\?php\s*\n/', $discussion_admin_content);
$fixes['DiscussionAdmin.php jQuery selectors'] = !preg_match('/\$\(\'\.\s+kg-/', $discussion_admin_content) && 
                                                  !preg_match('/button\.\s+prop/', $discussion_admin_content) &&
                                                  !preg_match('/\$\.\s+post/', $discussion_admin_content);

// Check DiscussionController.php regex fixes
$discussion_controller_content = file_get_contents(KG_CORE_PATH . 'includes/API/DiscussionController.php');
$fixes['DiscussionController.php regex'] = !preg_match('/\(\?\s+P<id>/', $discussion_controller_content);

foreach ($fixes as $fix_name => $passed) {
    echo ($passed ? "✅" : "❌") . " $fix_name\n";
    if (!$passed) $syntax_errors++;
}

echo "\n";

// Test 3: Verify kg-core.php integrations
echo "Test 3: Verify kg-core.php Integrations\n";
echo "----------------------------------------\n";

$kg_core_content = file_get_contents(KG_CORE_PATH . 'kg-core.php');

$integrations = [
    'Discussion.php include' => preg_match('/includes\/PostTypes\/Discussion\.php/', $kg_core_content),
    'CommunityCircle.php include' => preg_match('/includes\/Taxonomies\/CommunityCircle\.php/', $kg_core_content),
    'DiscussionAdmin.php include' => preg_match('/includes\/Admin\/DiscussionAdmin\.php/', $kg_core_content),
    'DiscussionController.php include' => preg_match('/includes\/API\/DiscussionController\.php/', $kg_core_content),
    'Discussion class init' => preg_match('/PostTypes\\\\Discussion/', $kg_core_content),
    'CommunityCircle class init' => preg_match('/Taxonomies\\\\CommunityCircle/', $kg_core_content),
    'DiscussionAdmin class init' => preg_match('/Admin\\\\DiscussionAdmin/', $kg_core_content),
    'DiscussionController class init' => preg_match('/API\\\\DiscussionController/', $kg_core_content),
];

foreach ($integrations as $integration_name => $found) {
    echo ($found ? "✅" : "❌") . " $integration_name\n";
    if (!$found) $syntax_errors++;
}

echo "\n";

// Test 4: Verify auto-circle assignment in UserController
echo "Test 4: Verify Auto-Circle Assignment\n";
echo "--------------------------------------\n";

$user_controller_content = file_get_contents(KG_CORE_PATH . 'includes/API/UserController.php');

$auto_circle_features = [
    'baby_birth_date parameter' => preg_match('/baby_birth_date.*get_param/', $user_controller_content),
    'get_circle_by_baby_age method' => preg_match('/function get_circle_by_baby_age/', $user_controller_content),
    'assign_default_circle method' => preg_match('/function assign_default_circle/', $user_controller_content),
    'Auto-assignment call' => preg_match('/assign_default_circle.*baby_birth_date/', $user_controller_content),
    'Age-based circle mapping' => preg_match('/6-9-ay.*9-12-ay.*1-2-yas/s', $user_controller_content),
];

foreach ($auto_circle_features as $feature_name => $found) {
    echo ($found ? "✅" : "❌") . " $feature_name\n";
    if (!$found) $syntax_errors++;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
if ($syntax_errors === 0) {
    echo "✅ All tests passed! The integration is complete and syntax is correct.\n";
    exit(0);
} else {
    echo "❌ $syntax_errors test(s) failed. Please review the errors above.\n";
    exit(1);
}

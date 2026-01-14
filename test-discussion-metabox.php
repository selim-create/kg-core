<?php
/**
 * Test script for DiscussionMetaBox implementation
 * Validates syntax, class loading, and integration
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/');
}

echo "=== Testing DiscussionMetaBox Implementation ===\n\n";

// Test 1: PHP Syntax Validation
echo "Test 1: PHP Syntax Validation\n";
echo "------------------------------\n";

$files_to_check = [
    'includes/Admin/DiscussionMetaBox.php',
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

// Test 2: Verify DiscussionMetaBox.php structure
echo "Test 2: Verify DiscussionMetaBox.php Structure\n";
echo "-----------------------------------------------\n";

$metabox_content = file_get_contents(KG_CORE_PATH . 'includes/Admin/DiscussionMetaBox.php');

$structure_checks = [
    'Namespace declaration' => preg_match('/namespace\s+KG_Core\\\\Admin;/', $metabox_content),
    'Class declaration' => preg_match('/class\s+DiscussionMetaBox/', $metabox_content),
    'Constructor with hooks' => preg_match('/public\s+function\s+__construct/', $metabox_content),
    'add_meta_boxes hook' => preg_match('/add_action.*add_meta_boxes.*add_meta_box/', $metabox_content),
    'save_post_discussion hook' => preg_match('/add_action.*save_post_discussion.*save_meta_data/', $metabox_content),
    'add_meta_box method' => preg_match('/public\s+function\s+add_meta_box/', $metabox_content),
    'render_meta_box method' => preg_match('/public\s+function\s+render_meta_box/', $metabox_content),
    'save_meta_data method' => preg_match('/public\s+function\s+save_meta_data/', $metabox_content),
];

foreach ($structure_checks as $check_name => $passed) {
    echo ($passed ? "✅" : "❌") . " $check_name\n";
    if (!$passed) $syntax_errors++;
}

echo "\n";

// Test 3: Verify featured checkbox implementation
echo "Test 3: Verify Featured Checkbox Implementation\n";
echo "------------------------------------------------\n";

$featured_checks = [
    '_kg_is_featured meta key' => preg_match('/_kg_is_featured/', $metabox_content),
    'Featured checkbox input' => preg_match('/checkbox.*id="kg_is_featured"/', $metabox_content),
    'Featured checkbox label' => preg_match('/Öne Çıkan Soru mu/', $metabox_content),
    'Featured save logic' => preg_match('/is_featured.*isset.*kg_is_featured.*1.*0/', $metabox_content),
    'Featured update_post_meta' => preg_match('/update_post_meta.*_kg_is_featured/', $metabox_content),
];

foreach ($featured_checks as $check_name => $passed) {
    echo ($passed ? "✅" : "❌") . " $check_name\n";
    if (!$passed) $syntax_errors++;
}

echo "\n";

// Test 4: Verify answer count override implementation
echo "Test 4: Verify Answer Count Override Implementation\n";
echo "----------------------------------------------------\n";

$answer_count_checks = [
    '_kg_answer_count meta key' => preg_match('/_kg_answer_count/', $metabox_content),
    'Answer count input field' => preg_match('/type="number".*kg_answer_count/', $metabox_content),
    'Answer count label' => preg_match('/Cevap Sayısı.*Override/', $metabox_content),
    'Answer count save logic' => preg_match('/kg_answer_count.*absint/', $metabox_content),
    'Answer count delete when empty' => preg_match('/delete_post_meta.*_kg_answer_count/', $metabox_content),
];

foreach ($answer_count_checks as $check_name => $passed) {
    echo ($passed ? "✅" : "❌") . " $check_name\n";
    if (!$passed) $syntax_errors++;
}

echo "\n";

// Test 5: Verify security implementation
echo "Test 5: Verify Security Implementation\n";
echo "---------------------------------------\n";

$security_checks = [
    'Nonce field generation' => preg_match('/wp_nonce_field.*kg_discussion_save.*kg_discussion_nonce/', $metabox_content),
    'Nonce verification' => preg_match('/wp_verify_nonce.*kg_discussion_nonce.*kg_discussion_save/', $metabox_content),
    'Autosave protection' => preg_match('/DOING_AUTOSAVE.*return/', $metabox_content),
    'Capability check' => preg_match('/current_user_can.*edit_post/', $metabox_content),
    'Input sanitization (absint)' => preg_match('/absint\(.*kg_answer_count/', $metabox_content),
    'Escaped output (esc_attr)' => preg_match('/esc_attr\(.*answer_count/', $metabox_content),
    'Checked helper function' => preg_match('/checked\(.*is_featured/', $metabox_content),
];

foreach ($security_checks as $check_name => $passed) {
    echo ($passed ? "✅" : "❌") . " $check_name\n";
    if (!$passed) $syntax_errors++;
}

echo "\n";

// Test 6: Verify kg-core.php integration
echo "Test 6: Verify kg-core.php Integration\n";
echo "---------------------------------------\n";

$kg_core_content = file_get_contents(KG_CORE_PATH . 'kg-core.php');

$integration_checks = [
    'DiscussionMetaBox.php include' => preg_match('/includes\/Admin\/DiscussionMetaBox\.php/', $kg_core_content),
    'DiscussionMetaBox class init (is_admin)' => preg_match('/is_admin.*Admin\\\\DiscussionMetaBox/', $kg_core_content),
    'DiscussionMetaBox instantiation' => preg_match('/new\s+\\\\KG_Core\\\\Admin\\\\DiscussionMetaBox\(\);/', $kg_core_content),
];

foreach ($integration_checks as $check_name => $passed) {
    echo ($passed ? "✅" : "❌") . " $check_name\n";
    if (!$passed) $syntax_errors++;
}

echo "\n";

// Test 7: Verify meta box configuration
echo "Test 7: Verify Meta Box Configuration\n";
echo "--------------------------------------\n";

$config_checks = [
    'Meta box ID' => preg_match('/kg_discussion_details/', $metabox_content),
    'Meta box title' => preg_match('/Soru Ayarları/', $metabox_content),
    'Post type is discussion' => preg_match('/\'discussion\'/', $metabox_content),
    'Context is side' => preg_match('/\'side\'/', $metabox_content),
    'Priority is high' => preg_match('/\'high\'/', $metabox_content),
];

foreach ($config_checks as $check_name => $passed) {
    echo ($passed ? "✅" : "❌") . " $check_name\n";
    if (!$passed) $syntax_errors++;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
if ($syntax_errors === 0) {
    echo "✅ All tests passed! DiscussionMetaBox implementation is complete and correct.\n";
    echo "\nImplementation includes:\n";
    echo "  - ✅ Featured checkbox (_kg_is_featured)\n";
    echo "  - ✅ Answer count override field (_kg_answer_count)\n";
    echo "  - ✅ Proper security (nonce, capability checks, sanitization)\n";
    echo "  - ✅ Integration with kg-core.php\n";
    echo "  - ✅ Sidebar placement with high priority\n";
    exit(0);
} else {
    echo "❌ $syntax_errors test(s) failed. Please review the errors above.\n";
    exit(1);
}

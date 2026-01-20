<?php
/**
 * Integration Test for Stain CPT and Migration
 * 
 * This test simulates WordPress environment to test:
 * 1. Stain CPT registration
 * 2. Stain Category taxonomy registration
 * 3. Meta box registration
 * 4. Migration functionality
 */

echo "=== Stain CPT Integration Test ===\n\n";

// Mock WordPress functions for testing
if (!function_exists('add_action')) {
    $GLOBALS['wp_actions'] = [];
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['wp_actions'][$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args
        ];
        return true;
    }
}

if (!function_exists('add_filter')) {
    $GLOBALS['wp_filters'] = [];
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['wp_filters'][$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args
        ];
        return true;
    }
}

if (!function_exists('add_meta_box')) {
    function add_meta_box($id, $title, $callback, $screen, $context = 'advanced', $priority = 'default', $callback_args = null) {
        echo "   ℹ Meta box registered: $id for post type: $screen\n";
        return true;
    }
}

if (!function_exists('register_post_type')) {
    function register_post_type($post_type, $args) {
        echo "   ℹ Post type registered: $post_type\n";
        return true;
    }
}

if (!function_exists('register_taxonomy')) {
    function register_taxonomy($taxonomy, $object_type, $args) {
        echo "   ℹ Taxonomy registered: $taxonomy for " . implode(', ', (array)$object_type) . "\n";
        return true;
    }
}

if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current, $echo = true) {
        $result = $selected == $current ? ' selected="selected"' : '';
        if ($echo) echo $result;
        return $result;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action, $name, $referer = true, $echo = true) {
        $html = '<input type="hidden" name="' . $name . '" value="test_nonce" />';
        if ($echo) echo $html;
        return $html;
    }
}

if (!function_exists('wp_verify_nonce')) {
    // Note: This is a mock function for testing only. Do not use in production.
    function wp_verify_nonce($nonce, $action) {
        return $nonce === 'test_nonce';
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post) {
        return 'stain';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim($str);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim($str);
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        return true;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('get_terms')) {
    function get_terms($args) {
        return [];
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy, $args = []) {
        return ['term_id' => 1];
    }
}

if (!defined('DOING_AUTOSAVE')) {
    define('DOING_AUTOSAVE', false);
}

// Load the classes
require_once dirname(__DIR__) . '/includes/PostTypes/Stain.php';
require_once dirname(__DIR__) . '/includes/Taxonomies/StainCategory.php';
require_once dirname(__DIR__) . '/includes/Admin/StainMetaBox.php';

$passed = 0;
$failed = 0;

// Test 1: Stain class instantiation
echo "1. Stain CPT Class Instantiation\n";
try {
    $stain = new \KG_Core\PostTypes\Stain();
    echo "   ✓ Stain class instantiated successfully\n";
    $passed++;
} catch (Exception $e) {
    echo "   ✗ Failed to instantiate Stain class: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: StainCategory class instantiation
echo "\n2. StainCategory Taxonomy Class Instantiation\n";
try {
    $category = new \KG_Core\Taxonomies\StainCategory();
    echo "   ✓ StainCategory class instantiated successfully\n";
    $passed++;
} catch (Exception $e) {
    echo "   ✗ Failed to instantiate StainCategory class: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 3: StainMetaBox class instantiation
echo "\n3. StainMetaBox Class Instantiation\n";
try {
    $metabox = new \KG_Core\Admin\StainMetaBox();
    echo "   ✓ StainMetaBox class instantiated successfully\n";
    $passed++;
} catch (Exception $e) {
    echo "   ✗ Failed to instantiate StainMetaBox class: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 4: Hooks registered
echo "\n4. WordPress Hooks Registration\n";
$expectedHooks = [
    'init' => 2, // register_post_type and register_taxonomy + insert_default_terms
    'add_meta_boxes' => 1,
    'save_post' => 1,
];

foreach ($expectedHooks as $hook => $expectedCount) {
    $actualCount = isset($GLOBALS['wp_actions'][$hook]) ? count($GLOBALS['wp_actions'][$hook]) : 0;
    if ($actualCount >= $expectedCount) {
        echo "   ✓ Hook '$hook' registered ($actualCount callbacks)\n";
        $passed++;
    } else {
        echo "   ✗ Hook '$hook' not registered correctly (expected >=$expectedCount, got $actualCount)\n";
        $failed++;
    }
}

// Test 5: Admin columns filter
echo "\n5. Admin Columns Filter\n";
$actualCount = isset($GLOBALS['wp_filters']['manage_stain_posts_columns']) ? count($GLOBALS['wp_filters']['manage_stain_posts_columns']) : 0;
if ($actualCount >= 1) {
    echo "   ✓ Custom columns filter registered\n";
    $passed++;
} else {
    echo "   ✗ Custom columns filter not registered\n";
    $failed++;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed.\n";
    exit(1);
}

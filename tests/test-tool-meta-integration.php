<?php
/**
 * Integration Test for Tool Meta Box
 * 
 * This test simulates WordPress environment to test:
 * 1. Meta box registration
 * 2. Meta data saving
 * 3. Meta data retrieval
 */

echo "=== Tool Meta Box Integration Test ===\n\n";

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

// Load the Tool class
require_once dirname(__DIR__) . '/includes/PostTypes/Tool.php';

$passed = 0;
$failed = 0;

// Test 1: Tool class instantiation
echo "1. Tool Class Instantiation\n";
try {
    $tool = new \KG_Core\PostTypes\Tool();
    echo "   ✓ Tool class instantiated successfully\n";
    $passed++;
} catch (Exception $e) {
    echo "   ✗ Failed to instantiate Tool class: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: Hooks registered
echo "\n2. WordPress Hooks Registration\n";
$expectedHooks = [
    'init' => 2, // register_post_type and register_taxonomy
    'add_meta_boxes' => 1,
    'save_post' => 1,
];

foreach ($expectedHooks as $hook => $expectedCount) {
    $actualCount = isset($GLOBALS['wp_actions'][$hook]) ? count($GLOBALS['wp_actions'][$hook]) : 0;
    if ($actualCount >= $expectedCount) {
        echo "   ✓ Hook '$hook' registered ($actualCount callbacks)\n";
        $passed++;
    } else {
        echo "   ✗ Hook '$hook' not registered correctly (expected $expectedCount, got $actualCount)\n";
        $failed++;
    }
}

// Check filters
if (isset($GLOBALS['wp_filters']['manage_tool_posts_columns'])) {
    echo "   ✓ Filter 'manage_tool_posts_columns' registered\n";
    $passed++;
} else {
    echo "   ✗ Filter 'manage_tool_posts_columns' not registered\n";
    $failed++;
}

// Test 3: Meta box methods exist
echo "\n3. Meta Box Methods Existence\n";
$requiredMethods = [
    'add_tool_meta_box',
    'render_tool_meta_box',
    'save_tool_meta',
];

foreach ($requiredMethods as $method) {
    if (method_exists($tool, $method)) {
        echo "   ✓ Method '$method' exists\n";
        $passed++;
    } else {
        echo "   ✗ Method '$method' does not exist\n";
        $failed++;
    }
}

// Test 4: add_tool_meta_box can be called
echo "\n4. Meta Box Registration\n";
try {
    ob_start();
    $tool->add_tool_meta_box();
    $output = ob_get_clean();
    
    if (strpos($output, 'kg_tool_settings') !== false) {
        echo "   ✓ Meta box registration successful\n";
        $passed++;
    } else {
        echo "   ✗ Meta box registration did not produce expected output\n";
        $failed++;
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✗ Error calling add_tool_meta_box: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 5: Meta box render output
echo "\n5. Meta Box Render Output\n";
if (!class_exists('stdClass')) {
    class stdClass {}
}

// Mock post object
$mockPost = new stdClass();
$mockPost->ID = 123;

// Mock get_post_meta
if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        // Return test data
        $data = [
            '_kg_tool_type' => 'blw_test',
            '_kg_tool_icon' => 'fa-calculator',
            '_kg_is_active' => '1',
            '_kg_requires_auth' => '0',
        ];
        return isset($data[$key]) ? $data[$key] : '';
    }
}

// Mock wp_nonce_field
if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action, $name, $referer = true, $echo = true) {
        $output = "<input type='hidden' name='$name' value='mock_nonce' />";
        if ($echo) {
            echo $output;
        }
        return $output;
    }
}

// Mock esc_attr
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Mock esc_html
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Mock selected
if (!function_exists('selected')) {
    function selected($selected, $current, $echo = true) {
        $result = ($selected == $current) ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

// Mock checked
if (!function_exists('checked')) {
    function checked($checked, $current, $echo = true) {
        $result = ($checked == $current) ? ' checked="checked"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

try {
    ob_start();
    $tool->render_tool_meta_box($mockPost);
    $output = ob_get_clean();
    
    // Check for required form elements
    $requiredElements = [
        'kg_tool_type',
        'kg_tool_icon',
        'kg_is_active',
        'kg_requires_auth',
        'kg_tool_settings_nonce',
    ];
    
    $allPresent = true;
    foreach ($requiredElements as $element) {
        if (strpos($output, $element) !== false) {
            echo "   ✓ Form element '$element' present in output\n";
            $passed++;
        } else {
            echo "   ✗ Form element '$element' missing from output\n";
            $failed++;
            $allPresent = false;
        }
    }
    
    // Check for tool type options
    if (strpos($output, 'blw_test') !== false && strpos($output, 'BLW Hazırlık Testi') !== false) {
        echo "   ✓ Tool type options rendered\n";
        $passed++;
    } else {
        echo "   ✗ Tool type options not rendered correctly\n";
        $failed++;
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✗ Error rendering meta box: " . $e->getMessage() . "\n";
    $failed++;
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "Test Summary:\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✅ All integration tests passed!\n";
    exit(0);
} else {
    echo "\n❌ Some integration tests failed.\n";
    exit(1);
}

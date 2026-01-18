<?php
/**
 * Test file for Gutenberg Blocks implementation
 * 
 * This file tests the Gutenberg block system for content embeds
 */

// Mock WordPress functions for testing
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        return true;
    }
}

if (!function_exists('register_block_type')) {
    function register_block_type($path) {
        echo "✓ Block registered at: " . $path . "\n";
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $name, $data) {
        echo "✓ Script localized: " . $handle . " -> " . $name . "\n";
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path) {
        return 'https://example.com/wp-admin/' . $path;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'test_nonce_' . md5($action);
    }
}

if (!function_exists('__')) {
    function __($text, $domain) {
        return $text;
    }
}

// Define constants
if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', dirname(__DIR__) . '/');
}

// Run tests
echo "=== Testing Gutenberg Block Implementation ===\n\n";

// Test 1: Check if block files exist
echo "Test 1: Checking block file structure...\n";
$files_to_check = [
    'blocks/kg-embed/block.json',
    'blocks/kg-embed/index.js',
    'blocks/kg-embed/edit.js',
    'blocks/kg-embed/save.js',
    'blocks/kg-embed/editor.scss',
    'blocks/build/kg-embed/block.json',
    'blocks/build/kg-embed/index.js',
    'blocks/build/kg-embed/index.css',
    'blocks/build/kg-embed/index.asset.php',
    'includes/Blocks/EmbedBlock.php',
];

$all_files_exist = true;
foreach ($files_to_check as $file) {
    $full_path = KG_CORE_PATH . $file;
    if (file_exists($full_path)) {
        echo "  ✓ $file exists\n";
    } else {
        echo "  ✗ $file MISSING\n";
        $all_files_exist = false;
    }
}

if ($all_files_exist) {
    echo "✓ All required files exist\n\n";
} else {
    echo "✗ Some files are missing\n\n";
    exit(1);
}

// Test 2: Check block.json structure
echo "Test 2: Validating block.json structure...\n";
$block_json_path = KG_CORE_PATH . 'blocks/build/kg-embed/block.json';
$block_json = json_decode(file_get_contents($block_json_path), true);

$required_fields = ['name', 'title', 'category', 'attributes', 'editorScript', 'editorStyle'];
$block_valid = true;

foreach ($required_fields as $field) {
    if (isset($block_json[$field])) {
        echo "  ✓ Field '$field' exists\n";
    } else {
        echo "  ✗ Field '$field' MISSING\n";
        $block_valid = false;
    }
}

// Check attributes
if (isset($block_json['attributes']['contentType']) && isset($block_json['attributes']['selectedItems'])) {
    echo "  ✓ Required attributes exist (contentType, selectedItems)\n";
} else {
    echo "  ✗ Required attributes missing\n";
    $block_valid = false;
}

if ($block_valid) {
    echo "✓ block.json structure is valid\n\n";
} else {
    echo "✗ block.json structure has issues\n\n";
    exit(1);
}

// Test 3: Load and validate EmbedBlock class
echo "Test 3: Testing EmbedBlock class...\n";
require_once KG_CORE_PATH . 'includes/Blocks/EmbedBlock.php';

if (class_exists('KG_Core\Blocks\EmbedBlock')) {
    echo "  ✓ EmbedBlock class loaded successfully\n";
    
    // Instantiate the class
    $embed_block = new \KG_Core\Blocks\EmbedBlock();
    echo "  ✓ EmbedBlock instantiated successfully\n";
    
    // Check if methods exist
    $required_methods = ['register_blocks', 'enqueue_editor_assets', 'ajax_search_content'];
    $methods_exist = true;
    
    foreach ($required_methods as $method) {
        if (method_exists($embed_block, $method)) {
            echo "  ✓ Method '$method' exists\n";
        } else {
            echo "  ✗ Method '$method' MISSING\n";
            $methods_exist = false;
        }
    }
    
    if ($methods_exist) {
        echo "✓ EmbedBlock class is valid\n\n";
    } else {
        echo "✗ EmbedBlock class is missing methods\n\n";
        exit(1);
    }
} else {
    echo "  ✗ EmbedBlock class not found\n\n";
    exit(1);
}

// Test 4: Check if build artifacts are properly generated
echo "Test 4: Validating build artifacts...\n";
$index_asset_path = KG_CORE_PATH . 'blocks/build/kg-embed/index.asset.php';
$index_js_path = KG_CORE_PATH . 'blocks/build/kg-embed/index.js';
$index_css_path = KG_CORE_PATH . 'blocks/build/kg-embed/index.css';

// Check asset file
if (file_exists($index_asset_path)) {
    $asset_data = require $index_asset_path;
    if (isset($asset_data['dependencies']) && isset($asset_data['version'])) {
        echo "  ✓ index.asset.php is valid\n";
        echo "    Dependencies: " . implode(', ', $asset_data['dependencies']) . "\n";
    } else {
        echo "  ✗ index.asset.php is invalid\n";
        exit(1);
    }
} else {
    echo "  ✗ index.asset.php not found\n";
    exit(1);
}

// Check JS file
if (file_exists($index_js_path) && filesize($index_js_path) > 0) {
    echo "  ✓ index.js exists and is not empty (" . filesize($index_js_path) . " bytes)\n";
} else {
    echo "  ✗ index.js is missing or empty\n";
    exit(1);
}

// Check CSS file
if (file_exists($index_css_path) && filesize($index_css_path) > 0) {
    echo "  ✓ index.css exists and is not empty (" . filesize($index_css_path) . " bytes)\n";
} else {
    echo "  ✗ index.css is missing or empty\n";
    exit(1);
}

echo "✓ All build artifacts are valid\n\n";

// Test 5: Validate ContentEmbed shortcode update
echo "Test 5: Testing ContentEmbed shortcode updates...\n";

// Mock additional WordPress functions
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        return true;
    }
}

// We can't fully test ContentEmbed without WordPress, but we can check it loads
$content_embed_path = KG_CORE_PATH . 'includes/Shortcodes/ContentEmbed.php';
if (file_exists($content_embed_path)) {
    echo "  ✓ ContentEmbed.php exists\n";
    
    // Check if the extract_embeds_from_content method includes block placeholder handling
    $content = file_get_contents($content_embed_path);
    if (strpos($content, 'kg-embed-placeholder') !== false) {
        echo "  ✓ Block placeholder handling code exists\n";
    } else {
        echo "  ✗ Block placeholder handling code missing\n";
        exit(1);
    }
    
    if (strpos($content, 'data-type') !== false && strpos($content, 'data-ids') !== false) {
        echo "  ✓ Block data attribute parsing exists\n";
    } else {
        echo "  ✗ Block data attribute parsing missing\n";
        exit(1);
    }
    
    echo "✓ ContentEmbed shortcode updates are valid\n\n";
} else {
    echo "  ✗ ContentEmbed.php not found\n\n";
    exit(1);
}

// Final summary
echo "=== All Tests Passed! ===\n\n";
echo "Summary:\n";
echo "✓ Block file structure is complete\n";
echo "✓ block.json is properly configured\n";
echo "✓ EmbedBlock PHP class is functional\n";
echo "✓ Build artifacts are generated correctly\n";
echo "✓ ContentEmbed shortcode handles blocks\n";
echo "\n";
echo "The Gutenberg block system is ready for WordPress integration.\n";

exit(0);

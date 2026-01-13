<?php
/**
 * Test script for AIRecipeMigrator
 * 
 * This validates that the AIRecipeMigrator class loads correctly
 * and has all required methods.
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/');
}

if (!defined('KG_CORE_URL')) {
    define('KG_CORE_URL', 'http://localhost/');
}

if (!defined('KG_CORE_VERSION')) {
    define('KG_CORE_VERSION', '1.0.0');
}

// Mock WordPress functions
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => '/tmp/wp-uploads',
            'baseurl' => 'http://localhost/wp-content/uploads'
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        if (!is_dir($target)) {
            @mkdir($target, 0777, true);
        }
        return is_dir($target);
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Return dummy API key for testing
        if ($option === 'kg_ai_api_key' || $option === 'kg_openai_api_key') {
            return 'test-api-key';
        }
        if ($option === 'kg_ai_model') {
            return 'gpt-4o';
        }
        return $default;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args) {
        return [];
    }
}

if (!function_exists('get_post')) {
    function get_post($id) {
        return null;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return strip_tags($string);
    }
}

if (!function_exists('html_entity_decode')) {
    // Already exists in PHP
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        return new WP_Error('test', 'Mock function');
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return '';
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($args) {
        return 999;
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        return true;
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($post_id, $terms, $taxonomy) {
        return [];
    }
}

if (!function_exists('term_exists')) {
    function term_exists($term, $taxonomy) {
        return null;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy) {
        return ['term_id' => 1];
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post_id) {
        return null;
    }
}

if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail($post_id, $thumbnail_id) {
        return true;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($args) {
        return $args['ID'];
    }
}

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = []) {
        return true;
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        
        public function __construct($code, $message) {
            $this->code = $code;
            $this->message = $message;
        }
        
        public function get_error_message() {
            return $this->message;
        }
        
        public function get_error_code() {
            return $this->code;
        }
    }
}

// Load required classes
require_once __DIR__ . '/includes/Migration/MigrationLogger.php';
require_once __DIR__ . '/includes/Migration/AIRecipeMigrator.php';

// Run tests
echo "Testing AIRecipeMigrator...\n\n";

try {
    // Test 1: Class instantiation
    echo "Test 1: Class instantiation... ";
    $migrator = new \KG_Core\Migration\AIRecipeMigrator();
    echo "✅ PASS\n";
    
    // Test 2: Check required methods exist
    echo "Test 2: Required methods exist... ";
    $required_methods = ['migrate', 'migrateBatch', 'migrateAll', 'getRecipeIds'];
    foreach ($required_methods as $method) {
        if (!method_exists($migrator, $method)) {
            throw new Exception("Method {$method} not found!");
        }
    }
    echo "✅ PASS\n";
    
    // Test 3: getRecipeIds returns array
    echo "Test 3: getRecipeIds returns array... ";
    $ids = $migrator->getRecipeIds();
    if (!is_array($ids)) {
        throw new Exception("getRecipeIds should return array!");
    }
    echo "✅ PASS (found " . count($ids) . " recipe IDs)\n";
    
    // Test 4: Verify AI prompt structure
    echo "Test 4: AI prompt includes all required fields... ";
    $reflection = new ReflectionClass($migrator);
    $method = $reflection->getMethod('buildPrompt');
    $method->setAccessible(true);
    $prompt = $method->invoke($migrator, 'Test Recipe', 'Test content with ingredients');
    
    $required_keywords = [
        'description', 'ingredients', 'instructions', 'substitutes',
        'expert', 'special_notes', 'nutrition', 'prep_time',
        'age_group', 'allergens', 'diet_types', 'meal_types', 'main_ingredient'
    ];
    
    foreach ($required_keywords as $keyword) {
        if (strpos($prompt, $keyword) === false) {
            throw new Exception("Prompt missing keyword: {$keyword}");
        }
    }
    echo "✅ PASS\n";
    
    // Test 5: Check duplicate prevention logic
    echo "Test 5: Duplicate prevention method exists... ";
    $method = $reflection->getMethod('getExistingRecipe');
    $method->setAccessible(true);
    $result = $method->invoke($migrator, 123);
    if (!is_null($result) && !is_int($result)) {
        throw new Exception("getExistingRecipe should return int or null!");
    }
    echo "✅ PASS\n";
    
    echo "\n✅ All tests passed!\n\n";
    echo "AIRecipeMigrator is ready to use.\n";
    echo "Configure OpenAI API key in WordPress Settings > AI Settings.\n";
    
} catch (Exception $e) {
    echo "❌ FAIL\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

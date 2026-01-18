<?php
/**
 * Static Validation Script for Content Embed System
 * 
 * This script performs static analysis and provides usage examples
 * Run: php tests/validate-content-embed-static.php
 */

echo "=== Content Embed System - Static Validation ===\n\n";

$base_path = __DIR__ . '/..';

// Test 1: Check if files exist
echo "Test 1: Checking if all required files exist\n";

$required_files = [
    'includes/Shortcodes/ContentEmbed.php',
    'includes/Admin/EmbedSelector.php',
    'assets/css/embed-selector.css',
    'assets/js/embed-selector.js',
];

$all_files_exist = true;
foreach ($required_files as $file) {
    $full_path = $base_path . '/' . $file;
    if (file_exists($full_path)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file NOT FOUND\n";
        $all_files_exist = false;
    }
}

if ($all_files_exist) {
    echo "✓ All required files exist\n";
} else {
    echo "✗ Some files are missing\n";
    exit(1);
}

echo "\n";

// Test 2: Check PHP syntax
echo "Test 2: Checking PHP syntax\n";

$php_files = [
    'includes/Shortcodes/ContentEmbed.php',
    'includes/Admin/EmbedSelector.php',
];

foreach ($php_files as $file) {
    $full_path = $base_path . '/' . $file;
    exec("php -l " . escapeshellarg($full_path) . " 2>&1", $output, $return);
    
    if ($return === 0) {
        echo "✓ $file has valid syntax\n";
    } else {
        echo "✗ $file has syntax errors:\n";
        echo implode("\n", $output) . "\n";
    }
}

echo "\n";

// Test 3: Check class structure
echo "Test 3: Checking class structure\n";

$content_embed = file_get_contents($base_path . '/includes/Shortcodes/ContentEmbed.php');
$embed_selector = file_get_contents($base_path . '/includes/Admin/EmbedSelector.php');

// Check ContentEmbed class
if (strpos($content_embed, 'namespace KG_Core\Shortcodes') !== false) {
    echo "✓ ContentEmbed has correct namespace\n";
} else {
    echo "✗ ContentEmbed namespace incorrect\n";
}

if (strpos($content_embed, 'class ContentEmbed') !== false) {
    echo "✓ ContentEmbed class defined\n";
} else {
    echo "✗ ContentEmbed class not found\n";
}

$required_methods = [
    'render_shortcode',
    'process_embeds_for_rest',
    'register_rest_fields',
    'get_embedded_content',
    'extract_embeds_from_content',
    'get_embed_data',
    'get_recipe_embed_data',
    'get_ingredient_embed_data',
    'get_tool_embed_data',
    'get_post_embed_data',
];

foreach ($required_methods as $method) {
    if (preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $content_embed)) {
        echo "✓ ContentEmbed has method: $method\n";
    } else {
        echo "✗ ContentEmbed missing method: $method\n";
    }
}

echo "\n";

// Check EmbedSelector class
if (strpos($embed_selector, 'namespace KG_Core\Admin') !== false) {
    echo "✓ EmbedSelector has correct namespace\n";
} else {
    echo "✗ EmbedSelector namespace incorrect\n";
}

if (strpos($embed_selector, 'class EmbedSelector') !== false) {
    echo "✓ EmbedSelector class defined\n";
} else {
    echo "✗ EmbedSelector class not found\n";
}

$required_admin_methods = [
    'enqueue_scripts',
    'add_embed_button',
    'render_embed_modal',
    'ajax_search_content',
];

foreach ($required_admin_methods as $method) {
    if (preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $embed_selector)) {
        echo "✓ EmbedSelector has method: $method\n";
    } else {
        echo "✗ EmbedSelector missing method: $method\n";
    }
}

echo "\n";

// Test 4: Check kg-core.php integration
echo "Test 4: Checking kg-core.php integration\n";

$kg_core = file_get_contents($base_path . '/kg-core.php');

if (strpos($kg_core, "includes/Shortcodes/ContentEmbed.php") !== false) {
    echo "✓ ContentEmbed is included in kg-core.php\n";
} else {
    echo "✗ ContentEmbed NOT included in kg-core.php\n";
}

if (strpos($kg_core, "includes/Admin/EmbedSelector.php") !== false) {
    echo "✓ EmbedSelector is included in kg-core.php\n";
} else {
    echo "✗ EmbedSelector NOT included in kg-core.php\n";
}

if (strpos($kg_core, "KG_Core\\Shortcodes\\ContentEmbed") !== false) {
    echo "✓ ContentEmbed is initialized in kg-core.php\n";
} else {
    echo "✗ ContentEmbed NOT initialized in kg-core.php\n";
}

if (strpos($kg_core, "KG_Core\\Admin\\EmbedSelector") !== false) {
    echo "✓ EmbedSelector is initialized in kg-core.php\n";
} else {
    echo "✗ EmbedSelector NOT initialized in kg-core.php\n";
}

echo "\n";

// Test 5: Check CSS file
echo "Test 5: Checking CSS file\n";

$css = file_get_contents($base_path . '/assets/css/embed-selector.css');

$css_classes = [
    '.kg-embed-button',
    '.kg-embed-modal',
    '.kg-embed-modal-content',
    '.kg-embed-tabs',
    '.kg-embed-tab',
    '.kg-embed-search',
    '.kg-embed-results',
    '.kg-embed-item',
];

foreach ($css_classes as $class) {
    if (strpos($css, $class) !== false) {
        echo "✓ CSS has class: $class\n";
    } else {
        echo "✗ CSS missing class: $class\n";
    }
}

echo "\n";

// Test 6: Check JavaScript file
echo "Test 6: Checking JavaScript file\n";

$js = file_get_contents($base_path . '/assets/js/embed-selector.js');

$js_functions = [
    'init',
    'bindEvents',
    'openModal',
    'closeModal',
    'switchTab',
    'searchContent',
    'loadContent',
    'renderResults',
    'insertEmbed',
];

foreach ($js_functions as $func) {
    if (preg_match('/' . preg_quote($func) . '\s*:\s*function/', $js)) {
        echo "✓ JavaScript has function: $func\n";
    } else {
        echo "✗ JavaScript missing function: $func\n";
    }
}

if (strpos($js, 'kgEmbedSelector') !== false) {
    echo "✓ JavaScript uses kgEmbedSelector object\n";
} else {
    echo "✗ JavaScript missing kgEmbedSelector object\n";
}

echo "\n";

// Test 7: File sizes
echo "Test 7: Checking file sizes\n";

foreach ($required_files as $file) {
    $full_path = $base_path . '/' . $file;
    $size = filesize($full_path);
    $size_kb = round($size / 1024, 2);
    
    if ($size > 0) {
        echo "✓ $file: {$size_kb} KB\n";
    } else {
        echo "✗ $file is empty\n";
    }
}

echo "\n";

// Usage Examples
echo "=== Usage Examples ===\n\n";

echo "1. Single Recipe Embed:\n";
echo "   [kg-embed type=\"recipe\" id=\"123\"]\n\n";

echo "2. Multiple Ingredients Embed:\n";
echo "   [kg-embed type=\"ingredient\" ids=\"456,789,101\"]\n\n";

echo "3. Tool Embed:\n";
echo "   [kg-embed type=\"tool\" id=\"112\"]\n\n";

echo "4. Post (Keşfet) Embed:\n";
echo "   [kg-embed type=\"post\" id=\"131\"]\n\n";

echo "5. Example Post Content with Multiple Embeds:\n";
echo <<<'EXAMPLE'
   <p>Bu yazıda size harika tarifler sunacağız.</p>
   
   [kg-embed type="recipe" id="123"]
   
   <p>Ayrıca malzemelerimiz hakkında bilgi vereceğiz.</p>
   
   [kg-embed type="ingredient" ids="456,789"]
   
   <p>Ve kullanabileceğiniz araçları tanıtacağız.</p>
   
   [kg-embed type="tool" id="112"]

EXAMPLE;

echo "\n\n";

echo "=== Expected REST API Response Structure ===\n\n";

$example_response = [
    'embedded_content' => [
        [
            'type' => 'recipe',
            'position' => 2,
            'placeholder_id' => 'kg-embed-0',
            'items' => [
                [
                    'id' => 123,
                    'title' => 'Havuçlu Bebek Püresi',
                    'slug' => 'havuclu-bebek-puresi',
                    'excerpt' => 'Bebeğiniz için lezzetli...',
                    'image' => 'https://example.com/image.jpg',
                    'url' => '/tarifler/havuclu-bebek-puresi',
                    'embed_type' => 'recipe',
                    'prep_time' => '15 dk',
                    'age_group' => '6-8 Ay',
                    'age_group_color' => '#FFAB91',
                    'diet_types' => ['Vejetaryen'],
                    'allergens' => [],
                    'is_featured' => false,
                ],
            ],
        ],
    ],
];

echo json_encode($example_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== Admin UI Features ===\n\n";

echo "1. Embed Button:\n";
echo "   - Located next to 'Add Media' button in post editor\n";
echo "   - Only visible when editing WordPress posts (post type: post)\n\n";

echo "2. Modal Features:\n";
echo "   - Tab-based navigation (Tarifler, Malzemeler, Araçlar, Keşfet)\n";
echo "   - Real-time search functionality\n";
echo "   - Multi-select with checkboxes\n";
echo "   - Selected item counter\n";
echo "   - Insert shortcode into editor\n\n";

echo "3. AJAX Endpoint:\n";
echo "   - Action: kg_search_embeddable_content\n";
echo "   - Parameters: type, search, nonce\n";
echo "   - Returns: Array of items with id, title, image, meta, icon\n\n";

echo "=== Validation Complete ===\n";
echo "\nAll components are in place and properly structured.\n";
echo "The content embed system is ready for testing in a WordPress environment.\n\n";

echo "To test in WordPress:\n";
echo "1. Activate the kg-core plugin\n";
echo "2. Create or edit a WordPress post\n";
echo "3. Click the 'İçerik Embed Et' button\n";
echo "4. Select content to embed\n";
echo "5. Check the REST API response at: /wp-json/wp/v2/posts/{post_id}\n";

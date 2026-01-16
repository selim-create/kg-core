<?php
/**
 * Test for Stain Encyclopedia Backend Implementation
 * 
 * This test verifies:
 * 1. Stain database has 40+ stains
 * 2. All popular stains from frontend exist
 * 3. All stains have required fields (id, slug, name, emoji, category, difficulty, steps, warnings, related_ingredients)
 * 4. Categories are expanded to 5 (food, bodily, outdoor, craft, household)
 * 5. Turkish character normalization works
 * 6. Popular stains endpoint exists
 */

echo "=== Stain Encyclopedia Backend Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Mock WordPress functions for testing
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {}
}
if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args) {}
}

// Load the controller file
require_once $baseDir . '/includes/API/SponsoredToolController.php';

// Create reflection to access private methods
$reflection = new ReflectionClass('KG_Core\API\SponsoredToolController');

// Test 1: Check stain database size
echo "1. Stain Database Size\n";
$method = $reflection->getMethod('get_stain_database');
$method->setAccessible(true);
$controller = new KG_Core\API\SponsoredToolController();
$stains = $method->invoke($controller);

if (count($stains) >= 40) {
    echo "   ✓ Database has " . count($stains) . " stains (requirement: 40+)\n";
    $passed++;
} else {
    echo "   ✗ Database has only " . count($stains) . " stains (requirement: 40+)\n";
    $failed++;
}

// Test 2: Check all popular stains exist
echo "\n2. Popular Stains from Frontend\n";
$popular_slugs = [
    'domates-lekesi' => 'Domates',
    'cikolata-lekesi' => 'Çikolata',
    'muz-lekesi' => 'Muz',
    'havuc-lekesi' => 'Havuç',
    'cim-lekesi' => 'Çim',
    'kaka-lekesi' => 'Kaka',
    'kusmuk-lekesi' => 'Kusmuk',
    'anne-sutu-lekesi' => 'Anne Sütü',
];

$stain_slugs = array_column($stains, 'slug');
foreach ($popular_slugs as $slug => $name) {
    if (in_array($slug, $stain_slugs)) {
        echo "   ✓ $name ($slug) exists\n";
        $passed++;
    } else {
        echo "   ✗ $name ($slug) NOT FOUND\n";
        $failed++;
    }
}

// Test 3: Check data structure for each stain
echo "\n3. Data Structure Validation\n";
$required_fields = ['id', 'slug', 'name', 'emoji', 'category', 'difficulty', 'steps', 'warnings', 'related_ingredients'];
$old_fields = ['removal_steps', 'products', 'tips', 'description'];

$structure_passed = true;
$sample_stain = $stains[0];

foreach ($required_fields as $field) {
    if (!isset($sample_stain[$field])) {
        echo "   ✗ Missing required field: $field\n";
        $failed++;
        $structure_passed = false;
    }
}

if ($structure_passed) {
    echo "   ✓ All required fields present (id, slug, name, emoji, category, difficulty, steps, warnings, related_ingredients)\n";
    $passed++;
}

// Check for old fields that should be removed
$old_fields_found = false;
foreach ($old_fields as $field) {
    if (isset($sample_stain[$field])) {
        echo "   ✗ Old field still present: $field\n";
        $failed++;
        $old_fields_found = true;
    }
}

if (!$old_fields_found) {
    echo "   ✓ Old fields removed (removal_steps, products, tips, description)\n";
    $passed++;
}

// Test 4: Check steps format
echo "\n4. Steps Format Validation\n";
if (isset($sample_stain['steps']) && is_array($sample_stain['steps']) && count($sample_stain['steps']) > 0) {
    $first_step = $sample_stain['steps'][0];
    if (isset($first_step['step']) && isset($first_step['instruction'])) {
        echo "   ✓ Steps format correct (step, instruction, optional tip)\n";
        $passed++;
    } else {
        echo "   ✗ Steps format incorrect\n";
        $failed++;
    }
} else {
    echo "   ✗ Steps not found or empty\n";
    $failed++;
}

// Test 5: Check categories
echo "\n5. Category Expansion\n";
$method = $reflection->getMethod('get_stain_categories');
$method->setAccessible(true);
$categories = $method->invoke($controller);

$expected_categories = ['food', 'bodily', 'outdoor', 'craft', 'household'];
$category_ids = array_column($categories, 'id');

if (count($categories) === 5) {
    echo "   ✓ 5 categories defined\n";
    $passed++;
} else {
    echo "   ✗ Only " . count($categories) . " categories (expected 5)\n";
    $failed++;
}

foreach ($expected_categories as $cat_id) {
    if (in_array($cat_id, $category_ids)) {
        $cat_label = '';
        foreach ($categories as $cat) {
            if ($cat['id'] === $cat_id) {
                $cat_label = $cat['label'];
                break;
            }
        }
        echo "   ✓ Category '$cat_id' exists ($cat_label)\n";
        $passed++;
    } else {
        echo "   ✗ Category '$cat_id' NOT FOUND\n";
        $failed++;
    }
}

// Test 6: Category distribution
echo "\n6. Category Distribution\n";
$category_counts = [];
foreach ($stains as $stain) {
    $cat = $stain['category'];
    if (!isset($category_counts[$cat])) {
        $category_counts[$cat] = 0;
    }
    $category_counts[$cat]++;
}

foreach ($category_counts as $cat => $count) {
    echo "   • $cat: $count stains\n";
}

// Verify minimum counts
$min_counts = ['food' => 20, 'bodily' => 8, 'outdoor' => 4, 'craft' => 4, 'household' => 4];
$distribution_ok = true;
foreach ($min_counts as $cat => $min) {
    $actual = $category_counts[$cat] ?? 0;
    if ($actual >= $min) {
        echo "   ✓ $cat has $actual stains (min: $min)\n";
        $passed++;
    } else {
        echo "   ✗ $cat has only $actual stains (min: $min)\n";
        $failed++;
        $distribution_ok = false;
    }
}

// Test 7: Turkish character normalization
echo "\n7. Turkish Character Normalization\n";
$method = $reflection->getMethod('normalize_turkish');
$method->setAccessible(true);

$test_cases = [
    'çikolata' => 'cikolata',
    'süt' => 'sut',
    'yağ' => 'yag',
    'gözyaşı' => 'gozyasi',
    'ıspanak' => 'ispanak',
    'ÇIKOLATA' => 'cikolata',
];

$normalize_ok = true;
foreach ($test_cases as $input => $expected) {
    $result = $method->invoke($controller, $input);
    if ($result === $expected) {
        echo "   ✓ '$input' → '$result'\n";
        $passed++;
    } else {
        echo "   ✗ '$input' → '$result' (expected: '$expected')\n";
        $failed++;
        $normalize_ok = false;
    }
}

// Test 8: Check routes registration
echo "\n8. API Route Registration\n";
$controllerFile = $baseDir . '/includes/API/SponsoredToolController.php';
$content = file_get_contents($controllerFile);

$required_routes = [
    '/tools/stain-encyclopedia/search',
    '/tools/stain-encyclopedia/popular',
    '/tools/stain-encyclopedia/(?P<slug>[a-zA-Z0-9_-]+)',
];

foreach ($required_routes as $route) {
    if (strpos($content, $route) !== false) {
        echo "   ✓ Route registered: $route\n";
        $passed++;
    } else {
        echo "   ✗ Route NOT found: $route\n";
        $failed++;
    }
}

// Test 9: Check required methods exist
echo "\n9. Required Methods\n";
$required_methods = [
    'search_stains',
    'get_popular_stains',
    'get_stain_detail',
    'get_stain_database',
    'get_stain_categories',
    'normalize_turkish',
];

foreach ($required_methods as $method_name) {
    if ($reflection->hasMethod($method_name)) {
        echo "   ✓ Method exists: $method_name\n";
        $passed++;
    } else {
        echo "   ✗ Method NOT found: $method_name\n";
        $failed++;
    }
}

// Test 10: Validate specific stains have complete data
echo "\n10. Sample Stain Data Validation\n";
$samples = ['domates-lekesi', 'cikolata-lekesi', 'kaka-lekesi', 'cim-lekesi'];
foreach ($samples as $slug) {
    $stain = null;
    foreach ($stains as $s) {
        if ($s['slug'] === $slug) {
            $stain = $s;
            break;
        }
    }
    
    if ($stain) {
        $has_emoji = !empty($stain['emoji']);
        $has_steps = isset($stain['steps']) && count($stain['steps']) > 0;
        $has_warnings = isset($stain['warnings']) && is_array($stain['warnings']);
        $has_ingredients = isset($stain['related_ingredients']) && count($stain['related_ingredients']) > 0;
        
        if ($has_emoji && $has_steps && $has_warnings && $has_ingredients) {
            echo "   ✓ $slug: Complete data (emoji: {$stain['emoji']}, steps: " . count($stain['steps']) . ", ingredients: " . count($stain['related_ingredients']) . ")\n";
            $passed++;
        } else {
            echo "   ✗ $slug: Incomplete data\n";
            if (!$has_emoji) echo "      - Missing emoji\n";
            if (!$has_steps) echo "      - Missing steps\n";
            if (!$has_warnings) echo "      - Missing warnings\n";
            if (!$has_ingredients) echo "      - Missing related_ingredients\n";
            $failed++;
        }
    } else {
        echo "   ✗ $slug: NOT FOUND\n";
        $failed++;
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Total stains in database: " . count($stains) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "\n";

if ($failed === 0) {
    echo "✓ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "✗ SOME TESTS FAILED\n";
    exit(1);
}

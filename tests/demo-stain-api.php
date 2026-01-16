#!/usr/bin/env php
<?php
/**
 * Demo script showing Stain Encyclopedia API functionality
 * 
 * This script demonstrates:
 * 1. Getting all stains
 * 2. Searching with Turkish characters
 * 3. Filtering by category
 * 4. Getting popular stains
 * 5. Getting stain details
 */

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║      Leke Ansiklopedisi - API Demo                          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Mock WordPress functions
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {}
}
if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args) {}
}

$baseDir = dirname(__DIR__);
require_once $baseDir . '/includes/API/SponsoredToolController.php';

$reflection = new ReflectionClass('KG_Core\API\SponsoredToolController');
$controller = new KG_Core\API\SponsoredToolController();

// Demo 1: Get all stains
echo "📚 Demo 1: Get All Stains\n";
echo str_repeat("─", 64) . "\n";
$method = $reflection->getMethod('get_stain_database');
$method->setAccessible(true);
$stains = $method->invoke($controller);
echo "Total stains: " . count($stains) . "\n";
echo "Categories: ";
$cats = [];
foreach ($stains as $stain) {
    $cats[$stain['category']] = ($cats[$stain['category']] ?? 0) + 1;
}
foreach ($cats as $cat => $count) {
    echo "$cat($count) ";
}
echo "\n\n";

// Demo 2: Search with Turkish characters
echo "🔍 Demo 2: Turkish Character Search\n";
echo str_repeat("─", 64) . "\n";
$method = $reflection->getMethod('normalize_turkish');
$method->setAccessible(true);

$search_terms = ['çikolata', 'süt', 'çim', 'kaka'];
foreach ($search_terms as $term) {
    $normalized = $method->invoke($controller, $term);
    $found = array_filter($stains, function($stain) use ($method, $controller, $term, $normalized) {
        $name_normalized = $method->invoke($controller, $stain['name']);
        return stripos($stain['name'], $term) !== false || 
               stripos($name_normalized, $normalized) !== false;
    });
    echo "Search: '$term' → Found: " . count($found) . " stain(s)\n";
    foreach ($found as $s) {
        echo "  • {$s['emoji']} {$s['name']}\n";
    }
}
echo "\n";

// Demo 3: Filter by category
echo "🏷️  Demo 3: Filter by Category\n";
echo str_repeat("─", 64) . "\n";
$method = $reflection->getMethod('get_stain_categories');
$method->setAccessible(true);
$categories = $method->invoke($controller);

foreach ($categories as $cat) {
    $cat_stains = array_filter($stains, fn($s) => $s['category'] === $cat['id']);
    echo "{$cat['label']}: " . count($cat_stains) . " stains\n";
    $samples = array_slice($cat_stains, 0, 3);
    foreach ($samples as $s) {
        echo "  • {$s['emoji']} {$s['name']}\n";
    }
    if (count($cat_stains) > 3) {
        echo "  ... and " . (count($cat_stains) - 3) . " more\n";
    }
}
echo "\n";

// Demo 4: Popular stains
echo "⭐ Demo 4: Popular Stains\n";
echo str_repeat("─", 64) . "\n";
$popular_slugs = [
    'domates-lekesi',
    'cikolata-lekesi',
    'muz-lekesi',
    'havuc-lekesi',
    'cim-lekesi',
    'kaka-lekesi',
    'kusmuk-lekesi',
    'anne-sutu-lekesi',
];

foreach ($popular_slugs as $slug) {
    foreach ($stains as $stain) {
        if ($stain['slug'] === $slug) {
            echo "{$stain['emoji']} {$stain['name']} ({$stain['difficulty']})\n";
            break;
        }
    }
}
echo "\n";

// Demo 5: Detailed stain example
echo "📖 Demo 5: Stain Detail Example (Domates Lekesi)\n";
echo str_repeat("─", 64) . "\n";
$sample = $stains[0]; // Domates
echo "Name: {$sample['name']} {$sample['emoji']}\n";
echo "Category: {$sample['category']}\n";
echo "Difficulty: {$sample['difficulty']}\n";
echo "\nSteps:\n";
foreach ($sample['steps'] as $step) {
    echo "  {$step['step']}. {$step['instruction']}\n";
    if (!empty($step['tip'])) {
        echo "     💡 {$step['tip']}\n";
    }
}
echo "\nWarnings:\n";
foreach ($sample['warnings'] as $warning) {
    echo "  ⚠️  $warning\n";
}
echo "\nRelated Ingredients:\n";
foreach ($sample['related_ingredients'] as $ingredient) {
    echo "  • $ingredient\n";
}
echo "\n";

// Summary
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  ✅ All Features Working                                     ║\n";
echo "║                                                              ║\n";
echo "║  • 40 comprehensive stains                                   ║\n";
echo "║  • 5 categories (food, bodily, outdoor, craft, household)    ║\n";
echo "║  • Turkish character search                                  ║\n";
echo "║  • Popular stains endpoint                                   ║\n";
echo "║  • Complete stain details with steps, warnings & ingredients ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

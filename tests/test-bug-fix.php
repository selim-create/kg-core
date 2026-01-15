<?php
/**
 * Test script for the specific bug scenario from issue
 * Tests the Brokoli Çorbası content from post ID 6490
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/');
}

// Mock WordPress functions
if (!function_exists('strip_shortcodes')) {
    function strip_shortcodes($content) {
        return $content;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return strip_tags($string);
    }
}

// Load migration classes
require_once __DIR__ . '/includes/Migration/ContentParser.php';
require_once __DIR__ . '/includes/Migration/IngredientParser.php';

use KG_Core\Migration\ContentParser;
use KG_Core\Migration\IngredientParser;

echo "=== Testing Bug Fix for Brokoli Çorbası (Post ID 6490) ===\n\n";

// The actual problematic content from the issue
$problematicContent = <<<HTML
<h2>Brokoli Çorbası</h2>

<h3>Malzemeler</h3>
* 3 çiçek brokoli
* 1/4 adet küçük kuru soğan,
* 2-3  bardak su
* 1-2 ölçek formül mama, 1 çay bardağı devam sütü veya inek sütü..(Tarifteki inek sütü 1 yaş üzeri içindir. 1 yaş altında kullanmanız önerilmez.)
* 1 tatlı kaşığı sızma zeytinyağı
* File badem (yetişkinler ve büyük yaş grubu çocuklar için )

<h3>Hazırlanışı</h3>
* Soğan tencerede zeytinyağında sote edilir.
* Ardından tencereye brokoli ve su ilave edilerek bir taşım kaynatılır.
* Blenderda püre haline getirilir fakat biraz taneli kalması tercih edilmelidir.
* Süt ilave edilerek 5 dk daha kaynatılır. ( Formül mama ya da anne sütü ilave edeceksiniz çorba piştikten sonra ekleyin. Bu iki süt türü pişirilmemelidir.)
* Büyük çocuklar ve yetişkinlere biraz tuz ilave edilir, ardından file badem ile süsleyerek servis edilir.

<p>Süt: Çocuğunuzun inek sütü alerjisi yoksa 9-10 ay üstü bebeğinize devam sütü ile de yapabilirsiniz...</p>

<p>Not: İçine ev yapımı bir iki et suyu bulyon da ilave edebilirsiniz.</p>

<h3>Doç.Dr. Enver Mahir Gülcan'ın notu</h3>
<p>Brokoli içerisinde izotiyosiyonat isimli fitokimyasallar bulunmaktadır. Bu maddeler vücudun antioksidan enzimlerini harekete geçirerek, vücudu kanser riskine karşı koruyan maddelerdir.</p>
HTML;

// Test ContentParser
echo "=== STEP 1: ContentParser Testing ===\n";
echo "--------------------------------------\n";

$parser = new ContentParser();
$result = $parser->parse($problematicContent, 'Brokoli Çorbası');

echo "\n📝 INGREDIENTS EXTRACTED:\n";
echo "Count: " . count($result['ingredients']) . "\n";
foreach ($result['ingredients'] as $i => $ing) {
    echo ($i + 1) . ". {$ing}\n";
}

echo "\n📝 INSTRUCTIONS EXTRACTED:\n";
echo "Count: " . count($result['instructions']) . "\n";
foreach ($result['instructions'] as $i => $inst) {
    echo ($i + 1) . ". {$inst}\n";
}

echo "\n📝 EXPERT NOTE:\n";
echo "Name: " . ($result['expert_name'] ?: 'NOT FOUND ❌') . "\n";
echo "Title: " . ($result['expert_title'] ?: 'NOT FOUND ❌') . "\n";
echo "Note: " . (strlen($result['expert_note']) > 0 ? substr($result['expert_note'], 0, 100) . '...' : 'NOT FOUND ❌') . "\n";

echo "\n📝 SPECIAL NOTES:\n";
echo ($result['special_notes'] ?: 'NOT FOUND ❌') . "\n";

// Test IngredientParser
echo "\n\n=== STEP 2: IngredientParser Testing ===\n";
echo "-----------------------------------------\n";

$ingredientParser = new IngredientParser();

foreach ($result['ingredients'] as $i => $rawIng) {
    $parsed = $ingredientParser->parse($rawIng);
    echo "\n" . ($i + 1) . ". Original: {$rawIng}\n";
    echo "   ✓ Quantity: " . ($parsed['quantity'] ?: '(empty)') . "\n";
    echo "   ✓ Unit: " . ($parsed['unit'] ?: '(empty)') . "\n";
    echo "   ✓ Name: " . ($parsed['name'] ?: '(empty)') . "\n";
    echo "   ✓ Note: " . ($parsed['preparation_note'] ?: '(none)') . "\n";
}

echo "\n\n=== EXPECTED VS ACTUAL ===\n";
echo "----------------------------\n";

$expected = [
    ['quantity' => '3', 'unit' => 'çiçek', 'name' => 'Brokoli'],
    ['quantity' => '1/4', 'unit' => 'adet', 'name' => 'Kuru Soğan'],
    ['quantity' => '2-3', 'unit' => 'bardak', 'name' => 'Su'],
    ['quantity' => '1-2', 'unit' => 'ölçek', 'name' => 'Formül Mama'],
    ['quantity' => '1', 'unit' => 'tatlı kaşığı', 'name' => 'Sızma Zeytinyağı'],
    ['quantity' => '', 'unit' => '', 'name' => 'File Badem'],
];

echo "\nIngredients Check:\n";
$hasIssues = false;
foreach ($expected as $i => $exp) {
    if (!isset($result['ingredients'][$i])) {
        echo "❌ Missing ingredient " . ($i + 1) . ": {$exp['name']}\n";
        $hasIssues = true;
        continue;
    }
    
    $parsed = $ingredientParser->parse($result['ingredients'][$i]);
    
    // Check quantity
    if ($parsed['quantity'] !== $exp['quantity']) {
        echo "❌ Ingredient " . ($i + 1) . " ({$exp['name']}): Expected quantity '{$exp['quantity']}', got '{$parsed['quantity']}'\n";
        $hasIssues = true;
    }
    
    // Check unit
    if ($parsed['unit'] !== $exp['unit']) {
        echo "❌ Ingredient " . ($i + 1) . " ({$exp['name']}): Expected unit '{$exp['unit']}', got '{$parsed['unit']}'\n";
        $hasIssues = true;
    }
    
    // Check name (case-insensitive)
    if (strtolower($parsed['name']) !== strtolower($exp['name'])) {
        echo "❌ Ingredient " . ($i + 1) . ": Expected name '{$exp['name']}', got '{$parsed['name']}'\n";
        $hasIssues = true;
    }
}

if (!$hasIssues) {
    echo "✅ All ingredient parsing checks PASSED!\n";
}

echo "\nExpert Note Check:\n";
if ($result['expert_name'] === 'Enver Mahir Gülcan' && $result['expert_title'] === 'Doç.Dr.') {
    echo "✅ Expert name and title extraction PASSED!\n";
} else {
    echo "❌ Expert extraction FAILED\n";
    echo "   Expected: name='Enver Mahir Gülcan', title='Doç.Dr.'\n";
    echo "   Got: name='{$result['expert_name']}', title='{$result['expert_title']}'\n";
}

echo "\nSpecial Notes Check:\n";
if (strpos($result['special_notes'], 'Süt:') !== false && strpos($result['special_notes'], 'Not:') !== false) {
    echo "✅ Special notes extraction PASSED!\n";
} else {
    echo "❌ Special notes extraction FAILED\n";
    echo "   Got: '{$result['special_notes']}'\n";
}

echo "\n=== Test Complete ===\n";

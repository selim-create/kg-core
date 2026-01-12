<?php
/**
 * Test script for migration system components
 * 
 * This script tests individual components without running actual migrations
 * Run from command line: php test-migration.php
 */

// Simulate WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/');
}

// Mock WordPress functions for testing
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

if (!function_exists('get_page_by_title')) {
    function get_page_by_title($title, $output = OBJECT, $post_type = 'post') {
        return null;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('get_users')) {
    function get_users($args) {
        return [];
    }
}

// Mock WP_Query
if (!class_exists('WP_Query')) {
    class WP_Query {
        public function __construct($args) {}
        public function have_posts() { return false; }
        public function the_post() {}
    }
}

if (!function_exists('wp_reset_postdata')) {
    function wp_reset_postdata() {}
}

if (!function_exists('get_the_ID')) {
    function get_the_ID() { return 0; }
}

// Load migration classes
require_once __DIR__ . '/includes/Migration/ContentParser.php';
require_once __DIR__ . '/includes/Migration/IngredientParser.php';
require_once __DIR__ . '/includes/Migration/AgeGroupMapper.php';

use KG_Core\Migration\ContentParser;
use KG_Core\Migration\IngredientParser;
use KG_Core\Migration\AgeGroupMapper;

echo "=== KG Core Migration System Test ===\n\n";

// Test 1: ContentParser
echo "Test 1: ContentParser\n";
echo "---------------------\n";

$sampleContent = <<<HTML
<h2>Brokoli Çorbası</h2>
<p>9 ay ve sonrası için harika bir tarif.</p>

<h3>Malzemeler</h3>
<ul>
<li>• 3 çiçek brokoli</li>
<li>• 1/4 adet küçük kuru soğan</li>
<li>• 2 yemek kaşığı zeytinyağı</li>
<li>• 1 su bardağı su</li>
</ul>

<h3>Hazırlanışı</h3>
<p>• Soğan tencerede zeytinyağında sote edilir.</p>
<p>• Ardından brokoli eklenir.</p>
<p>• Su ilave edilip 15 dakika pişirilir.</p>

<h3>Dyt. Figen Üvez'in notu:</h3>
<p>Brokoli C vitamini açısından çok zengindir. 9. aydan itibaren verilebilir.</p>
HTML;

$parser = new ContentParser();
$result = $parser->parse($sampleContent, 'Brokoli Çorbası 9 Ay');

echo "Ingredients found: " . count($result['ingredients']) . "\n";
if (!empty($result['ingredients'])) {
    echo "Sample ingredients:\n";
    foreach (array_slice($result['ingredients'], 0, 3) as $ing) {
        echo "  - {$ing}\n";
    }
}
echo "Instructions found: " . count($result['instructions']) . "\n";
if (!empty($result['instructions'])) {
    echo "Sample instructions:\n";
    foreach (array_slice($result['instructions'], 0, 2) as $inst) {
        echo "  - {$inst}\n";
    }
}
echo "Expert name: " . ($result['expert_name'] ?: 'Not found') . "\n";
echo "Expert note: " . (strlen($result['expert_note']) > 0 ? 'Found (' . strlen($result['expert_note']) . ' chars)' : 'Not found') . "\n";
echo "\n";

// Test 2: IngredientParser
echo "Test 2: IngredientParser\n";
echo "-------------------------\n";

$ingredientParser = new IngredientParser();

$testIngredients = [
    '3 çiçek brokoli',
    '1/4 adet ince kıyılmış lahana',
    'yarım su bardağı zeytinyağı',
    '2 yemek kaşığı doğranmış maydanoz'
];

foreach ($testIngredients as $ing) {
    $parsed = $ingredientParser->parse($ing);
    echo "Input: {$ing}\n";
    echo "  Quantity: {$parsed['quantity']}\n";
    echo "  Unit: {$parsed['unit']}\n";
    echo "  Name: {$parsed['name']}\n";
    echo "  Prep note: {$parsed['preparation_note']}\n";
    echo "\n";
}

// Test 3: AgeGroupMapper
echo "Test 3: AgeGroupMapper\n";
echo "----------------------\n";

$ageMapper = new AgeGroupMapper();

$testCases = [
    ['Brokoli Çorbası 9 Ay ve Sonrası', 'Bu tarif 9 aylık bebekler için'],
    ['Karabuğdaylı Muhallebi 1 Yaş', '1 yaş ve üzeri çocuklar için'],
    ['Vegan Brownie 2+ Yaş', 'İki yaşından büyük çocuklar için'],
    ['Havuç Püresi 6 Ay', '6. aydan itibaren verilebilir']
];

foreach ($testCases as [$title, $content]) {
    $slug = $ageMapper->map($title, $content);
    echo "Title: {$title}\n";
    echo "Mapped to: " . ($slug ?: 'Not found') . "\n";
    $mentions = $ageMapper->extractAgeMentions($title . ' ' . $content);
    if ($mentions) {
        echo "Age mentions: " . implode(', ', $mentions) . "\n";
    }
    echo "\n";
}

echo "=== All Tests Completed ===\n";

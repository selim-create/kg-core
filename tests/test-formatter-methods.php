<?php
/**
 * Test Formatter Methods
 * 
 * Tests the new formatter methods in RecipeMeta and IngredientMeta models
 */

// Load WordPress
require_once dirname(__FILE__) . '/../kg-core.php';

echo "=== Testing Formatter Methods ===\n\n";

// Test RecipeMeta formatters
echo "--- RecipeMeta Formatters ---\n";

// Test formatPrepTime
$tests = [
    ['input' => 30, 'expected' => '30 dakika'],
    ['input' => 60, 'expected' => '1 saat'],
    ['input' => 90, 'expected' => '1 saat 30 dakika'],
    ['input' => 120, 'expected' => '2 saat'],
    ['input' => 150, 'expected' => '2 saat 30 dakika'],
    ['input' => null, 'expected' => null],
    ['input' => '', 'expected' => null],
];

echo "\nformatPrepTime:\n";
foreach ($tests as $test) {
    $result = \KG_Core\Models\RecipeMeta::formatPrepTime($test['input']);
    $status = $result === $test['expected'] ? '✓' : '✗';
    echo "  $status Input: " . var_export($test['input'], true) . " => Output: " . var_export($result, true) . " (Expected: " . var_export($test['expected'], true) . ")\n";
}

// Test formatCookTime (should be same as formatPrepTime)
echo "\nformatCookTime:\n";
$result = \KG_Core\Models\RecipeMeta::formatCookTime(45);
$expected = '45 dakika';
$status = $result === $expected ? '✓' : '✗';
echo "  $status Input: 45 => Output: $result (Expected: $expected)\n";

// Test formatDifficulty
echo "\nformatDifficulty:\n";
$difficulty_tests = [
    ['input' => 'kolay', 'expected' => 'Kolay'],
    ['input' => 'orta', 'expected' => 'Orta'],
    ['input' => 'zor', 'expected' => 'Zor'],
    ['input' => 'KOLAY', 'expected' => 'Kolay'],
    ['input' => null, 'expected' => null],
    ['input' => '', 'expected' => null],
];

foreach ($difficulty_tests as $test) {
    $result = \KG_Core\Models\RecipeMeta::formatDifficulty($test['input']);
    $status = $result === $test['expected'] ? '✓' : '✗';
    echo "  $status Input: " . var_export($test['input'], true) . " => Output: " . var_export($result, true) . " (Expected: " . var_export($test['expected'], true) . ")\n";
}

// Test formatNutritionValue
echo "\nformatNutritionValue:\n";
$nutrition_tests = [
    ['value' => 180, 'unit' => 'kcal', 'expected' => '180 kcal'],
    ['value' => 6, 'unit' => 'g', 'expected' => '6 g'],
    ['value' => 6.5, 'unit' => 'g', 'expected' => '6,5 g'],
    ['value' => 6.00, 'unit' => 'g', 'expected' => '6 g'],
    ['value' => null, 'unit' => 'g', 'expected' => null],
    ['value' => '', 'unit' => 'g', 'expected' => null],
];

foreach ($nutrition_tests as $test) {
    $result = \KG_Core\Models\RecipeMeta::formatNutritionValue($test['value'], $test['unit']);
    $status = $result === $test['expected'] ? '✓' : '✗';
    echo "  $status Input: {$test['value']} {$test['unit']} => Output: " . var_export($result, true) . " (Expected: {$test['expected']})\n";
}

// Test formatNutrition
echo "\nformatNutrition:\n";
$meta = [
    'calories' => 180,
    'protein' => 6,
    'carbs' => 25.5,
    'fat' => 8.0,
    'fiber' => 3.2,
    'sugar' => 5,
    'sodium' => 150,
];
$result = \KG_Core\Models\RecipeMeta::formatNutrition($meta);
echo "  Input: " . json_encode($meta) . "\n";
echo "  Output: " . json_encode($result) . "\n";
$expected_calories = '180 kcal';
$status = $result['calories'] === $expected_calories ? '✓' : '✗';
echo "  $status Calories: {$result['calories']} (Expected: $expected_calories)\n";

// Test IngredientMeta formatters
echo "\n--- IngredientMeta Formatters ---\n";

// Test formatStartAge
echo "\nformatStartAge:\n";
$age_tests = [
    ['input' => 6, 'expected' => '6 ay'],
    ['input' => 12, 'expected' => '12 ay'],
    ['input' => null, 'expected' => null],
    ['input' => '', 'expected' => null],
];

foreach ($age_tests as $test) {
    $result = \KG_Core\Models\IngredientMeta::formatStartAge($test['input']);
    $status = $result === $test['expected'] ? '✓' : '✗';
    echo "  $status Input: " . var_export($test['input'], true) . " => Output: " . var_export($result, true) . " (Expected: " . var_export($test['expected'], true) . ")\n";
}

// Test formatNutritionPer100g
echo "\nformatNutritionPer100g:\n";
$nutrition_100g_tests = [
    ['value' => 52, 'unit' => 'kcal', 'expected' => '52 kcal'],
    ['value' => 1.4, 'unit' => 'g', 'expected' => '1,4 g'],
    ['value' => 12.0, 'unit' => 'g', 'expected' => '12 g'],
    ['value' => null, 'unit' => 'g', 'expected' => null],
];

foreach ($nutrition_100g_tests as $test) {
    $result = \KG_Core\Models\IngredientMeta::formatNutritionPer100g($test['value'], $test['unit']);
    $status = $result === $test['expected'] ? '✓' : '✗';
    echo "  $status Input: {$test['value']} {$test['unit']} => Output: " . var_export($result, true) . " (Expected: {$test['expected']})\n";
}

// Test formatNutrition for ingredients
echo "\nIngredient formatNutrition:\n";
$ing_meta = [
    'calories_100g' => 52,
    'protein_100g' => 1.4,
    'carbs_100g' => 12,
    'fat_100g' => 0.3,
    'fiber_100g' => 2.1,
    'sugar_100g' => 7.5,
];
$result = \KG_Core\Models\IngredientMeta::formatNutrition($ing_meta);
echo "  Input: " . json_encode($ing_meta) . "\n";
echo "  Output: " . json_encode($result) . "\n";
$expected_calories_100g = '52 kcal';
$status = $result['calories_100g'] === $expected_calories_100g ? '✓' : '✗';
echo "  $status Calories: {$result['calories_100g']} (Expected: $expected_calories_100g)\n";

echo "\n=== All Formatter Tests Complete ===\n";

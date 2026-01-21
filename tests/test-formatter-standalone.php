<?php
/**
 * Standalone Test for Formatter Methods
 * Tests the formatter logic without WordPress dependency
 */

echo "=== Testing Formatter Methods (Standalone) ===\n\n";

// Test formatPrepTime logic
function formatPrepTime($minutes) {
    if ($minutes === null || $minutes === '') {
        return null;
    }
    $minutes = intval($minutes);
    if ($minutes >= 60) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($mins > 0) {
            return "{$hours} saat {$mins} dakika";
        }
        return "{$hours} saat";
    }
    return "{$minutes} dakika";
}

// Test formatDifficulty logic
function formatDifficulty($difficulty) {
    if (empty($difficulty)) {
        return null;
    }
    $map = [
        'kolay' => 'Kolay',
        'orta' => 'Orta',
        'zor' => 'Zor',
    ];
    return $map[strtolower($difficulty)] ?? ucfirst($difficulty);
}

// Test formatNutritionValue logic
function formatNutritionValue($value, $unit) {
    if ($value === null || $value === '') {
        return null;
    }
    $formatted = rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    return "{$formatted} {$unit}";
}

// Test formatStartAge logic
function formatStartAge($months) {
    if ($months === null || $months === '') {
        return null;
    }
    return "{$months} ay";
}

// Run tests
echo "--- formatPrepTime Tests ---\n";
$tests = [
    [30, '30 dakika'],
    [60, '1 saat'],
    [90, '1 saat 30 dakika'],
    [120, '2 saat'],
    [150, '2 saat 30 dakika'],
    [null, null],
    ['', null],
];

foreach ($tests as $test) {
    $result = formatPrepTime($test[0]);
    $status = $result === $test[1] ? '✓ PASS' : '✗ FAIL';
    echo "  $status: formatPrepTime(" . var_export($test[0], true) . ") = " . var_export($result, true) . " (expected: " . var_export($test[1], true) . ")\n";
}

echo "\n--- formatDifficulty Tests ---\n";
$difficulty_tests = [
    ['kolay', 'Kolay'],
    ['orta', 'Orta'],
    ['zor', 'Zor'],
    ['KOLAY', 'Kolay'],
    [null, null],
    ['', null],
];

foreach ($difficulty_tests as $test) {
    $result = formatDifficulty($test[0]);
    $status = $result === $test[1] ? '✓ PASS' : '✗ FAIL';
    echo "  $status: formatDifficulty(" . var_export($test[0], true) . ") = " . var_export($result, true) . " (expected: " . var_export($test[1], true) . ")\n";
}

echo "\n--- formatNutritionValue Tests ---\n";
$nutrition_tests = [
    [180, 'kcal', '180 kcal'],
    [6, 'g', '6 g'],
    [6.5, 'g', '6,5 g'],
    [6.00, 'g', '6 g'],
    [null, 'g', null],
    ['', 'g', null],
];

foreach ($nutrition_tests as $test) {
    $result = formatNutritionValue($test[0], $test[1]);
    $status = $result === $test[2] ? '✓ PASS' : '✗ FAIL';
    echo "  $status: formatNutritionValue(" . var_export($test[0], true) . ", '{$test[1]}') = " . var_export($result, true) . " (expected: " . var_export($test[2], true) . ")\n";
}

echo "\n--- formatStartAge Tests ---\n";
$age_tests = [
    [6, '6 ay'],
    [12, '12 ay'],
    [null, null],
    ['', null],
];

foreach ($age_tests as $test) {
    $result = formatStartAge($test[0]);
    $status = $result === $test[1] ? '✓ PASS' : '✗ FAIL';
    echo "  $status: formatStartAge(" . var_export($test[0], true) . ") = " . var_export($result, true) . " (expected: " . var_export($test[1], true) . ")\n";
}

echo "\n=== All Tests Complete ===\n";

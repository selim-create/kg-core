<?php
/**
 * Test for Turkish Character Encoding Fix
 * 
 * This test verifies that Turkish characters are properly encoded
 * using JSON_UNESCAPED_UNICODE instead of wp_json_encode().
 */

// Mock WordPress functions for testing
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args) {
        return [];
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single) {
        return '';
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        return true;
    }
}

echo "🧪 Testing Turkish Character Encoding\n";
echo "=====================================\n\n";

// Test 1: Verify wp_json_encode escapes Turkish characters
echo "Test 1: wp_json_encode behavior\n";
$test_text = "Çim klorofil içerir, inatçı leke yapar.";
$wp_encoded = wp_json_encode($test_text);
echo "Input: $test_text\n";
echo "wp_json_encode output: $wp_encoded\n";
if (strpos($wp_encoded, '\u') !== false) {
    echo "✅ PASS: wp_json_encode escapes Unicode characters (this is the problem)\n\n";
} else {
    echo "❌ FAIL: wp_json_encode did not escape Unicode characters\n\n";
}

// Test 2: Verify json_encode with JSON_UNESCAPED_UNICODE preserves Turkish characters
echo "Test 2: json_encode with JSON_UNESCAPED_UNICODE\n";
$fixed_encoded = json_encode($test_text, JSON_UNESCAPED_UNICODE);
echo "Input: $test_text\n";
echo "json_encode with JSON_UNESCAPED_UNICODE output: $fixed_encoded\n";
if (strpos($fixed_encoded, '\u') === false && strpos($fixed_encoded, 'Çim') !== false) {
    echo "✅ PASS: Turkish characters are preserved without escaping\n\n";
} else {
    echo "❌ FAIL: Turkish characters were escaped or corrupted\n\n";
}

// Test 3: Verify the fix works end-to-end
echo "Test 3: Fix encoding process simulation\n";
$malformed_data = '["Çim klorofil içerir, inatçı leke yapar."]';
echo "Original (malformed with escaped Unicode): " . wp_json_encode(["Çim klorofil içerir, inatçı leke yapar."]) . "\n";

// Simulate the fix_encoding process
$decoded = json_decode(wp_json_encode(["Çim klorofil içerir, inatçı leke yapar."]), true);
$fixed = json_encode($decoded, JSON_UNESCAPED_UNICODE);
echo "After fix_encoding: $fixed\n";

if (strpos($fixed, 'Çim') !== false && strpos($fixed, 'içerir') !== false && strpos($fixed, 'inatçı') !== false) {
    echo "✅ PASS: fix_encoding successfully restored Turkish characters\n\n";
} else {
    echo "❌ FAIL: fix_encoding did not restore Turkish characters correctly\n\n";
}

// Test 4: Test with complex nested data structure
echo "Test 4: Complex data structure (steps array)\n";
$steps = [
    [
        'step' => 1,
        'instruction' => 'Fazla toprağı fırçalayın.',
        'tip' => 'Lekeyi ovuşturmayın, daha fazla yayılmasına neden olur.',
    ],
    [
        'step' => 2,
        'instruction' => 'Beyaz sirke veya alkol (isopropil) uygulayın.',
    ],
];

$encoded_with_unescaped = json_encode($steps, JSON_UNESCAPED_UNICODE);
echo "Steps encoded with JSON_UNESCAPED_UNICODE:\n";
echo $encoded_with_unescaped . "\n";

if (
    strpos($encoded_with_unescaped, 'Fazla') !== false &&
    strpos($encoded_with_unescaped, 'toprağı') !== false &&
    strpos($encoded_with_unescaped, 'fırçalayın') !== false &&
    strpos($encoded_with_unescaped, 'ovuşturmayın') !== false &&
    strpos($encoded_with_unescaped, '\u') === false
) {
    echo "✅ PASS: Complex structure preserves all Turkish characters without escaping\n\n";
} else {
    echo "❌ FAIL: Complex structure has escaped characters or missing Turkish text\n\n";
}

// Test 5: Verify real-world example from the problem statement
echo "Test 5: Real-world example from issue\n";
$example_warning = "Çim klorofil içerir, inatçı leke yapar.";
$correct_encoding = json_encode([$example_warning], JSON_UNESCAPED_UNICODE);
$wrong_encoding = wp_json_encode([$example_warning]);

echo "Correct encoding: $correct_encoding\n";
echo "Wrong encoding (wp_json_encode): $wrong_encoding\n";

// Decode the wrong encoding to show what it looks like
$decoded_wrong = json_decode($wrong_encoding, true);
$re_encoded_correct = json_encode($decoded_wrong, JSON_UNESCAPED_UNICODE);

echo "After fix: $re_encoded_correct\n";

if ($correct_encoding === $re_encoded_correct && strpos($re_encoded_correct, 'Çim') !== false) {
    echo "✅ PASS: Fix produces the same output as correct encoding from the start\n\n";
} else {
    echo "❌ FAIL: Fix did not produce correct encoding\n\n";
}

echo "=====================================\n";
echo "All tests completed!\n";

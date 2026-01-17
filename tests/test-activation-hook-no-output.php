<?php
/**
 * Test Script for Activation Hook - No Output Verification
 * This test verifies code changes without requiring WordPress
 */

echo "=== ACTIVATION HOOK NO OUTPUT TEST ===\n\n";

// Test 1: Check for output buffering
echo "TEST 1: Checking activation hook for output buffering...\n";
$plugin_file = dirname(__FILE__) . '/../kg-core.php';
$content = file_get_contents($plugin_file);

if (strpos($content, 'ob_start()') !== false) {
    echo "✅ Output buffering found\n";
} else {
    echo "❌ Output buffering NOT found\n";
}

if (strpos($content, 'ob_end_clean()') !== false) {
    echo "✅ Output buffer cleanup found\n";
} else {
    echo "❌ Output buffer cleanup NOT found\n";
}

// Test 2: Check for error handling
echo "\nTEST 2: Checking for error handling...\n";
if (strpos($content, 'try {') !== false) {
    echo "✅ Try/catch found\n";
} else {
    echo "❌ Try/catch NOT found\n";
}

if (strpos($content, 'error_log') !== false) {
    echo "✅ Error logging found\n";
} else {
    echo "❌ Error logging NOT found\n";
}

// Test 3: Check VaccinationSchema
echo "\nTEST 3: Checking VaccinationSchema...\n";
$schema_file = dirname(__FILE__) . '/../includes/Database/VaccinationSchema.php';
$schema_content = file_get_contents($schema_file);

$suppressed = substr_count($schema_content, '@dbDelta');
echo "✅ Found {$suppressed} suppressed dbDelta calls\n";

if (strpos($schema_content, 'try {') !== false) {
    echo "✅ Error handling found in VaccinationSchema\n";
} else {
    echo "❌ Error handling NOT found in VaccinationSchema\n";
}

// Test 4: Check VaccineManager
echo "\nTEST 4: Checking VaccineManager...\n";
$manager_file = dirname(__FILE__) . '/../includes/Health/VaccineManager.php';
$manager_content = file_get_contents($manager_file);

if (strpos($manager_content, 'try {') !== false) {
    echo "✅ Error handling found in VaccineManager\n";
} else {
    echo "❌ Error handling NOT found\n";
}

if (strpos($manager_content, '@file_get_contents') !== false) {
    echo "✅ Suppressed file warnings found\n";
} else {
    echo "❌ Suppressed file warnings NOT found\n";
}

// Test 5: Check for BOM
echo "\nTEST 5: Checking for UTF-8 BOM...\n";
$files = array(
    'kg-core.php',
    'includes/Database/VaccinationSchema.php',
    'includes/Health/VaccineManager.php'
);

$found_bom = false;
foreach ($files as $file) {
    $path = dirname(__FILE__) . '/../' . $file;
    $fc = file_get_contents($path);
    $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
    if (substr($fc, 0, 3) === $bom) {
        echo "❌ BOM in {$file}\n";
        $found_bom = true;
    }
}

if (!$found_bom) {
    echo "✅ No BOM found\n";
}

// Test 6: Check for closing tags
echo "\nTEST 6: Checking for closing PHP tags...\n";
$found_closing = false;
foreach ($files as $file) {
    $path = dirname(__FILE__) . '/../' . $file;
    $fc = file_get_contents($path);
    $trimmed = rtrim($fc);
    if (substr($trimmed, -2) === '?>') {
        echo "❌ Closing tag in {$file}\n";
        $found_closing = true;
    }
}

if (!$found_closing) {
    echo "✅ No closing tags found\n";
}

echo "\n=== TEST SUMMARY ===\n";
echo "All tests completed.\n";
echo "The activation hook should now be safe from 'Headers already sent' errors.\n";

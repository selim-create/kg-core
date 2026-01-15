<?php
/**
 * Test for Ingredient Note Field Implementation
 * 
 * Verifies that the note field has been added to the ingredient repeater
 * in the Recipe MetaBox
 */

echo "=== KG Core Ingredient Note Field Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check RecipeMetaBox for note field in render_ingredient_item
echo "1. RecipeMetaBox - render_ingredient_item() Method\n";
$metaBoxFile = $baseDir . '/includes/Admin/RecipeMetaBox.php';

if (file_exists($metaBoxFile)) {
    echo "   ✓ File exists: RecipeMetaBox.php\n";
    $passed++;
    
    $content = file_get_contents($metaBoxFile);
    
    // Check for note variable extraction
    if (preg_match('/\$note\s*=\s*isset\(\s*\$ingredient\[\'note\'\]\s*\)/', $content)) {
        echo "   ✓ Note variable extraction found\n";
        $passed++;
    } else {
        echo "   ✗ Note variable extraction not found\n";
        $failed++;
    }
    
    // Check for note field HTML input
    if (strpos($content, 'kg-ingredient-note-row') !== false) {
        echo "   ✓ Note field HTML wrapper found\n";
        $passed++;
    } else {
        echo "   ✗ Note field HTML wrapper not found\n";
        $failed++;
    }
    
    // Check for note input field
    if (preg_match('/name="kg_ingredients\[.*?\]\[note\]"/', $content)) {
        echo "   ✓ Note input field name attribute found\n";
        $passed++;
    } else {
        echo "   ✗ Note input field name attribute not found\n";
        $failed++;
    }
    
    // Check for note label with Turkish text
    if (strpos($content, 'Not <small>(opsiyonel - kullanıcıya gösterilecek ipucu)</small>') !== false) {
        echo "   ✓ Note field label found with Turkish text\n";
        $passed++;
    } else {
        echo "   ✗ Note field label not found\n";
        $failed++;
    }
    
    // Check for note placeholder
    if (strpos($content, 'placeholder="Örn: Oda sıcaklığında olmalı') !== false) {
        echo "   ✓ Note field placeholder found\n";
        $passed++;
    } else {
        echo "   ✗ Note field placeholder not found\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeMetaBox.php\n";
    $failed++;
}
echo "\n";

// Test 2: Check RecipeMetaBox save_custom_meta_data method
echo "2. RecipeMetaBox - save_custom_meta_data() Method\n";

if (file_exists($metaBoxFile)) {
    $content = file_get_contents($metaBoxFile);
    
    // Check for note field in save logic
    if (preg_match('/\'note\'\s*=>\s*isset\(\s*\$ingredient\[\'note\'\]\s*\)/', $content)) {
        echo "   ✓ Note field save logic found\n";
        $passed++;
    } else {
        echo "   ✗ Note field save logic not found\n";
        $failed++;
    }
    
    // Check for sanitization
    if (strpos($content, "sanitize_text_field( \$ingredient['note'] )") !== false) {
        echo "   ✓ Note field sanitization found\n";
        $passed++;
    } else {
        echo "   ✗ Note field sanitization not found\n";
        $failed++;
    }
    
    // Check for empty string default
    if (preg_match('/\'note\'\s*=>\s*isset.*?\?\s*sanitize_text_field.*?:\s*\'\'/', $content)) {
        echo "   ✓ Note field default empty string found\n";
        $passed++;
    } else {
        echo "   ✗ Note field default empty string not found\n";
        $failed++;
    }
}
echo "\n";

// Test 3: Check RecipeController (should automatically include note)
echo "3. RecipeController - API Data Preparation\n";
$controllerFile = $baseDir . '/includes/API/RecipeController.php';

if (file_exists($controllerFile)) {
    echo "   ✓ File exists: RecipeController.php\n";
    $passed++;
    
    $content = file_get_contents($controllerFile);
    
    // Check that ingredients are retrieved from meta
    if (preg_match('/get_post_meta.*?_kg_ingredients/', $content)) {
        echo "   ✓ Ingredients meta retrieval found (note will be auto-included)\n";
        $passed++;
    } else {
        echo "   ✗ Ingredients meta retrieval not found\n";
        $failed++;
    }
    
    // Check for maybe_unserialize
    if (strpos($content, 'maybe_unserialize($ingredients_raw)') !== false) {
        echo "   ✓ Ingredient data unserialization found\n";
        $passed++;
    } else {
        echo "   ✗ Ingredient data unserialization not found\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Summary
echo "=== TEST RESULTS ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed!\n";
    exit(1);
}

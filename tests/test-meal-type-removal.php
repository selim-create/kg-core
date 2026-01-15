<?php
/**
 * Static Code Analysis Test for Meal Type Field Removal
 * 
 * This test verifies that:
 * 1. Meal type select box is removed from RecipeMetaBox
 * 2. Meal type meta field variable is removed
 * 3. Meal type save logic is removed
 * 4. API fields are correctly added (special_notes, expert.note, expert.image, author.slug)
 */

echo "=== KG Core Meal Type Field Removal Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check RecipeMetaBox for removed meal type select box
echo "1. RecipeMetaBox - Meal Type Select Box Removal\n";
$metaboxFile = $baseDir . '/includes/Admin/RecipeMetaBox.php';
if (file_exists($metaboxFile)) {
    $content = file_get_contents($metaboxFile);
    
    // Check that meal type select box is NOT present
    if (strpos($content, '<select id="kg_meal_type"') === false) {
        echo "   ✓ Meal type select box successfully removed\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: Meal type select box still exists\n";
        $failed++;
    }
    
    // Check that meal type variable declaration is NOT present
    if (strpos($content, '$meal_type = get_post_meta( $post->ID, \'_kg_meal_type\'') === false) {
        echo "   ✓ Meal type variable declaration successfully removed\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: Meal type variable declaration still exists\n";
        $failed++;
    }
    
    // Check that meal type save logic is NOT present
    if (strpos($content, 'kg_meal_type') === false || strpos($content, "isset( \$_POST['kg_meal_type']") === false) {
        echo "   ✓ Meal type save logic successfully removed\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: Meal type save logic still exists\n";
        $failed++;
    }
} else {
    echo "   ✗ FAILED: RecipeMetaBox.php not found\n";
    $failed += 3;
}

// Test 2: Check RecipeController for new API fields
echo "\n2. RecipeController - API Field Additions\n";
$controllerFile = $baseDir . '/includes/API/RecipeController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for special_notes field
    if (strpos($content, '$data[\'special_notes\'] = get_post_meta( $post_id, \'_kg_special_notes\'') !== false) {
        echo "   ✓ special_notes field added to API\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: special_notes field not found in API\n";
        $failed++;
    }
    
    // Check for expert.note field
    if (strpos($content, '\'note\' => $expert_note') !== false) {
        echo "   ✓ expert.note field added to API\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: expert.note field not found in API\n";
        $failed++;
    }
    
    // Check for expert.image field
    if (strpos($content, '\'image\' => $expert_image') !== false) {
        echo "   ✓ expert.image field added to API\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: expert.image field not found in API\n";
        $failed++;
    }
    
    // Check for author.slug field
    if (strpos($content, '\'slug\' => $author->user_nicename') !== false) {
        echo "   ✓ author.slug field added to API\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: author.slug field not found in API\n";
        $failed++;
    }
    
    // Check for expert user lookup logic
    if (strpos($content, 'get_user_by( \'login\', sanitize_title( $expert_name )') !== false) {
        echo "   ✓ Expert user lookup logic added\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: Expert user lookup logic not found\n";
        $failed++;
    }
} else {
    echo "   ✗ FAILED: RecipeController.php not found\n";
    $failed += 5;
}

// Test 3: Verify meal-type taxonomy still exists
echo "\n3. MealType Taxonomy - Verify Still Exists\n";
$taxonomyFile = $baseDir . '/includes/Taxonomies/MealType.php';
if (file_exists($taxonomyFile)) {
    $content = file_get_contents($taxonomyFile);
    
    // Check that taxonomy registration exists
    if (strpos($content, 'register_taxonomy( \'meal-type\'') !== false) {
        echo "   ✓ Meal type taxonomy registration still exists\n";
        $passed++;
    } else {
        echo "   ✗ FAILED: Meal type taxonomy registration not found\n";
        $failed++;
    }
} else {
    echo "   ✗ FAILED: MealType.php not found\n";
    $failed++;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed!\n";
    exit(1);
}

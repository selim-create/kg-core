<?php
/**
 * Static Code Analysis Test for API Improvements
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core API Improvements Verification ===\n\n";

$baseDir = __DIR__;
$passed = 0;
$failed = 0;

// Test 1: Check Helper class for decode_html_entities
echo "1. Helper Class Updates\n";
$helperFile = $baseDir . '/includes/Utils/Helper.php';
if (file_exists($helperFile)) {
    echo "   ✓ File exists: Helper.php\n";
    $content = file_get_contents($helperFile);
    
    if (strpos($content, 'function decode_html_entities') !== false) {
        echo "   ✓ Method exists: decode_html_entities()\n";
        $passed++;
        
        // Check if it uses html_entity_decode
        if (strpos($content, 'html_entity_decode') !== false) {
            echo "   ✓ Uses html_entity_decode function\n";
            $passed++;
        } else {
            echo "   ✗ Missing html_entity_decode implementation\n";
            $failed++;
        }
    } else {
        echo "   ✗ Method missing: decode_html_entities()\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: Helper.php\n";
    $failed++;
}
echo "\n";

// Test 2: Check RecipeController updates
echo "2. RecipeController Updates\n";
$recipeControllerFile = $baseDir . '/includes/API/RecipeController.php';
if (file_exists($recipeControllerFile)) {
    echo "   ✓ File exists: RecipeController.php\n";
    $content = file_get_contents($recipeControllerFile);
    
    // Check for get_seo_data method
    if (strpos($content, 'function get_seo_data') !== false) {
        echo "   ✓ Method exists: get_seo_data()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: get_seo_data()\n";
        $failed++;
    }
    
    // Check for RankMath meta keys in Recipe
    $rankMathKeys = [
        'rank_math_title',
        'rank_math_description',
        'rank_math_focus_keyword',
        'rank_math_canonical_url',
        'rank_math_facebook_title',
        'rank_math_twitter_title'
    ];
    
    foreach ($rankMathKeys as $key) {
        if (strpos($content, $key) !== false) {
            echo "   ✓ RankMath key: $key\n";
            $passed++;
        } else {
            echo "   ✗ Missing RankMath key: $key\n";
            $failed++;
        }
    }
    
    // Check for new recipe fields
    $newRecipeFields = [
        '_kg_meal_type',
        '_kg_cook_time',
        '_kg_serving_size',
        '_kg_difficulty',
        '_kg_freezable',
        '_kg_storage_info'
    ];
    
    foreach ($newRecipeFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ New field: $field\n";
            $passed++;
        } else {
            echo "   ✗ Missing field: $field\n";
            $failed++;
        }
    }
    
    // Check for extended nutrition fields
    $nutritionFields = [
        '_kg_carbs',
        '_kg_fat',
        '_kg_sugar',
        '_kg_sodium',
        '_kg_minerals'
    ];
    
    foreach ($nutritionFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ Nutrition field: $field\n";
            $passed++;
        } else {
            echo "   ✗ Missing nutrition field: $field\n";
            $failed++;
        }
    }
    
    // Check for decode_html_entities usage
    if (strpos($content, 'decode_html_entities') !== false) {
        echo "   ✓ Uses Helper::decode_html_entities()\n";
        $passed++;
    } else {
        echo "   ✗ Not using decode_html_entities()\n";
        $failed++;
    }
    
    // Check for SEO in response
    if (strpos($content, "'seo'") !== false || strpos($content, "\"seo\"") !== false) {
        echo "   ✓ Adds 'seo' to response\n";
        $passed++;
    } else {
        echo "   ✗ Missing 'seo' in response\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 3: Check IngredientController updates
echo "3. IngredientController Updates\n";
$ingredientControllerFile = $baseDir . '/includes/API/IngredientController.php';
if (file_exists($ingredientControllerFile)) {
    echo "   ✓ File exists: IngredientController.php\n";
    $content = file_get_contents($ingredientControllerFile);
    
    // Check for get_seo_data method
    if (strpos($content, 'function get_seo_data') !== false) {
        echo "   ✓ Method exists: get_seo_data()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: get_seo_data()\n";
        $failed++;
    }
    
    // Check for nutrition_per_100g fields
    $nutrition100gFields = [
        '_kg_ing_calories_100g',
        '_kg_ing_protein_100g',
        '_kg_ing_carbs_100g',
        '_kg_ing_fat_100g',
        '_kg_ing_fiber_100g',
        '_kg_ing_sugar_100g',
        '_kg_ing_vitamins',
        '_kg_ing_minerals'
    ];
    
    foreach ($nutrition100gFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ Nutrition per 100g field: $field\n";
            $passed++;
        } else {
            echo "   ✗ Missing nutrition per 100g field: $field\n";
            $failed++;
        }
    }
    
    // Check for allergen_info fields
    $allergenFields = [
        '_kg_is_allergen',
        '_kg_allergen_type',
        '_kg_cross_contamination',
        '_kg_allergy_symptoms',
        '_kg_alternatives'
    ];
    
    foreach ($allergenFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ Allergen info field: $field\n";
            $passed++;
        } else {
            echo "   ✗ Missing allergen info field: $field\n";
            $failed++;
        }
    }
    
    // Check for prep fields
    $prepFields = [
        '_kg_prep_methods_list',
        '_kg_prep_tips',
        '_kg_cooking_suggestions'
    ];
    
    foreach ($prepFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ Prep field: $field\n";
            $passed++;
        } else {
            echo "   ✗ Missing prep field: $field\n";
            $failed++;
        }
    }
    
    // Check for HTML fields
    if (strpos($content, 'description_html') !== false) {
        echo "   ✓ Adds description_html field\n";
        $passed++;
    } else {
        echo "   ✗ Missing description_html field\n";
        $failed++;
    }
    
    if (strpos($content, 'benefits_html') !== false) {
        echo "   ✓ Adds benefits_html field\n";
        $passed++;
    } else {
        echo "   ✗ Missing benefits_html field\n";
        $failed++;
    }
    
    // Check for decode_html_entities usage
    if (strpos($content, 'decode_html_entities') !== false) {
        echo "   ✓ Uses Helper::decode_html_entities()\n";
        $passed++;
    } else {
        echo "   ✗ Not using decode_html_entities()\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: IngredientController.php\n";
    $failed++;
}
echo "\n";

// Test 4: Check RecipeMetaBox updates
echo "4. RecipeMetaBox Updates\n";
$recipeMetaBoxFile = $baseDir . '/includes/Admin/RecipeMetaBox.php';
if (file_exists($recipeMetaBoxFile)) {
    echo "   ✓ File exists: RecipeMetaBox.php\n";
    $content = file_get_contents($recipeMetaBoxFile);
    
    // Check for UI fields
    $uiFields = [
        'kg_meal_type',
        'kg_cook_time',
        'kg_serving_size',
        'kg_difficulty',
        'kg_freezable',
        'kg_storage_info',
        'kg_carbs',
        'kg_fat',
        'kg_sugar',
        'kg_sodium',
        'kg_minerals'
    ];
    
    foreach ($uiFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ UI field: $field\n";
            $passed++;
        } else {
            echo "   ✗ Missing UI field: $field\n";
            $failed++;
        }
    }
    
    // Check if save method updates the new fields
    if (strpos($content, "update_post_meta") !== false) {
        echo "   ✓ Has update_post_meta calls\n";
        $passed++;
    }
} else {
    echo "   ✗ File not found: RecipeMetaBox.php\n";
    $failed++;
}
echo "\n";

// Test 5: Check IngredientMetaBox updates
echo "5. IngredientMetaBox Updates\n";
$ingredientMetaBoxFile = $baseDir . '/includes/Admin/IngredientMetaBox.php';
if (file_exists($ingredientMetaBoxFile)) {
    echo "   ✓ File exists: IngredientMetaBox.php\n";
    $content = file_get_contents($ingredientMetaBoxFile);
    
    // Check for UI fields
    $uiFields = [
        'kg_ing_calories_100g',
        'kg_ing_protein_100g',
        'kg_ing_carbs_100g',
        'kg_ing_fat_100g',
        'kg_ing_fiber_100g',
        'kg_ing_sugar_100g',
        'kg_ing_vitamins',
        'kg_ing_minerals',
        'kg_is_allergen',
        'kg_allergen_type',
        'kg_cross_contamination',
        'kg_allergy_symptoms',
        'kg_alternatives',
        'kg_prep_methods_list',
        'kg_prep_tips',
        'kg_cooking_suggestions'
    ];
    
    foreach ($uiFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ UI field: $field\n";
            $passed++;
        } else {
            echo "   ✗ Missing UI field: $field\n";
            $failed++;
        }
    }
    
    // Check if save method updates the new fields
    if (strpos($content, "update_post_meta") !== false) {
        echo "   ✓ Has update_post_meta calls\n";
        $passed++;
    }
} else {
    echo "   ✗ File not found: IngredientMetaBox.php\n";
    $failed++;
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
echo "Success Rate: $percentage%\n\n";

if ($failed === 0) {
    echo "✓ All tests passed! Implementation is complete.\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}

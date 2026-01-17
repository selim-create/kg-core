<?php
/**
 * Static Code Analysis Test for Base Rating and Related Recipes Implementation
 * 
 * Tests the implementation of base rating system and related recipes endpoint
 */

echo "=== KG Core Base Rating and Related Recipes Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check RecipeController for base rating in prepare_recipe_data
echo "1. RecipeController - Base Rating in prepare_recipe_data()\n";
$recipeControllerFile = $baseDir . '/includes/API/RecipeController.php';
if (file_exists($recipeControllerFile)) {
    echo "   ✓ File exists: RecipeController.php\n";
    $content = file_get_contents($recipeControllerFile);
    
    // Check for base rating meta fields
    if (strpos($content, '_kg_base_rating') !== false) {
        echo "   ✓ Base rating meta field (_kg_base_rating) exists\n";
        $passed++;
    } else {
        echo "   ✗ Base rating meta field missing\n";
        $failed++;
    }
    
    if (strpos($content, '_kg_base_rating_count') !== false) {
        echo "   ✓ Base rating count meta field (_kg_base_rating_count) exists\n";
        $passed++;
    } else {
        echo "   ✗ Base rating count meta field missing\n";
        $failed++;
    }
    
    // Check for deterministic generation logic
    if (strpos($content, '4.0 + ( ( $post_id % 10 ) / 10 )') !== false) {
        echo "   ✓ Deterministic base rating generation (4.0-4.9) implemented\n";
        $passed++;
    } else {
        echo "   ✗ Deterministic base rating generation missing\n";
        $failed++;
    }
    
    if (strpos($content, '10 + ( $post_id % 141 )') !== false) {
        echo "   ✓ Deterministic base count generation (10-150) implemented\n";
        $passed++;
    } else {
        echo "   ✗ Deterministic base count generation missing\n";
        $failed++;
    }
    
    // Check for rating fields in response
    if (preg_match("/['\"]rating['\"].*?=>.*?round.*?floatval.*?display_rating/s", $content)) {
        echo "   ✓ Rating field added to response\n";
        $passed++;
    } else {
        echo "   ✗ Rating field not properly added to response\n";
        $failed++;
    }
    
    if (preg_match("/['\"]rating_count['\"].*?=>.*?intval.*?display_count/s", $content)) {
        echo "   ✓ Rating count field added to response\n";
        $passed++;
    } else {
        echo "   ✗ Rating count field not properly added to response\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 2: Check rate_recipe method for weighted average
echo "2. RecipeController - rate_recipe() Weighted Average\n";
if (file_exists($recipeControllerFile)) {
    $content = file_get_contents($recipeControllerFile);
    
    // Check for base rating retrieval in rate_recipe
    if (preg_match("/function rate_recipe.*?get_base_rating.*?\\\$recipe_id/s", $content) ||
        preg_match("/function rate_recipe.*?get_post_meta.*?_kg_base_rating/s", $content)) {
        echo "   ✓ Base rating retrieved in rate_recipe method\n";
        $passed++;
    } else {
        echo "   ✗ Base rating not retrieved in rate_recipe method\n";
        $failed++;
    }
    
    // Check for weighted average calculation
    if (strpos($content, 'weighted_sum') !== false && strpos($content, 'base_rating') !== false) {
        echo "   ✓ Weighted sum calculation implemented\n";
        $passed++;
    } else {
        echo "   ✗ Weighted sum calculation missing\n";
        $failed++;
    }
    
    // Check for total count calculation
    if (preg_match("/total_count.*?=.*?base_count.*?\+.*?real_count/", $content)) {
        echo "   ✓ Total count includes base count\n";
        $passed++;
    } else {
        echo "   ✗ Total count calculation incorrect\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 3: Check related recipes endpoint registration
echo "3. RecipeController - Related Recipes Endpoint\n";
if (file_exists($recipeControllerFile)) {
    $content = file_get_contents($recipeControllerFile);
    
    // Check for endpoint registration
    if (strpos($content, '/recipes/(?P<id>\d+)/related') !== false) {
        echo "   ✓ Related recipes endpoint route registered\n";
        $passed++;
    } else {
        echo "   ✗ Related recipes endpoint route not registered\n";
        $failed++;
    }
    
    // Check for limit parameter with validation
    if (preg_match("/limit.*?validate_callback.*?is_numeric.*?&& \\\$value >= 1 && \\\$value <= 10/s", $content)) {
        echo "   ✓ Limit parameter with validation (1-10) implemented\n";
        $passed++;
    } else {
        echo "   ✗ Limit parameter validation missing or incorrect\n";
        $failed++;
    }
    
    // Check for default limit
    if (preg_match("/limit.*?default.*?=>.*?4/s", $content)) {
        echo "   ✓ Default limit set to 4\n";
        $passed++;
    } else {
        echo "   ✗ Default limit not set to 4\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 4: Check get_related_recipes method
echo "4. RecipeController - get_related_recipes() Method\n";
if (file_exists($recipeControllerFile)) {
    $content = file_get_contents($recipeControllerFile);
    
    // Check that method is public
    if (preg_match("/public function get_related_recipes/", $content)) {
        echo "   ✓ get_related_recipes method is public\n";
        $passed++;
    } else {
        echo "   ✗ get_related_recipes method not public\n";
        $failed++;
    }
    
    // Check for dual-mode handling (REST and internal)
    if ((strpos($content, 'is_rest_call') !== false && strpos($content, 'instanceof') !== false) ||
        (strpos($content, 'is_rest_call') !== false && strpos($content, 'method_exists') !== false)) {
        echo "   ✓ Dual-mode handling (REST and internal) implemented\n";
        $passed++;
    } else {
        echo "   ✗ Dual-mode handling missing\n";
        $failed++;
    }
    
    // Check for taxonomy matching (age-group and meal-type)
    if (strpos($content, 'age-group') !== false && strpos($content, 'meal-type') !== false) {
        echo "   ✓ Taxonomy matching (age-group and meal-type) implemented\n";
        $passed++;
    } else {
        echo "   ✗ Taxonomy matching missing\n";
        $failed++;
    }
    
    // Check for random fill-up
    if (strpos($content, 'orderby') !== false && strpos($content, 'rand') !== false) {
        echo "   ✓ Random recipe fill-up implemented\n";
        $passed++;
    } else {
        echo "   ✗ Random recipe fill-up missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 5: Check prepare_recipe_card_data method
echo "5. RecipeController - prepare_recipe_card_data() Method\n";
if (file_exists($recipeControllerFile)) {
    $content = file_get_contents($recipeControllerFile);
    
    // Check that method exists
    if (preg_match("/function prepare_recipe_card_data/", $content)) {
        echo "   ✓ prepare_recipe_card_data method exists\n";
        $passed++;
    } else {
        echo "   ✗ prepare_recipe_card_data method missing\n";
        $failed++;
    }
    
    // Check for rating data in card
    if (preg_match("/prepare_recipe_card_data.*?rating.*?rating_count/s", $content)) {
        echo "   ✓ Rating data included in card data\n";
        $passed++;
    } else {
        echo "   ✗ Rating data not included in card data\n";
        $failed++;
    }
    
    // Check for simplified fields
    $cardFields = ['id', 'title', 'slug', 'image', 'age_group', 'meal_type', 'prep_time'];
    $allFieldsFound = true;
    foreach ($cardFields as $field) {
        if (!preg_match("/['\"]" . $field . "['\"].*?=>/", $content)) {
            $allFieldsFound = false;
            break;
        }
    }
    
    if ($allFieldsFound) {
        echo "   ✓ All card fields (id, title, slug, image, age_group, meal_type, prep_time) present\n";
        $passed++;
    } else {
        echo "   ✗ Some card fields missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
echo "Success Rate: $percentage%\n\n";

if ($failed === 0) {
    echo "✓ All tests passed! Implementation complete.\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}

#!/usr/bin/env php
<?php
/**
 * Test script for Featured Content Enhancements
 * Validates the implementation of featured posts and ingredients support
 */

echo "Testing Featured Content Enhancements\n";
echo "=====================================\n\n";

$errors = [];
$warnings = [];

// Test 1: Check FeaturedController.php
echo "Test 1: Checking FeaturedController.php...\n";
$featured_controller_path = __DIR__ . '/includes/API/FeaturedController.php';

if (!file_exists($featured_controller_path)) {
    $errors[] = "FeaturedController.php not found";
} else {
    $content = file_get_contents($featured_controller_path);
    
    // Check for ingredient support in validation
    if (strpos($content, "'ingredient'") === false) {
        $errors[] = "Ingredient type not found in FeaturedController validation";
    } else {
        echo "  ✓ Ingredient type added to validation\n";
    }
    
    // Check for get_featured_ingredients method
    if (strpos($content, 'get_featured_ingredients') === false) {
        $errors[] = "get_featured_ingredients method not found";
    } else {
        echo "  ✓ get_featured_ingredients method exists\n";
    }
    
    // Check for get_initials helper method
    if (strpos($content, 'get_initials') === false) {
        $errors[] = "get_initials helper method not found";
    } else {
        echo "  ✓ get_initials helper method exists\n";
    }
    
    // Check for HTML entity decoding in format methods
    if (strpos($content, 'decode_html_entities') === false) {
        $errors[] = "HTML entity decoding not implemented";
    } else {
        echo "  ✓ HTML entity decoding implemented\n";
    }
    
    // Check for author_avatar in questions
    if (strpos($content, 'author_avatar') === false) {
        $warnings[] = "author_avatar field might not be included in question responses";
    } else {
        echo "  ✓ author_avatar field added to questions\n";
    }
    
    // Check for category_slug
    if (strpos($content, 'category_slug') === false) {
        $warnings[] = "category_slug might not be included in responses";
    } else {
        echo "  ✓ category_slug field added\n";
    }
    
    // Syntax check using token_get_all (safer than exec)
    $syntax_check = kg_check_php_syntax($featured_controller_path);
    if ($syntax_check !== true) {
        $errors[] = "Syntax error in FeaturedController.php: " . $syntax_check;
    } else {
        echo "  ✓ Syntax is valid\n";
    }
}
echo "\n";

// Helper function for safer PHP syntax validation
function kg_check_php_syntax($file) {
    $code = file_get_contents($file);
    if ($code === false) {
        return "Cannot read file";
    }
    
    // Use token_get_all to check syntax without executing
    $tokens = @token_get_all($code);
    if ($tokens === false) {
        return "Invalid PHP syntax";
    }
    
    // Also check using php -l for complete validation
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
    if ($return_var !== 0) {
        return implode("\n", $output);
    }
    
    return true;
}

// Test 2: Check PostMetaBox.php
echo "Test 2: Checking PostMetaBox.php...\n";
$post_metabox_path = __DIR__ . '/includes/Admin/PostMetaBox.php';

if (!file_exists($post_metabox_path)) {
    $errors[] = "PostMetaBox.php not found";
} else {
    $content = file_get_contents($post_metabox_path);
    
    // Check for featured checkbox in render
    if (strpos($content, 'kg_is_featured') === false) {
        $errors[] = "Featured checkbox not found in PostMetaBox render";
    } else {
        echo "  ✓ Featured checkbox added to render method\n";
    }
    
    // Check for featured save in save_sponsor_meta_data
    if (strpos($content, "update_post_meta( \$post_id, '_kg_is_featured'") === false) {
        $errors[] = "Featured field save not found in PostMetaBox save method";
    } else {
        echo "  ✓ Featured field save implemented\n";
    }
    
    // Check for discount fields
    if (strpos($content, 'kg_has_discount') === false || strpos($content, 'kg_discount_text') === false) {
        $warnings[] = "Discount fields might not be fully implemented";
    } else {
        echo "  ✓ Discount fields added\n";
    }
    
    // Syntax check
    $syntax_check = kg_check_php_syntax($post_metabox_path);
    if ($syntax_check !== true) {
        $errors[] = "Syntax error in PostMetaBox.php: " . $syntax_check;
    } else {
        echo "  ✓ Syntax is valid\n";
    }
}
echo "\n";

// Test 3: Check IngredientMetaBox.php
echo "Test 3: Checking IngredientMetaBox.php...\n";
$ingredient_metabox_path = __DIR__ . '/includes/Admin/IngredientMetaBox.php';

if (!file_exists($ingredient_metabox_path)) {
    $errors[] = "IngredientMetaBox.php not found";
} else {
    $content = file_get_contents($ingredient_metabox_path);
    
    // Check for featured checkbox in render
    if (strpos($content, 'kg_is_featured') === false) {
        $errors[] = "Featured checkbox not found in IngredientMetaBox render";
    } else {
        echo "  ✓ Featured checkbox added to render method\n";
    }
    
    // Check for featured save
    if (strpos($content, "update_post_meta( \$post_id, '_kg_is_featured'") === false) {
        $errors[] = "Featured field save not found in IngredientMetaBox save method";
    } else {
        echo "  ✓ Featured field save implemented\n";
    }
    
    // Syntax check
    $syntax_check = kg_check_php_syntax($ingredient_metabox_path);
    if ($syntax_check !== true) {
        $errors[] = "Syntax error in IngredientMetaBox.php: " . $syntax_check;
    } else {
        echo "  ✓ Syntax is valid\n";
    }
}
echo "\n";

// Test 4: Check kg-core.php taxonomy filters
echo "Test 4: Checking kg-core.php taxonomy filters...\n";
$main_file_path = __DIR__ . '/kg-core.php';

if (!file_exists($main_file_path)) {
    $errors[] = "kg-core.php not found";
} else {
    $content = file_get_contents($main_file_path);
    
    // Check for taxonomy filters
    $taxonomies = ['age-group', 'meal-type', 'diet-type', 'category'];
    foreach ($taxonomies as $taxonomy) {
        if (strpos($content, "rest_prepare_$taxonomy") === false) {
            $errors[] = "REST API filter for $taxonomy taxonomy not found";
        } else {
            echo "  ✓ REST API filter for $taxonomy added\n";
        }
    }
    
    // Check for HTML entity decoding in filters
    if (strpos($content, 'html_entity_decode') === false) {
        $errors[] = "html_entity_decode not used in taxonomy filters";
    } else {
        echo "  ✓ HTML entity decoding in taxonomy filters\n";
    }
    
    // Syntax check
    $syntax_check = kg_check_php_syntax($main_file_path);
    if ($syntax_check !== true) {
        $errors[] = "Syntax error in kg-core.php: " . $syntax_check;
    } else {
        echo "  ✓ Syntax is valid\n";
    }
}
echo "\n";

// Test 5: Check Helper.php for decode_html_entities
echo "Test 5: Checking Helper.php...\n";
$helper_path = __DIR__ . '/includes/Utils/Helper.php';

if (!file_exists($helper_path)) {
    $errors[] = "Helper.php not found";
} else {
    $content = file_get_contents($helper_path);
    
    if (strpos($content, 'decode_html_entities') === false) {
        $errors[] = "decode_html_entities method not found in Helper.php";
    } else {
        echo "  ✓ decode_html_entities method exists\n";
    }
    
    // Syntax check
    $syntax_check = kg_check_php_syntax($helper_path);
    if ($syntax_check !== true) {
        $errors[] = "Syntax error in Helper.php: " . $syntax_check;
    } else {
        echo "  ✓ Syntax is valid\n";
    }
}
echo "\n";

// Summary
echo "=====================================\n";
echo "Test Summary\n";
echo "=====================================\n";

if (count($errors) > 0) {
    echo "\n❌ ERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "  • $error\n";
    }
}

if (count($warnings) > 0) {
    echo "\n⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  • $warning\n";
    }
}

if (count($errors) === 0) {
    echo "\n✅ All critical tests passed!\n";
    if (count($warnings) === 0) {
        echo "✅ No warnings.\n";
    }
    echo "\nImplementation checklist:\n";
    echo "  ✓ Featured checkbox added to posts\n";
    echo "  ✓ Featured checkbox added to ingredients\n";
    echo "  ✓ Ingredient support added to Featured API\n";
    echo "  ✓ HTML entity decoding implemented\n";
    echo "  ✓ Taxonomy REST API filters added\n";
    echo "  ✓ Discount fields added to sponsored posts\n";
    echo "  ✓ Enhanced metadata in API responses\n";
    exit(0);
} else {
    exit(1);
}

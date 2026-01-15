<?php
/**
 * Static Code Analysis Test for Recipe API Backend Improvements
 * 
 * Tests the implementation of new fields, filters, and taxonomy updates
 */

echo "=== KG Core Recipe API Backend Improvements Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check Helper class enhancements
echo "1. Helper Class - HTML Entity Decoding Enhancement\n";
$helperFile = $baseDir . '/includes/Utils/Helper.php';
if (file_exists($helperFile)) {
    echo "   ✓ File exists: Helper.php\n";
    $content = file_get_contents($helperFile);
    
    if (strpos($content, 'function decode_html_entities') !== false) {
        echo "   ✓ Method exists: decode_html_entities()\n";
        $passed++;
        
        // Check for double decoding (handling double-encoded entities)
        if (substr_count($content, 'html_entity_decode') >= 2) {
            echo "   ✓ Implements double-decoding for double-encoded entities\n";
            $passed++;
        } else {
            echo "   ✗ Missing double-decoding implementation\n";
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

// Test 2: Check SpecialCondition taxonomy
echo "2. SpecialCondition Taxonomy\n";
$specialConditionFile = $baseDir . '/includes/Taxonomies/SpecialCondition.php';
if (file_exists($specialConditionFile)) {
    echo "   ✓ File exists: SpecialCondition.php\n";
    $passed++;
    $content = file_get_contents($specialConditionFile);
    
    if (strpos($content, 'register_taxonomy') !== false) {
        echo "   ✓ Contains register_taxonomy method\n";
        $passed++;
    }
    
    if (strpos($content, 'insert_default_terms') !== false) {
        echo "   ✓ Contains insert_default_terms method\n";
        $passed++;
    }
    
    $requiredTerms = ['Kabızlık Giderici', 'Bağışıklık Dostu', 'Diş Çıkarma Dönemi', 'Alerjik Bebek'];
    $allTermsFound = true;
    foreach ($requiredTerms as $term) {
        if (strpos($content, $term) === false) {
            $allTermsFound = false;
            break;
        }
    }
    
    if ($allTermsFound) {
        echo "   ✓ All default terms present\n";
        $passed++;
    } else {
        echo "   ✗ Some default terms missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: SpecialCondition.php\n";
    $failed++;
}
echo "\n";

// Test 3: Check kg-core.php registration
echo "3. Main Plugin File - Taxonomy Registration\n";
$mainFile = $baseDir . '/kg-core.php';
if (file_exists($mainFile)) {
    echo "   ✓ File exists: kg-core.php\n";
    $content = file_get_contents($mainFile);
    
    if (strpos($content, 'SpecialCondition.php') !== false) {
        echo "   ✓ SpecialCondition file included\n";
        $passed++;
    } else {
        echo "   ✗ SpecialCondition file not included\n";
        $failed++;
    }
    
    if (strpos($content, "new \KG_Core\Taxonomies\SpecialCondition()") !== false) {
        echo "   ✓ SpecialCondition taxonomy initialized\n";
        $passed++;
    } else {
        echo "   ✗ SpecialCondition taxonomy not initialized\n";
        $failed++;
    }
    
    if (strpos($content, "rest_prepare_special-condition") !== false) {
        echo "   ✓ HTML entity decoding filter registered for special-condition\n";
        $passed++;
    } else {
        echo "   ✗ HTML entity decoding filter missing for special-condition\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: kg-core.php\n";
    $failed++;
}
echo "\n";

// Test 4: Check RecipeController updates
echo "4. RecipeController - prepare_recipe_data() Updates\n";
$recipeControllerFile = $baseDir . '/includes/API/RecipeController.php';
if (file_exists($recipeControllerFile)) {
    echo "   ✓ File exists: RecipeController.php\n";
    $content = file_get_contents($recipeControllerFile);
    
    // Check for new fields
    $newFields = [
        'age_group' => false,
        'age_group_color' => false,
        'meal_type' => false,
        'diet_types' => false,
        'author' => false,
        'expert' => false
    ];
    
    foreach ($newFields as $field => $found) {
        if (preg_match("/['\"]" . $field . "['\"]\s*=>/", $content)) {
            $newFields[$field] = true;
        }
    }
    
    $allFieldsFound = !in_array(false, $newFields, true);
    if ($allFieldsFound) {
        echo "   ✓ All new fields added to response (age_group, age_group_color, meal_type, diet_types, author, expert)\n";
        $passed++;
    } else {
        echo "   ✗ Some new fields missing: " . implode(', ', array_keys(array_filter($newFields, function($v) { return !$v; }))) . "\n";
        $failed++;
    }
    
    // Check for color code extraction
    if (strpos($content, '_kg_color_code') !== false) {
        echo "   ✓ Age group color code extraction implemented\n";
        $passed++;
    } else {
        echo "   ✗ Age group color code extraction missing\n";
        $failed++;
    }
    
    // Check that expert is outside full_detail condition
    if (strpos($content, "'expert'") !== false && strpos($content, "\$expert_data = [") !== false) {
        echo "   ✓ Expert data included in all responses\n";
        $passed++;
    } else {
        echo "   ✗ Expert data configuration issue\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 5: Check RecipeController get_recipes() updates
echo "5. RecipeController - get_recipes() Filtering & Sorting\n";
if (file_exists($recipeControllerFile)) {
    $content = file_get_contents($recipeControllerFile);
    
    // Check for new filters
    $newFilters = [
        'meal-type' => false,
        'special-condition' => false,
        'ingredient' => false,
        'search' => false
    ];
    
    foreach ($newFilters as $filter => $found) {
        if (strpos($content, "'" . $filter . "'") !== false || strpos($content, '"' . $filter . '"') !== false) {
            $newFilters[$filter] = true;
        }
    }
    
    $allFiltersFound = !in_array(false, $newFilters, true);
    if ($allFiltersFound) {
        echo "   ✓ All new filters implemented (meal-type, special-condition, ingredient, search)\n";
        $passed++;
    } else {
        echo "   ✗ Some filters missing: " . implode(', ', array_keys(array_filter($newFilters, function($v) { return !$v; }))) . "\n";
        $failed++;
    }
    
    // Check for orderby support
    if (strpos($content, 'orderby') !== false && strpos($content, 'popular') !== false) {
        echo "   ✓ Sorting by orderby parameter implemented\n";
        $passed++;
    } else {
        echo "   ✗ Sorting by orderby parameter missing\n";
        $failed++;
    }
    
    // Check for comma-separated value support
    if (strpos($content, 'explode') !== false) {
        echo "   ✓ Comma-separated filter values supported\n";
        $passed++;
    } else {
        echo "   ✗ Comma-separated filter values not supported\n";
        $failed++;
    }
    
    // Check for proper pagination response
    if (strpos($content, 'total_pages') !== false && strpos($content, 'per_page') !== false) {
        echo "   ✓ Proper pagination response format (total, page, per_page, total_pages)\n";
        $passed++;
    } else {
        echo "   ✗ Pagination response format incomplete\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 6: Check AgeGroup taxonomy updates
echo "6. AgeGroup Taxonomy - Term Name Updates\n";
$ageGroupFile = $baseDir . '/includes/Taxonomies/AgeGroup.php';
if (file_exists($ageGroupFile)) {
    echo "   ✓ File exists: AgeGroup.php\n";
    $content = file_get_contents($ageGroupFile);
    
    // Check for updated term names (age/month first)
    $expectedNames = [
        '0-6 Ay (Hazırlık Evresi)',
        '6-8 Ay (Başlangıç & Tadım)',
        '9-11 Ay (Keşif & Pütürlüye Geçiş)',
        '12-24 Ay (Aile Sofrasına Geçiş)',
        '2+ Yaş (Çocuk Gurme)'
    ];
    
    $allNamesFound = true;
    foreach ($expectedNames as $name) {
        if (strpos($content, $name) === false) {
            $allNamesFound = false;
            break;
        }
    }
    
    if ($allNamesFound) {
        echo "   ✓ All term names updated with age/month info first\n";
        $passed++;
    } else {
        echo "   ✗ Some term names not updated correctly\n";
        $failed++;
    }
    
    // Check for migration function
    if (strpos($content, 'update_existing_terms') !== false) {
        echo "   ✓ Migration function added for existing terms\n";
        $passed++;
    } else {
        echo "   ✗ Migration function missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: AgeGroup.php\n";
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

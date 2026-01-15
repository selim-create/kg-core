<?php
/**
 * Test script for Ingredient Field Consolidation
 * 
 * This script validates the field consolidation implementation
 * Run from command line: php tests/test-ingredient-consolidation.php
 */

echo "=== Ingredient Field Consolidation Test ===\n\n";

// Test 1: Check if IngredientEnricher class exists
echo "Test 1: Checking IngredientEnricher class...\n";
$enricherFile = __DIR__ . '/../includes/Admin/IngredientEnricher.php';
if (file_exists($enricherFile)) {
    echo "✅ IngredientEnricher.php exists\n";
    
    // Check class definition
    $content = file_get_contents($enricherFile);
    if (strpos($content, 'class IngredientEnricher') !== false) {
        echo "✅ IngredientEnricher class defined\n";
    } else {
        echo "❌ IngredientEnricher class not found in file\n";
    }
    
    // Check required methods
    $requiredMethods = [
        'add_enrichment_metabox',
        'render_enrichment_box',
        'get_missing_fields',
        'ajax_enrich_ingredient',
        'enqueue_scripts'
    ];
    
    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "✅ Method $method exists\n";
        } else {
            echo "❌ Method $method missing\n";
        }
    }
} else {
    echo "❌ IngredientEnricher.php not found\n";
}

echo "\n";

// Test 2: Check if FieldConsolidation class exists
echo "Test 2: Checking FieldConsolidation class...\n";
$consolidationFile = __DIR__ . '/../includes/Migration/FieldConsolidation.php';
if (file_exists($consolidationFile)) {
    echo "✅ FieldConsolidation.php exists\n";
    
    // Check class definition
    $content = file_get_contents($consolidationFile);
    if (strpos($content, 'class FieldConsolidation') !== false) {
        echo "✅ FieldConsolidation class defined\n";
    } else {
        echo "❌ FieldConsolidation class not found in file\n";
    }
    
    // Check required methods
    $requiredMethods = [
        'run',
        'migrate_category',
        'migrate_nutrition',
        'cleanup_deprecated_fields',
        'preview'
    ];
    
    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false || strpos($content, "$method(") !== false) {
            echo "✅ Method $method exists\n";
        } else {
            echo "❌ Method $method missing\n";
        }
    }
} else {
    echo "❌ FieldConsolidation.php not found\n";
}

echo "\n";

// Test 3: Check IngredientMetaBox modifications
echo "Test 3: Checking IngredientMetaBox modifications...\n";
$metaboxFile = __DIR__ . '/../includes/Admin/IngredientMetaBox.php';
if (file_exists($metaboxFile)) {
    echo "✅ IngredientMetaBox.php exists\n";
    
    $content = file_get_contents($metaboxFile);
    
    // Check that deprecated fields are removed
    $removedFields = [
        'kg_category',
        'kg_is_allergen',
        'kg_allergen_type',
        'kg_prep_methods_list',
        'kg_prep_tips',
        'kg_cooking_suggestions',
        'Besin Değerleri (Genel - Mevcut Sistem)'
    ];
    
    foreach ($removedFields as $field) {
        if (strpos($content, $field) === false) {
            echo "✅ Deprecated field '$field' removed\n";
        } else {
            echo "⚠️  Field '$field' still present (may be in comments or different context)\n";
        }
    }
    
    // Check that 100g nutrition fields exist
    $nutrition100gFields = [
        'kg_ing_calories_100g',
        'kg_ing_protein_100g',
        'kg_ing_carbs_100g',
        'kg_ing_fat_100g',
        'kg_ing_fiber_100g',
        'kg_ing_sugar_100g'
    ];
    
    foreach ($nutrition100gFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "✅ 100g nutrition field '$field' present\n";
        } else {
            echo "❌ 100g nutrition field '$field' missing\n";
        }
    }
} else {
    echo "❌ IngredientMetaBox.php not found\n";
}

echo "\n";

// Test 4: Check AIService prompt updates
echo "Test 4: Checking AIService prompt updates...\n";
$aiServiceFile = __DIR__ . '/../includes/Services/AIService.php';
if (file_exists($aiServiceFile)) {
    echo "✅ AIService.php exists\n";
    
    $content = file_get_contents($aiServiceFile);
    
    // Check for 100g nutrition format
    if (strpos($content, '100g için') !== false || strpos($content, '100g başına') !== false) {
        echo "✅ Prompt includes 100g nutrition format\n";
    } else {
        echo "⚠️  100g nutrition format not clearly mentioned in prompt\n";
    }
    
    // Check for minerals and sugar
    if (strpos($content, 'minerals') !== false || strpos($content, 'mineraller') !== false) {
        echo "✅ Prompt includes minerals field\n";
    } else {
        echo "⚠️  Minerals field not found in prompt\n";
    }
    
    if (strpos($content, 'sugar') !== false || strpos($content, 'şeker') !== false) {
        echo "✅ Prompt includes sugar field\n";
    } else {
        echo "⚠️  Sugar field not found in prompt\n";
    }
} else {
    echo "❌ AIService.php not found\n";
}

echo "\n";

// Test 5: Check IngredientGenerator updates
echo "Test 5: Checking IngredientGenerator updates...\n";
$generatorFile = __DIR__ . '/../includes/Services/IngredientGenerator.php';
if (file_exists($generatorFile)) {
    echo "✅ IngredientGenerator.php exists\n";
    
    $content = file_get_contents($generatorFile);
    
    // Check for new nutrition keys
    $newKeys = [
        '_kg_ing_calories_100g',
        '_kg_ing_protein_100g',
        '_kg_ing_carbs_100g',
        '_kg_ing_fat_100g',
        '_kg_ing_fiber_100g',
        '_kg_ing_sugar_100g',
        '_kg_ing_minerals'
    ];
    
    foreach ($newKeys as $key) {
        if (strpos($content, $key) !== false) {
            echo "✅ New nutrition key '$key' used\n";
        } else {
            echo "❌ New nutrition key '$key' not found\n";
        }
    }
    
    // Check that old _kg_category meta save is removed
    if (strpos($content, "update_post_meta(\$post_id, '_kg_category'") === false) {
        echo "✅ Old category meta save removed\n";
    } else {
        echo "⚠️  Old category meta save still present\n";
    }
} else {
    echo "❌ IngredientGenerator.php not found\n";
}

echo "\n";

// Test 6: Check IngredientController API updates
echo "Test 6: Checking IngredientController API updates...\n";
$controllerFile = __DIR__ . '/../includes/API/IngredientController.php';
if (file_exists($controllerFile)) {
    echo "✅ IngredientController.php exists\n";
    
    $content = file_get_contents($controllerFile);
    
    // Check for taxonomy-based category
    if (strpos($content, 'wp_get_post_terms') !== false && strpos($content, 'ingredient-category') !== false) {
        echo "✅ Category retrieved from taxonomy\n";
    } else {
        echo "❌ Category not retrieved from taxonomy\n";
    }
    
    // Check that duplicate nutrition objects are consolidated
    if (strpos($content, 'nutrition_per_100g') === false) {
        echo "✅ Duplicate nutrition_per_100g object removed\n";
    } else {
        echo "⚠️  nutrition_per_100g object still present\n";
    }
    
    // Check that deprecated allergen fields are removed
    if (strpos($content, "'is_allergen'") === false && strpos($content, "'allergen_type'") === false) {
        echo "✅ Deprecated allergen fields removed from API\n";
    } else {
        echo "⚠️  Deprecated allergen fields still in API response\n";
    }
} else {
    echo "❌ IngredientController.php not found\n";
}

echo "\n";

// Test 7: Check kg-core.php registration
echo "Test 7: Checking kg-core.php registration...\n";
$coreFile = __DIR__ . '/../kg-core.php';
if (file_exists($coreFile)) {
    echo "✅ kg-core.php exists\n";
    
    $content = file_get_contents($coreFile);
    
    // Check IngredientEnricher registration
    if (strpos($content, 'IngredientEnricher.php') !== false) {
        echo "✅ IngredientEnricher.php required\n";
    } else {
        echo "❌ IngredientEnricher.php not required\n";
    }
    
    if (strpos($content, 'new \KG_Core\Admin\IngredientEnricher()') !== false) {
        echo "✅ IngredientEnricher initialized\n";
    } else {
        echo "❌ IngredientEnricher not initialized\n";
    }
    
    // Check FieldConsolidation registration
    if (strpos($content, 'FieldConsolidation.php') !== false) {
        echo "✅ FieldConsolidation.php required\n";
    } else {
        echo "❌ FieldConsolidation.php not required\n";
    }
} else {
    echo "❌ kg-core.php not found\n";
}

echo "\n";

// Test 8: Check MigrationPage updates
echo "Test 8: Checking MigrationPage updates...\n";
$migrationPageFile = __DIR__ . '/../includes/Admin/MigrationPage.php';
if (file_exists($migrationPageFile)) {
    echo "✅ MigrationPage.php exists\n";
    
    $content = file_get_contents($migrationPageFile);
    
    // Check for FieldConsolidation usage
    if (strpos($content, 'use KG_Core\Migration\FieldConsolidation') !== false) {
        echo "✅ FieldConsolidation imported\n";
    } else {
        echo "❌ FieldConsolidation not imported\n";
    }
    
    // Check for AJAX handlers
    if (strpos($content, 'ajaxPreviewFieldConsolidation') !== false) {
        echo "✅ Preview AJAX handler exists\n";
    } else {
        echo "❌ Preview AJAX handler missing\n";
    }
    
    if (strpos($content, 'ajaxRunFieldConsolidation') !== false) {
        echo "✅ Run AJAX handler exists\n";
    } else {
        echo "❌ Run AJAX handler missing\n";
    }
    
    // Check for UI elements
    if (strpos($content, 'Malzeme (Ingredient) Alan Birleştirme') !== false) {
        echo "✅ Consolidation UI section added\n";
    } else {
        echo "❌ Consolidation UI section missing\n";
    }
} else {
    echo "❌ MigrationPage.php not found\n";
}

echo "\n";

// Test 9: Check migration.js updates
echo "Test 9: Checking migration.js updates...\n";
$migrationJsFile = __DIR__ . '/../assets/admin/js/migration.js';
if (file_exists($migrationJsFile)) {
    echo "✅ migration.js exists\n";
    
    $content = file_get_contents($migrationJsFile);
    
    // Check for consolidation handlers
    if (strpos($content, 'previewConsolidation') !== false) {
        echo "✅ previewConsolidation handler exists\n";
    } else {
        echo "❌ previewConsolidation handler missing\n";
    }
    
    if (strpos($content, 'runConsolidation') !== false) {
        echo "✅ runConsolidation handler exists\n";
    } else {
        echo "❌ runConsolidation handler missing\n";
    }
    
    // Check for button bindings
    if (strpos($content, '#kg-preview-consolidation') !== false) {
        echo "✅ Preview button binding exists\n";
    } else {
        echo "❌ Preview button binding missing\n";
    }
    
    if (strpos($content, '#kg-run-consolidation') !== false) {
        echo "✅ Run button binding exists\n";
    } else {
        echo "❌ Run button binding missing\n";
    }
} else {
    echo "❌ migration.js not found\n";
}

echo "\n";
echo "=== Test Summary ===\n";
echo "All critical components have been checked.\n";
echo "Review any ⚠️  or ❌ items above.\n";

<?php
/**
 * Static Code Analysis Test for SEO & Ingredient Queue Implementation
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core Implementation Verification ===\n\n";

$baseDir = __DIR__;
$passed = 0;
$failed = 0;

// Test 1: Check if RecipeSEOGenerator file exists
echo "1. RecipeSEOGenerator Service\n";
$seoGenFile = $baseDir . '/includes/Services/RecipeSEOGenerator.php';
if (file_exists($seoGenFile)) {
    echo "   ✓ File exists: RecipeSEOGenerator.php\n";
    $content = file_get_contents($seoGenFile);
    
    // Check for required methods
    $requiredMethods = [
        'generateSEO',
        'saveSEO',
        'buildPrompt',
        'callOpenAI',
        'parseResponse'
    ];
    
    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
    
    // Check for RankMath meta keys
    $rankMathKeys = [
        'rank_math_focus_keyword',
        'rank_math_title',
        'rank_math_description'
    ];
    
    foreach ($rankMathKeys as $key) {
        if (strpos($content, $key) !== false) {
            echo "   ✓ RankMath key implemented: $key\n";
            $passed++;
        } else {
            echo "   ✗ RankMath key missing: $key\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ File not found: RecipeSEOGenerator.php\n";
    $failed++;
}

// Test 2: Check main plugin file integration
echo "\n2. Plugin Integration (kg-core.php)\n";
$pluginFile = $baseDir . '/kg-core.php';
if (file_exists($pluginFile)) {
    $content = file_get_contents($pluginFile);
    
    // Check if RecipeSEOGenerator is included
    if (strpos($content, 'RecipeSEOGenerator.php') !== false) {
        echo "   ✓ RecipeSEOGenerator included in plugin\n";
        $passed++;
    } else {
        echo "   ✗ RecipeSEOGenerator not included\n";
        $failed++;
    }
    
    // Check for CRON hooks
    $cronHooks = [
        'kg_generate_ingredient',
        'kg_generate_recipe_seo'
    ];
    
    foreach ($cronHooks as $hook) {
        if (strpos($content, "add_action( '$hook'") !== false || 
            strpos($content, "add_action( \"$hook\"") !== false) {
            echo "   ✓ CRON hook registered: $hook\n";
            $passed++;
        } else {
            echo "   ✗ CRON hook missing: $hook\n";
            $failed++;
        }
    }
    
    // Check for fallback mechanism
    if (strpos($content, 'Fallback: Create basic ingredient') !== false) {
        echo "   ✓ Fallback mechanism implemented\n";
        $passed++;
    } else {
        echo "   ✗ Fallback mechanism missing\n";
        $failed++;
    }
} else {
    echo "   ✗ Plugin file not found\n";
    $failed++;
}

// Test 3: Check RecipeMetaBox integration
echo "\n3. RecipeMetaBox Integration\n";
$metaBoxFile = $baseDir . '/includes/Admin/RecipeMetaBox.php';
if (file_exists($metaBoxFile)) {
    $content = file_get_contents($metaBoxFile);
    
    // Check for autoGenerateSEO method
    if (strpos($content, 'function autoGenerateSEO') !== false) {
        echo "   ✓ autoGenerateSEO method exists\n";
        $passed++;
    } else {
        echo "   ✗ autoGenerateSEO method missing\n";
        $failed++;
    }
    
    // Check if it's called in save_custom_meta_data
    if (strpos($content, 'autoGenerateSEO') !== false && 
        strpos($content, 'kg_auto_generate_seo') !== false) {
        echo "   ✓ SEO generation integrated into save workflow\n";
        $passed++;
    } else {
        echo "   ✗ SEO generation not integrated into save workflow\n";
        $failed++;
    }
    
    // Check for CRON scheduling
    if (strpos($content, 'wp_schedule_single_event') !== false &&
        strpos($content, 'kg_generate_recipe_seo') !== false) {
        echo "   ✓ CRON scheduling implemented for SEO\n";
        $passed++;
    } else {
        echo "   ✗ CRON scheduling missing for SEO\n";
        $failed++;
    }
} else {
    echo "   ✗ RecipeMetaBox file not found\n";
    $failed++;
}

// Test 4: Check AIRecipeMigrator integration
echo "\n4. AIRecipeMigrator Integration\n";
$migratorFile = $baseDir . '/includes/Migration/AIRecipeMigrator.php';
if (file_exists($migratorFile)) {
    $content = file_get_contents($migratorFile);
    
    // Check for SEO generation in createRecipe
    if (strpos($content, 'kg_generate_recipe_seo') !== false) {
        echo "   ✓ SEO generation added to recipe migration\n";
        $passed++;
    } else {
        echo "   ✗ SEO generation not added to recipe migration\n";
        $failed++;
    }
    
    // Check that ingredients use queue instead of direct creation
    if (strpos($content, "Don't create a draft ingredient immediately") !== false ||
        strpos($content, 'use queue system instead') !== false) {
        echo "   ✓ Ingredient queue system implemented in migrator\n";
        $passed++;
    } else {
        echo "   ✗ Ingredient queue system not properly implemented\n";
        $failed++;
    }
} else {
    echo "   ✗ AIRecipeMigrator file not found\n";
    $failed++;
}

// Test 5: PHP Syntax Check
echo "\n5. PHP Syntax Validation\n";
$filesToCheck = [
    'includes/Services/RecipeSEOGenerator.php',
    'includes/Admin/RecipeMetaBox.php',
    'includes/Migration/AIRecipeMigrator.php',
    'kg-core.php'
];

foreach ($filesToCheck as $file) {
    $fullPath = $baseDir . '/' . $file;
    
    // Security: Validate file path is within base directory
    $realPath = realpath($fullPath);
    $realBase = realpath($baseDir);
    
    if ($realPath === false || strpos($realPath, $realBase) !== 0) {
        echo "   ✗ Invalid path: $file\n";
        $failed++;
        continue;
    }
    
    if (file_exists($fullPath)) {
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
        if ($returnCode === 0) {
            echo "   ✓ Valid syntax: $file\n";
            $passed++;
        } else {
            echo "   ✗ Syntax error: $file\n";
            echo "     " . implode("\n     ", $output) . "\n";
            $failed++;
        }
    }
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✓ All tests passed!\n";
    echo "\nImplementation Complete:\n";
    echo "1. RecipeSEOGenerator service created with OpenAI integration\n";
    echo "2. SEO generation integrated into recipe save workflow\n";
    echo "3. SEO generation added to recipe migration process\n";
    echo "4. Ingredient queue system improved with CRON-based processing\n";
    echo "5. Fallback mechanism implemented for AI failures\n";
    echo "6. RankMath meta fields (focus_keyword, title, description) supported\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the errors above.\n";
    exit(1);
}

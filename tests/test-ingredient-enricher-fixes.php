<?php
/**
 * Test script for IngredientEnricher Nonce and Pairings Fixes
 * 
 * This script validates the nonce and pairings fixes implementation
 * Run from command line: php tests/test-ingredient-enricher-fixes.php
 */

echo "=== IngredientEnricher Nonce and Pairings Fixes Test ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

// Test 1: Check IngredientEnricher.php nonce fix
echo "Test 1: Checking IngredientEnricher.php nonce implementation...\n";
$enricherFile = __DIR__ . '/../includes/Admin/IngredientEnricher.php';
if (file_exists($enricherFile)) {
    echo "✅ IngredientEnricher.php exists\n";
    
    $content = file_get_contents($enricherFile);
    
    // Check for wp_create_nonce instead of wp_nonce_field
    if (strpos($content, "\$nonce = wp_create_nonce('kg_enrich_ingredient');") !== false) {
        echo "✅ Uses wp_create_nonce for nonce generation\n";
        $testsPassed++;
    } else {
        echo "❌ Missing wp_create_nonce call\n";
        $testsFailed++;
    }
    
    // Check for hidden input with proper ID
    if (strpos($content, 'id="kg_enricher_nonce"') !== false) {
        echo "✅ Hidden input has correct ID (kg_enricher_nonce)\n";
        $testsPassed++;
    } else {
        echo "❌ Hidden input ID not correct\n";
        $testsFailed++;
    }
    
    // Check for wp_localize_script usage
    if (strpos($content, "wp_localize_script('kg-enricher-script', 'kgEnricher'") !== false) {
        echo "✅ Uses wp_localize_script for passing data to JavaScript\n";
        $testsPassed++;
    } else {
        echo "❌ Missing wp_localize_script\n";
        $testsFailed++;
    }
    
    // Check JavaScript uses kgEnricher.nonce
    if (strpos($content, 'nonce: kgEnricher.nonce') !== false) {
        echo "✅ JavaScript uses kgEnricher.nonce for AJAX calls\n";
        $testsPassed++;
    } else {
        echo "❌ JavaScript not using kgEnricher.nonce\n";
        $testsFailed++;
    }
    
    // Check JavaScript uses kgEnricher.postId
    if (strpos($content, 'post_id: kgEnricher.postId') !== false) {
        echo "✅ JavaScript uses kgEnricher.postId for AJAX calls\n";
        $testsPassed++;
    } else {
        echo "❌ JavaScript not using kgEnricher.postId\n";
        $testsFailed++;
    }
    
    // Check for pairings debug logging in update_single_field
    if (strpos($content, "if (\$key === '_kg_pairings')") !== false && 
        strpos($content, "error_log('KG Enricher: Attempting to save pairings") !== false) {
        echo "✅ Pairings debug logging added to update_single_field\n";
        $testsPassed++;
    } else {
        echo "❌ Missing pairings debug logging in update_single_field\n";
        $testsFailed++;
    }
    
} else {
    echo "❌ IngredientEnricher.php not found\n";
    $testsFailed += 6;
}

echo "\n";

// Test 2: Check AIService.php pairings validation
echo "Test 2: Checking AIService.php pairings validation...\n";
$aiServiceFile = __DIR__ . '/../includes/Services/AIService.php';
if (file_exists($aiServiceFile)) {
    echo "✅ AIService.php exists\n";
    
    $content = file_get_contents($aiServiceFile);
    
    // Check for pairings validation in parseIngredientResponse
    if (strpos($content, "if (!isset(\$data['pairings']) || !is_array(\$data['pairings']) || empty(\$data['pairings']))") !== false) {
        echo "✅ Pairings validation added to parseIngredientResponse\n";
        $testsPassed++;
    } else {
        echo "❌ Missing pairings validation in parseIngredientResponse\n";
        $testsFailed++;
    }
    
    // Check for pairings debug logging
    if (strpos($content, "error_log('KG Core: pairings alanı AI yanıtında bulunamadı veya boş") !== false) {
        echo "✅ Pairings debug logging added when missing\n";
        $testsPassed++;
    } else {
        echo "❌ Missing pairings debug logging\n";
        $testsFailed++;
    }
    
    // Check for WP_DEBUG conditional logging
    if (strpos($content, "if (defined('WP_DEBUG') && WP_DEBUG)") !== false && 
        strpos($content, "error_log('KG Core AI Response pairings:") !== false) {
        echo "✅ WP_DEBUG conditional pairings logging added\n";
        $testsPassed++;
    } else {
        echo "❌ Missing WP_DEBUG conditional logging\n";
        $testsFailed++;
    }
    
    // Check for enhanced pairings reminder in prompt
    if (strpos($content, "⚠️ ÖNEMLİ HATIRLATMALAR:") !== false && 
        strpos($content, "'pairings' alanı ZORUNLUDUR!") !== false) {
        echo "✅ Enhanced pairings reminder added to prompt\n";
        $testsPassed++;
    } else {
        echo "❌ Missing enhanced pairings reminder in prompt\n";
        $testsFailed++;
    }
    
    // Check pairings emphasis format
    if (strpos($content, 'Bu alan BOŞ BIRAKILAMAZ!') !== false) {
        echo "✅ Strong pairings emphasis in prompt\n";
        $testsPassed++;
    } else {
        echo "❌ Missing strong pairings emphasis\n";
        $testsFailed++;
    }
    
} else {
    echo "❌ AIService.php not found\n";
    $testsFailed += 5;
}

echo "\n";

// Test 3: Check IngredientGenerator.php pairings logging
echo "Test 3: Checking IngredientGenerator.php pairings logging...\n";
$generatorFile = __DIR__ . '/../includes/Services/IngredientGenerator.php';
if (file_exists($generatorFile)) {
    echo "✅ IngredientGenerator.php exists\n";
    
    $content = file_get_contents($generatorFile);
    
    // Check for pairings save success logging
    if (strpos($content, "error_log('KG Generator: Saved ' . count(\$sanitized_pairings) . ' pairings for post '") !== false) {
        echo "✅ Pairings save success logging added\n";
        $testsPassed++;
    } else {
        echo "❌ Missing pairings save success logging\n";
        $testsFailed++;
    }
    
    // Check for pairings missing warning
    if (strpos($content, "error_log('KG Generator: WARNING - No pairings data found for post '") !== false) {
        echo "✅ Pairings missing warning added\n";
        $testsPassed++;
    } else {
        echo "❌ Missing pairings missing warning\n";
        $testsFailed++;
    }
    
    // Check pairings validation logic
    if (strpos($content, "// Pairings (JSON array) - ZORUNLU ALAN") !== false) {
        echo "✅ Pairings marked as mandatory field\n";
        $testsPassed++;
    } else {
        echo "❌ Pairings not marked as mandatory\n";
        $testsFailed++;
    }
    
} else {
    echo "❌ IngredientGenerator.php not found\n";
    $testsFailed += 3;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";

if ($testsFailed === 0) {
    echo "\n✅ All tests passed! Implementation is correct.\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed. Please review the implementation.\n";
    exit(1);
}

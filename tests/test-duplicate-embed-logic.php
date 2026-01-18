<?php
/**
 * Unit Test for Duplicate Embed Fix (Standalone)
 * 
 * This test can be run directly with PHP without requiring WordPress
 * php tests/test-duplicate-embed-logic.php
 */

echo "=== Testing Duplicate Embed Fix Logic ===\n\n";

// Test the core logic of duplicate detection
function test_unique_key_generation() {
    echo "Test 1: Unique key generation\n";
    
    // Same IDs in different order should produce same key
    $ids1 = [3, 1, 2];
    $ids2 = [1, 2, 3];
    $ids3 = [2, 3, 1];
    
    sort($ids1);
    sort($ids2);
    sort($ids3);
    
    $key1 = 'ingredient-' . implode('-', $ids1);
    $key2 = 'ingredient-' . implode('-', $ids2);
    $key3 = 'ingredient-' . implode('-', $ids3);
    
    if ($key1 === $key2 && $key2 === $key3) {
        echo "✓ Same IDs produce same key regardless of order: $key1\n";
    } else {
        echo "✗ Keys don't match: $key1, $key2, $key3\n";
        return false;
    }
    
    // Different IDs should produce different keys
    $ids4 = [4, 5, 6];
    sort($ids4);
    $key4 = 'ingredient-' . implode('-', $ids4);
    
    if ($key1 !== $key4) {
        echo "✓ Different IDs produce different keys: $key1 vs $key4\n";
    } else {
        echo "✗ Different IDs produced same key\n";
        return false;
    }
    
    echo "\n";
    return true;
}

function test_shortcode_regex() {
    echo "Test 2: Shortcode regex parsing\n";
    
    $test_cases = [
        '[kg-embed type="ingredient" ids="1,2,3"]',
        '[kg-embed type=\'ingredient\' ids=\'1,2,3\']',
        '[kg-embed type=ingredient ids=1,2,3]',
        '[KG-EMBED type="ingredient" ids="1,2,3"]', // case insensitive
    ];
    
    $pattern = '/\[kg-embed\s+([^\]]+)\]/i';
    
    foreach ($test_cases as $shortcode) {
        if (preg_match($pattern, $shortcode, $match)) {
            echo "✓ Matched: $shortcode\n";
        } else {
            echo "✗ Failed to match: $shortcode\n";
            return false;
        }
    }
    
    echo "\n";
    return true;
}

function test_placeholder_regex() {
    echo "Test 3: Block placeholder regex parsing\n";
    
    $test_cases = [
        '<div class="kg-embed-placeholder" data-type="ingredient" data-ids="1,2,3">',
        '<div class="kg-embed-placeholder other-class" data-type="ingredient" data-ids="1,2,3">',
        '<div data-type="ingredient" class="kg-embed-placeholder" data-ids="1,2,3">',
    ];
    
    $pattern = '/<div[^>]*class="[^"]*kg-embed-placeholder[^"]*"[^>]*data-type="([^"]*)"[^>]*data-ids="([^"]*)"[^>]*>/i';
    
    foreach ($test_cases as $placeholder) {
        if (preg_match($pattern, $placeholder, $match)) {
            echo "✓ Matched: " . substr($placeholder, 0, 50) . "...\n";
            echo "  Type: {$match[1]}, IDs: {$match[2]}\n";
        } else {
            echo "✗ Failed to match: $placeholder\n";
            return false;
        }
    }
    
    echo "\n";
    return true;
}

function test_duplicate_detection_logic() {
    echo "Test 4: Duplicate detection logic simulation\n";
    
    // Simulate content with both shortcode and placeholder (the duplicate scenario)
    $content = <<<HTML
<p>Introduction</p>
<div class="kg-embed-placeholder" data-type="ingredient" data-ids="1,2,3">
[kg-embed type="ingredient" ids="1,2,3"]
</div>
<p>Conclusion</p>
HTML;
    
    $processed_embeds = [];
    $embeds = [];
    
    // Parse shortcodes
    $shortcode_pattern = '/\[kg-embed\s+([^\]]+)\]/i';
    if (preg_match_all($shortcode_pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $shortcode = $match[0];
            
            preg_match('/type=["\']?([^"\'\s]+)["\']?/i', $shortcode, $type_match);
            preg_match('/ids=["\']?([^"\']+)["\']?/i', $shortcode, $ids_match);
            
            if (isset($type_match[1]) && isset($ids_match[1])) {
                $type = $type_match[1];
                $ids = array_map('intval', array_filter(explode(',', $ids_match[1])));
                sort($ids);
                
                $embed_key = $type . '-' . implode('-', $ids);
                
                if (!isset($processed_embeds[$embed_key])) {
                    $processed_embeds[$embed_key] = true;
                    $embeds[] = ['type' => $type, 'ids' => $ids, 'source' => 'shortcode'];
                }
            }
        }
    }
    
    // Parse block placeholders (only if no shortcodes found)
    if (empty($embeds)) {
        $placeholder_pattern = '/<div[^>]*class="[^"]*kg-embed-placeholder[^"]*"[^>]*data-type="([^"]*)"[^>]*data-ids="([^"]*)"[^>]*>/i';
        if (preg_match_all($placeholder_pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $type = $match[1];
                $ids = array_map('intval', array_filter(explode(',', $match[2])));
                sort($ids);
                
                $embed_key = $type . '-' . implode('-', $ids);
                
                if (!isset($processed_embeds[$embed_key])) {
                    $processed_embeds[$embed_key] = true;
                    $embeds[] = ['type' => $type, 'ids' => $ids, 'source' => 'placeholder'];
                }
            }
        }
    }
    
    echo "Found " . count($embeds) . " embed(s)\n";
    
    if (count($embeds) === 1) {
        echo "✓ CORRECT! Only 1 embed found (duplicate prevented)\n";
        echo "  Source: " . $embeds[0]['source'] . "\n";
        echo "  Type: " . $embeds[0]['type'] . "\n";
        echo "  IDs: " . implode(',', $embeds[0]['ids']) . "\n";
    } else {
        echo "✗ FAILED! Expected 1 embed, got " . count($embeds) . "\n";
        return false;
    }
    
    echo "\n";
    return true;
}

function test_backwards_compatibility() {
    echo "Test 5: Backwards compatibility (only placeholders, no shortcodes)\n";
    
    $content = <<<HTML
<p>Introduction</p>
<div class="kg-embed-placeholder" data-type="ingredient" data-ids="1,2,3"></div>
<p>Conclusion</p>
HTML;
    
    $processed_embeds = [];
    $embeds = [];
    
    // Parse shortcodes first
    $shortcode_pattern = '/\[kg-embed\s+([^\]]+)\]/i';
    if (preg_match_all($shortcode_pattern, $content, $matches, PREG_SET_ORDER)) {
        // ... would add to embeds
    }
    
    // Parse placeholders only if no shortcodes
    if (empty($embeds)) {
        $placeholder_pattern = '/<div[^>]*class="[^"]*kg-embed-placeholder[^"]*"[^>]*data-type="([^"]*)"[^>]*data-ids="([^"]*)"[^>]*>/i';
        if (preg_match_all($placeholder_pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $type = $match[1];
                $ids = array_map('intval', array_filter(explode(',', $match[2])));
                sort($ids);
                
                $embed_key = $type . '-' . implode('-', $ids);
                
                if (!isset($processed_embeds[$embed_key])) {
                    $processed_embeds[$embed_key] = true;
                    $embeds[] = ['type' => $type, 'ids' => $ids, 'source' => 'placeholder'];
                }
            }
        }
    }
    
    echo "Found " . count($embeds) . " embed(s)\n";
    
    if (count($embeds) === 1 && $embeds[0]['source'] === 'placeholder') {
        echo "✓ Backwards compatibility maintained (placeholders still work)\n";
    } else {
        echo "✗ Backwards compatibility broken\n";
        return false;
    }
    
    echo "\n";
    return true;
}

// Run all tests
$all_passed = true;

$all_passed = test_unique_key_generation() && $all_passed;
$all_passed = test_shortcode_regex() && $all_passed;
$all_passed = test_placeholder_regex() && $all_passed;
$all_passed = test_duplicate_detection_logic() && $all_passed;
$all_passed = test_backwards_compatibility() && $all_passed;

echo "=== Test Results ===\n";
if ($all_passed) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed\n";
    exit(1);
}

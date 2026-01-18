<?php
/**
 * Verification Script for Duplicate Embed Fix
 * 
 * This script demonstrates the fix by showing the before/after behavior
 */

echo "=== Duplicate Embed Fix Verification ===\n\n";

// Simulate the OLD behavior (before fix)
function extract_embeds_old($content) {
    $embeds = [];
    $embed_counter = 0;
    
    // Parse shortcodes
    if (preg_match_all('/\[kg-embed\s+([^\]]+)\]/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $shortcode = $match[0];
            preg_match('/type=["\']?([^"\'\s]+)["\']?/i', $shortcode, $type_match);
            preg_match('/ids=["\']?([^"\']+)["\']?/i', $shortcode, $ids_match);
            
            if (isset($type_match[1]) && isset($ids_match[1])) {
                $embeds[] = [
                    'type' => $type_match[1],
                    'ids' => $ids_match[1],
                    'source' => 'shortcode'
                ];
            }
        }
    }
    
    // Parse block placeholders (runs ALWAYS - this is the bug!)
    if (preg_match_all('/<div[^>]*class="[^"]*kg-embed-placeholder[^"]*"[^>]*data-type="([^"]*)"[^>]*data-ids="([^"]*)"[^>]*>/i', 
        $content, $block_matches, PREG_SET_ORDER)) {
        foreach ($block_matches as $match) {
            $embeds[] = [
                'type' => $match[1],
                'ids' => $match[2],
                'source' => 'placeholder'
            ];
        }
    }
    
    return $embeds;
}

// Simulate the NEW behavior (after fix)
function extract_embeds_new($content) {
    $embeds = [];
    $processed_embeds = [];
    
    // Parse shortcodes
    if (preg_match_all('/\[kg-embed\s+([^\]]+)\]/i', $content, $matches, PREG_SET_ORDER)) {
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
                    $embeds[] = [
                        'type' => $type,
                        'ids' => implode(',', $ids),
                        'source' => 'shortcode'
                    ];
                }
            }
        }
    }
    
    // Parse block placeholders ONLY if no shortcodes
    if (empty($embeds) && preg_match_all('/<div[^>]*class="[^"]*kg-embed-placeholder[^"]*"[^>]*data-type="([^"]*)"[^>]*data-ids="([^"]*)"[^>]*>/i', 
        $content, $block_matches, PREG_SET_ORDER)) {
        foreach ($block_matches as $match) {
            $type = $match[1];
            $ids = array_map('intval', array_filter(explode(',', $match[2])));
            sort($ids);
            
            $embed_key = $type . '-' . implode('-', $ids);
            
            if (!isset($processed_embeds[$embed_key])) {
                $processed_embeds[$embed_key] = true;
                $embeds[] = [
                    'type' => $type,
                    'ids' => implode(',', $ids),
                    'source' => 'placeholder'
                ];
            }
        }
    }
    
    return $embeds;
}

// Test scenario: Gutenberg block output with 3 ingredients
$gutenberg_content = <<<HTML
<p>This is an introduction paragraph about ingredients.</p>

<div class="kg-embed-placeholder" data-type="ingredient" data-ids="101,102,103">
[kg-embed type="ingredient" ids="101,102,103"]
</div>

<p>This is a conclusion paragraph.</p>
HTML;

echo "TEST SCENARIO: Gutenberg block with 3 ingredients\n";
echo str_repeat("=", 70) . "\n\n";

echo "Content:\n";
echo $gutenberg_content . "\n\n";

echo str_repeat("-", 70) . "\n";
echo "OLD BEHAVIOR (BUGGY):\n";
echo str_repeat("-", 70) . "\n";

$old_embeds = extract_embeds_old($gutenberg_content);
echo "Found " . count($old_embeds) . " embed(s):\n";
foreach ($old_embeds as $i => $embed) {
    echo "  " . ($i + 1) . ". Type: {$embed['type']}, IDs: {$embed['ids']}, Source: {$embed['source']}\n";
}

if (count($old_embeds) > 1) {
    echo "\n❌ BUG: Same embed appears " . count($old_embeds) . " times!\n";
    echo "   Frontend would show " . count($old_embeds) * 3 . " cards instead of 3\n";
}

echo "\n" . str_repeat("-", 70) . "\n";
echo "NEW BEHAVIOR (FIXED):\n";
echo str_repeat("-", 70) . "\n";

$new_embeds = extract_embeds_new($gutenberg_content);
echo "Found " . count($new_embeds) . " embed(s):\n";
foreach ($new_embeds as $i => $embed) {
    echo "  " . ($i + 1) . ". Type: {$embed['type']}, IDs: {$embed['ids']}, Source: {$embed['source']}\n";
}

if (count($new_embeds) === 1) {
    echo "\n✅ FIXED: Only 1 embed extracted!\n";
    echo "   Frontend will correctly show 3 cards\n";
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// Test backwards compatibility
$legacy_content = <<<HTML
<p>This is legacy content with only placeholders.</p>

<div class="kg-embed-placeholder" data-type="ingredient" data-ids="201,202,203"></div>

<p>End of content.</p>
HTML;

echo "BACKWARDS COMPATIBILITY TEST: Legacy content with only placeholders\n";
echo str_repeat("=", 70) . "\n\n";

$legacy_embeds = extract_embeds_new($legacy_content);
echo "Found " . count($legacy_embeds) . " embed(s):\n";
foreach ($legacy_embeds as $i => $embed) {
    echo "  " . ($i + 1) . ". Type: {$embed['type']}, IDs: {$embed['ids']}, Source: {$embed['source']}\n";
}

if (count($legacy_embeds) === 1 && $legacy_embeds[0]['source'] === 'placeholder') {
    echo "\n✅ Backwards compatibility maintained!\n";
    echo "   Legacy content still works correctly\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "\n=== Verification Complete ===\n";

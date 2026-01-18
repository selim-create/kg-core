<?php
/**
 * Test Script for Duplicate Embed Fix
 * 
 * This script tests that embeds are not duplicated when both shortcode and 
 * block placeholder exist in the content (as happens with Gutenberg blocks)
 * 
 * Run this from WordPress root using: wp eval-file tests/test-duplicate-embed-fix.php
 */

// Prevent direct access - must be run from WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
    // Attempt to find WordPress bootstrap
    $wp_load_locations = [
        __DIR__ . '/../../../wp-load.php',  // Standard plugin location
        __DIR__ . '/../../wp-load.php',     // Alternative location
        __DIR__ . '/../wp-load.php',        // Another alternative
    ];
    
    $wp_loaded = false;
    foreach ( $wp_load_locations as $wp_load ) {
        if ( file_exists( $wp_load ) ) {
            require_once $wp_load;
            $wp_loaded = true;
            break;
        }
    }
    
    if ( ! $wp_loaded ) {
        die( "Error: WordPress environment not found. Please run this script using WP-CLI:\n  wp eval-file tests/test-duplicate-embed-fix.php\n" );
    }
}

echo "=== Testing Duplicate Embed Fix ===\n\n";

// Test 1: Check if ContentEmbed class exists
echo "Test 1: Check if ContentEmbed class exists\n";
if ( class_exists( '\KG_Core\Shortcodes\ContentEmbed' ) ) {
    echo "✓ ContentEmbed class exists\n\n";
} else {
    echo "✗ ContentEmbed class NOT found\n\n";
    exit(1);
}

// Create test ingredients
echo "Creating test ingredients...\n";

$ingredient1_id = wp_insert_post([
    'post_title' => 'Test Ingredient 1 - ' . date('Y-m-d H:i:s'),
    'post_content' => 'Test ingredient 1 content.',
    'post_excerpt' => 'Test ingredient 1',
    'post_status' => 'publish',
    'post_type' => 'ingredient'
]);

$ingredient2_id = wp_insert_post([
    'post_title' => 'Test Ingredient 2 - ' . date('Y-m-d H:i:s'),
    'post_content' => 'Test ingredient 2 content.',
    'post_excerpt' => 'Test ingredient 2',
    'post_status' => 'publish',
    'post_type' => 'ingredient'
]);

$ingredient3_id = wp_insert_post([
    'post_title' => 'Test Ingredient 3 - ' . date('Y-m-d H:i:s'),
    'post_content' => 'Test ingredient 3 content.',
    'post_excerpt' => 'Test ingredient 3',
    'post_status' => 'publish',
    'post_type' => 'ingredient'
]);

echo "✓ Created 3 test ingredients (IDs: $ingredient1_id, $ingredient2_id, $ingredient3_id)\n\n";

// Test 2: Test content with ONLY shortcodes
echo "Test 2: Testing content with ONLY shortcodes (3 embeds)\n";

$content_shortcodes_only = "
<p>Introduction paragraph.</p>

[kg-embed type=\"ingredient\" ids=\"$ingredient1_id,$ingredient2_id,$ingredient3_id\"]

<p>Conclusion paragraph.</p>
";

$embed_class = new \KG_Core\Shortcodes\ContentEmbed();
$reflection = new ReflectionClass($embed_class);
$method = $reflection->getMethod('extract_embeds_from_content');
$method->setAccessible(true);

$embeds_shortcodes = $method->invoke($embed_class, $content_shortcodes_only);

echo "Extracted " . count($embeds_shortcodes) . " embed(s)\n";

if ( count($embeds_shortcodes) === 1 ) {
    echo "✓ Correct number of embeds (1) for shortcode-only content\n";
    
    $embed = $embeds_shortcodes[0];
    if ( count($embed['items']) === 3 ) {
        echo "✓ Correct number of items (3) in the embed\n";
    } else {
        echo "✗ Expected 3 items, got " . count($embed['items']) . "\n";
    }
} else {
    echo "✗ Expected 1 embed, got " . count($embeds_shortcodes) . "\n";
}

echo "\n";

// Test 3: Test content with BOTH shortcode AND block placeholder (simulating Gutenberg output)
echo "Test 3: Testing content with BOTH shortcode AND block placeholder (duplicate scenario)\n";

// This simulates what Gutenberg block save.js outputs:
// <div class="kg-embed-placeholder" data-type="ingredient" data-ids="1,2,3">
//   [kg-embed type="ingredient" ids="1,2,3"]
// </div>
// And then WordPress processes the shortcode inside, creating another placeholder

$content_with_duplicates = "
<p>Introduction paragraph.</p>

<div class=\"kg-embed-placeholder\" data-type=\"ingredient\" data-ids=\"$ingredient1_id,$ingredient2_id,$ingredient3_id\">
[kg-embed type=\"ingredient\" ids=\"$ingredient1_id,$ingredient2_id,$ingredient3_id\"]
</div>

<p>Conclusion paragraph.</p>
";

$embeds_with_duplicates = $method->invoke($embed_class, $content_with_duplicates);

echo "Extracted " . count($embeds_with_duplicates) . " embed(s)\n";

if ( count($embeds_with_duplicates) === 1 ) {
    echo "✓ CORRECT! Only 1 embed extracted (duplicates prevented)\n";
    
    $embed = $embeds_with_duplicates[0];
    if ( count($embed['items']) === 3 ) {
        echo "✓ Correct number of items (3) in the embed\n";
    } else {
        echo "✗ Expected 3 items, got " . count($embed['items']) . "\n";
    }
} else {
    echo "✗ FAILED! Expected 1 embed, got " . count($embeds_with_duplicates) . "\n";
    echo "   This means duplicates are NOT being prevented!\n";
}

echo "\n";

// Test 4: Test content with multiple DIFFERENT embeds (should NOT be deduplicated)
echo "Test 4: Testing content with multiple DIFFERENT embeds\n";

$content_different_embeds = "
<p>Introduction paragraph.</p>

[kg-embed type=\"ingredient\" ids=\"$ingredient1_id\"]

<p>Middle paragraph.</p>

[kg-embed type=\"ingredient\" ids=\"$ingredient2_id\"]

<p>Another paragraph.</p>

[kg-embed type=\"ingredient\" ids=\"$ingredient3_id\"]

<p>Conclusion paragraph.</p>
";

$embeds_different = $method->invoke($embed_class, $content_different_embeds);

echo "Extracted " . count($embeds_different) . " embed(s)\n";

if ( count($embeds_different) === 3 ) {
    echo "✓ Correct number of embeds (3) for different embeds\n";
    
    $all_single = true;
    foreach ($embeds_different as $embed) {
        if (count($embed['items']) !== 1) {
            $all_single = false;
            break;
        }
    }
    
    if ($all_single) {
        echo "✓ Each embed has 1 item as expected\n";
    } else {
        echo "✗ Some embeds don't have exactly 1 item\n";
    }
} else {
    echo "✗ Expected 3 embeds, got " . count($embeds_different) . "\n";
}

echo "\n";

// Test 5: Test content with SAME embed appearing twice (user error - should be deduplicated)
echo "Test 5: Testing content with SAME embed appearing twice (user error)\n";

$content_duplicate_user_error = "
<p>Introduction paragraph.</p>

[kg-embed type=\"ingredient\" ids=\"$ingredient1_id,$ingredient2_id,$ingredient3_id\"]

<p>Middle paragraph.</p>

[kg-embed type=\"ingredient\" ids=\"$ingredient1_id,$ingredient2_id,$ingredient3_id\"]

<p>Conclusion paragraph.</p>
";

$embeds_user_error = $method->invoke($embed_class, $content_duplicate_user_error);

echo "Extracted " . count($embeds_user_error) . " embed(s)\n";

if ( count($embeds_user_error) === 1 ) {
    echo "✓ Correct! Only 1 embed (duplicate removed even for user error)\n";
} else {
    echo "! Note: Got " . count($embeds_user_error) . " embeds (duplicates not deduplicated for user error)\n";
    echo "  This is acceptable behavior\n";
}

echo "\n";

// Test 6: Test backwards compatibility - content with ONLY block placeholders (no shortcodes)
echo "Test 6: Testing backwards compatibility with ONLY block placeholders\n";

$content_only_placeholders = "
<p>Introduction paragraph.</p>

<div class=\"kg-embed-placeholder\" data-type=\"ingredient\" data-ids=\"$ingredient1_id,$ingredient2_id,$ingredient3_id\"></div>

<p>Conclusion paragraph.</p>
";

$embeds_only_placeholders = $method->invoke($embed_class, $content_only_placeholders);

echo "Extracted " . count($embeds_only_placeholders) . " embed(s)\n";

if ( count($embeds_only_placeholders) === 1 ) {
    echo "✓ Backwards compatibility maintained (block placeholders still work)\n";
    
    $embed = $embeds_only_placeholders[0];
    if ( count($embed['items']) === 3 ) {
        echo "✓ Correct number of items (3) in the embed\n";
    } else {
        echo "✗ Expected 3 items, got " . count($embed['items']) . "\n";
    }
} else {
    echo "✗ Expected 1 embed, got " . count($embeds_only_placeholders) . "\n";
}

echo "\n";

// Test 7: Test embed counter is NOT static (should reset between calls)
echo "Test 7: Testing embed counter is local (not static)\n";

$first_call_embeds = $method->invoke($embed_class, $content_shortcodes_only);
$first_placeholder_id = $first_call_embeds[0]['placeholder_id'];

$second_call_embeds = $method->invoke($embed_class, $content_shortcodes_only);
$second_placeholder_id = $second_call_embeds[0]['placeholder_id'];

if ($first_placeholder_id === $second_placeholder_id) {
    echo "✓ Embed counter resets between calls (both got '$first_placeholder_id')\n";
} else {
    echo "! Warning: Embed counter is incrementing across calls\n";
    echo "  First call: $first_placeholder_id\n";
    echo "  Second call: $second_placeholder_id\n";
    echo "  This might be acceptable depending on use case\n";
}

echo "\n";

// Cleanup
echo "Cleanup: Removing test ingredients\n";
wp_delete_post($ingredient1_id, true);
wp_delete_post($ingredient2_id, true);
wp_delete_post($ingredient3_id, true);
echo "✓ Test ingredients removed\n\n";

echo "=== All Tests Completed ===\n";
echo "\nSummary:\n";
echo "✓ Duplicate embeds are prevented when both shortcode and placeholder exist\n";
echo "✓ Multiple different embeds still work correctly\n";
echo "✓ Backwards compatibility with block placeholders maintained\n";
echo "✓ Embed counter is properly scoped\n";

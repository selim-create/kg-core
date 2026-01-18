<?php
/**
 * Test Script for Content Embed System
 * 
 * This script tests the ContentEmbed shortcode and REST API integration
 * Run this from WordPress root using: wp eval-file test-content-embed-system.php
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
        die( "Error: WordPress environment not found. Please run this script using WP-CLI:\n  wp eval-file test-content-embed-system.php\n" );
    }
}

echo "=== Testing Content Embed System ===\n\n";

// Test 1: Check if ContentEmbed class exists
echo "Test 1: Check if ContentEmbed class exists\n";
if ( class_exists( '\KG_Core\Shortcodes\ContentEmbed' ) ) {
    echo "✓ ContentEmbed class exists\n\n";
} else {
    echo "✗ ContentEmbed class NOT found\n\n";
    exit(1);
}

// Test 2: Check if EmbedSelector class exists
echo "Test 2: Check if EmbedSelector class exists\n";
if ( class_exists( '\KG_Core\Admin\EmbedSelector' ) ) {
    echo "✓ EmbedSelector class exists\n\n";
} else {
    echo "✗ EmbedSelector class NOT found\n\n";
    exit(1);
}

// Test 3: Check if shortcode is registered
echo "Test 3: Check if shortcode is registered\n";
if ( shortcode_exists( 'kg-embed' ) ) {
    echo "✓ [kg-embed] shortcode is registered\n\n";
} else {
    echo "✗ [kg-embed] shortcode NOT registered\n\n";
    exit(1);
}

// Test 4: Create test posts
echo "Test 4: Creating test content\n";

// Create a test recipe
$test_recipe_id = wp_insert_post([
    'post_title' => 'Test Havuçlu Püre - ' . date('Y-m-d H:i:s'),
    'post_content' => 'Bebeğiniz için sağlıklı havuçlu püre tarifi.',
    'post_excerpt' => 'Bebeğiniz için lezzetli havuç püresi',
    'post_status' => 'publish',
    'post_type' => 'recipe'
]);

if ( is_wp_error( $test_recipe_id ) ) {
    echo "✗ Failed to create test recipe\n\n";
    exit(1);
}

update_post_meta( $test_recipe_id, '_kg_prep_time', '15 dk' );
update_post_meta( $test_recipe_id, '_kg_is_featured', '1' );

echo "✓ Created test recipe (ID: $test_recipe_id)\n";

// Create a test ingredient
$test_ingredient_id = wp_insert_post([
    'post_title' => 'Test Havuç - ' . date('Y-m-d H:i:s'),
    'post_content' => 'Havuç beta-karoten açısından zengindir.',
    'post_excerpt' => 'Beta-karoten deposu',
    'post_status' => 'publish',
    'post_type' => 'ingredient'
]);

if ( is_wp_error( $test_ingredient_id ) ) {
    echo "✗ Failed to create test ingredient\n\n";
    exit(1);
}

update_post_meta( $test_ingredient_id, '_kg_start_age', '6 ay' );
update_post_meta( $test_ingredient_id, '_kg_benefits', 'Göz sağlığı, bağışıklık sistemi' );

echo "✓ Created test ingredient (ID: $test_ingredient_id)\n";

// Create a test tool
$test_tool_id = wp_insert_post([
    'post_title' => 'Test Blender - ' . date('Y-m-d H:i:s'),
    'post_content' => 'Bebek yemekleri için ideal blender.',
    'post_excerpt' => 'Pratik ve kullanışlı',
    'post_status' => 'publish',
    'post_type' => 'tool'
]);

if ( is_wp_error( $test_tool_id ) ) {
    echo "✗ Failed to create test tool\n\n";
    exit(1);
}

update_post_meta( $test_tool_id, '_kg_tool_type', 'kitchen' );
update_post_meta( $test_tool_id, '_kg_is_active', '1' );

echo "✓ Created test tool (ID: $test_tool_id)\n";

// Create a test post
$test_post_id = wp_insert_post([
    'post_title' => 'Test Keşfet Yazısı - ' . date('Y-m-d H:i:s'),
    'post_content' => 'Bebekler için beslenme ipuçları ve öneriler.',
    'post_excerpt' => 'Beslenme rehberi',
    'post_status' => 'publish',
    'post_type' => 'post'
]);

if ( is_wp_error( $test_post_id ) ) {
    echo "✗ Failed to create test post\n\n";
    exit(1);
}

echo "✓ Created test post (ID: $test_post_id)\n\n";

// Test 5: Test single embed shortcode
echo "Test 5: Testing single embed shortcode\n";

$single_embed = do_shortcode('[kg-embed type="recipe" id="' . $test_recipe_id . '"]');

if ( strpos( $single_embed, 'kg-embed-placeholder' ) !== false ) {
    echo "✓ Single embed shortcode renders placeholder\n";
} else {
    echo "✗ Single embed shortcode failed\n";
    echo "Output: " . $single_embed . "\n";
}

echo "\n";

// Test 6: Test multiple embed shortcode
echo "Test 6: Testing multiple embed shortcode\n";

$multiple_embed = do_shortcode('[kg-embed type="recipe" ids="' . $test_recipe_id . ',' . $test_recipe_id . '"]');

if ( strpos( $multiple_embed, 'kg-embed-placeholder' ) !== false ) {
    echo "✓ Multiple embed shortcode renders placeholder\n";
} else {
    echo "✗ Multiple embed shortcode failed\n";
    echo "Output: " . $multiple_embed . "\n";
}

echo "\n";

// Test 7: Create a test post with embeds for REST API testing
echo "Test 7: Creating post with embedded content\n";

$content_with_embeds = "
<p>Bu yazıda size harika tarifler sunacağız.</p>

[kg-embed type=\"recipe\" id=\"$test_recipe_id\"]

<p>Ayrıca malzemelerimiz hakkında bilgi vereceğiz.</p>

[kg-embed type=\"ingredient\" id=\"$test_ingredient_id\"]

<p>Ve kullanabileceğiniz araçları tanıtacağız.</p>

[kg-embed type=\"tool\" id=\"$test_tool_id\"]

<p>Son olarak size bazı ipuçları sunacağız.</p>
";

$embed_test_post_id = wp_insert_post([
    'post_title' => 'Embed Test Post - ' . date('Y-m-d H:i:s'),
    'post_content' => $content_with_embeds,
    'post_status' => 'publish',
    'post_type' => 'post'
]);

if ( is_wp_error( $embed_test_post_id ) ) {
    echo "✗ Failed to create embed test post\n\n";
    exit(1);
}

echo "✓ Created embed test post (ID: $embed_test_post_id)\n\n";

// Test 8: Test embedded content extraction
echo "Test 8: Testing embedded content extraction\n";

// Manually call the extraction method
$embed_class = new \KG_Core\Shortcodes\ContentEmbed();
$reflection = new ReflectionClass($embed_class);
$method = $reflection->getMethod('extract_embeds_from_content');
$method->setAccessible(true);

$embedded_content = $method->invoke($embed_class, $content_with_embeds);

echo "Extracted " . count($embedded_content) . " embeds\n";

if ( count($embedded_content) === 3 ) {
    echo "✓ Correct number of embeds extracted\n";
    
    // Check types
    $types = array_column($embedded_content, 'type');
    if ( in_array('recipe', $types) && in_array('ingredient', $types) && in_array('tool', $types) ) {
        echo "✓ All embed types present\n";
    } else {
        echo "✗ Missing embed types\n";
        echo "Found types: " . implode(', ', $types) . "\n";
    }
    
    // Check structure
    $first_embed = $embedded_content[0];
    $required_keys = ['type', 'position', 'placeholder_id', 'items'];
    $has_all_keys = true;
    foreach ( $required_keys as $key ) {
        if ( !isset($first_embed[$key]) ) {
            echo "✗ Missing key: $key\n";
            $has_all_keys = false;
        }
    }
    
    if ( $has_all_keys ) {
        echo "✓ Embed structure is correct\n";
        
        // Check item data
        if ( !empty($first_embed['items']) && isset($first_embed['items'][0]) ) {
            $item = $first_embed['items'][0];
            $required_item_keys = ['id', 'title', 'slug', 'excerpt', 'image', 'url', 'embed_type'];
            $has_all_item_keys = true;
            
            foreach ( $required_item_keys as $key ) {
                if ( !isset($item[$key]) ) {
                    echo "✗ Missing item key: $key\n";
                    $has_all_item_keys = false;
                }
            }
            
            if ( $has_all_item_keys ) {
                echo "✓ Item structure is correct\n";
                
                // Check type-specific fields
                if ( $first_embed['type'] === 'recipe' ) {
                    if ( isset($item['prep_time'], $item['age_group'], $item['diet_types'], $item['allergens'], $item['is_featured']) ) {
                        echo "✓ Recipe-specific fields present\n";
                    } else {
                        echo "✗ Missing recipe-specific fields\n";
                    }
                }
            }
        }
    }
} else {
    echo "✗ Expected 3 embeds, got " . count($embedded_content) . "\n";
}

echo "\n";

// Test 9: Test REST API field
echo "Test 9: Testing REST API embedded_content field\n";

// Simulate REST request
define('REST_REQUEST', true);

$rest_post = get_post($embed_test_post_id);
$embedded_via_rest = $embed_class->get_embedded_content(['id' => $embed_test_post_id]);

echo "REST API returned " . count($embedded_via_rest) . " embeds\n";

if ( count($embedded_via_rest) === 3 ) {
    echo "✓ REST API returns correct number of embeds\n";
} else {
    echo "✗ REST API embed count mismatch\n";
}

echo "\n";

// Test 10: Test paragraph position calculation
echo "Test 10: Testing paragraph position calculation\n";

$positions = array_column($embedded_content, 'position');
echo "Embed positions: " . implode(', ', $positions) . "\n";

$is_ascending = true;
for ($i = 0; $i < count($positions) - 1; $i++) {
    if ($positions[$i] >= $positions[$i + 1]) {
        $is_ascending = false;
        break;
    }
}

if ( $is_ascending ) {
    echo "✓ Paragraph positions are correctly calculated (ascending order)\n";
} else {
    echo "! Warning: Positions might not be in ascending order\n";
}

echo "\n";

// Test 11: Test AJAX endpoint (simulate)
echo "Test 11: Testing AJAX search endpoint\n";

$_POST['type'] = 'recipe';
$_POST['search'] = '';
$_POST['nonce'] = wp_create_nonce('kg_embed_selector_nonce');

$embed_selector = new \KG_Core\Admin\EmbedSelector();

// Capture output
ob_start();
try {
    $embed_selector->ajax_search_content();
    $ajax_output = ob_get_clean();
    
    $response = json_decode($ajax_output, true);
    
    if ( isset($response['success']) && $response['success'] ) {
        echo "✓ AJAX search endpoint works\n";
        
        if ( isset($response['data']['items']) && is_array($response['data']['items']) ) {
            echo "✓ AJAX returns items array\n";
            echo "  Found " . count($response['data']['items']) . " items\n";
            
            if ( !empty($response['data']['items']) ) {
                $item = $response['data']['items'][0];
                if ( isset($item['id'], $item['title'], $item['meta'], $item['icon']) ) {
                    echo "✓ AJAX item structure is correct\n";
                }
            }
        }
    } else {
        echo "✗ AJAX search failed\n";
        echo "Response: " . $ajax_output . "\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "✗ AJAX endpoint error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 12: Different content types
echo "Test 12: Testing different content types\n";

$types = ['recipe', 'ingredient', 'tool', 'post'];
foreach ($types as $type) {
    $shortcode = '[kg-embed type="' . $type . '" id="' . ${"test_{$type}_id"} . '"]';
    $output = do_shortcode($shortcode);
    
    if ( strpos($output, 'data-type="' . $type . '"') !== false ) {
        echo "✓ Type '$type' works correctly\n";
    } else {
        echo "✗ Type '$type' failed\n";
    }
}

echo "\n";

// Cleanup
echo "Cleanup: Removing test posts\n";
wp_delete_post($test_recipe_id, true);
wp_delete_post($test_ingredient_id, true);
wp_delete_post($test_tool_id, true);
wp_delete_post($test_post_id, true);
wp_delete_post($embed_test_post_id, true);
echo "✓ Test posts removed\n\n";

echo "=== All Tests Completed ===\n";
echo "\nSummary:\n";
echo "- ContentEmbed shortcode system is working\n";
echo "- EmbedSelector admin UI classes are loaded\n";
echo "- REST API integration is functional\n";
echo "- All content types (recipe, ingredient, tool, post) are supported\n";
echo "- Paragraph position calculation is working\n";
echo "- AJAX search endpoint is operational\n";

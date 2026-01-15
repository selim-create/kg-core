<?php
/**
 * Integration Test for Tool Seeder and API Endpoints
 * 
 * This test:
 * 1. Seeds all tools using ToolSeeder
 * 2. Verifies tools are created correctly
 * 3. Tests API endpoints work with seeded tools
 * 4. Verifies sponsor_data is null for non-sponsored tools
 */

// Load WordPress
require_once dirname(__DIR__) . '/kg-core.php';

echo "=== Tool Seeder Integration Test ===\n\n";

$passed = 0;
$failed = 0;

// Test 1: Seed all tools
echo "1. Seeding Tools\n";
try {
    if (class_exists('\KG_Core\Admin\ToolSeeder')) {
        \KG_Core\Admin\ToolSeeder::seed_on_activation();
        echo "   ✓ ToolSeeder::seed_on_activation() executed\n";
        $passed++;
    } else {
        echo "   ✗ ToolSeeder class not found\n";
        $failed++;
    }
} catch (Exception $e) {
    echo "   ✗ Error seeding tools: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 2: Verify tools were created
echo "\n2. Verifying Created Tools\n";
$requiredTools = [
    'bath-planner' => 'bath_planner',
    'hygiene-calculator' => 'hygiene_calculator',
    'diaper-calculator' => 'diaper_calculator',
    'air-quality' => 'air_quality_guide',
    'stain-encyclopedia' => 'stain_encyclopedia',
    'blw-testi' => 'blw_test',
    'persentil' => 'percentile',
    'su-ihtiyaci' => 'water_calculator',
    'ek-gida-rehberi' => 'food_guide',
    'ek-gidaya-baslama' => 'solid_food_readiness',
    'bu-gida-verilir-mi' => 'food_checker',
    'alerjen-planlayici' => 'allergen_planner',
    'besin-takvimi' => 'food_trial_calendar',
];

$createdTools = [];
foreach ($requiredTools as $slug => $toolType) {
    $args = [
        'post_type' => 'tool',
        'name' => $slug,
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => 1,
    ];
    
    $posts = get_posts($args);
    
    if (!empty($posts)) {
        $post = $posts[0];
        $createdTools[$slug] = $post;
        
        echo "   ✓ $slug created (ID: {$post->ID}, Status: {$post->post_status})\n";
        $passed++;
        
        // Verify meta data
        $saved_tool_type = get_post_meta($post->ID, '_kg_tool_type', true);
        if ($saved_tool_type === $toolType) {
            echo "      ✓ _kg_tool_type = $toolType\n";
            $passed++;
        } else {
            echo "      ✗ _kg_tool_type mismatch (expected: $toolType, got: $saved_tool_type)\n";
            $failed++;
        }
        
        // Verify is_sponsored is false/0 (should not be '1')
        $is_sponsored = get_post_meta($post->ID, '_kg_tool_is_sponsored', true);
        if ($is_sponsored !== '1') {
            echo "      ✓ _kg_tool_is_sponsored != '1' (not sponsored)\n";
            $passed++;
        } else {
            echo "      ✗ _kg_tool_is_sponsored should not be '1' (got: $is_sponsored)\n";
            $failed++;
        }
    } else {
        echo "   ✗ $slug not found\n";
        $failed += 3;
    }
}

// Test 3: Verify no duplicate tools were created
echo "\n3. Checking for Duplicates\n";
foreach ($requiredTools as $slug => $toolType) {
    $args = [
        'post_type' => 'tool',
        'name' => $slug,
        'post_status' => 'any',
        'posts_per_page' => -1,
    ];
    
    $posts = get_posts($args);
    
    if (count($posts) === 1) {
        echo "   ✓ $slug - No duplicates (1 post)\n";
        $passed++;
    } else {
        echo "   ✗ $slug - Found " . count($posts) . " posts (expected 1)\n";
        $failed++;
    }
}

// Test 4: Test API endpoint accessibility (simulate)
echo "\n4. Testing API Endpoint Logic\n";

// Test bath-planner endpoint logic
if (isset($createdTools['bath-planner'])) {
    $tool = $createdTools['bath-planner'];
    
    // Simulate what SponsoredToolController does
    $is_sponsored = get_post_meta($tool->ID, '_kg_tool_is_sponsored', true);
    
    if ($is_sponsored !== '1') {
        $sponsor_data = null;
    } else {
        // Would build sponsor data here
        $sponsor_data = ['is_sponsored' => true];
    }
    
    if ($sponsor_data === null) {
        echo "   ✓ bath-planner - sponsor_data is null (not sponsored)\n";
        $passed++;
    } else {
        echo "   ✗ bath-planner - sponsor_data should be null\n";
        $failed++;
    }
}

// Test hygiene-calculator endpoint logic
if (isset($createdTools['hygiene-calculator'])) {
    $tool = $createdTools['hygiene-calculator'];
    
    $is_sponsored = get_post_meta($tool->ID, '_kg_tool_is_sponsored', true);
    
    if ($is_sponsored !== '1') {
        $sponsor_data = null;
    } else {
        $sponsor_data = ['is_sponsored' => true];
    }
    
    if ($sponsor_data === null) {
        echo "   ✓ hygiene-calculator - sponsor_data is null (not sponsored)\n";
        $passed++;
    } else {
        echo "   ✗ hygiene-calculator - sponsor_data should be null\n";
        $failed++;
    }
}

// Test 5: Test REST API field registration
echo "\n5. Testing REST API sponsor_data Field\n";

// Check if rest_api_init action has the sponsor_data registration
global $wp_filter;
if (isset($wp_filter['rest_api_init'])) {
    echo "   ✓ rest_api_init hook exists\n";
    $passed++;
    
    // The actual registration happens in kg-core.php around line 336-378
    // We can verify by checking if the code exists in the file
    $coreFile = dirname(__DIR__) . '/kg-core.php';
    $content = file_get_contents($coreFile);
    
    if (strpos($content, "register_rest_field( 'tool', 'sponsor_data'") !== false) {
        echo "   ✓ sponsor_data REST field registered for 'tool' post type\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_data REST field not registered for 'tool' post type\n";
        $failed++;
    }
} else {
    echo "   ✗ rest_api_init hook not found\n";
    $failed += 2;
}

// Test 6: Verify all required meta fields are saved
echo "\n6. Verifying All Meta Fields\n";
$requiredMetaFields = [
    '_kg_tool_type',
    '_kg_tool_icon',
    '_kg_is_active',
    '_kg_requires_auth',
    '_kg_tool_is_sponsored',
];

if (isset($createdTools['bath-planner'])) {
    $tool = $createdTools['bath-planner'];
    
    foreach ($requiredMetaFields as $field) {
        $value = get_post_meta($tool->ID, $field, true);
        
        if ($value !== '' && $value !== false) {
            echo "   ✓ $field is set (value: $value)\n";
            $passed++;
        } else {
            echo "   ✗ $field is not set\n";
            $failed++;
        }
    }
}

// Test 7: Test icon field values
echo "\n7. Verifying Icon Fields\n";
$expectedIcons = [
    'bath-planner' => 'fa-bath',
    'hygiene-calculator' => 'fa-hand-sparkles',
    'diaper-calculator' => 'fa-baby',
    'air-quality' => 'fa-wind',
    'stain-encyclopedia' => 'fa-tshirt',
];

foreach ($expectedIcons as $slug => $expectedIcon) {
    if (isset($createdTools[$slug])) {
        $tool = $createdTools[$slug];
        $icon = get_post_meta($tool->ID, '_kg_tool_icon', true);
        
        if ($icon === $expectedIcon) {
            echo "   ✓ $slug - icon is $expectedIcon\n";
            $passed++;
        } else {
            echo "   ✗ $slug - icon mismatch (expected: $expectedIcon, got: $icon)\n";
            $failed++;
        }
    }
}

// Test 8: Verify published status
echo "\n8. Verifying Published Status\n";
foreach ($requiredTools as $slug => $toolType) {
    if (isset($createdTools[$slug])) {
        $tool = $createdTools[$slug];
        
        if ($tool->post_status === 'publish') {
            echo "   ✓ $slug is published\n";
            $passed++;
        } else {
            echo "   ✗ $slug is not published (status: {$tool->post_status})\n";
            $failed++;
        }
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";
echo str_repeat("=", 50) . "\n";

if ($failed === 0) {
    echo "✅ ALL INTEGRATION TESTS PASSED!\n";
    echo "\n📊 Created Tools Summary:\n";
    foreach ($createdTools as $slug => $tool) {
        echo "  - {$tool->post_title} (ID: {$tool->ID})\n";
    }
    exit(0);
} else {
    echo "❌ SOME INTEGRATION TESTS FAILED\n";
    exit(1);
}

<?php
/**
 * Test for Sponsored Tools Backend Infrastructure
 * 
 * This test verifies:
 * 1. Tool post type has new tool_type choices
 * 2. ToolSponsorMetaBox exists and handles sponsor fields
 * 3. SponsoredToolController exists with all required endpoints
 * 4. kg-core.php includes and initializes new classes
 */

echo "=== Sponsored Tools Backend Infrastructure Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check Tool.php for new tool types
echo "1. Tool Post Type - New Tool Types\n";
$toolFile = $baseDir . '/includes/PostTypes/Tool.php';
if (file_exists($toolFile)) {
    $content = file_get_contents($toolFile);
    
    $newToolTypes = ['bath_planner', 'hygiene_calculator', 'diaper_calculator', 'air_quality_guide', 'stain_encyclopedia'];
    foreach ($newToolTypes as $toolType) {
        if (strpos($content, "'" . $toolType . "'") !== false) {
            echo "   ✓ $toolType added to choices\n";
            $passed++;
        } else {
            echo "   ✗ $toolType not found in choices\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ Tool.php file not found\n";
    $failed += 5;
}

// Test 2: Check ToolSponsorMetaBox.php exists and has required fields
echo "\n2. ToolSponsorMetaBox - Meta Fields\n";
$metaBoxFile = $baseDir . '/includes/Admin/ToolSponsorMetaBox.php';
if (file_exists($metaBoxFile)) {
    echo "   ✓ ToolSponsorMetaBox.php exists\n";
    $passed++;
    
    $content = file_get_contents($metaBoxFile);
    
    $requiredFields = [
        '_kg_tool_is_sponsored',
        '_kg_tool_sponsor_name',
        '_kg_tool_sponsor_url',
        '_kg_tool_sponsor_logo',
        '_kg_tool_sponsor_light_logo',
        '_kg_tool_sponsor_tagline',
        '_kg_tool_sponsor_cta_text',
        '_kg_tool_sponsor_cta_url',
        '_kg_tool_gam_impression_url',
        '_kg_tool_gam_click_url'
    ];
    
    foreach ($requiredFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ $field meta field handled\n";
            $passed++;
        } else {
            echo "   ✗ $field meta field not found\n";
            $failed++;
        }
    }
    
    // Check if meta box is registered for 'tool' post type
    if (strpos($content, "'tool'") !== false) {
        echo "   ✓ Meta box registered for 'tool' post type\n";
        $passed++;
    } else {
        echo "   ✗ Meta box not registered for 'tool' post type\n";
        $failed++;
    }
} else {
    echo "   ✗ ToolSponsorMetaBox.php file not found\n";
    $failed += 12;
}

// Test 3: Check SponsoredToolController.php exists and has required endpoints
echo "\n3. SponsoredToolController - API Endpoints\n";
$controllerFile = $baseDir . '/includes/API/SponsoredToolController.php';
if (file_exists($controllerFile)) {
    echo "   ✓ SponsoredToolController.php exists\n";
    $passed++;
    
    $content = file_get_contents($controllerFile);
    
    $requiredEndpoints = [
        '/tools/bath-planner/config',
        '/tools/bath-planner/generate',
        '/tools/hygiene-calculator/calculate',
        '/tools/diaper-calculator/calculate',
        '/tools/diaper-calculator/rash-risk',
        '/tools/air-quality/analyze',
        '/tools/stain-encyclopedia/search',
        '/tools/stain-encyclopedia/(?P<slug>[a-zA-Z0-9_-]+)'
    ];
    
    foreach ($requiredEndpoints as $endpoint) {
        if (strpos($content, $endpoint) !== false) {
            echo "   ✓ $endpoint endpoint registered\n";
            $passed++;
        } else {
            echo "   ✗ $endpoint endpoint not found\n";
            $failed++;
        }
    }
    
    // Check for sponsor data helper method
    if (strpos($content, 'get_sponsor_data') !== false) {
        echo "   ✓ get_sponsor_data helper method exists\n";
        $passed++;
    } else {
        echo "   ✗ get_sponsor_data helper method not found\n";
        $failed++;
    }
} else {
    echo "   ✗ SponsoredToolController.php file not found\n";
    $failed += 10;
}

// Test 4: Check kg-core.php includes and initializes new classes
echo "\n4. kg-core.php - Includes and Initialization\n";
$coreFile = $baseDir . '/kg-core.php';
if (file_exists($coreFile)) {
    $content = file_get_contents($coreFile);
    
    // Check ToolSponsorMetaBox include
    if (strpos($content, "includes/Admin/ToolSponsorMetaBox.php") !== false) {
        echo "   ✓ ToolSponsorMetaBox.php included\n";
        $passed++;
    } else {
        echo "   ✗ ToolSponsorMetaBox.php not included\n";
        $failed++;
    }
    
    // Check SponsoredToolController include
    if (strpos($content, "includes/API/SponsoredToolController.php") !== false) {
        echo "   ✓ SponsoredToolController.php included\n";
        $passed++;
    } else {
        echo "   ✗ SponsoredToolController.php not included\n";
        $failed++;
    }
    
    // Check ToolSponsorMetaBox initialization
    if (strpos($content, "new \KG_Core\Admin\ToolSponsorMetaBox()") !== false) {
        echo "   ✓ ToolSponsorMetaBox initialized\n";
        $passed++;
    } else {
        echo "   ✗ ToolSponsorMetaBox not initialized\n";
        $failed++;
    }
    
    // Check SponsoredToolController initialization
    if (strpos($content, "new \KG_Core\API\SponsoredToolController()") !== false) {
        echo "   ✓ SponsoredToolController initialized\n";
        $passed++;
    } else {
        echo "   ✗ SponsoredToolController not initialized\n";
        $failed++;
    }
    
    // Check admin assets enqueue for tool post type
    if (strpos($content, "\$post_type === 'tool'") !== false) {
        echo "   ✓ Admin assets enqueue for 'tool' post type\n";
        $passed++;
    } else {
        echo "   ✗ Admin assets enqueue for 'tool' post type not found\n";
        $failed++;
    }
    
    // Check REST API field for tool sponsor_data
    if (strpos($content, "register_rest_field( 'tool', 'sponsor_data'") !== false) {
        echo "   ✓ REST API field 'sponsor_data' registered for 'tool'\n";
        $passed++;
    } else {
        echo "   ✗ REST API field 'sponsor_data' not registered for 'tool'\n";
        $failed++;
    }
} else {
    echo "   ✗ kg-core.php file not found\n";
    $failed += 6;
}

// Test 5: Syntax check
echo "\n5. PHP Syntax Check\n";
$files = [
    'includes/PostTypes/Tool.php',
    'includes/Admin/ToolSponsorMetaBox.php',
    'includes/API/SponsoredToolController.php',
    'kg-core.php'
];

foreach ($files as $file) {
    $fullPath = $baseDir . '/' . $file;
    if (file_exists($fullPath)) {
        exec("php -l " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
        if ($returnCode === 0) {
            echo "   ✓ $file has no syntax errors\n";
            $passed++;
        } else {
            echo "   ✗ $file has syntax errors\n";
            $failed++;
        }
    }
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed > 0) {
    echo "\nResult: FAILED ❌\n";
    exit(1);
} else {
    echo "\nResult: PASSED ✅\n";
    exit(0);
}

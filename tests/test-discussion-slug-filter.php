<?php
/**
 * Static Code Analysis Test for Discussion Slug Filter
 * 
 * This test verifies the implementation of slug filter in DiscussionController
 */

echo "=== KG Core Discussion Slug Filter Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check DiscussionController file exists
echo "1. DiscussionController File Check\n";
$controllerFile = $baseDir . '/includes/API/DiscussionController.php';
if (file_exists($controllerFile)) {
    echo "   ✓ File exists: DiscussionController.php\n";
    $passed++;
    
    $content = file_get_contents($controllerFile);
    
    // Test 2: Check if get_discussions method exists
    if (strpos($content, 'function get_discussions') !== false) {
        echo "   ✓ Method exists: get_discussions()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: get_discussions()\n";
        $failed++;
    }
    
    // Test 3: Check if slug parameter is retrieved
    if (preg_match("/\\\$slug\s*=.*get_param\s*\(\s*['\"]slug['\"]\s*\)/", $content)) {
        echo "   ✓ Slug parameter is retrieved from request\n";
        $passed++;
    } else {
        echo "   ✗ Slug parameter not retrieved from request\n";
        $failed++;
    }
    
    // Test 4: Check if slug is sanitized
    if (strpos($content, 'sanitize_title') !== false && strpos($content, '$slug') !== false) {
        echo "   ✓ Slug is sanitized with sanitize_title()\n";
        $passed++;
    } else {
        echo "   ✗ Slug sanitization not found\n";
        $failed++;
    }
    
    // Test 5: Check if WP_Query uses 'name' parameter for slug filtering
    if (preg_match("/if\s*\(\s*\\\$slug\s*\)/", $content) && 
        preg_match("/\['name'\]\s*=/", $content)) {
        echo "   ✓ WP_Query 'name' parameter used for slug filtering\n";
        $passed++;
    } else {
        echo "   ✗ WP_Query 'name' parameter not found for slug filtering\n";
        $failed++;
    }
    
    // Test 6: Check if posts_per_page is set to 1 when slug is provided
    if (preg_match("/if\s*\(\s*\\\$slug\s*\).*posts_per_page.*1/s", $content)) {
        echo "   ✓ posts_per_page set to 1 when slug is provided\n";
        $passed++;
    } else {
        echo "   ✗ posts_per_page not set to 1 for slug filter\n";
        $failed++;
    }
    
} else {
    echo "   ✗ File not found: DiscussionController.php\n";
    $failed++;
}

echo "\n";

// Test 7: Check URL patterns are updated correctly
echo "2. URL Pattern Verification\n";

$filesToCheck = [
    'includes/Admin/FrontendViewLinks.php' => 'FrontendViewLinks',
    'includes/Redirect/FrontendRedirect.php' => 'FrontendRedirect (includes)',
    'Redirect/FrontendRedirect.php' => 'FrontendRedirect (root)',
    'includes/API/LookupController.php' => 'LookupController',
];

foreach ($filesToCheck as $file => $name) {
    $fullPath = $baseDir . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        
        // Check if it has the correct pattern /topluluk (not /topluluk/soru)
        if (preg_match("/['\"]discussion['\"]\s*=>\s*['\"](\/topluluk)['\"](?!\/soru)/", $content)) {
            echo "   ✓ $name uses correct pattern: /topluluk\n";
            $passed++;
        } elseif (strpos($content, '/topluluk/soru') !== false) {
            echo "   ✗ $name still uses old pattern: /topluluk/soru\n";
            $failed++;
        } else {
            echo "   ~ $name: pattern not applicable or not found\n";
        }
    } else {
        echo "   ~ $name: file not found\n";
    }
}

echo "\n";

// Test 8: Check documentation is updated
echo "3. Documentation Update Verification\n";

$docFiles = [
    'docs/SLUG_LOOKUP_QUICKSTART.md' => 'Quick Start Guide',
    'docs/SLUG_LOOKUP_IMPLEMENTATION.md' => 'Implementation Guide',
];

foreach ($docFiles as $file => $name) {
    $fullPath = $baseDir . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        
        // Check if documentation uses the correct pattern
        if (preg_match("/Discussion.*\/topluluk\/\{slug\}/", $content)) {
            echo "   ✓ $name uses correct pattern: /topluluk/{slug}\n";
            $passed++;
        } elseif (preg_match("/Discussion.*\/topluluk\/soru/", $content)) {
            echo "   ✗ $name still uses old pattern: /topluluk/soru/{slug}\n";
            $failed++;
        } else {
            echo "   ~ $name: pattern not found\n";
        }
    } else {
        echo "   ~ $name: file not found\n";
    }
}

echo "\n";
echo "=== Test Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✅ All tests passed!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed!\n";
    exit(1);
}

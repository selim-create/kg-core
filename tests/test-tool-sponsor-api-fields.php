<?php
/**
 * Test for Tool API Sponsor Fields
 * 
 * This test verifies that:
 * 1. get_tools() method returns sponsor fields (is_sponsored, sponsor_name, sponsor_url)
 * 2. get_tool() method returns sponsor fields (is_sponsored, sponsor_name, sponsor_url)
 * 3. Fields are properly formatted (boolean for is_sponsored, null for empty strings)
 */

echo "=== Tool API Sponsor Fields Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check ToolController.php exists
echo "1. ToolController File Check\n";
$toolControllerFile = $baseDir . '/includes/API/ToolController.php';
if (file_exists($toolControllerFile)) {
    echo "   ✓ ToolController.php exists\n";
    $passed++;
} else {
    echo "   ✗ ToolController.php not found\n";
    $failed++;
    exit(1);
}

// Test 2: Check get_tools() method includes sponsor fields
echo "\n2. get_tools() Method - Sponsor Fields\n";
$content = file_get_contents($toolControllerFile);

// Just check the full content for sponsor fields in get_tools area
// Extract method by finding function declaration and its closing
if (preg_match('/public function get_tools.*?^\s*public function get_tool\(/ms', $content, $matches)) {
    $methodContent = $matches[0];
    
    // Check for is_sponsored field
    if (strpos($methodContent, "'is_sponsored'") !== false) {
        echo "   ✓ is_sponsored field added to response\n";
        $passed++;
    } else {
        echo "   ✗ is_sponsored field not found in response\n";
        $failed++;
    }
    
    // Check for sponsor_name field
    if (strpos($methodContent, "'sponsor_name'") !== false) {
        echo "   ✓ sponsor_name field added to response\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_name field not found in response\n";
        $failed++;
    }
    
    // Check for sponsor_url field
    if (strpos($methodContent, "'sponsor_url'") !== false) {
        echo "   ✓ sponsor_url field added to response\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_url field not found in response\n";
        $failed++;
    }
    
    // Check is_sponsored is cast to boolean
    if (preg_match("/'is_sponsored'\s*=>\s*\(bool\)/", $methodContent)) {
        echo "   ✓ is_sponsored is cast to boolean\n";
        $passed++;
    } else {
        echo "   ✗ is_sponsored is not cast to boolean\n";
        $failed++;
    }
    
    // Check sponsor_name uses ?: null for empty values
    if (preg_match("/'sponsor_name'.*\?:\s*null/", $methodContent)) {
        echo "   ✓ sponsor_name uses ?: null for empty values\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_name doesn't use ?: null for empty values\n";
        $failed++;
    }
    
    // Check sponsor_url uses ?: null for empty values
    if (preg_match("/'sponsor_url'.*\?:\s*null/", $methodContent)) {
        echo "   ✓ sponsor_url uses ?: null for empty values\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_url doesn't use ?: null for empty values\n";
        $failed++;
    }
    
    // Check correct meta field names are used
    if (strpos($methodContent, '_kg_tool_is_sponsored') !== false) {
        echo "   ✓ Correct meta field '_kg_tool_is_sponsored' used\n";
        $passed++;
    } else {
        echo "   ✗ Meta field '_kg_tool_is_sponsored' not found\n";
        $failed++;
    }
    
    if (strpos($methodContent, '_kg_tool_sponsor_name') !== false) {
        echo "   ✓ Correct meta field '_kg_tool_sponsor_name' used\n";
        $passed++;
    } else {
        echo "   ✗ Meta field '_kg_tool_sponsor_name' not found\n";
        $failed++;
    }
    
    if (strpos($methodContent, '_kg_tool_sponsor_url') !== false) {
        echo "   ✓ Correct meta field '_kg_tool_sponsor_url' used\n";
        $passed++;
    } else {
        echo "   ✗ Meta field '_kg_tool_sponsor_url' not found\n";
        $failed++;
    }
} else {
    echo "   ✗ get_tools() method not found or cannot be parsed\n";
    $failed += 10;
}

// Test 3: Check get_tool() method includes sponsor fields
echo "\n3. get_tool() Method - Sponsor Fields\n";

// Find get_tool method (single tool) - extract from declaration to next public function
if (preg_match('/public function get_tool\(.*?^\s*public function get_blw_test_config\(/ms', $content, $matches)) {
    $methodContent = $matches[0];
    
    // Check for is_sponsored field
    if (strpos($methodContent, "'is_sponsored'") !== false) {
        echo "   ✓ is_sponsored field added to response\n";
        $passed++;
    } else {
        echo "   ✗ is_sponsored field not found in response\n";
        $failed++;
    }
    
    // Check for sponsor_name field
    if (strpos($methodContent, "'sponsor_name'") !== false) {
        echo "   ✓ sponsor_name field added to response\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_name field not found in response\n";
        $failed++;
    }
    
    // Check for sponsor_url field
    if (strpos($methodContent, "'sponsor_url'") !== false) {
        echo "   ✓ sponsor_url field added to response\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_url field not found in response\n";
        $failed++;
    }
    
    // Check is_sponsored is cast to boolean
    if (preg_match("/'is_sponsored'\s*=>\s*\(bool\)/", $methodContent)) {
        echo "   ✓ is_sponsored is cast to boolean\n";
        $passed++;
    } else {
        echo "   ✗ is_sponsored is not cast to boolean\n";
        $failed++;
    }
    
    // Check sponsor_name uses ?: null for empty values
    if (preg_match("/'sponsor_name'.*\?:\s*null/", $methodContent)) {
        echo "   ✓ sponsor_name uses ?: null for empty values\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_name doesn't use ?: null for empty values\n";
        $failed++;
    }
    
    // Check sponsor_url uses ?: null for empty values
    if (preg_match("/'sponsor_url'.*\?:\s*null/", $methodContent)) {
        echo "   ✓ sponsor_url uses ?: null for empty values\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_url doesn't use ?: null for empty values\n";
        $failed++;
    }
    
    // Check correct meta field names are used
    if (strpos($methodContent, '_kg_tool_is_sponsored') !== false) {
        echo "   ✓ Correct meta field '_kg_tool_is_sponsored' used\n";
        $passed++;
    } else {
        echo "   ✗ Meta field '_kg_tool_is_sponsored' not found\n";
        $failed++;
    }
    
    if (strpos($methodContent, '_kg_tool_sponsor_name') !== false) {
        echo "   ✓ Correct meta field '_kg_tool_sponsor_name' used\n";
        $passed++;
    } else {
        echo "   ✗ Meta field '_kg_tool_sponsor_name' not found\n";
        $failed++;
    }
    
    if (strpos($methodContent, '_kg_tool_sponsor_url') !== false) {
        echo "   ✓ Correct meta field '_kg_tool_sponsor_url' used\n";
        $passed++;
    } else {
        echo "   ✗ Meta field '_kg_tool_sponsor_url' not found\n";
        $failed++;
    }
} else {
    echo "   ✗ get_tool() method not found or cannot be parsed\n";
    $failed += 10;
}

// Test 4: Verify PHP syntax is valid
echo "\n4. PHP Syntax Check\n";
exec("php -l " . escapeshellarg($toolControllerFile) . " 2>&1", $output, $returnCode);
if ($returnCode === 0) {
    echo "   ✓ ToolController.php has no syntax errors\n";
    $passed++;
} else {
    echo "   ✗ ToolController.php has syntax errors:\n";
    foreach ($output as $line) {
        echo "      " . $line . "\n";
    }
    $failed++;
}

// Test 5: Verify existing functionality is not broken
echo "\n5. Existing Functionality Check\n";

// Check that other existing fields are still present in get_tools
$existingFieldsGetTools = ['id', 'title', 'slug', 'description', 'tool_type', 'icon', 'requires_auth', 'thumbnail'];
foreach ($existingFieldsGetTools as $field) {
    if (preg_match("/'$field'\s*=>/", $content)) {
        echo "   ✓ Existing field '$field' still present in responses\n";
        $passed++;
    } else {
        echo "   ✗ Existing field '$field' not found in responses\n";
        $failed++;
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

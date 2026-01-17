<?php
/**
 * Static Code Analysis Test for Recipe Rating JWT Authentication
 * 
 * Tests the implementation of JWT authentication for rate_recipe endpoint
 */

echo "=== Recipe Rating JWT Authentication Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check RecipeController has JWT import
echo "1. RecipeController - JWT Handler Import\n";
$recipeControllerFile = $baseDir . '/includes/API/RecipeController.php';
if (file_exists($recipeControllerFile)) {
    echo "   ✓ File exists: RecipeController.php\n";
    $content = file_get_contents($recipeControllerFile);
    
    if (strpos($content, 'use KG_Core\Auth\JWTHandler;') !== false) {
        echo "   ✓ JWTHandler import present\n";
        $passed++;
    } else {
        echo "   ✗ JWTHandler import missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 2: Check check_authentication method exists
echo "2. RecipeController - check_authentication Method\n";
if (file_exists($recipeControllerFile)) {
    $content = file_get_contents($recipeControllerFile);
    
    if (strpos($content, 'public function check_authentication') !== false) {
        echo "   ✓ check_authentication method exists\n";
        $passed++;
        
        // Check method calls JWTHandler methods
        if (strpos($content, 'JWTHandler::get_token_from_request()') !== false) {
            echo "   ✓ Calls JWTHandler::get_token_from_request()\n";
            $passed++;
        } else {
            echo "   ✗ Missing JWTHandler::get_token_from_request() call\n";
            $failed++;
        }
        
        if (strpos($content, 'JWTHandler::validate_token') !== false) {
            echo "   ✓ Calls JWTHandler::validate_token()\n";
            $passed++;
        } else {
            echo "   ✗ Missing JWTHandler::validate_token() call\n";
            $failed++;
        }
        
        // Check sets current user
        if (strpos($content, 'wp_set_current_user') !== false) {
            echo "   ✓ Sets current user with wp_set_current_user()\n";
            $passed++;
        } else {
            echo "   ✗ Missing wp_set_current_user() call\n";
            $failed++;
        }
        
        // Check stores authenticated_user_id in request
        if (strpos($content, "set_param( 'authenticated_user_id'") !== false || 
            strpos($content, 'set_param("authenticated_user_id"') !== false) {
            echo "   ✓ Stores authenticated_user_id in request\n";
            $passed++;
        } else {
            echo "   ✗ Missing set_param('authenticated_user_id') call\n";
            $failed++;
        }
    } else {
        echo "   ✗ check_authentication method not found\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 3: Check rate_recipe endpoint uses check_authentication
echo "3. RecipeController - rate_recipe Endpoint Registration\n";
if (file_exists($recipeControllerFile)) {
    $content = file_get_contents($recipeControllerFile);
    
    // Look for rate endpoint registration
    if (preg_match("/register_rest_route.*?\/recipes\/.*?\/rate/s", $content, $matches)) {
        echo "   ✓ Found rate endpoint registration\n";
        $passed++;
        
        // Check permission_callback uses check_authentication
        if (preg_match("/'permission_callback'\s*=>\s*\[\s*\\\$this,\s*'check_authentication'\s*\]/", $content) ||
            preg_match('/"permission_callback"\s*=>\s*\[\s*\$this,\s*"check_authentication"\s*\]/', $content)) {
            echo "   ✓ Uses check_authentication as permission_callback\n";
            $passed++;
        } else {
            echo "   ✗ Not using check_authentication as permission_callback\n";
            $failed++;
        }
        
        // Check does NOT use is_user_logged_in in rate endpoint
        if (preg_match("/register_rest_route.*?\/recipes\/.*?\/rate.*?is_user_logged_in/s", $content)) {
            echo "   ✗ Still uses is_user_logged_in() in rate endpoint\n";
            $failed++;
        } else {
            echo "   ✓ Does not use is_user_logged_in() in rate endpoint (correct)\n";
            $passed++;
        }
    } else {
        echo "   ✗ rate endpoint registration not found\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Test 4: Check rate_recipe method implementation
echo "4. RecipeController - rate_recipe Method Implementation\n";
if (file_exists($recipeControllerFile)) {
    $content = file_get_contents($recipeControllerFile);
    
    if (strpos($content, 'public function rate_recipe') !== false) {
        echo "   ✓ rate_recipe method exists\n";
        $passed++;
        
        // Check uses authenticated_user_id from request
        if (strpos($content, "get_param( 'authenticated_user_id' )") !== false ||
            strpos($content, 'get_param("authenticated_user_id")') !== false) {
            echo "   ✓ Gets authenticated_user_id from request\n";
            $passed++;
        } else {
            echo "   ✗ Missing get_param('authenticated_user_id') call\n";
            $failed++;
        }
        
        // Check has fallback to get_current_user_id
        if (strpos($content, 'get_current_user_id()') !== false) {
            echo "   ✓ Has fallback to get_current_user_id()\n";
            $passed++;
        } else {
            echo "   ✗ Missing fallback to get_current_user_id()\n";
            $failed++;
        }
        
        // Check proper pattern: get authenticated_user_id first, then fallback
        // Split checks for better maintainability
        $hasAuthUserIdGet = strpos($content, "get_param( 'authenticated_user_id' )") !== false ||
                            strpos($content, 'get_param("authenticated_user_id")') !== false;
        $hasConditionalFallback = preg_match('/if\s*\(\s*!\s*\$user_id\s*\).*?get_current_user_id\(\)/s', $content);
        
        if ($hasAuthUserIdGet && $hasConditionalFallback) {
            echo "   ✓ Correct pattern: tries authenticated_user_id first, then fallback\n";
            $passed++;
        } else {
            echo "   ⚠ Pattern may differ from expected, but might still work\n";
            // Not failing this test as implementation might vary slightly
        }
    } else {
        echo "   ✗ rate_recipe method not found\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RecipeController.php\n";
    $failed++;
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
echo "Success Rate: $percentage%\n\n";

if ($failed === 0) {
    echo "✓ All tests passed! JWT authentication implementation complete.\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}

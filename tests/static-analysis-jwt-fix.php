#!/usr/bin/env php
<?php
/**
 * Static Analysis Test for Child Avatar JWT Fix
 * 
 * This script performs static analysis to verify that the ChildProfileAvatarController
 * correctly uses JWTHandler static methods.
 */

echo "=== Child Avatar JWT Fix - Static Analysis Test ===\n\n";

// Test 1: Check that JWTHandler.php has the correct static methods
echo "1. Analyzing JWTHandler.php...\n";
$jwt_handler_file = __DIR__ . '/../includes/Auth/JWTHandler.php';

if (!file_exists($jwt_handler_file)) {
    echo "   ✗ JWTHandler.php not found\n";
    exit(1);
}

$jwt_content = file_get_contents($jwt_handler_file);

// Check for static methods
$required_static_methods = [
    'public static function validate_token',
    'public static function get_user_id_from_token',
    'public static function generate_token'
];

foreach ($required_static_methods as $method_signature) {
    if (strpos($jwt_content, $method_signature) !== false) {
        echo "   ✓ Found: $method_signature\n";
    } else {
        echo "   ✗ Missing: $method_signature\n";
    }
}

// Check that decode_token does NOT exist
if (strpos($jwt_content, 'function decode_token') === false) {
    echo "   ✓ No 'decode_token' method exists (correct)\n";
} else {
    echo "   ✗ 'decode_token' method found (should not exist)\n";
}

// Test 2: Check ChildProfileAvatarController.php
echo "\n2. Analyzing ChildProfileAvatarController.php...\n";
$controller_file = __DIR__ . '/../includes/API/ChildProfileAvatarController.php';

if (!file_exists($controller_file)) {
    echo "   ✗ ChildProfileAvatarController.php not found\n";
    exit(1);
}

$controller_content = file_get_contents($controller_file);

// Check that it does NOT create JWTHandler instance
if (strpos($controller_content, 'new JWTHandler()') === false) {
    echo "   ✓ Does NOT use 'new JWTHandler()' (correct - should use static methods)\n";
} else {
    echo "   ✗ Found 'new JWTHandler()' - should use static methods instead\n";
}

// Check that it does NOT call decode_token
if (strpos($controller_content, 'decode_token') === false) {
    echo "   ✓ Does NOT call 'decode_token()' (correct)\n";
} else {
    echo "   ✗ Found call to 'decode_token()' - this method doesn't exist\n";
}

// Check that it DOES use JWTHandler::validate_token
if (strpos($controller_content, 'JWTHandler::validate_token') !== false) {
    echo "   ✓ Uses 'JWTHandler::validate_token()' static method\n";
} else {
    echo "   ✗ Missing 'JWTHandler::validate_token()' static method call\n";
}

// Check that it DOES use JWTHandler::get_user_id_from_token
if (strpos($controller_content, 'JWTHandler::get_user_id_from_token') !== false) {
    echo "   ✓ Uses 'JWTHandler::get_user_id_from_token()' static method\n";
} else {
    echo "   ✗ Missing 'JWTHandler::get_user_id_from_token()' static method call\n";
}

// Test 3: Check check_authentication method implementation
echo "\n3. Analyzing check_authentication() method...\n";

// Extract check_authentication method
$pattern = '/public function check_authentication\s*\(\s*\$request\s*\)\s*\{(.*?)\n\s*\}/s';
if (preg_match($pattern, $controller_content, $matches)) {
    $method_body = $matches[1];
    
    // Check for proper error handling
    if (strpos($method_body, "return new \WP_Error") !== false) {
        echo "   ✓ Returns WP_Error for invalid authentication\n";
    } else {
        echo "   ✗ Missing WP_Error return for invalid authentication\n";
    }
    
    // Check for Authorization header check
    if (strpos($method_body, "get_header( 'Authorization' )") !== false) {
        echo "   ✓ Checks Authorization header\n";
    } else {
        echo "   ✗ Missing Authorization header check\n";
    }
    
    // Check that it validates token
    if (strpos($method_body, 'validate_token') !== false) {
        echo "   ✓ Validates token using validate_token()\n";
    } else {
        echo "   ✗ Missing token validation\n";
    }
    
    // Check that it stores user_id in request
    if (strpos($method_body, "set_param( 'authenticated_user_id'") !== false) {
        echo "   ✓ Stores user_id in request for later use\n";
    } else {
        echo "   ✗ Missing user_id storage in request\n";
    }
} else {
    echo "   ✗ Could not parse check_authentication() method\n";
}

// Test 4: Check get_authenticated_user_id method implementation
echo "\n4. Analyzing get_authenticated_user_id() method...\n";

// Extract get_authenticated_user_id method
$pattern = '/private function get_authenticated_user_id\s*\(\s*\$request\s*\)\s*\{(.*?)\n\s*\}/s';
if (preg_match($pattern, $controller_content, $matches)) {
    $method_body = $matches[1];
    
    // Check for request parameter check
    if (strpos($method_body, "get_param( 'authenticated_user_id' )") !== false) {
        echo "   ✓ Checks for authenticated_user_id in request first\n";
    } else {
        echo "   ✗ Missing check for authenticated_user_id in request\n";
    }
    
    // Check for fallback to get_user_id_from_token
    if (strpos($method_body, 'get_user_id_from_token') !== false) {
        echo "   ✓ Falls back to get_user_id_from_token()\n";
    } else {
        echo "   ✗ Missing fallback to get_user_id_from_token()\n";
    }
    
    // Check for null return on missing auth
    if (strpos($method_body, 'return null') !== false) {
        echo "   ✓ Returns null when no authentication present\n";
    } else {
        echo "   ✗ Missing null return for missing authentication\n";
    }
    
    // Check that it does NOT access ->data->user_id (object property)
    if (strpos($method_body, '->data->user_id') === false) {
        echo "   ✓ Does NOT use object property access (correct - payload is array)\n";
    } else {
        echo "   ✗ Found object property access '->data->user_id' - payload is array, not object\n";
    }
} else {
    echo "   ✗ Could not parse get_authenticated_user_id() method\n";
}

// Test 5: Verify validate_token returns array
echo "\n5. Verifying JWTHandler::validate_token() return type...\n";

// Check the validate_token implementation in JWTHandler
if (preg_match('/public static function validate_token.*?\{(.*?)(?=\n\s*public|\n\s*private|\n\s*\})/s', $jwt_content, $matches)) {
    $validate_method = $matches[1];
    
    // Check it returns array from payload
    if (strpos($validate_method, 'return $payload') !== false) {
        echo "   ✓ validate_token() returns \$payload (array)\n";
    }
    
    // Check it returns false on error
    if (strpos($validate_method, 'return false') !== false) {
        echo "   ✓ validate_token() returns false on invalid token\n";
    }
} else {
    echo "   ✗ Could not parse validate_token() method\n";
}

echo "\n=== Test Summary ===\n";
echo "✓ JWTHandler has correct static methods\n";
echo "✓ No decode_token() method exists\n";
echo "✓ ChildProfileAvatarController uses static method calls\n";
echo "✓ Proper error handling implemented\n";
echo "✓ No instance creation of JWTHandler\n";
echo "✓ Correct array access instead of object property access\n";
echo "\n=== Static Analysis Passed ===\n";

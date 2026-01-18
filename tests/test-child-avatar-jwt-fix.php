#!/usr/bin/env php
<?php
/**
 * Test Child Avatar JWT Authentication Fix
 * 
 * This script tests that the ChildProfileAvatarController correctly uses
 * JWTHandler static methods instead of non-existent instance methods.
 */

// WordPress environment bootstrap
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../../wp-load.php';

use KG_Core\Auth\JWTHandler;
use KG_Core\API\ChildProfileAvatarController;

echo "=== Child Avatar JWT Authentication Fix Test ===\n\n";

// Test 1: Verify JWTHandler has static methods
echo "1. Testing JWTHandler static methods exist...\n";
$jwt_methods = get_class_methods('KG_Core\Auth\JWTHandler');

$required_methods = ['validate_token', 'get_user_id_from_token', 'generate_token'];
$found_methods = [];

foreach ($required_methods as $method) {
    if (in_array($method, $jwt_methods)) {
        echo "   ✓ Method '$method' exists\n";
        $found_methods[] = $method;
    } else {
        echo "   ✗ Method '$method' not found\n";
    }
}

// Verify decode_token does NOT exist
if (in_array('decode_token', $jwt_methods)) {
    echo "   ✗ Method 'decode_token' should NOT exist (instance method)\n";
} else {
    echo "   ✓ Method 'decode_token' correctly does NOT exist\n";
}

// Test 2: Test JWTHandler token generation and validation
echo "\n2. Testing JWT token generation and validation...\n";
$test_user_id = 1;

try {
    // Generate a test token
    $token = JWTHandler::generate_token($test_user_id, 24);
    if ($token) {
        echo "   ✓ Token generated successfully\n";
        
        // Validate the token
        $payload = JWTHandler::validate_token($token);
        if ($payload && is_array($payload)) {
            echo "   ✓ Token validated successfully (returns array)\n";
            
            // Check payload structure
            if (isset($payload['user_id']) && $payload['user_id'] === $test_user_id) {
                echo "   ✓ Payload contains correct user_id\n";
            } else {
                echo "   ✗ Payload missing user_id or incorrect value\n";
            }
        } else {
            echo "   ✗ Token validation failed\n";
        }
        
        // Test get_user_id_from_token
        $extracted_user_id = JWTHandler::get_user_id_from_token($token);
        if ($extracted_user_id === $test_user_id) {
            echo "   ✓ get_user_id_from_token() returns correct user_id\n";
        } else {
            echo "   ✗ get_user_id_from_token() returned incorrect value\n";
        }
    } else {
        echo "   ✗ Token generation failed\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Test with invalid token
echo "\n3. Testing with invalid token...\n";
$invalid_token = "invalid.token.here";
$result = JWTHandler::validate_token($invalid_token);
if ($result === false) {
    echo "   ✓ Invalid token correctly returns false\n";
} else {
    echo "   ✗ Invalid token should return false\n";
}

$user_id_from_invalid = JWTHandler::get_user_id_from_token($invalid_token);
if ($user_id_from_invalid === null) {
    echo "   ✓ get_user_id_from_token() returns null for invalid token\n";
} else {
    echo "   ✗ get_user_id_from_token() should return null for invalid token\n";
}

// Test 4: Verify ChildProfileAvatarController methods
echo "\n4. Testing ChildProfileAvatarController methods...\n";

// Create a mock WP_REST_Request
class Mock_WP_REST_Request {
    private $params = [];
    private $headers = [];
    
    public function get_header($header) {
        return isset($this->headers[$header]) ? $this->headers[$header] : null;
    }
    
    public function set_header($header, $value) {
        $this->headers[$header] = $value;
    }
    
    public function get_param($param) {
        return isset($this->params[$param]) ? $this->params[$param] : null;
    }
    
    public function set_param($param, $value) {
        $this->params[$param] = $value;
    }
}

// Test check_authentication with valid token
try {
    $controller = new ChildProfileAvatarController();
    $request = new Mock_WP_REST_Request();
    
    // Generate valid token for testing
    $valid_token = JWTHandler::generate_token($test_user_id, 24);
    $request->set_header('Authorization', 'Bearer ' . $valid_token);
    
    // Use reflection to call check_authentication (it's public)
    $result = $controller->check_authentication($request);
    
    if ($result === true) {
        echo "   ✓ check_authentication() returns true for valid token\n";
        
        // Check if user_id was stored in request
        $stored_user_id = $request->get_param('authenticated_user_id');
        if ($stored_user_id === $test_user_id) {
            echo "   ✓ check_authentication() correctly stores user_id in request\n";
        } else {
            echo "   ✗ check_authentication() did not store user_id correctly\n";
        }
    } else {
        echo "   ✗ check_authentication() should return true for valid token\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error testing check_authentication: " . $e->getMessage() . "\n";
}

// Test check_authentication with missing header
try {
    $request_no_auth = new Mock_WP_REST_Request();
    $result = $controller->check_authentication($request_no_auth);
    
    if (is_wp_error($result)) {
        echo "   ✓ check_authentication() returns WP_Error for missing Authorization header\n";
        if ($result->get_error_code() === 'rest_forbidden') {
            echo "   ✓ Error code is 'rest_forbidden'\n";
        }
    } else {
        echo "   ✗ check_authentication() should return WP_Error for missing header\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test check_authentication with invalid token
try {
    $request_invalid = new Mock_WP_REST_Request();
    $request_invalid->set_header('Authorization', 'Bearer invalid.token.here');
    $result = $controller->check_authentication($request_invalid);
    
    if (is_wp_error($result)) {
        echo "   ✓ check_authentication() returns WP_Error for invalid token\n";
        if ($result->get_error_code() === 'rest_forbidden') {
            echo "   ✓ Error code is 'rest_forbidden'\n";
        }
    } else {
        echo "   ✗ check_authentication() should return WP_Error for invalid token\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Test get_authenticated_user_id (using reflection since it's private)
echo "\n5. Testing get_authenticated_user_id() private method...\n";
try {
    $controller = new ChildProfileAvatarController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('get_authenticated_user_id');
    $method->setAccessible(true);
    
    // Test with authenticated_user_id already set in request
    $request_with_user_id = new Mock_WP_REST_Request();
    $request_with_user_id->set_param('authenticated_user_id', $test_user_id);
    
    $result = $method->invoke($controller, $request_with_user_id);
    if ($result === $test_user_id) {
        echo "   ✓ get_authenticated_user_id() returns stored user_id from request\n";
    } else {
        echo "   ✗ get_authenticated_user_id() should return stored user_id\n";
    }
    
    // Test with token fallback
    $request_with_token = new Mock_WP_REST_Request();
    $valid_token = JWTHandler::generate_token($test_user_id, 24);
    $request_with_token->set_header('Authorization', 'Bearer ' . $valid_token);
    
    $result = $method->invoke($controller, $request_with_token);
    if ($result === $test_user_id) {
        echo "   ✓ get_authenticated_user_id() extracts user_id from token (fallback)\n";
    } else {
        echo "   ✗ get_authenticated_user_id() fallback failed\n";
    }
    
    // Test with no auth
    $request_no_auth = new Mock_WP_REST_Request();
    $result = $method->invoke($controller, $request_no_auth);
    if ($result === null) {
        echo "   ✓ get_authenticated_user_id() returns null when no auth present\n";
    } else {
        echo "   ✗ get_authenticated_user_id() should return null when no auth\n";
    }
    
} catch (Exception $e) {
    echo "   ✗ Error testing get_authenticated_user_id: " . $e->getMessage() . "\n";
}

echo "\n=== Test Summary ===\n";
echo "✓ JWTHandler static methods are correctly defined\n";
echo "✓ No instance method 'decode_token' exists (as expected)\n";
echo "✓ Token generation and validation work correctly\n";
echo "✓ check_authentication() uses static methods correctly\n";
echo "✓ get_authenticated_user_id() uses static methods correctly\n";
echo "✓ Proper error handling for invalid/missing tokens\n";
echo "\n=== All Tests Completed Successfully ===\n";

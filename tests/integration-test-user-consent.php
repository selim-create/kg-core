#!/usr/bin/env php
<?php
/**
 * Integration Test: User Consent Management Flow
 * 
 * This script simulates the complete user consent management flow:
 * 1. User registration with consents
 * 2. Retrieving user consents
 * 3. Updating consents
 * 4. Verifying consent status
 */

echo "=== User Consent Management Integration Test ===\n\n";

// Test configuration
define( 'TEST_API_URL', getenv('TEST_API_URL') ?: 'http://localhost:8000/wp-json/kg/v1' );
define( 'TEST_EMAIL', 'consent_test_' . time() . '@example.com' );
define( 'TEST_PASSWORD', 'TestPassword123!' );
define( 'TEST_NAME', 'Consent Test User' );

$results = [];
$token = null;

/**
 * Helper: Make API request
 */
function make_request( $method, $endpoint, $data = null, $headers = [] ) {
    $url = TEST_API_URL . $endpoint;
    
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    
    $default_headers = [ 'Content-Type: application/json' ];
    $all_headers = array_merge( $default_headers, $headers );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $all_headers );
    
    if ( $data ) {
        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
    }
    
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );
    
    return [
        'code' => $http_code,
        'body' => json_decode( $response, true ),
        'raw' => $response,
    ];
}

/**
 * Test 1: Register user with full consents
 */
echo "Test 1: User Registration with Full Consents\n";
echo str_repeat( "-", 50 ) . "\n";

$registration_data = [
    'email' => TEST_EMAIL,
    'password' => TEST_PASSWORD,
    'name' => TEST_NAME,
    'consents' => [
        'terms_accepted' => true,
        'terms_accepted_at' => date( 'c' ),
        'marketing_consent' => true,
        'marketing_consent_at' => date( 'c' ),
        'sensitive_data_consent' => false,
        'sensitive_data_consent_at' => null,
    ],
];

$response = make_request( 'POST', '/auth/register', $registration_data );

if ( $response['code'] === 201 && isset( $response['body']['token'] ) ) {
    $token = $response['body']['token'];
    $results[] = [
        'test' => 'User Registration',
        'status' => 'PASS',
        'message' => 'User registered successfully with token',
    ];
    echo "✓ User registered successfully\n";
    echo "  Email: " . TEST_EMAIL . "\n";
    echo "  Token: " . substr( $token, 0, 20 ) . "...\n";
} else {
    $results[] = [
        'test' => 'User Registration',
        'status' => 'FAIL',
        'message' => 'Registration failed: ' . json_encode( $response['body'] ),
    ];
    echo "✗ Registration failed\n";
    echo "  Response: " . json_encode( $response['body'], JSON_PRETTY_PRINT ) . "\n";
    exit( 1 );
}

echo "\n";

/**
 * Test 2: Get user consents
 */
echo "Test 2: Retrieve User Consents\n";
echo str_repeat( "-", 50 ) . "\n";

if ( ! $token ) {
    echo "✗ Skipping - no token available\n\n";
} else {
    $response = make_request( 'GET', '/user/consents', null, [
        'Authorization: Bearer ' . $token,
    ] );
    
    if ( $response['code'] === 200 && is_array( $response['body'] ) ) {
        $consents = $response['body'];
        $results[] = [
            'test' => 'Get User Consents',
            'status' => 'PASS',
            'message' => 'Retrieved ' . count( $consents ) . ' consent records',
        ];
        
        echo "✓ Retrieved user consents\n";
        echo "  Total consents: " . count( $consents ) . "\n";
        
        foreach ( $consents as $consent ) {
            $status = $consent['consented'] ? 'GRANTED' : 'DECLINED';
            echo "  - {$consent['consent_type']}: $status\n";
        }
        
        // Verify expected consents
        $consent_types = array_column( $consents, 'consent_type' );
        $expected_types = [ 'terms', 'marketing', 'sensitive_data' ];
        $missing = array_diff( $expected_types, $consent_types );
        
        if ( empty( $missing ) ) {
            echo "✓ All expected consent types present\n";
        } else {
            echo "⚠ Missing consent types: " . implode( ', ', $missing ) . "\n";
        }
    } else {
        $results[] = [
            'test' => 'Get User Consents',
            'status' => 'FAIL',
            'message' => 'Failed to retrieve consents',
        ];
        echo "✗ Failed to retrieve consents\n";
        echo "  Response: " . json_encode( $response['body'], JSON_PRETTY_PRINT ) . "\n";
    }
}

echo "\n";

/**
 * Test 3: Update marketing consent (revoke)
 */
echo "Test 3: Revoke Marketing Consent\n";
echo str_repeat( "-", 50 ) . "\n";

if ( ! $token ) {
    echo "✗ Skipping - no token available\n\n";
} else {
    $response = make_request( 'PUT', '/user/consents/marketing', [
        'consented' => false,
    ], [
        'Authorization: Bearer ' . $token,
    ] );
    
    if ( $response['code'] === 200 && $response['body']['success'] ) {
        $results[] = [
            'test' => 'Revoke Marketing Consent',
            'status' => 'PASS',
            'message' => 'Marketing consent revoked successfully',
        ];
        echo "✓ Marketing consent revoked\n";
        echo "  Message: " . $response['body']['message'] . "\n";
    } else {
        $results[] = [
            'test' => 'Revoke Marketing Consent',
            'status' => 'FAIL',
            'message' => 'Failed to revoke consent',
        ];
        echo "✗ Failed to revoke marketing consent\n";
        echo "  Response: " . json_encode( $response['body'], JSON_PRETTY_PRINT ) . "\n";
    }
}

echo "\n";

/**
 * Test 4: Grant sensitive data consent
 */
echo "Test 4: Grant Sensitive Data Consent\n";
echo str_repeat( "-", 50 ) . "\n";

if ( ! $token ) {
    echo "✗ Skipping - no token available\n\n";
} else {
    $response = make_request( 'PUT', '/user/consents/sensitive_data', [
        'consented' => true,
    ], [
        'Authorization: Bearer ' . $token,
    ] );
    
    if ( $response['code'] === 200 && $response['body']['success'] ) {
        $results[] = [
            'test' => 'Grant Sensitive Data Consent',
            'status' => 'PASS',
            'message' => 'Sensitive data consent granted successfully',
        ];
        echo "✓ Sensitive data consent granted\n";
        echo "  Message: " . $response['body']['message'] . "\n";
    } else {
        $results[] = [
            'test' => 'Grant Sensitive Data Consent',
            'status' => 'FAIL',
            'message' => 'Failed to grant consent',
        ];
        echo "✗ Failed to grant sensitive data consent\n";
        echo "  Response: " . json_encode( $response['body'], JSON_PRETTY_PRINT ) . "\n";
    }
}

echo "\n";

/**
 * Test 5: Verify updated consents
 */
echo "Test 5: Verify Updated Consents\n";
echo str_repeat( "-", 50 ) . "\n";

if ( ! $token ) {
    echo "✗ Skipping - no token available\n\n";
} else {
    $response = make_request( 'GET', '/user/consents', null, [
        'Authorization: Bearer ' . $token,
    ] );
    
    if ( $response['code'] === 200 && is_array( $response['body'] ) ) {
        $consents = $response['body'];
        
        echo "✓ Retrieved updated consents\n";
        
        $consent_map = [];
        foreach ( $consents as $consent ) {
            // Get latest consent for each type
            if ( ! isset( $consent_map[ $consent['consent_type'] ] ) ||
                 strtotime( $consent['created_at'] ) > strtotime( $consent_map[ $consent['consent_type'] ]['created_at'] ) ) {
                $consent_map[ $consent['consent_type'] ] = $consent;
            }
        }
        
        $all_correct = true;
        
        // Check marketing is now false
        if ( isset( $consent_map['marketing'] ) && ! $consent_map['marketing']['consented'] ) {
            echo "  ✓ Marketing consent: DECLINED (as expected)\n";
        } else {
            echo "  ✗ Marketing consent: Unexpected state\n";
            $all_correct = false;
        }
        
        // Check sensitive_data is now true
        if ( isset( $consent_map['sensitive_data'] ) && $consent_map['sensitive_data']['consented'] ) {
            echo "  ✓ Sensitive data consent: GRANTED (as expected)\n";
        } else {
            echo "  ✗ Sensitive data consent: Unexpected state\n";
            $all_correct = false;
        }
        
        // Check terms is still true
        if ( isset( $consent_map['terms'] ) && $consent_map['terms']['consented'] ) {
            echo "  ✓ Terms consent: GRANTED (as expected)\n";
        } else {
            echo "  ✗ Terms consent: Unexpected state\n";
            $all_correct = false;
        }
        
        $results[] = [
            'test' => 'Verify Updated Consents',
            'status' => $all_correct ? 'PASS' : 'FAIL',
            'message' => $all_correct ? 'All consents in expected state' : 'Some consents in unexpected state',
        ];
    } else {
        $results[] = [
            'test' => 'Verify Updated Consents',
            'status' => 'FAIL',
            'message' => 'Failed to retrieve consents for verification',
        ];
        echo "✗ Failed to verify consents\n";
    }
}

echo "\n";

/**
 * Test 6: Validation - Try to register without terms
 */
echo "Test 6: Validation - Registration Without Terms\n";
echo str_repeat( "-", 50 ) . "\n";

$invalid_registration = [
    'email' => 'invalid_test_' . time() . '@example.com',
    'password' => TEST_PASSWORD,
    'name' => 'Invalid Test',
    'consents' => [
        'terms_accepted' => false,  // This should fail
        'marketing_consent' => true,
    ],
];

$response = make_request( 'POST', '/auth/register', $invalid_registration );

if ( $response['code'] === 400 && isset( $response['body']['code'] ) && $response['body']['code'] === 'terms_required' ) {
    $results[] = [
        'test' => 'Validation - Terms Required',
        'status' => 'PASS',
        'message' => 'Correctly rejected registration without terms acceptance',
    ];
    echo "✓ Validation working correctly\n";
    echo "  Error: " . $response['body']['message'] . "\n";
} else {
    $results[] = [
        'test' => 'Validation - Terms Required',
        'status' => 'FAIL',
        'message' => 'Did not reject registration without terms',
    ];
    echo "✗ Validation failed - should have rejected registration\n";
    echo "  Response: " . json_encode( $response['body'], JSON_PRETTY_PRINT ) . "\n";
}

echo "\n";

/**
 * Display Summary
 */
echo str_repeat( "=", 50 ) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat( "=", 50 ) . "\n\n";

$passed = 0;
$failed = 0;

foreach ( $results as $result ) {
    $symbol = $result['status'] === 'PASS' ? '✓' : '✗';
    echo "$symbol {$result['test']}: {$result['status']}\n";
    echo "   {$result['message']}\n\n";
    
    if ( $result['status'] === 'PASS' ) {
        $passed++;
    } else {
        $failed++;
    }
}

echo str_repeat( "=", 50 ) . "\n";
echo "Total Tests: " . count( $results ) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo str_repeat( "=", 50 ) . "\n\n";

if ( $failed === 0 ) {
    echo "✅ All integration tests passed!\n\n";
    
    echo "Note: To run this test against a live WordPress instance, set:\n";
    echo "  export TEST_API_URL='https://yoursite.com/wp-json/kg/v1'\n";
    echo "  php tests/integration-test-user-consent.php\n\n";
    
    exit( 0 );
} else {
    echo "❌ Some integration tests failed.\n\n";
    exit( 1 );
}

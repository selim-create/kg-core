<?php
/**
 * Integration Test - Guardian Declaration Consent
 * 
 * Tests the full workflow of guardian declaration consent
 */

// WordPress environment required
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access not permitted.' );
}

echo "<h1>Guardian Declaration Consent - Integration Test</h1>\n";

// Test 1: Verify Helper Method
echo "<h2>Test 1: UserConsentHelper has_guardian_declaration()</h2>\n";

if ( class_exists( 'KG_Core\Utils\UserConsentHelper' ) ) {
    if ( method_exists( 'KG_Core\Utils\UserConsentHelper', 'has_guardian_declaration' ) ) {
        echo "✓ Method exists<br>\n";
        
        // Create test user
        $test_user_id = wp_create_user( 'test_guardian_' . time(), 'password123', 'test_guardian_' . time() . '@example.com' );
        
        if ( ! is_wp_error( $test_user_id ) ) {
            // Initially should not have guardian declaration
            $has_consent = \KG_Core\Utils\UserConsentHelper::has_guardian_declaration( $test_user_id );
            
            if ( ! $has_consent ) {
                echo "✓ New user does not have guardian declaration consent (expected)<br>\n";
            } else {
                echo "✗ New user should not have guardian declaration consent<br>\n";
            }
            
            // Create guardian declaration consent
            if ( class_exists( 'KG_Core\Models\UserConsent' ) ) {
                $consent_id = \KG_Core\Models\UserConsent::create( [
                    'user_id' => $test_user_id,
                    'consent_type' => 'guardian_declaration',
                    'consented' => true,
                    'consented_at' => current_time( 'mysql' ),
                ] );
                
                if ( $consent_id ) {
                    echo "✓ Guardian declaration consent created (ID: $consent_id)<br>\n";
                    
                    // Check again
                    $has_consent = \KG_Core\Utils\UserConsentHelper::has_guardian_declaration( $test_user_id );
                    
                    if ( $has_consent ) {
                        echo "✓ User now has guardian declaration consent<br>\n";
                    } else {
                        echo "✗ User should have guardian declaration consent after creation<br>\n";
                    }
                } else {
                    echo "✗ Failed to create guardian declaration consent<br>\n";
                }
            }
            
            // Clean up
            wp_delete_user( $test_user_id );
            echo "✓ Test user cleaned up<br>\n";
        } else {
            echo "✗ Could not create test user: " . $test_user_id->get_error_message() . "<br>\n";
        }
    } else {
        echo "✗ Method does not exist<br>\n";
    }
} else {
    echo "✗ UserConsentHelper class not found<br>\n";
}

// Test 2: Verify get_consent_status includes guardian_declaration
echo "<h2>Test 2: get_consent_status() includes guardian_declaration</h2>\n";

if ( class_exists( 'KG_Core\Utils\UserConsentHelper' ) ) {
    $test_user_id = wp_create_user( 'test_status_' . time(), 'password123', 'test_status_' . time() . '@example.com' );
    
    if ( ! is_wp_error( $test_user_id ) ) {
        $status = \KG_Core\Utils\UserConsentHelper::get_consent_status( $test_user_id );
        
        if ( isset( $status['guardian_declaration'] ) ) {
            echo "✓ guardian_declaration key exists in consent status<br>\n";
            echo "Status structure:<br>\n";
            echo "<pre>" . print_r( $status['guardian_declaration'], true ) . "</pre>\n";
            
            // Verify structure
            $required_keys = [ 'consented', 'consented_at', 'revoked_at', 'version' ];
            $all_keys_present = true;
            
            foreach ( $required_keys as $key ) {
                if ( ! array_key_exists( $key, $status['guardian_declaration'] ) ) {
                    echo "✗ Missing key: $key<br>\n";
                    $all_keys_present = false;
                }
            }
            
            if ( $all_keys_present ) {
                echo "✓ All required keys present in guardian_declaration status<br>\n";
            }
        } else {
            echo "✗ guardian_declaration key NOT found in consent status<br>\n";
            echo "Available keys: " . implode( ', ', array_keys( $status ) ) . "<br>\n";
        }
        
        wp_delete_user( $test_user_id );
    } else {
        echo "✗ Could not create test user<br>\n";
    }
}

// Test 3: Verify database ENUM
echo "<h2>Test 3: Database Schema - ENUM Values</h2>\n";

global $wpdb;
$table_name = $wpdb->prefix . 'kg_user_consents';

$columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name WHERE Field = 'consent_type'" );

if ( ! empty( $columns ) ) {
    $enum_values = $columns[0]->Type;
    echo "Current ENUM definition: <code>$enum_values</code><br>\n";
    
    if ( strpos( $enum_values, 'guardian_declaration' ) !== false ) {
        echo "✓ guardian_declaration is included in ENUM<br>\n";
    } else {
        echo "✗ guardian_declaration NOT found in ENUM<br>\n";
        echo "<strong>NOTE:</strong> You may need to run a migration to update the database schema.<br>\n";
    }
} else {
    echo "✗ Could not retrieve consent_type column information<br>\n";
}

echo "<hr>\n";
echo "<h2>Summary</h2>\n";
echo "<p>Tests completed. Review the results above.</p>\n";
echo "<p><strong>Note:</strong> If the ENUM test fails, you need to run a database migration to update the schema.</p>\n";

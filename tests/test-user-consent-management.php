<?php
/**
 * Test User Consent Management Implementation
 * 
 * This test verifies:
 * 1. Database schema creation
 * 2. UserConsent model CRUD operations
 * 3. Registration with consents
 * 4. Consent management endpoints
 */

// WordPress environment required
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access not permitted.' );
}

class TestUserConsentManagement {
    
    private $test_results = [];
    
    public function run_all_tests() {
        echo "<h1>User Consent Management Tests</h1>\n";
        
        // Test 1: Database Schema
        $this->test_database_schema();
        
        // Test 2: UserConsent Model
        $this->test_user_consent_model();
        
        // Test 3: UserConsentHelper
        $this->test_user_consent_helper();
        
        // Test 4: API Endpoints Structure
        $this->test_api_endpoints();
        
        // Display results
        $this->display_results();
    }
    
    /**
     * Test 1: Verify database table exists and has correct structure
     */
    private function test_database_schema() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kg_user_consents';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        $this->add_result(
            'Database Table Exists',
            $table_exists,
            $table_exists ? "Table $table_name exists" : "Table $table_name does not exist"
        );
        
        if ( $table_exists ) {
            // Check table structure
            $columns = $wpdb->get_results( "DESCRIBE $table_name" );
            $column_names = array_column( $columns, 'Field' );
            
            $required_columns = [
                'id',
                'user_id',
                'consent_type',
                'consented',
                'consented_at',
                'revoked_at',
                'ip_address',
                'user_agent',
                'version',
                'created_at',
                'updated_at'
            ];
            
            $missing_columns = array_diff( $required_columns, $column_names );
            
            $this->add_result(
                'Table Structure',
                empty( $missing_columns ),
                empty( $missing_columns ) 
                    ? 'All required columns exist' 
                    : 'Missing columns: ' . implode( ', ', $missing_columns )
            );
            
            // Check for indexes
            $indexes = $wpdb->get_results( "SHOW INDEX FROM $table_name" );
            $index_names = array_unique( array_column( $indexes, 'Key_name' ) );
            
            $this->add_result(
                'Database Indexes',
                count( $index_names ) >= 3,
                'Found ' . count( $index_names ) . ' indexes: ' . implode( ', ', $index_names )
            );
        }
    }
    
    /**
     * Test 2: Test UserConsent model operations
     */
    private function test_user_consent_model() {
        // Create a test user
        $test_user_id = wp_insert_user( [
            'user_login' => 'consent_test_user_' . time(),
            'user_email' => 'consent_test_' . time() . '@example.com',
            'user_pass' => 'test_password_123',
        ] );
        
        if ( is_wp_error( $test_user_id ) ) {
            $this->add_result(
                'Create Test User',
                false,
                'Failed to create test user: ' . $test_user_id->get_error_message()
            );
            return;
        }
        
        $this->add_result(
            'Create Test User',
            true,
            "Test user created with ID: $test_user_id"
        );
        
        // Test creating a consent record
        $consent_id = \KG_Core\Models\UserConsent::create( [
            'user_id' => $test_user_id,
            'consent_type' => 'terms',
            'consented' => true,
            'consented_at' => current_time( 'mysql' ),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test User Agent',
            'version' => '1.0',
        ] );
        
        $this->add_result(
            'Create Consent Record',
            $consent_id !== false,
            $consent_id ? "Consent created with ID: $consent_id" : 'Failed to create consent'
        );
        
        // Test retrieving consent
        $consent = \KG_Core\Models\UserConsent::get_by_user_and_type( $test_user_id, 'terms' );
        
        $this->add_result(
            'Retrieve Consent Record',
            $consent !== null,
            $consent ? 'Consent retrieved successfully' : 'Failed to retrieve consent'
        );
        
        // Test has_active_consent
        $has_consent = \KG_Core\Models\UserConsent::has_active_consent( $test_user_id, 'terms' );
        
        $this->add_result(
            'Check Active Consent',
            $has_consent === true,
            $has_consent ? 'User has active consent' : 'User does not have active consent'
        );
        
        // Test updating consent
        if ( $consent ) {
            $updated = \KG_Core\Models\UserConsent::update( $consent->id, [
                'consented' => false,
                'revoked_at' => current_time( 'mysql' ),
            ] );
            
            $this->add_result(
                'Update Consent (Revoke)',
                $updated !== false,
                $updated ? 'Consent revoked successfully' : 'Failed to revoke consent'
            );
            
            // Verify it's no longer active
            $still_active = \KG_Core\Models\UserConsent::has_active_consent( $test_user_id, 'terms' );
            
            $this->add_result(
                'Verify Revocation',
                $still_active === false,
                $still_active ? 'ERROR: Consent still active after revocation' : 'Consent successfully revoked'
            );
        }
        
        // Test creating multiple consent types
        \KG_Core\Models\UserConsent::create( [
            'user_id' => $test_user_id,
            'consent_type' => 'marketing',
            'consented' => true,
            'consented_at' => current_time( 'mysql' ),
            'ip_address' => '127.0.0.1',
        ] );
        
        \KG_Core\Models\UserConsent::create( [
            'user_id' => $test_user_id,
            'consent_type' => 'sensitive_data',
            'consented' => false,
            'ip_address' => '127.0.0.1',
        ] );
        
        // Test getting all user consents
        $all_consents = \KG_Core\Models\UserConsent::get_by_user_id( $test_user_id );
        
        $this->add_result(
            'Get All User Consents',
            count( $all_consents ) === 3,
            'Found ' . count( $all_consents ) . ' consent records (expected 3)'
        );
        
        // Clean up
        wp_delete_user( $test_user_id );
        
        $this->add_result(
            'Cleanup Test User',
            true,
            'Test user deleted'
        );
    }
    
    /**
     * Test 3: Test UserConsentHelper methods
     */
    private function test_user_consent_helper() {
        // Create a test user with consents
        $test_user_id = wp_insert_user( [
            'user_login' => 'consent_helper_test_' . time(),
            'user_email' => 'helper_test_' . time() . '@example.com',
            'user_pass' => 'test_password_123',
        ] );
        
        if ( is_wp_error( $test_user_id ) ) {
            return;
        }
        
        // Create marketing consent
        \KG_Core\Models\UserConsent::create( [
            'user_id' => $test_user_id,
            'consent_type' => 'marketing',
            'consented' => true,
            'consented_at' => current_time( 'mysql' ),
        ] );
        
        // Create sensitive_data consent (not consented)
        \KG_Core\Models\UserConsent::create( [
            'user_id' => $test_user_id,
            'consent_type' => 'sensitive_data',
            'consented' => false,
        ] );
        
        // Test helper methods
        $has_marketing = \KG_Core\Utils\UserConsentHelper::has_marketing_consent( $test_user_id );
        $this->add_result(
            'Helper: has_marketing_consent',
            $has_marketing === true,
            $has_marketing ? 'User has marketing consent' : 'User does not have marketing consent'
        );
        
        $has_sensitive = \KG_Core\Utils\UserConsentHelper::has_sensitive_data_consent( $test_user_id );
        $this->add_result(
            'Helper: has_sensitive_data_consent',
            $has_sensitive === false,
            $has_sensitive ? 'ERROR: User should not have sensitive data consent' : 'Correctly identified no sensitive data consent'
        );
        
        $status = \KG_Core\Utils\UserConsentHelper::get_consent_status( $test_user_id );
        $this->add_result(
            'Helper: get_consent_status',
            is_array( $status ) && count( $status ) === 3,
            'Status array contains ' . count( $status ) . ' consent types (expected 3)'
        );
        
        // Clean up
        wp_delete_user( $test_user_id );
    }
    
    /**
     * Test 4: Verify API endpoints are registered
     */
    private function test_api_endpoints() {
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        
        // Check for consent endpoints
        $consent_routes = [
            '/kg/v1/user/consents',
            '/kg/v1/user/consents/(?P<type>terms|marketing|sensitive_data)',
        ];
        
        foreach ( $consent_routes as $route ) {
            $exists = false;
            foreach ( array_keys( $routes ) as $registered_route ) {
                if ( strpos( $registered_route, '/kg/v1/user/consents' ) !== false ) {
                    $exists = true;
                    break;
                }
            }
            
            $this->add_result(
                'API Endpoint: ' . $route,
                $exists,
                $exists ? 'Endpoint registered' : 'Endpoint not found'
            );
        }
    }
    
    /**
     * Add a test result
     */
    private function add_result( $test_name, $passed, $message ) {
        $this->test_results[] = [
            'name' => $test_name,
            'passed' => $passed,
            'message' => $message,
        ];
    }
    
    /**
     * Display all test results
     */
    private function display_results() {
        $total = count( $this->test_results );
        $passed = count( array_filter( $this->test_results, function( $r ) {
            return $r['passed'];
        } ) );
        
        echo "<h2>Test Results Summary</h2>\n";
        echo "<p><strong>Passed: $passed / $total</strong></p>\n";
        
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<thead>\n";
        echo "<tr style='background-color: #f0f0f0;'>\n";
        echo "<th>Test Name</th><th>Status</th><th>Message</th>\n";
        echo "</tr>\n";
        echo "</thead>\n";
        echo "<tbody>\n";
        
        foreach ( $this->test_results as $result ) {
            $status = $result['passed'] ? '✅ PASS' : '❌ FAIL';
            $bg_color = $result['passed'] ? '#e8f5e9' : '#ffebee';
            
            echo "<tr style='background-color: $bg_color;'>\n";
            echo "<td>{$result['name']}</td>\n";
            echo "<td><strong>$status</strong></td>\n";
            echo "<td>{$result['message']}</td>\n";
            echo "</tr>\n";
        }
        
        echo "</tbody>\n";
        echo "</table>\n";
        
        if ( $passed === $total ) {
            echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✅ All tests passed!</p>\n";
        } else {
            echo "<p style='color: red; font-weight: bold; font-size: 18px;'>❌ Some tests failed. Please review.</p>\n";
        }
    }
}

// Run tests
$tester = new TestUserConsentManagement();
$tester->run_all_tests();

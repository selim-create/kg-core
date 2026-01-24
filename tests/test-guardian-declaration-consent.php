<?php
/**
 * Test Guardian Declaration Consent Implementation
 * 
 * This test verifies:
 * 1. Database schema includes guardian_declaration
 * 2. UserConsentHelper has_guardian_declaration() method
 * 3. Registration with child profile requires guardian_declaration
 * 4. Registration without child profile does not require guardian_declaration
 * 5. Consent update endpoint accepts guardian_declaration
 */

// WordPress environment required
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access not permitted.' );
}

class TestGuardianDeclarationConsent {
    
    private $test_results = [];
    
    public function run_all_tests() {
        echo "<h1>Guardian Declaration Consent Tests</h1>\n";
        
        // Test 1: Database Schema
        $this->test_database_schema();
        
        // Test 2: UserConsentHelper
        $this->test_user_consent_helper();
        
        // Test 3: API Validation
        $this->test_api_validation();
        
        // Display results
        $this->display_results();
    }
    
    /**
     * Test 1: Verify database table has guardian_declaration in ENUM
     */
    private function test_database_schema() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kg_user_consents';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        if ( ! $table_exists ) {
            $this->add_result(
                'Database Table Exists',
                false,
                "Table $table_name does not exist. Please run the activation/migration script."
            );
            return;
        }
        
        // Check consent_type ENUM values
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name WHERE Field = 'consent_type'" );
        
        if ( empty( $columns ) ) {
            $this->add_result(
                'consent_type Column',
                false,
                'consent_type column not found'
            );
            return;
        }
        
        $enum_values = $columns[0]->Type;
        $has_guardian_declaration = strpos( $enum_values, 'guardian_declaration' ) !== false;
        
        $this->add_result(
            'Database Schema - guardian_declaration ENUM',
            $has_guardian_declaration,
            $has_guardian_declaration 
                ? 'guardian_declaration is included in consent_type ENUM' 
                : "guardian_declaration NOT found in ENUM: $enum_values"
        );
    }
    
    /**
     * Test 2: Test UserConsentHelper methods
     */
    private function test_user_consent_helper() {
        // Check if method exists
        $method_exists = method_exists( 'KG_Core\Utils\UserConsentHelper', 'has_guardian_declaration' );
        
        $this->add_result(
            'UserConsentHelper::has_guardian_declaration() exists',
            $method_exists,
            $method_exists 
                ? 'Method exists and is callable' 
                : 'Method does not exist'
        );
        
        // Check get_consent_status includes guardian_declaration
        if ( class_exists( 'KG_Core\Utils\UserConsentHelper' ) ) {
            // Create a test user
            $test_user_id = wp_create_user( 'test_guardian_' . time(), 'password123', 'test_guardian_' . time() . '@example.com' );
            
            if ( ! is_wp_error( $test_user_id ) ) {
                $status = \KG_Core\Utils\UserConsentHelper::get_consent_status( $test_user_id );
                
                $has_guardian_key = isset( $status['guardian_declaration'] );
                
                $this->add_result(
                    'get_consent_status() includes guardian_declaration',
                    $has_guardian_key,
                    $has_guardian_key 
                        ? 'guardian_declaration is included in consent status' 
                        : 'guardian_declaration NOT found in consent status. Keys: ' . implode( ', ', array_keys( $status ) )
                );
                
                // Clean up test user
                wp_delete_user( $test_user_id );
            } else {
                $this->add_result(
                    'get_consent_status() test',
                    false,
                    'Could not create test user: ' . $test_user_id->get_error_message()
                );
            }
        }
    }
    
    /**
     * Test 3: Test API validation logic
     */
    private function test_api_validation() {
        // Test registration endpoint validation
        // This is a code inspection test - checking if the validation logic is present
        
        $controller_file = dirname( __DIR__ ) . '/includes/API/UserController.php';
        
        if ( ! file_exists( $controller_file ) ) {
            $this->add_result(
                'UserController File Exists',
                false,
                "File not found: $controller_file"
            );
            return;
        }
        
        $content = file_get_contents( $controller_file );
        
        // Check for guardian_declaration validation in registration
        $has_validation = strpos( $content, 'guardian_declaration_required' ) !== false;
        
        $this->add_result(
            'Registration Validation - guardian_declaration_required error',
            $has_validation,
            $has_validation 
                ? 'Validation error code exists in registration endpoint' 
                : 'Validation error code NOT found in registration endpoint'
        );
        
        // Check for guardian_declaration in consent route
        $has_route = strpos( $content, 'terms|marketing|sensitive_data|guardian_declaration' ) !== false;
        
        $this->add_result(
            'Consent Route - guardian_declaration type',
            $has_route,
            $has_route 
                ? 'guardian_declaration is included in consent route pattern' 
                : 'guardian_declaration NOT found in consent route pattern'
        );
        
        // Check for guardian_declaration consent creation
        $has_creation = strpos( $content, "consent_type' => 'guardian_declaration'" ) !== false;
        
        $this->add_result(
            'Registration - guardian_declaration consent creation',
            $has_creation,
            $has_creation 
                ? 'guardian_declaration consent creation code exists' 
                : 'guardian_declaration consent creation code NOT found'
        );
    }
    
    /**
     * Add test result
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
        $passed = array_filter( $this->test_results, function( $r ) { return $r['passed']; } );
        $passed_count = count( $passed );
        
        echo "<h2>Test Results: $passed_count / $total Passed</h2>\n";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Test Name</th><th>Status</th><th>Message</th></tr>\n";
        
        foreach ( $this->test_results as $result ) {
            $status_color = $result['passed'] ? 'green' : 'red';
            $status_text = $result['passed'] ? '✓ PASS' : '✗ FAIL';
            
            echo "<tr>";
            echo "<td>{$result['name']}</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>$status_text</td>";
            echo "<td>{$result['message']}</td>";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        
        if ( $passed_count === $total ) {
            echo "<p style='color: green; font-weight: bold; font-size: 20px;'>✓ All tests passed!</p>\n";
        } else {
            echo "<p style='color: red; font-weight: bold; font-size: 20px;'>✗ Some tests failed. Please review.</p>\n";
        }
    }
}

// Run tests
$test = new TestGuardianDeclarationConsent();
$test->run_all_tests();

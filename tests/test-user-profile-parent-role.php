<?php
/**
 * Test User Profile & Parent Role Implementation
 * 
 * This test validates the code changes without requiring WordPress runtime.
 * It performs static analysis of the implemented features.
 */

// Colors for output
define( 'COLOR_GREEN', "\033[32m" );
define( 'COLOR_RED', "\033[31m" );
define( 'COLOR_YELLOW', "\033[33m" );
define( 'COLOR_BLUE', "\033[34m" );
define( 'COLOR_RESET', "\033[0m" );

class UserProfileParentRoleTest {
    
    private $test_count = 0;
    private $passed = 0;
    private $failed = 0;
    
    public function run_all_tests() {
        echo COLOR_BLUE . "\n=== User Profile & Parent Role Code Analysis ===\n" . COLOR_RESET;
        
        $this->test_role_manager_file();
        $this->test_user_controller_file();
        $this->test_google_auth_file();
        
        $this->print_summary();
    }
    
    private function assert_true( $condition, $message ) {
        $this->test_count++;
        if ( $condition ) {
            echo COLOR_GREEN . "✓ PASS: $message\n" . COLOR_RESET;
            $this->passed++;
            return true;
        } else {
            echo COLOR_RED . "✗ FAIL: $message\n" . COLOR_RESET;
            $this->failed++;
            return false;
        }
    }
    
    private function test_role_manager_file() {
        echo COLOR_YELLOW . "\n--- Testing RoleManager.php ---\n" . COLOR_RESET;
        
        $file = __DIR__ . '/includes/Roles/RoleManager.php';
        $content = file_get_contents( $file );
        
        $this->assert_true(
            file_exists( $file ),
            'RoleManager.php file exists'
        );
        
        $this->assert_true(
            strpos( $content, "add_role( 'kg_parent'" ) !== false,
            'kg_parent role is registered'
        );
        
        $this->assert_true(
            strpos( $content, "'kg_manage_children' => true" ) !== false,
            'kg_parent has kg_manage_children capability'
        );
        
        $this->assert_true(
            strpos( $content, "'kg_ask_questions' => true" ) !== false,
            'kg_parent has kg_ask_questions capability'
        );
        
        $this->assert_true(
            strpos( $content, "'kg_create_collections' => true" ) !== false,
            'kg_parent has kg_create_collections capability'
        );
        
        $this->assert_true(
            strpos( $content, "add_filter( 'pre_option_default_role'" ) !== false,
            'Default role filter is added'
        );
        
        $this->assert_true(
            strpos( $content, "public function set_default_role" ) !== false,
            'set_default_role() method exists'
        );
        
        $this->assert_true(
            strpos( $content, "return 'kg_parent';" ) !== false,
            'set_default_role() returns kg_parent'
        );
        
        $this->assert_true(
            strpos( $content, 'public static function is_parent' ) !== false,
            'is_parent() helper method exists'
        );
        
        $this->assert_true(
            strpos( $content, 'public static function is_expert' ) !== false,
            'is_expert() helper method exists'
        );
        
        $this->assert_true(
            strpos( $content, 'public static function get_public_profile_path' ) !== false,
            'get_public_profile_path() helper method exists'
        );
        
        // Check is_expert includes all expert roles
        $this->assert_true(
            strpos( $content, "'kg_expert', 'author', 'editor', 'administrator'" ) !== false,
            'is_expert() checks all expert roles'
        );
        
        // Check get_public_profile_path returns correct paths
        $this->assert_true(
            strpos( $content, "return 'uzman';" ) !== false,
            'get_public_profile_path() returns "uzman" for experts'
        );
        
        $this->assert_true(
            strpos( $content, "return 'profil';" ) !== false,
            'get_public_profile_path() returns "profil" for parents'
        );
    }
    
    private function test_user_controller_file() {
        echo COLOR_YELLOW . "\n--- Testing UserController.php ---\n" . COLOR_RESET;
        
        $file = __DIR__ . '/includes/API/UserController.php';
        $content = file_get_contents( $file );
        
        $this->assert_true(
            file_exists( $file ),
            'UserController.php file exists'
        );
        
        // Test register_user sets kg_parent role
        $this->assert_true(
            strpos( $content, "\$user->set_role( 'kg_parent' );" ) !== false,
            'register_user() sets kg_parent role'
        );
        
        // Test login response includes new fields
        $this->assert_true(
            strpos( $content, "'username' => \$user->user_login" ) !== false,
            'login_user() includes username in response'
        );
        
        $this->assert_true(
            strpos( $content, "'is_expert' => \$is_expert" ) !== false,
            'login_user() includes is_expert in response'
        );
        
        $this->assert_true(
            strpos( $content, "'redirect_url' => \$redirect_url" ) !== false,
            'login_user() includes redirect_url in response'
        );
        
        $this->assert_true(
            strpos( $content, "'/dashboard/expert'" ) !== false && strpos( $content, "'/dashboard'" ) !== false,
            'login_user() sets correct redirect URLs'
        );
        
        // Test update_profile new fields
        $this->assert_true(
            strpos( $content, "\$gender = sanitize_text_field( \$request->get_param( 'gender' )" ) !== false,
            'update_profile() handles gender field'
        );
        
        $this->assert_true(
            strpos( $content, "\$birth_date = sanitize_text_field( \$request->get_param( 'birth_date' )" ) !== false,
            'update_profile() handles birth_date field'
        );
        
        $this->assert_true(
            strpos( $content, "\$biography = sanitize_textarea_field( \$request->get_param( 'biography' )" ) !== false,
            'update_profile() handles biography field'
        );
        
        $this->assert_true(
            strpos( $content, "\$social_links = \$request->get_param( 'social_links' )" ) !== false,
            'update_profile() handles social_links field'
        );
        
        $this->assert_true(
            strpos( $content, "\$show_email = \$request->get_param( 'show_email' )" ) !== false,
            'update_profile() handles show_email field'
        );
        
        $this->assert_true(
            strpos( $content, "\$expertise = \$request->get_param( 'expertise' )" ) !== false,
            'update_profile() handles expertise field'
        );
        
        // Test gender validation
        $this->assert_true(
            strpos( $content, "\$allowed_genders = [ 'male', 'female', 'other' ];" ) !== false,
            'Gender enum validation exists'
        );
        
        // Test birth date validation
        $this->assert_true(
            strpos( $content, "\$birth_date_obj > \$now" ) !== false,
            'Birth date future validation exists'
        );
        
        // Test expert-only field protection
        $this->assert_true(
            strpos( $content, "if ( ! \$is_expert ) {" ) !== false,
            'Expert-only field protection exists'
        );
        
        $this->assert_true(
            strpos( $content, "'not_expert', 'Only experts can set biography'" ) !== false,
            'Biography is protected for experts only'
        );
        
        // Test social links validation
        $this->assert_true(
            strpos( $content, "\$allowed_platforms = [ 'instagram', 'twitter', 'linkedin', 'youtube', 'website' ];" ) !== false,
            'Social links platform validation exists'
        );
        
        // Test get_user_me includes new fields
        $this->assert_true(
            strpos( $content, "\$gender = get_user_meta( \$user_id, '_kg_gender', true );" ) !== false,
            'get_user_me() retrieves gender'
        );
        
        $this->assert_true(
            strpos( $content, "'is_expert' => \$is_expert" ) !== false,
            'get_user_me() includes is_expert'
        );
        
        // Test expert public profile endpoint
        $this->assert_true(
            strpos( $content, "register_rest_route( 'kg/v1', '/expert/public/" ) !== false,
            'Expert public profile route is registered'
        );
        
        $this->assert_true(
            strpos( $content, 'public function get_expert_public_profile' ) !== false,
            'get_expert_public_profile() method exists'
        );
        
        // Test helper methods
        $this->assert_true(
            strpos( $content, 'private function get_user_recipes' ) !== false,
            'get_user_recipes() helper method exists'
        );
        
        $this->assert_true(
            strpos( $content, 'private function get_user_blog_posts' ) !== false,
            'get_user_blog_posts() helper method exists'
        );
        
        $this->assert_true(
            strpos( $content, 'private function get_user_answered_questions' ) !== false,
            'get_user_answered_questions() helper method exists'
        );
        
        $this->assert_true(
            strpos( $content, 'private function count_user_recipes' ) !== false,
            'count_user_recipes() helper method exists'
        );
        
        $this->assert_true(
            strpos( $content, 'private function count_user_blog_posts' ) !== false,
            'count_user_blog_posts() helper method exists'
        );
        
        $this->assert_true(
            strpos( $content, 'private function get_user_answer_count' ) !== false,
            'get_user_answer_count() helper method exists'
        );
        
        $this->assert_true(
            strpos( $content, 'private function get_role_display_name' ) !== false,
            'get_role_display_name() helper method exists'
        );
        
        // Test role display names in Turkish
        $this->assert_true(
            strpos( $content, "'administrator' => 'Yönetici'" ) !== false,
            'Turkish role name for administrator'
        );
        
        $this->assert_true(
            strpos( $content, "'kg_expert' => 'Beslenme Uzmanı'" ) !== false,
            'Turkish role name for kg_expert'
        );
        
        $this->assert_true(
            strpos( $content, "'kg_parent' => 'Ebeveyn'" ) !== false,
            'Turkish role name for kg_parent'
        );
    }
    
    private function test_google_auth_file() {
        echo COLOR_YELLOW . "\n--- Testing GoogleAuth.php ---\n" . COLOR_RESET;
        
        $file = __DIR__ . '/includes/Auth/GoogleAuth.php';
        $content = file_get_contents( $file );
        
        $this->assert_true(
            file_exists( $file ),
            'GoogleAuth.php file exists'
        );
        
        $this->assert_true(
            strpos( $content, "'role' => 'kg_parent'" ) !== false,
            'Google auth creates users with kg_parent role'
        );
        
        // Ensure 'subscriber' is no longer used
        $this->assert_true(
            strpos( $content, "'role' => 'subscriber'" ) === false,
            'Google auth no longer uses subscriber role'
        );
    }
    
    private function print_summary() {
        echo COLOR_BLUE . "\n=== Test Summary ===\n" . COLOR_RESET;
        echo "Total Tests: " . $this->test_count . "\n";
        echo COLOR_GREEN . "Passed: " . $this->passed . "\n" . COLOR_RESET;
        echo COLOR_RED . "Failed: " . $this->failed . "\n" . COLOR_RESET;
        
        if ( $this->failed === 0 ) {
            echo COLOR_GREEN . "\n🎉 All tests passed!\n" . COLOR_RESET;
            echo "\nImplementation Summary:\n";
            echo "✓ kg_parent role created with appropriate capabilities\n";
            echo "✓ Default role filter set to kg_parent\n";
            echo "✓ Helper methods: is_parent(), is_expert(), get_public_profile_path()\n";
            echo "✓ User registration sets kg_parent role\n";
            echo "✓ Google auth sets kg_parent role\n";
            echo "✓ New user meta fields: gender, birth_date, biography, social_links, show_email, expertise\n";
            echo "✓ Field validations: enum for gender, date format and future check for birth_date\n";
            echo "✓ Expert-only field protection: biography, social_links, expertise\n";
            echo "✓ Login response includes: username, is_expert, redirect_url\n";
            echo "✓ Expert public profile endpoint: /kg/v1/expert/public/{username}\n";
            echo "✓ Helper methods for expert profile data\n";
            echo "✓ Turkish role display names\n";
        } else {
            echo COLOR_RED . "\n⚠️  Some tests failed. Please review.\n" . COLOR_RESET;
        }
    }
}

// Run the tests
$test = new UserProfileParentRoleTest();
$test->run_all_tests();


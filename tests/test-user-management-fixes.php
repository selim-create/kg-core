<?php
/**
 * Test Script for User Management Bug Fixes
 * 
 * Tests:
 * 1. Username login support
 * 2. Email login support  
 * 3. KVKK validation flexibility
 * 4. /user/me endpoint fields
 * 5. /auth/me endpoint fields
 * 6. Expert dashboard access
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Test configuration
$test_email = 'testuser_' . time() . '@example.com';
$test_username = 'testuser_' . time();
$test_password = 'TestPass123!';

echo "=== USER MANAGEMENT BUG FIXES TEST ===\n\n";

// ===== TEST 1: Create Test User =====
echo "TEST 1: Creating test user...\n";
$user_id = wp_create_user($test_username, $test_password, $test_email);
if (is_wp_error($user_id)) {
    echo "❌ Failed to create user: " . $user_id->get_error_message() . "\n";
    exit(1);
}
echo "✅ User created: ID=$user_id, Email=$test_email, Username=$test_username\n\n";

// ===== TEST 2: Login with Email =====
echo "TEST 2: Testing login with email...\n";
$request = new WP_REST_Request('POST', '/kg/v1/auth/login');
$request->set_param('email', $test_email);
$request->set_param('password', $test_password);

$controller = new \KG_Core\API\UserController();
$response = $controller->login_user($request);

if (is_wp_error($response)) {
    echo "❌ Email login failed: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✅ Email login successful!\n";
    echo "   Token: " . substr($data['token'], 0, 20) . "...\n";
    echo "   User ID: " . $data['user_id'] . "\n";
    echo "   Email: " . $data['email'] . "\n";
    echo "   Name: " . $data['name'] . "\n";
    echo "   Role: " . $data['role'] . "\n";
    $login_token = $data['token'];
}
echo "\n";

// ===== TEST 3: Login with Username =====
echo "TEST 3: Testing login with username...\n";
$request = new WP_REST_Request('POST', '/kg/v1/auth/login');
$request->set_param('email', $test_username); // Using 'email' param for username
$request->set_param('password', $test_password);

$response = $controller->login_user($request);

if (is_wp_error($response)) {
    echo "❌ Username login failed: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✅ Username login successful!\n";
    echo "   Token: " . substr($data['token'], 0, 20) . "...\n";
    echo "   User ID: " . $data['user_id'] . "\n";
    echo "   Email: " . $data['email'] . "\n";
    echo "   Name: " . $data['name'] . "\n";
    echo "   Role: " . $data['role'] . "\n";
}
echo "\n";

// ===== TEST 4: Test KVKK Validation Flexibility =====
echo "TEST 4: Testing KVKK validation with different truthy values...\n";

// Set up user meta for testing
update_user_meta($user_id, '_kg_display_name', 'Test Parent');
update_user_meta($user_id, '_kg_parent_role', 'Anne');

$kvkk_test_values = [
    true,
    'true',
    1,
    '1',
    'on'
];

foreach ($kvkk_test_values as $kvkk_value) {
    $request = new WP_REST_Request('POST', '/kg/v1/user/children');
    $request->set_param('authenticated_user_id', $user_id);
    $request->set_param('name', 'Test Child');
    $request->set_param('birth_date', '2023-01-01');
    $request->set_param('kvkk_consent', $kvkk_value);
    
    $response = $controller->add_child($request);
    
    if (is_wp_error($response)) {
        echo "❌ KVKK validation failed for value: " . json_encode($kvkk_value) . "\n";
        echo "   Error: " . $response->get_error_message() . "\n";
    } else {
        echo "✅ KVKK validation passed for value: " . json_encode($kvkk_value) . "\n";
        // Delete the child to clean up
        $data = $response->get_data();
        $children = get_user_meta($user_id, '_kg_children', true);
        $children = array_filter($children, function($child) use ($data) {
            return $child['id'] !== $data['id'];
        });
        update_user_meta($user_id, '_kg_children', array_values($children));
    }
}
echo "\n";

// ===== TEST 5: Test /auth/me Endpoint =====
echo "TEST 5: Testing /auth/me endpoint fields...\n";

// Add some test data
$test_children = [
    [
        'id' => wp_generate_uuid4(),
        'name' => 'Test Child',
        'birth_date' => '2023-01-01',
        'gender' => 'unspecified',
        'allergies' => [],
        'feeding_style' => 'mixed',
        'photo_id' => null,
        'kvkk_consent' => true,
        'created_at' => current_time('c')
    ]
];
update_user_meta($user_id, '_kg_children', $test_children);

$request = new WP_REST_Request('GET', '/kg/v1/auth/me');
$request->set_param('authenticated_user_id', $user_id);

$response = $controller->get_current_user($request);

if (is_wp_error($response)) {
    echo "❌ /auth/me failed: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✅ /auth/me successful!\n";
    
    $required_fields = ['user_id', 'id', 'email', 'name', 'display_name', 'parent_role', 'avatar_url', 'children', 'role', 'created_at'];
    foreach ($required_fields as $field) {
        if (array_key_exists($field, $data)) {
            echo "   ✅ Field '$field': " . (is_array($data[$field]) ? json_encode($data[$field]) : $data[$field]) . "\n";
        } else {
            echo "   ❌ Missing field: $field\n";
        }
    }
}
echo "\n";

// ===== TEST 6: Test /user/me Endpoint =====
echo "TEST 6: Testing /user/me endpoint fields...\n";

$request = new WP_REST_Request('GET', '/kg/v1/user/me');
$request->set_param('authenticated_user_id', $user_id);

$response = $controller->get_user_me($request);

if (is_wp_error($response)) {
    echo "❌ /user/me failed: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✅ /user/me successful!\n";
    
    $required_fields = ['id', 'email', 'name', 'display_name', 'parent_role', 'avatar_url', 'children', 'role', 'followed_circles', 'stats'];
    foreach ($required_fields as $field) {
        if (array_key_exists($field, $data)) {
            if (is_array($data[$field])) {
                echo "   ✅ Field '$field': " . json_encode($data[$field]) . "\n";
            } else {
                echo "   ✅ Field '$field': " . $data[$field] . "\n";
            }
        } else {
            echo "   ❌ Missing field: $field\n";
        }
    }
}
echo "\n";

// ===== TEST 7: Test Expert Dashboard (Normal User - Should Fail) =====
echo "TEST 7: Testing expert dashboard access (normal user - should fail)...\n";

$request = new WP_REST_Request('GET', '/kg/v1/expert/dashboard');
$request->set_header('Authorization', 'Bearer ' . $login_token);

$expert_controller = new \KG_Core\API\ExpertController();
$perm_check = $expert_controller->check_expert_permission($request);

if (is_wp_error($perm_check)) {
    echo "✅ Correctly denied access for normal user!\n";
    echo "   Error: " . $perm_check->get_error_message() . "\n";
} else {
    echo "❌ Normal user should not have access to expert dashboard!\n";
}
echo "\n";

// ===== TEST 8: Test Expert Dashboard (Admin User - Should Pass) =====
echo "TEST 8: Testing expert dashboard access (admin user - should pass)...\n";

// Create admin user
$admin_username = 'admin_' . time();
$admin_email = 'admin_' . time() . '@example.com';
$admin_id = wp_create_user($admin_username, $test_password, $admin_email);
$admin_user = get_user_by('id', $admin_id);
$admin_user->set_role('administrator');

// Login as admin
$request = new WP_REST_Request('POST', '/kg/v1/auth/login');
$request->set_param('email', $admin_email);
$request->set_param('password', $test_password);
$response = $controller->login_user($request);
$admin_token = $response->get_data()['token'];

// Test expert dashboard
$request = new WP_REST_Request('GET', '/kg/v1/expert/dashboard');
$request->set_header('Authorization', 'Bearer ' . $admin_token);

$perm_check = $expert_controller->check_expert_permission($request);

if (is_wp_error($perm_check)) {
    echo "❌ Admin should have access: " . $perm_check->get_error_message() . "\n";
} else {
    echo "✅ Admin user has access to expert dashboard!\n";
    
    // Test the actual dashboard endpoint
    $dashboard_response = $expert_controller->get_dashboard($request);
    if (is_wp_error($dashboard_response)) {
        echo "❌ Dashboard failed: " . $dashboard_response->get_error_message() . "\n";
    } else {
        $dashboard_data = $dashboard_response->get_data();
        echo "   Dashboard data:\n";
        echo "   - Pending questions: " . $dashboard_data['pending_questions'] . "\n";
        echo "   - Pending comments: " . $dashboard_data['pending_comments'] . "\n";
        echo "   - Today answers: " . $dashboard_data['today_answers'] . "\n";
        echo "   - Weekly stats: " . json_encode($dashboard_data['weekly_stats']) . "\n";
    }
}
echo "\n";

// ===== CLEANUP =====
echo "CLEANUP: Removing test users...\n";
wp_delete_user($user_id);
wp_delete_user($admin_id);
echo "✅ Test users deleted\n\n";

echo "=== ALL TESTS COMPLETED ===\n";

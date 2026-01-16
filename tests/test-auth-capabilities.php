<?php
/**
 * Test Script for Auth Endpoint Capabilities
 * 
 * Tests:
 * 1. /auth/me endpoint includes capabilities
 * 2. /auth/me endpoint includes role flags
 * 3. /auth/me endpoint includes edit URLs
 * 4. /auth/can-edit endpoint for specific posts
 * 5. Different user roles have correct permissions
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Test configuration
$test_email = 'testcap_' . time() . '@example.com';
$test_username = 'testcap_' . time();
$test_password = 'TestPass123!';

echo "=== AUTH CAPABILITIES TEST ===\n\n";

// ===== TEST 1: Create Test User (Parent Role) =====
echo "TEST 1: Creating test parent user...\n";
$parent_user_id = wp_create_user($test_username, $test_password, $test_email);
if (is_wp_error($parent_user_id)) {
    echo "❌ Failed to create user: " . $parent_user_id->get_error_message() . "\n";
    exit(1);
}

$parent_user = get_user_by('id', $parent_user_id);
$parent_user->set_role('kg_parent');

echo "✅ Parent user created: ID=$parent_user_id, Email=$test_email\n\n";

// ===== TEST 2: Create Test Admin User =====
echo "TEST 2: Creating test admin user...\n";
$admin_email = 'admin_' . time() . '@example.com';
$admin_username = 'admin_' . time();
$admin_user_id = wp_create_user($admin_username, $test_password, $admin_email);
if (is_wp_error($admin_user_id)) {
    echo "❌ Failed to create admin: " . $admin_user_id->get_error_message() . "\n";
    exit(1);
}

$admin_user = get_user_by('id', $admin_user_id);
$admin_user->set_role('administrator');

echo "✅ Admin user created: ID=$admin_user_id, Email=$admin_email\n\n";

// ===== TEST 3: Create Test Expert User =====
echo "TEST 3: Creating test expert user...\n";
$expert_email = 'expert_' . time() . '@example.com';
$expert_username = 'expert_' . time();
$expert_user_id = wp_create_user($expert_username, $test_password, $expert_email);
if (is_wp_error($expert_user_id)) {
    echo "❌ Failed to create expert: " . $expert_user_id->get_error_message() . "\n";
    exit(1);
}

$expert_user = get_user_by('id', $expert_user_id);
$expert_user->set_role('kg_expert');

echo "✅ Expert user created: ID=$expert_user_id, Email=$expert_email\n\n";

// ===== TEST 4: Test /auth/me Endpoint for Parent (Should Have No Edit Permissions) =====
echo "TEST 4: Testing /auth/me for parent user...\n";

$controller = new \KG_Core\API\UserController();
$request = new WP_REST_Request('GET', '/kg/v1/auth/me');
$request->set_param('authenticated_user_id', $parent_user_id);

$response = $controller->get_current_user($request);

if (is_wp_error($response)) {
    echo "❌ /auth/me failed: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✅ /auth/me successful for parent!\n";
    
    // Check new fields exist
    $new_fields = ['capabilities', 'can_edit', 'can_edit_others', 'is_admin', 'is_editor', 'is_expert', 'is_author', 'has_editor_access', 'admin_url', 'edit_urls', 'roles'];
    foreach ($new_fields as $field) {
        if (array_key_exists($field, $data)) {
            echo "   ✅ Field '$field' exists\n";
        } else {
            echo "   ❌ Missing field: $field\n";
        }
    }
    
    // Check role flags for parent
    echo "\n   Role Flags for Parent:\n";
    echo "   - is_admin: " . ($data['is_admin'] ? 'true' : 'false') . " (expected: false)\n";
    echo "   - is_editor: " . ($data['is_editor'] ? 'true' : 'false') . " (expected: false)\n";
    echo "   - is_expert: " . ($data['is_expert'] ? 'true' : 'false') . " (expected: false)\n";
    echo "   - has_editor_access: " . ($data['has_editor_access'] ? 'true' : 'false') . " (expected: false)\n";
    
    if (!$data['is_admin'] && !$data['is_editor'] && !$data['is_expert'] && !$data['has_editor_access']) {
        echo "   ✅ Role flags correct for parent\n";
    } else {
        echo "   ❌ Role flags incorrect for parent\n";
    }
    
    // Check can_edit permissions for parent
    echo "\n   Edit Permissions for Parent:\n";
    echo "   - can_edit.posts: " . ($data['can_edit']['posts'] ? 'true' : 'false') . " (expected: false)\n";
    echo "   - can_edit_others.posts: " . ($data['can_edit_others']['posts'] ? 'true' : 'false') . " (expected: false)\n";
    
    if (!$data['can_edit']['posts'] && !$data['can_edit_others']['posts']) {
        echo "   ✅ Edit permissions correct for parent\n";
    } else {
        echo "   ❌ Edit permissions incorrect for parent\n";
    }
}
echo "\n";

// ===== TEST 5: Test /auth/me Endpoint for Admin (Should Have All Edit Permissions) =====
echo "TEST 5: Testing /auth/me for admin user...\n";

$request = new WP_REST_Request('GET', '/kg/v1/auth/me');
$request->set_param('authenticated_user_id', $admin_user_id);

$response = $controller->get_current_user($request);

if (is_wp_error($response)) {
    echo "❌ /auth/me failed: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✅ /auth/me successful for admin!\n";
    
    // Check role flags for admin
    echo "   Role Flags for Admin:\n";
    echo "   - is_admin: " . ($data['is_admin'] ? 'true' : 'false') . " (expected: true)\n";
    echo "   - is_editor: " . ($data['is_editor'] ? 'true' : 'false') . " (expected: true)\n";
    echo "   - has_editor_access: " . ($data['has_editor_access'] ? 'true' : 'false') . " (expected: true)\n";
    
    if ($data['is_admin'] && $data['is_editor'] && $data['has_editor_access']) {
        echo "   ✅ Role flags correct for admin\n";
    } else {
        echo "   ❌ Role flags incorrect for admin\n";
    }
    
    // Check can_edit permissions for admin
    echo "\n   Edit Permissions for Admin:\n";
    echo "   - can_edit.posts: " . ($data['can_edit']['posts'] ? 'true' : 'false') . " (expected: true)\n";
    echo "   - can_edit_others.posts: " . ($data['can_edit_others']['posts'] ? 'true' : 'false') . " (expected: true)\n";
    echo "   - capabilities.edit_posts: " . ($data['capabilities']['edit_posts'] ? 'true' : 'false') . " (expected: true)\n";
    echo "   - capabilities.edit_others_posts: " . ($data['capabilities']['edit_others_posts'] ? 'true' : 'false') . " (expected: true)\n";
    
    if ($data['can_edit']['posts'] && $data['can_edit_others']['posts']) {
        echo "   ✅ Edit permissions correct for admin\n";
    } else {
        echo "   ❌ Edit permissions incorrect for admin\n";
    }
    
    // Check admin_url
    echo "\n   Admin URL: " . $data['admin_url'] . "\n";
    if (!empty($data['admin_url'])) {
        echo "   ✅ Admin URL exists\n";
    } else {
        echo "   ❌ Admin URL missing\n";
    }
    
    // Check edit_urls
    echo "\n   Edit URLs:\n";
    $edit_url_keys = ['new_post', 'new_recipe', 'new_ingredient', 'new_discussion', 'edit_post', 'edit_recipe', 'edit_ingredient'];
    foreach ($edit_url_keys as $key) {
        if (array_key_exists($key, $data['edit_urls'])) {
            echo "   ✅ edit_urls.$key: " . $data['edit_urls'][$key] . "\n";
        } else {
            echo "   ❌ Missing edit_urls.$key\n";
        }
    }
}
echo "\n";

// ===== TEST 6: Test /auth/me Endpoint for Expert =====
echo "TEST 6: Testing /auth/me for expert user...\n";

$request = new WP_REST_Request('GET', '/kg/v1/auth/me');
$request->set_param('authenticated_user_id', $expert_user_id);

$response = $controller->get_current_user($request);

if (is_wp_error($response)) {
    echo "❌ /auth/me failed: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✅ /auth/me successful for expert!\n";
    
    // Check role flags for expert
    echo "   Role Flags for Expert:\n";
    echo "   - is_admin: " . ($data['is_admin'] ? 'true' : 'false') . " (expected: false)\n";
    echo "   - is_expert: " . ($data['is_expert'] ? 'true' : 'false') . " (expected: true)\n";
    echo "   - has_editor_access: " . ($data['has_editor_access'] ? 'true' : 'false') . " (expected: true)\n";
    
    if (!$data['is_admin'] && $data['is_expert'] && $data['has_editor_access']) {
        echo "   ✅ Role flags correct for expert\n";
    } else {
        echo "   ❌ Role flags incorrect for expert\n";
    }
    
    // Check can_edit permissions for expert
    echo "\n   Edit Permissions for Expert:\n";
    echo "   - can_edit.posts: " . ($data['can_edit']['posts'] ? 'true' : 'false') . " (expected: true)\n";
    echo "   - can_edit_others.posts: " . ($data['can_edit_others']['posts'] ? 'true' : 'false') . " (expected: true)\n";
    
    if ($data['can_edit']['posts'] && $data['can_edit_others']['posts']) {
        echo "   ✅ Edit permissions correct for expert\n";
    } else {
        echo "   ❌ Edit permissions incorrect for expert\n";
    }
}
echo "\n";

// ===== TEST 7: Create Test Post and Test /auth/can-edit Endpoint =====
echo "TEST 7: Testing /auth/can-edit endpoint...\n";

// Create a test post
$post_id = wp_insert_post([
    'post_title'   => 'Test Post for Can Edit',
    'post_content' => 'Test content',
    'post_status'  => 'publish',
    'post_type'    => 'post',
    'post_author'  => $admin_user_id,
]);

if (is_wp_error($post_id)) {
    echo "❌ Failed to create test post: " . $post_id->get_error_message() . "\n";
} else {
    echo "✅ Test post created: ID=$post_id\n";
    
    // Test 7a: Parent user trying to edit post (should be false)
    echo "\n   7a: Parent user checking if can edit post...\n";
    $request = new WP_REST_Request('GET', '/kg/v1/auth/can-edit');
    $request->set_param('authenticated_user_id', $parent_user_id);
    $request->set_param('post_id', $post_id);
    
    $response = $controller->can_edit_content($request);
    if (is_wp_error($response)) {
        echo "   ❌ /auth/can-edit failed: " . $response->get_error_message() . "\n";
    } else {
        $data = $response->get_data();
        echo "   Response: can_edit=" . ($data['can_edit'] ? 'true' : 'false') . ", post_id=" . $data['post_id'] . ", post_type=" . $data['post_type'] . "\n";
        
        if (!$data['can_edit']) {
            echo "   ✅ Parent correctly cannot edit post\n";
        } else {
            echo "   ❌ Parent should not be able to edit post\n";
        }
        
        if ($data['edit_url'] === null) {
            echo "   ✅ Edit URL correctly null for parent\n";
        } else {
            echo "   ❌ Edit URL should be null for parent\n";
        }
    }
    
    // Test 7b: Admin user trying to edit post (should be true)
    echo "\n   7b: Admin user checking if can edit post...\n";
    $request = new WP_REST_Request('GET', '/kg/v1/auth/can-edit');
    $request->set_param('authenticated_user_id', $admin_user_id);
    $request->set_param('post_id', $post_id);
    
    $response = $controller->can_edit_content($request);
    if (is_wp_error($response)) {
        echo "   ❌ /auth/can-edit failed: " . $response->get_error_message() . "\n";
    } else {
        $data = $response->get_data();
        echo "   Response: can_edit=" . ($data['can_edit'] ? 'true' : 'false') . ", post_id=" . $data['post_id'] . ", post_type=" . $data['post_type'] . "\n";
        
        if ($data['can_edit']) {
            echo "   ✅ Admin correctly can edit post\n";
        } else {
            echo "   ❌ Admin should be able to edit post\n";
        }
        
        if ($data['edit_url'] !== null) {
            echo "   ✅ Edit URL provided for admin: " . $data['edit_url'] . "\n";
        } else {
            echo "   ❌ Edit URL should be provided for admin\n";
        }
    }
    
    // Test 7c: Expert user trying to edit post (should be true)
    echo "\n   7c: Expert user checking if can edit post...\n";
    $request = new WP_REST_Request('GET', '/kg/v1/auth/can-edit');
    $request->set_param('authenticated_user_id', $expert_user_id);
    $request->set_param('post_id', $post_id);
    
    $response = $controller->can_edit_content($request);
    if (is_wp_error($response)) {
        echo "   ❌ /auth/can-edit failed: " . $response->get_error_message() . "\n";
    } else {
        $data = $response->get_data();
        echo "   Response: can_edit=" . ($data['can_edit'] ? 'true' : 'false') . ", post_id=" . $data['post_id'] . ", post_type=" . $data['post_type'] . "\n";
        
        if ($data['can_edit']) {
            echo "   ✅ Expert correctly can edit post\n";
        } else {
            echo "   ❌ Expert should be able to edit post\n";
        }
    }
    
    // Test 7d: Test with 'id' parameter instead of 'post_id'
    echo "\n   7d: Testing with 'id' parameter...\n";
    $request = new WP_REST_Request('GET', '/kg/v1/auth/can-edit');
    $request->set_param('authenticated_user_id', $admin_user_id);
    $request->set_param('id', $post_id);
    
    $response = $controller->can_edit_content($request);
    if (is_wp_error($response)) {
        echo "   ❌ /auth/can-edit with 'id' failed: " . $response->get_error_message() . "\n";
    } else {
        $data = $response->get_data();
        if ($data['can_edit']) {
            echo "   ✅ 'id' parameter works correctly\n";
        } else {
            echo "   ❌ 'id' parameter not working\n";
        }
    }
    
    // Clean up: Delete test post
    wp_delete_post($post_id, true);
}
echo "\n";

// ===== TEST 8: Test /auth/can-edit with Non-Existent Post =====
echo "TEST 8: Testing /auth/can-edit with non-existent post...\n";

$request = new WP_REST_Request('GET', '/kg/v1/auth/can-edit');
$request->set_param('authenticated_user_id', $admin_user_id);
$request->set_param('post_id', 999999); // Non-existent post ID

$response = $controller->can_edit_content($request);
if (is_wp_error($response)) {
    echo "❌ Unexpected error: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    if (!$data['can_edit'] && $data['reason'] === 'post_not_found') {
        echo "✅ Correctly handled non-existent post\n";
        echo "   Response: can_edit=false, reason=post_not_found\n";
    } else {
        echo "❌ Should return can_edit=false with reason=post_not_found\n";
    }
}
echo "\n";

// ===== TEST 9: Test /auth/can-edit without Parameters =====
echo "TEST 9: Testing /auth/can-edit without parameters...\n";

$request = new WP_REST_Request('GET', '/kg/v1/auth/can-edit');
$request->set_param('authenticated_user_id', $admin_user_id);

$response = $controller->can_edit_content($request);
if (is_wp_error($response)) {
    $error_code = $response->get_error_code();
    if ($error_code === 'missing_param') {
        echo "✅ Correctly returned error for missing parameters\n";
        echo "   Error: " . $response->get_error_message() . "\n";
    } else {
        echo "❌ Wrong error code: $error_code\n";
    }
} else {
    echo "❌ Should return error for missing parameters\n";
}
echo "\n";

// ===== CLEANUP =====
echo "CLEANUP: Deleting test users...\n";
wp_delete_user($parent_user_id);
wp_delete_user($admin_user_id);
wp_delete_user($expert_user_id);
echo "✅ Test users deleted\n\n";

echo "=== ALL TESTS COMPLETED ===\n";

<?php
/**
 * Test Script for /user/me Authorization Fields
 * 
 * Tests that /kg/v1/user/me endpoint includes all authorization fields:
 * - is_admin
 * - is_editor
 * - is_expert
 * - has_editor_access
 * - can_edit
 * - can_edit_others
 * - admin_url
 * - edit_urls
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Test configuration
$test_email_prefix = 'userme_test_' . time();

echo "=== /user/me AUTHORIZATION FIELDS TEST ===\n\n";

$test_results = [];
$all_passed = true;

// ===== TEST 1: Create Test Admin User =====
echo "TEST 1: Creating test admin user...\n";
$admin_email = $test_email_prefix . '_admin@example.com';
$admin_username = $test_email_prefix . '_admin';
$test_password = 'TestPass123!';

$admin_user_id = wp_create_user($admin_username, $test_password, $admin_email);
if (is_wp_error($admin_user_id)) {
    echo "❌ Failed to create admin: " . $admin_user_id->get_error_message() . "\n";
    exit(1);
}

$admin_user = get_user_by('id', $admin_user_id);
$admin_user->set_role('administrator');

echo "✅ Admin user created: ID=$admin_user_id\n\n";

// ===== TEST 2: Test /user/me Endpoint for Admin =====
echo "TEST 2: Testing /user/me for admin user...\n";

$controller = new \KG_Core\API\UserController();
$request = new WP_REST_Request('GET', '/kg/v1/user/me');
$request->set_param('authenticated_user_id', $admin_user_id);

$response = $controller->get_user_me($request);

if (is_wp_error($response)) {
    echo "❌ /user/me failed: " . $response->get_error_message() . "\n";
    $all_passed = false;
} else {
    $data = $response->get_data();
    echo "✅ /user/me successful for admin!\n\n";
    
    // Check all required authorization fields exist
    echo "Checking authorization fields:\n";
    $required_fields = [
        'is_admin' => 'boolean',
        'is_editor' => 'boolean', 
        'is_expert' => 'boolean',
        'has_editor_access' => 'boolean',
        'can_edit' => 'array',
        'can_edit_others' => 'array',
        'admin_url' => 'string_or_null',
        'edit_urls' => 'array_or_null'
    ];
    
    foreach ($required_fields as $field => $expected_type) {
        if (!array_key_exists($field, $data)) {
            echo "   ❌ Missing field: $field\n";
            $all_passed = false;
            continue;
        }
        
        $value = $data[$field];
        $type_ok = false;
        
        if ($expected_type === 'boolean') {
            $type_ok = is_bool($value);
        } elseif ($expected_type === 'array') {
            $type_ok = is_array($value);
        } elseif ($expected_type === 'string_or_null') {
            $type_ok = is_string($value) || is_null($value);
        } elseif ($expected_type === 'array_or_null') {
            $type_ok = is_array($value) || is_null($value);
        }
        
        if ($type_ok) {
            echo "   ✅ Field '$field' exists with correct type\n";
        } else {
            echo "   ❌ Field '$field' has wrong type (expected: $expected_type)\n";
            $all_passed = false;
        }
    }
    
    echo "\nValidating admin user permissions:\n";
    
    // Check admin flags
    if ($data['is_admin'] === true) {
        echo "   ✅ is_admin = true\n";
    } else {
        echo "   ❌ is_admin should be true, got: " . var_export($data['is_admin'], true) . "\n";
        $all_passed = false;
    }
    
    if ($data['is_editor'] === true) {
        echo "   ✅ is_editor = true (admin includes editor)\n";
    } else {
        echo "   ❌ is_editor should be true for admin\n";
        $all_passed = false;
    }
    
    if ($data['has_editor_access'] === true) {
        echo "   ✅ has_editor_access = true\n";
    } else {
        echo "   ❌ has_editor_access should be true for admin\n";
        $all_passed = false;
    }
    
    // Check admin_url is not null for admin
    if ($data['admin_url'] !== null && is_string($data['admin_url'])) {
        echo "   ✅ admin_url = " . $data['admin_url'] . "\n";
    } else {
        echo "   ❌ admin_url should not be null for admin\n";
        $all_passed = false;
    }
    
    // Check edit_urls is not null and is an array
    if ($data['edit_urls'] !== null && is_array($data['edit_urls'])) {
        echo "   ✅ edit_urls is array with " . count($data['edit_urls']) . " items\n";
        
        // Check required edit_urls keys
        $required_urls = ['new_post', 'new_recipe', 'new_ingredient', 'new_discussion'];
        foreach ($required_urls as $url_key) {
            if (isset($data['edit_urls'][$url_key])) {
                echo "   ✅ edit_urls['$url_key'] = " . $data['edit_urls'][$url_key] . "\n";
            } else {
                echo "   ❌ Missing edit_urls['$url_key']\n";
                $all_passed = false;
            }
        }
    } else {
        echo "   ❌ edit_urls should be array for admin\n";
        $all_passed = false;
    }
    
    // Check can_edit structure
    if (is_array($data['can_edit'])) {
        echo "   ✅ can_edit is array\n";
        $required_keys = ['posts', 'recipes', 'ingredients', 'discussions'];
        foreach ($required_keys as $key) {
            if (isset($data['can_edit'][$key])) {
                echo "   ✅ can_edit['$key'] = " . var_export($data['can_edit'][$key], true) . "\n";
            } else {
                echo "   ❌ Missing can_edit['$key']\n";
                $all_passed = false;
            }
        }
    }
    
    // Check can_edit_others structure
    if (is_array($data['can_edit_others'])) {
        echo "   ✅ can_edit_others is array\n";
        $required_keys = ['posts', 'recipes', 'ingredients', 'discussions'];
        foreach ($required_keys as $key) {
            if (isset($data['can_edit_others'][$key])) {
                echo "   ✅ can_edit_others['$key'] = " . var_export($data['can_edit_others'][$key], true) . "\n";
            } else {
                echo "   ❌ Missing can_edit_others['$key']\n";
                $all_passed = false;
            }
        }
    }
}

echo "\n";

// ===== TEST 3: Create Test Parent User =====
echo "TEST 3: Creating test parent user...\n";
$parent_email = $test_email_prefix . '_parent@example.com';
$parent_username = $test_email_prefix . '_parent';

$parent_user_id = wp_create_user($parent_username, $test_password, $parent_email);
if (is_wp_error($parent_user_id)) {
    echo "❌ Failed to create parent: " . $parent_user_id->get_error_message() . "\n";
    exit(1);
}

$parent_user = get_user_by('id', $parent_user_id);
$parent_user->set_role('kg_parent');

echo "✅ Parent user created: ID=$parent_user_id\n\n";

// ===== TEST 4: Test /user/me Endpoint for Parent =====
echo "TEST 4: Testing /user/me for parent user...\n";

$request = new WP_REST_Request('GET', '/kg/v1/user/me');
$request->set_param('authenticated_user_id', $parent_user_id);

$response = $controller->get_user_me($request);

if (is_wp_error($response)) {
    echo "❌ /user/me failed: " . $response->get_error_message() . "\n";
    $all_passed = false;
} else {
    $data = $response->get_data();
    echo "✅ /user/me successful for parent!\n\n";
    
    echo "Validating parent user permissions:\n";
    
    // Check parent flags (should be false)
    if ($data['is_admin'] === false) {
        echo "   ✅ is_admin = false\n";
    } else {
        echo "   ❌ is_admin should be false for parent\n";
        $all_passed = false;
    }
    
    if ($data['is_editor'] === false) {
        echo "   ✅ is_editor = false\n";
    } else {
        echo "   ❌ is_editor should be false for parent\n";
        $all_passed = false;
    }
    
    if ($data['is_expert'] === false) {
        echo "   ✅ is_expert = false\n";
    } else {
        echo "   ❌ is_expert should be false for parent\n";
        $all_passed = false;
    }
    
    if ($data['has_editor_access'] === false) {
        echo "   ✅ has_editor_access = false\n";
    } else {
        echo "   ❌ has_editor_access should be false for parent\n";
        $all_passed = false;
    }
    
    // Check admin_url is null for parent
    if ($data['admin_url'] === null) {
        echo "   ✅ admin_url = null\n";
    } else {
        echo "   ❌ admin_url should be null for parent, got: " . var_export($data['admin_url'], true) . "\n";
        $all_passed = false;
    }
    
    // Check edit_urls is null for parent
    if ($data['edit_urls'] === null) {
        echo "   ✅ edit_urls = null\n";
    } else {
        echo "   ❌ edit_urls should be null for parent\n";
        $all_passed = false;
    }
    
    // Check can_edit (parent typically has no edit permissions)
    if (is_array($data['can_edit'])) {
        echo "   ✅ can_edit is array (parent has no edit permissions)\n";
    } else {
        echo "   ❌ can_edit should still be an array structure\n";
        $all_passed = false;
    }
}

echo "\n";

// ===== TEST 5: Create Test Expert User =====
echo "TEST 5: Creating test expert user...\n";
$expert_email = $test_email_prefix . '_expert@example.com';
$expert_username = $test_email_prefix . '_expert';

$expert_user_id = wp_create_user($expert_username, $test_password, $expert_email);
if (is_wp_error($expert_user_id)) {
    echo "❌ Failed to create expert: " . $expert_user_id->get_error_message() . "\n";
    exit(1);
}

$expert_user = get_user_by('id', $expert_user_id);
$expert_user->set_role('kg_expert');

echo "✅ Expert user created: ID=$expert_user_id\n\n";

// ===== TEST 6: Test /user/me Endpoint for Expert =====
echo "TEST 6: Testing /user/me for expert user...\n";

$request = new WP_REST_Request('GET', '/kg/v1/user/me');
$request->set_param('authenticated_user_id', $expert_user_id);

$response = $controller->get_user_me($request);

if (is_wp_error($response)) {
    echo "❌ /user/me failed: " . $response->get_error_message() . "\n";
    $all_passed = false;
} else {
    $data = $response->get_data();
    echo "✅ /user/me successful for expert!\n\n";
    
    echo "Validating expert user permissions:\n";
    
    if ($data['is_expert'] === true) {
        echo "   ✅ is_expert = true\n";
    } else {
        echo "   ❌ is_expert should be true\n";
        $all_passed = false;
    }
    
    if ($data['has_editor_access'] === true) {
        echo "   ✅ has_editor_access = true (experts have editor access)\n";
    } else {
        echo "   ❌ has_editor_access should be true for expert\n";
        $all_passed = false;
    }
    
    // Expert should have admin_url and edit_urls because has_editor_access is true
    if ($data['admin_url'] !== null) {
        echo "   ✅ admin_url is not null for expert\n";
    } else {
        echo "   ❌ admin_url should not be null for expert (has editor access)\n";
        $all_passed = false;
    }
    
    if ($data['edit_urls'] !== null && is_array($data['edit_urls'])) {
        echo "   ✅ edit_urls is array for expert (has editor access)\n";
    } else {
        echo "   ❌ edit_urls should be array for expert\n";
        $all_passed = false;
    }
}

echo "\n";

// ===== Cleanup =====
echo "CLEANUP: Removing test users...\n";
wp_delete_user($admin_user_id);
wp_delete_user($parent_user_id);
wp_delete_user($expert_user_id);
echo "✅ Test users removed\n\n";

// ===== Final Result =====
echo "========================================\n";
if ($all_passed) {
    echo "✅ ALL TESTS PASSED!\n";
    echo "========================================\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "========================================\n";
    exit(1);
}

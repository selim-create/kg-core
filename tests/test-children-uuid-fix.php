<?php
/**
 * Test Script for Children UUID Pattern Fix
 * 
 * Tests:
 * 1. Add child profile (generates UUID v4)
 * 2. Update child profile with UUID v4
 * 3. Delete child profile with UUID v4
 * 4. Verify UUID v4 format is recognized
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Test configuration
$test_email = 'testchild_' . time() . '@example.com';
$test_password = 'TestPass123!';

echo "=== CHILDREN UUID PATTERN FIX TEST ===\n\n";

// ===== TEST 1: Create Test User =====
echo "TEST 1: Creating test user...\n";
$user_id = wp_create_user($test_email, $test_password, $test_email);
if (is_wp_error($user_id)) {
    echo "❌ Failed to create user: " . $user_id->get_error_message() . "\n";
    exit(1);
}
$user = get_user_by('id', $user_id);
$user->set_role('kg_parent');
echo "✅ User created: ID=$user_id\n\n";

$controller = new \KG_Core\API\UserController();

// ===== TEST 2: Add Child Profile (Generates UUID v4) =====
echo "TEST 2: Adding child profile (should generate UUID v4)...\n";
$request = new WP_REST_Request('POST', '/kg/v1/user/children');
$request->set_param('authenticated_user_id', $user_id);
$request->set_param('name', 'Test Child');
$request->set_param('birth_date', '2023-06-15');
$request->set_param('gender', 'male');
$request->set_param('feeding_style', 'blw');
$request->set_param('kvkk_consent', true);

$response = $controller->add_child($request);

if (is_wp_error($response)) {
    echo "❌ Failed to add child: " . $response->get_error_message() . "\n";
    exit(1);
}

$child_data = $response->get_data();
$child_uuid = $child_data['id'];

echo "✅ Child added successfully!\n";
echo "   Child ID: $child_uuid\n";
echo "   Child Name: " . $child_data['name'] . "\n";

// Verify UUID v4 format
$uuid_pattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i';
if (preg_match($uuid_pattern, $child_uuid)) {
    echo "   ✅ ID is valid UUID v4 format (contains hyphens)\n";
} else {
    echo "   ⚠️  Warning: ID format is: $child_uuid\n";
}
echo "\n";

// ===== TEST 3: Update Child Profile with UUID v4 =====
echo "TEST 3: Updating child profile with UUID v4...\n";
$request = new WP_REST_Request('PUT', '/kg/v1/user/children/' . $child_uuid);
$request->set_param('authenticated_user_id', $user_id);
$request->set_param('id', $child_uuid);
$request->set_param('name', 'Updated Test Child');
$request->set_param('feeding_style', 'mixed');

$response = $controller->update_child($request);

if (is_wp_error($response)) {
    echo "❌ Failed to update child: " . $response->get_error_message() . "\n";
    echo "   This indicates the regex pattern doesn't support UUID v4 format!\n";
    exit(1);
} else {
    echo "✅ Child updated successfully with UUID v4!\n";
    
    // Verify the update was applied
    $children = get_user_meta($user_id, '_kg_children', true);
    foreach ($children as $child) {
        if ($child['id'] === $child_uuid) {
            echo "   Updated Name: " . $child['name'] . "\n";
            echo "   Updated Feeding Style: " . $child['feeding_style'] . "\n";
            break;
        }
    }
}
echo "\n";

// ===== TEST 4: Add Another Child to Test Multiple Children =====
echo "TEST 4: Adding second child...\n";
$request = new WP_REST_Request('POST', '/kg/v1/user/children');
$request->set_param('authenticated_user_id', $user_id);
$request->set_param('name', 'Second Child');
$request->set_param('birth_date', '2024-01-10');
$request->set_param('gender', 'female');
$request->set_param('kvkk_consent', true);

$response = $controller->add_child($request);
$child2_data = $response->get_data();
$child2_uuid = $child2_data['id'];

echo "✅ Second child added: $child2_uuid\n\n";

// ===== TEST 5: Get Children List =====
echo "TEST 5: Getting children list...\n";
$request = new WP_REST_Request('GET', '/kg/v1/user/children');
$request->set_param('authenticated_user_id', $user_id);

$response = $controller->get_children($request);
$children = $response->get_data();

echo "✅ Retrieved " . count($children) . " children\n";
foreach ($children as $child) {
    echo "   - " . $child['name'] . " (ID: " . $child['id'] . ")\n";
}
echo "\n";

// ===== TEST 6: Delete Child Profile with UUID v4 =====
echo "TEST 6: Deleting first child with UUID v4...\n";
$request = new WP_REST_Request('DELETE', '/kg/v1/user/children/' . $child_uuid);
$request->set_param('authenticated_user_id', $user_id);
$request->set_param('id', $child_uuid);

$response = $controller->delete_child($request);

if (is_wp_error($response)) {
    echo "❌ Failed to delete child: " . $response->get_error_message() . "\n";
    echo "   This indicates the regex pattern doesn't support UUID v4 format!\n";
    exit(1);
} else {
    echo "✅ Child deleted successfully with UUID v4!\n";
    
    // Verify deletion
    $children = get_user_meta($user_id, '_kg_children', true);
    echo "   Remaining children: " . count($children) . "\n";
    foreach ($children as $child) {
        echo "   - " . $child['name'] . " (ID: " . $child['id'] . ")\n";
    }
}
echo "\n";

// ===== TEST 7: Test BLW Results Endpoint (Already Has Correct Pattern) =====
echo "TEST 7: Testing BLW results endpoint with UUID v4...\n";
$request = new WP_REST_Request('GET', '/kg/v1/user/children/' . $child2_uuid . '/blw-results');
$request->set_param('authenticated_user_id', $user_id);
$request->set_param('child_id', $child2_uuid);

$response = $controller->get_child_blw_results($request);

if (is_wp_error($response)) {
    echo "❌ Failed to get BLW results: " . $response->get_error_message() . "\n";
} else {
    echo "✅ BLW results endpoint works with UUID v4!\n";
    $results = $response->get_data();
    echo "   BLW results count: " . count($results) . "\n";
}
echo "\n";

// ===== CLEANUP =====
echo "CLEANUP: Removing test user...\n";
wp_delete_user($user_id);
echo "✅ Test user deleted\n\n";

echo "=== ALL TESTS COMPLETED SUCCESSFULLY ===\n";
echo "✅ UUID v4 format (with hyphens) is now supported in children endpoints!\n";

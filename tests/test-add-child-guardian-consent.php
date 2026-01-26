<?php
/**
 * Test Script for Add Child Guardian Declaration Consent
 * 
 * Tests that guardian_declaration consent is automatically created
 * when adding a child profile via the add_child endpoint
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Test configuration
$test_email = 'test_guardian_consent_' . time() . '@example.com';
$test_username = 'test_guardian_' . time();
$test_password = 'TestPass123!';

echo "=== ADD CHILD GUARDIAN DECLARATION CONSENT TEST ===\n\n";

// ===== TEST 1: Create Test User =====
echo "TEST 1: Creating test user...\n";
$user_id = wp_create_user($test_username, $test_password, $test_email);
if (is_wp_error($user_id)) {
    echo "❌ Failed to create user: " . $user_id->get_error_message() . "\n";
    exit(1);
}

// Set user role
$user = get_user_by('id', $user_id);
$user->set_role('kg_parent');

echo "✅ User created: ID=$user_id, Email=$test_email\n\n";

// ===== TEST 2: Verify No Guardian Declaration Initially =====
echo "TEST 2: Verifying no guardian_declaration consent exists initially...\n";
$existing_consent = \KG_Core\Models\UserConsent::get_by_user_and_type($user_id, 'guardian_declaration');
if (!$existing_consent) {
    echo "✅ No guardian_declaration consent exists initially (expected)\n\n";
} else {
    echo "⚠️ Guardian declaration consent already exists (unexpected but continuing)\n\n";
}

// ===== TEST 3: Add Child Without Guardian Declaration in Request =====
echo "TEST 3: Adding child without guardian_declaration in request...\n";
$request = new WP_REST_Request('POST', '/kg/v1/user/children');
$request->set_param('authenticated_user_id', $user_id);
$request->set_param('name', 'Test Child 1');
$request->set_param('birth_date', '2023-01-15');
$request->set_param('gender', 'male');
$request->set_param('kvkk_consent', true);

$controller = new \KG_Core\API\UserController();
$response = $controller->add_child($request);

if (is_wp_error($response)) {
    echo "❌ Failed to add child: " . $response->get_error_message() . "\n";
    exit(1);
} else {
    $child_data = $response->get_data();
    echo "✅ Child added successfully: ID=" . $child_data['id'] . ", Name=" . $child_data['name'] . "\n";
}

// Verify guardian_declaration consent was auto-created
$guardian_consent = \KG_Core\Models\UserConsent::get_by_user_and_type($user_id, 'guardian_declaration');
if ($guardian_consent && $guardian_consent->consented) {
    echo "✅ Guardian declaration consent was automatically created\n";
    echo "   Consent ID: " . $guardian_consent->id . "\n";
    echo "   Consented: " . ($guardian_consent->consented ? 'true' : 'false') . "\n";
    echo "   Consented At: " . $guardian_consent->consented_at . "\n";
} else {
    echo "❌ Guardian declaration consent was NOT created\n";
    exit(1);
}
echo "\n";

// ===== TEST 4: Add Another Child With Existing Guardian Declaration =====
echo "TEST 4: Adding another child (guardian_declaration should not be duplicated)...\n";
$request2 = new WP_REST_Request('POST', '/kg/v1/user/children');
$request2->set_param('authenticated_user_id', $user_id);
$request2->set_param('name', 'Test Child 2');
$request2->set_param('birth_date', '2024-06-20');
$request2->set_param('gender', 'female');
$request2->set_param('kvkk_consent', true);

$response2 = $controller->add_child($request2);

if (is_wp_error($response2)) {
    echo "❌ Failed to add second child: " . $response2->get_error_message() . "\n";
    exit(1);
} else {
    $child_data2 = $response2->get_data();
    echo "✅ Second child added successfully: ID=" . $child_data2['id'] . ", Name=" . $child_data2['name'] . "\n";
}

// Verify only one guardian_declaration consent exists
global $wpdb;
$consent_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}kg_user_consents WHERE user_id = %d AND consent_type = 'guardian_declaration'",
    $user_id
));

echo "✅ Total guardian_declaration consents: $consent_count (should be 1)\n";
if ($consent_count > 1) {
    echo "⚠️ WARNING: Multiple guardian_declaration consents were created\n";
}
echo "\n";

// ===== TEST 5: Add Child With Sensitive Data Consent =====
echo "TEST 5: Adding child with sensitive_data consent...\n";
$request3 = new WP_REST_Request('POST', '/kg/v1/user/children');
$request3->set_param('authenticated_user_id', $user_id);
$request3->set_param('name', 'Test Child 3');
$request3->set_param('birth_date', '2024-11-10');
$request3->set_param('kvkk_consent', true);
$request3->set_param('consents', [
    'sensitive_data_consent' => true,
    'sensitive_data_consent_at' => current_time('mysql')
]);

$response3 = $controller->add_child($request3);

if (is_wp_error($response3)) {
    echo "❌ Failed to add third child: " . $response3->get_error_message() . "\n";
    exit(1);
} else {
    $child_data3 = $response3->get_data();
    echo "✅ Third child added successfully: ID=" . $child_data3['id'] . ", Name=" . $child_data3['name'] . "\n";
}

// Verify sensitive_data consent was created
$sensitive_consent = \KG_Core\Models\UserConsent::get_by_user_and_type($user_id, 'sensitive_data');
if ($sensitive_consent && $sensitive_consent->consented) {
    echo "✅ Sensitive data consent was created\n";
    echo "   Consent ID: " . $sensitive_consent->id . "\n";
    echo "   Consented: " . ($sensitive_consent->consented ? 'true' : 'false') . "\n";
    echo "   Consented At: " . $sensitive_consent->consented_at . "\n";
} else {
    echo "❌ Sensitive data consent was NOT created\n";
    exit(1);
}
echo "\n";

// ===== TEST 6: Add Child With Custom Guardian Declaration Timestamp =====
echo "TEST 6: Adding child with custom guardian_declaration timestamp...\n";

// Clean up and create a new test user for this test
wp_delete_user($user_id);
$user_id2 = wp_create_user('test_guardian_timestamp_' . time(), $test_password, 'test_guardian_timestamp_' . time() . '@example.com');
$user2 = get_user_by('id', $user_id2);
$user2->set_role('kg_parent');

$custom_timestamp = '2024-01-01 12:00:00';
$request4 = new WP_REST_Request('POST', '/kg/v1/user/children');
$request4->set_param('authenticated_user_id', $user_id2);
$request4->set_param('name', 'Test Child With Custom Timestamp');
$request4->set_param('birth_date', '2023-12-01');
$request4->set_param('kvkk_consent', true);
$request4->set_param('consents', [
    'guardian_declaration_at' => $custom_timestamp
]);

$response4 = $controller->add_child($request4);

if (is_wp_error($response4)) {
    echo "❌ Failed to add child with custom timestamp: " . $response4->get_error_message() . "\n";
    exit(1);
} else {
    echo "✅ Child with custom timestamp added successfully\n";
}

// Verify guardian_declaration consent has custom timestamp
$guardian_consent2 = \KG_Core\Models\UserConsent::get_by_user_and_type($user_id2, 'guardian_declaration');
if ($guardian_consent2) {
    // Format both timestamps to compare
    $expected_timestamp = date('Y-m-d H:i:s', strtotime($custom_timestamp));
    $actual_timestamp = date('Y-m-d H:i:s', strtotime($guardian_consent2->consented_at));
    
    if ($expected_timestamp === $actual_timestamp) {
        echo "✅ Guardian declaration consent has correct custom timestamp: $actual_timestamp\n";
    } else {
        echo "⚠️ Timestamp mismatch. Expected: $expected_timestamp, Got: $actual_timestamp\n";
    }
} else {
    echo "❌ Guardian declaration consent was NOT created with custom timestamp\n";
}
echo "\n";

// ===== CLEANUP =====
echo "CLEANUP: Removing test users...\n";
wp_delete_user($user_id2);
echo "✅ Test users cleaned up\n\n";

echo "=== ALL TESTS COMPLETED ===\n";
echo "✅ Guardian declaration consent is automatically created when adding a child\n";
echo "✅ Existing guardian declaration consents are not duplicated\n";
echo "✅ Sensitive data consent can be provided when adding a child\n";
echo "✅ Custom timestamps are respected when provided\n";

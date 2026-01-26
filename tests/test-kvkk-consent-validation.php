<?php
/**
 * Test Script for KVKK Consent Validation Fix
 * 
 * Tests that add_child accepts both old kvkk_consent format 
 * and new consents.guardian_declaration format
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

echo "=== KVKK CONSENT VALIDATION TEST ===\n\n";

// ===== TEST 1: Old Format - kvkk_consent =====
echo "TEST 1: Adding child with OLD format (kvkk_consent)...\n";
$user_id_1 = wp_create_user('test_old_format_' . time(), 'TestPass123!', 'test_old_' . time() . '@example.com');
$user_1 = get_user_by('id', $user_id_1);
$user_1->set_role('kg_parent');

$request_1 = new WP_REST_Request('POST', '/kg/v1/user/children');
$request_1->set_param('authenticated_user_id', $user_id_1);
$request_1->set_param('name', 'Old Format Child');
$request_1->set_param('birth_date', '2023-01-15');
$request_1->set_param('gender', 'male');
$request_1->set_param('kvkk_consent', true);

$controller = new \KG_Core\API\UserController();
$response_1 = $controller->add_child($request_1);

if (is_wp_error($response_1)) {
    echo "❌ FAILED: " . $response_1->get_error_message() . "\n";
    echo "   Error Code: " . $response_1->get_error_code() . "\n";
    exit(1);
} else {
    echo "✅ SUCCESS: Child added with old kvkk_consent format\n";
    $child_data = $response_1->get_data();
    echo "   Child ID: " . $child_data['id'] . "\n";
    echo "   Child Name: " . $child_data['name'] . "\n";
}
echo "\n";

// ===== TEST 2: New Format - consents.guardian_declaration =====
echo "TEST 2: Adding child with NEW format (consents.guardian_declaration)...\n";
$user_id_2 = wp_create_user('test_new_format_' . time(), 'TestPass123!', 'test_new_' . time() . '@example.com');
$user_2 = get_user_by('id', $user_id_2);
$user_2->set_role('kg_parent');

$request_2 = new WP_REST_Request('POST', '/kg/v1/user/children');
$request_2->set_param('authenticated_user_id', $user_id_2);
$request_2->set_param('name', 'Manti');
$request_2->set_param('birth_date', '2017-02-01');
$request_2->set_param('gender', 'male');
$request_2->set_param('allergies', ['Tavuk']);
$request_2->set_param('feeding_style', 'mixed');
$request_2->set_param('consents', [
    'guardian_declaration' => true,
    'guardian_declaration_at' => '2026-01-26T21:31:37.039Z',
    'sensitive_data_consent' => true,
    'sensitive_data_consent_at' => '2026-01-26T21:31:37.039Z'
]);

$response_2 = $controller->add_child($request_2);

if (is_wp_error($response_2)) {
    echo "❌ FAILED: " . $response_2->get_error_message() . "\n";
    echo "   Error Code: " . $response_2->get_error_code() . "\n";
    exit(1);
} else {
    echo "✅ SUCCESS: Child added with new consents.guardian_declaration format\n";
    $child_data = $response_2->get_data();
    echo "   Child ID: " . $child_data['id'] . "\n";
    echo "   Child Name: " . $child_data['name'] . "\n";
}

// Verify guardian_declaration consent was created
$guardian_consent = \KG_Core\Models\UserConsent::get_by_user_and_type($user_id_2, 'guardian_declaration');
if ($guardian_consent && $guardian_consent->consented) {
    echo "✅ Guardian declaration consent created\n";
} else {
    echo "❌ Guardian declaration consent NOT created\n";
    exit(1);
}

// Verify sensitive_data consent was created
$sensitive_consent = \KG_Core\Models\UserConsent::get_by_user_and_type($user_id_2, 'sensitive_data');
if ($sensitive_consent && $sensitive_consent->consented) {
    echo "✅ Sensitive data consent created\n";
} else {
    echo "❌ Sensitive data consent NOT created\n";
    exit(1);
}
echo "\n";

// ===== TEST 3: Missing Consent - Should Fail =====
echo "TEST 3: Adding child WITHOUT any consent (should fail)...\n";
$user_id_3 = wp_create_user('test_no_consent_' . time(), 'TestPass123!', 'test_no_consent_' . time() . '@example.com');
$user_3 = get_user_by('id', $user_id_3);
$user_3->set_role('kg_parent');

$request_3 = new WP_REST_Request('POST', '/kg/v1/user/children');
$request_3->set_param('authenticated_user_id', $user_id_3);
$request_3->set_param('name', 'No Consent Child');
$request_3->set_param('birth_date', '2023-01-15');
$request_3->set_param('gender', 'female');

$response_3 = $controller->add_child($request_3);

if (is_wp_error($response_3)) {
    if ($response_3->get_error_code() === 'kvkk_consent_required') {
        echo "✅ CORRECTLY REJECTED: Missing consent validation works\n";
        echo "   Error Message: " . $response_3->get_error_message() . "\n";
    } else {
        echo "⚠️ UNEXPECTED ERROR: " . $response_3->get_error_code() . " - " . $response_3->get_error_message() . "\n";
    }
} else {
    echo "❌ FAILED: Should have rejected request without consent\n";
    exit(1);
}
echo "\n";

// ===== TEST 4: Both Formats Together =====
echo "TEST 4: Adding child with BOTH old and new format (should work)...\n";
$user_id_4 = wp_create_user('test_both_format_' . time(), 'TestPass123!', 'test_both_' . time() . '@example.com');
$user_4 = get_user_by('id', $user_id_4);
$user_4->set_role('kg_parent');

$request_4 = new WP_REST_Request('POST', '/kg/v1/user/children');
$request_4->set_param('authenticated_user_id', $user_id_4);
$request_4->set_param('name', 'Both Formats Child');
$request_4->set_param('birth_date', '2023-01-15');
$request_4->set_param('gender', 'male');
$request_4->set_param('kvkk_consent', true);
$request_4->set_param('consents', [
    'guardian_declaration' => true,
    'guardian_declaration_at' => '2026-01-26T21:31:37.039Z'
]);

$response_4 = $controller->add_child($request_4);

if (is_wp_error($response_4)) {
    echo "❌ FAILED: " . $response_4->get_error_message() . "\n";
    exit(1);
} else {
    echo "✅ SUCCESS: Child added with both consent formats\n";
}
echo "\n";

// ===== CLEANUP =====
echo "CLEANUP: Removing test users...\n";
wp_delete_user($user_id_1);
wp_delete_user($user_id_2);
wp_delete_user($user_id_3);
wp_delete_user($user_id_4);
echo "✅ Test users cleaned up\n\n";

echo "=== ALL TESTS PASSED ===\n";
echo "✅ Old format (kvkk_consent) works\n";
echo "✅ New format (consents.guardian_declaration) works\n";
echo "✅ Missing consent is properly rejected\n";
echo "✅ Both formats together work\n";
echo "✅ Guardian declaration consent is saved\n";
echo "✅ Sensitive data consent is saved\n";

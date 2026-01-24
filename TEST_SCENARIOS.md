# Guardian Declaration Consent - Test Scenarios

This document provides step-by-step test scenarios to verify the guardian_declaration consent implementation.

## Pre-requisites

1. WordPress installation with kg-core plugin activated
2. Run migration script: `tests/run-guardian-declaration-migration.php`
3. Verify database schema updated (guardian_declaration in ENUM)

## Test Scenario 1: Registration WITHOUT Child Profile

### Expected Behavior
Registration should succeed without requiring guardian_declaration consent.

### Steps
1. Send POST request to `/wp-json/kg/v1/auth/register`
2. Request body:
```json
{
  "email": "test1@example.com",
  "password": "password123",
  "name": "Test User 1",
  "consents": {
    "terms_accepted": true,
    "terms_accepted_at": "2026-01-24T10:00:00.000Z"
  }
}
```

### Expected Result
- ✅ Status: 201 Created
- ✅ Response includes: token, user_id, email, name, children (empty array)
- ✅ No error about guardian_declaration

### Verification
```bash
curl -X POST http://localhost/wp-json/kg/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test1@example.com",
    "password": "password123",
    "name": "Test User 1",
    "consents": {
      "terms_accepted": true,
      "terms_accepted_at": "2026-01-24T10:00:00.000Z"
    }
  }'
```

---

## Test Scenario 2: Registration WITH Child Profile BUT NO guardian_declaration

### Expected Behavior
Registration should FAIL with guardian_declaration_required error.

### Steps
1. Send POST request to `/wp-json/kg/v1/auth/register`
2. Request body:
```json
{
  "email": "test2@example.com",
  "password": "password123",
  "name": "Test User 2",
  "child": {
    "name": "Test Child",
    "birth_date": "2023-06-15"
  },
  "consents": {
    "terms_accepted": true,
    "terms_accepted_at": "2026-01-24T10:00:00.000Z"
  }
}
```

### Expected Result
- ✅ Status: 400 Bad Request
- ✅ Error code: `guardian_declaration_required`
- ✅ Error message: "Çocuk profili eklemek için veli/vasi beyanını onaylamanız gerekmektedir."

### Verification
```bash
curl -X POST http://localhost/wp-json/kg/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test2@example.com",
    "password": "password123",
    "name": "Test User 2",
    "child": {
      "name": "Test Child",
      "birth_date": "2023-06-15"
    },
    "consents": {
      "terms_accepted": true,
      "terms_accepted_at": "2026-01-24T10:00:00.000Z"
    }
  }'
```

---

## Test Scenario 3: Registration WITH Child Profile AND guardian_declaration

### Expected Behavior
Registration should succeed with both child profile and guardian_declaration consent.

### Steps
1. Send POST request to `/wp-json/kg/v1/auth/register`
2. Request body:
```json
{
  "email": "test3@example.com",
  "password": "password123",
  "name": "Test User 3",
  "child": {
    "name": "Test Child",
    "birth_date": "2023-06-15"
  },
  "consents": {
    "terms_accepted": true,
    "terms_accepted_at": "2026-01-24T10:00:00.000Z",
    "guardian_declaration": true,
    "guardian_declaration_at": "2026-01-24T10:00:00.000Z"
  }
}
```

### Expected Result
- ✅ Status: 201 Created
- ✅ Response includes: token, user_id, email, name, children (with child object)
- ✅ Child object includes: id, name, birth_date, gender, allergies, feeding_style, photo_id, kvkk_consent, created_at

### Verification
```bash
curl -X POST http://localhost/wp-json/kg/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test3@example.com",
    "password": "password123",
    "name": "Test User 3",
    "child": {
      "name": "Test Child",
      "birth_date": "2023-06-15"
    },
    "consents": {
      "terms_accepted": true,
      "terms_accepted_at": "2026-01-24T10:00:00.000Z",
      "guardian_declaration": true,
      "guardian_declaration_at": "2026-01-24T10:00:00.000Z"
    }
  }'
```

---

## Test Scenario 4: Get User Consents (includes guardian_declaration)

### Expected Behavior
GET /user/consents should return guardian_declaration in the list.

### Steps
1. Login or register a user (use token from test scenario 3)
2. Send GET request to `/wp-json/kg/v1/user/consents`

### Expected Result
- ✅ Status: 200 OK
- ✅ Response is array of consent objects
- ✅ Array includes consent with `consent_type: "guardian_declaration"`
- ✅ Guardian declaration consent has `consented: true`

### Verification
```bash
curl -X GET http://localhost/wp-json/kg/v1/user/consents \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## Test Scenario 5: Update guardian_declaration Consent

### Expected Behavior
PUT /user/consents/guardian_declaration should update the consent.

### Steps
1. Login as a user (use token)
2. Send PUT request to `/wp-json/kg/v1/user/consents/guardian_declaration`
3. Request body:
```json
{
  "consented": true
}
```

### Expected Result
- ✅ Status: 200 OK
- ✅ Response includes: success: true, message: "Consent updated successfully"
- ✅ consent_type: "guardian_declaration", consented: true

### Verification
```bash
curl -X PUT http://localhost/wp-json/kg/v1/user/consents/guardian_declaration \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"consented": true}'
```

---

## Test Scenario 6: PHP Helper Method - has_guardian_declaration()

### Expected Behavior
PHP helper should correctly identify users with guardian_declaration consent.

### Steps
1. In WordPress admin or a PHP test file:
```php
use KG_Core\Utils\UserConsentHelper;

$user_id = 123; // Use actual user ID from test scenario 3

if ( UserConsentHelper::has_guardian_declaration( $user_id ) ) {
    echo "User has guardian declaration consent";
} else {
    echo "User does NOT have guardian declaration consent";
}
```

### Expected Result
- ✅ For user from scenario 3: Should output "User has guardian declaration consent"
- ✅ For user from scenario 1: Should output "User does NOT have guardian declaration consent"

---

## Test Scenario 7: PHP Helper Method - get_consent_status()

### Expected Behavior
get_consent_status() should include guardian_declaration in the result.

### Steps
1. In WordPress admin or a PHP test file:
```php
use KG_Core\Utils\UserConsentHelper;

$user_id = 123; // Use actual user ID

$status = UserConsentHelper::get_consent_status( $user_id );
print_r( $status );
```

### Expected Result
- ✅ Array contains key `guardian_declaration`
- ✅ Value is an array with keys: consented, consented_at, revoked_at, version

---

## Test Scenario 8: Database Migration - Auto-Grant for Existing Users

### Expected Behavior
Migration should auto-grant guardian_declaration to users who already have children.

### Steps
1. Create a user with children BEFORE running migration (simulate existing data)
2. Run migration: `tests/run-guardian-declaration-migration.php`
3. Check user's consents

### Expected Result
- ✅ Migration completes successfully
- ✅ User with existing children receives guardian_declaration consent
- ✅ consented: true, consented_at: timestamp

### Verification
```php
global $wpdb;
$table = $wpdb->prefix . 'kg_user_consents';
$consents = $wpdb->get_results(
    "SELECT * FROM $table WHERE consent_type = 'guardian_declaration'"
);
print_r( $consents );
```

---

## Test Scenario 9: Invalid Date Format

### Expected Behavior
Should return error if guardian_declaration_at has invalid format.

### Steps
1. Send POST request with invalid date:
```json
{
  "email": "test9@example.com",
  "password": "password123",
  "name": "Test User 9",
  "child": {
    "name": "Test Child",
    "birth_date": "2023-06-15"
  },
  "consents": {
    "terms_accepted": true,
    "terms_accepted_at": "2026-01-24T10:00:00.000Z",
    "guardian_declaration": true,
    "guardian_declaration_at": "invalid-date"
  }
}
```

### Expected Result
- ✅ Status: 400 Bad Request
- ✅ Error code: `invalid_date`
- ✅ Error message: "Invalid guardian declaration date format"

---

## Test Scenario 10: Integration Test Script

### Steps
1. Access: `/wp-content/plugins/kg-core/tests/integration-test-guardian-declaration.php`
2. Review test results

### Expected Result
- ✅ All tests pass
- ✅ Helper methods work correctly
- ✅ Database ENUM includes guardian_declaration

---

## Checklist Summary

After running all scenarios, verify:

- [ ] Registration without child works (no guardian_declaration required)
- [ ] Registration with child but no guardian_declaration fails
- [ ] Registration with child and guardian_declaration succeeds
- [ ] GET /user/consents includes guardian_declaration
- [ ] PUT /user/consents/guardian_declaration works
- [ ] has_guardian_declaration() helper works
- [ ] get_consent_status() includes guardian_declaration
- [ ] Migration auto-grants to users with children
- [ ] Invalid date format returns error
- [ ] All PHP syntax checks pass
- [ ] No console/PHP errors

## Notes

- All timestamps should be in ISO 8601 format for API requests
- Internally, WordPress uses MySQL datetime format (Y-m-d H:i:s)
- The migration is safe to run multiple times (it checks if already applied)
- Guardian declaration consent is audit-trailed (IP, user agent, timestamp)

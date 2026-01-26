# Guardian Declaration Consent Implementation - Manual Verification

## Test Scenarios

### Scenario 1: New User Adding First Child
**Input:**
- User has no existing guardian_declaration consent
- User adds first child via POST /kg/v1/user/children

**Expected Behavior:**
1. Child is created successfully
2. System checks for existing guardian_declaration consent
3. No consent found → Creates new guardian_declaration consent with:
   - consent_type: 'guardian_declaration'
   - consented: true
   - consented_at: current timestamp (or custom if provided in request)
   - ip_address: from request
   - user_agent: from request

**Code Path:** Lines 1290-1324 in UserController.php
- Condition: `! $existing_guardian` is true
- Action: Creates new consent record (lines 1316-1323)

---

### Scenario 2: User Adding Second Child
**Input:**
- User already has guardian_declaration consent (consented: true)
- User adds another child

**Expected Behavior:**
1. Child is created successfully
2. System checks for existing guardian_declaration consent
3. Consent found and already consented → No action taken
4. Only one guardian_declaration consent exists in database

**Code Path:** Lines 1290-1325 in UserController.php
- Condition: `! $existing_guardian || ! $existing_guardian->consented` is false
- Action: Skip consent creation block

---

### Scenario 3: User with Revoked Guardian Declaration
**Input:**
- User has guardian_declaration consent but consented: false (revoked)
- User adds a child

**Expected Behavior:**
1. Child is created successfully
2. System checks for existing guardian_declaration consent
3. Consent found but not consented → Updates existing consent to consented: true

**Code Path:** Lines 1290-1325 in UserController.php
- Condition: `! $existing_guardian->consented` is true
- Action: Updates existing consent (lines 1308-1313)

---

### Scenario 4: Adding Child with Sensitive Data Consent
**Input:**
- User adds child with request parameter:
  ```json
  {
    "consents": {
      "sensitive_data_consent": true,
      "sensitive_data_consent_at": "2024-01-15 10:00:00"
    }
  }
  ```

**Expected Behavior:**
1. Child is created successfully
2. Guardian declaration consent is processed (scenario 1, 2, or 3)
3. System checks for sensitive_data consent parameter
4. Creates or updates sensitive_data consent record

**Code Path:** Lines 1327-1360 in UserController.php
- Condition: `isset( $consents_data['sensitive_data_consent'] )` is true
- Action: Creates or updates sensitive_data consent

---

### Scenario 5: Adding Child with Custom Guardian Declaration Timestamp
**Input:**
- User adds child with request parameter:
  ```json
  {
    "consents": {
      "guardian_declaration_at": "2024-01-01 12:00:00"
    }
  }
  ```

**Expected Behavior:**
1. Child is created successfully
2. Guardian declaration consent uses custom timestamp instead of current_time()

**Code Path:** Lines 1299-1303 in UserController.php
- Condition: `! empty( $consents_data['guardian_declaration_at'] )` is true
- Action: Uses custom timestamp

---

## Comparison with Registration Flow

### During Registration (register_user)
**Lines 537-548 in UserController.php**
```php
$guardian_consented = isset( $consents_data['guardian_declaration'] ) && $consents_data['guardian_declaration'];
if ( $guardian_consented ) {
    UserConsent::create( [
        'user_id' => $user_id,
        'consent_type' => 'guardian_declaration',
        'consented' => true,
        'consented_at' => $consents_data['guardian_declaration_at'] ?? current_time( 'mysql' ),
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
    ] );
}
```
- Only creates consent if explicitly provided in request
- Does NOT auto-create if missing

### After Registration (add_child) - NEW BEHAVIOR
**Lines 1288-1325 in UserController.php**
- Auto-creates guardian_declaration if it doesn't exist
- Updates if exists but not consented
- Skips if already consented
- Uses custom timestamp if provided

**Key Difference:** The add_child endpoint now automatically ensures guardian_declaration consent exists, while register_user only creates it if explicitly requested. This makes sense because:
1. Adding a child implicitly means the user is a guardian
2. Ensures all parents have guardian_declaration consent
3. Maintains data consistency across user workflows

---

## Code Review Checklist

- [x] Syntax is valid (verified with `php -l`)
- [x] Follows existing code patterns (matches register_user style)
- [x] Handles all edge cases:
  - [x] No existing consent
  - [x] Existing consent (consented: true)
  - [x] Revoked consent (consented: false)
  - [x] Custom timestamp support
  - [x] Sensitive data consent handling
- [x] Uses existing helper methods (get_client_ip_address, get_header)
- [x] Minimal changes (74 lines added, no lines removed)
- [x] Error handling matches existing pattern
- [x] Comments are clear and concise

---

## Testing Notes

The test file `test-add-child-guardian-consent.php` covers all 5 scenarios above.
To run the test, a WordPress environment with the plugin active is required.

**Expected Test Results:**
1. ✅ Guardian declaration consent auto-created for first child
2. ✅ No duplicate consents when adding second child
3. ✅ Sensitive data consent created when requested
4. ✅ Custom timestamp respected when provided
5. ✅ Existing consents updated correctly when revoked

---

## Security Considerations

- IP address and User-Agent are logged for compliance
- Consent timestamps support custom values for backdating if needed
- No sensitive data is logged in error messages
- Follows KVKK/GDPR compliance patterns established in codebase

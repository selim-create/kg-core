# User Consent Management System - Implementation Summary

## Overview

This implementation adds a complete user consent management system to the KG Core plugin for compliance with Turkish data protection laws (KVKK) and electronic communications regulations (ETK).

## 🎯 Features Implemented

### 1. Database Schema
- **Table**: `wp_kg_user_consents`
- **Columns**:
  - User identification (`user_id`)
  - Consent type (ENUM: `terms`, `marketing`, `sensitive_data`)
  - Consent status (`consented`, `consented_at`, `revoked_at`)
  - Audit trail (`ip_address`, `user_agent`)
  - Document version tracking (`version`)
  - Timestamps (`created_at`, `updated_at`)
- **Indexes**: Optimized for user queries and consent type lookups

### 2. Model Layer
- **UserConsent Model** (`includes/Models/UserConsent.php`)
  - CRUD operations
  - Consent status queries
  - Active consent filtering
  - API formatting utilities

### 3. Helper Utilities
- **UserConsentHelper** (`includes/Utils/UserConsentHelper.php`)
  - `has_marketing_consent()` - Check if user can receive marketing emails
  - `has_sensitive_data_consent()` - Check if user consents to health data processing
  - `get_consent_status()` - Get all consent statuses for a user

### 4. API Endpoints

#### Registration with Consents
```
POST /wp-json/kg/v1/auth/register
```
- Accepts consent data during user registration
- Validates that terms are accepted (required)
- Records IP address, user agent, and timestamp

#### Get User Consents
```
GET /wp-json/kg/v1/user/consents
```
- Returns all consent records for authenticated user
- Requires JWT authentication

#### Update Consent
```
PUT /wp-json/kg/v1/user/consents/{type}
```
- Grant or revoke specific consent type
- Records audit trail for changes
- Requires JWT authentication

### 5. Validation Rules

During registration:
- `consents.terms_accepted` - **Required**, must be `true`
- `consents.terms_accepted_at` - Required if terms accepted, valid ISO 8601 date
- `consents.marketing_consent` - Optional, boolean
- `consents.marketing_consent_at` - Optional, valid ISO 8601 date
- `consents.sensitive_data_consent` - Optional, boolean
- `consents.sensitive_data_consent_at` - Optional, valid ISO 8601 date

## 📁 Files Created

### Core Implementation
```
includes/
├── Database/
│   └── UserConsentSchema.php       # Database schema definition
├── Models/
│   └── UserConsent.php              # Consent model with CRUD operations
└── Utils/
    └── UserConsentHelper.php        # Helper functions for consent checks
```

### Modified Files
```
includes/API/UserController.php      # Added consent handling and endpoints
kg-core.php                          # Registered schema, model, and helper
```

### Tests & Documentation
```
tests/
├── test-user-consent-management.php      # WordPress-based unit tests
├── static-analysis-user-consent.php      # Static code analysis
└── integration-test-user-consent.php     # Full API integration test

docs/
└── USER_CONSENT_API.md                   # Complete API documentation
```

## 🧪 Testing

### Static Analysis (No WordPress Required)
```bash
php tests/static-analysis-user-consent.php
```
This verifies:
- All required files exist
- Files are properly included in kg-core.php
- Schema is registered in activation hook
- UserController has consent handling
- Model methods are present

### WordPress Unit Tests
Load in WordPress admin or via WP-CLI:
```php
// Navigate to: /wp-content/plugins/kg-core/tests/test-user-consent-management.php
```
Tests:
- Database table creation and structure
- UserConsent model CRUD operations
- UserConsentHelper methods
- API endpoint registration

### Integration Tests (Requires Running WordPress Instance)
```bash
# Set your WordPress API URL
export TEST_API_URL='https://yoursite.com/wp-json/kg/v1'

# Run integration tests
php tests/integration-test-user-consent.php
```
Tests:
- User registration with consents
- Consent retrieval via API
- Consent updates (grant/revoke)
- Validation rules enforcement

## 🔐 Legal Compliance (KVKK/ETK)

### Data Recorded for Audit
For each consent action, the system automatically records:
- **IP Address**: Client IP from headers or REMOTE_ADDR
- **User Agent**: Browser/application identifier
- **Timestamp**: Exact date and time
- **Version**: Document version being accepted (for terms)

### Data Retention
- Consents are **never deleted**
- Revoked consents are marked with `revoked_at` timestamp
- `consented` flag is set to `false` when revoked
- Full audit trail maintained for legal compliance

### Consent Types

| Type | Required | Purpose | Legal Basis |
|------|----------|---------|-------------|
| `terms` | Yes | User Agreement | KVKK - Required for service |
| `marketing` | No | Marketing Communications | ETK - Commercial messages |
| `sensitive_data` | No | Health Data Processing | KVKK - Sensitive personal data |

## 📖 Usage Examples

### Registration with Consents
```javascript
const response = await fetch('/wp-json/kg/v1/auth/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123',
    name: 'John Doe',
    consents: {
      terms_accepted: true,
      terms_accepted_at: new Date().toISOString(),
      marketing_consent: true,
      marketing_consent_at: new Date().toISOString(),
      sensitive_data_consent: false
    }
  })
});
```

### Check Consent in PHP
```php
use KG_Core\Utils\UserConsentHelper;

// Before sending marketing email
if ( UserConsentHelper::has_marketing_consent( $user_id ) ) {
    send_marketing_email( $user_id );
}

// Before processing health data
if ( UserConsentHelper::has_sensitive_data_consent( $user_id ) ) {
    process_health_data( $user_id );
}
```

### Update Consent via API
```javascript
// Grant marketing consent
await fetch('/wp-json/kg/v1/user/consents/marketing', {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ consented: true })
});
```

## 🚀 Activation

The user consents table is automatically created when:
1. Plugin is activated
2. Plugin is updated (if schema changes)

The activation hook calls:
```php
\KG_Core\Database\UserConsentSchema::create_table();
```

## 🔄 Migration from Existing System

If you have existing users, you may want to:

1. **Create default consent records** for existing users:
```php
// Example migration script
$users = get_users();
foreach ( $users as $user ) {
    // Create default terms consent (assumed accepted if user exists)
    \KG_Core\Models\UserConsent::create([
        'user_id' => $user->ID,
        'consent_type' => 'terms',
        'consented' => true,
        'consented_at' => $user->user_registered,
        'ip_address' => null,
        'user_agent' => 'Migration Script',
        'version' => '1.0',
    ]);
    
    // Create declined marketing/sensitive consents (opt-in required)
    \KG_Core\Models\UserConsent::create([
        'user_id' => $user->ID,
        'consent_type' => 'marketing',
        'consented' => false,
    ]);
    
    \KG_Core\Models\UserConsent::create([
        'user_id' => $user->ID,
        'consent_type' => 'sensitive_data',
        'consented' => false,
    ]);
}
```

## 📊 Database Queries

### Get all consented users for marketing
```sql
SELECT DISTINCT user_id 
FROM wp_kg_user_consents 
WHERE consent_type = 'marketing' 
  AND consented = 1 
  AND revoked_at IS NULL;
```

### Get consent history for a user
```sql
SELECT * 
FROM wp_kg_user_consents 
WHERE user_id = 123 
ORDER BY created_at DESC;
```

### Count users by consent type
```sql
SELECT 
    consent_type,
    SUM(CASE WHEN consented = 1 AND revoked_at IS NULL THEN 1 ELSE 0 END) as active_consents,
    SUM(CASE WHEN consented = 0 OR revoked_at IS NOT NULL THEN 1 ELSE 0 END) as declined_consents
FROM wp_kg_user_consents 
GROUP BY consent_type;
```

## 🛡️ Security Considerations

1. **JWT Authentication**: All consent endpoints require valid JWT token
2. **User Isolation**: Users can only view/modify their own consents
3. **Audit Trail**: All consent changes logged with IP and user agent
4. **Immutable Records**: Consents are never deleted, only marked as revoked
5. **Input Validation**: All consent data validated before storage

## 📝 Future Enhancements

Potential future additions:
- [ ] Consent version management (track changes to terms documents)
- [ ] Bulk consent operations
- [ ] Admin dashboard for consent analytics
- [ ] Export consent data for KVKK data portability requests
- [ ] Email notifications when consents are updated
- [ ] Consent expiration and renewal reminders

## 🆘 Troubleshooting

### Table not created after activation
```bash
# Manually create the table via WP-CLI
wp eval '\KG_Core\Database\UserConsentSchema::create_table();'
```

### Consents not saving during registration
1. Check WordPress error logs
2. Verify UserConsent model is loaded
3. Check database permissions
4. Enable WP_DEBUG to see errors

### API endpoints not working
1. Verify JWT authentication is working
2. Check that routes are registered: `wp rest list-routes | grep consents`
3. Ensure UserController is initialized

## 📞 Support

For issues or questions:
1. Check the [API Documentation](docs/USER_CONSENT_API.md)
2. Run static analysis: `php tests/static-analysis-user-consent.php`
3. Check WordPress error logs
4. Review test files for usage examples

## 📄 License

This implementation is part of the KG Core plugin.
Proprietary - Hip Medya

---

**Version**: 1.0.0  
**Date**: 2026-01-21  
**Author**: KG Core Development Team

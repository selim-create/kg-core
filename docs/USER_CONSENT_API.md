# User Consent Management API - Usage Examples

This document provides examples of how to use the new user consent management API endpoints for KVKK and ETK compliance.

## Table of Contents
1. [Registration with Consents](#registration-with-consents)
2. [Get User Consents](#get-user-consents)
3. [Update User Consent](#update-user-consent)
4. [Check Consent Status (Helper Methods)](#check-consent-status)

---

## Registration with Consents

### Endpoint
`POST /wp-json/kg/v1/auth/register`

### Request Body

#### Minimal Registration (Terms Required)
```json
{
  "email": "user@example.com",
  "password": "password123",
  "name": "User Name",
  "consents": {
    "terms_accepted": true,
    "terms_accepted_at": "2026-01-21T10:30:00.000Z"
  }
}
```

#### Full Registration with All Consents
```json
{
  "email": "user@example.com",
  "password": "password123",
  "name": "User Name",
  "consents": {
    "terms_accepted": true,
    "terms_accepted_at": "2026-01-21T10:30:00.000Z",
    "marketing_consent": true,
    "marketing_consent_at": "2026-01-21T10:30:00.000Z",
    "sensitive_data_consent": true,
    "sensitive_data_consent_at": "2026-01-21T10:30:00.000Z"
  }
}
```

#### Registration with Child Profile (Guardian Declaration Required)
```json
{
  "email": "user@example.com",
  "password": "password123",
  "name": "User Name",
  "child": {
    "name": "Child Name",
    "birth_date": "2023-01-15"
  },
  "consents": {
    "terms_accepted": true,
    "terms_accepted_at": "2026-01-21T10:30:00.000Z",
    "guardian_declaration": true,
    "guardian_declaration_at": "2026-01-21T10:30:00.000Z",
    "marketing_consent": false,
    "sensitive_data_consent": false
  }
}
```

#### Registration with Some Consents Declined
```json
{
  "email": "user@example.com",
  "password": "password123",
  "name": "User Name",
  "consents": {
    "terms_accepted": true,
    "terms_accepted_at": "2026-01-21T10:30:00.000Z",
    "marketing_consent": false,
    "marketing_consent_at": null,
    "sensitive_data_consent": false,
    "sensitive_data_consent_at": null
  }
}
```

### Response
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user_id": 123,
  "email": "user@example.com",
  "name": "User Name",
  "username": "user@example.com",
  "children": []
}
```

### cURL Example
```bash
curl -X POST https://yoursite.com/wp-json/kg/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "securepassword123",
    "name": "John Doe",
    "consents": {
      "terms_accepted": true,
      "terms_accepted_at": "2026-01-21T10:30:00.000Z",
      "marketing_consent": true,
      "marketing_consent_at": "2026-01-21T10:30:00.000Z",
      "sensitive_data_consent": false
    }
  }'
```

### JavaScript/Fetch Example
```javascript
const response = await fetch('https://yoursite.com/wp-json/kg/v1/auth/register', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'securepassword123',
    name: 'John Doe',
    consents: {
      terms_accepted: true,
      terms_accepted_at: new Date().toISOString(),
      marketing_consent: true,
      marketing_consent_at: new Date().toISOString(),
      sensitive_data_consent: true,
      sensitive_data_consent_at: new Date().toISOString()
    }
  })
});

const data = await response.json();
console.log('User registered:', data);
```

### Validation Rules

The following validation rules apply to the consents:

- `consents` - Must be an array/object
- `consents.terms_accepted` - **Required**, must be `true` (boolean)
- `consents.terms_accepted_at` - Required if `terms_accepted` is true, must be a valid ISO 8601 date
- `consents.marketing_consent` - Optional, boolean
- `consents.marketing_consent_at` - Optional, must be a valid ISO 8601 date if provided
- `consents.sensitive_data_consent` - Optional, boolean
- `consents.sensitive_data_consent_at` - Optional, must be a valid ISO 8601 date if provided
- `consents.guardian_declaration` - **Required when adding a child profile**, boolean
- `consents.guardian_declaration_at` - Optional, must be a valid ISO 8601 date if provided

### Error Responses

#### Missing Terms Acceptance
```json
{
  "code": "terms_required",
  "message": "Terms and conditions must be accepted",
  "data": {
    "status": 400
  }
}
```

#### Invalid Date Format
```json
{
  "code": "invalid_date",
  "message": "Invalid terms acceptance date format",
  "data": {
    "status": 400
  }
}
```

#### Missing Guardian Declaration (when adding child profile)
```json
{
  "code": "guardian_declaration_required",
  "message": "Çocuk profili eklemek için veli/vasi beyanını onaylamanız gerekmektedir.",
  "data": {
    "status": 400
  }
}
```

---

## Get User Consents

Retrieve all consent records for the authenticated user.

### Endpoint
`GET /wp-json/kg/v1/user/consents`

### Authentication
Requires JWT token in Authorization header.

### Request
```bash
curl -X GET https://yoursite.com/wp-json/kg/v1/user/consents \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Response
```json
[
  {
    "id": 1,
    "consent_type": "terms",
    "consented": true,
    "consented_at": "2026-01-21 10:30:00",
    "revoked_at": null,
    "version": "1.0",
    "created_at": "2026-01-21 10:30:00",
    "updated_at": "2026-01-21 10:30:00"
  },
  {
    "id": 2,
    "consent_type": "marketing",
    "consented": true,
    "consented_at": "2026-01-21 10:30:00",
    "revoked_at": null,
    "version": null,
    "created_at": "2026-01-21 10:30:00",
    "updated_at": "2026-01-21 10:30:00"
  },
  {
    "id": 3,
    "consent_type": "sensitive_data",
    "consented": false,
    "consented_at": null,
    "revoked_at": null,
    "version": null,
    "created_at": "2026-01-21 10:30:00",
    "updated_at": "2026-01-21 10:30:00"
  },
  {
    "id": 4,
    "consent_type": "guardian_declaration",
    "consented": true,
    "consented_at": "2026-01-21 10:30:00",
    "revoked_at": null,
    "version": null,
    "created_at": "2026-01-21 10:30:00",
    "updated_at": "2026-01-21 10:30:00"
  }
]
```

### JavaScript Example
```javascript
const response = await fetch('https://yoursite.com/wp-json/kg/v1/user/consents', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const consents = await response.json();
console.log('User consents:', consents);
```

---

## Update User Consent

Update or revoke a specific consent type for the authenticated user.

### Endpoint
`PUT /wp-json/kg/v1/user/consents/{type}`

Where `{type}` is one of:
- `terms`
- `marketing`
- `sensitive_data`
- `guardian_declaration`

### Authentication
Requires JWT token in Authorization header.

### Grant Consent

#### Request
```bash
curl -X PUT https://yoursite.com/wp-json/kg/v1/user/consents/marketing \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "consented": true
  }'
```

#### Response
```json
{
  "success": true,
  "message": "Consent updated successfully",
  "consent_type": "marketing",
  "consented": true
}
```

### Revoke Consent

#### Request
```bash
curl -X PUT https://yoursite.com/wp-json/kg/v1/user/consents/marketing \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "consented": false
  }'
```

#### Response
```json
{
  "success": true,
  "message": "Consent updated successfully",
  "consent_type": "marketing",
  "consented": false
}
```

### JavaScript Example
```javascript
// Grant marketing consent
async function grantMarketingConsent(token) {
  const response = await fetch('https://yoursite.com/wp-json/kg/v1/user/consents/marketing', {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      consented: true
    })
  });
  
  return await response.json();
}

// Revoke sensitive data consent
async function revokeSensitiveDataConsent(token) {
  const response = await fetch('https://yoursite.com/wp-json/kg/v1/user/consents/sensitive_data', {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      consented: false
    })
  });
  
  return await response.json();
}
```

### Error Responses

#### Invalid Consent Type
```json
{
  "code": "invalid_type",
  "message": "Invalid consent type",
  "data": {
    "status": 400
  }
}
```

#### Missing Consented Field
```json
{
  "code": "missing_consented",
  "message": "Consented field is required and must be boolean",
  "data": {
    "status": 400
  }
}
```

---

## Check Consent Status

You can also check consent status programmatically in PHP using the helper methods.

### Using UserConsentHelper

```php
<?php
use KG_Core\Utils\UserConsentHelper;

$user_id = 123;

// Check if user has marketing consent
if ( UserConsentHelper::has_marketing_consent( $user_id ) ) {
    // Send marketing email
    send_marketing_email( $user_id );
}

// Check if user has sensitive data consent
if ( UserConsentHelper::has_sensitive_data_consent( $user_id ) ) {
    // Process health data
    process_health_data( $user_id );
}

// Check if user has guardian declaration consent
if ( UserConsentHelper::has_guardian_declaration( $user_id ) ) {
    // Allow child data processing
    process_child_data( $user_id );
}

// Get all consent statuses
$status = UserConsentHelper::get_consent_status( $user_id );
/*
Array:
[
    'terms' => [
        'consented' => true,
        'consented_at' => '2026-01-21 10:30:00',
        'revoked_at' => null,
        'version' => '1.0'
    ],
    'marketing' => [
        'consented' => true,
        'consented_at' => '2026-01-21 10:30:00',
        'revoked_at' => null,
        'version' => null
    ],
    'sensitive_data' => [
        'consented' => false,
        'consented_at' => null,
        'revoked_at' => null,
        'version' => null
    ],
    'guardian_declaration' => [
        'consented' => true,
        'consented_at' => '2026-01-21 10:30:00',
        'revoked_at' => null,
        'version' => null
    ]
]
*/
```

### Using UserConsent Model Directly

```php
<?php
use KG_Core\Models\UserConsent;

$user_id = 123;

// Check active consent
$has_marketing = UserConsent::has_active_consent( $user_id, 'marketing' );

// Get specific consent
$consent = UserConsent::get_by_user_and_type( $user_id, 'terms' );

// Get all user consents
$consents = UserConsent::get_by_user_id( $user_id );

// Get only active consents
$active_consents = UserConsent::get_active_by_user_id( $user_id );
```

---

## Consent Types

| Type | Description | Required | Use Case |
|------|-------------|----------|----------|
| `terms` | Terms & Conditions / User Agreement | Yes | Must be accepted during registration (KVKK) |
| `marketing` | Commercial Communications Consent | No | ETK compliance for marketing emails/SMS |
| `sensitive_data` | Sensitive Personal Data (Health) | No | KVKK compliance for processing health data |
| `guardian_declaration` | Guardian/Legal Representative Declaration | Yes* | KVKK compliance for processing child data |

*Required when adding a child profile

---

## Legal Requirements (KVKK/ETK)

### What is Recorded
For legal compliance, the system automatically records:
- **IP Address**: Client IP address (for audit trail)
- **User Agent**: Browser/app information
- **Timestamp**: Exact date and time of consent
- **Version**: Document version being accepted (for terms)

### Data Retention
- Consent records are **never deleted**, only marked as revoked
- When a user revokes consent, `revoked_at` is set to current timestamp
- `consented` flag is set to `false`

### Use in Application Logic

**Marketing Emails:**
```php
// Before sending marketing email
if ( ! UserConsentHelper::has_marketing_consent( $user_id ) ) {
    // Do not send marketing email
    return;
}
```

**Health Data Processing:**
```php
// Before processing health/medical data
if ( ! UserConsentHelper::has_sensitive_data_consent( $user_id ) ) {
    // Do not process sensitive health data
    return;
}
```

---

## Complete React Example

```jsx
import React, { useState } from 'react';

function RegistrationForm() {
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    name: '',
    consents: {
      terms_accepted: false,
      marketing_consent: false,
      sensitive_data_consent: false
    }
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    const payload = {
      ...formData,
      consents: {
        terms_accepted: formData.consents.terms_accepted,
        terms_accepted_at: formData.consents.terms_accepted 
          ? new Date().toISOString() 
          : null,
        marketing_consent: formData.consents.marketing_consent,
        marketing_consent_at: formData.consents.marketing_consent 
          ? new Date().toISOString() 
          : null,
        sensitive_data_consent: formData.consents.sensitive_data_consent,
        sensitive_data_consent_at: formData.consents.sensitive_data_consent 
          ? new Date().toISOString() 
          : null,
      }
    };

    try {
      const response = await fetch('/wp-json/kg/v1/auth/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const data = await response.json();
      
      if (response.ok) {
        // Save token and redirect
        localStorage.setItem('token', data.token);
        window.location.href = '/dashboard';
      } else {
        alert(data.message);
      }
    } catch (error) {
      console.error('Registration failed:', error);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="email"
        placeholder="Email"
        value={formData.email}
        onChange={(e) => setFormData({...formData, email: e.target.value})}
        required
      />
      
      <input
        type="password"
        placeholder="Password"
        value={formData.password}
        onChange={(e) => setFormData({...formData, password: e.target.value})}
        required
      />
      
      <input
        type="text"
        placeholder="Full Name"
        value={formData.name}
        onChange={(e) => setFormData({...formData, name: e.target.value})}
      />
      
      <label>
        <input
          type="checkbox"
          checked={formData.consents.terms_accepted}
          onChange={(e) => setFormData({
            ...formData, 
            consents: {...formData.consents, terms_accepted: e.target.checked}
          })}
          required
        />
        I accept the Terms and Conditions (Required)
      </label>
      
      <label>
        <input
          type="checkbox"
          checked={formData.consents.marketing_consent}
          onChange={(e) => setFormData({
            ...formData, 
            consents: {...formData.consents, marketing_consent: e.target.checked}
          })}
        />
        I consent to receive marketing communications
      </label>
      
      <label>
        <input
          type="checkbox"
          checked={formData.consents.sensitive_data_consent}
          onChange={(e) => setFormData({
            ...formData, 
            consents: {...formData.consents, sensitive_data_consent: e.target.checked}
          })}
        />
        I consent to processing of my health data
      </label>
      
      <button type="submit">Register</button>
    </form>
  );
}

export default RegistrationForm;
```

---

## Database Schema

For reference, the `user_consents` table structure:

```sql
CREATE TABLE wp_kg_user_consents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    consent_type ENUM('terms', 'marketing', 'sensitive_data', 'guardian_declaration') NOT NULL,
    consented BOOLEAN NOT NULL DEFAULT FALSE,
    consented_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    version VARCHAR(20) NULL COMMENT 'Onaylanan döküman versiyonu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_consents (user_id, consent_type),
    INDEX idx_consent_type (consent_type),
    INDEX idx_consented_at (consented_at)
);
```

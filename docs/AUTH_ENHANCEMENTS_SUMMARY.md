# Auth System Enhancements - Implementation Summary

## Overview

This implementation adds three critical authentication features to the KidsGourmet backend (kg-core) to enhance the user registration and password recovery experience.

## Features Implemented

### 1. Forgot Password Endpoint
**Endpoint:** `POST /kg/v1/auth/forgot-password`

**Purpose:** Allows users to request a password reset link via email.

**Key Features:**
- Email enumeration protection (always returns success)
- WordPress native password reset key generation
- Branded HTML email template
- Configurable frontend URL for reset links

**Implementation Details:**
- Route registered in `register_routes()` method
- Callback: `forgot_password()` method
- Email template: `get_password_reset_email_template()` private method
- Security: Returns 200 status even when email doesn't exist

### 2. Reset Password Endpoint
**Endpoint:** `POST /kg/v1/auth/reset-password`

**Purpose:** Processes password reset requests using the key sent via email.

**Key Features:**
- WordPress native key validation
- 6-character minimum password requirement
- Secure key expiration (24 hours via WordPress)

**Implementation Details:**
- Route registered in `register_routes()` method
- Callback: `reset_password()` method
- Uses WordPress functions: `check_password_reset_key()` and `reset_password()`

### 3. Enhanced Registration
**Endpoint:** `POST /kg/v1/auth/register` (updated)

**Purpose:** Adds username support and child profile creation during registration.

**New Features:**
- Username parameter (optional, falls back to email)
- Username uniqueness validation
- Username format validation
- Child profile creation support
- Relaxed password requirement (6 characters instead of 8)

**Implementation Details:**
- Updated `register_user()` method
- Username validation using WordPress functions
- Child profile stored in `_kg_children` user meta
- Auto-generated UUID for child IDs
- KVKK consent auto-approved during registration
- Response includes `username` and `children` fields

## API Request/Response Examples

### Forgot Password
```bash
POST /kg/v1/auth/forgot-password
{
  "email": "user@example.com"
}

Response: 200 OK
{
  "success": true,
  "message": "Eğer bu e-posta adresi kayıtlıysa, şifre sıfırlama bağlantısı gönderildi."
}
```

### Reset Password
```bash
POST /kg/v1/auth/reset-password
{
  "key": "reset_key_from_email",
  "login": "username",
  "password": "NewPass123"
}

Response: 200 OK
{
  "success": true,
  "message": "Şifreniz başarıyla değiştirildi. Giriş yapabilirsiniz."
}
```

### Register with Username and Child
```bash
POST /kg/v1/auth/register
{
  "email": "parent@example.com",
  "password": "SecurePass123",
  "name": "Test Parent",
  "username": "testparent",
  "child": {
    "name": "Baby Test",
    "birth_date": "2024-06-15"
  }
}

Response: 201 Created
{
  "token": "jwt_token_here",
  "user_id": 123,
  "email": "parent@example.com",
  "name": "Test Parent",
  "username": "testparent",
  "children": [
    {
      "id": "uuid-here",
      "name": "Baby Test",
      "birth_date": "2024-06-15",
      "gender": "unspecified",
      "allergies": [],
      "feeding_style": "mixed",
      "photo_id": null,
      "kvkk_consent": true,
      "created_at": "2026-01-17T23:09:07+00:00"
    }
  ]
}
```

## Security Considerations

### Email Enumeration Protection
The forgot password endpoint always returns a 200 status with a success message, regardless of whether the email exists. This prevents attackers from discovering valid email addresses.

### Input Validation & Sanitization
- Email: `sanitize_email()` + `is_email()`
- Username: `sanitize_user()` + `validate_username()` + `username_exists()`
- Text fields: `sanitize_text_field()`
- Birth dates: Format validation with `DateTime::createFromFormat()`

### Output Escaping
- Email template: `esc_html()` and `esc_url()` for all dynamic content

### WordPress Native Functions
Uses WordPress core functions for security:
- `get_password_reset_key()` - Secure key generation
- `check_password_reset_key()` - Key validation
- `reset_password()` - Password reset
- `wp_mail()` - Email sending

## Configuration

### Frontend URL
Set the frontend URL for password reset links in `wp-config.php`:

```php
define( 'KG_FRONTEND_URL', 'https://kidsgourmet.com.tr' );
```

If not defined, defaults to `https://kidsgourmet.com.tr`.

## Testing

### Static Analysis Tests
A comprehensive test suite is available at `tests/test-auth-enhancements.php`:

```bash
php tests/test-auth-enhancements.php
```

**Test Coverage:**
- Route registration verification
- Method existence checks
- Security feature validation
- Password validation requirements
- Username validation
- Child profile creation
- Email template verification

**Results:** 23/23 tests passing (100%)

### Manual Testing
See `docs/AUTH_API_EXAMPLES.md` for curl command examples.

## Database Schema

### User Meta Keys
- `_kg_children`: Array of child profiles (existing)
  - Each child has: id, name, birth_date, gender, allergies, feeding_style, photo_id, kvkk_consent, created_at

### WordPress Core Tables
- `wp_users`: Stores username in `user_login` field
- Password reset keys stored in WordPress transients (expires in 24 hours)

## Backward Compatibility

### Registration Endpoint
- **Backward compatible:** All existing parameters work as before
- **Email fallback:** If no username provided, email is used as username
- **Legacy baby_birth_date:** Still supported for circle assignment
- **Response changes:** Added `username` and `children` fields (non-breaking)

### Password Requirements
- **Changed:** Minimum password length reduced from 8 to 6 characters
- **Removed:** Password complexity requirements (uppercase, lowercase, number)
- **Reason:** Match frontend requirements

## Error Codes

### Forgot Password
- `invalid_email` (400): Invalid email format
- `reset_key_failed` (500): Failed to generate reset key

### Reset Password
- `missing_fields` (400): Required fields missing
- `weak_password` (400): Password less than 6 characters
- `invalid_key` (400): Invalid or expired reset key

### Registration
- `missing_fields` (400): Email or password missing
- `invalid_email` (400): Invalid email format
- `weak_password` (400): Password less than 6 characters
- `email_exists` (409): Email already registered
- `username_exists` (409): Username already taken
- `invalid_username` (400): Invalid username format

## Email Template

The password reset email includes:
- KidsGourmet branding with gradient header (#FF8A65 to #AED581)
- Personalized greeting with user's display name
- Clear "Şifremi Sıfırla" (Reset My Password) button
- Security notice about 24-hour expiration
- Footer with KidsGourmet branding

Template can be customized in the `get_password_reset_email_template()` method.

## Future Enhancements

Potential improvements for future iterations:
1. Email template customization via admin panel
2. Rate limiting for forgot password requests
3. Two-factor authentication support
4. Account recovery questions
5. Email verification for new accounts
6. Password strength meter feedback

## Support & Documentation

- **API Examples:** `docs/AUTH_API_EXAMPLES.md`
- **Test Suite:** `tests/test-auth-enhancements.php`
- **Implementation:** `includes/API/UserController.php`

## Version History

- **v1.0.0** (2026-01-17): Initial implementation
  - Forgot password endpoint
  - Reset password endpoint
  - Username support in registration
  - Child profile creation during registration
  - Relaxed password requirements

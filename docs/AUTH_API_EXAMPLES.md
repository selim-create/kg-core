# Auth System Enhancements - API Test Examples

This document provides example curl commands to test the new authentication endpoints.

## 1. Forgot Password

### Basic Request
```bash
curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com"
  }'
```

### Expected Response (Success)
```json
{
  "success": true,
  "message": "Eğer bu e-posta adresi kayıtlıysa, şifre sıfırlama bağlantısı gönderildi."
}
```

### Invalid Email Response
```bash
curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "invalid-email"
  }'
```

Response:
```json
{
  "code": "invalid_email",
  "message": "Geçerli bir e-posta adresi girin.",
  "data": {
    "status": 400
  }
}
```

## 2. Reset Password

### Basic Request
```bash
curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "key": "YOUR_RESET_KEY",
    "login": "testuser",
    "password": "NewSecurePass123"
  }'
```

### Expected Response (Success)
```json
{
  "success": true,
  "message": "Şifreniz başarıyla değiştirildi. Giriş yapabilirsiniz."
}
```

### Weak Password Response
```bash
curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "key": "YOUR_RESET_KEY",
    "login": "testuser",
    "password": "12345"
  }'
```

Response:
```json
{
  "code": "weak_password",
  "message": "Şifre en az 6 karakter olmalıdır.",
  "data": {
    "status": 400
  }
}
```

### Invalid Key Response
```json
{
  "code": "invalid_key",
  "message": "Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.",
  "data": {
    "status": 400
  }
}
```

## 3. Register with Username

### Basic Request
```bash
curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "parent@example.com",
    "password": "SecurePass123",
    "name": "Test Parent",
    "username": "testparent"
  }'
```

### Expected Response
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user_id": 123,
  "email": "parent@example.com",
  "name": "Test Parent",
  "username": "testparent",
  "children": []
}
```

### Duplicate Username Response
```json
{
  "code": "username_exists",
  "message": "Bu kullanıcı adı zaten kullanılıyor",
  "data": {
    "status": 409
  }
}
```

### Invalid Username Response
```json
{
  "code": "invalid_username",
  "message": "Geçersiz kullanıcı adı formatı",
  "data": {
    "status": 400
  }
}
```

## 4. Register with Child Profile

### Basic Request
```bash
curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "parent@example.com",
    "password": "SecurePass123",
    "name": "Test Parent",
    "child": {
      "name": "Baby Test",
      "birth_date": "2024-06-15"
    }
  }'
```

### Expected Response
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user_id": 123,
  "email": "parent@example.com",
  "name": "Test Parent",
  "username": "parent@example.com",
  "children": [
    {
      "id": "12345678-1234-5678-1234-567812345678",
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

## 5. Register with Both Username and Child Profile

### Basic Request
```bash
curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "parent@example.com",
    "password": "SecurePass123",
    "name": "Test Parent",
    "username": "testparent",
    "child": {
      "name": "Baby Test",
      "birth_date": "2024-06-15"
    }
  }'
```

### Expected Response
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user_id": 123,
  "email": "parent@example.com",
  "name": "Test Parent",
  "username": "testparent",
  "children": [
    {
      "id": "12345678-1234-5678-1234-567812345678",
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

## Security Notes

1. **Email Enumeration Protection**: The forgot password endpoint always returns success (200) even if the email doesn't exist, preventing attackers from discovering valid email addresses.

2. **Password Requirements**: Passwords must be at least 6 characters long (relaxed from the previous 8-character requirement to match frontend specifications).

3. **Username Validation**: Usernames are validated for format and uniqueness before account creation.

4. **Birth Date Validation**: Child birth dates cannot be in the future.

5. **KVKK Consent**: When creating a child profile during registration, KVKK consent is automatically set to `true`.

## Testing the Email Template

The password reset email includes:
- KidsGourmet branding with gradient header
- Personalized greeting using user's display name
- Clear call-to-action button for password reset
- Security notice about 24-hour link expiration
- Proper HTML escaping for security

The email template can be customized by modifying the `get_password_reset_email_template()` method in `includes/API/UserController.php`.

## Frontend URL Configuration

The reset password link uses the `KG_FRONTEND_URL` constant. If not defined, it defaults to `https://kidsgourmet.com.tr`.

To set a custom frontend URL, define the constant in your `wp-config.php`:

```php
define( 'KG_FRONTEND_URL', 'https://your-frontend-url.com' );
```

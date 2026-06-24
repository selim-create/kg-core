# Apple Sign-In Integration — Complete Documentation

## 🎯 Overview

This implementation adds Apple Sign-In to the KidsGourmet WordPress backend, allowing users to sign in with their Apple accounts on both iOS (native) and web flows.

Apple Sign-In uses an **ES256-signed JWT** called `identity_token` (distinct from Google's approach of calling a tokeninfo API). Verification is done locally using Apple's public JWKS keys.

---

## 📁 Files Added/Modified

### Created Files
1. **`includes/Auth/AppleAuth.php`**  
   - Core Apple Sign-In handler class  
   - JWKS fetching + caching, JWT verification, user management

2. **`docs/APPLE_SIGN_IN_IMPLEMENTATION.md`** (this file)

### Modified Files
1. **`composer.json`** — added `firebase/php-jwt: ^6.10`
2. **`includes/Admin/SettingsPage.php`** — added Apple Sign-In settings section
3. **`includes/API/UserController.php`** — added `/auth/apple` endpoint + `apple_auth()` method
4. **`kg-core.php`** — loads Composer autoloader + `AppleAuth.php`

---

## 🏗️ Architecture

```
Mobile (iOS / Expo)
    │
    │  expo-apple-authentication
    │  → identity_token (ES256 JWT)
    ▼
POST /wp-json/kg/v1/auth/apple
    │
    ▼
AppleAuth::verify_identity_token()
    │  1. Fetch JWKS from https://appleid.apple.com/auth/keys (cached 24 h)
    │  2. Decode JWT header → get kid
    │  3. Find matching key in JWKS
    │  4. Firebase\JWT\JWT::decode() — verifies ES256 signature + exp
    │  5. Validate iss, aud (Bundle ID or Service ID), email_verified
    ▼
AppleAuth::get_or_create_user()
    │  Match by apple_id meta → email → create new kg_parent user
    ▼
JWTHandler::generate_token($user->ID)
    ▼
Response: { success, token, user, message }
```

---

## 🔌 API Endpoint Contract

### Endpoint

```
POST /wp-json/kg/v1/auth/apple
```

### Request Body

```json
{
  "identity_token": "******",
  "name": {
    "given_name": "Selim",
    "family_name": "Altınkaynak"
  }
}
```

> **Note:** `name` is only sent by Apple on the **first sign-in**. Pass it through from your Apple authentication result. Subsequent sign-ins will not include it.

### Success Response (200)

```json
{
  "success": true,
  "token": "******",
  "user": {
    "id": 123,
    "email": "user@example.com",
    "name": "Selim Altınkaynak",
    "display_name": "Selim Altınkaynak",
    "avatar_url": "https://...",
    "children": [],
    "created_at": "2026-01-01 00:00:00"
  },
  "message": "Apple ile giriş başarılı.",
  "apple_first_signin": true
}
```

> `apple_first_signin: true` is only present on the user's very first sign-in. Use it to show a "Welcome" onboarding screen.

### Error Responses

| HTTP | Error Code             | Description                                     |
|------|------------------------|-------------------------------------------------|
| 400  | `missing_token`        | `identity_token` not provided                   |
| 401  | `invalid_token`        | Malformed JWT or signature mismatch             |
| 401  | `invalid_issuer`       | `iss` ≠ `https://appleid.apple.com`             |
| 401  | `invalid_audience`     | `aud` doesn't match Bundle ID or Service ID     |
| 401  | `expired_token`        | `exp` claim is in the past                      |
| 403  | `apple_auth_disabled`  | Feature disabled in WP admin                    |
| 500  | `config_error`         | Bundle ID / Service ID not configured           |
| 500  | `apple_jwks_error`     | Could not fetch Apple public keys               |
| 500  | `user_creation_failed` | WordPress user creation failed                  |

---

## 🔒 Security Features

### JWKS Caching
Apple's public keys are fetched once and cached as a WordPress transient (`kg_apple_jwks`) for **24 hours**. If a `kid` mismatch occurs (key rotation), the transient is cleared automatically and the request fails with `invalid_token` — the client should retry.

### kid Lookup
The JWT header is decoded to extract the `kid` (key ID embedded in the token), which is then used to find the exact matching public key in Apple's JWKS. This is distinct from the Apple Developer Key ID used to sign client secrets.

### Claim Validation
After signature verification, the following claims are checked:
- `iss` = `https://appleid.apple.com`
- `aud` = configured Bundle ID **or** Service ID (both are valid depending on flow)
- `exp` = checked by Firebase JWT library automatically
- `email_verified` = `true` (or string `"true"` — Apple returns both)

### Name on First Sign-In
Apple only sends the user's name on the **first** authentication. The client must capture and forward `given_name` / `family_name` in the `name` field. Subsequent requests omit the name, so the stored display name is preserved.

### Private Email Relay
Apple allows users to hide their real email with a `@privaterelay.appleid.com` address. This is accepted and stored as-is. No special handling is required beyond not assuming the email is a real mailbox.

---

## 🛠️ Setup Guide

### Step 1: Apple Developer Console

1. Log in to [Apple Developer](https://developer.apple.com/account/)
2. **Certificates, Identifiers & Profiles → Identifiers**
3. Select your App ID (`com.kidsgourmet.mobile`) → Enable **Sign in with Apple** capability
4. Create a new **Services ID** (for web flow):
   - Identifier: `com.kidsgourmet.signin` (or similar)
   - Enable **Sign in with Apple**
   - Configure domains: `kidsgourmet.com.tr`, `api.kidsgourmet.com.tr`, `www.kidsgourmet.com.tr`
   - Return URL: `https://api.kidsgourmet.com.tr/wp-json/kg/v1/auth/apple/callback`
5. **Keys → Create a Key**:
   - Enable **Sign in with Apple**
   - Select primary App ID: `com.kidsgourmet.mobile`
   - Download the `.p8` file (⚠️ only downloadable once)
   - Note the **Key ID** (e.g. `3V45N4F6K3`)

### Step 2: WordPress Admin

1. Go to **WordPress Admin → Ingredients → AI Ayarları**
2. Scroll to the **🍏 Apple Sign-In Ayarları** section
3. Fill in:
   - **Team ID** — from Apple Developer account header
   - **Bundle ID** — `com.kidsgourmet.mobile`
   - **Service ID** — `com.kidsgourmet.signin`
   - **Key ID** — `3V45N4F6K3`
   - **Private Key (.p8)** — paste the full contents of the `.p8` file
4. Check **Apple ile giriş özelliğini aktif et**
5. Click **💾 Ayarları Kaydet**

### Step 3: Install Dependencies

Run Composer on the server to fetch `firebase/php-jwt`:

```bash
cd /path/to/wp-content/plugins/kg-core
composer install --no-dev --optimize-autoloader
```

---

## 📱 Frontend Integration (Expo)

```typescript
import * as AppleAuthentication from 'expo-apple-authentication';

async function signInWithApple() {
  try {
    const credential = await AppleAuthentication.signInAsync({
      requestedScopes: [
        AppleAuthentication.AppleAuthenticationScope.FULL_NAME,
        AppleAuthentication.AppleAuthenticationScope.EMAIL,
      ],
    });

    const response = await fetch(`${API_URL}/wp-json/kg/v1/auth/apple`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        identity_token: credential.identityToken,
        // Apple only sends name on first sign-in
        name: credential.fullName
          ? {
              given_name: credential.fullName.givenName ?? '',
              family_name: credential.fullName.familyName ?? '',
            }
          : undefined,
      }),
    });

    const data = await response.json();

    if (data.success) {
      // Store KG JWT
      await SecureStore.setItemAsync('jwt_token', data.token);
      // Optionally show welcome screen on first sign-in
      if (data.apple_first_signin) {
        router.replace('/welcome');
      } else {
        router.replace('/(tabs)');
      }
    }
  } catch (error: any) {
    if (error.code !== 'ERR_REQUEST_CANCELED') {
      console.error('Apple Sign-In error:', error);
    }
  }
}
```

---

## 🔑 User Meta Fields

After Apple Sign-In, the following user meta fields are set:

| Meta Key                 | Description                                      |
|--------------------------|--------------------------------------------------|
| `apple_id`               | Apple user subject identifier (for matching)     |
| `apple_email_is_private` | `true` if using a private relay email            |
| `registered_via`         | `"apple"` for users created via Apple Sign-In    |
| `apple_first_signin`     | Temporary flag — deleted after first read        |

---

## ✅ Token Revocation (Implemented)

Apple's App Store guidelines (§5.1.1.v) require implementing token revocation. This is implemented as follows:

- `AppleAuth::revoke_token($refresh_token)` — calls `POST https://appleid.apple.com/auth/revoke`
- `AppleAuth::generate_client_secret()` — generates an ES256-signed JWT using Team ID, Key ID, Bundle ID, and the stored `.p8` private key
- Triggered from `DELETE /kg/v1/user/account` when `registered_via === 'apple'` and `apple_refresh_token` is provided
- **Best-effort**: revocation failure does not block account deletion

### Client Secret JWT Structure

```
Header:  { alg: "ES256", kid: "{key_id}" }
Payload: { iss: "{team_id}", iat: now, exp: now+300, aud: "https://appleid.apple.com", sub: "{bundle_id}" }
```

The `.p8` private key is read from `kg_apple_private_key` WordPress option (configured in the admin panel).

---

## 📊 Acceptance Criteria

- [x] `POST /kg/v1/auth/apple` returns same response shape as `POST /kg/v1/auth/google`
- [x] Token verification rejects bad signature, wrong `iss`, wrong `aud`, expired `exp`
- [x] Admin panel has Apple Sign-In settings section with `.p8` textarea
- [x] Plugin activates cleanly when settings are unset
- [x] `kg_apple_auth_enabled = false` → endpoint returns `403 apple_auth_disabled`
- [x] `composer.json` lists `firebase/php-jwt ^6.10`
- [x] Documentation mirrors Google doc structure

---

**Implementation Date:** June 2026  
**Version:** 1.0.0  
**Status:** ✅ Complete

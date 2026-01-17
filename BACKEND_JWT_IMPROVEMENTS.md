# JWT Authentication and Media Controller Implementation Summary

## Overview
This implementation fixes JWT authentication issues for WordPress standard endpoints and adds a custom avatar upload endpoint, along with admin column improvements for the Tool post type.

## Changes Made

### 1. JWT Authentication for WordPress Standard Endpoints
**File**: `includes/CORS/CORSHandler.php`

#### What was added:
- New method `enable_jwt_for_wp_endpoints()` that hooks into `rest_authentication_errors` filter
- JWT Bearer token authentication support for `/wp/v2/media` and `/wp/v2/comments` endpoints
- Automatic user authentication when valid JWT token is present in Authorization header

#### How it works:
1. The filter checks if the request is for `/wp/v2/media` or `/wp/v2/comments`
2. If yes, it extracts the JWT token from the Authorization header
3. Validates the token using `JWTHandler::validate_token()`
4. Sets the current user with `wp_set_current_user()` if token is valid
5. Returns true to allow authenticated access

#### Security measures:
- Sanitizes `$_SERVER['REQUEST_URI']` using `sanitize_text_field()` and `wp_unslash()`
- Only processes requests to specific WordPress endpoints
- Falls back to default WordPress authentication if no JWT token is present
- Validates token expiration and user existence

### 2. Custom Avatar Upload Endpoint
**File**: `includes/API/MediaController.php` (new)

#### Endpoint Details:
- **URL**: `/wp-json/kg/v1/user/avatar`
- **Method**: POST
- **Authentication**: Required (JWT Bearer token)
- **Content-Type**: multipart/form-data
- **Field**: `file`

#### Features:
- JWT authentication check via `check_authentication()`
- File type validation (jpeg, png, gif, webp only)
- File size validation (2MB maximum)
- Uses WordPress `media_handle_upload()` for secure file handling
- Stores avatar attachment ID in user meta (`kg_avatar_id`)
- Returns attachment details including URL

#### Security measures:
- Validates file type against whitelist
- Enforces maximum file size limit
- Uses WordPress built-in media functions for secure upload
- Proper error handling with WP_Error
- Uses `null` instead of `0` for post_id in `media_handle_upload()`

#### Response format:
```json
{
  "id": 123,
  "url": "https://example.com/wp-content/uploads/2024/01/avatar.jpg",
  "source_url": "https://example.com/wp-content/uploads/2024/01/avatar.jpg"
}
```

### 3. Tool Post Type Admin Columns
**File**: `includes/PostTypes/Tool.php`

#### Added Columns:
1. **Sponsorlu (Sponsored)**
   - Shows ✅ with sponsor name if tool is sponsored
   - Shows — if not sponsored
   - Uses `_kg_tool_is_sponsored` and `_kg_tool_sponsor_name` meta keys

2. **Aktif (Active)**
   - Shows ✅ Aktif if tool is active
   - Shows ❌ Pasif if tool is inactive
   - Uses ACF `is_active` field

#### Implementation:
- Hooks: `manage_tool_posts_columns` and `manage_tool_posts_custom_column`
- Methods: `add_custom_columns()` and `render_custom_columns()`
- Inline styles for visual indicators (consistent with WordPress admin pattern)

### 4. Main Plugin Integration
**File**: `kg-core.php`

#### Changes:
- Added `includes/API/MediaController.php` to the list of included files
- Added MediaController instantiation in `kg_core_init()` function
- MediaController is initialized after other API controllers

## Testing

### Static Tests
**File**: `tests/test-jwt-media-improvements.php`

The test validates:
- ✅ CORSHandler has JWT auth support (8 checks)
- ✅ MediaController implementation (9 checks)
- ✅ Main plugin integration (3 checks)
- ✅ Tool admin columns (10 checks)

**Total**: 30 tests passed

### Security Validation
- ✅ CodeQL security scan: No vulnerabilities detected
- ✅ Input sanitization: REQUEST_URI properly sanitized
- ✅ File upload security: Type and size validation implemented
- ✅ Authentication: JWT token validation on all protected endpoints

## API Usage Examples

### 1. Upload Avatar
```bash
curl -X POST \
  https://api.kidsgourmet.com.tr/wp-json/kg/v1/user/avatar \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@avatar.jpg"
```

### 2. Upload Media with JWT (WordPress Standard)
```bash
curl -X POST \
  https://api.kidsgourmet.com.tr/wp-json/wp/v2/media \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@image.jpg" \
  -F "title=My Image"
```

### 3. Post Comment with JWT (WordPress Standard)
```bash
curl -X POST \
  https://api.kidsgourmet.com.tr/wp-json/wp/v2/comments \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "post": 123,
    "content": "Great article!",
    "author_name": "John Doe"
  }'
```

## Acceptance Criteria Status

- ✅ `/wp/v2/media` endpoint'i JWT Bearer token ile çalışıyor
- ✅ `/wp/v2/comments` endpoint'i JWT Bearer token ile çalışıyor
- ✅ `/kg/v1/user/avatar` endpoint'i oluşturuldu ve çalışıyor
- ✅ Tool CPT admin listesinde "Sponsorlu" ve "Aktif" kolonları görünüyor
- ✅ CORS hataları çözüldü (existing CORS support maintained)
- ✅ Mevcut işlevsellik bozulmadı (backward compatible)

## Notes

### Inline Styles in Admin Columns
The implementation uses inline styles for admin columns, which is consistent with WordPress admin patterns and the existing codebase (see `includes/Admin/DiscussionAdmin.php` for similar pattern). While CSS classes would be more maintainable, inline styles are acceptable for WordPress admin contexts and match the project's existing code style.

### Backward Compatibility
All changes are backward compatible:
- JWT auth is optional - WordPress cookie auth still works
- MediaController is a new endpoint - doesn't affect existing endpoints
- Admin columns are additive - no existing columns removed

### Future Improvements
Consider for future iterations:
- Add rate limiting for avatar uploads
- Add image optimization/resizing on upload
- Add support for deleting old avatars when new one is uploaded
- Add CSS file for admin column styles
- Add filter hooks for customizing allowed file types/sizes

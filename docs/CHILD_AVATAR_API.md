# Child Profile Avatar API Documentation

## Overview
This API allows parents to upload, retrieve, and delete avatar photos for their child profiles. All endpoints require JWT authentication and enforce strict ownership checks.

## Security Features
- **Private Storage**: Avatars stored in `wp-content/uploads/private/child-avatars/` (not publicly accessible)
- **Ownership Validation**: Only the parent who created the child profile can access/modify avatars
- **JWT Authentication**: All endpoints require valid JWT token
- **Rate Limiting**: 5 upload requests per minute per user
- **File Validation**:
  - Allowed types: jpg, jpeg, png, webp
  - Maximum size: 2MB
  - MIME type verification (real file content check)
- **Signed URLs**: Avatar access via temporary URLs (15 minutes expiration)

## Base URL
```
https://yourdomain.com/wp-json/kg/v1
```

## Authentication
All requests require JWT authentication header:
```
Authorization: Bearer YOUR_JWT_TOKEN
```

---

## Endpoints

### 1. Upload Child Avatar

Upload or update an avatar for a child profile.

**Endpoint:** `POST /child-profiles/{child_uuid}/avatar`

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: multipart/form-data
```

**Parameters:**
- `child_uuid` (path parameter, required): UUID of the child profile
- `avatar` (file parameter, required): Image file to upload

**Request Example (cURL):**
```bash
curl -X POST \
  https://yourdomain.com/wp-json/kg/v1/child-profiles/abc123-def456-ghi789/avatar \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "avatar=@/path/to/photo.jpg"
```

**Success Response (200):**
```json
{
  "message": "Avatar uploaded successfully",
  "avatar": {
    "path": "private/child-avatars/1/abc123-def456-ghi789/avatar_1705579200.jpg",
    "url": "https://yourdomain.com/wp-json/kg/v1/child-profiles/avatar-file?path=cHJpdmF0ZS9jaGlsZC1hdmF0YXJzLzEvYWJjMTIzLWRlZjQ1Ni1naGk3ODkvYXZhdGFyXzE3MDU1NzkyMDAuanBn&expires=1705580100&token=abc123def",
    "uploaded_at": "2024-01-18T10:00:00+00:00"
  }
}
```

**Error Responses:**

403 Forbidden (Not the owner):
```json
{
  "code": "forbidden",
  "message": "You do not have permission to update this child profile",
  "data": {
    "status": 403
  }
}
```

400 Bad Request (No file):
```json
{
  "code": "no_file",
  "message": "No avatar file provided",
  "data": {
    "status": 400
  }
}
```

400 Bad Request (File too large):
```json
{
  "code": "file_too_large",
  "message": "File size exceeds 2MB limit",
  "data": {
    "status": 400
  }
}
```

400 Bad Request (Invalid file type):
```json
{
  "code": "invalid_file_type",
  "message": "File type not allowed. Allowed types: jpg, jpeg, png, webp",
  "data": {
    "status": 400
  }
}
```

429 Rate Limit Exceeded:
```json
{
  "code": "rate_limit_exceeded",
  "message": "Rate limit exceeded. Maximum 5 requests per 60 seconds allowed.",
  "data": {
    "status": 429
  }
}
```

---

### 2. Get Child Avatar URL

Get a temporary signed URL to access the child's avatar.

**Endpoint:** `GET /child-profiles/{child_uuid}/avatar`

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Parameters:**
- `child_uuid` (path parameter, required): UUID of the child profile

**Request Example (cURL):**
```bash
curl -X GET \
  https://yourdomain.com/wp-json/kg/v1/child-profiles/abc123-def456-ghi789/avatar \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Success Response (200):**
```json
{
  "url": "https://yourdomain.com/wp-json/kg/v1/child-profiles/avatar-file?path=cHJpdmF0ZS9jaGlsZC1hdmF0YXJzLzEvYWJjMTIzLWRlZjQ1Ni1naGk3ODkvYXZhdGFyXzE3MDU1NzkyMDAuanBn&expires=1705580100&token=abc123def",
  "expires_in": 900
}
```

**Error Responses:**

403 Forbidden (Not the owner):
```json
{
  "code": "forbidden",
  "message": "You do not have permission to view this child profile",
  "data": {
    "status": 403
  }
}
```

404 Not Found (No avatar):
```json
{
  "code": "no_avatar",
  "message": "No avatar found for this profile",
  "data": {
    "status": 404
  }
}
```

---

### 3. Delete Child Avatar

Delete the avatar for a child profile.

**Endpoint:** `DELETE /child-profiles/{child_uuid}/avatar`

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Parameters:**
- `child_uuid` (path parameter, required): UUID of the child profile

**Request Example (cURL):**
```bash
curl -X DELETE \
  https://yourdomain.com/wp-json/kg/v1/child-profiles/abc123-def456-ghi789/avatar \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

**Success Response (200):**
```json
{
  "message": "Avatar deleted successfully"
}
```

**Error Responses:**

403 Forbidden (Not the owner):
```json
{
  "code": "forbidden",
  "message": "You do not have permission to update this child profile",
  "data": {
    "status": 403
  }
}
```

404 Not Found (Child not found):
```json
{
  "code": "child_not_found",
  "message": "Child profile not found",
  "data": {
    "status": 404
  }
}
```

---

## Usage Flow

### 1. Initial Setup (One-time)
```bash
# User registers/logs in to get JWT token
curl -X POST https://yourdomain.com/wp-json/kg/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "parent@example.com", "password": "password"}'

# Response includes JWT token
# {"token": "eyJ0eXAiOiJKV1QiLCJhbGc...", ...}
```

### 2. Upload Avatar
```bash
# Upload avatar for child
curl -X POST \
  https://yourdomain.com/wp-json/kg/v1/child-profiles/CHILD_UUID/avatar \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "avatar=@child_photo.jpg"
```

### 3. Display Avatar
```bash
# Get signed URL for avatar
curl -X GET \
  https://yourdomain.com/wp-json/kg/v1/child-profiles/CHILD_UUID/avatar \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Use the returned URL in <img src="..."> tag
# URL expires in 15 minutes
```

### 4. Update Avatar
```bash
# Upload new avatar (automatically deletes old one)
curl -X POST \
  https://yourdomain.com/wp-json/kg/v1/child-profiles/CHILD_UUID/avatar \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "avatar=@new_photo.jpg"
```

### 5. Delete Avatar
```bash
# Delete avatar
curl -X DELETE \
  https://yourdomain.com/wp-json/kg/v1/child-profiles/CHILD_UUID/avatar \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## Testing Checklist

### Security Tests
- [ ] Upload avatar as the child's parent (should succeed)
- [ ] Try to upload avatar for another user's child (should fail with 403)
- [ ] Try to access avatar without authentication (should fail with 401)
- [ ] Try to upload a PHP file renamed as .jpg (should fail MIME check)
- [ ] Try to upload a file larger than 2MB (should fail)
- [ ] Try to upload 6 avatars in quick succession (should hit rate limit on 6th)
- [ ] Verify signed URL expires after 15 minutes

### Functional Tests
- [ ] Upload JPG avatar
- [ ] Upload PNG avatar
- [ ] Upload WebP avatar
- [ ] Replace existing avatar (old file should be deleted)
- [ ] Get avatar URL and verify it returns the image
- [ ] Delete avatar and verify file is removed
- [ ] Try to get URL for deleted avatar (should fail with 404)

### Edge Cases
- [ ] Upload avatar for non-existent child UUID (should fail with 404)
- [ ] Upload with missing file parameter (should fail with 400)
- [ ] Upload with invalid file extension (should fail with 400)
- [ ] Try to access expired signed URL (should fail with 403)
- [ ] Try to tamper with signed URL token (should fail with 403)

---

## Database Migration

If you have existing child profiles in user meta, run the migration:

### Via WP-CLI (if available):
```bash
wp eval 'use KG_Core\Migration\ChildProfileMigrator; 
$result = ChildProfileMigrator::migrate_all(); 
print_r($result);'
```

### Via PHP Script:
Create a temporary file `migrate-children.php` in WordPress root:
```php
<?php
require_once __DIR__ . '/wp-load.php';
use KG_Core\Migration\ChildProfileMigrator;

$result = ChildProfileMigrator::migrate_all();
echo "Migration Results:\n";
echo "Total Users: " . $result['total_users'] . "\n";
echo "Total Children: " . $result['total_children'] . "\n";
echo "Migrated: " . $result['migrated'] . "\n";
echo "Skipped: " . $result['skipped'] . "\n";
if (!empty($result['errors'])) {
    echo "Errors:\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
}
```

Then run:
```bash
php migrate-children.php
```

### Verify Migration:
```php
<?php
require_once __DIR__ . '/wp-load.php';
use KG_Core\Migration\ChildProfileMigrator;

$result = ChildProfileMigrator::verify_migration();
echo "Verification Results:\n";
echo "Matching: " . $result['matching'] . "\n";
echo "Missing in DB: " . count($result['missing_in_db']) . "\n";
echo "Discrepancies: " . count($result['discrepancies']) . "\n";
```

---

## File Structure

```
wp-content/uploads/
└── private/
    └── child-avatars/
        └── {user_id}/
            └── {child_uuid}/
                ├── .htaccess (denies direct access)
                └── avatar_{timestamp}.{ext}
```

---

## Notes

1. **Backward Compatibility**: The system creates a new `kg_child_profiles` table but doesn't automatically delete data from user meta. The migration is non-destructive.

2. **Private Storage**: Avatars are stored in the `private` subdirectory with `.htaccess` protection. They cannot be accessed directly via URL.

3. **Signed URLs**: All avatar access is through temporary signed URLs that expire after 15 minutes. This prevents unauthorized sharing.

4. **Automatic Cleanup**: When uploading a new avatar, the old avatar file is automatically deleted.

5. **Rate Limiting**: Upload rate limit is enforced per user, not per child. This prevents abuse while allowing parents with multiple children to upload avatars.

6. **Error Handling**: All errors return appropriate HTTP status codes and descriptive messages.

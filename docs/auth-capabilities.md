# Auth Endpoint Capabilities Implementation

## Summary

This implementation adds user capabilities and role information to the `/auth/me` endpoint and creates a new `/auth/can-edit` endpoint for checking specific post edit permissions.

## Changes Made

### 1. Updated `/kg/v1/auth/me` Endpoint

The endpoint now returns additional fields to help the frontend determine user permissions:

#### New Response Fields:

```json
{
  "id": 123,
  "email": "user@example.com",
  "display_name": "User Name",
  "username": "username",
  "role": "editor",
  "roles": ["editor", "kg_expert"],
  "avatar": "https://...",
  "avatar_url": "https://...",
  
  // NEW: User capabilities
  "capabilities": {
    "edit_posts": true,
    "edit_others_posts": true,
    "edit_published_posts": true,
    "publish_posts": true,
    "delete_posts": true,
    "edit_recipes": true,
    "edit_others_recipes": true,
    "edit_ingredients": true,
    "edit_others_ingredients": true,
    "edit_discussions": true,
    "manage_categories": true,
    "moderate_comments": true,
    "upload_files": true
  },
  
  // NEW: Simplified edit permissions for frontend
  "can_edit": {
    "posts": true,
    "recipes": true,
    "ingredients": true,
    "discussions": true
  },
  
  // NEW: Can edit others' content
  "can_edit_others": {
    "posts": true,
    "recipes": true,
    "ingredients": true,
    "discussions": true
  },
  
  // NEW: Role flags
  "is_admin": false,
  "is_editor": true,
  "is_expert": true,
  "is_author": false,
  "has_editor_access": true,
  
  // NEW: Admin and edit URLs
  "admin_url": "https://api.kidsgourmet.com.tr/wp-admin/",
  "edit_urls": {
    "new_post": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php",
    "new_recipe": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=recipe",
    "new_ingredient": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=ingredient",
    "new_discussion": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=discussion",
    "edit_post": "https://api.kidsgourmet.com.tr/wp-admin/post.php?post=%d&action=edit",
    "edit_recipe": "https://api.kidsgourmet.com.tr/wp-admin/post.php?post=%d&action=edit",
    "edit_ingredient": "https://api.kidsgourmet.com.tr/wp-admin/post.php?post=%d&action=edit"
  },
  
  // Existing fields remain unchanged
  "children": [...],
  "parent_role": "Anne",
  "created_at": "2024-01-01T00:00:00"
}
```

### 2. New `/kg/v1/auth/can-edit` Endpoint

Check if the current user can edit a specific post.

#### Request:

```bash
GET /kg/v1/auth/can-edit?post_id=123
# OR
GET /kg/v1/auth/can-edit?id=123
```

#### Response (Can Edit):

```json
{
  "can_edit": true,
  "post_id": 123,
  "post_type": "recipe",
  "edit_url": "https://api.kidsgourmet.com.tr/wp-admin/post.php?post=123&action=edit"
}
```

#### Response (Cannot Edit):

```json
{
  "can_edit": false,
  "post_id": 123,
  "post_type": "recipe",
  "edit_url": null
}
```

#### Response (Post Not Found):

```json
{
  "can_edit": false,
  "reason": "post_not_found"
}
```

#### Response (Not Authenticated):

```json
{
  "can_edit": false,
  "reason": "not_authenticated"
}
```

## Role Permissions

### Administrator
- `is_admin`: true
- `is_editor`: true
- `has_editor_access`: true
- Can edit all content types
- Can edit others' content

### Editor
- `is_admin`: false
- `is_editor`: true
- `has_editor_access`: true
- Can edit all content types
- Can edit others' content

### KG Expert (kg_expert)
- `is_admin`: false
- `is_editor`: false
- `is_expert`: true
- `has_editor_access`: true
- Can edit all content types
- Can edit others' content

### Author
- `is_admin`: false
- `is_editor`: false
- `is_expert`: false
- `is_author`: true
- `has_editor_access`: false
- Can edit own content only

### Parent (kg_parent) / Subscriber
- `is_admin`: false
- `is_editor`: false
- `is_expert`: false
- `has_editor_access`: false
- Cannot edit content

## Admin URL Configuration

The `admin_url` field uses the `KG_API_URL` constant if defined, otherwise falls back to WordPress's `admin_url()`.

To set a custom admin URL, define the constant in `wp-config.php`:

```php
define('KG_API_URL', 'https://api.kidsgourmet.com.tr');
```

If not defined, the plugin will automatically use the WordPress installation URL.

## Frontend Usage Examples

### Check if user can edit content

```javascript
// Get user info
const response = await fetch('/wp-json/kg/v1/auth/me', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const user = await response.json();

// Show edit buttons based on permissions
if (user.can_edit.posts) {
  // Show "New Post" button
  // Link to: user.edit_urls.new_post
}

if (user.can_edit.recipes) {
  // Show "New Recipe" button
  // Link to: user.edit_urls.new_recipe
}

// Check if user has editor access
if (user.has_editor_access) {
  // Show admin dashboard link
  // Link to: user.admin_url
}
```

### Check if user can edit specific post

```javascript
const postId = 123;
const response = await fetch(`/wp-json/kg/v1/auth/can-edit?post_id=${postId}`, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const result = await response.json();

if (result.can_edit) {
  // Show edit button
  // Link to: result.edit_url
}
```

## Testing

A comprehensive test suite has been created at `tests/test-auth-capabilities.php`. To run the tests in a WordPress environment:

```bash
php tests/test-auth-capabilities.php
```

The test suite verifies:
- Parent users have no edit permissions
- Admin users have all edit permissions
- Expert users have editor-level permissions
- `/auth/can-edit` endpoint works correctly
- Proper error handling for missing parameters and non-existent posts

## Backward Compatibility

All existing fields in the `/auth/me` endpoint response remain unchanged. The new fields are additions only, ensuring backward compatibility with existing frontend implementations.

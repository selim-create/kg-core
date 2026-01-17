# Authorization Fields Added to /user/me Endpoint

## Summary of Changes

The `/kg/v1/user/me` endpoint in `includes/API/UserController.php` has been enhanced to include authorization fields that were previously only available in the `/kg/v1/auth/me` endpoint.

## Changes Made

### 1. Added Authorization Calculations (Lines 1299-1334)

After the `$is_expert` check, the following calculations were added:

```php
// Rol kontrolleri
$roles = $user->roles;
$is_admin = in_array( 'administrator', $roles );
$is_editor = in_array( 'editor', $roles ) || $is_admin;

// Editör seviyesi yetki (admin, editor, kg_expert)
$has_editor_access = $is_admin || $is_editor || $is_expert;

// Admin URL
$admin_url = defined( 'KG_API_URL' ) 
    ? KG_API_URL . '/wp-admin/'
    : admin_url();

// Basitleştirilmiş düzenleme yetkileri (frontend için)
$can_edit = [
    'posts'       => $user->has_cap( 'edit_posts' ),
    'recipes'     => $user->has_cap( 'edit_posts' ),
    'ingredients' => $user->has_cap( 'edit_posts' ),
    'discussions' => $user->has_cap( 'edit_posts' ),
];

// Başkalarının içeriklerini düzenleyebilir mi?
$can_edit_others = [
    'posts'       => $user->has_cap( 'edit_others_posts' ),
    'recipes'     => $user->has_cap( 'edit_others_posts' ),
    'ingredients' => $user->has_cap( 'edit_others_posts' ),
    'discussions' => $user->has_cap( 'edit_others_posts' ),
];

// Edit URLs
$edit_urls = [
    'new_post'       => $admin_url . 'post-new.php',
    'new_recipe'     => $admin_url . 'post-new.php?post_type=recipe',
    'new_ingredient' => $admin_url . 'post-new.php?post_type=ingredient',
    'new_discussion' => $admin_url . 'post-new.php?post_type=discussion',
];
```

### 2. Updated Response Array (Lines 1395-1401)

Added the following fields to the response array:

```php
'is_admin' => $is_admin,
'is_editor' => $is_editor,
'has_editor_access' => $has_editor_access,
'admin_url' => $has_editor_access ? $admin_url : null,
'edit_urls' => $has_editor_access ? $edit_urls : null,
'can_edit' => $can_edit,
'can_edit_others' => $can_edit_others,
```

### 3. Minor Refactoring

- Removed duplicate `$roles` assignment (was at line 1368)
- Combined role-related calculations in one place for better code organization

## Response Format Examples

### Admin User Response
```json
{
  "id": 44,
  "email": "admin@example.com",
  "name": "Admin User",
  "role": "administrator",
  "is_admin": true,
  "is_editor": true,
  "is_expert": true,
  "has_editor_access": true,
  "can_edit": {
    "posts": true,
    "recipes": true,
    "ingredients": true,
    "discussions": true
  },
  "can_edit_others": {
    "posts": true,
    "recipes": true,
    "ingredients": true,
    "discussions": true
  },
  "admin_url": "https://api.kidsgourmet.com.tr/wp-admin/",
  "edit_urls": {
    "new_post": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php",
    "new_recipe": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=recipe",
    "new_ingredient": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=ingredient",
    "new_discussion": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=discussion"
  }
}
```

### Parent User Response
```json
{
  "id": 123,
  "role": "kg_parent",
  "is_admin": false,
  "is_editor": false,
  "is_expert": false,
  "has_editor_access": false,
  "can_edit": {
    "posts": false,
    "recipes": false,
    "ingredients": false,
    "discussions": false
  },
  "can_edit_others": {
    "posts": false,
    "recipes": false,
    "ingredients": false,
    "discussions": false
  },
  "admin_url": null,
  "edit_urls": null
}
```

### Expert User Response
```json
{
  "id": 789,
  "role": "kg_expert",
  "is_admin": false,
  "is_editor": false,
  "is_expert": true,
  "has_editor_access": true,
  "can_edit": {
    "posts": true,
    "recipes": true,
    "ingredients": true,
    "discussions": true
  },
  "can_edit_others": {
    "posts": false,
    "recipes": false,
    "ingredients": false,
    "discussions": false
  },
  "admin_url": "https://api.kidsgourmet.com.tr/wp-admin/",
  "edit_urls": {
    "new_post": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php",
    "new_recipe": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=recipe",
    "new_ingredient": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=ingredient",
    "new_discussion": "https://api.kidsgourmet.com.tr/wp-admin/post-new.php?post_type=discussion"
  }
}
```

## Backward Compatibility

✅ **This change is fully backward compatible:**
- Only **adds** new fields to the response
- Does **not remove** or modify any existing fields
- Existing API consumers will continue to work without any changes
- New fields are optional and can be safely ignored by older clients

## Security Considerations

✅ **Security measures implemented:**
- `admin_url` is only returned when `has_editor_access` is true (null otherwise)
- `edit_urls` is only returned when `has_editor_access` is true (null otherwise)
- Permission checks use WordPress native capabilities (`has_cap`)
- Role checks align with existing authorization logic in `get_current_user()`

## Testing

Two test files have been created:

1. **static-analysis-user-me-auth-fields.php** - Static code analysis test that verifies all required fields are present (can run without WordPress)
   - ✅ All 7 authorization calculations verified
   - ✅ All 7 response fields verified
   - ✅ All array structures verified

2. **test-user-me-authorization-fields.php** - Integration test for WordPress environment
   - Tests admin user permissions
   - Tests parent user permissions
   - Tests expert user permissions
   - Validates correct null values for restricted users

## Impact on Frontend

The frontend `AdminQuickMenu` component will now correctly display:
- "Yeni Tarif" (New Recipe) button for users with editor access
- "Yeni Malzeme" (New Ingredient) button for users with editor access
- "Yeni Yazı" (New Post) button for users with editor access

This resolves the issue where admin users couldn't see these menu options because the `/kg/v1/user/me` endpoint didn't include the necessary permission fields.

## Files Changed

1. `includes/API/UserController.php` - Modified `get_user_me()` method
2. `tests/static-analysis-user-me-auth-fields.php` - New static analysis test
3. `tests/test-user-me-authorization-fields.php` - New integration test

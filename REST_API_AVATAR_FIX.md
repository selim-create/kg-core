# WordPress REST API Avatar URL Fix - Implementation Summary

## Problem

WordPress REST API endpoints (`/wp/v2/posts` and `/wp/v2/users`) return Gravatar URLs in the `avatar_urls` field, even when users have uploaded custom profile photos stored in the `_kg_avatar_id` user meta field.

The frontend uses WordPress's standard `_embedded.author` structure:

```typescript
const getAuthorImage = (post: BlogPost) => {
  const avatarUrls = post._embedded?.author?.[0]?.avatar_urls;
  const avatar = avatarUrls?.['96'] || avatarUrls?.['48'] || avatarUrls?.['24'];
  // This returns Gravatar or ui-avatars.com fallback, not the user's custom photo
};
```

## Solution Implemented

Created a new `RestApiFilters` class that filters WordPress REST API responses to include custom avatar URLs instead of Gravatar URLs.

### Files Created/Modified

#### 1. New File: `includes/API/RestApiFilters.php`

A comprehensive class that implements multiple WordPress filters:

**Filter 1: `rest_prepare_user`**
- Overrides the `avatar_urls` field in `/wp/v2/users` responses
- Replaces all sizes (24, 48, 96) with the custom avatar URL
- Adds a `custom_avatar` field for direct access

**Filter 2: `rest_prepare_post`, `rest_prepare_recipe`, `rest_prepare_discussion`, `rest_prepare_ingredient`**
- Adds `author_avatar` and `author_custom_avatar` fields to post responses
- Makes custom avatars easily accessible without requiring `_embed` parameter

**Filter 3: `pre_get_avatar_data` (Most Important)**
- Intercepts WordPress's `get_avatar_url()` function BEFORE it generates Gravatar URLs
- Affects ALL avatar URL generation in WordPress, including:
  - Direct `get_avatar_url()` calls
  - Embedded author data in REST API (`_embedded.author[0].avatar_urls`)
  - Any other WordPress avatar functionality
- This is the key filter that makes frontend changes unnecessary

#### 2. Modified: `kg-core.php`

Added two lines to include and initialize the RestApiFilters class:

```php
// Line ~160: Include the class file
if ( file_exists( KG_CORE_PATH . 'includes/API/RestApiFilters.php' ) ) 
    require_once KG_CORE_PATH . 'includes/API/RestApiFilters.php';

// Line ~346: Initialize the class
if ( class_exists( '\KG_Core\API\RestApiFilters' ) ) 
    new \KG_Core\API\RestApiFilters();
```

#### 3. New File: `tests/test-rest-api-filters.php`

Static analysis test that verifies:
- RestApiFilters.php exists and contains all required filters
- All required methods are implemented
- kg-core.php properly includes and initializes the class
- Integration with existing Helper class

## Avatar Priority

The implementation maintains the correct priority order:

1. **Custom Avatar** (`_kg_avatar_id` user meta) - User-uploaded profile photo
2. **Google Avatar** (`google_avatar` user meta) - Photo from Google OAuth login
3. **Gravatar** (WordPress default) - Fallback to Gravatar service

This priority is implemented in the existing `Helper::get_user_avatar_url()` method, which is reused by RestApiFilters.

## How It Works

### The `pre_get_avatar_data` Filter

This is the most elegant part of the solution. Instead of trying to intercept REST API responses after they're built, we intercept avatar generation at the source.

When WordPress REST API builds a user response with `_embed` parameter:
1. WordPress calls `get_avatar_url($user_id)` to get the avatar URL
2. Our `pre_get_avatar_data` filter intercepts this call
3. We check for custom avatar → Google avatar → return null (Gravatar fallback)
4. WordPress uses our custom URL instead of generating a Gravatar URL
5. The REST API response automatically contains the correct avatar URL

**Result:** Frontend code doesn't need to change at all! The `_embedded.author[0].avatar_urls` field automatically contains the custom avatar URL.

## Testing

### Automated Test

Run the static analysis test:

```bash
cd /home/runner/work/kg-core/kg-core
php tests/test-rest-api-filters.php
```

Expected output: All 22 tests passing ✓

### Manual Testing Scenarios

#### Test 1: WordPress REST API User Endpoint

```bash
curl https://your-domain.com/wp-json/wp/v2/users/1
```

Expected response:
```json
{
  "id": 1,
  "name": "User Name",
  "avatar_urls": {
    "24": "https://your-domain.com/wp-content/uploads/2024/01/custom-avatar.jpg",
    "48": "https://your-domain.com/wp-content/uploads/2024/01/custom-avatar.jpg",
    "96": "https://your-domain.com/wp-content/uploads/2024/01/custom-avatar.jpg"
  },
  "custom_avatar": "https://your-domain.com/wp-content/uploads/2024/01/custom-avatar.jpg",
  ...
}
```

#### Test 2: WordPress REST API Posts with Embed

```bash
curl https://your-domain.com/wp-json/wp/v2/posts?_embed
```

Expected response:
```json
{
  "id": 123,
  "title": "Post Title",
  "_embedded": {
    "author": [
      {
        "id": 1,
        "name": "User Name",
        "avatar_urls": {
          "24": "https://your-domain.com/wp-content/uploads/2024/01/custom-avatar.jpg",
          "48": "https://your-domain.com/wp-content/uploads/2024/01/custom-avatar.jpg",
          "96": "https://your-domain.com/wp-content/uploads/2024/01/custom-avatar.jpg"
        },
        ...
      }
    ]
  },
  "author_avatar": "https://your-domain.com/wp-content/uploads/2024/01/custom-avatar.jpg",
  ...
}
```

#### Test 3: Custom Post Types (Recipe, Discussion, Ingredient)

```bash
curl https://your-domain.com/wp-json/wp/v2/recipe?_embed
curl https://your-domain.com/wp-json/wp/v2/discussion?_embed
curl https://your-domain.com/wp-json/wp/v2/ingredient?_embed
```

Expected: Same behavior as posts - custom avatar URLs in both `_embedded.author[0].avatar_urls` and `author_avatar` fields.

#### Test 4: Custom KG API Endpoints (Should Already Work)

These endpoints already use `Helper::get_user_avatar_url()` so they should continue working:

```bash
curl https://your-domain.com/wp-json/kg/v1/recipes
curl https://your-domain.com/wp-json/kg/v1/featured
```

## User Meta Fields

The implementation checks these user meta fields in priority order:

1. `_kg_avatar_id` (int) - Attachment ID of uploaded custom avatar
2. `google_avatar` (string) - URL of Google profile photo

To set a custom avatar for a user:

```php
// Upload an image and get the attachment ID
$attachment_id = 123;

// Set it as the user's custom avatar
update_user_meta($user_id, '_kg_avatar_id', $attachment_id);
```

To remove a custom avatar:

```php
delete_user_meta($user_id, '_kg_avatar_id');
```

## Frontend Compatibility

**No frontend changes required!** 

The existing frontend code that uses `_embedded.author[0].avatar_urls` will automatically receive custom avatar URLs:

```typescript
// This code works without modification
const getAuthorImage = (post: BlogPost) => {
  const avatarUrls = post._embedded?.author?.[0]?.avatar_urls;
  const avatar = avatarUrls?.['96'] || avatarUrls?.['48'] || avatarUrls?.['24'];
  // Now returns custom avatar URL instead of Gravatar
};
```

Optionally, frontend can use the new `author_avatar` field for simpler access:

```typescript
const avatar = post.author_avatar; // Direct access to custom avatar
```

## Benefits

1. **No Frontend Changes**: Existing code works immediately
2. **Global Solution**: Affects all avatar URLs throughout WordPress, not just REST API
3. **Backward Compatible**: Falls back to Gravatar if no custom avatar is set
4. **Consistent**: Uses existing `Helper::get_user_avatar_url()` method
5. **Clean**: Minimal code changes, follows WordPress best practices
6. **Tested**: Includes automated test suite

## Edge Cases Handled

1. **No Custom Avatar**: Falls back to Google avatar, then Gravatar
2. **Invalid Attachment ID**: Falls back to next priority level
3. **Deleted Attachment**: Falls back to next priority level
4. **Various Input Types**: Handles user ID, email, WP_User, WP_Post, WP_Comment objects
5. **Multiple Sizes**: Returns same custom avatar URL for all sizes (24, 48, 96)

## Performance Considerations

The `pre_get_avatar_data` filter is very efficient:
- Only runs when avatars are requested
- Uses simple `get_user_meta()` calls (cached by WordPress)
- `wp_get_attachment_url()` is also cached
- No additional database queries beyond what WordPress already does

## Security

- Uses WordPress built-in functions (`get_user_meta`, `wp_get_attachment_url`)
- Validates attachment IDs exist before using URLs
- Sanitizes all user inputs through WordPress functions
- No direct database queries or file system access

## Future Enhancements

Possible improvements:
1. Add image size support (generate different sizes instead of using same URL for all)
2. Add admin UI for uploading custom avatars (currently requires code)
3. Add bulk import tool for migrating existing avatars
4. Add avatar caching for improved performance

## Conclusion

This implementation provides a clean, efficient solution to the WordPress REST API avatar URL problem. By intercepting avatar generation at the source with `pre_get_avatar_data`, we ensure that custom avatar URLs are used throughout the entire WordPress system, including REST API responses, without requiring any frontend code changes.

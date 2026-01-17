# WordPress REST API Avatar Fix - Example Responses

This file shows example API responses BEFORE and AFTER implementing the RestApiFilters fix.

## Example 1: User Endpoint

### BEFORE (Gravatar URL)
```bash
curl https://example.com/wp-json/wp/v2/users/1
```

```json
{
  "id": 1,
  "name": "John Doe",
  "url": "https://example.com",
  "description": "Parent and food enthusiast",
  "link": "https://example.com/author/johndoe",
  "slug": "johndoe",
  "avatar_urls": {
    "24": "https://secure.gravatar.com/avatar/abc123?s=24&d=mm&r=g",
    "48": "https://secure.gravatar.com/avatar/abc123?s=48&d=mm&r=g",
    "96": "https://secure.gravatar.com/avatar/abc123?s=96&d=mm&r=g"
  }
}
```

### AFTER (Custom Avatar URL) ✓
```bash
curl https://example.com/wp-json/wp/v2/users/1
```

```json
{
  "id": 1,
  "name": "John Doe",
  "url": "https://example.com",
  "description": "Parent and food enthusiast",
  "link": "https://example.com/author/johndoe",
  "slug": "johndoe",
  "avatar_urls": {
    "24": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg",
    "48": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg",
    "96": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg"
  },
  "custom_avatar": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg"
}
```

---

## Example 2: Posts with Embedded Author

### BEFORE (Gravatar in _embedded.author)
```bash
curl https://example.com/wp-json/wp/v2/posts?_embed
```

```json
[
  {
    "id": 123,
    "title": {
      "rendered": "Bebeklerde Katı Gıdaya Geçiş Rehberi"
    },
    "author": 1,
    "_embedded": {
      "author": [
        {
          "id": 1,
          "name": "John Doe",
          "avatar_urls": {
            "24": "https://secure.gravatar.com/avatar/abc123?s=24&d=mm&r=g",
            "48": "https://secure.gravatar.com/avatar/abc123?s=48&d=mm&r=g",
            "96": "https://secure.gravatar.com/avatar/abc123?s=96&d=mm&r=g"
          }
        }
      ]
    }
  }
]
```

### AFTER (Custom Avatar in _embedded.author) ✓
```bash
curl https://example.com/wp-json/wp/v2/posts?_embed
```

```json
[
  {
    "id": 123,
    "title": {
      "rendered": "Bebeklerde Katı Gıdaya Geçiş Rehberi"
    },
    "author": 1,
    "author_avatar": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg",
    "_embedded": {
      "author": [
        {
          "id": 1,
          "name": "John Doe",
          "avatar_urls": {
            "24": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg",
            "48": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg",
            "96": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg"
          },
          "custom_avatar": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg"
        }
      ]
    }
  }
]
```

---

## Example 3: Recipe Custom Post Type

### BEFORE
```bash
curl https://example.com/wp-json/wp/v2/recipe/456?_embed
```

```json
{
  "id": 456,
  "title": {
    "rendered": "Avokadolu Bebek Püresi"
  },
  "author": 1,
  "_embedded": {
    "author": [
      {
        "id": 1,
        "name": "John Doe",
        "avatar_urls": {
          "24": "https://secure.gravatar.com/avatar/abc123?s=24&d=mm&r=g",
          "48": "https://secure.gravatar.com/avatar/abc123?s=48&d=mm&r=g",
          "96": "https://secure.gravatar.com/avatar/abc123?s=96&d=mm&r=g"
        }
      }
    ]
  }
}
```

### AFTER ✓
```bash
curl https://example.com/wp-json/wp/v2/recipe/456?_embed
```

```json
{
  "id": 456,
  "title": {
    "rendered": "Avokadolu Bebek Püresi"
  },
  "author": 1,
  "author_avatar": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg",
  "_embedded": {
    "author": [
      {
        "id": 1,
        "name": "John Doe",
        "avatar_urls": {
          "24": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg",
          "48": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg",
          "96": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg"
        },
        "custom_avatar": "https://example.com/wp-content/uploads/2024/01/custom-profile-photo.jpg"
      }
    ]
  }
}
```

---

## Frontend Code Compatibility

### Existing Frontend Code (No Changes Required)

This code from `/kesfet/[slug]/page.tsx` works without modification:

```typescript
const getAuthorImage = (post: BlogPost) => {
  const avatarUrls = post._embedded?.author?.[0]?.avatar_urls;
  const avatar = avatarUrls?.['96'] || avatarUrls?.['48'] || avatarUrls?.['24'];
  // Now automatically returns custom avatar URL instead of Gravatar ✓
  return avatar || '/default-avatar.png';
};
```

### Optional: Simplified Access

Frontend can now also use the direct `author_avatar` field:

```typescript
const getAuthorImage = (post: BlogPost) => {
  // Direct access to custom avatar
  return post.author_avatar || post._embedded?.author?.[0]?.custom_avatar || '/default-avatar.png';
};
```

---

## Avatar Priority Examples

### Case 1: User with Custom Uploaded Avatar
User meta: `_kg_avatar_id = 789` (attachment ID)

Response:
```json
"avatar_urls": {
  "96": "https://example.com/wp-content/uploads/2024/01/my-profile-pic.jpg"
}
```

### Case 2: User with Google OAuth Avatar (No Custom Upload)
User meta: 
- `_kg_avatar_id` = empty
- `google_avatar = "https://lh3.googleusercontent.com/xyz123"`

Response:
```json
"avatar_urls": {
  "96": "https://lh3.googleusercontent.com/xyz123"
}
```

### Case 3: User with No Custom Avatar (Fallback to Gravatar)
User meta:
- `_kg_avatar_id` = empty
- `google_avatar` = empty

Response:
```json
"avatar_urls": {
  "96": "https://secure.gravatar.com/avatar/hash?s=96&d=mm&r=g"
}
```

---

## Testing Commands

### Test User Endpoint
```bash
curl -s https://example.com/wp-json/wp/v2/users/1 | jq '.avatar_urls'
```

Expected output:
```json
{
  "24": "https://example.com/wp-content/uploads/.../custom-avatar.jpg",
  "48": "https://example.com/wp-content/uploads/.../custom-avatar.jpg",
  "96": "https://example.com/wp-content/uploads/.../custom-avatar.jpg"
}
```

### Test Posts with Embed
```bash
curl -s "https://example.com/wp-json/wp/v2/posts?_embed&per_page=1" | jq '.[0]._embedded.author[0].avatar_urls'
```

Expected output:
```json
{
  "24": "https://example.com/wp-content/uploads/.../custom-avatar.jpg",
  "48": "https://example.com/wp-content/uploads/.../custom-avatar.jpg",
  "96": "https://example.com/wp-content/uploads/.../custom-avatar.jpg"
}
```

### Test Recipe Custom Post Type
```bash
curl -s "https://example.com/wp-json/wp/v2/recipe?_embed&per_page=1" | jq '.[0]._embedded.author[0].avatar_urls'
```

---

## User Meta Management

### Set Custom Avatar for a User (WordPress Admin or Code)

```php
// After uploading an image to WordPress media library
$attachment_id = 789; // ID of the uploaded image
$user_id = 1;

update_user_meta($user_id, '_kg_avatar_id', $attachment_id);
```

### Remove Custom Avatar (Fallback to Google or Gravatar)

```php
$user_id = 1;
delete_user_meta($user_id, '_kg_avatar_id');
```

### Check Current Avatar Settings

```php
$user_id = 1;

$custom_avatar_id = get_user_meta($user_id, '_kg_avatar_id', true);
$google_avatar = get_user_meta($user_id, 'google_avatar', true);

if ($custom_avatar_id) {
    echo "Custom avatar: " . wp_get_attachment_url($custom_avatar_id);
} elseif ($google_avatar) {
    echo "Google avatar: " . $google_avatar;
} else {
    echo "Using Gravatar";
}
```

---

## Summary

✅ **Custom avatars are now returned in ALL WordPress REST API endpoints**
✅ **Frontend code works without modification**
✅ **Backward compatible - falls back to Gravatar if no custom avatar**
✅ **Consistent behavior across all post types and endpoints**

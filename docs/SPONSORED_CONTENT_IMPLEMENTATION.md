# Sponsored Content Support Implementation

## Overview
This implementation adds comprehensive sponsored content support for WordPress Posts, enabling GAM (Google Ad Manager) integration for impression and click tracking.

## Files Created

### 1. `/includes/Admin/PostMetaBox.php`
**Purpose**: Meta box for managing sponsored content fields on WordPress Posts.

**Features**:
- Meta box visible only on `post` post type
- 8 meta fields as specified:
  - `_kg_is_sponsored` - Checkbox to enable sponsored content
  - `_kg_sponsor_name` - Text field for brand name
  - `_kg_sponsor_url` - URL field for brand website
  - `_kg_sponsor_logo` - Media upload for sponsor logo (Attachment ID)
  - `_kg_sponsor_light_logo` - Media upload for light logo (Attachment ID)
  - `_kg_direct_redirect` - Radio buttons for redirect behavior
  - `_kg_gam_impression_url` - URL field for GAM impression pixel
  - `_kg_gam_click_url` - URL field for GAM click tracker

**Security**:
- Nonce verification (`kg_sponsor_nonce`)
- Capability check (`edit_post`)
- Autosave prevention
- Post type validation

**Sanitization**:
- `sanitize_text_field()` for text inputs
- `esc_url_raw()` for URL inputs
- `absint()` for attachment IDs
- Input validation for radio buttons

**Conditional Display**:
- Sponsor fields hidden by default
- Shown only when "Sponsorlu Gönderi mi?" checkbox is checked
- JavaScript toggle implemented in `sponsor-media.js`

**Data Cleanup**:
- All sponsor meta fields deleted when post is marked as non-sponsored

### 2. `/assets/admin/js/sponsor-media.js`
**Purpose**: JavaScript for handling media uploads and field visibility.

**Features**:
- WordPress Media Uploader integration
- Logo upload functionality
- Logo preview display
- Logo removal functionality
- Toggle visibility of sponsor fields based on checkbox state

**Technical Details**:
- Uses `wp.media` API
- Handles both sponsor logo and light logo uploads
- Updates hidden input fields with attachment IDs
- Updates preview areas with selected images
- Responsive to user interactions

### 3. REST API Integration
**Location**: `/kg-core.php` (rest_api_init hook)

**Field Name**: `sponsor_data`

**Returns**:
- `null` for non-sponsored posts
- Object with sponsor data for sponsored posts

**Structure**:
```json
{
  "is_sponsored": true,
  "sponsor_name": "Brand Name",
  "sponsor_url": "https://example.com",
  "sponsor_logo": {
    "id": 123,
    "url": "https://example.com/logo.png"
  },
  "sponsor_light_logo": {
    "id": 124,
    "url": "https://example.com/logo-light.png"
  },
  "direct_redirect": true,
  "gam_impression_url": "https://ad.doubleclick.net/...",
  "gam_click_url": "https://ad.doubleclick.net/...adurl="
}
```

**Features**:
- Converts attachment IDs to URLs automatically
- Returns both ID and URL for logos
- Boolean conversion for `direct_redirect`
- Null handling for missing logos

## Files Modified

### `/kg-core.php`
**Changes**:
1. Added `PostMetaBox.php` inclusion (line ~62)
2. Added `PostMetaBox` initialization in admin context (line ~117)
3. Added sponsor-media.js enqueue for post edit screens (lines ~194-207)
4. Added REST API field registration (lines ~211-248)

## Usage

### Admin Interface
1. Edit or create a WordPress Post
2. Find "Sponsorlu İçerik Bilgileri" meta box
3. Check "Sponsorlu Gönderi mi?" to enable sponsor fields
4. Fill in sponsor information:
   - Sponsor name and URL
   - Upload logos using media uploader
   - Set redirect behavior
   - Add GAM tracking URLs
5. Save the post

### REST API
Access sponsor data through WordPress REST API:

```
GET /wp-json/wp/v2/posts/{id}
```

The response will include `sponsor_data` field with all sponsor information.

### Frontend Integration
Use the `sponsor_data` field to:
- Display sponsor logos
- Show sponsor attribution
- Handle click redirects
- Track impressions with GAM pixel
- Track clicks with GAM click tracker

## GAM Integration

### Impression Tracking
Use the `gam_impression_url` to load a 1x1 pixel:
```html
<img src="{gam_impression_url}" width="1" height="1" alt="" />
```

### Click Tracking
Use the `gam_click_url` as base URL for click tracking:
```javascript
const trackingUrl = `${sponsor_data.gam_click_url}${encodeURIComponent(sponsor_data.sponsor_url)}`;
```

### Direct Redirect Logic
```javascript
if (sponsor_data.direct_redirect) {
  // Redirect to sponsor URL with tracking
  window.location.href = trackingUrl;
} else {
  // Show post detail page
  // Load impression pixel in background
}
```

## Technical Notes

### Meta Keys
All meta keys are prefixed with `_kg_` (private meta):
- `_kg_is_sponsored`
- `_kg_sponsor_name`
- `_kg_sponsor_url`
- `_kg_sponsor_logo`
- `_kg_sponsor_light_logo`
- `_kg_direct_redirect`
- `_kg_gam_impression_url`
- `_kg_gam_click_url`

### WordPress Coding Standards
- Follows WordPress PHP Coding Standards
- Uses WordPress escaping functions
- Implements WordPress nonce security
- Uses WordPress capabilities system

### Browser Compatibility
JavaScript is compatible with:
- Modern browsers (ES5+)
- WordPress admin interface
- WordPress Media Uploader

## Testing

A test script is provided at `/test-sponsored-content.php` to verify:
1. PostMetaBox class loading
2. Meta data save functionality
3. Meta data retrieval
4. REST API field structure
5. Non-sponsored post handling

Run the test script:
```bash
php test-sponsored-content.php
```

Or via WP-CLI:
```bash
wp eval-file test-sponsored-content.php
```

## Future Enhancements

Potential improvements:
1. Admin UI styling (CSS for meta box)
2. Validation for GAM URLs (ensure correct format)
3. Preview functionality for redirect behavior
4. Analytics integration
5. Sponsor logo size validation
6. Bulk edit support for sponsored posts
7. Custom post list column showing sponsor status

## Support

For issues or questions related to sponsored content support, please refer to:
- WordPress REST API documentation
- Google Ad Manager documentation
- WordPress Media Uploader API documentation

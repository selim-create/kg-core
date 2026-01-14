# Featured Content API Fixes

## Summary of Issues Fixed

This document outlines the critical fixes made to the Featured Content API in `includes/API/FeaturedController.php`.

## Issues Identified and Resolved

### 1. ✅ Sponsored Content Appearing Twice (CRITICAL)

**Problem:** Sponsored content was appearing both as "featured posts" AND "sponsored content" in the API response, causing duplicates.

**Root Cause:** 
- `get_featured_posts()` was fetching ALL posts with `_kg_is_featured = 1`, including sponsored posts
- `get_sponsored_content()` was fetching ALL posts with `_kg_is_sponsored = 1`, regardless of featured status

**Solution:**
```php
// get_featured_posts() - NOW EXCLUDES SPONSORED CONTENT
'meta_query' => [
    'relation' => 'AND',
    [
        'key' => '_kg_is_featured',
        'value' => '1',
        'compare' => '='
    ],
    [
        'relation' => 'OR',
        [
            'key' => '_kg_is_sponsored',
            'compare' => 'NOT EXISTS'  // Post doesn't have sponsored meta
        ],
        [
            'key' => '_kg_is_sponsored',
            'value' => '1',
            'compare' => '!='  // Post has sponsored = 0
        ]
    ]
]

// get_sponsored_content() - NOW REQUIRES FEATURED FLAG
'meta_query' => [
    'relation' => 'AND',
    [
        'key' => '_kg_is_sponsored',
        'value' => '1',
        'compare' => '='
    ],
    [
        'key' => '_kg_is_featured',  // NEW: Must also be featured
        'value' => '1',
        'compare' => '='
    ]
]
```

**Impact:** Sponsored content now appears ONLY ONCE in the API response, under the "sponsor" type, only if it's also marked as featured.

---

### 2. ✅ Discount Text Truncation

**Problem:** Discount text like "%50 indirim alın" was appearing as "indirim alın" (percentage symbol missing).

**Root Cause:** The `discount_text` field wasn't being decoded with `decode_html_entities()`, causing HTML entities like `%` to be improperly handled.

**Solution:**
```php
$discount_text = get_post_meta( $post->ID, '_kg_discount_text', true ) ?: '';
$discount_text = \KG_Core\Utils\Helper::decode_html_entities( $discount_text ); // NEW
```

**Impact:** Discount text now displays properly with all special characters intact.

---

### 3. ✅ Missing `direct_redirect` Field

**Problem:** The `direct_redirect` field was stored in the database but not returned in the API response.

**Root Cause:** The field was saved in `PostMetaBox.php` but never read in `get_sponsored_content()`.

**Solution:**
```php
// Read the meta field
$direct_redirect = get_post_meta( $post->ID, '_kg_direct_redirect', true ) === '1';

// Include in API response
'meta' => [
    // ... other fields
    'direct_redirect' => $direct_redirect,  // NEW
    // ... other fields
]
```

**Impact:** Frontend can now properly handle direct redirects to sponsor URLs vs. post detail pages.

---

### 4. ✅ Featured Questions Not Appearing

**Problem:** Featured questions (discussions) weren't appearing in the API response.

**Root Cause:** The `Discussion` post type has a `force_pending_status()` filter that forces non-admin submissions to "pending" status, but the API was only querying "publish" status.

**Solution:**
```php
// get_featured_questions() - NOW INCLUDES PENDING STATUS
'post_status' => ['publish', 'pending'],  // Was: 'publish'
```

**Impact:** Featured questions now appear in the API regardless of whether they're published or pending.

---

## API Response Examples

### Before Fix - Sponsored Content Appearing Twice
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "type": "post",  // ❌ WRONG: Appearing as regular post
      "title": "Sponsored Article",
      "meta": {
        "category": "Guide"
      }
    },
    {
      "id": 123,  // ❌ DUPLICATE: Same ID
      "type": "sponsor",
      "title": "Sponsored Article",
      "meta": {
        "sponsor_name": "Brand X",
        "discount_text": "indirim alın"  // ❌ TRUNCATED
        // ❌ MISSING: direct_redirect
      }
    }
  ]
}
```

### After Fix - Sponsored Content Appears Once
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "type": "sponsor",  // ✅ Only appears as sponsor
      "title": "Sponsored Article",
      "meta": {
        "sponsor_name": "Brand X",
        "discount_text": "%50 indirim alın",  // ✅ FIXED: Full text
        "direct_redirect": true  // ✅ FIXED: Field included
      }
    }
  ]
}
```

---

## Testing

### Automated Tests

Run the comprehensive test suite:
```bash
php test-featured-fix.php
```

This validates:
- ✅ `get_featured_posts()` excludes sponsored content
- ✅ `get_sponsored_content()` requires featured flag
- ✅ `direct_redirect` field is present
- ✅ `discount_text` is decoded
- ✅ `get_featured_questions()` includes pending status

### Manual Testing Checklist

1. **Test Sponsored Content Duplication**
   - Create a post with BOTH `_kg_is_featured = 1` AND `_kg_is_sponsored = 1`
   - Call `/wp-json/kg/v1/featured?type=all`
   - ✅ Verify the post appears ONLY ONCE as type "sponsor"
   - Call `/wp-json/kg/v1/featured?type=post`
   - ✅ Verify the post DOES NOT appear
   - Call `/wp-json/kg/v1/featured?type=sponsor`
   - ✅ Verify the post DOES appear

2. **Test Non-Featured Sponsored Content**
   - Create a post with `_kg_is_sponsored = 1` but `_kg_is_featured = 0` (or not set)
   - Call `/wp-json/kg/v1/featured?type=sponsor`
   - ✅ Verify the post DOES NOT appear

3. **Test Direct Redirect Field**
   - Create a sponsored post with `_kg_direct_redirect = 1`
   - Call `/wp-json/kg/v1/featured?type=sponsor`
   - ✅ Verify response includes `"direct_redirect": true`

4. **Test Discount Text**
   - Create a sponsored post with `_kg_discount_text = "%50 indirim alın"`
   - Call `/wp-json/kg/v1/featured?type=sponsor`
   - ✅ Verify response includes full text: `"discount_text": "%50 indirim alın"`

5. **Test Featured Questions**
   - Create a discussion with `_kg_is_featured = 1` and status "pending"
   - Call `/wp-json/kg/v1/featured?type=question`
   - ✅ Verify the question appears in the response

---

## Files Modified

1. **includes/API/FeaturedController.php**
   - Updated `get_featured_posts()` to exclude sponsored content
   - Updated `get_sponsored_content()` to require featured flag
   - Added `direct_redirect` field to sponsored content response
   - Added HTML entity decoding to `discount_text`
   - Updated `get_featured_questions()` to include pending status

2. **test-featured-fix.php** (NEW)
   - Comprehensive test suite validating all fixes

---

## Migration Notes

### No Database Changes Required

All fixes are implemented at the application level (PHP code). No database migrations are needed.

### No Breaking Changes

These fixes are **backward compatible**:
- Existing API consumers will continue to work
- The API structure remains the same
- Only the data filtering logic has changed

### Recommended Actions

1. **Clear any API response caches** if using caching plugins
2. **Test frontend applications** that consume the `/wp-json/kg/v1/featured` endpoint
3. **Verify sponsored content displays correctly** on the frontend

---

## API Endpoint Reference

### GET `/wp-json/kg/v1/featured`

**Parameters:**
- `limit` (optional, default: 5, max: 50) - Number of items to return
- `type` (optional, default: 'all') - Filter by type: 'all', 'recipe', 'post', 'question', 'ingredient', 'sponsor'

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "type": "sponsor",
      "title": "...",
      "slug": "...",
      "image": "...",
      "excerpt": "...",
      "date": "...",
      "meta": {
        "sponsor_name": "...",
        "sponsor_logo": "...",
        "sponsor_light_logo": "...",
        "sponsor_url": "...",
        "direct_redirect": true,
        "category": "...",
        "category_slug": "...",
        "has_discount": true,
        "discount_text": "%50 indirim alın"
      }
    }
  ]
}
```

---

## Related Documentation

- See `PostMetaBox.php` for how sponsored content meta fields are saved
- See `SPONSORED_CONTENT_IMPLEMENTATION.md` for original sponsored content implementation
- See `FEATURED_CONTENT_IMPLEMENTATION.md` for original featured content implementation

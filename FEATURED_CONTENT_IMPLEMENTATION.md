# Featured Content Enhancement Implementation

## Overview
This implementation adds comprehensive featured content support for multiple post types and fixes HTML entity encoding issues across the platform.

## Changes Made

### 1. Post Type: Standard Posts (`post`)

#### Admin UI Changes (`includes/Admin/PostMetaBox.php`)
- ✅ Added "Öne Çıkan Gönderi mi?" checkbox
- ✅ Added discount fields for sponsored posts:
  - `_kg_has_discount` - Boolean checkbox for discount availability
  - `_kg_discount_text` - Text field for discount description
- ✅ Meta fields are saved when posts are created/updated
- ✅ Featured status stored in `_kg_is_featured` meta field

### 2. Post Type: Ingredients (`ingredient`)

#### Admin UI Changes (`includes/Admin/IngredientMetaBox.php`)
- ✅ Added "Öne Çıkan Malzeme mi?" checkbox
- ✅ Meta field `_kg_is_featured` registered and saved
- ✅ Checkbox appears at the top of ingredient details metabox

### 3. Featured API Endpoint (`includes/API/FeaturedController.php`)

#### New Features
- ✅ Added `ingredient` to supported content types
- ✅ Implemented `get_featured_ingredients()` method
  - Queries ingredients with `_kg_is_featured = '1'`
  - Returns formatted ingredient data with metadata
- ✅ Added `get_initials()` helper method (Turkish-aware)
  - Properly handles Turkish characters (ş, ğ, ı, etc.)
  - Uses `mb_substr` and `mb_strtoupper` for multibyte support

#### Enhanced Response Metadata

**Recipes:**
- HTML entity decoding for: title, excerpt, age_group, meal_type, diet_types

**Posts:**
- HTML entity decoding for: title, excerpt, category
- Added: `category_slug`, `author_avatar`, improved `read_time` calculation
- Better excerpt handling (uses post_excerpt if available, falls back to content)

**Questions:**
- HTML entity decoding for: title
- Added: `author_avatar`
- Improved initials generation (Turkish-aware)

**Ingredients:**
- HTML entity decoding for: title, excerpt
- Metadata: `start_age`, `allergy_risk`, `season`, `category`
- Smart age display formatting (e.g., "6 Ay" → "+6 Ay")

**Sponsors:**
- HTML entity decoding for: title, excerpt, category
- Added: `category_slug`, `discount_text`
- Enhanced discount support with dedicated fields

### 4. HTML Entity Decoding (`kg-core.php`)

#### REST API Filters
Added filters to decode HTML entities in taxonomy responses:

```php
// Taxonomies with HTML entity decoding
- age-group
- meal-type
- diet-type
- category
```

Each filter decodes both `name` and `description` fields using:
```php
html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')
```

This fixes issues like:
- `&amp;` → `&`
- `&quot;` → `"`
- `&#8217;` → `'`
- `&#8220;` → `"`
- Turkish characters preserved correctly

### 5. Helper Utility (`includes/Utils/Helper.php`)

The existing `decode_html_entities()` method is used throughout:
```php
public static function decode_html_entities( $text ) {
    if ( empty( $text ) ) {
        return $text;
    }
    return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}
```

## API Endpoint Usage

### Endpoint: `/wp-json/kg/v1/featured`

#### Parameters
- `limit` (optional, default: 5, max: 50) - Number of items to return
- `type` (optional, default: 'all') - Filter by content type
  - Valid values: `all`, `recipe`, `post`, `question`, `ingredient`, `sponsor`

#### Examples

**Get all featured content:**
```
GET /wp-json/kg/v1/featured?limit=10
```

**Get only featured ingredients:**
```
GET /wp-json/kg/v1/featured?type=ingredient&limit=5
```

**Get only featured posts:**
```
GET /wp-json/kg/v1/featured?type=post&limit=5
```

#### Response Format

All items include:
- `id` - Post ID
- `type` - Content type (recipe, post, question, ingredient, sponsor)
- `title` - Decoded title
- `slug` - URL slug
- `image` - Featured image URL (or empty string)
- `excerpt` - Decoded excerpt
- `date` - ISO 8601 date
- `meta` - Type-specific metadata

**Ingredient Response Example:**
```json
{
  "id": 123,
  "type": "ingredient",
  "title": "Avokado",
  "slug": "avokado",
  "image": "https://example.com/avokado.jpg",
  "excerpt": "Bebekler için harika bir besin...",
  "date": "2024-01-15T10:30:00",
  "meta": {
    "start_age": "+6 Ay",
    "allergy_risk": "Düşük",
    "season": "Tüm Mevsim",
    "category": "Meyveler"
  }
}
```

**Post Response Example:**
```json
{
  "id": 456,
  "type": "post",
  "title": "Bebek Beslenmesi Rehberi",
  "slug": "bebek-beslenmesi-rehberi",
  "image": "https://example.com/guide.jpg",
  "excerpt": "Bebeğinizi sağlıklı beslemenin yolları...",
  "date": "2024-01-16T14:20:00",
  "meta": {
    "category": "Rehberler",
    "category_slug": "rehberler",
    "author": "Dr. Ayşe Yılmaz",
    "author_avatar": "https://example.com/avatar.jpg",
    "read_time": "5 dk"
  }
}
```

## Testing

Run the test script to verify implementation:
```bash
php test-featured-enhancements.php
```

This validates:
- ✅ Syntax of all modified files
- ✅ Presence of required methods and fields
- ✅ HTML entity decoding implementation
- ✅ Taxonomy REST API filters
- ✅ Featured checkbox in admin metaboxes

## Database Schema

### Meta Fields Added/Modified

**Post (`post`):**
- `_kg_is_featured` - String ('0' or '1')
- `_kg_has_discount` - String ('0' or '1')
- `_kg_discount_text` - String

**Ingredient (`ingredient`):**
- `_kg_is_featured` - String ('0' or '1')

**Discussion (`discussion`):**
- `_kg_is_featured` - Already existed
- `_kg_answer_count` - Already existed

## Migration Notes

No database migration needed. New meta fields will be:
- Set to '0' by default for existing posts
- Created when posts are saved with the checkbox checked

## Backward Compatibility

✅ Fully backward compatible:
- Existing featured recipes and discussions continue to work
- New ingredient and post featured items are additive
- HTML entity decoding doesn't break existing data
- API responses maintain same structure with enhanced metadata

## Security Considerations

- ✅ All meta saves use proper WordPress sanitization
- ✅ Nonce verification on all form submissions
- ✅ Capability checks (`current_user_can('edit_post')`)
- ✅ HTML entity decoding uses secure WordPress/PHP functions

## Performance

- Minimal impact: Only adds meta field queries and decoding
- Featured items use meta_query (indexed in WordPress)
- Sorting done after fetching (already limited by query)
- No additional database tables needed

## Future Enhancements

Potential improvements:
1. Add featured item order/priority meta field
2. Add featured item duration (auto-unfeature after date)
3. Add featured item analytics (impression tracking)
4. Create admin UI to manage featured items from one place

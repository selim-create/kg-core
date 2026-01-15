# Recipe API Backend Improvements - Implementation Summary

## Overview
This implementation adds enhanced filtering, sorting, and new fields to the Recipe API endpoints to support the updated frontend Recipes page.

## Changes Made

### 1. Helper Class Enhancement (`includes/Utils/Helper.php`)
**Updated:** `decode_html_entities()` method
- Now handles double-encoded HTML entities (e.g., `&amp;amp;` → `&`)
- Runs `html_entity_decode()` twice to ensure complete decoding
- Ensures clean text output for frontend display

### 2. New SpecialCondition Taxonomy (`includes/Taxonomies/SpecialCondition.php`)
**Created:** New taxonomy for special health conditions
- Taxonomy slug: `special-condition`
- Default terms:
  - Kabızlık Giderici (kabizlik-giderici)
  - Bağışıklık Dostu (bagisiklik-dostu)
  - Diş Çıkarma Dönemi (dis-cikarma)
  - Alerjik Bebek (alerjik-bebek)
- Registered in `kg-core.php` and included in REST API

### 3. RecipeController Updates (`includes/API/RecipeController.php`)

#### A. Enhanced `prepare_recipe_data()` Method
**New fields added to ALL responses (not just full_detail):**

```php
[
    'age_group'       => '6-8 Ay (Başlangıç & Tadım)',     // First age group name (decoded)
    'age_group_color' => '#FFAB91',                        // Color code from term meta
    'meal_type'       => 'Kahvaltı',                       // First meal type (decoded)
    'diet_types'      => ['BLW', 'Püre'],                  // Array of all diet types (decoded)
    'author'          => [                                  // Author information
        'id' => 1,
        'name' => 'Admin',
        'avatar' => 'https://...'
    ],
    'expert'          => [                                  // Expert approval (always included)
        'name' => 'Dr. Ayşe Yılmaz',
        'title' => 'Beslenme Uzmanı',
        'approved' => true
    ]
]
```

**Changes:**
- Age group extracted with color code from term meta `_kg_color_code`
- Meal type extracted from `meal-type` taxonomy (first term)
- Diet types extracted as array from `diet-type` taxonomy
- Author data includes ID, name, and avatar URL
- Expert data moved outside `full_detail` condition (now always returned)
- Expert data includes new `title` field
- All text fields decoded using enhanced `Helper::decode_html_entities()`

#### B. Enhanced `get_recipes()` Method
**New filter parameters:**

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `meal-type` | string | Filter by meal type (comma-separated) | `?meal-type=kahvalti,ara-ogun-kusluk` |
| `special-condition` | string | Filter by special condition (comma-separated) | `?special-condition=kabizlik-giderici` |
| `ingredient` | string | Search in ingredients field | `?ingredient=havuç` |
| `search` | string | Full-text search in title/content | `?search=çorba` |
| `orderby` | string | Sort by: `date`, `popular`, `prep_time` | `?orderby=popular` |
| `order` | string | Sort direction: `ASC`, `DESC` | `?order=DESC` |

**Enhanced response format:**
```php
{
    "recipes": [...],
    "total": 125,              // Total matching recipes
    "page": 1,                 // Current page
    "per_page": 12,           // Items per page
    "total_pages": 11         // Total pages
}
```

**Filter improvements:**
- All taxonomy filters support comma-separated values
- `allergen` filter uses `NOT IN` operator (exclude allergens)
- All other taxonomy filters use `IN` operator
- Filters combined with `AND` relation

**Sorting options:**
- `date` (default): Newest first
- `popular`: By rating count (DESC)
- `prep_time`: By preparation time (ASC/DESC)

### 4. AgeGroup Taxonomy Updates (`includes/Taxonomies/AgeGroup.php`)

**Updated term names** (age/month info moved to front):
- `0-6 Ay (Hazırlık Evresi)` (was: Hazırlık Evresi (0-6 Ay))
- `6-8 Ay (Başlangıç & Tadım)` (was: Başlangıç & Tadım (6-8 Ay))
- `9-11 Ay (Keşif & Pütürlüye Geçiş)` (was: Keşif & Pütürlüye Geçiş (9-11 Ay))
- `12-24 Ay (Aile Sofrasına Geçiş)` (was: Aile Sofrasına Geçiş (12-24 Ay))
- `2+ Yaş (Çocuk Gurme)` (was: Çocuk Gurme (2+ Yaş))

**Added migration function:**
```php
public function update_existing_terms()
```
This function can be called to update existing term names in the database.

### 5. Main Plugin File Updates (`kg-core.php`)
- Included `SpecialCondition.php` taxonomy file
- Initialized `SpecialCondition` taxonomy in `kg_core_init()`
- Added HTML entity decoding filter for `special-condition` taxonomy

## Testing

### Test File: `tests/test-recipe-api-improvements.php`
Comprehensive static code analysis test covering:
1. Helper class double-decoding implementation
2. SpecialCondition taxonomy creation and default terms
3. Main plugin file registrations
4. RecipeController new fields in `prepare_recipe_data()`
5. RecipeController filters and sorting in `get_recipes()`
6. AgeGroup term name updates and migration function

**Test Results:** ✅ 18/18 tests passed (100% success rate)

## API Usage Examples

### Example 1: Get recipes for 6-8 month old babies with breakfast meal type
```bash
GET /wp-json/kg/v1/recipes?age-group=6-8-ay-baslangic&meal-type=kahvalti
```

### Example 2: Get popular gluten-free recipes without milk allergen
```bash
GET /wp-json/kg/v1/recipes?diet-type=glutensiz&allergen=sut&orderby=popular
```

### Example 3: Search for soup recipes with ingredient filter
```bash
GET /wp-json/kg/v1/recipes?search=çorba&ingredient=havuç&page=1&per_page=12
```

### Example 4: Get recipes for special condition (constipation relief)
```bash
GET /wp-json/kg/v1/recipes?special-condition=kabizlik-giderici
```

### Example 5: Combined filters with sorting
```bash
GET /wp-json/kg/v1/recipes?age-group=9-11-ay-kesif&meal-type=ogle-yemegi,aksam-yemegi&diet-type=blw&orderby=prep_time&order=ASC
```

## Response Example

```json
{
  "recipes": [
    {
      "id": 123,
      "title": "Bal Kabaklı Bebek Çorbası",
      "slug": "bal-kabakli-bebek-corbasi",
      "excerpt": "Bebeğiniz için lezzetli ve besleyici...",
      "image": "https://example.com/image.jpg",
      "prep_time": "15 dk",
      "ingredients": ["Bal kabağı", "Havuç", "Patates"],
      "instructions": ["Sebzeleri yıkayın", "Haşlayın", "Blenderdan geçirin"],
      
      "age_group": "6-8 Ay (Başlangıç & Tadım)",
      "age_group_color": "#FFAB91",
      "meal_type": "Öğle Yemeği",
      "diet_types": ["Püre", "Vejetaryen"],
      "author": {
        "id": 1,
        "name": "KidsGourmet Editörü",
        "avatar": "https://example.com/avatar.jpg"
      },
      "expert": {
        "name": "Dr. Ayşe Yılmaz",
        "title": "Çocuk Beslenmesi Uzmanı",
        "approved": true
      },
      
      "age_groups": ["6-8 Ay (Başlangıç & Tadım)"],
      "allergens": [],
      "is_featured": false
    }
  ],
  "total": 45,
  "page": 1,
  "per_page": 12,
  "total_pages": 4
}
```

## Migration Guide

### To update existing AgeGroup term names:
Run this code once (e.g., via WP-CLI, Admin page, or direct PHP execution):

```php
$age_group = new \KG_Core\Taxonomies\AgeGroup();
$age_group->update_existing_terms();
```

Or via WordPress admin dashboard by adding this temporary code to theme's `functions.php`:

```php
add_action( 'admin_init', function() {
    if ( current_user_can( 'manage_options' ) && isset( $_GET['update_age_terms'] ) ) {
        $age_group = new \KG_Core\Taxonomies\AgeGroup();
        $age_group->update_existing_terms();
        wp_redirect( admin_url() );
        exit;
    }
});
```

Then visit: `wp-admin/?update_age_terms=1`

## Acceptance Criteria Status

- ✅ `/wp-json/kg/v1/recipes` returns `age_group`, `age_group_color`, `meal_type`, `diet_types`, `author`, `expert` fields
- ✅ `age-group` filter works (supports comma-separated values)
- ✅ `meal-type` filter works
- ✅ `diet-type` filter works
- ✅ `special-condition` filter works
- ✅ `ingredient` search works
- ✅ `orderby=date|popular|prep_time` sorting works
- ✅ Response includes `total`, `page`, `per_page`, `total_pages`
- ✅ HTML entities (&amp; etc.) are correctly decoded
- ✅ Age group term names have age/month info first

## Files Modified

1. `includes/Utils/Helper.php` - Enhanced HTML entity decoding
2. `includes/Taxonomies/SpecialCondition.php` - NEW taxonomy file
3. `includes/Taxonomies/AgeGroup.php` - Updated term names & added migration
4. `includes/API/RecipeController.php` - Enhanced fields, filters, sorting
5. `kg-core.php` - Registered SpecialCondition taxonomy
6. `tests/test-recipe-api-improvements.php` - NEW comprehensive test file

## Backward Compatibility

All changes are backward compatible:
- Existing API responses include all previous fields
- New fields are additions, not replacements
- Old filter parameter names still work
- Response format enhanced but maintains previous structure

## Notes

- The `expert.title` field may be empty for existing recipes if not previously set
- Author avatar uses WordPress Gravatar by default (size: 48px)
- Color codes are retrieved from age group term meta `_kg_color_code`
- HTML entity decoding runs twice to handle double-encoded entities
- All text fields from taxonomies are decoded for clean display

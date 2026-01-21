# Expert User Integration Feature

## Overview
This feature integrates recipe expert approvals with registered WordPress users. It allows selecting registered experts from a dropdown instead of manually entering their names, and enriches the API response with expert profile information including avatar, slug, and user ID.

## Changes Made

### 1. RecipeMetaBox.php (Admin UI)
- **New Field**: Added `_kg_expert_user_id` meta field to store the WordPress user ID of the expert
- **Dropdown Selector**: Added a dropdown to select from users with the `kg_expert` role
- **Auto-fill JavaScript**: When an expert is selected, their display name automatically fills the "Uzman Adı" field
- **Backward Compatibility**: Manual text entry is still supported for external/unregistered experts

### 2. RecipeController.php (API)
Enhanced expert data in API responses with three new fields:

**New Fields in Expert Object:**
- `slug`: User's nicename (URL-friendly username) - useful for linking to expert profiles
- `image`: Expert's avatar URL using `Helper::get_user_avatar_url()` which prioritizes custom avatars
- `user_id`: WordPress user ID (null for manual/unregistered experts)

**API Response Examples:**

Registered Expert:
```json
{
  "expert": {
    "name": "Doç. Dr. Enver Mahir Gülcan",
    "title": "Doç.Dr.",
    "note": "Bu tarif 6 aydan sonra verilebilir...",
    "image": "https://kidsgourmet.com/wp-content/uploads/avatars/13/avatar.jpg",
    "slug": "dr-enver-mahir-gulcan",
    "user_id": 13,
    "approved": true
  }
}
```

Manual/Unregistered Expert:
```json
{
  "expert": {
    "name": "Dış Uzman Adı",
    "title": "Dr.",
    "note": "Uzman notu...",
    "image": "",
    "slug": "",
    "user_id": null,
    "approved": true
  }
}
```

### 3. ExpertMigrator.php (Migration Tool)
A new migration tool to map existing expert names to registered WordPress users.

**Features:**
- **Preview Mode**: Shows which recipes will be matched to which users before applying changes
- **Pattern Matching**: Uses three methods to find matches:
  1. Known expert mappings (configurable array)
  2. Exact display name matching
  3. Fuzzy matching with 70% similarity threshold
- **Safe Execution**: Only updates recipes that don't already have a user ID assigned
- **Admin UI**: Accessible via WordPress admin menu under "Uzman Migration"

**Known Expert Mappings:**
```php
private $knownExperts = [
    'Enver Mahir' => 13,        // Doç. Dr. Enver Mahir Gülcan
    'Gülcan' => 13,
    'Çiğdem Ünver' => 10,       // Fzt. Çiğdem Ünver
    'Cigdem Unver' => 10,
    'Deniz Özkılıç' => 14,      // Dr. Psikolog Deniz Özkılıç Kabul
    // ... more mappings
];
```

### 4. Plugin Initialization
- Added `ExpertMigrator.php` to the plugin's required files
- Initialized the migration class for admin users

## Database Schema

### New Post Meta Field
- **Key**: `_kg_expert_user_id`
- **Type**: Integer (WordPress user ID)
- **Purpose**: Links recipe to registered expert user
- **Optional**: Can be empty for manual/unregistered experts

## Usage Guide

### For Content Editors
1. Edit a recipe in WordPress admin
2. Scroll to "Uzman Onayı" section
3. Use the dropdown to select a registered expert OR manually enter expert information
4. Save the recipe
5. API will automatically include expert's profile photo and slug if registered

### For Administrators
1. Go to WordPress Admin → Uzman Migration
2. Click "Eşleşmeleri Önizle" to preview which recipes will be matched
3. Review the matches (green = matched, red = no match found)
4. Click "Migration Çalıştır" to apply the changes
5. Verify the results

## Testing

Run the test file to verify the implementation:
```bash
php tests/test-expert-user-integration.php
```

All tests should pass, confirming:
- RecipeMetaBox has the expert user dropdown
- RecipeController includes slug, image, and user_id in API
- ExpertMigrator has all required methods
- Plugin is properly initialized
- All PHP files have valid syntax

## Backward Compatibility

✅ **Fully Backward Compatible**
- Existing recipes with manual expert names continue to work
- API fields `name`, `title`, `note`, and `approved` remain unchanged
- New fields (`slug`, `image`, `user_id`) are added but don't break existing consumers
- Manual expert entry is still supported

## Security Considerations

- User ID is validated and sanitized as integer
- Only users with `kg_expert` role appear in dropdown
- Admin-only access to migration tool
- No direct database queries - uses WordPress meta API

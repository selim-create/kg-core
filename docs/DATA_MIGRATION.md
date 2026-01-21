# Data Migration System - Phase 3

## Overview

This migration system transfers data from WordPress's `wp_postmeta` table to custom optimized tables:
- `kg_recipe_meta` - Recipe metadata
- `kg_ingredient_meta` - Ingredient metadata
- `kg_post_meta` - Post metadata

## Features

### 1. **DataMigration Class** (`includes/Database/DataMigration.php`)

Main migration engine with following capabilities:

#### Methods:
- `migrateAll()` - Migrates all post types (recipes, ingredients, posts)
- `migrateRecipes($batch_size = 50)` - Migrates recipe metadata
- `migrateIngredients($batch_size = 50)` - Migrates ingredient metadata
- `migratePosts($batch_size = 50)` - Migrates post metadata
- `verify($type)` - Verifies migration completeness
- `rollback($type)` - Rolls back migration (truncates table)

#### Key Features:
- **Batch Processing**: Processes 50 records at a time to prevent timeouts
- **Skip Existing**: Automatically skips already migrated records
- **Serialized Data**: Handles WordPress serialized data with `maybe_unserialize()`
- **Type Conversion**: Converts values to appropriate types (boolean, int, float, json)
- **Value Mapping**: Maps Turkish values to English (e.g., "Kolay" → "kolay")

#### Meta Key Mappings:

**Recipe Fields:**
- `_kg_prep_time` → `prep_time`
- `_kg_cook_time` → `cook_time`
- `_kg_serving_size` → `serving_size`
- `_kg_difficulty` → `difficulty`
- `_kg_freezable` → `freezable`
- `_kg_storage_info` → `storage_info`
- `_kg_is_featured` → `is_featured`
- `_kg_video_url` → `video_url`
- `_kg_special_notes` → `special_notes`
- `_kg_calories` → `calories`
- `_kg_protein` → `protein`
- `_kg_carbs` → `carbs`
- `_kg_fat` → `fat`
- `_kg_fiber` → `fiber`
- `_kg_sugar` → `sugar`
- `_kg_sodium` → `sodium`
- `_kg_vitamins` → `vitamins`
- `_kg_minerals` → `minerals`
- `_kg_expert_user_id` → `expert_user_id`
- `_kg_expert_name` → `expert_name`
- `_kg_expert_title` → `expert_title`
- `_kg_expert_note` → `expert_note`
- `_kg_expert_approved` → `expert_approved`
- `_kg_ingredients` → `ingredients`
- `_kg_instructions` → `instructions`
- `_kg_substitutes` → `substitutes`
- `_kg_cross_sell` → `cross_sell`
- `_kg_rating` → `rating`
- `_kg_rating_count` → `rating_count`
- `_kg_base_rating` → `base_rating`
- `_kg_base_rating_count` → `base_rating_count`
- `_kg_ratings` → `ratings_data`

**Ingredient Fields:**
- `_kg_start_age` → `start_age`
- `_kg_allergy_risk` → `allergy_risk`
- `_kg_is_featured` → `is_featured`
- `_kg_season` → `season`
- `_kg_ing_calories_100g` → `calories_100g`
- `_kg_ing_protein_100g` → `protein_100g`
- `_kg_ing_carbs_100g` → `carbs_100g`
- `_kg_ing_fat_100g` → `fat_100g`
- `_kg_ing_fiber_100g` → `fiber_100g`
- `_kg_ing_sugar_100g` → `sugar_100g`
- `_kg_ing_vitamins` → `vitamins`
- `_kg_ing_minerals` → `minerals`
- `_kg_cross_contamination` → `cross_contamination`
- `_kg_allergy_symptoms` → `allergy_symptoms`
- `_kg_alternatives` → `alternatives`
- `_kg_benefits` → `benefits`
- `_kg_storage_tips` → `storage_tips`
- `_kg_preparation_tips` → `preparation_tips`
- `_kg_selection_tips` → `selection_tips`
- `_kg_pro_tips` → `pro_tips`
- `_kg_prep_methods` → `prep_methods`
- `_kg_prep_by_age` → `prep_by_age`
- `_kg_pairings` → `pairings`
- `_kg_faq` → `faq`
- Expert fields...

**Post Fields:**
- `_kg_is_featured` → `is_featured`
- `_kg_is_sponsored` → `is_sponsored`
- `_kg_sponsor_name` → `sponsor_name`
- `_kg_sponsor_url` → `sponsor_url`
- `_kg_sponsor_logo` → `sponsor_logo_id`
- `_kg_sponsor_light_logo` → `sponsor_light_logo_id`
- `_kg_direct_redirect` → `direct_redirect`
- `_kg_gam_impression_url` → `gam_impression_url`
- `_kg_gam_click_url` → `gam_click_url`
- `_kg_has_discount` → `has_discount`
- `_kg_discount_text` → `discount_text`
- Expert fields...

#### Value Mappings:
- **Difficulty**: `Kolay` → `kolay`, `Orta` → `orta`, `Zor` → `zor`
- **Allergy Risk**: `Düşük` → `low`, `Orta` → `medium`, `Yüksek` → `high`

### 2. **DataMigrationPage** (`includes/Admin/DataMigrationPage.php`)

Admin interface for managing migrations.

#### Location:
WordPress Admin → KG Core → Data Migration

#### Features:
- **Table Status Dashboard**: Shows current record counts and completion percentage
- **Migration Buttons**:
  - Migrate Recipes
  - Migrate Ingredients
  - Migrate Posts
  - Migrate All (all types at once)
- **Verification Tool**: Checks for missing migrations
- **Rollback Tool**: Truncates tables (with confirmation)
- **AJAX Operations**: All operations run via AJAX for better UX
- **Real-time Results**: Shows migrated count, skipped count, and errors
- **Auto-refresh**: Table status updates after operations

## Usage

### 1. Access Admin Page

Navigate to: **WordPress Admin → KG Core → Data Migration**

### 2. Check Table Status

The page displays:
- Current number of records in each custom table
- Total number of posts for each type
- Completion percentage

### 3. Run Migration

Click one of the migration buttons:
- **Tarifleri Migrate Et** - Migrates only recipes
- **Malzemeleri Migrate Et** - Migrates only ingredients
- **Postları Migrate Et** - Migrates only posts
- **Tümünü Migrate Et** - Migrates all types

The system will:
1. Process records in batches of 50
2. Skip already migrated records
3. Show results (migrated, skipped, errors)
4. Update table status

### 4. Verify Migration

1. Select verification type (All, Recipes, Ingredients, or Posts)
2. Click **Doğrula** button
3. View missing record IDs if any

### 5. Rollback (if needed)

⚠️ **Warning**: This permanently deletes data!

1. Select table to rollback
2. Click **Geri Al** button
3. Confirm the action
4. Table will be truncated

## Data Preservation

**Important**: This migration **does not delete** data from `wp_postmeta`. The original data is preserved as a backup.

## Error Handling

The system:
- Catches exceptions for each record
- Continues processing even if one record fails
- Reports all errors in the results
- Allows re-running migration to retry failed records

## Performance

- Batch size: 50 records per batch
- Timeout: 300 seconds for single migrations, 600 for all
- Memory: Uses WordPress's efficient querying
- Database: Uses prepared statements for security

## Technical Details

### Models Used:
- `RecipeMeta` - Recipe metadata model
- `IngredientMeta` - Ingredient metadata model
- `PostMeta` - Post metadata model

All models extend `BaseModel` and provide:
- Type casting (json, boolean, int, float)
- Automatic serialization
- Cache invalidation
- Prepared statement security

### Database Tables:
Created by `Schema::createTables()`:
- `wp_kg_recipe_meta` - Recipe metadata
- `wp_kg_ingredient_meta` - Ingredient metadata
- `wp_kg_post_meta` - Post metadata

## Files

- `includes/Database/DataMigration.php` - Migration engine
- `includes/Admin/DataMigrationPage.php` - Admin UI
- `assets/admin/js/data-migration.js` - Frontend JavaScript
- `assets/admin/css/data-migration.css` - Styling

## Integration

Added to `kg-core.php`:
```php
// Database class
if ( file_exists( KG_CORE_PATH . 'includes/Database/DataMigration.php' ) ) {
    require_once KG_CORE_PATH . 'includes/Database/DataMigration.php';
}

// Admin page
if ( is_admin() && file_exists( KG_CORE_PATH . 'includes/Admin/DataMigrationPage.php' ) ) {
    require_once KG_CORE_PATH . 'includes/Admin/DataMigrationPage.php';
    new \KG_Core\Admin\DataMigrationPage();
}
```

## Testing

1. Navigate to Data Migration page
2. Check table status shows 0 records initially
3. Run migration for one type first (test with recipes)
4. Verify results show correct counts
5. Check table status updates
6. Run verification to confirm no missing records
7. Test rollback on a small dataset first

## Support

For issues or questions about the migration system, please refer to the plugin documentation or contact the development team.

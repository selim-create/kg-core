# Dual-Write System Implementation Summary

## Overview
This PR implements a dual-write system that synchronizes data from WordPress postmeta to custom database tables during the migration period. This allows for a gradual, safe migration without data loss or downtime.

## New Components

### 1. Feature Flags System (`includes/Config/FeatureFlags.php`)

A flexible feature flag system that controls the migration rollout:

**Available Flags:**
- `dual_write` (default: `true`) - Enables writing to both wp_postmeta and custom tables
- `read_from_custom_table` (default: `true`) - Controls reading from custom tables
- `custom_table_only` (default: `false`) - Only use custom tables (final migration phase)

**Usage Examples:**
```php
// Check if dual-write is enabled
if (\KG_Core\Config\FeatureFlags::useDualWrite()) {
    // Sync to custom table
}

// Get all flags
$flags = \KG_Core\Config\FeatureFlags::getAll();

// Enable/disable specific flag
\KG_Core\Config\FeatureFlags::enable('dual_write');
\KG_Core\Config\FeatureFlags::disable('dual_write');
```

### 2. Meta Sync Service (`includes/Services/MetaSyncService.php`)

Handles the dual-write synchronization logic:

**Public Methods:**
- `syncRecipe($post_id, $data = [])` - Sync recipe to kg_recipe_meta
- `syncIngredient($post_id, $data = [])` - Sync ingredient to kg_ingredient_meta
- `syncPost($post_id, $data = [])` - Sync post to kg_post_meta
- `extractRecipeFromPostMeta($post_id)` - Extract recipe data from wp_postmeta
- `extractIngredientFromPostMeta($post_id)` - Extract ingredient data
- `extractPostFromPostMeta($post_id)` - Extract post data

**How It Works:**
1. Extracts data from wp_postmeta using the same mappings as DataMigration.php
2. Applies value transformations (e.g., "Kolay" → "kolay", "Düşük" → "low")
3. Calls the appropriate Model's save() method to store in custom table

## Integration Points

### MetaBox Save Hooks

Three MetaBox classes were updated to call the sync service:

**1. RecipeMetaBox.php** - At end of `save_custom_meta_data()`:
```php
// === DUAL-WRITE: Sync to custom table ===
if ( class_exists( '\KG_Core\Config\FeatureFlags' ) && \KG_Core\Config\FeatureFlags::useDualWrite() ) {
    \KG_Core\Services\MetaSyncService::syncRecipe( $post_id );
}
```

**2. IngredientMetaBox.php** - At end of `save_custom_meta_data()`:
```php
// === DUAL-WRITE: Sync to custom table ===
if ( class_exists( '\KG_Core\Config\FeatureFlags' ) && \KG_Core\Config\FeatureFlags::useDualWrite() ) {
    \KG_Core\Services\MetaSyncService::syncIngredient( $post_id );
}
```

**3. PostMetaBox.php** - At end of `save_sponsor_meta_data()`:
```php
// === DUAL-WRITE: Sync to custom table ===
if ( class_exists( '\KG_Core\Config\FeatureFlags' ) && \KG_Core\Config\FeatureFlags::useDualWrite() ) {
    \KG_Core\Services\MetaSyncService::syncPost( $post_id );
}
```

### Core Plugin File

Updated `kg-core.php` to include the new classes:

```php
// 1.6. CONFIG CLASSES (Feature Flags)
if ( file_exists( KG_CORE_PATH . 'includes/Config/FeatureFlags.php' ) ) {
    require_once KG_CORE_PATH . 'includes/Config/FeatureFlags.php';
}

// 2.7. SERVICE CLASSES (includes MetaSyncService)
if ( file_exists( KG_CORE_PATH . 'includes/Services/MetaSyncService.php' ) ) 
    require_once KG_CORE_PATH . 'includes/Services/MetaSyncService.php';
```

## Migration Strategy

### Phase 1: Dual-Write (Current Phase)
- `dual_write`: **true** ✅
- `read_from_custom_table`: **true** ✅
- `custom_table_only`: **false**

**Behavior:**
- All saves write to BOTH wp_postmeta AND custom tables
- Reads come from custom tables (with fallback to wp_postmeta)
- Both systems stay in sync

### Phase 2: Monitoring
- Keep dual-write enabled
- Monitor custom table performance
- Verify data consistency
- Fix any sync issues

### Phase 3: Custom Table Only (Future)
- `dual_write`: **false**
- `read_from_custom_table`: **true** ✅
- `custom_table_only`: **true** ✅

**Behavior:**
- Only writes to custom tables
- Only reads from custom tables
- wp_postmeta no longer used for KG meta

## Data Mappings

The service uses the same meta key mappings as `DataMigration.php`:

**Recipe Mappings (Sample):**
- `_kg_prep_time` → `prep_time`
- `_kg_cook_time` → `cook_time`
- `_kg_difficulty` → `difficulty`
- `_kg_ingredients` → `ingredients`
- etc.

**Ingredient Mappings (Sample):**
- `_kg_start_age` → `start_age`
- `_kg_allergy_risk` → `allergy_risk` (with value transformation)
- `_kg_season` → `season`
- etc.

**Post Mappings (Sample):**
- `_kg_is_featured` → `is_featured`
- `_kg_is_sponsored` → `is_sponsored`
- `_kg_sponsor_name` → `sponsor_name`
- etc.

## Error Handling

The sync service includes robust error handling:

```php
try {
    // Extract and sync data
    return RecipeMeta::save( $post_id, $data );
} catch ( \Exception $e ) {
    error_log( 'KG Core MetaSyncService: Failed to sync recipe ' . $post_id . ': ' . $e->getMessage() );
    return false;
}
```

Errors are logged but don't prevent the save to wp_postmeta from completing.

## Testing

All components have been tested and verified:

✅ FeatureFlags class loads and all methods work  
✅ MetaSyncService class loads with all required methods  
✅ All MetaBox files integrated with dual-write calls  
✅ kg-core.php includes new classes  
✅ PHP syntax checks pass  

## Next Steps

1. **Deploy to staging** - Test with real WordPress environment
2. **Monitor sync** - Check logs for any sync errors
3. **Verify data** - Compare wp_postmeta vs custom table data
4. **Performance testing** - Ensure no slowdown in admin saves
5. **Migration completion** - Run DataMigration to backfill existing posts
6. **Phase transition** - Eventually move to custom_table_only mode

## Rollback Plan

If issues arise, the feature can be disabled instantly:

```php
\KG_Core\Config\FeatureFlags::disable('dual_write');
```

This immediately stops syncing to custom tables without code changes.

## Files Changed

**New Files:**
- `includes/Config/FeatureFlags.php` (127 lines)
- `includes/Services/MetaSyncService.php` (304 lines)

**Modified Files:**
- `includes/Admin/RecipeMetaBox.php` (+5 lines)
- `includes/Admin/IngredientMetaBox.php` (+5 lines)
- `includes/Admin/PostMetaBox.php` (+5 lines)
- `kg-core.php` (+6 lines)

**Total:** 2 new files, 4 modified files, ~452 lines added

# Duplicate Embed Content Fix - Implementation Summary

## Problem
When embedding 3 ingredients using Gutenberg blocks, the frontend displayed 6 cards (each embed appearing twice) instead of the expected 3 cards.

## Root Cause
The issue occurred because:

1. **Gutenberg Block Output**: The `blocks/kg-embed/save.js` file outputs:
   ```html
   <div class="kg-embed-placeholder" data-type="ingredient" data-ids="1,2,3">
     [kg-embed type="ingredient" ids="1,2,3"]
   </div>
   ```

2. **Double Processing**: The `extract_embeds_from_content()` method in `includes/Shortcodes/ContentEmbed.php` was parsing BOTH:
   - The `[kg-embed]` shortcode
   - The `<div class="kg-embed-placeholder">` wrapper

3. **Result**: Same content extracted twice, leading to duplicate display.

## Solution Implemented

### Changes to `includes/Shortcodes/ContentEmbed.php`

Modified the `extract_embeds_from_content()` method with:

1. **Duplicate Tracking**
   - Added `$processed_embeds` array to track processed embeds
   - Created unique keys: `type-id1-id2-id3` (IDs sorted for consistency)
   - Skip any embed already processed

2. **Parse Priority**
   - Parse shortcodes FIRST
   - Parse block placeholders ONLY if no shortcodes found (backwards compatibility)

3. **Counter Scope**
   - Changed `static $embed_counter` to local `$embed_counter`
   - Prevents counter pollution across different requests

### Key Code Changes

**Before:**
```php
public function extract_embeds_from_content($content) {
    $embeds = [];
    static $embed_counter = 0;
    
    // Parse shortcodes...
    // Parse block placeholders... (ALWAYS ran - causing duplicates)
}
```

**After:**
```php
public function extract_embeds_from_content($content) {
    $embeds = [];
    $processed_embeds = []; // Track duplicates
    $embed_counter = 0;     // Local, not static
    
    // Method 1: Parse shortcodes
    if (preg_match_all(...)) {
        foreach ($matches as $match) {
            // ... extract data ...
            
            // Create unique key
            sort($ids);
            $embed_key = $type . '-' . implode('-', $ids);
            
            // Skip if already processed
            if (isset($processed_embeds[$embed_key])) {
                continue;
            }
            $processed_embeds[$embed_key] = true;
            
            // ... add to embeds ...
        }
    }
    
    // Method 2: Parse block placeholders
    // ONLY if no shortcodes found
    if (empty($embeds) && preg_match_all(...)) {
        // Same duplicate checking logic
    }
}
```

## Testing

### Created Test Files

1. **`tests/test-duplicate-embed-fix.php`**
   - WordPress integration test
   - Creates test posts and verifies extraction
   - Requires WP-CLI environment

2. **`tests/test-duplicate-embed-logic.php`**
   - Standalone unit test
   - Tests regex patterns and duplicate detection logic
   - No WordPress required

3. **`tests/verify-duplicate-embed-fix.php`**
   - Demonstration script
   - Shows before/after behavior
   - Verifies backwards compatibility

### Test Results

✅ **All tests passing:**
- Unique key generation works correctly
- Shortcode regex matches all variations
- Block placeholder regex works
- Duplicate detection prevents double extraction
- Backwards compatibility maintained

### Verification Output

```
OLD BEHAVIOR (BUGGY):
Found 2 embed(s):
  1. Type: ingredient, IDs: 101,102,103, Source: shortcode
  2. Type: ingredient, IDs: 101,102,103, Source: placeholder
❌ Frontend would show 6 cards instead of 3

NEW BEHAVIOR (FIXED):
Found 1 embed(s):
  1. Type: ingredient, IDs: 101,102,103, Source: shortcode
✅ Frontend will correctly show 3 cards
```

## Benefits

1. **Fixes the bug**: No more duplicate embeds
2. **Maintains backwards compatibility**: Old content with only placeholders still works
3. **Handles edge cases**: User duplicates are also deduplicated
4. **Better performance**: Local counter instead of static
5. **Clean code**: Clear comments and logical structure

## Files Modified

- `includes/Shortcodes/ContentEmbed.php` - Core fix implementation

## Files Added

- `tests/test-duplicate-embed-fix.php` - WordPress integration test
- `tests/test-duplicate-embed-logic.php` - Standalone unit test
- `tests/verify-duplicate-embed-fix.php` - Verification script

## Impact

- **Before**: 3 ingredients = 6 cards displayed (duplicate)
- **After**: 3 ingredients = 3 cards displayed (correct)

## Backwards Compatibility

✅ Fully maintained - existing content with only block placeholders (no shortcodes) continues to work correctly.

# Migration System Bug Fixes - Documentation

## Overview
This document details the comprehensive bug fixes applied to the migration system for parsing blog post content into recipe posts.

## Issues Fixed

### 1. Ingredient/Instruction Separation (CRITICAL)
**Problem:** Instructions were being parsed as ingredients because the section boundary detection was incorrect.

**Root Cause:** The `extractIngredients()` method was not properly finding where the "Hazırlanışı" section begins, causing it to extract bullet points from both sections.

**Solution:**
- Rewrote `findSectionStart()` method to accurately locate section boundaries
- Modified logic to find the START of the Hazırlanış heading (not the end)
- For Malzemeler section, returns position AFTER the heading tag
- For Hazırlanış section, returns position AT the heading tag start

**Code Changes:** `includes/Migration/ContentParser.php`

### 2. Unit Detection Issues
**Problem:** 
- Units had trailing spaces (e.g., "çiçek " instead of "çiçek")
- "bardak" wasn't recognized before "adet" (default unit)
- "tatlı kaşığı" and other compound units not properly detected

**Root Cause:** Units weren't sorted by length, so shorter units matched first. Also, trimming wasn't applied after extraction.

**Solution:**
- Sort units by length (longest first) before matching
- Expanded units list to include all common Turkish cooking units
- Removed unit pattern from extracted string properly
- Set default unit to 'adet' only when quantity exists but no unit found

**Code Changes:** `includes/Migration/IngredientParser.php`

### 3. Parenthesis Notes Not Extracted
**Problem:** Text in parentheses (like ingredient alternatives or age restrictions) was not extracted as preparation notes.

**Example:** 
- Input: `File badem (yetişkinler ve büyük yaş grubu çocuklar için)`
- Expected: name="File badem", note="yetişkinler ve büyük yaş grubu çocuklar için"

**Solution:**
- Extract parenthesis content before parsing other components
- Remove parenthesis and content from main string
- Add parenthesis content to preparation_note field

**Code Changes:** `includes/Migration/IngredientParser.php`

### 4. Expert Name/Title Parsing Incomplete
**Problem:** Expert title (e.g., "Doç.Dr.") and full name weren't extracted properly.

**Example:**
- Input: `Doç.Dr. Enver Mahir Gülcan'ın notu`
- Was getting: name="Mahir Gülcan", title=""
- Expected: name="Enver Mahir Gülcan", title="Doç.Dr."

**Root Cause:** Regex pattern wasn't capturing title in a separate group, and wasn't accounting for multi-word names with titles.

**Solution:**
- Improved regex patterns to capture title in separate group
- Added support for various Turkish academic/professional titles
- Enhanced name extraction to get full name after title

**Code Changes:** `includes/Migration/ContentParser.php`

### 5. Special Notes Not Saved
**Problem:** Special notes like "Süt:", "Not:", "İpucu:" were extracted but not saved to database.

**Solution:**
- Added `_kg_special_notes` meta field to recipe posts
- Updated `RecipeMigrator` to save special notes
- Added field to `RecipeMetaBox` for display and editing
- Added save handler in metabox

**Code Changes:** 
- `includes/Migration/RecipeMigrator.php`
- `includes/Admin/RecipeMetaBox.php`

## Technical Details

### ContentParser.php Changes

#### findSectionStart() Method
```php
private function findSectionStart($content, $sectionName) {
    // Patterns for different section formats
    $patterns = [
        '/<h[1-6][^>]*>' . $sectionName . '[ıi]?ş?[ıi]?/iu',
        '/<strong>' . $sectionName . '[ıi]?ş?[ıi]?/iu',
        '/\n' . $sectionName . '[ıi]?ş?[ıi]?\s*\n/iu',
    ];
    
    // For Malzemeler: return position AFTER tag
    // For Hazırlanış: return position AT tag start
    // This ensures proper section boundary
}
```

#### extractExpertNote() Method
```php
// Multiple patterns for different title formats
$patterns = [
    // With title (Doç.Dr., Prof.Dr., etc.)
    '/((Doç\.?\s*Dr\.?|Prof\.?\s*Dr\.?|Dr\.?|Dyt\.?|Uzm\.?)\s+([A-ZÇĞİÖŞÜa-zçğıöşü\s\.]+?))[\'\'`´]+\s*(nın|nin|nun|nün|ın|in|un|ün)\s+[Nn]otu:?\s*/u',
    // Without title
    '/([A-ZÇĞİÖŞÜ][a-zçğıöşü]+\s+[A-ZÇĞİÖŞÜ][a-zçğıöşü]+(?:\s+[A-ZÇĞİÖŞÜ][a-zçğıöşü]+)?)[\'\'`´]+\s*(nın|nin|nun|nün|ın|in|un|ün)\s+[Nn]otu:?\s*/u',
];
```

### IngredientParser.php Changes

#### Parse Method Flow
1. Extract parenthesis content → save as note
2. Extract quantity (numbers, fractions, ranges)
3. Extract unit (sorted by length, longest first)
4. Extract preparation terms from remaining text
5. Split by comma to separate alternatives → add to note
6. Clean and capitalize name
7. Combine all notes

#### Units List Expanded
Added comprehensive list of Turkish cooking units:
- Measurements: çiçek, dal, yaprak, dilim, diş, demet
- Spoons: çay kaşığı, tatlı kaşığı, yemek kaşığı
- Cups: çay bardağı, su bardağı, bardak, fincan
- Portions: avuç, tutam, ölçek, porsiyon
- Weight/Volume: gram, kg, ml, litre

### RecipeMigrator.php Changes

```php
// Enhanced expert approved logic
$hasExpertNote = !empty($parsedData['expert_note']) && !empty($parsedData['expert_name']);
update_post_meta($recipeId, '_kg_expert_approved', $hasExpertNote ? '1' : '0');

// Save special notes
if (!empty($parsedData['special_notes'])) {
    update_post_meta($recipeId, '_kg_special_notes', $parsedData['special_notes']);
}
```

### RecipeMetaBox.php Changes

```php
// Added field retrieval
$special_notes = get_post_meta( $post->ID, '_kg_special_notes', true );

// Added render field
<h3>Özel Notlar</h3>
<p>
    <label for="kg_special_notes"><strong>Özel Notlar (Süt, Not, İpucu vb.):</strong></label><br>
    <textarea id="kg_special_notes" name="kg_special_notes" rows="4" style="width:100%;">
        <?php echo esc_textarea( $special_notes ); ?>
    </textarea>
    <small>Süt bilgisi, uyarılar ve ek ipuçları (Süt:, Not:, İpucu: vb.)</small>
</p>

// Added save handler
if ( isset( $_POST['kg_special_notes'] ) ) {
    update_post_meta( $post_id, '_kg_special_notes', sanitize_textarea_field( $_POST['kg_special_notes'] ) );
}
```

## Testing

### Test Files
1. `test-migration.php` - Original test suite
2. `test-bug-fix.php` - Specific bug scenario test
3. `test-comprehensive.php` - Complete demonstration with expected output

### Test Results
All tests passing ✅

```
✅ Ingredients separated from instructions
✅ Instructions not in ingredients list
✅ Expert name extracted correctly
✅ Expert title extracted correctly
✅ Expert note extracted
✅ Special notes extracted
✅ Parenthesis notes extracted
✅ Unit "bardak" recognized
✅ Unit "tatlı kaşığı" recognized
✅ Comma alternatives in notes
```

## Example Output

### Input
```
Malzemeler
* 3 çiçek brokoli
* 1/4 adet küçük kuru soğan,
* 2-3 bardak su
* 1-2 ölçek formül mama, 1 çay bardağı devam sütü veya inek sütü..(Tarifteki inek sütü 1 yaş üzeri içindir.)
* 1 tatlı kaşığı sızma zeytinyağı
* File badem (yetişkinler ve büyük yaş grubu çocuklar için)

Hazırlanışı
* Soğan tencerede zeytinyağında sote edilir.
* Ardından tencereye brokoli ve su ilave edilerek bir taşım kaynatılır.

Doç.Dr. Enver Mahir Gülcan'ın notu
Brokoli içerisinde izotiyosiyonat isimli fitokimyasallar bulunmaktadır...

Süt: Çocuğunuzun inek sütü alerjisi yoksa 9-10 ay üstü bebeğinize devam sütü ile de yapabilirsiniz...
```

### Output
```json
{
  "ingredients": [
    {"quantity": "3", "unit": "çiçek", "name": "Brokoli"},
    {"quantity": "1/4", "unit": "adet", "name": "Kuru soğan", "preparation_note": "küçük"},
    {"quantity": "2-3", "unit": "bardak", "name": "Su"},
    {"quantity": "1-2", "unit": "ölçek", "name": "Formül mama", 
     "preparation_note": "Tarifteki inek sütü 1 yaş üzeri içindir. 1 çay bardağı devam sütü veya inek sütü"},
    {"quantity": "1", "unit": "tatlı kaşığı", "name": "Sızma zeytinyağı"},
    {"quantity": "", "unit": "", "name": "File badem", 
     "preparation_note": "yetişkinler ve büyük yaş grubu çocuklar için"}
  ],
  "expert_name": "Enver Mahir Gülcan",
  "expert_title": "Doç.Dr.",
  "expert_note": "Brokoli içerisinde izotiyosiyonat isimli fitokimyasallar bulunmaktadır...",
  "expert_approved": "1",
  "special_notes": "Süt: Çocuğunuzun inek sütü alerjisi yoksa 9-10 ay üstü bebeğinize devam sütü ile de yapabilirsiniz..."
}
```

## Impact
- ✅ No more instructions appearing as ingredients
- ✅ All units properly recognized
- ✅ Parenthesis notes preserved
- ✅ Expert information complete
- ✅ Special notes saved and editable
- ✅ All existing tests still passing

## Backward Compatibility
All changes are backward compatible. Existing recipes will continue to work, and the new fields are optional.

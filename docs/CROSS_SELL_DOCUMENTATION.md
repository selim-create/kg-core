# Cross-Sell Feature Documentation

## Overview

The Cross-Sell feature provides a hybrid system (manual + automatic) for the "Bizimkiler Ne Yiyecek?" widget, integrating with Tariften.com API to suggest related recipes.

## Features

### Hybrid Mode System

Editors can choose between two modes:

#### 1. Manual Mode
- Manually enter Tariften.com URL
- Custom title input
- Optional image URL input
- Full control over the cross-sell content

#### 2. Automatic Mode
- Select main ingredient from recipe ingredients
- Click "Fetch Suggestions" to get recommendations from Tariften.com API
- Choose from suggested recipes
- Automatic data population

## User Interface

### Location
The Cross-Sell metabox appears in the Recipe edit screen in WordPress admin panel.

### Mode Selection
Two radio buttons allow switching between:
- **Manuel Seçim** (Manual Selection)
- **Otomatik Öneri (Malzeme Bazlı)** (Automatic Suggestion - Ingredient Based)

### Manual Mode Fields
1. **Tariften.com Linki** - URL input for Tariften.com recipe
2. **Başlık** - Custom title for the cross-sell
3. **Görsel URL (opsiyonel)** - Optional image URL

### Automatic Mode Fields
1. **Ana Malzeme Seç** - Dropdown populated with recipe ingredients
2. **Öneri Getir** - Button to fetch suggestions from API
3. **Suggestions Container** - Displays fetched suggestions with:
   - Recipe image (80x60px)
   - Recipe title
   - Preparation time and difficulty
   - Select button

## Data Storage

### Meta Key: `_kg_cross_sell`

Data is stored as JSON in post meta:

```json
{
    "mode": "manual",
    "url": "https://tariften.com/tarif/firinda-kabak",
    "title": "Fırında Kabak Sandal",
    "image": "https://...",
    "ingredient": "",
    "tariften_id": ""
}
```

For automatic mode:
```json
{
    "mode": "auto",
    "url": "https://tariften.com/tarif/firinda-kabak",
    "title": "Fırında Kabak Sandal",
    "image": "https://...",
    "ingredient": "Kabak",
    "tariften_id": "1234"
}
```

### Backward Compatibility

Legacy meta keys are also maintained:
- `_kg_cross_sell_url` - Recipe URL
- `_kg_cross_sell_title` - Recipe title

## API Integration

### Service: `TariftenService`

Located at: `includes/Services/TariftenService.php`

#### Methods

**`getRecipesByIngredient($ingredient, $limit = 3)`**
- Fetches recipe suggestions based on ingredient
- Parameters:
  - `$ingredient` (string): Ingredient name
  - `$limit` (int): Maximum number of suggestions (default: 3)
- Returns: Array with `success` boolean and `recipes` or `message`

**`getRecipeBySlug($slug)`**
- Fetches single recipe by slug
- Parameters:
  - `$slug` (string): Recipe slug
- Returns: Recipe data array or null

#### API Endpoint

Base URL: `https://api.tariften.com/wp-json/tariften/v1`

**Get recipes by ingredient:**
```
GET /recipes/by-ingredient?ingredient={ingredient}&limit={limit}
```

**Search recipe by slug:**
```
GET /recipes/search?slug={slug}
```

### AJAX Controller: `CrossSellController`

Located at: `includes/API/CrossSellController.php`

#### AJAX Action

**Action:** `kg_fetch_tariften_suggestions`

**Method:** POST

**Parameters:**
- `nonce` - Security nonce (kg_cross_sell_nonce)
- `ingredient` - Ingredient name

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": "1234",
            "url": "https://tariften.com/tarif/...",
            "title": "Recipe Title",
            "image": "https://...",
            "prep_time": "30 dk",
            "difficulty": "Kolay"
        }
    ]
}
```

## Security Features

### Input Validation
- Mode validated against whitelist (`manual`, `auto`)
- All text fields sanitized with `sanitize_text_field()`
- URLs sanitized with `esc_url_raw()`

### Output Escaping
- HTML attributes escaped with `esc_attr()`
- HTML content escaped with `esc_html()`
- JavaScript data escaped to prevent XSS

### AJAX Security
- Nonce verification with `check_ajax_referer()`
- Permission checking with `current_user_can('edit_posts')`

### API Security
- HTTP status code validation (200-299)
- JSON decode error handling
- Timeout limit (10 seconds)
- Error response handling

## JavaScript

### File: `assets/admin/js/cross-sell.js`

#### Functionality

1. **Mode Switching**
   - Shows/hides appropriate fields based on selected mode
   - Event listener on radio button change

2. **Fetch Suggestions**
   - Validates ingredient selection
   - Makes AJAX request to WordPress
   - Displays loading state
   - Renders suggestions with images
   - Shows error messages on failure

3. **Select Suggestion**
   - Highlights selected item
   - Populates hidden fields with selection data
   - Disables other selection buttons
   - Visual feedback with "Seçildi" (Selected) state

#### Event Handlers

```javascript
// Mode change
$('input[name="kg_cross_sell_mode"]').on('change', ...)

// Fetch suggestions
$('#kg_fetch_suggestions').on('click', ...)

// Select suggestion
$(document).on('click', '.kg-select-suggestion', ...)
```

## CSS Styling

### File: `assets/admin/css/metabox.css`

#### Key Classes

- `.kg-cross-sell-mode` - Mode selector container
- `.kg-cross-sell-manual` - Manual mode fields container
- `.kg-cross-sell-auto` - Automatic mode fields container
- `.kg-suggestions-list` - List of suggestions
- `.kg-suggestion-item` - Individual suggestion card
- `.kg-suggestion-item.selected` - Selected suggestion state
- `.kg-suggestion-image` - Suggestion thumbnail (80x60px)
- `.kg-cross-sell-error` - Error message styling

## Error Handling

### API Errors

1. **Connection Error**
   - Message: WordPress error message from `wp_remote_get()`
   - Fallback: "Bağlantı hatası. Lütfen tekrar deneyin."

2. **HTTP Error**
   - Message: "API hatası: HTTP durum kodu {code}"
   - Occurs when status code is not 200-299

3. **JSON Parse Error**
   - Message: "JSON ayrıştırma hatası"
   - Occurs when response is invalid JSON

4. **No Results**
   - Message: "Öneri bulunamadı"
   - Occurs when API returns empty results

### User Errors

1. **No Ingredient Selected**
   - Message: "⚠️ Lütfen bir malzeme seçin"
   - Styled with error background

2. **AJAX Error**
   - Message: "❌ Bağlantı hatası. Lütfen tekrar deneyin."

## Usage Example

### Manual Mode

1. Select "Manuel Seçim" radio button
2. Enter Tariften.com URL: `https://tariften.com/tarif/firinda-kabak`
3. Enter title: `Fırında Kabak Sandal`
4. Optionally add image URL
5. Save/Update post

### Automatic Mode

1. Ensure recipe has ingredients added
2. Select "Otomatik Öneri" radio button
3. Choose ingredient from dropdown (e.g., "Kabak")
4. Click "🔄 Öneri Getir" button
5. Wait for suggestions to load
6. Click "✓ Seç" on desired suggestion
7. Save/Update post

## Testing

### Manual Testing

#### Test Manual Mode
1. Create/edit a recipe
2. Select Manual mode
3. Fill in URL and title
4. Save post
5. Verify data is saved correctly
6. Reload page and check values persist

#### Test Automatic Mode
1. Create recipe with ingredients
2. Select Automatic mode
3. Choose an ingredient
4. Click "Öneri Getir"
5. Verify suggestions appear (or graceful error)
6. Select a suggestion
7. Save post
8. Verify data is saved correctly

#### Test Mode Switching
1. Fill in Manual mode fields
2. Switch to Automatic mode
3. Switch back to Manual mode
4. Verify fields are still visible

### API Testing

Test with different scenarios:
- Valid ingredient with results
- Valid ingredient with no results
- Invalid/empty ingredient
- API unavailable (network error)
- API returns non-200 status
- API returns invalid JSON

## Troubleshooting

### Suggestions Not Loading

**Problem:** Click "Öneri Getir" but nothing happens

**Solutions:**
1. Check browser console for JavaScript errors
2. Verify nonce is being generated (check localized script)
3. Check network tab for AJAX request
4. Verify Tariften.com API is accessible

### Data Not Saving

**Problem:** Cross-sell data doesn't persist after save

**Solutions:**
1. Check post type is 'recipe'
2. Verify nonce validation passes
3. Check user has 'edit_posts' capability
4. Look for PHP errors in WordPress debug log

### Ingredient Dropdown Empty

**Problem:** No ingredients in dropdown

**Solution:** Ensure recipe has ingredients added in the Ingredients section first

## Future Enhancements

Potential improvements:
- Cache API responses to reduce API calls
- Add preview of selected cross-sell
- Support for multiple cross-sell suggestions
- Analytics tracking for cross-sell clicks
- Admin settings for API URL configuration
- Support for custom API credentials

## Developer Notes

### Hooks Available

None currently. Future versions may add:
- `kg_cross_sell_before_save` - Before saving cross-sell data
- `kg_cross_sell_after_save` - After saving cross-sell data
- `kg_cross_sell_api_url` - Filter API base URL

### Extending TariftenService

To add custom methods:

```php
class CustomTariftenService extends \KG_Core\Services\TariftenService {
    public function customMethod() {
        // Custom implementation
    }
}
```

### Custom Suggestion Rendering

Modify `assets/admin/js/cross-sell.js` around line 45-60 to customize suggestion display.

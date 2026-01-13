# RankMath SEO & Ingredient Queue Implementation

## Overview

This implementation addresses two critical issues in the KG Core plugin:

1. **RankMath SEO Optimization**: Automatic generation of SEO metadata for recipe pages using OpenAI
2. **Ingredient Queue System**: Improved CRON-based queue system for AI-powered ingredient generation

## Problem 1: RankMath SEO Optimization

### Issue
When AI creates recipe pages, RankMath SEO plugin fields were not being populated:
- Focus Keyword (rank_math_focus_keyword)
- SEO Title (rank_math_title)
- SEO Meta Description (rank_math_description)

### Solution

#### New Service: RecipeSEOGenerator

Created `/includes/Services/RecipeSEOGenerator.php` with the following features:

**Methods:**
- `generateSEO($recipeId, $recipeData)` - Generates SEO metadata using OpenAI
- `saveSEO($recipeId, $seoData)` - Saves SEO metadata to post meta
- `buildPrompt()` - Creates optimized prompt for SEO generation
- `callOpenAI()` - Handles OpenAI API communication
- `parseResponse()` - Parses and validates AI response

**SEO Generation Logic:**
1. Extracts recipe title, content, excerpt, age group, and main ingredient
2. Sends structured prompt to OpenAI requesting:
   - Focus keyword (natural, searchable, includes recipe name)
   - Meta title (max 60 characters, attractive, informative)
   - Meta description (150-160 characters, includes call-to-action)
3. Validates response structure
4. Saves to both RankMath and Yoast meta fields (for compatibility)

**Example Output:**
```json
{
  "focus_keyword": "Bebekler İçin Brokoli Çorbası",
  "meta_title": "Brokoli Çorbası Tarifi | Besleyici ve Sağlıklı",
  "meta_description": "Lif kaynağı yüksek lezzetli Brokoli Çorbası. Çocuklarınız ve bebekleriniz için ideal tarif. Keşfet!"
}
```

#### Integration Points

**1. Recipe Save Workflow (`RecipeMetaBox.php`)**
- Added `autoGenerateSEO()` method
- Integrated into `save_custom_meta_data()` hook
- Only generates if focus keyword is not already set
- Uses CRON for non-blocking execution

```php
// Auto-generate SEO metadata if enabled and not already set
if ( get_option( 'kg_auto_generate_seo', true ) ) {
    $this->autoGenerateSEO( $post_id );
}
```

**2. Recipe Migration (`AIRecipeMigrator.php`)**
- SEO generation scheduled after recipe creation
- Uses 15-second delay to ensure recipe is fully saved
- Automatically applies to all migrated recipes

```php
// Generate SEO metadata via CRON
if (!wp_next_scheduled('kg_generate_recipe_seo', [$recipeId])) {
    wp_schedule_single_event(time() + 15, 'kg_generate_recipe_seo', [$recipeId]);
}
```

**3. CRON Hook (`kg-core.php`)**
- Registered `kg_generate_recipe_seo` action
- Handles async SEO generation
- Logs success and errors

```php
add_action( 'kg_generate_recipe_seo', function( $recipe_id ) {
    if ( class_exists( '\KG_Core\Services\RecipeSEOGenerator' ) ) {
        $generator = new \KG_Core\Services\RecipeSEOGenerator();
        $seo_data = $generator->generateSEO( $recipe_id );
        
        if ( is_wp_error( $seo_data ) ) {
            error_log( 'KG Core: Recipe SEO generation failed for ID ' . $recipe_id );
        } else {
            $generator->saveSEO( $recipe_id, $seo_data );
            error_log( 'KG Core: Recipe SEO generated successfully for ID ' . $recipe_id );
        }
    }
} );
```

## Problem 2: Ingredient Queue System

### Issue
Ingredients were being created with just titles, without proper AI processing. The OpenAI task queue was not properly implemented.

### Solution

#### Improved CRON-based Queue System

**1. Enhanced Ingredient Generation Hook (`kg-core.php`)**

Added comprehensive error handling and fallback mechanism:

```php
add_action( 'kg_generate_ingredient', function( $ingredient_name ) {
    if ( class_exists( '\KG_Core\Services\IngredientGenerator' ) ) {
        $generator = new \KG_Core\Services\IngredientGenerator();
        $result = $generator->create( $ingredient_name );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'KG Core: Ingredient generation failed for ' . $ingredient_name );
            
            // Fallback: Create basic ingredient post if AI fails
            $fallback_id = wp_insert_post([
                'post_title' => $ingredient_name,
                'post_type' => 'ingredient',
                'post_status' => 'draft',
                'post_content' => 'Bu malzeme otomatik oluşturuldu ve AI ile zenginleştirilmesi bekleniyor.'
            ]);
            
            if ( ! is_wp_error( $fallback_id ) ) {
                update_post_meta( $fallback_id, '_kg_needs_ai_enrichment', '1' );
                error_log( 'KG Core: Fallback ingredient created (ID: ' . $fallback_id . ')' );
            }
        } else {
            error_log( 'KG Core: Ingredient generated successfully - ' . $ingredient_name . ' (ID: ' . $result . ')' );
        }
    }
} );
```

**Key Features:**
- AI-first approach using `IngredientGenerator` service
- Comprehensive error logging
- Fallback mechanism creates draft post when AI fails
- Special meta field `_kg_needs_ai_enrichment` marks ingredients for retry
- All errors logged for monitoring

**2. Recipe Integration**

Ingredients are queued from two locations:

**RecipeMetaBox** - When saving recipes manually:
```php
private function autoGenerateMissingIngredients( $post_id ) {
    // ... code to iterate through ingredients ...
    
    if ( empty( $ingredient_id ) && ! empty( $name ) ) {
        $existing = get_page_by_title( $name, OBJECT, 'ingredient' );
        
        if ( ! $existing ) {
            // Queue via CRON
            wp_schedule_single_event( time() + 5, 'kg_generate_ingredient', [ $name ] );
        }
    }
}
```

**AIRecipeMigrator** - When migrating blog posts:
```php
private function findOrCreateIngredient($name) {
    // ... search for existing ingredient ...
    
    // Use queue system instead of creating empty draft
    if (!wp_next_scheduled('kg_generate_ingredient', [$name])) {
        wp_schedule_single_event(time() + 5, 'kg_generate_ingredient', [$name]);
    }
    
    return null; // Ingredient will be created by CRON
}
```

## Configuration

### WordPress Options

**SEO Generation:**
- `kg_auto_generate_seo` (default: true) - Enable/disable automatic SEO generation
- `kg_openai_api_key` - OpenAI API key
- `kg_ai_model` (default: 'gpt-4o-mini') - AI model to use

**Ingredient Generation:**
- `kg_auto_generate_on_missing` (default: false) - Auto-generate missing ingredients
- `_kg_needs_ai_enrichment` (post meta) - Marks ingredients needing AI processing

### RankMath Meta Fields

The following meta fields are set for each recipe:

**RankMath:**
- `rank_math_focus_keyword` - Main SEO keyword
- `rank_math_title` - SEO page title
- `rank_math_description` - Meta description

**Yoast (Fallback):**
- `_yoast_wpseo_focuskw` - Focus keyword
- `_yoast_wpseo_title` - SEO title
- `_yoast_wpseo_metadesc` - Meta description

## Testing

Run the implementation verification test:

```bash
php test-implementation.php
```

This validates:
- ✓ RecipeSEOGenerator service structure
- ✓ CRON hooks registration
- ✓ Integration into save workflows
- ✓ Fallback mechanisms
- ✓ PHP syntax validity

## Usage Examples

### Manual SEO Generation

```php
$generator = new \KG_Core\Services\RecipeSEOGenerator();
$seo_data = $generator->generateSEO($recipe_id);

if (!is_wp_error($seo_data)) {
    $generator->saveSEO($recipe_id, $seo_data);
}
```

### Queue Ingredient Generation

```php
if (!wp_next_scheduled('kg_generate_ingredient', ['Brokoli'])) {
    wp_schedule_single_event(time() + 5, 'kg_generate_ingredient', ['Brokoli']);
}
```

### Check Scheduled Events

```php
$scheduled = wp_next_scheduled('kg_generate_recipe_seo', [$recipe_id]);
if ($scheduled) {
    echo "SEO generation scheduled at: " . date('Y-m-d H:i:s', $scheduled);
}
```

## Monitoring

### Error Logs

Check WordPress error logs for:
```
KG Core: Recipe SEO generation failed for ID XXX
KG Core: Recipe SEO generated successfully for ID XXX
KG Core: Ingredient generation failed for [name]
KG Core: Ingredient generated successfully - [name] (ID: XXX)
KG Core: Fallback ingredient created (ID: XXX)
```

### CRON Events

Monitor scheduled CRON events:
```php
$crons = _get_cron_array();
foreach ($crons as $timestamp => $cron) {
    foreach ($cron as $hook => $events) {
        if (strpos($hook, 'kg_') === 0) {
            // Log or display KG Core events
        }
    }
}
```

## Best Practices

1. **API Key Security**: Store OpenAI API key in wp-config.php or use environment variables
2. **Rate Limiting**: CRON delays prevent API rate limit issues (5-15 second delays)
3. **Error Handling**: All AI calls have fallback mechanisms
4. **Logging**: Enable WordPress debug logging to monitor CRON execution
5. **Testing**: Test with a small number of recipes before bulk operations
6. **Manual Review**: SEO-generated content should be reviewed before publishing

## Troubleshooting

### SEO Not Generated
- Check if `kg_auto_generate_seo` option is enabled
- Verify OpenAI API key is set
- Check error logs for API errors
- Ensure CRON is running (wp-cron.php)

### Ingredients Not Created
- Enable `kg_auto_generate_on_missing` option
- Check ingredient queue: `wp_next_scheduled('kg_generate_ingredient', [$name])`
- Review error logs for AI failures
- Check for fallback draft ingredients with `_kg_needs_ai_enrichment` meta

### CRON Not Executing
- Ensure WordPress CRON is not disabled
- Consider using real cron instead of wp-cron
- Check server logs for wp-cron.php execution

## Files Modified

1. **New File**: `/includes/Services/RecipeSEOGenerator.php`
   - Complete SEO generation service

2. **Modified**: `/kg-core.php`
   - Added RecipeSEOGenerator inclusion
   - Enhanced ingredient CRON hook with fallback
   - Added recipe SEO CRON hook

3. **Modified**: `/includes/Admin/RecipeMetaBox.php`
   - Added `autoGenerateSEO()` method
   - Integrated into save workflow

4. **Modified**: `/includes/Migration/AIRecipeMigrator.php`
   - Added SEO generation scheduling
   - Improved ingredient queue system

## Future Enhancements

1. **Batch Processing**: Process multiple SEO/ingredient generations in one CRON run
2. **Retry Logic**: Automatic retry for failed AI calls
3. **Admin Interface**: Dashboard to view queued items and manually trigger generation
4. **Analytics**: Track SEO performance metrics
5. **Multi-language**: Support for multiple languages in SEO generation
6. **Schema.org**: Add structured data generation alongside RankMath

# AI-Powered Ingredient Management System

## Overview

The AI-Powered Ingredient Management System automates the creation and management of ingredient content for the KidsGourmet platform. It uses artificial intelligence to generate comprehensive, baby food-specific content in Turkish, complete with nutritional information, allergen data, and high-quality images.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Admin Interface                          │
├─────────────────────────────────────────────────────────────┤
│  ⚙️ AI Settings Page          🤖 Bulk Ingredient Seeder    │
│  - API Key Management          - Predefined packages        │
│  - Provider Selection          - Custom lists               │
│  - Auto-generation toggle      - Progress tracking          │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│                   Service Layer                              │
├─────────────────────────────────────────────────────────────┤
│  🤖 AIService           🖼️ ImageService                     │
│  - OpenAI GPT-4          - Unsplash API                      │
│  - Anthropic Claude      - Pexels API                        │
│  - Google Gemini         - Fallback mechanism               │
│                                                              │
│  🧩 IngredientGenerator                                     │
│  - Orchestrates AI + Image services                         │
│  - Creates WordPress posts                                  │
│  - Manages metadata and taxonomies                          │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│                   Data Layer                                 │
├─────────────────────────────────────────────────────────────┤
│  📊 WordPress Database                                       │
│  - ingredient post type                                      │
│  - Meta fields (_kg_*)                                       │
│  - allergen taxonomy                                         │
│  - Media library                                             │
└─────────────────────────────────────────────────────────────┘
```

## Components

### 1. AIService (`includes/Services/AIService.php`)

Multi-provider AI service for generating ingredient content.

**Supported Providers:**
- OpenAI (GPT-4o, GPT-4o-mini, GPT-4 Turbo)
- Anthropic (Claude 3.5 Sonnet, Claude 3 Opus, Claude 3 Sonnet)
- Google (Gemini 1.5 Pro, Gemini 1.5 Flash)

**Key Methods:**
```php
public function generateIngredientContent(string $ingredient_name): array
```
Generates comprehensive ingredient data including:
- Title, excerpt, content (3-4 paragraphs)
- **Category** (Meyveler/Sebzeler/Proteinler/Tahıllar/Süt Ürünleri)
- Start age (in months)
- Benefits, allergy risk, allergens
- Season, storage tips, preparation tips
- **Selection tips** (how to choose fresh ingredients)
- **Pro tips** (baby-specific tips)
- **Prep by age** (age-specific preparation methods)
- **Pairings** (compatible ingredient combinations)
- Nutrition values (calories, protein, carbs, fat, fiber, vitamins)
- FAQ (3 questions/answers)
- Image search query (in English)

**Prompt Engineering:**
The system uses a specialized prompt that:
- Identifies as a baby nutrition expert
- Requests Turkish content
- Enforces specific JSON structure
- Focuses on baby food safety and nutrition
- Includes new fields for comprehensive ingredient data

### 2. ImageService (`includes/Services/ImageService.php`)

Handles fetching and downloading ingredient images from stock photo APIs and AI generation.

**Supported APIs:**
- **DALL-E 3** (AI-generated images with consistent studio style)
- Unsplash (landscape orientation, high quality)
- Pexels (landscape orientation)

**DALL-E 3 Features:**
- Generates professional food photography with consistent style
- Clean white background, studio lighting
- Turkish-to-English translation for accurate prompts
- Automatic metadata tagging (AI-generated flag)
- Cost: ~$0.04/image

**Key Methods:**
```php
public function generateImage(string $ingredient_name): ?array
public function fetchImage(string $query): ?array
public function downloadToMediaLibrary(string $url, string $filename): int
```

**Translation Support:**
Includes 40+ Turkish-to-English ingredient name translations for optimal DALL-E 3 prompts.

### 3. IngredientGenerator (`includes/Services/IngredientGenerator.php`)

Orchestrates the entire ingredient creation process.

**Workflow:**
1. Check if ingredient already exists
2. Generate content with AIService
3. Create WordPress post (draft status)
4. Save all meta fields
5. Fetch and attach featured image
6. Create/assign allergen terms
7. Return post ID

**Key Methods:**
```php
public function create(string $ingredient_name): int|WP_Error
```

**Error Handling:**
- Returns WP_Error for all failures
- Logs errors to WordPress error log
- Handles cron context (author ID fallback)

### 4. SettingsPage (`includes/Admin/SettingsPage.php`)

Admin interface for configuring AI and image APIs.

**Settings:**
- `kg_ai_provider` - AI provider (openai/anthropic/gemini)
- `kg_ai_api_key` - API key (password-masked)
- `kg_ai_model` - Model name
- `kg_unsplash_api_key` - Unsplash API key
- `kg_pexels_api_key` - Pexels API key
- `kg_preferred_image_api` - Primary image API (dall-e/unsplash/pexels)
- `kg_auto_generate_on_missing` - Auto-generation toggle

**Security:**
- `manage_options` capability required
- WordPress settings API with built-in nonce verification
- Password inputs for API keys

**Location:** Malzeme Rehberi > ⚙️ AI Ayarları

### 5. BulkIngredientSeeder (`includes/Admin/BulkIngredientSeeder.php`)

Admin tool for bulk ingredient creation.

**Predefined Categories:**
- 🥕 All 100+ ingredients
- 🍎 Fruits (25 items)
- 🥦 Vegetables (30 items)
- 🍗 Proteins (20 items)
- 🌾 Grains (15 items)
- 🥛 Dairy Products (10 items)

**Features:**
- One-click package deployment
- Custom ingredient list input
- Real-time progress bar
- Live logging with timestamps
- Rate limiting (2-second delay)
- Duplicate detection
- Stop/pause functionality

**AJAX Handler:**
```php
public function ajax_seed_ingredient()
```

**Location:** Malzeme Rehberi > 🤖 Toplu AI Oluştur

### 6. AIEnrichButton (`includes/Admin/AIEnrichButton.php`)

Admin bar button for enriching existing ingredients with AI-generated content.

**Features:**
- Appears in admin bar on ingredient edit pages
- Modal interface with options:
  - **Overwrite existing data**: Replace all fields or only fill empty ones
  - **Generate new image**: Create/replace featured image
- AJAX-powered with real-time progress
- Automatic page reload on success

**Options:**
- **Overwrite Mode**: Replaces all existing content with AI-generated data
- **Fill Empty Mode**: Only populates fields that are currently empty
- **Image Generation**: Uses configured image API (DALL-E 3/Unsplash/Pexels)

**AJAX Handler:**
```php
public function ajax_enrich_ingredient()
```

**Security:**
- `manage_options` capability required
- Nonce verification
- Post type validation

**JavaScript:**
- Modal display/hide
- Progress indication
- AJAX communication
- Auto-reload on success

**Location:** Admin bar (visible only on ingredient edit pages)

### 7. AIController (`includes/API/AIController.php`)

REST API endpoints for ingredient generation.

**Endpoints:**

**POST** `/wp-json/kg/v1/ai/generate-ingredient`
- Body: `{"name": "Havuç"}`
- Returns: `{"success": true, "post_id": 123}`
- Authorization: Bearer token (manage_options)

**GET** `/wp-json/kg/v1/ai/status`
- Returns: Provider, configuration status, enabled features
- Authorization: Bearer token (manage_options)

### 7. RecipeMetaBox Auto-Generation

Automatically generates missing ingredients when saving recipes.

**How it Works:**
1. User saves a recipe with ingredients
2. System checks each ingredient for `ingredient_id`
3. If missing (not linked to an ingredient post):
   - Check if ingredient exists by title
   - If not exists, schedule cron job: `kg_generate_ingredient`
4. Cron job runs in background (5 seconds after save)
5. IngredientGenerator creates the ingredient

**Configuration:**
Enable in AI Settings: "Tarif kaydedilirken eksik malzemeleri otomatik oluştur"

## Data Structure

### Generated Ingredient Post

**Post Data:**
```php
[
  'post_title' => 'Havuç',
  'post_content' => '<p>Havuç, bebeklerin...</p><p>Zengin...</p>',
  'post_excerpt' => 'Bebeklerin ilk ek gıda döneminde...',
  'post_type' => 'ingredient',
  'post_status' => 'draft',
  'post_author' => 1
]
```

**Meta Fields:**
- `_kg_start_age` (int): 4, 6, 8, 10, 12, 18, 24, 36
- `_kg_category` (string): Meyveler, Sebzeler, Proteinler, Tahıllar, Süt Ürünleri
- `_kg_benefits` (text): HTML-formatted benefits
- `_kg_allergy_risk` (string): Düşük, Orta, Yüksek
- `_kg_season` (string): İlkbahar, Yaz, Sonbahar, Kış, Tüm Yıl
- `_kg_storage_tips` (text): Storage instructions
- `_kg_selection_tips` (text): How to choose fresh ingredients
- `_kg_pro_tips` (text): Baby-specific pro tips
- `_kg_preparation_tips` (text): General preparation tips
- `_kg_prep_methods` (array): ['Püre', 'Haşlama', 'Buhar']
- `_kg_prep_by_age` (array): [{'age': '6-9 Ay', 'method': 'Püre', 'text': '...'}]
- `_kg_pairings` (array): [{'emoji': '🍌', 'name': 'Muz'}]
- `_kg_calories` (string): Calories per 100g
- `_kg_protein` (string): Protein in grams
- `_kg_carbs` (string): Carbohydrates in grams
- `_kg_fat` (string): Fat in grams
- `_kg_fiber` (string): Fiber in grams
- `_kg_vitamins` (string): Vitamin list
- `_kg_faq` (array): [['question' => '...', 'answer' => '...']]
- `_kg_image_credit` (string): Photographer name or 'AI Generated (DALL-E 3)'
- `_kg_image_credit_url` (string): Photographer profile URL
- `_kg_image_source` (string): unsplash/pexels/dall-e-3
- `_kg_ai_generated` (bool): True if image was AI-generated (on attachment)

**Taxonomies:**
- `allergen`: Süt, Yumurta, Gluten, Fıstık, etc.

## AI Prompt Structure

The system sends a specialized prompt to the AI:

```
Sen bebek beslenmesi konusunda uzman bir diyetisyen ve çocuk doktorusun.
Aşağıdaki malzeme hakkında Türkçe olarak detaylı ve bilimsel bilgi ver.

Malzeme: {name}

Lütfen yanıtını SADECE aşağıdaki JSON formatında ver:
{
  "title": "...",
  "excerpt": "...",
  "content": "...",
  ...
}
```

This ensures:
- Turkish language responses
- Baby nutrition expertise
- Structured JSON output
- Consistent quality

## Security

### API Key Storage
- Keys stored in `wp_options` table
- Displayed as password inputs (masked)
- Sanitized with `sanitize_text_field()`

### Access Control
- Settings page: `manage_options` capability
- Bulk seeder: `manage_options` capability
- REST API: Bearer token with `manage_options`
- AJAX: Nonce verification

### Rate Limiting
- Bulk operations: 2-second delay between requests
- Prevents API quota exhaustion
- User can stop process anytime

### Content Safety
- All AI-generated content saved as **draft**
- Requires manual review before publishing
- Prevents inappropriate content from going live

## New Features (v1.1)

### DALL-E 3 Image Generation
- Professional food photography with consistent studio style
- Turkish-to-English translation for accurate prompts
- Clean white background, optimal studio lighting
- Cost-effective: ~$0.04 per image
- Automatic AI-generated metadata tagging

### Enhanced Meta Fields
- **Category**: Organized classification (Fruits, Vegetables, Proteins, etc.)
- **Prep by Age**: Age-specific preparation instructions in JSON format
- **Selection Tips**: Guide for choosing fresh, quality ingredients
- **Pro Tips**: Baby-specific expert advice
- **Pairings**: Compatible ingredient combinations with emojis

### AI Enrichment Button
- One-click enrichment for existing ingredients
- Available in admin bar on ingredient edit pages
- Options to overwrite or fill only empty fields
- Optional new image generation
- Real-time progress with automatic page reload

### Updated API Responses
All new fields available via REST API:
- `/wp-json/kg/v1/ingredients/{slug}` includes all new fields
- Category, prep_by_age, pairings, selection_tips, pro_tips
- Image metadata including AI-generated flag
- Complete nutrition data

## Usage Examples

### Example 1: Configure and Generate Single Ingredient

```php
// 1. Configure in admin (or programmatically)
update_option('kg_ai_provider', 'openai');
update_option('kg_ai_api_key', 'sk-...');
update_option('kg_ai_model', 'gpt-4o-mini');

// 2. Generate ingredient
$generator = new \KG_Core\Services\IngredientGenerator();
$post_id = $generator->create('Havuç');

if (is_wp_error($post_id)) {
    echo 'Error: ' . $post_id->get_error_message();
} else {
    echo 'Created ingredient with ID: ' . $post_id;
}
```

### Example 2: Bulk Generate via REST API

```javascript
const token = 'your-jwt-token';
const ingredients = ['Elma', 'Armut', 'Muz'];

for (const name of ingredients) {
  const response = await fetch('/wp-json/kg/v1/ai/generate-ingredient', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ name })
  });
  
  const result = await response.json();
  console.log(`${name}: ${result.success ? '✓' : '✗'}`);
  
  // Rate limiting
  await new Promise(resolve => setTimeout(resolve, 2000));
}
```

### Example 3: Enrich Existing Ingredient

**Via Admin Interface:**
1. Navigate to ingredient edit page
2. Click "🤖 AI ile Zenginleştir" in admin bar
3. Choose options:
   - ✓ Overwrite existing data (or fill only empty fields)
   - ✓ Generate new image
4. Click "Zenginleştir"
5. Wait for completion and automatic page reload

**Via Code:**
```php
use KG_Core\Services\AIService;
use KG_Core\Services\ImageService;

$post_id = 123; // Existing ingredient ID
$ai_service = new AIService();
$image_service = new ImageService();

// Generate new content
$ingredient_name = get_the_title($post_id);
$ai_data = $ai_service->generateIngredientContent($ingredient_name);

// Update meta fields
if (!is_wp_error($ai_data)) {
    update_post_meta($post_id, '_kg_category', $ai_data['category']);
    update_post_meta($post_id, '_kg_selection_tips', $ai_data['selection_tips']);
    update_post_meta($post_id, '_kg_pro_tips', $ai_data['pro_tips']);
    update_post_meta($post_id, '_kg_prep_by_age', $ai_data['prep_by_age']);
    update_post_meta($post_id, '_kg_pairings', $ai_data['pairings']);
    
    // Generate new image with DALL-E 3
    $image_data = $image_service->generateImage($ingredient_name);
    if ($image_data) {
        $attachment_id = $image_service->downloadToMediaLibrary(
            $image_data['url'],
            sanitize_title($ingredient_name)
        );
        set_post_thumbnail($post_id, $attachment_id);
    }
}
```

### Example 4: Custom Cron Schedule

```php
// Schedule weekly ingredient generation
add_action('init', function() {
    if (!wp_next_scheduled('kg_weekly_ingredient_batch')) {
        wp_schedule_event(time(), 'weekly', 'kg_weekly_ingredient_batch');
    }
});

add_action('kg_weekly_ingredient_batch', function() {
    $ingredients = ['New Ingredient 1', 'New Ingredient 2'];
    
    foreach ($ingredients as $name) {
        wp_schedule_single_event(time(), 'kg_generate_ingredient', [$name]);
    }
});
```

## Performance Considerations

### Generation Time
- **AI Response**: 2-8 seconds (varies by provider/model)
- **Image Download**: 2-5 seconds
- **WordPress Save**: 1-2 seconds
- **Total per ingredient**: ~5-15 seconds

### Bulk Operations
- 10 ingredients: ~2-3 minutes
- 25 ingredients: ~5-8 minutes
- 100 ingredients: ~20-30 minutes

### Optimization Tips
1. Use faster AI models (gpt-4o-mini, gemini-flash)
2. Pre-cache common ingredients
3. Run bulk operations during off-peak hours
4. Use real cron instead of WP-Cron for background jobs

## Cost Estimation

### OpenAI GPT-4o-mini
- Input: ~$0.15 per 1M tokens
- Output: ~$0.60 per 1M tokens
- Average per ingredient: ~$0.002-$0.005
- 100 ingredients: ~$0.20-$0.50

### OpenAI DALL-E 3
- Standard quality (1024x1024): $0.040 per image
- HD quality (1024x1024): $0.080 per image
- 100 ingredients: ~$4.00 (standard) or ~$8.00 (HD)

### Total Cost (AI Content + DALL-E 3)
- Per ingredient: ~$0.042-$0.045
- 100 ingredients: ~$4.20-$4.50

### Unsplash
- Free tier: 50 requests/hour
- Sufficient for most use cases

### Pexels
- Free tier: Unlimited
- Rate limit: Check API documentation

## Troubleshooting

### Common Issues

**Issue**: "AI API anahtarı ayarlanmamış"
- **Solution**: Configure API key in AI Settings

**Issue**: "JSON parse error"
- **Cause**: AI returned invalid JSON
- **Solution**: Check AI provider status, try different model

**Issue**: "No image found"
- **Cause**: Image API returned no results
- **Solution**: Check image search query, try fallback API

**Issue**: Cron not running
- **Solution**: Run manually: `wp cron event run kg_generate_ingredient`

**Issue**: Rate limit exceeded
- **Solution**: Wait 60 seconds, check API quotas

### Debug Mode

Enable WordPress debug logging:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs:
```bash
tail -f wp-content/debug.log
```

Look for:
- `KG Core AI Error: ...`
- `KG Core Unsplash Error: ...`
- `KG Core: Ingredient generated successfully ...`

## Future Enhancements

### Planned Features
- [ ] Batch image optimization
- [ ] Multi-language support
- [ ] AI content review/approval workflow
- [ ] Analytics dashboard (generation stats)
- [ ] Custom AI prompt templates
- [ ] Ingredient updates via AI (regenerate old content)
- [ ] Image regeneration without content change
- [ ] Scheduled batch generation
- [ ] Export/import ingredient data

### API Integrations
- [ ] Additional image APIs (Pixabay, etc.)
- [ ] Nutrition databases (USDA, etc.)
- [ ] Translation services for multi-language

## Taxonomies with Rich Metadata

### Age Group Taxonomy

The Age Group taxonomy has been enhanced with WHO-based metadata to provide comprehensive age-appropriate recipe filtering.

#### Structure

File: `includes/Taxonomies/AgeGroup.php`

#### Default Terms

Five age categories are automatically created on plugin activation:

1. **Hazırlık Evresi (0-6 Ay)** - `0-6-ay-sadece-sut`
2. **Başlangıç & Tadım (6-8 Ay)** - `6-8-ay-baslangic`
3. **Keşif & Pütürlüye Geçiş (9-11 Ay)** - `9-11-ay-kesif`
4. **Aile Sofrasına Geçiş (12-24 Ay)** - `12-24-ay-gecis`
5. **Çocuk Gurme (2+ Yaş)** - `2-yas-ve-uzeri`

#### Metadata Fields

Each age group includes the following metadata (prefix: `_kg_`):

- `min_month` (int): Minimum age in months
- `max_month` (int): Maximum age in months
- `daily_meal_count` (int): Recommended daily meal count
- `max_salt_limit` (string): Maximum salt consumption guideline
- `texture_guide` (string): Food texture recommendations
- `forbidden_list` (JSON array): List of prohibited foods
- `color_code` (string): HEX color code for UI representation
- `warning_message` (string): Age-specific warnings

#### Admin Interface

- **Add Term Form:** Custom fields for all metadata when creating new age groups
- **Edit Term Form:** Editable fields for existing age groups
- **Auto-save:** Metadata automatically saved with term creation/update

#### REST API Support

Age group metadata is exposed via REST API:

**Endpoint:** `/wp-json/wp/v2/age-group`

**Response Example:**
```json
{
  "id": 15,
  "name": "Başlangıç & Tadım (6-8 Ay)",
  "slug": "6-8-ay-baslangic",
  "age_group_meta": {
    "min_month": 6,
    "max_month": 8,
    "daily_meal_count": 2,
    "max_salt_limit": "0g (Yasak)",
    "texture_guide": "Yoğurt kıvamı, pürüzsüz püreler veya parmak boyutunda yumuşak parçalar (BLW)",
    "forbidden_list": ["Bal", "İnek Sütü", "Tuz", "Şeker", "Yumurta Beyazı"],
    "color_code": "#A8E6CF",
    "warning_message": "Her yeni gıdayı 3 gün arayla deneyin."
  }
}
```

### Meal Type Taxonomy

A new taxonomy for categorizing recipes by meal type.

#### Structure

File: `includes/Taxonomies/MealType.php`

**Configuration:**
- Slug: `meal-type`
- Post Type: `recipe`
- Hierarchical: `false` (tag-like flat structure)
- REST API: Enabled

#### Default Terms

Six meal types are automatically created:

1. **Kahvaltı** - `kahvalti` (Breakfast) 🌅
2. **Ara Öğün (Kuşluk)** - `ara-ogun-kusluk` (Morning Snack) 🍎
3. **Öğle Yemeği** - `ogle-yemegi` (Lunch) 🍽️
4. **Ara Öğün (İkindi)** - `ara-ogun-ikindi` (Afternoon Snack) 🧃
5. **Akşam Yemeği** - `aksam-yemegi` (Dinner) 🌙
6. **Beslenme Çantası** - `beslenme-cantasi` (Lunch Box) 🎒

#### Metadata Fields

Each meal type includes (prefix: `_kg_`):

- `icon` (string): Emoji icon representation
- `time_range` (string): Recommended time range
- `color_code` (string): HEX color code for UI

#### Admin Interface

- **Add/Edit Forms:** Custom fields for icon, time range, and color code
- **Visual Display:** Emoji icons visible in admin columns

#### REST API Support

Meal type metadata is exposed via REST API:

**Endpoint:** `/wp-json/wp/v2/meal-type`

**Response Example:**
```json
{
  "id": 20,
  "name": "Kahvaltı",
  "slug": "kahvalti",
  "meal_type_meta": {
    "icon": "🌅",
    "time_range": "07:00-09:00",
    "color_code": "#FFE4B5"
  }
}
```

#### Usage in Recipes

To assign meal types to recipes, use the standard WordPress term assignment:

```php
// Assign meal type to recipe
wp_set_object_terms( $recipe_id, ['kahvalti', 'ara-ogun-kusluk'], 'meal-type' );

// Get recipes by meal type
$args = [
    'post_type' => 'recipe',
    'tax_query' => [
        [
            'taxonomy' => 'meal-type',
            'field'    => 'slug',
            'terms'    => 'kahvalti',
        ],
    ],
];
$breakfast_recipes = new WP_Query( $args );
```

## Support

For issues or feature requests:
1. Check WordPress debug.log
2. Verify API keys and quotas
3. Test with single ingredient first
4. Review AI_TESTING.md for test procedures

## License

Proprietary - Hip Medya

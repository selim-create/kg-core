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
- Start age (in months)
- Benefits, allergy risk, allergens
- Season, storage tips, preparation tips
- Nutrition values (calories, protein, carbs, fat, fiber, vitamins)
- FAQ (3 questions/answers)
- Image search query (in English)

**Prompt Engineering:**
The system uses a specialized prompt that:
- Identifies as a baby nutrition expert
- Requests Turkish content
- Enforces specific JSON structure
- Focuses on baby food safety and nutrition

### 2. ImageService (`includes/Services/ImageService.php`)

Handles fetching and downloading ingredient images from stock photo APIs.

**Supported APIs:**
- Unsplash (landscape orientation, high quality)
- Pexels (landscape orientation)

**Features:**
- Configurable primary API with automatic fallback
- Downloads images to WordPress media library
- Stores attribution metadata (credit, source, URL)

**Key Methods:**
```php
public function fetchImage(string $query): ?array
public function downloadToMediaLibrary(string $url, string $filename): int
```

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
- `kg_preferred_image_api` - Primary image API
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

### 6. AIController (`includes/API/AIController.php`)

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
- `_kg_benefits` (text): HTML-formatted benefits
- `_kg_allergy_risk` (string): Düşük, Orta, Yüksek
- `_kg_season` (string): İlkbahar, Yaz, Sonbahar, Kış, Tüm Yıl
- `_kg_storage_tips` (text): Storage instructions
- `_kg_preparation_tips` (text): Baby-specific preparation
- `_kg_prep_methods` (array): ['Püre', 'Haşlama', 'Buhar']
- `_kg_calories` (string): Calories per 100g
- `_kg_protein` (string): Protein in grams
- `_kg_carbs` (string): Carbohydrates in grams
- `_kg_fat` (string): Fat in grams
- `_kg_fiber` (string): Fiber in grams
- `_kg_vitamins` (string): Vitamin list
- `_kg_faq` (array): [['question' => '...', 'answer' => '...']]
- `_kg_image_credit` (string): Photographer name
- `_kg_image_credit_url` (string): Photographer profile URL
- `_kg_image_source` (string): unsplash/pexels

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

### Example 3: Custom Cron Schedule

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

## Support

For issues or feature requests:
1. Check WordPress debug.log
2. Verify API keys and quotas
3. Test with single ingredient first
4. Review AI_TESTING.md for test procedures

## License

Proprietary - Hip Medya

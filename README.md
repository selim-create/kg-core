# KG Core (KidsGourmet Backend)

WordPress headless CMS plugin providing comprehensive REST API for the KidsGourmet platform.

## Overview

KG Core is a complete backend solution for a baby food recipes platform, offering:

- **Custom Post Types:** Recipes, Ingredients, Tools
- **Taxonomies:** Age Groups (with WHO-based metadata), Meal Types, Allergens, Diet Types
- **REST API:** Full CRUD operations with JWT authentication
- **User Management:** Profile, children profiles, favorites, shopping lists
- **Expert Reviews:** Recipe validation by nutritionists
- **Cross-selling:** Integration with Tariften.com
- **🤖 AI-Powered Content:** Automated ingredient generation with multiple AI providers

## Features

### 🤖 AI-Powered Ingredient Management (NEW)
- Multi-provider AI support (OpenAI GPT-4, Anthropic Claude, Google Gemini)
- Automated ingredient content generation in Turkish
- Bulk ingredient creation (100+ predefined items)
- Automatic image fetching (Unsplash/Pexels)
- Smart allergen assignment
- Auto-generation on recipe save
- See [AI_DOCUMENTATION.md](./AI_DOCUMENTATION.md) for details

### 🍽️ Recipe Management
- Complete recipe CRUD with nutritional information
- Age-appropriate filtering (4+ months to 2+ years)
- Allergen warnings and diet type classifications
- Expert approval system
- Video tutorial support
- Ingredient substitutions
- Related recipes suggestions

### 🥕 Ingredient Database
- Comprehensive ingredient information
- Age recommendations for introduction
- Preparation methods
- Allergy risk indicators
- Seasonal availability
- Storage tips
- FAQ support
- AI-generated content with manual review

### 👤 User Features
- JWT-based authentication
- User profiles with contact information
- Multiple children profiles with allergen tracking
- Favorite recipes collection
- Shopping list management
- Age-based recipe recommendations

### 🔍 Search & Discovery
- Global search across recipes and ingredients
- Advanced filtering by age group, diet type, allergens
- Featured recipes showcase

## Installation

1. Upload the plugin to `/wp-content/plugins/kg-core/`
2. Activate through WordPress admin panel
3. Access API at `/wp-json/kg/v1/`

## API Documentation

See [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) for complete endpoint reference.

### Quick Start

**Get all recipes:**
```bash
GET /wp-json/kg/v1/recipes
```

**Get recipe by slug:**
```bash
GET /wp-json/kg/v1/recipes/bal-kabakli-bebek-corbasi
```

**User registration:**
```bash
POST /wp-json/kg/v1/auth/register
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "securepassword",
  "name": "User Name"
}
```

**Search:**
```bash
GET /wp-json/kg/v1/search?q=kabak&type=recipe
```

**AI-powered ingredient generation:**
```bash
POST /wp-json/kg/v1/ai/generate-ingredient
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "name": "Havuç"
}
```

## Architecture

```
kg-core/
├── includes/
│   ├── API/                    # REST API Controllers
│   │   ├── RecipeController.php
│   │   ├── IngredientController.php
│   │   ├── UserController.php
│   │   ├── SearchController.php
│   │   └── AIController.php         # 🆕 AI endpoints
│   ├── Admin/                  # WordPress Admin Meta Boxes
│   │   ├── RecipeMetaBox.php
│   │   ├── IngredientMetaBox.php
│   │   ├── SettingsPage.php         # 🆕 AI Settings
│   │   └── BulkIngredientSeeder.php # 🆕 Bulk AI Generator
│   ├── Auth/                   # Authentication
│   │   └── JWTHandler.php
│   ├── Services/               # Business Logic Services
│   │   ├── TariftenService.php
│   │   ├── AIService.php            # 🆕 Multi-provider AI
│   │   ├── ImageService.php         # 🆕 Unsplash/Pexels
│   │   └── IngredientGenerator.php  # 🆕 Ingredient creation
│   ├── PostTypes/              # Custom Post Types
│   │   ├── Recipe.php
│   │   ├── Ingredient.php
│   │   └── Tool.php
│   ├── Taxonomies/             # Custom Taxonomies
│   │   ├── AgeGroup.php             # WHO-based age categories with metadata
│   │   ├── MealType.php             # 🆕 Meal types (breakfast, lunch, etc.)
│   │   ├── Allergen.php
│   │   └── DietType.php
│   └── Utils/                  # Helper Functions
│       └── Helper.php
└── kg-core.php                 # Main plugin file
```

## Custom Post Types

### Recipe
- Fields: title, content, excerpt, thumbnail
- Meta: prep_time, ingredients, instructions, nutrition, expert info, video_url
- Taxonomies: age-group, meal-type, allergen, diet-type

### Ingredient
- Fields: title, content, excerpt, thumbnail
- Meta: start_age, benefits, prep_methods, allergy_risk, season, storage_tips, FAQ
- Taxonomies: allergen

### Tool
- Kitchen tools and equipment database

## Taxonomies

### Age Group (WHO-Based with Rich Metadata)

Age groups aligned with WHO guidelines and developmental milestones:

| Age Group | Slug | Months | Daily Meals | Salt Limit | Color |
|-----------|------|--------|-------------|------------|-------|
| Hazırlık Evresi (0-6 Ay) | `0-6-ay-sadece-sut` | 0-6 | 0 | 0g (Yasak) | #E8F5E9 |
| Başlangıç & Tadım (6-8 Ay) | `6-8-ay-baslangic` | 6-8 | 2 | 0g (Yasak) | #A8E6CF |
| Keşif & Pütürlüye Geçiş (9-11 Ay) | `9-11-ay-kesif` | 9-11 | 3 | 0g (Yasak) | #FDFD96 |
| Aile Sofrasına Geçiş (12-24 Ay) | `12-24-ay-gecis` | 12-24 | 5 | <1g/gün | #FFB347 |
| Çocuk Gurme (2+ Yaş) | `2-yas-ve-uzeri` | 24-144 | 5 | <2g/gün | #87CEEB |

**Metadata Fields:**
- `min_month`, `max_month`: Age range in months
- `daily_meal_count`: Recommended daily meals
- `max_salt_limit`: Salt consumption guidelines
- `texture_guide`: Food texture recommendations
- `forbidden_list`: JSON array of prohibited foods
- `color_code`: HEX color for UI
- `warning_message`: Age-specific warnings

**REST API:** Accessible at `/wp-json/wp/v2/age-group` with `age_group_meta` field

### Meal Type

Meal types for recipe categorization:

| Meal Type | Slug | Icon | Time Range | Color |
|-----------|------|------|------------|-------|
| Kahvaltı | `kahvalti` | 🌅 | 07:00-09:00 | #FFE4B5 |
| Ara Öğün (Kuşluk) | `ara-ogun-kusluk` | 🍎 | 10:00-11:00 | #98FB98 |
| Öğle Yemeği | `ogle-yemegi` | 🍽️ | 12:00-13:00 | #FFD700 |
| Ara Öğün (İkindi) | `ara-ogun-ikindi` | 🧃 | 15:00-16:00 | #DDA0DD |
| Akşam Yemeği | `aksam-yemegi` | 🌙 | 18:00-19:00 | #87CEEB |
| Beslenme Çantası | `beslenme-cantasi` | 🎒 | Değişken | #F0E68C |

**Metadata Fields:**
- `icon`: Emoji icon for the meal type
- `time_range`: Recommended time range
- `color_code`: HEX color for UI

**REST API:** Accessible at `/wp-json/wp/v2/meal-type` with `meal_type_meta` field

### Allergen
- Süt, Yumurta, Gluten, Fıstık, Balık, Soya, Kabuklu Deniz Ürünleri, Fındık, Susam, Hardal

### Diet Type
- BLW, Püre, Vegan, Vejetaryen, Glutensiz, Şekersiz, Tuzsuz, Laktozsuz

## Security

- **JWT Authentication:** Stateless token-based auth
- **Input Sanitization:** All user inputs sanitized
- **Nonce Verification:** WordPress nonce for admin operations
- **Permission Callbacks:** Role-based access control
- **CORS:** Configured for cross-origin requests

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+

## Development

### Adding New Endpoints

1. Create controller in `includes/API/`
2. Register routes in `register_routes()` method
3. Add controller to `kg-core.php`
4. Initialize in `kg_core_init()` function

### Adding Meta Fields

1. Update respective MetaBox class
2. Add field to `render_meta_box()` method
3. Add save logic to `save_custom_meta_data()` method

## CORS Configuration

CORS is configured for security by default. To enable access from your frontend:

### Development Setup

Add this to your theme's `functions.php` or a custom plugin:

```php
// Allow localhost development
add_filter( 'kg_core_allowed_origins', function( $origins ) {
    return array_merge( $origins, [
        'http://localhost:3000',
        'http://localhost:3001',
    ]);
});
```

### Production Setup

```php
// Production domains only
add_filter( 'kg_core_allowed_origins', function( $origins ) {
    return [
        'https://kidsgourmet.com.tr',
        'https://www.kidsgourmet.com.tr',
    ];
});
```

### Enable Public CORS (Not Recommended)

Only use this if you need to allow any domain for public endpoints:

```php
add_filter( 'kg_core_allow_public_cors', '__return_true' );
```

**Security Note:** By default, CORS is restricted to whitelisted origins. Public CORS is disabled to prevent API abuse.

## License

Proprietary - Hip Medya

## Support

For technical support, contact the development team.

## Version History

### 1.0.0 (2024)
- Initial release
- Complete recipe and ingredient management
- JWT authentication
- User profiles and children tracking
- Favorites and shopping list features
- Comprehensive REST API
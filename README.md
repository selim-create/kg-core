# KG Core (KidsGourmet Backend)

WordPress headless CMS plugin providing comprehensive REST API for the KidsGourmet platform.

## Overview

KG Core is a complete backend solution for a baby food recipes platform, offering:

- **Custom Post Types:** Recipes, Ingredients, Tools
- **Taxonomies:** Age Groups, Allergens, Diet Types
- **REST API:** Full CRUD operations with JWT authentication
- **User Management:** Profile, children profiles, favorites, shopping lists
- **Expert Reviews:** Recipe validation by nutritionists
- **Cross-selling:** Integration with Tariften.com

## Features

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

## Architecture

```
kg-core/
├── includes/
│   ├── API/                    # REST API Controllers
│   │   ├── RecipeController.php
│   │   ├── IngredientController.php
│   │   ├── UserController.php
│   │   └── SearchController.php
│   ├── Admin/                  # WordPress Admin Meta Boxes
│   │   ├── RecipeMetaBox.php
│   │   └── IngredientMetaBox.php
│   ├── Auth/                   # Authentication
│   │   └── JWTHandler.php
│   ├── PostTypes/              # Custom Post Types
│   │   ├── Recipe.php
│   │   ├── Ingredient.php
│   │   └── Tool.php
│   ├── Taxonomies/             # Custom Taxonomies
│   │   ├── AgeGroup.php
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
- Taxonomies: age-group, allergen, diet-type

### Ingredient
- Fields: title, content, excerpt, thumbnail
- Meta: start_age, benefits, prep_methods, allergy_risk, season, storage_tips, FAQ
- Taxonomies: allergen

### Tool
- Kitchen tools and equipment database

## Taxonomies

### Age Group
- 4-6 ay (4-6 months)
- 6-12 ay (6-12 months)
- 12-24 ay (1-2 years)
- 2 yaş üzeri (2+ years)

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
        'https://kidsgourmet.com',
        'https://www.kidsgourmet.com',
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
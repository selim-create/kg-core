# KG Core - API Documentation

## Overview

KG Core is the headless backend for KidsGourmet, providing RESTful API endpoints for recipes, ingredients, user management, and more.

**Base URL:** `/wp-json/kg/v1`

---

## Authentication

Most endpoints are public, but user-specific features require JWT authentication.

### Endpoints

#### Register User
```
POST /kg/v1/auth/register
```

**Body:**
```json
{
  "email": "user@example.com",
  "password": "securepassword",
  "name": "User Name"
}
```

**Response (201):**
```json
{
  "token": "eyJhbGciOiJIUzI1...",
  "user_id": 1,
  "email": "user@example.com",
  "name": "User Name"
}
```

---

#### Login
```
POST /kg/v1/auth/login
```

**Body:**
```json
{
  "email": "user@example.com",
  "password": "securepassword"
}
```

**Response (200):**
```json
{
  "token": "eyJhbGciOiJIUzI1...",
  "user_id": 1,
  "email": "user@example.com",
  "name": "User Name"
}
```

---

#### Logout
```
POST /kg/v1/auth/logout
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

---

#### Get Current User
```
GET /kg/v1/auth/me
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "user_id": 1,
  "email": "user@example.com",
  "name": "User Name"
}
```

---

## Recipes

### Get All Recipes
```
GET /kg/v1/recipes
```

**Query Parameters:**
- `page` (int): Page number (default: 1)
- `per_page` (int): Results per page (default: 10)
- `age_group` (string): Filter by age group slug
- `diet_type` (string): Filter by diet type slug
- `allergen` (string): Exclude this allergen

**Response (200):**
```json
{
  "recipes": [
    {
      "id": 123,
      "title": "Bal Kabaklı Bebek Çorbası",
      "slug": "bal-kabakli-bebek-corbasi",
      "excerpt": "6 aydan itibaren...",
      "image": "https://example.com/image.jpg",
      "prep_time": "15",
      "ingredients": ["1 adet bal kabağı", "1 su bardağı su"],
      "instructions": [{"title": "Adım 1", "text": "..."}],
      "age_groups": ["6-12 ay"],
      "allergens": [],
      "diet_types": ["Püre"],
      "is_featured": false,
      "expert": {
        "name": "Dyt. Ayşe Yılmaz",
        "approved": true
      }
    }
  ],
  "total": 45,
  "pages": 5
}
```

---

### Get Recipe by Slug
```
GET /kg/v1/recipes/{slug}
```

**Response (200):**
```json
{
  "id": 123,
  "title": "Bal Kabaklı Bebek Çorbası",
  "slug": "bal-kabakli-bebek-corbasi",
  "content": "Full recipe content...",
  "excerpt": "Short description...",
  "image": "https://example.com/image.jpg",
  "prep_time": "15",
  "ingredients": ["1 adet bal kabağı"],
  "instructions": [
    {
      "title": "Adım 1",
      "text": "Bal kabağını yıkayın",
      "tip": "İnce dilimleyin"
    }
  ],
  "nutrition": {
    "calories": "80",
    "protein": "2g",
    "fiber": "3g",
    "vitamins": "A, C, E"
  },
  "allergens": [],
  "age_groups": ["6-12 ay"],
  "diet_types": ["Püre", "Vegan"],
  "video_url": "https://youtube.com/watch?v=...",
  "substitutes": [
    {
      "original": "Bal kabağı",
      "substitute": "Kabak"
    }
  ],
  "is_featured": true,
  "expert": {
    "name": "Dyt. Ayşe Yılmaz",
    "title": "Diyetisyen",
    "approved": true
  },
  "related_recipes": [
    {
      "id": 124,
      "title": "Havuçlu Çorba",
      "slug": "havuclu-corba",
      "image": "https://example.com/image2.jpg"
    }
  ],
  "cross_sell": {
    "url": "https://tariften.com/bebek-tarifleri",
    "title": "Tariften.com'da daha fazla tarif"
  }
}
```

---

### Get Recipes by Age Group
```
GET /kg/v1/recipes/by-age/{age}
```

**Parameters:**
- `age` (string): Age group slug (e.g., "6-12-ay")
- `per_page` (int): Results per page (default: 10)

**Response:** Array of recipes

---

### Get Featured Recipes
```
GET /kg/v1/recipes/featured
```

**Response:** Array of featured recipes (max 5)

---

## Ingredients

### Get All Ingredients
```
GET /kg/v1/ingredients
```

**Query Parameters:**
- `page` (int): Page number (default: 1)
- `per_page` (int): Results per page (default: 20)

**Response (200):**
```json
{
  "ingredients": [
    {
      "id": 456,
      "name": "Havuç",
      "slug": "havuc",
      "description": "Short description...",
      "image": "https://example.com/havuc.jpg",
      "start_age": "6 ay"
    }
  ],
  "total": 120,
  "pages": 6
}
```

---

### Get Ingredient by Slug
```
GET /kg/v1/ingredients/{slug}
```

**Response (200):**
```json
{
  "id": 456,
  "name": "Havuç",
  "slug": "havuc",
  "description": "Full ingredient description...",
  "image": "https://example.com/havuc.jpg",
  "start_age": "6 ay",
  "benefits": "A vitamini açısından zengin...",
  "prep_methods": ["Püre", "Haşlama", "Buhar"],
  "allergy_risk": "Düşük",
  "season": "Tüm Yıl",
  "storage_tips": "Buzdolabında 1 hafta saklanabilir",
  "faq": [
    {
      "question": "Ne zaman verilmeli?",
      "answer": "6 aydan sonra"
    }
  ],
  "related_recipes": [
    {
      "id": 125,
      "title": "Havuçlu Çorba",
      "slug": "havuclu-corba",
      "image": "https://example.com/image.jpg"
    }
  ]
}
```

---

### Search Ingredients
```
GET /kg/v1/ingredients/search?q={query}
```

**Query Parameters:**
- `q` (string): Search query

**Response:** Array of matching ingredients

---

## User Profile

### Get Profile
```
GET /kg/v1/user/profile
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "user_id": 1,
  "email": "user@example.com",
  "name": "User Name",
  "phone": "+90 555 123 4567"
}
```

---

### Update Profile
```
PUT /kg/v1/user/profile
Authorization: Bearer {token}
```

**Body:**
```json
{
  "name": "Updated Name",
  "phone": "+90 555 999 8888"
}
```

**Response (200):**
```json
{
  "message": "Profile updated successfully"
}
```

---

## Children Profiles

### Get Children
```
GET /kg/v1/user/children
Authorization: Bearer {token}
```

**Response (200):**
```json
[
  {
    "id": "unique-id-123",
    "name": "Ali",
    "birth_date": "2024-06-15",
    "allergens": ["süt", "yumurta"],
    "notes": "Laktozsuz süt kullanılmalı"
  }
]
```

---

### Add Child
```
POST /kg/v1/user/children
Authorization: Bearer {token}
```

**Body:**
```json
{
  "name": "Ali",
  "birth_date": "2024-06-15",
  "allergens": ["süt", "yumurta"],
  "notes": "Laktozsuz süt kullanılmalı"
}
```

**Response (201):**
```json
{
  "id": "unique-id-123",
  "name": "Ali",
  "birth_date": "2024-06-15",
  "allergens": ["süt", "yumurta"],
  "notes": "Laktozsuz süt kullanılmalı"
}
```

---

### Update Child
```
PUT /kg/v1/user/children/{id}
Authorization: Bearer {token}
```

**Body:**
```json
{
  "name": "Ali Updated",
  "notes": "New notes"
}
```

**Response (200):**
```json
{
  "message": "Child updated successfully"
}
```

---

### Delete Child
```
DELETE /kg/v1/user/children/{id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Child deleted successfully"
}
```

---

## Favorites

### Get Favorites
```
GET /kg/v1/user/favorites
Authorization: Bearer {token}
```

**Response (200):**
```json
[
  {
    "id": 123,
    "title": "Bal Kabaklı Çorba",
    "slug": "bal-kabakli-corba",
    "image": "https://example.com/image.jpg"
  }
]
```

---

### Add to Favorites
```
POST /kg/v1/user/favorites
Authorization: Bearer {token}
```

**Body:**
```json
{
  "recipe_id": 123
}
```

**Response (201):**
```json
{
  "message": "Recipe added to favorites"
}
```

---

### Remove from Favorites
```
DELETE /kg/v1/user/favorites/{recipe_id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Recipe removed from favorites"
}
```

---

## Shopping List

### Get Shopping List
```
GET /kg/v1/user/shopping-list
Authorization: Bearer {token}
```

**Response (200):**
```json
[
  {
    "id": "item-123",
    "item": "Havuç",
    "quantity": "500g",
    "checked": false
  }
]
```

---

### Add to Shopping List
```
POST /kg/v1/user/shopping-list
Authorization: Bearer {token}
```

**Body:**
```json
{
  "item": "Havuç",
  "quantity": "500g"
}
```

**Response (201):**
```json
{
  "id": "item-123",
  "item": "Havuç",
  "quantity": "500g",
  "checked": false
}
```

---

### Remove from Shopping List
```
DELETE /kg/v1/user/shopping-list/{item_id}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "message": "Item removed from shopping list"
}
```

---

## Search

### Global Search
```
GET /kg/v1/search?q={query}&type={type}&age_group={age}
```

**Query Parameters:**
- `q` (string): Search query (required)
- `type` (string): 'recipe', 'ingredient', or 'all' (default: 'all')
- `age_group` (string): Filter by age group (optional, recipes only)

**Response (200):**
```json
{
  "query": "kabak",
  "type": "all",
  "results": [
    {
      "id": 123,
      "title": "Bal Kabaklı Çorba",
      "slug": "bal-kabakli-corba",
      "type": "recipe",
      "image": "https://example.com/image.jpg",
      "prep_time": "15",
      "age_groups": ["6-12 ay"]
    },
    {
      "id": 456,
      "title": "Bal Kabağı",
      "slug": "bal-kabagi",
      "type": "ingredient",
      "image": "https://example.com/ingredient.jpg",
      "start_age": "6 ay"
    }
  ],
  "total": 2
}
```

---

## Taxonomies

### Age Groups
- `4-6-ay`: 4-6 ay
- `6-12-ay`: 6-12 ay
- `12-24-ay`: 12-24 ay (1-2 yaş)
- `2-yas-uzeri`: 2 yaş üzeri

### Allergens
- Süt
- Yumurta
- Gluten
- Fıstık
- Balık
- Soya
- Kabuklu Deniz Ürünleri
- Fındık
- Susam
- Hardal

### Diet Types
- BLW (Baby-Led Weaning)
- Püre
- Vegan
- Vejetaryen
- Glutensiz
- Şekersiz
- Tuzsuz
- Laktozsuz

---

## Error Responses

All endpoints return standard WordPress REST API error responses:

**400 Bad Request:**
```json
{
  "code": "missing_fields",
  "message": "Email and password are required",
  "data": {
    "status": 400
  }
}
```

**401 Unauthorized:**
```json
{
  "code": "invalid_credentials",
  "message": "Invalid email or password",
  "data": {
    "status": 401
  }
}
```

**404 Not Found:**
```json
{
  "code": "recipe_not_found",
  "message": "Recipe not found",
  "data": {
    "status": 404
  }
}
```

---

## CORS

CORS is enabled for all origins (`Access-Control-Allow-Origin: *`) to support frontend applications running on different domains.

**Allowed Methods:** GET, POST, PUT, DELETE, OPTIONS

**Allowed Headers:** Authorization, Content-Type, X-WP-Nonce

---

## Rate Limiting

Currently no rate limiting is implemented. Consider implementing rate limiting in production.

---

## Additional Notes

1. **JWT Token Expiration:** Tokens expire after 24 hours by default
2. **Pagination:** Most list endpoints support `page` and `per_page` parameters
3. **Data Sanitization:** All inputs are sanitized before saving
4. **WordPress Integration:** Fully compatible with standard WordPress REST API

---

## Support

For issues or questions, please contact the development team.

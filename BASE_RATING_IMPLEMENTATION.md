# Base Rating System and Related Recipes Endpoint Implementation

## Summary

This implementation adds a base/fake rating system for recipes and creates a new related recipes endpoint with proper limit handling.

## Changes Made

### 1. Base Rating System in `RecipeController.php`

#### New Meta Fields
- `_kg_base_rating` - Deterministic base rating value (float, 4.0-4.9)
- `_kg_base_rating_count` - Deterministic base rating count (int, 10-150)

#### `prepare_recipe_data()` Method Updates
- Generates deterministic base rating using formula: `4.0 + (($post_id % 10) / 10)` → Range: 4.0-4.9
- Generates deterministic base count using formula: `10 + ($post_id % 141)` → Range: 10-150
- Returns base rating when no real ratings exist
- Returns real rating when user ratings are available
- Adds `rating` and `rating_count` fields to all recipe responses

#### `rate_recipe()` Method Updates
- Retrieves base rating and base count
- Calculates weighted average: `(base_rating * base_count + real_sum) / (base_count + real_count)`
- Updates total count to include base count
- Preserves individual user ratings in `_kg_ratings` meta field

### 2. Related Recipes Endpoint

#### New REST Route
```
GET /wp-json/kg/v1/recipes/{id}/related?limit=4
```

**Parameters:**
- `limit` (optional): Number of recipes to return (1-10, default: 4)

**Response:** Array of recipe card objects

#### Endpoint Implementation
- Matches recipes by `age-group` and `meal-type` taxonomies (OR relation)
- Fills remaining slots with random recipes if not enough related ones found
- Returns simplified recipe card data using `prepare_recipe_card_data()`

#### `get_related_recipes()` Method
- Now public method (previously private)
- Dual-mode operation:
  - Can be called as REST endpoint with `WP_REST_Request` object
  - Can be called internally with post ID (backward compatible)
- Implements proper error handling for both modes

#### `prepare_recipe_card_data()` Helper Method
New method to prepare simplified recipe card data with:
- Basic recipe info (id, title, slug, image)
- Age group and meal type
- Prep time
- **Rating data with base rating fallback**

## API Endpoints Usage

### Get Recipe with Rating
```
GET /wp-json/kg/v1/recipes/{slug}
```
Response includes `rating` and `rating_count` fields.

### Rate a Recipe
```
POST /wp-json/kg/v1/recipes/{id}/rate
Body: { "rating": 4.5 }
```
Calculates weighted average including base rating.

### Get Related Recipes
```
GET /wp-json/kg/v1/recipes/{id}/related?limit=4
```
Returns up to 4 related recipes with rating data.

## Testing

All tests pass successfully (19/19):
- Base rating generation and fallback
- Weighted average calculation
- Related recipes endpoint registration
- Dual-mode method handling
- Recipe card data preparation

Run tests with:
```bash
php tests/test-base-rating-implementation.php
```

## Notes

- Base ratings are deterministic (same post ID always generates same base rating)
- Real user ratings always take precedence over base ratings when available
- Weighted averaging ensures smooth transition from base to real ratings
- Related recipes endpoint gracefully falls back to random recipes when needed

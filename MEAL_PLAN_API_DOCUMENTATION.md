# Haftalık Beslenme Planı (Smart Meal Planner) - API Documentation

## 📋 Overview

The Meal Plan feature is a smart weekly meal planning system for KidsGourmet that automatically generates age-appropriate, allergy-safe meal plans for children. It analyzes the child's age, allergies, and preferences to create personalized weekly meal plans.

## 🎯 Features

- **Age-based Meal Planning**: Automatically adjusts meal slots based on child's age
  - 6-8 months: 2 meals/day (breakfast + dinner)
  - 9-11 months: 3 meals/day (breakfast + lunch + dinner)
  - 12+ months: 5 meals/day (3 main meals + 2 snacks)
- **Allergy Safety**: Filters out recipes containing child's allergens
- **Smart Recipe Selection**: Ensures variety by avoiding repetitive recipes
- **Shopping List Generation**: Automatically aggregates ingredients from meal plans
- **Plan Management**: Full CRUD operations for meal plans

## 🔌 API Endpoints

### 1. Generate Meal Plan
**POST** `/kg/v1/meal-plans/generate`

Generates a new weekly meal plan for a child.

**Authentication**: Required (JWT)

**Request Body**:
```json
{
  "child_id": "uuid-of-child",
  "week_start": "2026-01-12"
}
```

**Response** (201 Created):
```json
{
  "success": true,
  "plan": {
    "id": "plan-uuid",
    "child_id": "child-uuid",
    "week_start": "2026-01-12",
    "week_end": "2026-01-18",
    "status": "active",
    "days": [
      {
        "date": "2026-01-12",
        "day_name": "Pazartesi",
        "slots": [
          {
            "id": "slot-uuid",
            "slot_type": "breakfast",
            "slot_label": "Kahvaltı",
            "status": "filled",
            "recipe": {
              "id": 123,
              "title": "Avokadolu Omlet",
              "slug": "avokadolu-omlet",
              "image": "https://...",
              "prep_time": "10 dk",
              "age_group": "9-11 Ay",
              "allergens": ["yumurta"]
            },
            "time_range": "07:00-09:00",
            "color_code": "#FFF9C4"
          }
        ]
      }
    ],
    "nutrition_summary": {
      "total_meals": 21,
      "vegetables_servings": 0,
      "protein_servings": 0,
      "grains_servings": 0,
      "new_allergens_introduced": ["yumurta"]
    }
  }
}
```

### 2. Get Active Plan
**GET** `/kg/v1/meal-plans/active?child_id={uuid}`

Retrieves the active meal plan for a specific child.

**Authentication**: Required (JWT)

**Query Parameters**:
- `child_id` (required): UUID of the child

**Response** (200 OK):
```json
{
  "success": true,
  "plan": { /* same structure as generate response */ }
}
```

### 3. Get Plan by ID
**GET** `/kg/v1/meal-plans/{id}`

Retrieves a specific meal plan by its ID.

**Authentication**: Required (JWT)

**Response** (200 OK):
```json
{
  "success": true,
  "plan": { /* same structure as generate response */ }
}
```

### 4. Update Plan
**PUT** `/kg/v1/meal-plans/{id}`

Updates a meal plan's status.

**Authentication**: Required (JWT)

**Request Body**:
```json
{
  "status": "completed"
}
```

**Valid Statuses**: `draft`, `active`, `completed`

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Plan updated successfully"
}
```

### 5. Delete Plan
**DELETE** `/kg/v1/meal-plans/{id}`

Deletes a meal plan.

**Authentication**: Required (JWT)

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Plan deleted successfully"
}
```

### 6. Refresh Slot Recipe
**PUT** `/kg/v1/meal-plans/{id}/slots/{slotId}/refresh`

Replaces the recipe in a slot with a new alternative recipe.

**Authentication**: Required (JWT)

**Response** (200 OK):
```json
{
  "success": true,
  "slot": {
    "id": "slot-uuid",
    "slot_type": "breakfast",
    "slot_label": "Kahvaltı",
    "status": "filled",
    "recipe": { /* recipe details */ },
    "time_range": "07:00-09:00",
    "color_code": "#FFF9C4"
  }
}
```

### 7. Skip Slot
**PUT** `/kg/v1/meal-plans/{id}/slots/{slotId}/skip`

Marks a slot as skipped (e.g., eating out, using ready meal).

**Authentication**: Required (JWT)

**Request Body**:
```json
{
  "skip_reason": "eating_out"
}
```

**Valid Reasons**: `eating_out`, `ready_meal`, `family_meal`, `other`

**Response** (200 OK):
```json
{
  "success": true,
  "message": "Slot skipped successfully"
}
```

### 8. Generate Shopping List
**POST** `/kg/v1/meal-plans/{id}/shopping-list`

Generates a shopping list from the meal plan's recipes.

**Authentication**: Required (JWT)

**Response** (200 OK):
```json
{
  "success": true,
  "items": [
    {
      "ingredient_name": "Avokado",
      "total_amount": 5,
      "unit": "adet",
      "category": "fruits_vegetables",
      "recipes": [
        {
          "id": 123,
          "title": "Avokadolu Omlet",
          "amount": "1 adet"
        },
        {
          "id": 456,
          "title": "Avokado Püresi",
          "amount": "2 adet"
        }
      ],
      "checked": false
    }
  ],
  "total_count": 15
}
```

## 🔒 Business Rules

### 1. Allergy Safety (CRITICAL)
- Recipes containing child's allergens are **NEVER** included in plans
- Allergen taxonomy is checked against child's `allergies` array
- Uses `NOT IN` operator for allergen filtering

### 2. Age Appropriateness
- Recipes are filtered by `age-group` taxonomy
- Age is calculated in months from child's `birth_date`
- Age groups mapped to taxonomy slugs:
  - 0-6 months: `0-6-ay-sadece-sut`
  - 6-8 months: `6-8-ay-baslangic`
  - 9-11 months: `9-11-ay-gecis`
  - 12-18 months: `12-18-ay-pekistirme`
  - 19-36 months: `19-36-ay-cesitlendirme`
  - 36+ months: `3-yas-usti`

### 3. Variety Control
- Same recipe appears max 2 times per week
- Refresh operation excludes current recipe
- Random selection from eligible recipes

### 4. Slot Visibility
- 6-8 months: Only breakfast + dinner
- 9-11 months: breakfast + lunch + dinner
- 12+ months: All 5 meal slots

## 📊 Data Model

### User Meta Key
Plans are stored in user meta: `_kg_meal_plans` (array)

### Plan Structure
```php
[
    'id' => 'uuid-v4',
    'child_id' => 'child-uuid',
    'week_start' => '2026-01-12',
    'week_end' => '2026-01-18',
    'status' => 'active', // draft | active | completed
    'days' => [
        [
            'date' => '2026-01-12',
            'day_name' => 'Pazartesi',
            'slots' => [
                [
                    'id' => 'slot-uuid',
                    'slot_type' => 'breakfast',
                    'slot_label' => 'Kahvaltı',
                    'status' => 'filled', // filled | empty | skipped
                    'recipe_id' => 123,
                    'skip_reason' => null, // eating_out | ready_meal | family_meal | other
                    'time_range' => '07:00-09:00',
                    'color_code' => '#FFF9C4'
                ]
            ]
        ]
    ],
    'created_at' => '2026-01-10T10:00:00+00:00',
    'updated_at' => '2026-01-10T10:00:00+00:00'
]
```

## 🧪 Testing

Run the test suite:
```bash
php test-meal-plan-api.php
```

### Test Coverage
- ✅ Class loading
- ✅ Age-based slot count (2, 3, 5 slots)
- ✅ Plan structure validation
- ✅ Slot structure validation
- ✅ Shopping list generation
- ✅ Turkish day names
- ✅ Nutrition summary

### WordPress Integration Tests
When WordPress is available, additional tests run:
- Recipe post type validation
- REST API route registration
- Allergen filtering with actual taxonomy

## 🔧 Services

### MealPlanGenerator
**Location**: `includes/Services/MealPlanGenerator.php`

**Methods**:
- `generate($child, $week_start)`: Generate new meal plan
- `refresh_slot_recipe($slot_type, $age_group, $allergies, $excluded_ids)`: Get alternative recipe
- `calculate_nutrition_summary($plan)`: Calculate nutrition metrics

### ShoppingListAggregator
**Location**: `includes/Services/ShoppingListAggregator.php`

**Methods**:
- `generate($plan)`: Generate shopping list from plan
- Ingredient categorization:
  - `fruits_vegetables`: Fruits and vegetables
  - `meat_protein`: Meat, fish, eggs, legumes
  - `dairy`: Milk, yogurt, cheese
  - `grains`: Flour, rice, pasta, bread
  - `other`: Everything else

## 🎨 Slot Types

| Slot Type | Turkish Label | Meal Type Slug | Time Range | Color |
|-----------|--------------|----------------|------------|-------|
| breakfast | Kahvaltı | kahvalti | 07:00-09:00 | #FFF9C4 |
| snack_morning | Ara Öğün (Kuşluk) | ara-ogun-kusluk | 10:00-11:00 | #E8F5E9 |
| lunch | Öğle Yemeği | ogle-yemegi | 12:00-13:00 | #DCEDC8 |
| snack_afternoon | Ara Öğün (İkindi) | ara-ogun-ikindi | 15:00-16:00 | #F3E5F5 |
| dinner | Akşam Yemeği | aksam-yemegi | 18:00-19:00 | #FFCC80 |

## ⚠️ Error Responses

### 400 Bad Request
```json
{
  "code": "invalid_date",
  "message": "Invalid week_start date format. Use Y-m-d",
  "data": { "status": 400 }
}
```

### 401 Unauthorized
Returned when JWT token is missing or invalid.

### 404 Not Found
```json
{
  "code": "plan_not_found",
  "message": "Meal plan not found",
  "data": { "status": 404 }
}
```

## 🚀 Usage Example

```javascript
// Generate a meal plan
const response = await fetch('/wp-json/kg/v1/meal-plans/generate', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${jwtToken}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    child_id: 'abc-123-def',
    week_start: '2026-01-12'
  })
});

const { plan } = await response.json();

// Get active plan
const activePlan = await fetch(
  `/wp-json/kg/v1/meal-plans/active?child_id=abc-123-def`,
  {
    headers: { 'Authorization': `Bearer ${jwtToken}` }
  }
).then(r => r.json());

// Refresh a slot
await fetch(
  `/wp-json/kg/v1/meal-plans/${plan.id}/slots/${slotId}/refresh`,
  {
    method: 'PUT',
    headers: { 'Authorization': `Bearer ${jwtToken}` }
  }
);

// Generate shopping list
const shoppingList = await fetch(
  `/wp-json/kg/v1/meal-plans/${plan.id}/shopping-list`,
  {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${jwtToken}` }
  }
).then(r => r.json());
```

## 📝 Notes

- All endpoints require JWT authentication
- Plans are stored in user meta for easy access
- Only one plan can be "active" per child at a time
- Generating a new plan automatically marks previous active plans as "completed"
- Shopping list intelligently combines duplicate ingredients across recipes

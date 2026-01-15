# Meal Plan API - Quick Reference

## 🚀 Quick Start

### Generate a Meal Plan
```bash
curl -X POST https://kidsgourmet.com/wp-json/kg/v1/meal-plans/generate \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "child_id": "abc-123-def",
    "week_start": "2026-01-12"
  }'
```

### Get Active Plan
```bash
curl https://kidsgourmet.com/wp-json/kg/v1/meal-plans/active?child_id=abc-123-def \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Refresh a Recipe
```bash
curl -X PUT https://kidsgourmet.com/wp-json/kg/v1/meal-plans/{plan_id}/slots/{slot_id}/refresh \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Generate Shopping List
```bash
curl -X POST https://kidsgourmet.com/wp-json/kg/v1/meal-plans/{plan_id}/shopping-list \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 📊 Data Structure

### Child Profile (Required)
```javascript
{
  "id": "uuid",
  "birth_date": "2025-06-15",  // Y-m-d format
  "allergies": ["yumurta", "sut"]  // Allergen slugs
}
```

### Plan Response
```javascript
{
  "success": true,
  "plan": {
    "id": "plan-uuid",
    "child_id": "child-uuid",
    "week_start": "2026-01-12",
    "week_end": "2026-01-18",
    "status": "active",
    "days": [/* 7 days */],
    "nutrition_summary": {/* metrics */}
  }
}
```

## 🎯 Key Features

### Age-Based Slots
- **6-8 months**: 2 meals (breakfast, dinner)
- **9-11 months**: 3 meals (breakfast, lunch, dinner)
- **12+ months**: 5 meals (3 main + 2 snacks)

### Safety Rules
- ✅ Allergens are NEVER included
- ✅ Only age-appropriate recipes
- ✅ Same recipe max 2x per week

## 🔑 Authentication

All endpoints require JWT token:
```
Authorization: Bearer YOUR_JWT_TOKEN
```

Get token from login endpoint:
```bash
POST /wp-json/kg/v1/auth/login
```

## 📝 Slot Types

| Type | Turkish | Time |
|------|---------|------|
| breakfast | Kahvaltı | 07:00-09:00 |
| snack_morning | Ara Öğün (Kuşluk) | 10:00-11:00 |
| lunch | Öğle Yemeği | 12:00-13:00 |
| snack_afternoon | Ara Öğün (İkindi) | 15:00-16:00 |
| dinner | Akşam Yemeği | 18:00-19:00 |

## 🛠 Operations

### CRUD
- **Create**: `POST /meal-plans/generate`
- **Read**: `GET /meal-plans/{id}` or `GET /meal-plans/active`
- **Update**: `PUT /meal-plans/{id}`
- **Delete**: `DELETE /meal-plans/{id}`

### Slot Management
- **Refresh**: `PUT /meal-plans/{id}/slots/{slotId}/refresh`
- **Skip**: `PUT /meal-plans/{id}/slots/{slotId}/skip`

### Shopping List
- **Generate**: `POST /meal-plans/{id}/shopping-list`

## ⚠️ Common Errors

### 400 Bad Request
```json
{"code": "invalid_date", "message": "Invalid week_start date format. Use Y-m-d"}
```

### 401 Unauthorized
Missing or invalid JWT token.

### 404 Not Found
```json
{"code": "plan_not_found", "message": "Meal plan not found"}
```

## 🧪 Testing

Run the test suite:
```bash
php test-meal-plan-api.php
```

Expected: 14/14 tests passing ✓

## 📚 Full Documentation

See `MEAL_PLAN_API_DOCUMENTATION.md` for complete API reference.

## 💡 Tips

1. Always provide `week_start` as Monday
2. Store `plan_id` after generation
3. Use `active` endpoint for current plan
4. Refresh slots when user wants alternatives
5. Generate shopping list before grocery shopping

## 🔗 Related Endpoints

- Child Management: `/kg/v1/user/children`
- User Profile: `/kg/v1/user/profile`
- Recipes: `/kg/v1/recipes`

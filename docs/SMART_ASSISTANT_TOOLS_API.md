# Smart Assistant Tools API Documentation

This documentation covers the 6 new smart assistant tools added to the KidsGourmet API.

## Table of Contents
1. [Ingredient Guide](#1-ingredient-guide)
2. [Solid Food Readiness Check](#2-solid-food-readiness-check)
3. [Food Suitability Check](#3-food-suitability-check)
4. [Allergen Introduction Planner](#4-allergen-introduction-planner)
5. [Food Trial Calendar](#5-food-trial-calendar)
6. [Water Calculator](#6-water-calculator)

---

## 1. Ingredient Guide

**Slug:** `ek-gida-rehberi`  
**Tool Type:** `ingredient_guide`  
**Description:** Quick guide to check if an ingredient is suitable for a child's age.

### Endpoint
```
GET /wp-json/kg/v1/tools/ingredient-guide/check
```

### Parameters
- `ingredient_slug` (string, required) - Ingredient slug (e.g., "avokado")
- `child_age_months` (integer, required) - Child's age in months

### Example Request
```bash
curl -X GET "https://example.com/wp-json/kg/v1/tools/ingredient-guide/check?ingredient_slug=avokado&child_age_months=6"
```

### Example Response
```json
{
  "ingredient": {
    "id": 123,
    "name": "Avokado",
    "slug": "avokado",
    "image": "https://example.com/wp-content/uploads/avokado.jpg"
  },
  "is_suitable": true,
  "start_age_months": 6,
  "allergy_risk": "Düşük",
  "warnings": [],
  "preparation_method": "6-9 ay: Ezilmiş/püre | 9+ ay: Küçük parçalar",
  "prep_by_age": [
    {
      "age_range": "6-9 ay",
      "method": "Ezilmiş veya püre"
    }
  ],
  "tips": "İlk denemede yarım çay kaşığı ile başlayın",
  "pairings": [
    {
      "emoji": "🍌",
      "name": "Muz"
    }
  ],
  "related_recipes": [
    {
      "id": 456,
      "title": "Avokado Püresi",
      "slug": "avokado-puresi",
      "image": "https://example.com/wp-content/uploads/recipe.jpg"
    }
  ]
}
```

---

## 2. Solid Food Readiness Check

**Slug:** `ek-gidaya-baslama`  
**Tool Type:** `solid_food_readiness`  
**Description:** Assessment tool to determine if a baby is ready for solid foods based on WHO/AAP standards.

### Get Configuration
```
GET /wp-json/kg/v1/tools/solid-food-readiness/config
```

Returns the test configuration with questions and result buckets.

### Submit Test
```
POST /wp-json/kg/v1/tools/solid-food-readiness/submit
```

### Request Body
```json
{
  "answers": {
    "q1_sitting": "sitting_yes",
    "q2_tongue_reflex": "reflex_gone",
    "q3_interest": "interest_high",
    "q4_hand_mouth": "coordination_yes",
    "q5_age": "age_6plus",
    "q6_weight": "weight_doubled"
  },
  "child_id": "uuid-optional"
}
```

### Example Response
```json
{
  "score": 95.5,
  "result": {
    "id": "ready",
    "min_score": 80,
    "max_score": 100,
    "title": "Ek Gıdaya Hazır Görünüyor",
    "description": "Bebeğiniz tüm önemli gelişim göstergelerini karşılıyor.",
    "color": "green",
    "icon": "✓",
    "recommendations": [
      "Yumuşak, kolay sindirilebilir gıdalarla başlayın",
      "İlk hafta tek bir gıda deneyin (3 gün kuralı)"
    ]
  },
  "timestamp": "2026-01-15T10:30:00+00:00"
}
```

### Questions
1. **Sitting Support** - Can baby sit with support?
2. **Tongue Reflex** - Has tongue thrust reflex diminished?
3. **Food Interest** - Does baby show interest in food?
4. **Hand-Mouth Coordination** - Can baby bring objects to mouth?
5. **Age** - Is baby at least 4-6 months old?
6. **Weight** - Has baby doubled birth weight?

### Result Buckets
- **ready** (80-100): Baby is ready for solid foods
- **almost_ready** (60-79): Baby needs a bit more time
- **not_yet** (0-59): Baby is not ready yet

---

## 3. Food Suitability Check

**Slug:** `bu-gida-verilir-mi`  
**Tool Type:** `food_check`  
**Description:** Quick decision tool to check if a specific food is suitable for a child's age.

### Endpoint
```
GET /wp-json/kg/v1/tools/food-check
```

### Parameters
- `query` (string, required) - Food name to search
- `child_age_months` (integer, required) - Child's age in months

### Example Request
```bash
curl -X GET "https://example.com/wp-json/kg/v1/tools/food-check?query=bal&child_age_months=10"
```

### Example Response (Not Suitable)
```json
{
  "query": "bal",
  "found": false,
  "ingredient": null,
  "verdict": {
    "status": "not_suitable",
    "status_color": "red",
    "message": "Bal 12 aydan önce verilmemelidir",
    "reason": "Botulizm riski nedeniyle 1 yaşından önce kesinlikle verilmemelidir.",
    "recommended_age": "12+ ay"
  },
  "alternatives": [
    {
      "id": 789,
      "name": "Muz",
      "slug": "muz",
      "image": "...",
      "start_age": 6
    }
  ]
}
```

### Hardcoded Safety Rules
The following foods have special hardcoded rules beyond the ingredient database:

1. **Honey (Bal)** - Prohibited before 12 months (botulism risk)
2. **Whole Nuts** - Prohibited before 48 months (choking hazard)
3. **Unpasteurized Dairy** - Prohibited before 12 months (infection risk)
4. **Added Salt** - Not recommended before 12 months (kidney development)
5. **Added Sugar** - Not recommended before 12 months (health reasons)
6. **Choking Hazards** - Seeds, popcorn prohibited before 48 months

### Status Types
- `suitable` - Food is appropriate for the child's age
- `caution` - Can be given with caution (allergen risk)
- `not_suitable` - Should not be given at this age
- `unknown` - No data available in database

---

## 4. Allergen Introduction Planner

**Slug:** `alerjen-planlayici`  
**Tool Type:** `allergen_planner`  
**Description:** Step-by-step plan for safely introducing common allergens based on WHO/AAP guidelines.

### Get Configuration
```
GET /wp-json/kg/v1/tools/allergen-planner/config
```

Returns list of available allergens and warning signs.

### Generate Introduction Plan
```
POST /wp-json/kg/v1/tools/allergen-planner/generate
```

### Request Body
```json
{
  "allergen_id": "yumurta",
  "child_id": "uuid-optional",
  "previous_reactions": []
}
```

### Available Allergens
- `yumurta` - Eggs (5-day plan)
- `sut` - Cow's Milk (5-day plan)
- `fistik` - Peanuts (7-day plan)
- `balik` - Fish (5-day plan)
- `buday` - Wheat/Gluten (5-day plan)
- `soya` - Soy (5-day plan)
- `findik` - Hazelnuts (7-day plan)
- `susam` - Sesame (5-day plan)

### Example Response
```json
{
  "allergen": {
    "id": "yumurta",
    "name": "Yumurta",
    "risk_level": "Yüksek"
  },
  "introduction_plan": {
    "total_days": 5,
    "days": [
      {
        "day": 1,
        "amount": "1/4 çay kaşığı",
        "form": "Tam pişmiş yumurta sarısı",
        "time": "Sabah (reaksiyon takibi için)",
        "notes": "Diğer yeni gıda vermekten kaçının"
      },
      {
        "day": 2,
        "amount": "1/2 çay kaşığı",
        "form": "Tam pişmiş yumurta sarısı",
        "time": "Sabah",
        "notes": "Önceki gün reaksiyon yoksa miktarı artırın"
      }
    ]
  },
  "warning_signs": [
    "Cilt döküntüsü, kızarıklık",
    "Kusma veya ishal",
    "Nefes almada zorluk (ACİL)"
  ],
  "emergency_signs": [
    "Nefes almada ciddi zorluk",
    "Yüz veya dudaklarda şişme",
    "**ACİL DURUM: 112 ARAYIN**"
  ],
  "when_to_stop": [
    "Herhangi bir alerji belirtisi gözlemlerseniz hemen durdurun",
    "Reaksiyon gözlenmesi durumunda en az 3 ay bekleyin"
  ],
  "success_criteria": "5 gün boyunca hiçbir reaksiyon gözlenmezse, bu alerjeni güvenle tüketebilir",
  "related_ingredients": [
    {
      "name": "tavuk",
      "warning": "Çapraz alerji riski bulunmaktadır"
    }
  ]
}
```

---

## 5. Food Trial Calendar

**Slug:** `besin-deneme-takvimi`  
**Tool Type:** `food_trial_calendar`  
**Description:** Track which foods have been tried and their outcomes.

**Authentication:** Required for all endpoints

### List Food Trials
```
GET /wp-json/kg/v1/tools/food-trials
```

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Query Parameters:**
- `child_id` (string, optional) - Filter by child ID

### Add Food Trial
```
POST /wp-json/kg/v1/tools/food-trials
```

**Request Body:**
```json
{
  "child_id": "uuid-123",
  "ingredient_id": 456,
  "trial_date": "2026-01-15",
  "result": "success",
  "reaction_notes": "",
  "amount": "1 çay kaşığı",
  "form": "Püre"
}
```

**Result Types:**
- `success` - No reaction, food accepted
- `mild_reaction` - Minor reaction (retry after 2 weeks)
- `reaction` - Noticeable reaction (retry after 1 month)
- `severe_reaction` - Severe reaction (retry after 3 months, consult doctor)

### Update Food Trial
```
PUT /wp-json/kg/v1/tools/food-trials/{id}
```

### Delete Food Trial
```
DELETE /wp-json/kg/v1/tools/food-trials/{id}
```

### Get Statistics
```
GET /wp-json/kg/v1/tools/food-trials/stats?child_id=uuid-123
```

**Response:**
```json
{
  "total_trials": 15,
  "success": 12,
  "mild_reaction": 2,
  "reaction": 1,
  "severe_reaction": 0,
  "recent_trials": [...]
}
```

---

## 6. Water Calculator

**Slug:** `su-ihtiyaci`  
**Tool Type:** `water_calculator`  
**Description:** Calculate daily fluid needs based on baby's weight using Holliday-Segar formula.

### Endpoint
```
GET /wp-json/kg/v1/tools/water-calculator
```

### Parameters
- `weight_kg` (float, required) - Baby's weight in kg
- `age_months` (integer, required) - Baby's age in months
- `weather` (string, optional) - Weather condition: `hot`, `normal`, `cold` (default: `normal`)
- `is_breastfed` (boolean, optional) - Is baby exclusively breastfed? (default: `false`)

### Example Request
```bash
curl -X GET "https://example.com/wp-json/kg/v1/tools/water-calculator?weight_kg=8.5&age_months=7&weather=hot&is_breastfed=true"
```

### Example Response
```json
{
  "daily_fluid_need_ml": 978,
  "breakdown": {
    "from_breast_milk_formula": 734,
    "from_food": 196,
    "from_water": 49
  },
  "notes": [
    "6-12 ay arası bebekler için su ihtiyacının çoğu anne sütü/mamadan karşılanır",
    "Ek gıda ile birlikte az miktarda su verilebilir",
    "Sıcak havalarda %10-20 artış önerilir"
  ],
  "formula": "Holliday-Segar: İlk 10 kg için 100 ml/kg/gün",
  "warning": null
}
```

### Holliday-Segar Formula
The calculator uses the internationally recognized Holliday-Segar formula:

- **First 10 kg:** 100 ml/kg/day
- **10-20 kg:** 1000 ml + 50 ml/kg (for each kg above 10)
- **20+ kg:** 1500 ml + 20 ml/kg (for each kg above 20)

### Weather Adjustments
- **Hot weather:** +15% increase
- **Normal weather:** No adjustment
- **Cold weather:** -5% decrease

### Age-Specific Warnings
- **Under 6 months:** Water should NOT be given. All fluids from breast milk/formula.
- **6-12 months:** Majority from breast milk/formula, small amounts of water with solid foods.
- **12+ months:** Balanced fluid intake from water, milk, and foods.

---

## Authentication

Most endpoints are **public** and do not require authentication. However:

### Public Endpoints (No Auth Required)
- Ingredient Guide Check
- Solid Food Readiness Config/Submit
- Food Check
- Allergen Planner Config/Generate
- Water Calculator

### Protected Endpoints (Auth Required)
- All Food Trial endpoints (GET, POST, PUT, DELETE)

### Optional Authentication
Some public endpoints (like Solid Food Readiness Submit and Allergen Planner Generate) can optionally save results to user profile if authenticated.

**Authentication Method:** JWT Bearer Token

```
Authorization: Bearer {your_jwt_token}
```

---

## Error Responses

All endpoints return consistent error responses:

### Example Error Response
```json
{
  "code": "invalid_age",
  "message": "Geçerli bir yaş değeri giriniz",
  "data": {
    "status": 400
  }
}
```

### Common Error Codes
- `missing_ingredient_slug` - Required parameter missing
- `invalid_age` - Age value is invalid
- `ingredient_not_found` - Ingredient not found in database
- `unauthorized` - Authentication required
- `invalid_token` - JWT token is invalid
- `invalid_allergen` - Allergen ID not recognized
- `no_answers` - No answers provided for test

---

## Data Storage

### User Meta Keys
The following user meta keys are used to store tool results:

- `_kg_food_trials` - Array of food trial entries
- `_kg_solid_food_readiness_results` - Array of readiness test results
- `_kg_allergen_plans` - Array of saved allergen introduction plans

All data is stored as JSON-serialized arrays in WordPress user meta.

---

## Rate Limiting

Currently, no rate limiting is implemented. Consider implementing rate limiting for production use, especially for public endpoints.

---

## Versioning

All endpoints are under the `/kg/v1/` namespace. Future versions may introduce `/kg/v2/` for breaking changes while maintaining backward compatibility.

---

## Support

For questions or issues, please contact the development team or create an issue in the repository.

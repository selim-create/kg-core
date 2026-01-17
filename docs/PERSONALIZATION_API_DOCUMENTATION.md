# Personalization API Documentation

## Overview
KidsGourmet kişiselleştirilmiş öneri ve güvenlik API'si, çocuk profil verilerine göre kişiselleştirilmiş tarif önerileri, güvenlik kontrolleri, beslenme takibi ve besin tanıtım yönetimi sağlar.

## Authentication
Tüm endpoint'ler JWT token ile kimlik doğrulama gerektirir.

```http
Authorization: Bearer <JWT_TOKEN>
```

## Base URL
```
/wp-json/kg/v1
```

---

## Recommendation Endpoints

### 1. Dashboard Recommendations
Çocuk için günlük dashboard önerileri alır.

**Endpoint:** `GET /recommendations/dashboard`

**Parameters:**
- `child_id` (required, string): Child UUID

**Response:**
```json
{
  "today": [
    {
      "recipe_id": 123,
      "title": "Havuç Püresi",
      "slug": "havuc-puresi",
      "image": "https://...",
      "score": 92.5
    }
  ],
  "weekly_plan_status": {
    "has_plan": true,
    "completion": 85,
    "filled_slots": 17,
    "total_slots": 20
  },
  "nutrition_summary": {
    "protein_servings": 10,
    "vegetable_servings": 12,
    "fruit_servings": 8,
    "grains_servings": 9,
    "dairy_servings": 7,
    "iron_rich_count": 5,
    "variety_score": 78
  },
  "alerts": [
    {
      "type": "age",
      "severity": "info",
      "message": "..."
    }
  ]
}
```

### 2. Personalized Recipe Recommendations
Kişiselleştirilmiş tarif listesi alır.

**Endpoint:** `GET /recommendations/recipes`

**Parameters:**
- `child_id` (required, string): Child UUID
- `limit` (optional, int, default: 10): Number of recommendations
- `category` (optional, string): Recipe category slug
- `include_scores` (optional, boolean, default: false): Include detailed scoring

**Response:**
```json
{
  "child_id": "uuid-...",
  "recommendations": [
    {
      "recipe_id": 123,
      "title": "Havuç Püresi",
      "slug": "havuc-puresi",
      "image": "https://...",
      "score": 92.5,
      "detailed_scores": {
        "age_compatibility": 100,
        "allergen_safety": 100,
        "nutritional_balance": 85,
        "feeding_style_match": 90,
        "seasonal_relevance": 95,
        "variety_bonus": 80,
        "user_preferences": 70,
        "total": 92.5
      }
    }
  ],
  "count": 10
}
```

**Scoring Factors:**
- `age_compatibility` (0-100): Yaş grubu uyumu
- `allergen_safety` (0-100): Alerjen güvenliği (alerjik malzeme varsa 0)
- `nutritional_balance` (0-100): Haftalık beslenme dengesine katkı
- `feeding_style_match` (0-100): BLW/püre/karma uyumu
- `seasonal_relevance` (0-100): Mevsimsel uygunluk
- `variety_bonus` (0-100): Son 7 günde yenmemiş besinler için bonus
- `user_preferences` (0-100): Favori kategoriler, geçmiş tüketim

### 3. Similar Safe Recipes
Benzer güvenli tarifler önerir.

**Endpoint:** `GET /recommendations/similar/{recipe_id}`

**Parameters:**
- `recipe_id` (required, int, path): Reference recipe ID
- `child_id` (required, string): Child UUID

**Response:**
```json
{
  "recipe_id": 123,
  "similar_recipes": [
    {
      "recipe_id": 456,
      "title": "Kabak Püresi",
      "slug": "kabak-puresi",
      "image": "https://...",
      "similarity_score": 88.5
    }
  ],
  "count": 10
}
```

---

## Safety Check Endpoints

### 1. Check Recipe Safety
Tarif güvenlik kontrolü yapar.

**Endpoint:** `POST /safety/check-recipe`

**Request Body:**
```json
{
  "recipe_id": 123,
  "child_id": "uuid-..."
}
```

**Response:**
```json
{
  "recipe_id": 123,
  "is_safe": false,
  "safety_score": 0,
  "alerts": [
    {
      "type": "allergy",
      "severity": "critical",
      "message": "KESİNLİKLE VERMEYİN! Bu tarif yumurta içeriyor.",
      "ingredient": "Yumurta",
      "alternative": "Yumurta içermeyen benzer tarifler aramayı deneyin."
    }
  ],
  "alternatives": [
    {
      "recipe_id": 456,
      "title": "Kabak Püresi",
      "slug": "kabak-puresi",
      "image": "https://..."
    }
  ],
  "checked_at": "2026-01-17T12:00:00+00:00"
}
```

**Alert Types:**
- `allergy`: Alerji uyarısı
- `age`: Yaş uyumsuzluğu
- `forbidden`: Yasak malzeme (bal, tam fındık, vb.)
- `nutrition`: Beslenme endişesi (tuz, şeker, vb.)

**Alert Severity:**
- `critical`: Kesinlikle verilmemeli
- `warning`: Dikkatli olunmalı
- `info`: Bilgilendirme

### 2. Check Ingredient Safety
Malzeme güvenlik kontrolü yapar.

**Endpoint:** `POST /safety/check-ingredient`

**Request Body:**
```json
{
  "ingredient_id": 789,
  "child_id": "uuid-..."
}
```

**Response:**
```json
{
  "ingredient_id": 789,
  "ingredient_name": "Yumurta",
  "is_safe": false,
  "is_introduced": false,
  "alerts": [
    {
      "type": "allergy",
      "severity": "critical",
      "message": "Bu malzeme yumurta içeriyor.",
      "ingredient": "Yumurta"
    }
  ],
  "allergy_risk": "Yüksek",
  "start_age": 6
}
```

### 3. Batch Safety Check
Birden fazla tarif için toplu güvenlik kontrolü yapar.

**Endpoint:** `POST /safety/batch-check`

**Request Body:**
```json
{
  "recipe_ids": [123, 456, 789],
  "child_id": "uuid-..."
}
```

**Response:**
```json
{
  "child_id": "uuid-...",
  "checked_count": 3,
  "results": [
    {
      "recipe_id": 123,
      "is_safe": true,
      "safety_score": 100,
      "critical_alerts": []
    },
    {
      "recipe_id": 456,
      "is_safe": false,
      "safety_score": 0,
      "critical_alerts": [
        {
          "type": "allergy",
          "severity": "critical",
          "message": "..."
        }
      ]
    }
  ]
}
```

---

## Nutrition Tracking Endpoints

### 1. Weekly Nutrition Summary
Haftalık beslenme özeti alır.

**Endpoint:** `GET /nutrition/weekly-summary`

**Parameters:**
- `child_id` (required, string): Child UUID
- `week_start` (optional, string, format: Y-m-d): Week start date (default: current week Monday)

**Response:**
```json
{
  "child_id": "uuid-...",
  "week_start": "2026-01-13",
  "week_end": "2026-01-19",
  "summary": {
    "protein_servings": 10,
    "vegetable_servings": 12,
    "fruit_servings": 8,
    "grains_servings": 9,
    "dairy_servings": 7,
    "iron_rich_count": 5,
    "new_foods_introduced": ["Havuç", "Kabak"],
    "allergen_exposures": ["Yumurta", "Süt"],
    "variety_score": 78
  }
}
```

### 2. Missing Nutrients
Eksik besinleri tespit eder.

**Endpoint:** `GET /nutrition/missing-nutrients`

**Parameters:**
- `child_id` (required, string): Child UUID
- `week_start` (optional, string): Week start date

**Response:**
```json
{
  "child_id": "uuid-...",
  "week_start": "2026-01-13",
  "missing_nutrients": [
    {
      "nutrient": "Protein",
      "current": 7,
      "recommended": 10,
      "deficit": 3,
      "sources": ["Tavuk", "Balık", "Yumurta"],
      "severity": "medium"
    }
  ],
  "count": 2
}
```

**Deficit Severity:**
- `low`: 80%+ karşılanmış
- `medium`: 50-80% karşılanmış
- `high`: <50% karşılanmış

### 3. Variety Analysis
Besin çeşitliliği analizi yapar.

**Endpoint:** `GET /nutrition/variety-analysis`

**Parameters:**
- `child_id` (required, string): Child UUID
- `days` (optional, int, default: 7): Number of days to analyze

**Response:**
```json
{
  "child_id": "uuid-...",
  "analysis": {
    "variety_score": 78,
    "unique_recipes": 12,
    "unique_ingredients": 25,
    "repeated_recipes": [
      {
        "recipe_id": 123,
        "title": "Havuç Püresi",
        "count": 4
      }
    ],
    "days_analyzed": 7,
    "recommendation": "İyi gidiyorsunuz, ancak biraz daha çeşitlilik eklenebilir."
  }
}
```

### 4. Allergen Exposure Log
Alerjen maruziyet geçmişini gösterir.

**Endpoint:** `GET /nutrition/allergen-log`

**Parameters:**
- `child_id` (required, string): Child UUID
- `days` (optional, int, default: 30): Number of days to look back

**Response:**
```json
{
  "child_id": "uuid-...",
  "days": 30,
  "exposures": [
    {
      "date": "2026-01-15",
      "allergen": "Yumurta",
      "recipe_id": 123,
      "recipe_title": "Yumurtalı Ekmek"
    }
  ],
  "count": 5
}
```

---

## Food Introduction Endpoints

### 1. Suggested Foods
Yaşa göre önerilen besinler.

**Endpoint:** `GET /food-introduction/suggested`

**Parameters:**
- `child_id` (required, string): Child UUID

**Response:**
```json
{
  "child_id": "uuid-...",
  "suggestions": {
    "age_in_months": 8,
    "foods": [
      {
        "ingredient_id": 123,
        "name": "Havuç",
        "start_age": 6,
        "allergy_risk": "Düşük",
        "preparation_tips": "Haşlayıp püre yapın.",
        "introduction_guide": {
          "first_portion": "1-2 çay kaşığı",
          "waiting_period": "3 gün",
          "best_time": "Sabah veya öğle",
          "tips": [
            "Normal porsiyon ile başlayabilirsiniz.",
            "Yine de reaksiyonları takip edin."
          ]
        }
      }
    ]
  }
}
```

### 2. Introduction History
Besin tanıtım geçmişi.

**Endpoint:** `GET /food-introduction/history`

**Parameters:**
- `child_id` (required, string): Child UUID

**Response:**
```json
{
  "child_id": "uuid-...",
  "history": [
    {
      "food": "Havuç",
      "ingredient_id": 123,
      "introduced_date": "2026-01-10",
      "reaction": "none",
      "notes": "İlk deneme başarılı"
    }
  ],
  "count": 5
}
```

### 3. Log Food Introduction
Yeni besin tanıtımını kaydet.

**Endpoint:** `POST /food-introduction/log`

**Request Body:**
```json
{
  "child_id": "uuid-...",
  "food": "Havuç",
  "reaction": "none",
  "notes": "İlk deneme başarılı",
  "date": "2026-01-17"
}
```

**Reaction Types:**
- `none`: Reaksiyon yok
- `mild_rash`: Hafif döküntü
- `vomiting`: Kusma
- `diarrhea`: İshal
- `other`: Diğer

**Response:**
```json
{
  "success": true,
  "food": "Havuç",
  "reaction": "none",
  "date": "2026-01-17"
}
```

### 4. Next Food Suggestion
Öncelikli besin önerileri.

**Endpoint:** `GET /food-introduction/next-suggestion`

**Parameters:**
- `child_id` (required, string): Child UUID

**Response:**
```json
{
  "child_id": "uuid-...",
  "next_suggestions": {
    "age_in_months": 8,
    "introduced_count": 5,
    "suggestions": [
      {
        "ingredient_id": 456,
        "name": "Ispanak",
        "allergy_risk": "Düşük",
        "start_age": 6,
        "priority": 85,
        "introduction_guide": {
          "first_portion": "1-2 çay kaşığı",
          "waiting_period": "3 gün",
          "best_time": "Sabah veya öğle",
          "tips": ["..."]
        }
      }
    ],
    "message": "İyi gidiyorsunuz! Besin çeşitliliğini artırmaya devam edin."
  }
}
```

---

## User Dashboard Endpoint

### Dashboard with Recommendations
Kullanıcı dashboard'u (çocuk seçildiyse önerilerle birlikte).

**Endpoint:** `GET /user/dashboard`

**Parameters:**
- `child_id` (optional, string): Child UUID for personalized recommendations

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "Ayşe Yılmaz",
    "email": "ayse@example.com"
  },
  "children": [
    {
      "id": "uuid-...",
      "name": "Ali",
      "birth_date": "2025-06-15",
      "allergies": ["yumurta"],
      "feeding_style": "blw"
    }
  ],
  "recommendations": {
    "today": [...],
    "weekly_plan_status": {...},
    "nutrition_summary": {...},
    "alerts": [...]
  }
}
```

---

## Error Responses

### 400 Bad Request
```json
{
  "code": "missing_fields",
  "message": "Child ID is required",
  "data": {
    "status": 400
  }
}
```

### 401 Unauthorized
```json
{
  "code": "unauthorized",
  "message": "Authentication required",
  "data": {
    "status": 401
  }
}
```

### 404 Not Found
```json
{
  "code": "child_not_found",
  "message": "Child not found or access denied",
  "data": {
    "status": 404
  }
}
```

---

## Rate Limiting
- **Limit:** 100 requests/minute/user
- **Headers:**
  - `X-RateLimit-Limit`: 100
  - `X-RateLimit-Remaining`: 95
  - `X-RateLimit-Reset`: 1642425600

---

## WHO/AAP Compliance
Tüm besin tanıtım önerileri WHO (Dünya Sağlık Örgütü) ve AAP (Amerikan Pediatri Akademisi) rehberlerine uygundur.

## KVKK Compliance
- Minimum veri toplama prensibi uygulanır
- Çocuk verileri şifrelenir
- Ebeveyn onayı gereklidir

---

## Usage Examples

### Example 1: Get Daily Recommendations
```javascript
const response = await fetch('/wp-json/kg/v1/recommendations/dashboard?child_id=uuid-123', {
  headers: {
    'Authorization': 'Bearer <token>'
  }
});
const dashboard = await response.json();
console.log(dashboard.today); // Daily recipe recommendations
```

### Example 2: Check Recipe Safety
```javascript
const response = await fetch('/wp-json/kg/v1/safety/check-recipe', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer <token>',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    recipe_id: 123,
    child_id: 'uuid-123'
  })
});
const safety = await response.json();
if (!safety.is_safe) {
  console.warn('Safety alerts:', safety.alerts);
}
```

### Example 3: Log New Food Introduction
```javascript
const response = await fetch('/wp-json/kg/v1/food-introduction/log', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer <token>',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    child_id: 'uuid-123',
    food: 'Havuç',
    reaction: 'none',
    notes: 'İlk deneme başarılı',
    date: '2026-01-17'
  })
});
const result = await response.json();
```

---

## Support
Sorularınız için: support@kidsgourmet.com

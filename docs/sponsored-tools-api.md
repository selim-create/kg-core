# Sponsored Tools API Documentation

## Overview
This document describes the API endpoints for the 5 new sponsored tools:
1. Bath Planner (Banyo Rutini Planlayıcı)
2. Hygiene Calculator (Günlük Hijyen İhtiyacı Hesaplayıcı)
3. Diaper Calculator (Akıllı Bez Hesaplayıcı)
4. Air Quality Guide (Hava Kalitesi Rehberi)
5. Stain Encyclopedia (Leke Ansiklopedisi)

## Base URL
All endpoints use the base URL: `/wp-json/kg/v1`

## Authentication
All endpoints are public (`permission_callback: __return_true`)

## Sponsor Data Structure
All tool responses include sponsor data when the tool is marked as sponsored:

```json
{
  "sponsor_data": {
    "is_sponsored": true,
    "sponsor_name": "Brand Name",
    "sponsor_url": "https://brand.com",
    "sponsor_logo": {
      "id": 123,
      "url": "https://example.com/logo.png"
    },
    "sponsor_light_logo": {
      "id": 124,
      "url": "https://example.com/logo-light.png"
    },
    "sponsor_tagline": "Brand tagline",
    "sponsor_cta": {
      "text": "Ürünü İncele",
      "url": "https://brand.com/product"
    },
    "gam_impression_url": "https://ad.doubleclick.net/impression",
    "gam_click_url": "https://ad.doubleclick.net/click"
  }
}
```

---

## 1. Bath Planner (Banyo Rutini Planlayıcı)

### Get Configuration
**GET** `/tools/bath-planner/config`

Returns bath planner configuration including age groups, bath types, and sponsor data.

**Response:**
```json
{
  "tool_info": {
    "id": 1,
    "title": "Banyo Rutini Planlayıcı",
    "description": "Tool description",
    "icon": "fa-bath"
  },
  "age_groups": [
    {
      "id": "0-3months",
      "label": "0-3 Ay",
      "frequency": "2-3 kez/hafta"
    }
  ],
  "bath_types": [
    {
      "id": "sponge",
      "label": "Sünger Banyosu",
      "suitable_for": "0-3months"
    }
  ],
  "sponsor_data": {...}
}
```

### Generate Bath Routine
**POST** `/tools/bath-planner/generate`

Generates a personalized bath routine plan.

**Request Body:**
```json
{
  "child_age_months": 6,
  "skin_type": "sensitive",
  "activity_level": "moderate"
}
```

**Parameters:**
- `child_age_months` (int, required): Child's age in months
- `skin_type` (string, optional): "normal", "dry", "sensitive" (default: "normal")
- `activity_level` (string, optional): "low", "moderate", "high" (default: "moderate")

**Response:**
```json
{
  "recommended_frequency": "4-5 kez/hafta",
  "products": [
    {
      "type": "Şampuan",
      "recommendation": "Parfümsüz, hipoalerjenik bebek şampuanı"
    }
  ],
  "routine": [
    {
      "step": 1,
      "title": "Hazırlık",
      "description": "Su sıcaklığını kontrol edin..."
    }
  ],
  "tips": [
    "Bebeği banyoda asla yalnız bırakmayın"
  ],
  "sponsor_data": {...}
}
```

---

## 2. Hygiene Calculator (Günlük Hijyen İhtiyacı Hesaplayıcı)

### Calculate Hygiene Needs
**POST** `/tools/hygiene-calculator/calculate`

Calculates daily and monthly hygiene product needs.

**Request Body:**
```json
{
  "child_age_months": 8,
  "lifestyle": "moderate"
}
```

**Parameters:**
- `child_age_months` (int, required): Child's age in months
- `lifestyle` (string, optional): "moderate", "active" (default: "moderate")

**Response:**
```json
{
  "daily_needs": {
    "diapers": 5,
    "wipes": 15,
    "bath_products": {...},
    "laundry_loads": 1.5
  },
  "monthly_needs": {
    "diapers": 150,
    "wipes": 450,
    "bath_products": {...},
    "laundry_loads": 45
  },
  "estimated_cost": {
    "diapers": "225 TL",
    "wipes": "225 TL",
    "total_estimated": "450 TL"
  },
  "recommendations": [
    "Bebek bezini her 2-3 saatte bir kontrol edin"
  ],
  "sponsor_data": {...}
}
```

---

## 3. Diaper Calculator (Akıllı Bez Hesaplayıcı)

### Calculate Diaper Needs
**POST** `/tools/diaper-calculator/calculate`

Calculates diaper requirements based on age and weight.

**Request Body:**
```json
{
  "child_age_months": 6,
  "weight_kg": 7.5,
  "feeding_type": "mixed"
}
```

**Parameters:**
- `child_age_months` (int, required): Child's age in months
- `weight_kg` (float, required): Child's weight in kilograms
- `feeding_type` (string, optional): "breast", "formula", "mixed" (default: "mixed")

**Response:**
```json
{
  "daily_count": 6,
  "weekly_count": 42,
  "monthly_count": 180,
  "recommended_size": "2 (Midi)",
  "change_frequency": "Her 3-4 saatte bir veya kirlendiğinde",
  "tips": [
    "Bez değiştirirken her seferinde temizleyin"
  ],
  "sponsor_data": {...}
}
```

### Assess Rash Risk
**POST** `/tools/diaper-calculator/rash-risk`

Assesses diaper rash risk based on various factors.

**Request Body:**
```json
{
  "factors": {
    "change_frequency": "infrequent",
    "skin_type": "sensitive",
    "recent_antibiotics": true,
    "diet_change": false,
    "diarrhea": false
  }
}
```

**Parameters:**
- `factors` (object, required): Risk factors object
  - `change_frequency`: "frequent", "infrequent"
  - `skin_type`: "normal", "sensitive"
  - `recent_antibiotics`: boolean
  - `diet_change`: boolean
  - `diarrhea`: boolean

**Response:**
```json
{
  "risk_level": "moderate",
  "risk_score": 50,
  "risk_factors": [
    "Bezler yeterince sık değiştirilmiyor",
    "Hassas cilt"
  ],
  "prevention_tips": [
    "Bezleri sık değiştirin",
    "Bariyer krem kullanın"
  ],
  "treatment_recommendations": [
    "Hafif bariyer krem kullanın",
    "Bez değişim sıklığını artırın"
  ],
  "sponsor_data": {...}
}
```

---

## 4. Air Quality Guide (Hava Kalitesi Rehberi)

### Analyze Air Quality
**POST** `/tools/air-quality/analyze`

Analyzes air quality and provides recommendations for children.

**Request Body:**
```json
{
  "aqi": 75,
  "has_newborn": true,
  "respiratory_issues": false
}
```

**Parameters:**
- `aqi` (int, required): Air Quality Index (0-500)
- `has_newborn` (bool, optional): Whether there's a newborn (default: false)
- `respiratory_issues` (bool, optional): Whether child has respiratory issues (default: false)

**Response:**
```json
{
  "aqi": 75,
  "quality_level": {
    "level": "Orta",
    "color": "yellow",
    "description": "Hassas gruplar için kabul edilebilir"
  },
  "is_safe_for_outdoor": true,
  "recommendations": [
    "Normal aktivitelerinize devam edebilirsiniz"
  ],
  "indoor_tips": [
    "Düzenli havalandırma yapın",
    "Hava temizleyici kullanın"
  ],
  "sponsor_data": {...}
}
```

---

## 5. Stain Encyclopedia (Leke Ansiklopedisi)

### Search Stains
**GET** `/tools/stain-encyclopedia/search?q=mama&category=food`

Searches for stain removal information.

**Query Parameters:**
- `q` (string, optional): Search query
- `category` (string, optional): Filter by category ("food", "bodily", "other")

**Response:**
```json
{
  "total": 2,
  "stains": [
    {
      "slug": "mama-lekesi",
      "name": "Mama Lekesi",
      "category": "food",
      "description": "Sebze veya meyve bazlı mama lekeleri",
      "difficulty": "easy"
    }
  ],
  "categories": [
    {
      "id": "food",
      "label": "Yemek Lekeleri"
    }
  ],
  "sponsor_data": {...}
}
```

### Get Stain Detail
**GET** `/tools/stain-encyclopedia/{slug}`

Gets detailed stain removal instructions.

**Path Parameters:**
- `slug` (string, required): Stain slug (e.g., "mama-lekesi")

**Response:**
```json
{
  "slug": "mama-lekesi",
  "name": "Mama Lekesi",
  "category": "food",
  "description": "Sebze veya meyve bazlı mama lekeleri",
  "difficulty": "easy",
  "removal_steps": [
    "Fazla mamayı kazıyın",
    "Soğuk suyla durulayın",
    "Leke çıkarıcı sprey uygulayın",
    "30 dakika bekleyin",
    "Normal yıkama programında yıkayın"
  ],
  "products": [
    "Leke çıkarıcı sprey",
    "Bebek deterjanı"
  ],
  "tips": [
    "Hemen müdahale edin",
    "Sıcak su kullanmayın"
  ],
  "sponsor_data": {...}
}
```

---

## Tool Post Type - Sponsor Meta Fields

The following meta fields are available for the `tool` post type via the admin interface:

### Meta Fields
- `_kg_tool_is_sponsored` (checkbox): Whether the tool is sponsored
- `_kg_tool_sponsor_name` (text): Sponsor brand name
- `_kg_tool_sponsor_url` (url): Sponsor website URL
- `_kg_tool_sponsor_logo` (media): Sponsor logo attachment ID
- `_kg_tool_sponsor_light_logo` (media): Sponsor light logo attachment ID (for dark backgrounds)
- `_kg_tool_sponsor_tagline` (text): Sponsor tagline or description
- `_kg_tool_sponsor_cta_text` (text): Call-to-action button text
- `_kg_tool_sponsor_cta_url` (url): Call-to-action button URL
- `_kg_tool_gam_impression_url` (url): Google Ad Manager impression tracking pixel
- `_kg_tool_gam_click_url` (url): Google Ad Manager click tracking URL

### REST API Integration

The `sponsor_data` field is automatically included in the REST API response for `tool` post type when accessed via `/wp-json/wp/v2/tool/{id}` or `/wp-json/kg/v1/tools/{slug}`.

---

## Error Responses

All endpoints return standard WordPress REST API error responses:

```json
{
  "code": "error_code",
  "message": "Error message",
  "data": {
    "status": 400
  }
}
```

Common error codes:
- `invalid_input`: Invalid or missing required parameters
- `invalid_age`: Invalid age value
- `invalid_aqi`: Invalid AQI value
- `tool_not_found`: Tool not found
- `stain_not_found`: Stain not found

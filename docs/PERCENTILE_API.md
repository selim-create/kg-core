# Percentile Calculator API - Usage Examples

This document provides examples of how to use the Percentile Calculator API endpoints.

## Endpoints

### 1. Calculate Percentile (Public)

Calculate growth percentiles for a child without requiring authentication.

**Endpoint:** `POST /kg/v1/tools/percentile/calculate`

**Request:**
```json
{
  "gender": "male",
  "birth_date": "2024-06-15",
  "measurement_date": "2025-01-15",
  "weight_kg": 9.5,
  "height_cm": 75.0,
  "head_circumference_cm": 45.0
}
```

**Response:**
```json
{
  "age_in_days": 214,
  "age_in_months": 7,
  "percentiles": [
    {
      "measurement_type": "weight_for_age",
      "value": 9.5,
      "percentile": 75.2,
      "z_score": 0.68,
      "category": "normal",
      "interpretation": "Kilo yaşa göre normal aralıkta"
    },
    {
      "measurement_type": "height_for_age",
      "value": 75.0,
      "percentile": 85.1,
      "z_score": 1.04,
      "category": "normal",
      "interpretation": "Boy yaşa göre normal aralıkta"
    },
    {
      "measurement_type": "head_for_age",
      "value": 45.0,
      "percentile": 65.3,
      "z_score": 0.39,
      "category": "normal",
      "interpretation": "Baş çevresi normal aralıkta"
    },
    {
      "measurement_type": "weight_for_height",
      "value": 9.5,
      "percentile": 50.2,
      "z_score": 0.01,
      "category": "normal",
      "interpretation": "Kilo-boy oranı ideal"
    }
  ],
  "red_flags": [],
  "measurement": {
    "gender": "male",
    "birth_date": "2024-06-15",
    "measurement_date": "2025-01-15",
    "weight_kg": 9.5,
    "height_cm": 75.0,
    "head_circumference_cm": 45.0
  },
  "created_at": "2025-01-15T10:30:00+03:00"
}
```

**cURL Example:**
```bash
curl -X POST https://api.kidsgourmet.com/wp-json/kg/v1/tools/percentile/calculate \
  -H "Content-Type: application/json" \
  -d '{
    "gender": "male",
    "birth_date": "2024-06-15",
    "measurement_date": "2025-01-15",
    "weight_kg": 9.5,
    "height_cm": 75.0,
    "head_circumference_cm": 45.0
  }'
```

### 2. Save Percentile Result with Registration

Save percentile results and create a new user account.

**Endpoint:** `POST /kg/v1/tools/percentile/save`

**Request:**
```json
{
  "register": true,
  "email": "anne@example.com",
  "password": "password123",
  "name": "Ayşe Anne",
  "child_name": "Bebek",
  "child_birth_date": "2024-06-15",
  "measurement": {
    "gender": "male",
    "birth_date": "2024-06-15",
    "measurement_date": "2025-01-15",
    "weight_kg": 9.5,
    "height_cm": 75.0,
    "head_circumference_cm": 45.0
  },
  "percentiles": [...],
  "red_flags": []
}
```

**Response:**
```json
{
  "success": true,
  "message": "Persentil sonucu kaydedildi",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user_id": 123,
  "child_id": "550e8400-e29b-41d4-a716-446655440000",
  "child_name": "Bebek"
}
```

### 3. Save Result for Existing User

Save percentile results for an authenticated user.

**Endpoint:** `POST /kg/v1/tools/percentile/save`

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Request:**
```json
{
  "child_id": "550e8400-e29b-41d4-a716-446655440000",
  "measurement": {...},
  "percentiles": [...],
  "red_flags": []
}
```

**Response:**
```json
{
  "success": true,
  "message": "Persentil sonucu kaydedildi"
}
```

### 4. Get User's Percentile Results

Retrieve all percentile results for the authenticated user.

**Endpoint:** `GET /kg/v1/user/percentile-results`

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response:**
```json
[
  {
    "id": "uuid-1",
    "child_id": "child-uuid",
    "measurement": {...},
    "age_in_days": 214,
    "percentiles": [...],
    "red_flags": [],
    "created_at": "2025-01-15T10:30:00+03:00"
  },
  ...
]
```

### 5. Get Child's Percentile History

Retrieve percentile results for a specific child.

**Endpoint:** `GET /kg/v1/user/children/{child_id}/percentile-results`

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
```

**Response:**
```json
[
  {
    "id": "uuid-1",
    "child_id": "550e8400-e29b-41d4-a716-446655440000",
    "measurement": {...},
    "age_in_days": 214,
    "percentiles": [...],
    "red_flags": [],
    "created_at": "2025-01-15T10:30:00+03:00"
  },
  ...
]
```

## Percentile Categories

| Category | Percentile Range | Interpretation |
|----------|------------------|----------------|
| very_low | < 3% | Çok düşük - Pediatrist konsültasyonu önerilir |
| low | 3-15% | Düşük |
| normal | 15-85% | Normal aralık |
| high | 85-97% | Yüksek |
| very_high | > 97% | Çok yüksek - Pediatrist konsültasyonu önerilir |

## Red Flags

Red flags are automatically detected when measurements fall outside normal ranges:

- **Weight-for-age < 3%**: Critical - Kilo çok düşük
- **Weight-for-age > 97%**: Warning - Kilo çok yüksek
- **Height-for-age < 3%**: Critical - Boy çok düşük
- **Head circumference < 3%**: Critical - Mikrosefali riski
- **Head circumference > 97%**: Critical - Makrosefali riski

## Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 400 | invalid_gender | Gender must be 'male' or 'female' |
| 400 | missing_dates | Birth and measurement dates are required |
| 400 | invalid_dates | Measurement date cannot be before birth date |
| 400 | age_limit | WHO standards are valid for 0-5 years |
| 400 | registration_failed | Missing email or password |
| 400 | invalid_email | Email format is invalid |
| 400 | weak_password | Password must be at least 8 characters |
| 401 | unauthorized | Authentication required |
| 409 | email_exists | Email already registered |

## WHO Growth Standards

The API uses official WHO Child Growth Standards with LMS (Lambda-Mu-Sigma) method:

- **Data Source**: https://www.who.int/tools/child-growth-standards/standards
- **Age Range**: 0-5 years
- **Measurements**:
  - Weight-for-age
  - Length/Height-for-age
  - Head circumference-for-age
  - Weight-for-length/height

## Implementation Details

### Z-Score Calculation

Using WHO LMS method:
```
If L ≠ 0: Z = [(value/M)^L - 1] / (L*S)
If L = 0: Z = ln(value/M) / S
```

### Percentile Conversion

Using standard normal cumulative distribution:
```
Percentile = 100 * 0.5 * (1 + erf(Z / √2))
```

Where `erf` is the error function.

## Testing

Run the test suite:
```bash
php tests/test-percentile-backend.php
```

Expected output:
```
Test Summary:
✓ Passed: 66
✗ Failed: 0
Success Rate: 100%
```

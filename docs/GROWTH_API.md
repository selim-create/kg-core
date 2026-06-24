# Growth Tracking API — Documentation

## Overview

The Growth Tracking API allows parents to record and monitor their children's physical growth measurements (weight, height, head circumference) over time. Percentiles are calculated using the WHO Child Growth Standards (LMS method).

## Base URL

`/wp-json/kg/v1`

## Authentication

All endpoints require a valid JWT token:

```
Authorization: ******
```

---

## Endpoints

### 1. `GET /health/growth`

Returns all growth records for a specific child, the latest record, and calculated WHO percentiles.

**Query Parameters:**

| Parameter  | Type   | Required | Description       |
|------------|--------|----------|-------------------|
| `child_id` | string | ✅        | Child UUID        |

**Response (200):**

```json
{
  "records": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "child_id": "abc123",
      "date": "2026-01-15",
      "weight_kg": 9.5,
      "height_cm": 75.0,
      "head_circumference_cm": 45.0,
      "notes": ""
    }
  ],
  "latest": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "child_id": "abc123",
    "date": "2026-01-15",
    "weight_kg": 9.5,
    "height_cm": 75.0,
    "head_circumference_cm": 45.0,
    "notes": ""
  },
  "percentile": {
    "age_months": 7,
    "calculated_at": "2026-06-24T12:00:00+03:00",
    "weight_percentile": 75.2,
    "height_percentile": 60.1,
    "head_circumference_percentile": 50.0
  }
}
```

---

### 2. `POST /health/growth`

Adds a new growth measurement for a child.

**Request Body:**

```json
{
  "child_id": "abc123",
  "date": "2026-01-15",
  "weight_kg": 9.5,
  "height_cm": 75.0,
  "head_circumference_cm": 45.0,
  "notes": "Doktor kontrolünde ölçüldü"
}
```

| Field                    | Type   | Required | Description                     |
|--------------------------|--------|----------|---------------------------------|
| `child_id`               | string | ✅        | Child UUID                      |
| `date`                   | string | ✅        | Measurement date (YYYY-MM-DD)   |
| `weight_kg`              | float  | ❌        | Weight in kilograms             |
| `height_cm`              | float  | ❌        | Height/length in centimeters    |
| `head_circumference_cm`  | float  | ❌        | Head circumference in cm        |
| `notes`                  | string | ❌        | Optional notes                  |

**Response (201):**

```json
{
  "success": true,
  "record": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "child_id": "abc123",
    "date": "2026-01-15",
    "weight_kg": 9.5,
    "height_cm": 75.0,
    "head_circumference_cm": 45.0,
    "notes": "Doktor kontrolünde ölçüldü"
  },
  "message": "Ölçüm kaydedildi."
}
```

---

### 3. `PUT /health/growth/{id}`

Updates an existing growth record. Only provided fields are updated.

**Request Body (example):**

```json
{
  "date": "2026-01-20",
  "weight_kg": 9.8,
  "notes": "Ev ölçümü"
}
```

**Response (200):**

```json
{
  "success": true,
  "record": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "child_id": "abc123",
    "date": "2026-01-20",
    "weight_kg": 9.8,
    "height_cm": 75.0,
    "head_circumference_cm": 45.0,
    "notes": "Ev ölçümü"
  },
  "message": "Ölçüm güncellendi."
}
```

---

### 4. `DELETE /health/growth/{id}`

Deletes a growth record by `id`.

**Response (200):**

```json
{
  "success": true,
  "message": "Ölçüm silindi."
}
```

If record does not exist:

```json
{
  "code": "record_not_found",
  "message": "Ölçüm kaydı bulunamadı."
}
```

---

### 5. `GET /health/growth/chart-data`

Returns chart-ready data including the child's measurements with percentiles and WHO reference curves.

**Query Parameters:**

| Parameter  | Type   | Required | Default           | Description                                            |
|------------|--------|----------|-------------------|--------------------------------------------------------|
| `child_id` | string | ✅        | —                 | Child UUID                                             |
| `type`     | string | ❌        | `weight_for_age`  | `weight_for_age` \| `height_for_age` \| `head_for_age` |

**Response (200):**

```json
{
  "child": {
    "name": "Ayşe",
    "gender": "female",
    "birth_date": "2025-06-10"
  },
  "type": "weight_for_age",
  "measurements": [
    {
      "age_days": 214,
      "value": 9.5,
      "percentile": 75.2,
      "z_score": 0.68,
      "date": "2026-01-10"
    }
  ],
  "reference_curves": {
    "p3":  [{ "age_days": 0, "value": 2.355 }, { "age_days": 7, "value": 2.546 }, "..."],
    "p15": [{ "age_days": 0, "value": 2.750 }, "..."],
    "p50": [{ "age_days": 0, "value": 3.346 }, "..."],
    "p85": [{ "age_days": 0, "value": 3.908 }, "..."],
    "p97": [{ "age_days": 0, "value": 4.341 }, "..."]
  }
}
```

### Reference Curve Calculation

Reference curves are calculated from the WHO LMS tables using the inverse LMS formula:

```
If L ≠ 0:  value = M * (1 + L * S * z)^(1/L)
If L = 0:  value = M * exp(S * z)
```

Z-score values for each percentile:

| Percentile | Z-score  |
|------------|----------|
| P3         | −1.881   |
| P15        | −1.036   |
| P50        |  0.000   |
| P85        | +1.036   |
| P97        | +1.881   |

---

## Data Storage

Growth records are stored as a JSON array in the `_kg_growth_records` WordPress user meta key. Each record has the following shape:

```json
{
  "id": "uuid",
  "child_id": "uuid",
  "date": "YYYY-MM-DD",
  "weight_kg": 9.5,
  "height_cm": 75.0,
  "head_circumference_cm": 45.0,
  "notes": ""
}
```

---

## WHO Growth Standards

Data source: [WHO Child Growth Standards](https://www.who.int/tools/child-growth-standards/standards)

Supported measurement types:

| Type             | WHO Table                      | Age Range |
|------------------|--------------------------------|-----------|
| `weight_for_age` | `wfa_boys_0_5.json` / `girls`  | 0–5 years |
| `height_for_age` | `lhfa_boys_0_5.json` / `girls` | 0–5 years |
| `head_for_age`   | `hcfa_boys_0_5.json` / `girls` | 0–5 years |

---

## Error Responses

| HTTP Status | Error Code          | Description                                |
|-------------|---------------------|--------------------------------------------|
| 401         | `unauthorized`      | Missing or invalid JWT token               |
| 400         | `missing_birth_date`| Child's birth date not set in profile      |
| 404         | `child_not_found`   | Child ID not found in user's profile       |

---

**Implementation Date:** June 2026  
**Version:** 1.0.0  
**Status:** ✅ Implemented

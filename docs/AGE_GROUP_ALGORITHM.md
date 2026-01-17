# Age Group Algorithm and Safety Mapping Implementation

## Overview

This implementation provides a **comprehensive, deterministic age group algorithm** with centralized mapping for all safety checks including allergy, age compatibility, forbidden ingredients, and nutrition concerns.

## Key Features

### 1. Centralized Age Compatibility Mapping

All age group combinations are handled through a single, well-documented mapping function that provides consistent severity levels:

#### Severity Mapping Table

| Child Age Group | Recipe Age Group | Severity | Color | Safe? | Description |
|----------------|------------------|----------|-------|-------|-------------|
| Exact match | Same as child | success | green | ✓ | Perfect match, safe to use |
| Older child | 1-2 levels younger | info | blue | ✓ | Older children can safely eat younger food |
| Younger child | 1 level older | warning | yellow | ✗ | Caution needed, recipe may be too advanced |
| Younger child | 2+ levels older | critical | red | ✗ | DANGEROUS - recipe is too advanced |

#### Specific Examples

- **0-6 months + 9-11 month recipe** → WARNING (1 level gap)
- **0-6 months + 2+ year recipe** → CRITICAL (4 level gap)
- **24 months + 6-8 month recipe** → INFO (safe, older can eat younger food)
- **8 months + 6-8 month recipe** → SUCCESS (exact match)

### 2. Age Group Order

The algorithm uses a deterministic 5-level hierarchy:

```php
0: '0-6-ay-sadece-sut'    (0-6 months, milk only)
1: '6-8-ay-baslangic'     (6-8 months, starting)
2: '9-11-ay-kesif'        (9-11 months, discovery)
3: '12-24-ay-gecis'       (12-24 months, transition)
4: '2-yas-ve-uzeri'       (2+ years)
```

### 3. Alert Types and Severity Colors

All alerts include both `severity` and `severity_color` fields for frontend compatibility:

#### Allergy Alerts
- **Type**: `allergy`
- **Severity**: `critical`
- **Color**: `red`
- **Description**: Ingredient contains child's allergen

#### Forbidden Ingredient Alerts
- **Type**: `forbidden`
- **Severity**: `critical` or `warning`
- **Color**: `red` or `yellow`
- **Examples**:
  - Honey for <12 months → CRITICAL (botulism risk)
  - Whole nuts for <48 months → CRITICAL (choking hazard)
  - Cow's milk for <12 months → WARNING (nutritional concern)

#### Age Mismatch Alerts
- **Type**: `age`
- **Severity**: `critical`, `warning`, or `info`
- **Color**: `red`, `yellow`, or `blue`
- **Determined by**: Gap between child and recipe age levels

#### Nutrition Alerts
- **Type**: `nutrition`
- **Severity**: `warning` or `info`
- **Color**: `yellow` or `blue`
- **Examples**:
  - Salt for <12 months → WARNING
  - Sugar for young children → INFO

### 4. HTML Entity Decoding

All API responses are automatically decoded to prevent HTML entities in messages:

- Messages are decoded using `html_entity_decode()` with `ENT_QUOTES | ENT_HTML5` and `UTF-8` encoding
- No `&amp;`, `&lt;`, `&gt;`, `&quot;` will appear in responses
- Applies to:
  - Alert messages
  - Alternative suggestions
  - Ingredient names
  - Verdict messages

## Implementation Details

### SafetyCheckService

#### Main Methods

1. **`checkRecipeSafety($recipe_id, $child)`**
   - Performs comprehensive safety check for a recipe
   - Returns: Recipe safety data with alerts and alternatives
   - Automatically decodes all messages

2. **`checkIngredientSafety($ingredient_id, $child)`**
   - Checks individual ingredient safety
   - Returns: Ingredient safety data with alerts
   - Automatically decodes all messages

3. **`batchSafetyCheck($recipe_ids, $child)`**
   - Batch checks multiple recipes
   - Returns: Array of summary results with critical alerts

#### Private Helper Methods

1. **`get_age_compatibility_severity($child_age_group, $recipe_age_group, $child_age_months, $recipe_age_name)`**
   - Centralized age compatibility logic
   - Calculates level gap and determines severity
   - Returns: Severity data with color, message, alternative

2. **`decode_alert_messages($alerts)`**
   - Decodes HTML entities in all alert fields
   - Applies to: message, alternative, reason, ingredient

3. **`check_allergens($recipe_id, $child)`**
   - Checks recipe for child's allergens
   - Always returns CRITICAL severity

4. **`check_age_appropriateness($recipe_id, $child)`**
   - Uses centralized mapping for age checks
   - Returns alerts with detailed metadata

5. **`check_forbidden_ingredients($recipe_id, $child)`**
   - Checks for age-restricted ingredients
   - Returns CRITICAL or WARNING based on ingredient

6. **`check_nutrition_concerns($recipe_id, $child)`**
   - Checks for salt, sugar, etc.
   - Returns WARNING or INFO

### FoodSuitabilityChecker

Enhanced with:
- HTML decoding for all verdict messages
- HTML decoding for ingredient names
- Consistent color mapping

## Response Format

### Recipe Safety Check Response

```json
{
  "recipe_id": 123,
  "is_safe": false,
  "safety_score": 0,
  "alerts": [
    {
      "type": "age",
      "severity": "critical",
      "severity_color": "red",
      "message": "Bu tarif 2+ yaş yaş grubu için önerilmiş. Çocuğunuz 4 aylık. KESİNLİKLE VERMEYİN! Bu tarif çocuğunuz için çok erken ve tehlikeli olabilir.",
      "alternative": "Yaş grubunuza uygun tariflere göz atın.",
      "is_for_older": true,
      "child_age_months": 4,
      "child_age_group": "0-6-ay-sadece-sut",
      "recipe_age_group": "2-yas-ve-uzeri"
    }
  ],
  "alternatives": [...],
  "checked_at": "2026-01-17T20:00:00+00:00"
}
```

### Ingredient Safety Check Response

```json
{
  "ingredient_id": 456,
  "ingredient_name": "Bal",
  "is_safe": false,
  "is_introduced": false,
  "alerts": [
    {
      "type": "forbidden",
      "severity": "critical",
      "severity_color": "red",
      "message": "KESİNLİKLE VERMEYİN! Bal 12 aydan önce botulizm riski taşır.",
      "ingredient": "Bal",
      "alternative": "Bal yerine muz veya elma püresi kullanabilirsiniz."
    }
  ],
  "allergy_risk": "Düşük",
  "start_age": 12
}
```

## Testing

### Test Files

1. **`test-age-group-algorithm.php`** - Comprehensive test suite
   - Age compatibility mapping tests
   - Severity color mapping tests
   - HTML decoding tests
   - Combined alert scenario tests

2. **`test-safety-age-appropriateness.php`** - Legacy tests (still passing)
   - Backward compatibility validation

### Running Tests

```bash
# Static validation (no WordPress required)
php tests/test-age-group-algorithm.php

# With WordPress loaded (full functional tests)
php tests/test-age-group-algorithm.php
```

### Test Coverage

- ✓ All age group combinations (0-6 months through 2+ years)
- ✓ Severity levels (critical, warning, info, success)
- ✓ Severity colors (red, yellow, blue, green)
- ✓ HTML entity decoding
- ✓ Combined alert scenarios
- ✓ is_safe flag behavior
- ✓ Safety score calculation

## Frontend Integration

### Alert Display Mapping

Use `severity_color` field for UI:

```javascript
const colorMap = {
  'red': '#DC2626',      // Critical - danger
  'yellow': '#F59E0B',   // Warning - caution
  'blue': '#3B82F6',     // Info - informational
  'green': '#10B981'     // Success - safe
};
```

### Alert Icons

```javascript
const iconMap = {
  'critical': '⛔',  // or custom danger icon
  'warning': '⚠️',   // or custom warning icon
  'info': 'ℹ️',      // or custom info icon
  'success': '✅'    // or custom success icon
};
```

## Code Quality

### Documentation
- All methods have comprehensive docblocks
- Inline comments explain complex logic
- Centralized mapping has detailed explanation

### Maintainability
- Single source of truth for age compatibility
- Consistent severity and color mapping
- Easy to extend with new age groups or rules

### Consistency
- All alert types follow same structure
- All messages are decoded automatically
- All responses include severity_color

## Migration Notes

### Changes from Previous Version

1. **Centralized Mapping**: Age compatibility logic moved from scattered checks to single function
2. **Color Field**: Added `severity_color` to all alerts
3. **HTML Decoding**: All messages automatically decoded
4. **Granular Severity**: Warning vs Critical based on age gap size
5. **Better Documentation**: Comprehensive inline and external docs

### Backward Compatibility

- ✓ All existing alert types preserved
- ✓ Response structure unchanged (only added fields)
- ✓ is_safe logic consistent with previous behavior
- ✓ Existing tests still pass

## Future Enhancements

Potential improvements:
1. Add more forbidden ingredients with age-specific rules
2. Support for partial allergen matching
3. Customizable severity thresholds
4. Multi-language support for messages
5. Cache frequently-checked recipes

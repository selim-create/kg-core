# Featured Content API Implementation

## Overview
This implementation adds a new Featured Content API endpoint and enhances existing endpoints to support the frontend homepage redesign.

## New API Endpoints

### 1. Featured Content Endpoint
**GET** `/wp-json/kg/v1/featured`

Aggregates featured content from different types (recipes, posts, discussions, sponsors) into a unified response.

#### Query Parameters:
- `limit` (integer, default: 5, max: 50) - Maximum number of items to return
- `type` (string, default: 'all') - Filter by content type
  - `all` - Return all types
  - `recipe` - Only recipes
  - `post` - Only blog posts
  - `question` - Only discussions/questions
  - `sponsor` - Only sponsored content

#### Example Request:
```bash
GET /wp-json/kg/v1/featured?limit=10&type=all
```

#### Response Structure:
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "type": "recipe",
      "title": "Karnabahar Tabanlı Bebek Pizzası",
      "slug": "karnabahar-pizza",
      "image": "https://...",
      "excerpt": "...",
      "date": "2026-01-10T12:00:00+00:00",
      "meta": {
        "age_group": "12+ Ay",
        "age_group_color": "#CE93D8",
        "prep_time": "25 dk",
        "rating": 4.8,
        "rating_count": 120,
        "meal_type": "Ana Öğün",
        "diet_types": ["Şekersiz", "Glutensiz"],
        "expert": {
          "name": "Hande Özyılmaz",
          "title": "Dyt.",
          "approved": true
        }
      }
    },
    {
      "id": 456,
      "type": "post",
      "title": "Gece Uyanmalarının Sebebi Açlık mı?",
      "slug": "gece-uyanmalari",
      "image": "https://...",
      "excerpt": "...",
      "date": "2026-01-09T10:00:00+00:00",
      "meta": {
        "category": "Uyku & Beslenme",
        "author": "Dr. Ayşe Yılmaz",
        "read_time": "5 dk"
      }
    },
    {
      "id": 789,
      "type": "question",
      "title": "Ek gıdaya geçişte kabızlık sorunu yaşayan var mı?",
      "slug": "kabizlik-sorunu",
      "date": "2026-01-08T14:00:00+00:00",
      "meta": {
        "author_name": "Deniz'in Annesi",
        "author_initials": "DA",
        "answer_count": 24
      }
    },
    {
      "id": 101,
      "type": "sponsor",
      "title": "Organik Kavanoz Serisi ile Pratik Ara Öğün Fikirleri",
      "slug": "organik-kavanoz",
      "image": "https://...",
      "excerpt": "...",
      "date": "2026-01-07T09:00:00+00:00",
      "meta": {
        "sponsor_name": "Organik Bebek",
        "sponsor_logo": "https://...",
        "sponsor_light_logo": "https://...",
        "sponsor_url": "https://...",
        "category": "Ara Öğün",
        "has_discount": true,
        "discount_text": "İndirim"
      }
    }
  ]
}
```

### 2. Recipe Rating Endpoint
**POST** `/wp-json/kg/v1/recipes/{id}/rate`

Rate a recipe (requires authentication).

#### Authentication:
User must be logged in (JWT token required).

#### Path Parameters:
- `id` (integer, required) - Recipe ID

#### Body Parameters:
- `rating` (float, required, 1-5) - Rating value

#### Example Request:
```bash
POST /wp-json/kg/v1/recipes/123/rate
Content-Type: application/json
Authorization: Bearer {jwt_token}

{
  "rating": 4.5
}
```

#### Response:
```json
{
  "success": true,
  "rating": 4.8,
  "rating_count": 121,
  "user_rating": 4.5
}
```

## Enhanced Existing Endpoints

### WordPress Posts API Enhancement
**GET** `/wp-json/wp/v2/posts`

The default WordPress posts endpoint now includes additional fields:

#### New Fields:
- `author_data` (object)
  - `name` (string) - Author display name
  - `avatar` (string) - Avatar URL
- `category_data` (object|null)
  - `id` (integer) - Category ID
  - `name` (string) - Category name
  - `slug` (string) - Category slug
- `read_time` (string) - Estimated read time (e.g., "5 dk")

#### HTML Entity Decoding:
All titles are automatically decoded from HTML entities (e.g., `&amp;` → `&`, `&quot;` → `"`).

## Meta Fields

### Recipe Meta Fields (Added)
- `_kg_rating` (float) - Average rating
- `_kg_rating_count` (integer) - Total number of ratings
- `_kg_ratings` (array) - Individual user ratings (user_id => rating)

### Discussion/Question Meta Fields (Added)
- `_kg_is_featured` (string: '0' or '1') - Featured flag
- `_kg_answer_count` (integer) - Number of answers/comments

### Post Meta Fields (Existing, for sponsored content)
- `_kg_is_sponsored` (string: '0' or '1')
- `_kg_is_featured` (string: '0' or '1')
- `_kg_sponsor_name` (string)
- `_kg_sponsor_url` (string)
- `_kg_sponsor_logo` (integer) - Attachment ID
- `_kg_sponsor_light_logo` (integer) - Attachment ID
- `_kg_has_discount` (string: '0' or '1')
- `_kg_discount_text` (string)

## Age Group Color Codes

Updated color codes for age group taxonomy to use pastel colors:

| Age Group | Slug | Color Code | Description |
|-----------|------|------------|-------------|
| 0-6 Ay | 0-6-ay-sadece-sut | #E8F5E9 | Pastel Light Green |
| 6-8 Ay | 6-8-ay-baslangic | #FFAB91 | Pastel Orange |
| 9-11 Ay | 9-11-ay-kesif | #A5D6A7 | Pastel Green |
| 12-24 Ay | 12-24-ay-gecis | #90CAF9 | Pastel Blue |
| 2+ Yaş | 2-yas-ve-uzeri | #CE93D8 | Pastel Purple |

### Age Group API Response
The `age-group` taxonomy now includes `age_group_meta` field with:
```json
{
  "min_month": 6,
  "max_month": 8,
  "color_code": "#FFAB91",
  "daily_meal_count": 2,
  "max_salt_limit": "0g (Yasak)",
  "texture_guide": "...",
  "forbidden_list": ["Bal", "İnek Sütü"],
  "warning_message": "..."
}
```

## Content Type Requirements

### Recipe (featured)
Required meta:
- `_kg_is_featured` = '1'

### Post (featured)
Required meta:
- `_kg_is_featured` = '1'

### Post (sponsored)
Required meta:
- `_kg_is_sponsored` = '1'
- `_kg_sponsor_name`
- `_kg_sponsor_logo` (attachment ID)

### Discussion/Question (featured)
Required meta:
- `_kg_is_featured` = '1'
- Post type: 'discussion'

## Technical Details

### Files Modified:
1. **includes/API/FeaturedController.php** (NEW)
   - Implements featured content aggregation
   - Handles all content types
   - Proper HTML entity decoding
   - Sponsor logo URL formatting

2. **includes/API/RecipeController.php**
   - Added `rate_recipe` method
   - New rating endpoint route

3. **includes/Taxonomies/AgeGroup.php**
   - Updated default color codes to pastel colors

4. **includes/PostTypes/Discussion.php**
   - Added `register_meta_fields` method
   - Registered `_kg_is_featured` and `_kg_answer_count` meta fields

5. **kg-core.php**
   - Loads FeaturedController
   - Added `rest_prepare_post` filter for enhanced post data
   - Enhanced author, category, and read time fields

### HTML Entity Decoding
All text fields (titles, excerpts) are automatically decoded using:
```php
\KG_Core\Utils\Helper::decode_html_entities( $text )
```

This ensures proper display of Turkish characters and special characters.

### Sponsor Logo URL Formatting
Logos are converted from attachment IDs to full URLs:
```php
$sponsor_logo_id = get_post_meta( $post->ID, '_kg_sponsor_logo', true );
$sponsor_logo = '';
if ( $sponsor_logo_id ) {
    $logo_url = wp_get_attachment_url( $sponsor_logo_id );
    $sponsor_logo = $logo_url ? (string) $logo_url : '';
}
```

## Testing

Run the test suite:
```bash
php test-featured-api.php
```

### Manual Testing Checklist:
1. ✅ Featured endpoint returns mixed content types
2. ✅ Recipe rating endpoint requires authentication
3. ✅ Age group colors display correctly
4. ✅ HTML entities are decoded in responses
5. ✅ Sponsor logos return as strings (URLs), not arrays
6. ✅ Post responses include author_data and category_data
7. ✅ Read time is calculated correctly

## Usage Examples

### Frontend Integration

```javascript
// Fetch featured content
const response = await fetch('/wp-json/kg/v1/featured?limit=5&type=all');
const { success, data } = await response.json();

// Display based on type
data.forEach(item => {
  switch(item.type) {
    case 'recipe':
      // Render recipe card with age_group_color
      break;
    case 'post':
      // Render blog post card with author and read_time
      break;
    case 'question':
      // Render question card with author_initials
      break;
    case 'sponsor':
      // Render sponsored content with sponsor_logo
      break;
  }
});

// Rate a recipe
const ratingResponse = await fetch('/wp-json/kg/v1/recipes/123/rate', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${jwt_token}`
  },
  body: JSON.stringify({ rating: 4.5 })
});
```

## Performance Considerations

- Featured content queries are optimized with proper meta_query
- Results are sorted in PHP, not in database (allows mixed content types)
- Limit parameter prevents excessive data transfer
- Caching recommended for production (use WordPress transients or object cache)

## Security

- Rating endpoint requires authentication (is_user_logged_in())
- Rating values validated (1-5 range)
- All user inputs sanitized
- HTML entities decoded safely with ENT_QUOTES | ENT_HTML5
- Attachment URLs validated before output

## Future Enhancements

Potential improvements:
- Add caching layer for featured content
- Implement weighted sorting (featured priority + recency)
- Add analytics tracking for featured content clicks
- Support for featured content scheduling
- Admin UI for managing featured flags

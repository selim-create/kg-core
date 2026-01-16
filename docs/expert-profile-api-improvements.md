# Expert Profile API Improvements - Implementation Summary

## Overview
This implementation addresses the requirements specified in the problem statement for the expert profile API:
1. Retrieve all content (not limited to 5-6 items)
2. Properly decode HTML entities in titles, categories, age groups, and circles
3. Support user slugs (user_nicename) for expert profile URLs

## Changes Made

### 1. UserController.php - Get All Content

#### get_expert_public_profile() method (lines 1618-1622)
**Before:**
```php
$recipes = $this->get_user_recipes( $user_id, 6 );
$blog_posts = $this->get_user_blog_posts( $user_id, 6 );
$answered_questions = $this->get_user_answered_questions( $user_id, 6 );
$asked_questions = $this->get_user_asked_questions( $user_id, 6 );
```

**After:**
```php
$recipes = $this->get_user_recipes( $user_id, -1 );
$blog_posts = $this->get_user_blog_posts( $user_id, -1 );
$answered_questions = $this->get_user_answered_questions( $user_id, -1 );
$asked_questions = $this->get_user_asked_questions( $user_id, -1 );
```

**Impact:** For an expert with 608 blog posts, all 608 posts will now be returned instead of just 6.

#### get_user_answered_questions() method (lines 1728-1748)
**Before:**
```php
$query = $wpdb->prepare(
    "SELECT DISTINCT c.comment_post_ID ... LIMIT %d",
    $user_id,
    $limit
);
```

**After:**
```php
$sql = "SELECT DISTINCT c.comment_post_ID ... ORDER BY c.comment_date DESC";

// Add LIMIT only if not requesting all results
if ( $limit !== -1 ) {
    $sql .= " LIMIT %d";
    $query = $wpdb->prepare( $sql, $user_id, $limit );
} else {
    $query = $wpdb->prepare( $sql, $user_id );
}
```

**Impact:** When `$limit` is -1, no SQL LIMIT clause is applied, returning all answered questions.

### 2. UserController.php - HTML Entity Decoding

#### format_recipe_card() - Age Group (line 1422)
**Before:**
```php
$age_group = $age_groups[0]->name;
```

**After:**
```php
$age_group = \KG_Core\Utils\Helper::decode_html_entities( $age_groups[0]->name );
```

**Example:** `Keşif &amp; Pütürlüye Geçiş (9-11 Ay)` → `Keşif & Pütürlüye Geçiş (9-11 Ay)`

#### format_recipe_card() - Meal Type Categories (line 1431)
**Before:**
```php
$categories[] = $meal_type->name;
```

**After:**
```php
$categories[] = \KG_Core\Utils\Helper::decode_html_entities( $meal_type->name );
```

#### format_post_card() - Category (line 1472)
**Before:**
```php
$category = $categories[0]->name;
```

**After:**
```php
$category = \KG_Core\Utils\Helper::decode_html_entities( $categories[0]->name );
```

#### format_discussion_card() - Circle (line 1513)
**Before:**
```php
$circle = $circles[0]->name;
```

**After:**
```php
$circle = \KG_Core\Utils\Helper::decode_html_entities( $circles[0]->name );
```

**Note:** Titles were already using `\KG_Core\Utils\Helper::decode_html_entities()` before this change.

### 3. UserController.php - Slug Support

#### get_expert_public_profile() response (line 1630)
**Before:**
```php
'username' => $user->user_login,
```

**After:**
```php
'username' => $user->user_nicename,
```

**Impact:** The API now returns the URL-friendly slug (e.g., `dr-enver-mahir-gulcan`) instead of the login username.

**Note:** The method already supports lookup by slug on line 1582:
```php
if ( ! $user ) {
    $user = get_user_by( 'slug', $username );
}
```

### 4. ExpertController.php - Slug Support

#### get_experts_list() method (line 112)
**Before:**
```php
'username' => $user->user_login,
```

**After:**
```php
'username' => $user->user_nicename,
```

**Impact:** The experts list endpoint now returns slugs that can be used directly in URLs.

## API Endpoints Affected

### 1. GET /kg/v1/expert/public/{username}
- **Before:** Limited to 6 items per category
- **After:** Returns ALL recipes, blog posts, questions asked, and questions answered
- **Before:** `username` field returns `user_login`
- **After:** `username` field returns `user_nicename` (slug)
- Accepts both `user_login` and `user_nicename` as the `{username}` parameter

### 2. GET /kg/v1/experts
- **Before:** `username` field returns `user_login`
- **After:** `username` field returns `user_nicename` (slug)

## Testing

A test script was created at `tests/test-expert-profile-api-improvements.php` that verifies:
1. All recipes are returned (not limited to 6)
2. All blog posts are returned (not limited to 6)
3. All questions are returned (not limited to 6)
4. HTML entities are properly decoded in titles and taxonomy terms
5. `username` field returns `user_nicename` (slug)
6. Both endpoints support slug-based lookups

## Backward Compatibility

### Breaking Changes
None. The changes are backward compatible:
- The endpoint still accepts `user_login` as a parameter (it tries login first, then slug)
- The response structure remains the same, only the `username` field value changes
- Frontend applications should use the `username` field from the API response for links

### Migration Notes
If frontend applications are constructing URLs manually using `user_login`, they should be updated to use the `username` field from the API response instead, which now contains the URL-friendly slug.

## Files Modified
1. `/includes/API/UserController.php`
   - Updated `get_expert_public_profile()` to return all content
   - Updated `get_user_answered_questions()` to support unlimited results
   - Added HTML entity decoding to format methods
   - Changed response to return `user_nicename` as username

2. `/includes/API/ExpertController.php`
   - Changed `get_experts_list()` to return `user_nicename` as username

## Performance Considerations

### Database Queries
- **Recipes:** `posts_per_page: -1` retrieves all published recipes by the expert
- **Blog Posts:** `posts_per_page: -1` retrieves all published posts by the expert
- **Discussions:** `posts_per_page: -1` retrieves all published discussions by the expert
- **Answered Questions:** Removed SQL `LIMIT` clause when limit is -1

### Impact
For experts with many posts (e.g., 608 blog posts), the API response will be larger and take slightly longer to generate. However, this is the expected behavior to display all content on the expert profile page.

**Recommendation:** If performance becomes an issue, consider implementing:
- Client-side pagination
- Lazy loading
- Caching the API response

## HTML Entity Decode Implementation

The `\KG_Core\Utils\Helper::decode_html_entities()` method is used, which:
1. Runs `html_entity_decode()` twice to handle double-encoded entities
2. Uses `ENT_QUOTES | ENT_HTML5` and `UTF-8` encoding
3. Properly handles entities like:
   - `&amp;` → `&`
   - `&lt;` → `<`
   - `&gt;` → `>`
   - `&quot;` → `"`
   - Turkish characters

## User Nicename (Slug) Implementation

WordPress's `user_nicename` field is used, which:
- Is automatically generated by WordPress when a user is created
- Is URL-friendly (lowercase, hyphens instead of spaces)
- Can be updated using `wp_update_user()`
- Can be queried using `get_user_by('slug', $slug)`

**Example:**
- Display name: `Dr. Enver Mahir Gülcan`
- user_nicename: `dr-enver-mahir-gulcan`
- URL: `/expert/dr-enver-mahir-gulcan`

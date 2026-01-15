# Quick Start Guide - Featured Content API

## For Frontend Developers

### 1. Fetch Featured Content

```javascript
// Get all featured content (mixed types)
const response = await fetch('/wp-json/kg/v1/featured?limit=10');
const { success, data } = await response.json();

// Get only recipes
const recipes = await fetch('/wp-json/kg/v1/featured?type=recipe&limit=5');

// Get only blog posts
const posts = await fetch('/wp-json/kg/v1/featured?type=post&limit=5');

// Get only questions
const questions = await fetch('/wp-json/kg/v1/featured?type=question&limit=5');

// Get only sponsored content
const sponsors = await fetch('/wp-json/kg/v1/featured?type=sponsor&limit=5');
```

### 2. Render Content by Type

```jsx
// React example
{data.map(item => {
  switch(item.type) {
    case 'recipe':
      return (
        <RecipeCard
          key={item.id}
          title={item.title}
          image={item.image}
          ageGroupColor={item.meta.age_group_color}
          prepTime={item.meta.prep_time}
          rating={item.meta.rating}
          ratingCount={item.meta.rating_count}
        />
      );
      
    case 'post':
      return (
        <BlogCard
          key={item.id}
          title={item.title}
          image={item.image}
          author={item.meta.author}
          category={item.meta.category}
          readTime={item.meta.read_time}
        />
      );
      
    case 'question':
      return (
        <QuestionCard
          key={item.id}
          title={item.title}
          authorInitials={item.meta.author_initials}
          answerCount={item.meta.answer_count}
        />
      );
      
    case 'sponsor':
      return (
        <SponsorCard
          key={item.id}
          title={item.title}
          image={item.image}
          sponsorName={item.meta.sponsor_name}
          sponsorLogo={item.meta.sponsor_logo}
          hasDiscount={item.meta.has_discount}
        />
      );
  }
})}
```

### 3. Rate a Recipe

```javascript
async function rateRecipe(recipeId, rating) {
  try {
    const response = await fetch(`/wp-json/kg/v1/recipes/${recipeId}/rate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getJWTToken()}` // User must be logged in
      },
      body: JSON.stringify({ rating })
    });
    
    const result = await response.json();
    console.log('New average:', result.rating);
    console.log('Total ratings:', result.rating_count);
    return result;
  } catch (error) {
    console.error('Failed to rate recipe:', error);
  }
}

// Usage
await rateRecipe(123, 4.5);
```

## For WordPress Admins

### Mark Content as Featured

#### Recipes
```php
// Via admin or programmatically
update_post_meta($recipe_id, '_kg_is_featured', '1');
```

#### Blog Posts
```php
update_post_meta($post_id, '_kg_is_featured', '1');
```

#### Questions/Discussions
```php
update_post_meta($discussion_id, '_kg_is_featured', '1');
update_post_meta($discussion_id, '_kg_answer_count', 24); // Optional: override comment count
```

#### Sponsored Posts
```php
update_post_meta($post_id, '_kg_is_sponsored', '1');
update_post_meta($post_id, '_kg_sponsor_name', 'Brand Name');
update_post_meta($post_id, '_kg_sponsor_url', 'https://brand.com');
update_post_meta($post_id, '_kg_sponsor_logo', $attachment_id); // Upload logo first
update_post_meta($post_id, '_kg_has_discount', '1'); // Optional
update_post_meta($post_id, '_kg_discount_text', 'İndirim'); // Optional
```

### Remove from Featured
```php
delete_post_meta($post_id, '_kg_is_featured');
```

## Age Group Colors

Use these hex codes for UI elements:

```css
/* 6-8 Ay - Başlangıç */
.age-6-8 { background-color: #FFAB91; }

/* 9-11 Ay - Keşif */
.age-9-11 { background-color: #A5D6A7; }

/* 12-24 Ay - Geçiş */
.age-12-24 { background-color: #90CAF9; }

/* 2+ Yaş - Gurme */
.age-2-plus { background-color: #CE93D8; }
```

## API Response Types

### Recipe Meta
```typescript
interface RecipeMeta {
  age_group: string;           // "12+ Ay"
  age_group_color: string;     // "#CE93D8"
  prep_time: string;           // "25 dk"
  rating: number;              // 4.8
  rating_count: number;        // 120
  meal_type: string;           // "Ana Öğün"
  diet_types: string[];        // ["Şekersiz", "Glutensiz"]
  expert: {
    name: string;              // "Hande Özyılmaz"
    title: string;             // "Dyt."
    approved: boolean;         // true
  }
}
```

### Post Meta
```typescript
interface PostMeta {
  category: string;            // "Uyku & Beslenme"
  author: string;              // "Dr. Ayşe Yılmaz"
  read_time: string;           // "5 dk"
}
```

### Question Meta
```typescript
interface QuestionMeta {
  author_name: string;         // "Deniz'in Annesi"
  author_initials: string;     // "DA"
  answer_count: number;        // 24
}
```

### Sponsor Meta
```typescript
interface SponsorMeta {
  sponsor_name: string;        // "Organik Bebek"
  sponsor_logo: string;        // "https://..."
  sponsor_light_logo: string;  // "https://..." (for dark backgrounds)
  sponsor_url: string;         // "https://..."
  category: string;            // "Ara Öğün"
  has_discount: boolean;       // true
  discount_text: string;       // "İndirim"
}
```

## Common Tasks

### Filter by Multiple Criteria
```javascript
// Get only featured recipes
const recipes = await fetch('/wp-json/kg/v1/featured?type=recipe&limit=20');

// Then filter on frontend
const filteredRecipes = recipes.data.filter(recipe => 
  recipe.meta.age_group === '12+ Ay' &&
  recipe.meta.diet_types.includes('Glutensiz')
);
```

### Display Rating Stars
```javascript
function StarRating({ rating, ratingCount }) {
  const stars = Math.round(rating);
  return (
    <div>
      {'⭐'.repeat(stars)}
      <span>({rating.toFixed(1)})</span>
      <span>{ratingCount} değerlendirme</span>
    </div>
  );
}
```

### Check User's Rating
```javascript
// Rating endpoint stores per-user ratings
// To get user's current rating, you'd need to track it separately
// or add a new endpoint (future enhancement)
```

## Troubleshooting

### Featured content not showing?
- Check if `_kg_is_featured` meta is set to '1'
- Ensure post status is 'publish'
- Verify post type is correct ('recipe', 'post', 'discussion')

### Colors not displaying?
- Age group colors are in `meta.age_group_color`
- Default fallback is `#87CEEB` (light blue)
- Make sure age group taxonomy is assigned to recipe

### Rating not working?
- User must be authenticated (JWT token required)
- Rating must be between 1 and 5
- Check browser console for auth errors

### Sponsor logos not showing?
- Logos should be uploaded as WordPress media
- Use attachment ID in `_kg_sponsor_logo` meta
- API automatically converts to URL

## Performance Tips

1. **Limit Results**: Always use reasonable `limit` parameter
```javascript
// Good: limit=10
// Bad: limit=100 (may be slow)
```

2. **Filter on Server**: Use `type` parameter instead of fetching all
```javascript
// Better
fetch('/wp-json/kg/v1/featured?type=recipe&limit=5')

// Slower
fetch('/wp-json/kg/v1/featured?limit=50')
  .then(data => data.filter(item => item.type === 'recipe'))
```

3. **Cache Results**: Cache on frontend for 5-10 minutes
```javascript
const cache = new Map();
async function getCachedFeatured(type, limit) {
  const key = `${type}-${limit}`;
  const cached = cache.get(key);
  
  if (cached && Date.now() - cached.timestamp < 300000) { // 5 min
    return cached.data;
  }
  
  const response = await fetch(`/wp-json/kg/v1/featured?type=${type}&limit=${limit}`);
  const data = await response.json();
  
  cache.set(key, { data, timestamp: Date.now() });
  return data;
}
```

## Support

- Full API documentation: `FEATURED_API_DOCUMENTATION.md`
- Implementation details: `IMPLEMENTATION_SUMMARY_FEATURED_API.md`
- Test examples: `test-featured-api.php`

# Slug Lookup, Frontend Links & Redirect - Quick Start Guide

## 🚀 Quick Start

### 1. Configuration

Add to your `wp-config.php`:

```php
define('KG_FRONTEND_URL', 'https://kidsgourmet.com.tr');
```

### 2. Activate Plugin

The features are automatically enabled when the KG-Core plugin is active.

### 3. Flush Rewrite Rules

After plugin activation, flush WordPress rewrite rules:

**Option A: Via WordPress Admin**
- Go to Settings → Permalinks
- Click "Save Changes" (no changes needed)

**Option B: Via Code**
```php
flush_rewrite_rules();
```

## 📡 API Usage

### Lookup Endpoint

**Endpoint:** `GET /wp-json/kg/v1/lookup?slug={slug}`

**Example Request:**
```bash
curl "https://api.kidsgourmet.com.tr/wp-json/kg/v1/lookup?slug=brokoli-corbasi"
```

**Example Response:**
```json
{
  "found": true,
  "type": "recipe",
  "slug": "brokoli-corbasi",
  "id": 123,
  "redirect": "/tarifler/brokoli-corbasi"
}
```

### Frontend URL Format

| Content Type | URL Pattern |
|--------------|-------------|
| Recipe | `/tarifler/{slug}` |
| Post | `/kesfet/{slug}` |
| Ingredient | `/beslenme-rehberi/{slug}` |
| Discussion | `/topluluk/soru/{slug}` |
| Category | `/kesfet/kategori/{slug}` |
| Tag | `/etiket/{slug}` |

## 🎨 Admin Features

### Frontend View Button

When editing published content:

1. **Admin Bar Button**: Orange "Frontend'de Görüntüle" button appears in the top admin bar
2. **Post List**: "View" links in post lists open frontend URLs
3. **Edit Page Notice**: Blue info box shows frontend URL below post title

### Supported Post Types

- ✅ Recipes (recipe)
- ✅ Blog Posts (post)
- ✅ Ingredients (ingredient)
- ✅ Discussions (discussion)

## 🔀 Automatic Redirects

All public-facing WordPress URLs automatically redirect to the frontend site:

**Redirected:**
- `api.kidsgourmet.com.tr/brokoli-corbasi` → `kidsgourmet.com.tr/tarifler/brokoli-corbasi`
- `api.kidsgourmet.com.tr/bebek-bakimi` → `kidsgourmet.com.tr/kesfet/kategori/bebek-bakimi`

**Not Redirected:**
- `api.kidsgourmet.com.tr/wp-admin/*` ✅
- `api.kidsgourmet.com.tr/wp-json/*` ✅
- `api.kidsgourmet.com.tr/wp-content/*` ✅

## 🧪 Testing

### Run Unit Tests

```bash
cd wp-content/plugins/kg-core
php tests/test-slug-lookup-unit.php
```

Expected output:
```
=== Unit Tests for Slug Lookup & Frontend Features ===
✓ All files exist
✓ All files have valid PHP syntax
✓ All classes have correct namespace and class declarations
...
```

### Test API Endpoint

```bash
# Test with a real recipe slug
curl "https://api.kidsgourmet.com.tr/wp-json/kg/v1/lookup?slug=YOUR_RECIPE_SLUG"

# Test with non-existent slug
curl "https://api.kidsgourmet.com.tr/wp-json/kg/v1/lookup?slug=non-existent"
```

## ⚙️ Advanced Configuration

### Custom Frontend URL

If you need different frontend URLs per environment:

```php
// Production
if (defined('WP_ENV') && WP_ENV === 'production') {
    define('KG_FRONTEND_URL', 'https://kidsgourmet.com.tr');
}
// Staging
else if (defined('WP_ENV') && WP_ENV === 'staging') {
    define('KG_FRONTEND_URL', 'https://staging.kidsgourmet.com.tr');
}
// Development
else {
    define('KG_FRONTEND_URL', 'http://localhost:3000');
}
```

### Disable Frontend Redirect

If you need to temporarily disable automatic redirects:

```php
// In your theme's functions.php or a custom plugin
remove_action('template_redirect', [FrontendRedirect_instance, 'maybe_redirect_to_frontend'], 1);
remove_action('parse_request', [FrontendRedirect_instance, 'early_redirect_check'], 1);
```

## 🐛 Troubleshooting

### Lookup Endpoint Returns 404

**Problem:** API endpoint not accessible  
**Solution:**
```bash
# Via WordPress admin
Go to Settings → Permalinks → Save Changes

# Or via code
flush_rewrite_rules();
```

### Admin View Button Not Showing

**Checklist:**
- ✅ Is the post published? (Drafts don't show the button)
- ✅ Is the post type supported? (recipe, post, ingredient, discussion)
- ✅ Are you on the post edit page?

### Infinite Redirect Loop

**Problem:** Site redirects in a loop  
**Cause:** `KG_FRONTEND_URL` may be pointing to the wrong domain  
**Solution:**
```php
// Check your wp-config.php
define('KG_FRONTEND_URL', 'https://kidsgourmet.com.tr'); // NOT api.kidsgourmet.com.tr
```

### 404 on Frontend After Redirect

**Problem:** Content redirects but frontend shows 404  
**Cause:** Frontend doesn't have the content or route configured  
**Solution:** Verify frontend has proper routing for the content type

## 📚 Additional Resources

- [Full Implementation Documentation](docs/SLUG_LOOKUP_IMPLEMENTATION.md)
- [KG-Core Plugin README](README.md)

## 🆘 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review error logs: `wp-content/debug.log`
3. Enable WordPress debug mode:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

## 📄 License

Part of KG-Core plugin for KidsGourmet.

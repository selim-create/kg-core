# Slug Lookup Endpoint, Frontend View Links & Redirect - Implementation Documentation

## Overview

This implementation adds three new features to the KG-Core WordPress plugin:

1. **Slug Lookup Endpoint**: A REST API endpoint to lookup content by slug and get redirect URLs
2. **Frontend View Links**: Admin UI enhancements to preview content on the frontend
3. **Frontend Redirect**: Automatic redirection of non-admin requests to the frontend site

## 1. Slug Lookup Endpoint

### Endpoint Details

- **URL**: `/wp-json/kg/v1/lookup`
- **Method**: `GET`
- **Parameter**: `slug` (required, sanitized via `sanitize_title`)
- **Permission**: Public (no authentication required)

### Supported Content Types

- `recipe` → `/tarifler/{slug}`
- `post` → `/kesfet/{slug}`
- `ingredient` → `/beslenme-rehberi/{slug}`
- `discussion` → `/topluluk/soru/{slug}`
- `category` → `/kesfet/kategori/{slug}`
- `post_tag` → `/etiket/{slug}`

### Response Format

**Success Response (Content Found):**
```json
{
  "found": true,
  "type": "recipe",
  "slug": "brokoli-corbasi-9-ay",
  "id": 123,
  "redirect": "/tarifler/brokoli-corbasi-9-ay"
}
```

**Not Found Response:**
```json
{
  "found": false,
  "type": null,
  "slug": "non-existent-slug",
  "id": null,
  "redirect": null
}
```

### Usage Examples

```bash
# Lookup a recipe
curl "https://api.kidsgourmet.com.tr/wp-json/kg/v1/lookup?slug=brokoli-corbasi"

# Lookup a blog post
curl "https://api.kidsgourmet.com.tr/wp-json/kg/v1/lookup?slug=bebeklerde-reflu"

# Lookup a category
curl "https://api.kidsgourmet.com.tr/wp-json/kg/v1/lookup?slug=bebek-bakimi"
```

## 2. Frontend View Links

### Features

1. **Admin Bar Button**: Adds a "Frontend'de Görüntüle" button in the admin bar when editing published content
2. **Post List Actions**: Modifies "View" links in post lists to open frontend URLs
3. **Edit Page Notice**: Displays frontend URL in a blue info box on post edit pages
4. **Permalink Override**: Changes WordPress permalinks to frontend URLs in admin context

### Supported Post Types

- `recipe` (Tarif)
- `post` (Yazı)
- `ingredient` (Malzeme)
- `discussion` (Tartışma)

### CSS Styling

Custom admin styles are injected via `admin_head` hook:
- Blue info box with external link icon
- Orange highlighted admin bar button
- Consistent color scheme (#1c7ed6 for links, #f59f00 for buttons)

## 3. Frontend Redirect

### Purpose

Redirects all non-admin, non-API requests from the WordPress backend domain to the frontend site, ensuring:
- Users don't accidentally access WordPress public pages
- SEO is maintained with proper 301 redirects
- Admin and API functionality remains intact

### Excluded Paths (No Redirect)

The following paths are NOT redirected:
- `/wp-admin/*` - WordPress admin panel
- `/wp-login.php` - Login page
- `/wp-json/*` - REST API endpoints
- `/wp-content/*` - Media and assets
- `/wp-includes/*` - WordPress core files
- `/xmlrpc.php` - XML-RPC
- `/wp-cron.php` - Cron jobs
- `/favicon.ico` - Favicon
- `/robots.txt` - Robots file
- `/sitemap*` - Sitemap files

### Redirect Logic

1. **Homepage**: Redirects to frontend root URL
2. **Content Pages**: Looks up slug and redirects to appropriate frontend path
3. **404 Fallback**: Redirects unknown slugs to `/kesfet/{slug}` (handled by frontend)

### Implementation Details

- Uses `parse_request` hook for early detection
- Falls back to `template_redirect` hook
- Respects `is_admin()`, `wp_doing_ajax()`, and `REST_REQUEST` checks
- Performs 301 (permanent) redirects

## Configuration

### Required Constant

Add to `wp-config.php`:

```php
define('KG_FRONTEND_URL', 'https://kidsgourmet.com.tr');
```

**Default**: If not defined, defaults to `https://kidsgourmet.com.tr`

## File Structure

```
kg-core/
├── includes/
│   ├── API/
│   │   └── LookupController.php       # Slug lookup endpoint
│   ├── Admin/
│   │   └── FrontendViewLinks.php      # Frontend view buttons
│   └── Redirect/
│       └── FrontendRedirect.php       # Frontend redirect logic
├── kg-core.php                         # Main plugin file (updated)
└── tests/
    ├── test-slug-lookup.php           # WordPress integration tests
    └── test-slug-lookup-unit.php      # Standalone unit tests
```

## Testing

### Unit Tests

Run standalone unit tests (no WordPress required):

```bash
cd /path/to/kg-core
php tests/test-slug-lookup-unit.php
```

**Tests Include:**
- File existence verification
- PHP syntax validation
- Namespace and class declarations
- Method existence checks
- Integration with kg-core.php
- Content type prefix consistency

### Integration Tests

Run WordPress integration tests (requires WordPress installation):

```bash
cd /path/to/kg-core
php tests/test-slug-lookup.php
```

**Tests Include:**
- LookupController instantiation
- FrontendViewLinks functionality
- API endpoint registration
- Real content lookups (recipes, posts)
- Non-existent slug handling
- Frontend URL generation

## Security Considerations

### Implemented Protections

1. **Input Sanitization**: All slugs are sanitized via `sanitize_title()`
2. **URI Sanitization**: `$_SERVER['REQUEST_URI']` is sanitized with `esc_url_raw()`
3. **Permission Checks**: Admin features only load in admin context
4. **Redirect Safety**: Excluded paths prevent infinite loops
5. **XSS Prevention**: All output is escaped with `esc_url()`, `esc_html()`

### Potential Concerns

- FrontendRedirect runs on every frontend request (performance impact minimal)
- 301 redirects are cached by browsers (test thoroughly before deployment)

## Performance

- **Lookup Endpoint**: Single database query per lookup
- **Frontend Links**: No additional queries (uses existing post data)
- **Redirect**: Minimal overhead (path matching only)

## Troubleshooting

### Issue: Lookup endpoint returns 404
**Solution**: Flush rewrite rules
```php
// In WordPress admin or via code
flush_rewrite_rules();
```

### Issue: Admin redirect loops
**Solution**: Check `KG_FRONTEND_URL` constant is correctly set and doesn't point to admin domain

### Issue: Frontend view button not appearing
**Solution**: Ensure content is published (draft posts don't show the button)

## Changelog

### Version 1.0.0
- Initial implementation of slug lookup endpoint
- Added frontend view links in admin
- Implemented frontend redirect logic
- Added comprehensive unit tests
- Security improvements (sanitize REQUEST_URI)

## Future Enhancements

Potential improvements for future versions:

1. **Caching**: Add transient caching for frequently looked up slugs
2. **Expert Support**: Add expert custom post type to URL prefixes
3. **Preview Mode**: Support draft content previews with temporary tokens
4. **Analytics**: Log redirect patterns for debugging
5. **Batch Lookup**: Support multiple slugs in single API call

## Credits

- **Developed for**: KidsGourmet (kidsgourmet.com.tr)
- **Plugin**: KG Core
- **Version**: 1.0.0

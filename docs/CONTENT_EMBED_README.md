# Content Embed System - Quick Start Guide

> **Status:** ✅ Production Ready  
> **Version:** 1.0.0  
> **Date:** 2026-01-18

## Overview

A complete WordPress content embed system that allows embedding recipes, ingredients, tools, and posts within standard WordPress posts using shortcodes and a user-friendly admin interface.

## Quick Start

### 1. Basic Usage

```php
// Single item
[kg-embed type="recipe" id="123"]

// Multiple items
[kg-embed type="ingredient" ids="456,789,101"]
```

### 2. Admin UI

1. Edit any WordPress post
2. Click "İçerik Embed Et" button next to "Add Media"
3. Select content type tab (Tarifler, Malzemeler, Araçlar, Keşfet)
4. Search and select items
5. Click "Embed Ekle"

### 3. REST API

Access embedded content via WordPress REST API:

```bash
GET /wp-json/wp/v2/posts/{id}
```

Response includes `embedded_content` field with full metadata.

## Supported Content Types

| Type | Description | Fields |
|------|-------------|--------|
| `recipe` | Tarifler | prep_time, age_group, diet_types, allergens, is_featured |
| `ingredient` | Malzemeler | start_age, benefits, allergy_risk, allergens, season |
| `tool` | Araçlar | tool_type, tool_icon, tool_types, is_active |
| `post` | Keşfet | category, author, date, read_time |

## Files Overview

### Backend (PHP)
- `includes/Shortcodes/ContentEmbed.php` - Shortcode logic & REST API
- `includes/Admin/EmbedSelector.php` - Admin UI & AJAX handler

### Frontend
- `assets/css/embed-selector.css` - Modal styling
- `assets/js/embed-selector.js` - Modal logic & AJAX

### Tests
- `tests/test-content-embed-system.php` - Full test suite
- `tests/validate-content-embed-static.php` - Static validation

### Documentation
- `docs/CONTENT_EMBED_IMPLEMENTATION.md` - Complete guide
- `docs/SECURITY_SUMMARY.md` - Security analysis
- `docs/ARCHITECTURE.md` - Architecture diagrams

## Example Response

```json
{
  "embedded_content": [
    {
      "type": "recipe",
      "position": 2,
      "placeholder_id": "kg-embed-0",
      "items": [
        {
          "id": 123,
          "title": "Havuçlu Bebek Püresi",
          "prep_time": "15 dk",
          "age_group": "6-8 Ay",
          "age_group_color": "#FFAB91",
          "diet_types": ["Vejetaryen"],
          "allergens": [],
          "is_featured": false,
          "url": "/tarifler/havuclu-bebek-puresi",
          "image": "https://...",
          "excerpt": "..."
        }
      ]
    }
  ]
}
```

## Testing

### Run Full Test Suite
```bash
wp eval-file tests/test-content-embed-system.php
```

### Run Static Validation
```bash
php tests/validate-content-embed-static.php
```

## Security

- ✅ All inputs sanitized and validated
- ✅ XSS protection via proper escaping
- ✅ CSRF protection with nonces
- ✅ SQL injection prevented via WP Query API
- ✅ CodeQL scan: 0 vulnerabilities

## Performance

- Minimal database queries
- REST-only processing (no frontend overhead)
- Compatible with object caching
- Lazy loading support

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- IE11+ (with polyfills)

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Modern browser for admin UI

## Changelog

### 1.0.0 (2026-01-18)
- Initial implementation
- Shortcode system
- Admin UI with modal
- REST API integration
- Full test coverage
- Complete documentation

## Support

For issues or questions, refer to:
- [Implementation Guide](docs/CONTENT_EMBED_IMPLEMENTATION.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Security](docs/SECURITY_SUMMARY.md)

## License

Part of KG Core plugin - same license applies

---

**Built with ❤️ for KidsGourmet**

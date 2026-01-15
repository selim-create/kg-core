# Tool Seeder Documentation

## Overview
The Tool Seeder system automatically creates and manages "Araç" (tool) posts in WordPress for the KidsGourmet platform. These tools are required by the SponsoredToolController API endpoints.

## Problem Solved
Before this implementation:
- Frontend was receiving 404 errors on tool endpoints
- `SponsoredToolController::get_tool_by_slug()` couldn't find tools
- No tools existed in the database
- Manual creation was tedious and error-prone

## Installation

### Automatic Seeding (Recommended)
When you **activate or update** the kg-core plugin, all 13 tools are automatically created if they don't exist:

1. Go to WordPress Admin → Plugins
2. Activate "KG Core (KidsGourmet)" plugin
3. All missing tools are automatically created

### Manual Seeding
You can also manually manage tools through the admin interface:

1. Go to WordPress Admin → **Araçlar** → **🛠️ Araçları Oluştur**
2. View the status table showing all tools
3. Use individual buttons to create/update specific tools
4. Or use bulk operations:
   - **"Tüm Araçları Oluştur"** - Creates all missing tools
   - **"Tüm Araçları Güncelle"** - Updates all existing tools

## Tools Created

All 13 tools are automatically created with the following configuration:

| # | Tool Name | Slug | Tool Type | Icon | Active | Auth Required | Sponsored |
|---|-----------|------|-----------|------|--------|---------------|-----------|
| 1 | Banyo Rutini Planlayıcı | `bath-planner` | `bath_planner` | `fa-bath` | ✓ | ✗ | ✗ |
| 2 | Günlük Hijyen İhtiyacı Hesaplayıcı | `hygiene-calculator` | `hygiene_calculator` | `fa-hand-sparkles` | ✓ | ✗ | ✗ |
| 3 | Akıllı Bez Hesaplayıcı | `diaper-calculator` | `diaper_calculator` | `fa-baby` | ✓ | ✗ | ✗ |
| 4 | Hava Kalitesi Rehberi | `air-quality` | `air_quality_guide` | `fa-wind` | ✓ | ✗ | ✗ |
| 5 | Leke Ansiklopedisi | `stain-encyclopedia` | `stain_encyclopedia` | `fa-tshirt` | ✓ | ✗ | ✗ |
| 6 | BLW Hazırlık Testi | `blw-testi` | `blw_test` | `fa-baby` | ✓ | ✗ | ✗ |
| 7 | Persentil Hesaplayıcı | `persentil` | `percentile` | `fa-chart-line` | ✓ | ✗ | ✗ |
| 8 | Su İhtiyacı Hesaplayıcı | `su-ihtiyaci` | `water_calculator` | `fa-glass-water` | ✓ | ✗ | ✗ |
| 9 | Ek Gıda Rehberi | `ek-gida-rehberi` | `food_guide` | `fa-carrot` | ✓ | ✗ | ✗ |
| 10 | Ek Gıdaya Başlama Kontrolü | `ek-gidaya-baslama` | `solid_food_readiness` | `fa-utensils` | ✓ | ✗ | ✗ |
| 11 | Bu Gıda Verilir mi? | `bu-gida-verilir-mi` | `food_checker` | `fa-check-circle` | ✓ | ✗ | ✗ |
| 12 | Alerjen Deneme Planlayıcı | `alerjen-planlayici` | `allergen_planner` | `fa-shield-heart` | ✓ | ✗ | ✗ |
| 13 | Besin Deneme Takvimi | `besin-takvimi` | `food_trial_calendar` | `fa-calendar-check` | ✓ | ✗ | ✗ |

## Metadata Saved for Each Tool

Each tool is created with the following metadata:

### WordPress Post Data
- `post_title` - Tool name (e.g., "Banyo Rutini Planlayıcı")
- `post_name` - Slug (e.g., "bath-planner")
- `post_content` - Description
- `post_type` - "tool"
- `post_status` - "publish"

### Custom Meta Fields
- `_kg_tool_type` - Tool type identifier (e.g., "bath_planner")
- `_kg_tool_icon` - FontAwesome icon class (e.g., "fa-bath")
- `_kg_is_active` - Whether tool is active (default: "1")
- `_kg_requires_auth` - Whether login is required (default: "0")
- `_kg_tool_is_sponsored` - Whether tool is sponsored (default: "0")

### ACF Fields (if ACF is installed)
The same data is also saved to ACF fields:
- `tool_type`
- `tool_icon`
- `is_active`
- `requires_auth`

## API Integration

### REST API Field
Tools are exposed via WordPress REST API with a `sponsor_data` field:

```
GET /wp-json/wp/v2/tool/{id}
```

Response for **non-sponsored** tools:
```json
{
  "id": 123,
  "title": "Banyo Rutini Planlayıcı",
  "slug": "bath-planner",
  "sponsor_data": null
}
```

### SponsoredToolController Endpoints
These tools work with existing API endpoints:

- `GET /wp-json/kg/v1/tools/bath-planner/config`
- `POST /wp-json/kg/v1/tools/bath-planner/generate`
- `POST /wp-json/kg/v1/tools/hygiene-calculator/calculate`
- `POST /wp-json/kg/v1/tools/diaper-calculator/calculate`
- `POST /wp-json/kg/v1/tools/diaper-calculator/rash-risk`
- `POST /wp-json/kg/v1/tools/air-quality/analyze`
- `GET /wp-json/kg/v1/tools/stain-encyclopedia/search`
- `GET /wp-json/kg/v1/tools/stain-encyclopedia/{slug}`

All endpoints return `sponsor_data: null` for non-sponsored tools.

## Admin UI Features

### Status Table
Shows all tools with:
- Tool name
- Slug
- Tool type
- Icon
- Status (exists or not)
- Action buttons (create/update/edit)

### Individual Operations
- **Create** - Creates a new tool (only shown if doesn't exist)
- **Update** - Updates existing tool metadata
- **Edit** - Opens WordPress post editor

### Bulk Operations
- **Seed All** - Creates all missing tools at once
- **Update All** - Updates metadata for all existing tools

### Progress Tracking
- Real-time progress bar
- Live log with timestamps
- Color-coded status messages (success, error, warning, info)

## Security Features

1. **Nonce Verification** - All AJAX requests verified with WordPress nonces
2. **Capability Check** - Requires `manage_options` capability
3. **Input Sanitization** - All user inputs sanitized
4. **Custom Script Handle** - No conflicts with other plugins

## Duplicate Prevention

The seeder prevents duplicate tools:
- Checks if tool already exists before creating
- Uses `get_page_by_path()` to find existing tools by slug
- Provides "update" mode to modify existing tools
- On plugin activation, only creates missing tools

## Testing

Run the test suite to verify functionality:

```bash
# Unit tests (53 tests)
php tests/test-tool-seeder.php

# API compatibility tests (33 tests)
php tests/test-tool-seeder-api.php

# Integration tests (requires WordPress)
php tests/test-tool-seeder-integration.php
```

Expected output:
```
✅ ALL TESTS PASSED!
Passed: 86
Failed: 0
```

## Developer Usage

### Programmatic Seeding

```php
// Seed all tools automatically
if (class_exists('\KG_Core\Admin\ToolSeeder')) {
    \KG_Core\Admin\ToolSeeder::seed_on_activation();
}

// Create a single tool
$seeder = new \KG_Core\Admin\ToolSeeder();
$tool_data = [
    'title' => 'My Custom Tool',
    'slug' => 'my-custom-tool',
    'description' => 'Tool description',
    'tool_type' => 'custom_type',
    'icon' => 'fa-wrench',
    'is_active' => true,
    'requires_auth' => false,
    'is_sponsored' => false,
];
$post_id = $seeder->seed_tool($tool_data);
```

### Checking Tool Status

```php
// Get all tools status
$seeder = new \KG_Core\Admin\ToolSeeder();
$status = $seeder->get_tools_status();

foreach ($status as $slug => $data) {
    echo "Tool: $slug, ID: {$data['id']}, Status: {$data['status']}\n";
}
```

## Troubleshooting

### Tools Not Created on Activation
1. Check if plugin is properly activated
2. Verify file permissions
3. Check WordPress error logs
4. Manually trigger from admin UI

### 404 Errors on API Endpoints
1. Verify tools are created (check admin UI)
2. Flush WordPress permalinks (Settings → Permalinks → Save)
3. Check tool slugs match API endpoints
4. Verify post_status is "publish"

### Duplicate Tools Created
1. Use "Update" mode instead of "Create"
2. Delete duplicates manually
3. Use `get_tools_status()` to check existing tools

### Nonce Verification Failed
1. Clear browser cache
2. Reload admin page to get fresh nonce
3. Check for JavaScript errors in console

## File Structure

```
kg-core/
├── includes/
│   ├── Admin/
│   │   └── ToolSeeder.php           # Main seeder class
│   ├── API/
│   │   └── SponsoredToolController.php  # API endpoints
│   └── PostTypes/
│       └── Tool.php                  # Tool post type
├── tests/
│   ├── test-tool-seeder.php         # Unit tests
│   ├── test-tool-seeder-api.php     # API tests
│   └── test-tool-seeder-integration.php  # Integration tests
└── kg-core.php                       # Main plugin file
```

## Code Quality

- ✅ Follows WordPress coding standards
- ✅ Proper namespacing (`KG_Core\Admin`)
- ✅ Comprehensive error handling
- ✅ Security best practices
- ✅ Well-documented code
- ✅ Tested with 86 passing tests

## Support

For issues or questions:
1. Check test results to verify setup
2. Review WordPress error logs
3. Use admin UI to manually verify tools
4. Check API responses for sponsor_data field

## Changelog

### Version 1.0.0 (2026-01-15)
- ✅ Initial release
- ✅ 13 tools auto-seeded
- ✅ Admin UI implemented
- ✅ Security features added
- ✅ Comprehensive test suite
- ✅ API integration complete
- ✅ Bug fix: air-quality slug corrected

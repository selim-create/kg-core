# Tool Seeder Admin UI - Quick Reference

## Access
WordPress Admin → **Araçlar** → **🛠️ Araçları Oluştur**

## Page Sections

### 1. Information Banner (Blue)
```
ℹ️ Bilgi:
• Zaten var olan araçlar atlanır (duplicate kontrolü)
• Oluşturulan araçlar yayınlanmış (published) olarak kaydedilir
• Her araç için gerekli meta veriler otomatik eklenir
• Sponsorlu olmayan araçlar sponsor_data: null döner
```

### 2. Status Table
| Column | Description |
|--------|-------------|
| Araç Adı | Full tool name in Turkish |
| Slug | URL-friendly identifier |
| Tool Type | Backend identifier |
| İkon | FontAwesome icon class |
| Durum | ✓ Mevcut (exists) or ⚠ Yok (missing) |
| İşlem | Action buttons |

### 3. Action Buttons

**For Existing Tools:**
- **Düzenle** - Opens WordPress post editor
- **Güncelle** - Updates metadata

**For Missing Tools:**
- **Oluştur** - Creates the tool

### 4. Bulk Operations
```
🚀 Toplu İşlemler

[Tüm Araçları Oluştur (Eksik Olanlar)]  ← Creates all missing tools
[Tüm Araçları Güncelle (Mevcut Olanlar)] ← Updates all existing tools
```

### 5. Progress Section (appears during operations)
```
⏳ İlerleme

İlerleme: 5/13
[████████████░░░░░░░░░░░░] 38%

Log:
[14:23:45] 🔄 İşleniyor: Banyo Rutini Planlayıcı (bath-planner)...
[14:23:46] ✅ Banyo Rutini Planlayıcı - Oluşturuldu
[14:23:47] 🔄 İşleniyor: Günlük Hijyen İhtiyacı...
```

## Common Workflows

### First Time Setup
1. Activate kg-core plugin (auto-seeds all tools)
2. Or go to admin page and click "Tüm Araçları Oluştur"
3. Wait for completion
4. Verify API endpoints work

### Add a Single Missing Tool
1. Go to seeder page
2. Find the tool in status table
3. Click **Oluştur** button
4. Wait for success message

### Update Tool Metadata
1. Go to seeder page
2. Find the tool in status table
3. Click **Güncelle** button
4. Metadata refreshed from template

### Edit Tool Content
1. Go to seeder page
2. Click **Düzenle** button
3. Standard WordPress editor opens
4. Edit title, description, etc.
5. Click **Yayınla** to save

## Log Message Types

| Icon | Type | Meaning |
|------|------|---------|
| 🔄 | Info | Processing in progress |
| ✅ | Success | Action completed successfully |
| ⚠️ | Warning | Non-critical issue (e.g., already exists) |
| ❌ | Error | Failed to complete action |
| 🎉 | Success | All operations completed |

## Status Messages

### In Status Table
- **✓ Mevcut (ID: 123)** - Tool exists with ID 123
- **⚠ Yok** - Tool doesn't exist yet

### In Progress Log
- **"Oluşturuldu"** - Successfully created
- **"Güncellendi"** - Successfully updated
- **"Zaten mevcut"** - Skipped (already exists)
- **"Hata: ..."** - Error occurred

## Tips

✅ **Do:**
- Use bulk create on first setup
- Check status table before creating
- Review logs for errors
- Verify API endpoints after seeding

❌ **Don't:**
- Click buttons multiple times (duplicate risk)
- Close page during bulk operations
- Manually delete tool posts (use admin UI)

## Keyboard Shortcuts
None currently implemented.

## Browser Compatibility
- ✅ Chrome/Edge (recommended)
- ✅ Firefox
- ✅ Safari
- ⚠️ IE11 (not supported)

## Performance
- Single tool: ~1-2 seconds
- Bulk create (13 tools): ~15-20 seconds
- Progress updates in real-time

## Mobile Support
⚠️ Desktop only - use responsive WordPress admin

## Accessibility
- Basic screen reader support
- Keyboard navigation supported
- Color-blind friendly (icons + text)

## Security
- Requires `manage_options` capability
- Nonce verification on all actions
- Input sanitization
- No direct SQL queries

## What Gets Created

Each tool post includes:
```
Title: Banyo Rutini Planlayıcı
Slug: bath-planner
Description: Bebeğiniz için mevsime göre ideal banyo sıklığını...
Status: Published

Meta:
_kg_tool_type: bath_planner
_kg_tool_icon: fa-bath
_kg_is_active: 1
_kg_requires_auth: 0
_kg_tool_is_sponsored: 0
```

## Troubleshooting

**Problem:** Button does nothing
**Solution:** Check browser console for JS errors

**Problem:** "Yetkiniz yok" error
**Solution:** Need admin/manage_options capability

**Problem:** Nonce verification failed
**Solution:** Refresh page to get new nonce

**Problem:** Progress bar stuck
**Solution:** Check browser console, refresh page

**Problem:** Duplicate tools created
**Solution:** Delete manually, use Update instead of Create

## Support Resources
- Documentation: `/docs/TOOL_SEEDER.md`
- Tests: `/tests/test-tool-seeder*.php`
- Code: `/includes/Admin/ToolSeeder.php`

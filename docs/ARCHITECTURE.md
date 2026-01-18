# Content Embed System - Architecture & Flow

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        WordPress Admin                           │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Post Editor Screen                                         │ │
│  │                                                            │ │
│  │  [Add Media] [İçerik Embed Et] ← EmbedSelector Button    │ │
│  └────────────────────────────────────────────────────────────┘ │
│                              │                                    │
│                              ▼ (Click)                            │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Embed Modal (EmbedSelector)                                │ │
│  │  ┌──────┬──────────┬────────┬─────────┐                   │ │
│  │  │Recipe│Ingredient│  Tool  │ Keşfet  │ ← Tabs            │ │
│  │  └──────┴──────────┴────────┴─────────┘                   │ │
│  │  [Search: _____________]                                   │ │
│  │  ┌────────────────────────────────────┐                   │ │
│  │  │ ☑ Havuçlu Püre                     │                   │ │
│  │  │ ☐ Elma Püresi                      │  ← Results        │ │
│  │  │ ☑ Brokoli Çorbası                  │                   │ │
│  │  └────────────────────────────────────┘                   │ │
│  │  [2 öğe seçildi]        [Embed Ekle]                      │ │
│  └────────────────────────────────────────────────────────────┘ │
│                              │                                    │
│                              ▼ (AJAX)                             │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ AJAX Endpoint: kg_search_embeddable_content                │ │
│  │ - Searches content by type                                 │ │
│  │ - Returns items with metadata                              │ │
│  │ - Nonce validation                                         │ │
│  └────────────────────────────────────────────────────────────┘ │
│                              │                                    │
│                              ▼ (Insert)                           │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │ Editor Content                                             │ │
│  │                                                            │ │
│  │ <p>Bu yazıda harika tarifler sunacağız.</p>              │ │
│  │                                                            │ │
│  │ [kg-embed type="recipe" ids="123,456"]                    │ │
│  │                                                            │ │
│  │ <p>Ayrıca malzeme bilgileri...</p>                        │ │
│  └────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

                              │
                              ▼ (Save & Publish)
                              
┌─────────────────────────────────────────────────────────────────┐
│                      WordPress Database                          │
│                                                                   │
│  wp_posts                                                        │
│  ├─ post_content: Contains [kg-embed] shortcodes                │
│  ├─ post_type: post                                             │
│  └─ post_status: publish                                        │
└─────────────────────────────────────────────────────────────────┘

                              │
                              ▼ (REST API Request)
                              
┌─────────────────────────────────────────────────────────────────┐
│                    ContentEmbed Class                            │
│                                                                   │
│  register_rest_fields()                                          │
│       │                                                           │
│       ▼                                                           │
│  extract_embeds_from_content()                                   │
│       ├─ Parse shortcodes                                        │
│       ├─ Calculate positions                                     │
│       └─ Get embed data                                          │
│            │                                                      │
│            ▼                                                      │
│       get_embed_data()                                           │
│            ├─ get_recipe_embed_data()                           │
│            ├─ get_ingredient_embed_data()                       │
│            ├─ get_tool_embed_data()                             │
│            └─ get_post_embed_data()                             │
└─────────────────────────────────────────────────────────────────┘

                              │
                              ▼ (Return)
                              
┌─────────────────────────────────────────────────────────────────┐
│                    REST API Response                             │
│                                                                   │
│  {                                                               │
│    "id": 789,                                                    │
│    "title": "Bebek Beslenmesi...",                              │
│    "content": "...",                                             │
│    "embedded_content": [                                         │
│      {                                                           │
│        "type": "recipe",                                         │
│        "position": 2,                                            │
│        "placeholder_id": "kg-embed-0",                          │
│        "items": [                                                │
│          {                                                       │
│            "id": 123,                                            │
│            "title": "Havuçlu Püre",                            │
│            "prep_time": "15 dk",                                │
│            "age_group": "6-8 Ay",                               │
│            ...                                                   │
│          }                                                       │
│        ]                                                         │
│      }                                                           │
│    ]                                                             │
│  }                                                               │
└─────────────────────────────────────────────────────────────────┘

                              │
                              ▼ (Consume)
                              
┌─────────────────────────────────────────────────────────────────┐
│                    Frontend Application                          │
│                                                                   │
│  - Parses embedded_content array                                │
│  - Renders embedded items at correct positions                  │
│  - Uses placeholder_id for tracking                             │
│  - Displays type-specific data                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Data Flow Diagram

```
User Action          System Component           Database/API
─────────────────────────────────────────────────────────────

[Click Button] ──────▶ EmbedSelector
                          │
                          │ Render Modal
                          ▼
                      [Modal Opens]
                          │
[Type in Search] ────────▶│
                          │ AJAX Request
                          ├──────────────▶ WordPress Query
                          │                      │
                          │                      ▼
                          │               Query wp_posts
                          │                      │
                          │◀─────────────── Return Items
                          │
                      [Display Results]
                          │
[Select Items] ──────────▶│
                          │
[Click Insert] ──────────▶│
                          │ Insert Shortcode
                          ├──────────────▶ Editor Content
                          
[Save Post] ─────────────────────────────▶ wp_posts table
                          
[REST Request] ──────────▶ ContentEmbed
                          │
                          │ Extract Shortcodes
                          ├──────────────▶ Parse Content
                          │
                          │ Get Embed Data
                          ├──────────────▶ Query Posts
                          │
                          │ Build Response
                          ▼
                      [Return JSON] ─────▶ Frontend App
```

## Component Interaction

```
┌─────────────────────┐         ┌──────────────────────┐
│  ContentEmbed       │         │  EmbedSelector       │
│  (Shortcode Logic)  │         │  (Admin UI)          │
├─────────────────────┤         ├──────────────────────┤
│ - Shortcode handler │         │ - Modal rendering    │
│ - REST API field    │         │ - AJAX search        │
│ - Data extraction   │         │ - Button placement   │
│ - Type getters      │         │ - Asset enqueue      │
└─────────────────────┘         └──────────────────────┘
         │                                │
         │                                │
         └────────────┬───────────────────┘
                      │
         ┌────────────▼──────────────┐
         │   WordPress Core          │
         ├───────────────────────────┤
         │ - Post meta               │
         │ - REST API                │
         │ - AJAX handlers           │
         │ - Shortcode API           │
         │ - User permissions        │
         └───────────────────────────┘
```

## File Organization

```
kg-core/
├── includes/
│   ├── Shortcodes/
│   │   └── ContentEmbed.php       ← Backend shortcode logic
│   └── Admin/
│       └── EmbedSelector.php      ← Admin UI & AJAX
├── assets/
│   ├── css/
│   │   └── embed-selector.css     ← Modal styling
│   └── js/
│       └── embed-selector.js      ← Modal logic & AJAX
├── docs/
│   ├── CONTENT_EMBED_IMPLEMENTATION.md  ← Usage guide
│   └── SECURITY_SUMMARY.md              ← Security analysis
├── tests/
│   ├── test-content-embed-system.php    ← Full test suite
│   └── validate-content-embed-static.php ← Static validation
└── kg-core.php                    ← Plugin initialization
```

## Supported Content Types

```
┌──────────────┬─────────────┬──────────────────────────────────┐
│ Type         │ Post Type   │ Special Fields                   │
├──────────────┼─────────────┼──────────────────────────────────┤
│ recipe       │ recipe      │ prep_time, age_group,           │
│              │             │ diet_types, allergens,           │
│              │             │ is_featured, age_group_color     │
├──────────────┼─────────────┼──────────────────────────────────┤
│ ingredient   │ ingredient  │ start_age, benefits,            │
│              │             │ allergy_risk, allergens, season  │
├──────────────┼─────────────┼──────────────────────────────────┤
│ tool         │ tool        │ tool_type, tool_icon,           │
│              │             │ tool_types, is_active            │
├──────────────┼─────────────┼──────────────────────────────────┤
│ post         │ post        │ category, author,                │
│              │             │ date, read_time                  │
└──────────────┴─────────────┴──────────────────────────────────┘
```

## Performance Characteristics

- **Admin UI**: Modal opens instantly (cached templates)
- **AJAX Search**: ~100-300ms response time
- **Shortcode Processing**: Frontend only (no overhead)
- **REST API**: Processes embeds on-demand
- **Database Queries**: Optimized with minimal joins
- **Caching**: Compatible with object caching

## Security Layers

```
User Input ──▶ Sanitization ──▶ Validation ──▶ Authorization ──▶ Processing
                   │                │               │
                   │                │               └── Nonce Check
                   │                └── Type & ID Validation
                   └── XSS Prevention (esc_*)
```

---

**Version:** 1.0.0  
**Last Updated:** 2026-01-18  
**Status:** Production Ready ✅

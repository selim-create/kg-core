# Expert User Integration - Data Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ADMIN INTERFACE                                 │
│  (RecipeMetaBox.php)                                                    │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────┐      │
│  │ Kayıtlı Uzman Seç:                                           │      │
│  │ ┌────────────────────────────────────────────────────────┐   │      │
│  │ │ [Select] Doç. Dr. Enver Mahir Gülcan (ID: 13)  ▼      │   │      │
│  │ └────────────────────────────────────────────────────────┘   │      │
│  │                                                              │      │
│  │ Uzman Adı: [Doç. Dr. Enver Mahir Gülcan] ← Auto-filled     │      │
│  │ Uzman Ünvanı: [Doç.Dr.]                                     │      │
│  │ Uzman Notu: [6 aydan sonra verilebilir...]                  │      │
│  └──────────────────────────────────────────────────────────────┘      │
│                                                                         │
│  JavaScript: Auto-fill name when expert selected                       │
│                                                                         │
│  Save Post Meta:                                                        │
│    • _kg_expert_user_id: 13                                            │
│    • _kg_expert_name: "Doç. Dr. Enver Mahir Gülcan"                   │
│    • _kg_expert_title: "Doç.Dr."                                       │
│    • _kg_expert_note: "6 aydan sonra verilebilir..."                  │
│    • _kg_expert_approved: 1                                            │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         DATABASE LAYER                                  │
│  (WordPress Post Meta)                                                  │
│                                                                         │
│  Recipe Post ID: 123                                                    │
│  ┌─────────────────────────────────────────────────────────────┐       │
│  │ Meta Key              │ Meta Value                          │       │
│  ├───────────────────────┼─────────────────────────────────────┤       │
│  │ _kg_expert_user_id    │ 13                        ← NEW!   │       │
│  │ _kg_expert_name       │ "Doç. Dr. Enver Mahir Gülcan"      │       │
│  │ _kg_expert_title      │ "Doç.Dr."                           │       │
│  │ _kg_expert_note       │ "6 aydan sonra verilebilir..."      │       │
│  │ _kg_expert_approved   │ "1"                                 │       │
│  └─────────────────────────────────────────────────────────────┘       │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         API CONTROLLER                                  │
│  (RecipeController.php)                                                 │
│                                                                         │
│  get_expert_data($post_id, $include_note) {                            │
│    1. Fetch _kg_expert_user_id                                         │
│    2. If user_id exists:                                               │
│       • Get user object: get_user_by('ID', user_id)                    │
│       • Extract slug: user->user_nicename                              │
│       • Get image: Helper::get_user_avatar_url(user_id)                │
│       • Fallback name: user->display_name (if name empty)              │
│    3. Return expert data array                                         │
│  }                                                                      │
│                                                                         │
│  Called by:                                                             │
│    • prepare_recipe_data() - basic expert data (all endpoints)          │
│    • full_detail section - extended data with note (single recipe)      │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         API RESPONSE                                    │
│  (JSON)                                                                 │
│                                                                         │
│  Registered Expert Response:                                           │
│  {                                                                      │
│    "expert": {                                                          │
│      "name": "Doç. Dr. Enver Mahir Gülcan",                            │
│      "title": "Doç.Dr.",                                               │
│      "note": "6 aydan sonra verilebilir...",  ← full_detail only      │
│      "image": "https://.../avatars/13/avatar.jpg",  ← NEW!            │
│      "slug": "dr-enver-mahir-gulcan",  ← NEW!                         │
│      "user_id": 13,  ← NEW!                                           │
│      "approved": true                                                   │
│    }                                                                    │
│  }                                                                      │
│                                                                         │
│  Manual Expert Response (backward compatible):                         │
│  {                                                                      │
│    "expert": {                                                          │
│      "name": "Dış Uzman",                                              │
│      "title": "Dr.",                                                    │
│      "note": "...",                                                     │
│      "image": "",  ← Empty for manual experts                          │
│      "slug": "",  ← Empty for manual experts                           │
│      "user_id": null,  ← Null for manual experts                       │
│      "approved": true                                                   │
│    }                                                                    │
│  }                                                                      │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         FRONTEND USE CASES                              │
│                                                                         │
│  1. Expert Profile Link:                                               │
│     if (expert.slug) {                                                  │
│       <a href="/uzman/{expert.slug}">{expert.name}</a>                 │
│     }                                                                   │
│                                                                         │
│  2. Expert Avatar Display:                                             │
│     if (expert.image) {                                                │
│       <img src="{expert.image}" alt="{expert.name}" />                 │
│     }                                                                   │
│                                                                         │
│  3. Conditional Features:                                              │
│     if (expert.user_id) {                                              │
│       // Show verified badge, link to profile, etc.                     │
│     }                                                                   │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                         MIGRATION TOOL                                  │
│  (ExpertMigrator.php)                                                   │
│                                                                         │
│  Admin → Uzman Migration                                               │
│                                                                         │
│  1. Preview: Shows matches (green) and non-matches (red)               │
│     ┌──────────────────────────────────────────────────────────┐       │
│     │ ID  │ Recipe        │ Current Name  │ Match  │ User ID  │       │
│     ├─────┼───────────────┼───────────────┼────────┼──────────┤       │
│     │ 123 │ Elma Püresi   │ Enver Mahir   │ ✓ Yes  │ 13      │       │
│     │ 124 │ Havuç Püresi  │ Unknown       │ ✗ No   │ -       │       │
│     └──────────────────────────────────────────────────────────┘       │
│                                                                         │
│  2. Execute: Updates _kg_expert_user_id for matched recipes            │
│                                                                         │
│  Matching Logic:                                                        │
│    1. Known expert patterns (configurable array)                        │
│    2. Exact display name match                                          │
│    3. Fuzzy matching (70%+ similarity)                                  │
└─────────────────────────────────────────────────────────────────────────┘
```

## Key Benefits

1. **Enhanced User Experience**: Frontend can now display expert profile photos and link to expert profiles
2. **Data Integrity**: Links recipes to actual WordPress users instead of free text
3. **Backward Compatible**: Existing manual entries still work perfectly
4. **Migration Support**: Tool to automatically map existing expert names to users
5. **DRY Code**: Refactored to use helper method, reducing code duplication

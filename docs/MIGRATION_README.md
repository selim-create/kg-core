# Recipe Migration System - AI-First

## Genel Bakış

Bu sistem, kidsgourmet.com.tr sitesinde blog yazısı olarak kaydedilmiş 337 adet tarifi yeni `recipe` post type'ına otomatik olarak aktarmak için geliştirilmiştir.

**YENİ YAKLAŞIM:** OpenAI GPT-4 kullanarak blog içeriğini tek seferde parse eder ve tüm alanları doldurur. HİÇBİR VERİ KAYBI OLMAZ.

## Özellikler

### AI-First Yaklaşım
- **Tam Otomatik Parsing:** Blog içeriğini OpenAI'a gönderir, tüm yapıyı tek çağrıda çıkarır
- **Sıfır Veri Kaybı:** Blog yazısındaki TÜM bilgiler (malzemeler, adımlar, uzman notu, özel notlar) korunur
- **Akıllı Ayrıştırma:** Malzeme notlarını, adım ipuçlarını, ikame malzemeleri otomatik tespit eder
- **Uzman Notu Korunur:** Uzman açıklamalarını TAMAMEN ve KESİNTİSİZ alır
- **Özel Notlar:** "Süt:", "Not:", "İpucu:" gibi tüm özel açıklamalar korunur

### Otomatik Parsing (GPT-4 ile)
- Blog içeriğinden malzemeleri otomatik çıkarır
- Hazırlanış adımlarını parse eder
- Uzman notunu ve uzman bilgilerini tespit eder
- Video URL'lerini bulur
- Beslenme değerlerini tahmin eder
- İkame malzemeleri önerir
- Alerjen bilgilerini çıkarır
- Yaş grubunu belirler

### Malzeme Standardizasyonu
- Malzeme miktarlarını standardize eder (1/2, 1/4, vs.)
- Birimleri normalleştirir (gram, litre, adet, vs.)
- Hazırlama notlarını ayırır (ince kıyılmış, rendel anmış, vs.)
- Mevcut malzeme CPT'leri ile eşleştirir
- Bulunamayan malzemeler için otomatik yeni CPT oluşturur

### Duplicate Kontrolü
- `_kg_migrated_from` meta key ile kontrol
- Aynı post ID için sadece bir kez recipe oluşturulur
- Mevcut recipe varsa yeni oluşturulmaz

### Loglama ve Takip
- Veritabanı tablosunda migration durumunu takip eder
- Dosya bazlı loglama
- Başarılı/başarısız işlemleri raporlar
- Hata detaylarını kaydeder

## Kullanım

### Admin Arayüzü

WordPress admin panelinde **Tarif Migration** menüsünden erişilebilir.

#### 1. Tek Tarif Test
```
Post ID girerek tek bir tarif ile test yapabilirsiniz.
Örnek: 6490 (Brokoli Çorbası), 22044, 7598
```

#### 2. Batch İşlem
```
10'ar 10'ar migration yapar.
Her seferinde 10 tarif işlenir.
```

#### 3. Toplu İşlem
```
Tüm 337 tarifi sırayla işler.
UYARI: Bu işlem saatler sürebilir!
```

### Programatik Kullanım

```php
use KG_Core\Migration\AIRecipeMigrator;

$migrator = new AIRecipeMigrator();

// Tek tarif
$recipeId = $migrator->migrate(6490);

// Batch (10 tarif)
$results = $migrator->migrateBatch(10);

// Tümü
$results = $migrator->migrateAll();
```

## Migration İş Akışı (YENİ AI-First)

Her tarif için sırasıyla:

1. ✅ Duplicate kontrolü (`_kg_migrated_from` meta key)
2. ✅ Blog post'u oku
3. 🤖 OpenAI GPT-4'e TÜM içeriği gönder
4. 📋 JSON response'u parse et
5. 🍳 Recipe post oluştur
6. 📦 Tüm meta alanları doldur:
   - Malzemeler (miktar, birim, not ile)
   - Hazırlanış adımları (ipuçları ile)
   - İkame malzemeler
   - Beslenme değerleri
   - Uzman bilgileri (TAM NOT ile)
   - Özel notlar
7. 🏷️ Taxonomy'leri ata (age-group, allergen, diet-type, meal-type)
8. 🖼️ Featured image kopyala
9. 📝 Original post'u draft yap
10. ✅ Log başarı

## Migration İş Akışı (ESKİ Regex Yaklaşımı - DEPRECATED)
3. 🥕 IngredientParser ile malzemeleri standardize et
4. 👶 AgeGroupMapper ile yaş grubunu belirle
5. 🔗 Mevcut malzeme CPT'leri ile eşleştir (bulunamayanlar için yeni oluştur)
6. 🤖 AIEnhancer ile eksik alanları AI ile doldur
7. 📊 SEOHandler ile RankMath meta'ları ayarla
8. 🖼️ Featured image'ı tarife kopyala
9. ✨ Yeni recipe post oluştur (DRAFT olarak)
10. 📝 Orijinal blog post'u DRAFT'a çek
11. 💾 İşlemi logla

## Dosya Yapısı

```
includes/Migration/
├── AIRecipeMigrator.php     # YENİ: AI-First ana migrator
├── RecipeMigrator.php       # ESKİ: Regex-based migrator (deprecated)
├── ContentParser.php        # HTML parsing (deprecated - AI kullanıyor)
├── IngredientParser.php     # Malzeme standardizasyonu (deprecated - AI kullanıyor)
├── AgeGroupMapper.php       # Yaş eşleştirme (deprecated - AI kullanıyor)
├── AIEnhancer.php          # OpenAI entegrasyonu (deprecated - AIRecipeMigrator'a entegre)
├── SEOHandler.php          # RankMath SEO
└── MigrationLogger.php     # Loglama sistemi

includes/Admin/
└── MigrationPage.php       # Admin UI (AIRecipeMigrator kullanıyor)

data/
└── recipe-ids.json         # Taşınacak 337 post ID

assets/admin/
├── css/migration.css       # Admin CSS
└── js/migration.js         # Admin JavaScript
```

## Veritabanı

Migration durumu `wp_kg_migration_log` tablosunda saklanır:

```sql
CREATE TABLE wp_kg_migration_log (
    id bigint(20) AUTO_INCREMENT PRIMARY KEY,
    blog_post_id bigint(20) NOT NULL,
    recipe_post_id bigint(20) DEFAULT NULL,
    status varchar(20) DEFAULT 'pending',
    started_at datetime DEFAULT NULL,
    completed_at datetime DEFAULT NULL,
    error_message text DEFAULT NULL,
    metadata longtext DEFAULT NULL
);
```

## Recipe Meta Alanları

AI tarafından doldurulan tüm alanlar:

```php
// Temel Bilgiler
_kg_prep_time           // Hazırlama süresi (dk)
_kg_is_featured         // Öne çıkan tarif (0/1)

// Malzemeler (array)
_kg_ingredients = [
    [
        'amount' => '3',
        'unit' => 'çiçek',
        'name' => 'Brokoli',
        'note' => 'orta boy',
        'ingredient_id' => 123
    ]
]

// Hazırlanış Adımları (array)
_kg_instructions = [
    [
        'id' => 1,
        'title' => '',
        'text' => 'Soğan zeytinyağında sote edilir.',
        'tip' => 'Orta ateşte yapın'
    ]
]

// İkame Malzemeler (array)
_kg_substitutes = [
    [
        'original' => 'İnek sütü',
        'substitute' => 'Badem sütü',
        'note' => 'Süt alerjisi olanlar için'
    ]
]

// Beslenme Değerleri
_kg_calories           // Kalori
_kg_protein            // Protein (g)
_kg_fiber              // Lif (g)
_kg_vitamins           // Vitaminler (örn: "A, C, D")

// Uzman Bilgileri
_kg_expert_name        // Uzman adı
_kg_expert_title       // Uzman unvanı
_kg_expert_note        // Uzman notu (TAM METİN)
_kg_expert_approved    // Uzman onayı (0/1)

// Özel Notlar
_kg_special_notes      // Süt:, Not:, İpucu: gibi tüm özel açıklamalar

// Medya
_kg_video_url          // YouTube URL

// Cross-Sell (array)
_kg_cross_sell = [
    'mode' => 'manual',
    'url' => 'https://www.tariften.com/recipes?q=...',
    'title' => 'Cross-sell başlığı',
    'image' => '',
    'ingredient' => 'Ana malzeme',
    'tariften_id' => ''
]

// Migration İzleme
_kg_migrated_from      // Orijinal blog post ID
```

## Taxonomies

```php
// Yaş Grubu
age-group:
  - 6-8-ay-baslangic
  - 9-11-ay-kesif
  - 12-24-ay-gecis
  - 2-yas-ve-uzeri

// Alerjenler
allergen:
  - süt
  - yumurta
  - fındık
  - badem
  - vs.

// Diyet Tipleri
diet-type:
  - vegan
  - vejetaryen
  - glutensiz
  - vs.

// Öğün Tipi
meal-type:
  - kahvaltı
  - ara öğün
  - öğle yemeği
  - akşam yemeği
```

## Rate Limiting

- AI çağrıları arasında 2 saniye bekleme (OpenAI rate limit için)
- Batch işlemlerde timeout kontrolü
- PHP `set_time_limit` kullanımı

## Hata Yönetimi

- Tüm hatalar `error_log`'a yazılır
- Veritabanında hata mesajları saklanır
- Admin panelinde hatalı işlemler gösterilir
- Try-catch blokları ile güvenli çalışma
- JSON parse hataları yakalanır
- OpenAI API hataları loglanır

## Test Senaryoları

İlk testler için önerilen post ID'ler:

- **6490** - Brokoli çorbası 9 ay ve sonrası (Tam uzman notu var)
- **22044** - Vegan brownie tarifi 1 yaş sonrası
- **7598** - Karabuğdaylı muhallebi 1 yaş ve sonrası

## Gereksinimler

- WordPress 5.0+
- PHP 7.4+
- OpenAI API Key (Settings > AI Settings'den yapılandırılmalı)
- `kg_openai_api_key` veya `kg_ai_api_key` option değeri
- `kg_ai_model` option değeri (varsayılan: gpt-4o)

## Farklar: AI-First vs Regex Yaklaşımı

### ESKİ (Regex):
❌ Malzemeler yanlış parse ediliyor  
❌ Hazırlanış adımları karışıyor  
❌ Uzman notu kesik kalıyor  
❌ Özel notlar eksik  
❌ Duplicate tarifler oluşabiliyor  

### YENİ (AI-First):
✅ Malzemeler doğru ve eksiksiz  
✅ Hazırlanış adımları ipuçları ile  
✅ Uzman notu TAM ve KESİNTİSİZ  
✅ Tüm özel notlar korunuyor  
✅ Duplicate kontrolü güçlü  
✅ İkame malzemeler otomatik  
✅ Beslenme değerleri tahmin ediliyor  

## Örnek AI Response

```json
{
  "description": "Brokoli çorbası, 9 ay ve üzeri bebekler için besleyici bir tarif...",
  "ingredients": [
    {
      "amount": "3",
      "unit": "çiçek",
      "name": "Brokoli",
      "note": ""
    },
    {
      "amount": "1/4",
      "unit": "adet",
      "name": "Kuru Soğan",
      "note": "küçük"
    }
  ],
  "instructions": [
    {
      "step": 1,
      "title": "",
      "text": "Soğan tencerede zeytinyağında sote edilir.",
      "tip": ""
    }
  ],
  "expert": {
    "name": "Enver Mahir Gülcan",
    "title": "Doç.Dr.",
    "note": "UZMAN NOTUNUN TAMAMI BURAYA"
  },
  "special_notes": "Süt: Çocuğunuzun inek sütü alerjisi yoksa...",
  "nutrition": {
    "calories": "120",
    "protein": "5",
    "fiber": "3",
    "vitamins": "A, C, K"
  },
  "prep_time": "25 dakika",
  "age_group": "9-11-ay-kesif",
  "allergens": ["süt"],
  "diet_types": ["vejetaryen"],
  "meal_types": ["öğle yemeği", "akşam yemeği"],
  "main_ingredient": "Brokoli",
  "video_url": ""
}
```

- WordPress 5.0+
- PHP 7.4+
- OpenAI API Key (AI özellikler için)
- RankMath veya Yoast SEO eklentisi (opsiyonel)

## Notlar

- Tüm yeni recipe postlar DRAFT olarak oluşturulur
- Orijinal blog postlar DRAFT'a çekilir ama silinmez
- Featured image kopyalanır, orijinal korunur
- Malzemeler için yeni CPT'ler DRAFT olarak oluşturulur
- Migration tekrarlanabilir (isMigrated kontrolü ile)

## Destek ve Geliştirme

- Log dosyaları: `wp-content/uploads/kg-migration-logs/`
- Error log: WordPress `error_log`
- Admin panel: **Tarif Migration** menüsü

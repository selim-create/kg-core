# Recipe Migration System

## Genel Bakış

Bu sistem, kidsgourmet.com.tr sitesinde blog yazısı olarak kaydedilmiş 337 adet tarifi yeni `recipe` post type'ına otomatik olarak aktarmak için geliştirilmiştir.

## Özellikler

### Otomatik Parsing
- Blog içeriğinden malzemeleri otomatik çıkarır
- Hazırlanış adımlarını parse eder
- Uzman notunu ve uzman bilgilerini tespit eder
- Video URL'lerini bulur

### Malzeme Standardizasyonu
- Malzeme miktarlarını standardize eder (1/2, 1/4, vs.)
- Birimleri normalleştirir (gram, litre, adet, vs.)
- Hazırlama notlarını ayırır (ince kıyılmış, rendel anmış, vs.)
- Mevcut malzeme CPT'leri ile eşleştirir
- Bulunamayan malzemeler için otomatik yeni CPT oluşturur

### Yaş Grubu Eşleştirme
- Başlık ve içerikten yaş ifadelerini bulur
- Doğru age-group taxonomy'sine atar
- Desteklenen yaş grupları:
  - 6-8 ay (Başlangıç)
  - 9-11 ay (Keşif)
  - 12-24 ay (Geçiş)
  - 2+ yaş

### AI ile Zenginleştirme
OpenAI API kullanarak eksik verileri otomatik doldurur:
- Hazırlama süresi tahmini
- Besin değerleri (kalori, protein, lif, vitaminler)
- İkame malzemeler
- Alerjen bilgisi
- Diyet tipleri (vegan, glutensiz, vs.)
- Öğün tipleri
- Ana malzeme tespiti
- Cross-sell URL ve başlığı

### SEO Optimizasyonu
- RankMath meta alanlarını doldurur
- SEO başlığı oluşturur
- Meta description üretir (AI ile veya manuel)
- Focus keyword belirler

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
Örnek: 6490, 22044, 7598
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
use KG_Core\Migration\RecipeMigrator;

$migrator = new RecipeMigrator();

// Tek tarif
$recipeId = $migrator->migrate(6490);

// Batch (10 tarif)
$results = $migrator->migrateBatch(10);

// Tümü
$results = $migrator->migrateAll();
```

## Migration İş Akışı

Her tarif için sırasıyla:

1. ✅ Blog post'u oku
2. 🔍 ContentParser ile içeriği parse et
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
├── RecipeMigrator.php      # Ana orchestrator
├── ContentParser.php        # HTML parsing
├── IngredientParser.php     # Malzeme standardizasyonu
├── AgeGroupMapper.php       # Yaş eşleştirme
├── AIEnhancer.php          # OpenAI entegrasyonu
├── SEOHandler.php          # RankMath SEO
└── MigrationLogger.php     # Loglama sistemi

includes/Admin/
└── MigrationPage.php       # Admin UI

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

## Rate Limiting

- AI çağrıları arasında 1 saniye bekleme
- Batch işlemlerde timeout kontrolü
- PHP `set_time_limit` kullanımı

## Hata Yönetimi

- Tüm hatalar `error_log`'a yazılır
- Veritabanında hata mesajları saklanır
- Admin panelinde hatalı işlemler gösterilir
- Try-catch blokları ile güvenli çalışma

## Test Senaryoları

İlk testler için önerilen post ID'ler:

- **6490** - Brokoli çorbası 9 ay ve sonrası
- **22044** - Vegan brownie tarifi 1 yaş sonrası
- **7598** - Karabuğdaylı muhallebi 1 yaş ve sonrası

## Gereksinimler

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

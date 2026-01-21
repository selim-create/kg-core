# Custom Table Migration Kılavuzu

## 📋 İçindekiler

1. [Genel Bakış](#genel-bakış)
2. [Veritabanı Tabloları](#veritabanı-tabloları)
3. [Model Kullanımı](#model-kullanımı)
4. [Feature Flags](#feature-flags)
5. [WP-CLI Komutları](#wp-cli-komutları)
6. [Dual-Write Sistemi](#dual-write-sistemi)
7. [Migration Süreci](#migration-süreci)
8. [Troubleshooting](#troubleshooting)
9. [Dosya Yapısı](#dosya-yapısı)

---

## 🎯 Genel Bakış

KG Core eklentisi, meta verileri daha performanslı yönetmek için `wp_postmeta` tablosundan özel custom tablolara geçiş yapmaktadır. Bu geçiş, özellikle büyük veri setlerinde önemli performans kazançları sağlar.

### Neden Custom Table?

**wp_postmeta Problemleri:**
- EAV (Entity-Attribute-Value) yapısı nedeniyle çok sayıda JOIN gerektirir
- Her meta değeri için ayrı satır = yüksek satır sayısı
- Serialized veri kullanımı = zor sorgulama
- Index optimizasyonu sınırlı

**Custom Table Avantajları:**
- Her post için tek satır = JOIN'siz veri çekme
- Tiplendirilmiş kolonlar = daha iyi query performance
- JSON veri desteği ile MySQL 5.7+ özelliklerinden yararlanma
- Kolay indexleme ve optimize sorgu yazma
- %70-85 daha hızlı veri okuma

### Geçiş Stratejisi

Geçiş, 3 aşamalı bir **dual-write** stratejisi ile güvenli şekilde yapılır:

1. **Faz 1: Dual-Write** - Hem wp_postmeta hem custom table'a yazılır
2. **Faz 2: Migration** - Eski veri custom table'a taşınır
3. **Faz 3: Read Switch** - Okuma custom table'dan yapılır
4. **Faz 4 (Opsiyonel): wp_postmeta Cleanup** - Eski veri temizlenir

---

## 🗄️ Veritabanı Tabloları

### 1. wp_kg_recipe_meta

Tarif meta verilerini saklar.

```sql
CREATE TABLE wp_kg_recipe_meta (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL UNIQUE,
    
    -- Temel Bilgiler
    prep_time INT UNSIGNED DEFAULT NULL,              -- Hazırlama süresi (dakika)
    cook_time INT UNSIGNED DEFAULT NULL,              -- Pişirme süresi (dakika)
    serving_size VARCHAR(100) DEFAULT NULL,            -- Porsiyon sayısı
    difficulty ENUM('kolay', 'orta', 'zor') DEFAULT NULL,
    freezable BOOLEAN DEFAULT FALSE,                   -- Dondurulabilir mi?
    storage_info TEXT DEFAULT NULL,                    -- Saklama bilgileri
    is_featured BOOLEAN DEFAULT FALSE,                 -- Öne çıkarılmış mı?
    video_url VARCHAR(500) DEFAULT NULL,               -- Video URL
    special_notes TEXT DEFAULT NULL,                   -- Özel notlar
    
    -- Beslenme Değerleri
    calories DECIMAL(10,2) DEFAULT NULL,               -- Kalori
    protein DECIMAL(10,2) DEFAULT NULL,                -- Protein (g)
    carbs DECIMAL(10,2) DEFAULT NULL,                  -- Karbonhidrat (g)
    fat DECIMAL(10,2) DEFAULT NULL,                    -- Yağ (g)
    fiber DECIMAL(10,2) DEFAULT NULL,                  -- Lif (g)
    sugar DECIMAL(10,2) DEFAULT NULL,                  -- Şeker (g)
    sodium DECIMAL(10,2) DEFAULT NULL,                 -- Sodyum (mg)
    vitamins TEXT DEFAULT NULL,                        -- Vitaminler
    minerals TEXT DEFAULT NULL,                        -- Mineraller
    
    -- Uzman Bilgileri
    expert_user_id BIGINT UNSIGNED DEFAULT NULL,      -- Uzman kullanıcı ID
    expert_name VARCHAR(255) DEFAULT NULL,             -- Uzman adı
    expert_title VARCHAR(255) DEFAULT NULL,            -- Uzman ünvanı
    expert_note TEXT DEFAULT NULL,                     -- Uzman notu
    expert_approved BOOLEAN DEFAULT FALSE,             -- Uzman onayı
    
    -- JSON Alanları
    ingredients JSON DEFAULT NULL,                     -- Malzemeler listesi
    instructions JSON DEFAULT NULL,                    -- Talimatlar
    substitutes JSON DEFAULT NULL,                     -- Alternatif malzemeler
    cross_sell JSON DEFAULT NULL,                      -- Çapraz satış ürünleri
    
    -- Puanlama Sistemi
    rating DECIMAL(3,2) DEFAULT NULL,                  -- Genel puan
    rating_count INT UNSIGNED DEFAULT 0,               -- Puan sayısı
    base_rating DECIMAL(3,2) DEFAULT NULL,             -- Temel puan
    base_rating_count INT UNSIGNED DEFAULT 0,          -- Temel puan sayısı
    ratings_data JSON DEFAULT NULL,                    -- Detaylı puanlama verileri
    
    -- Zaman Damgaları
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexler
    INDEX idx_post_id (post_id),
    INDEX idx_is_featured (is_featured),
    INDEX idx_difficulty (difficulty),
    INDEX idx_expert_approved (expert_approved),
    INDEX idx_rating (rating)
);
```

### 2. wp_kg_ingredient_meta

Malzeme meta verilerini saklar.

```sql
CREATE TABLE wp_kg_ingredient_meta (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL UNIQUE,
    
    -- Temel Bilgiler
    start_age INT UNSIGNED DEFAULT NULL,               -- Başlangıç yaşı (ay)
    allergy_risk ENUM('low', 'medium', 'high') DEFAULT NULL,
    is_featured BOOLEAN DEFAULT FALSE,
    season JSON DEFAULT NULL,                          -- Mevsim bilgisi
    
    -- Beslenme Değerleri (100g başına)
    calories_100g DECIMAL(10,2) DEFAULT NULL,
    protein_100g DECIMAL(10,2) DEFAULT NULL,
    carbs_100g DECIMAL(10,2) DEFAULT NULL,
    fat_100g DECIMAL(10,2) DEFAULT NULL,
    fiber_100g DECIMAL(10,2) DEFAULT NULL,
    sugar_100g DECIMAL(10,2) DEFAULT NULL,
    vitamins TEXT DEFAULT NULL,
    minerals TEXT DEFAULT NULL,
    
    -- Güvenlik Bilgileri
    cross_contamination TEXT DEFAULT NULL,             -- Çapraz bulaşma riski
    allergy_symptoms TEXT DEFAULT NULL,                -- Alerji belirtileri
    alternatives TEXT DEFAULT NULL,                    -- Alternatifler
    
    -- Kullanım Bilgileri
    benefits TEXT DEFAULT NULL,                        -- Faydaları
    storage_tips TEXT DEFAULT NULL,                    -- Saklama ipuçları
    preparation_tips TEXT DEFAULT NULL,                -- Hazırlama ipuçları
    selection_tips TEXT DEFAULT NULL,                  -- Seçim ipuçları
    pro_tips TEXT DEFAULT NULL,                        -- Profesyonel ipuçları
    
    -- JSON Alanları
    prep_methods JSON DEFAULT NULL,                    -- Hazırlama yöntemleri
    prep_by_age JSON DEFAULT NULL,                     -- Yaşa göre hazırlama
    pairings JSON DEFAULT NULL,                        -- Eşleştirmeler
    faq JSON DEFAULT NULL,                             -- Sıkça sorulan sorular
    
    -- Uzman Bilgileri
    expert_user_id BIGINT UNSIGNED DEFAULT NULL,
    expert_name VARCHAR(255) DEFAULT NULL,
    expert_title VARCHAR(255) DEFAULT NULL,
    expert_note TEXT DEFAULT NULL,
    expert_approved BOOLEAN DEFAULT FALSE,
    
    -- Zaman Damgaları
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexler
    INDEX idx_post_id (post_id),
    INDEX idx_is_featured (is_featured),
    INDEX idx_allergy_risk (allergy_risk),
    INDEX idx_start_age (start_age),
    INDEX idx_expert_approved (expert_approved)
);
```

### 3. wp_kg_post_meta

Blog yazısı meta verilerini saklar.

```sql
CREATE TABLE wp_kg_post_meta (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL UNIQUE,
    
    -- Genel
    is_featured BOOLEAN DEFAULT FALSE,
    
    -- Sponsorluk Bilgileri
    is_sponsored BOOLEAN DEFAULT FALSE,
    sponsor_name VARCHAR(255) DEFAULT NULL,
    sponsor_url VARCHAR(500) DEFAULT NULL,
    sponsor_logo_id BIGINT UNSIGNED DEFAULT NULL,
    sponsor_light_logo_id BIGINT UNSIGNED DEFAULT NULL,
    direct_redirect BOOLEAN DEFAULT FALSE,
    
    -- GAM Entegrasyonu
    gam_impression_url VARCHAR(1000) DEFAULT NULL,
    gam_click_url VARCHAR(1000) DEFAULT NULL,
    
    -- İndirim Bilgileri
    has_discount BOOLEAN DEFAULT FALSE,
    discount_text VARCHAR(255) DEFAULT NULL,
    
    -- Uzman Bilgileri
    expert_user_id BIGINT UNSIGNED DEFAULT NULL,
    expert_name VARCHAR(255) DEFAULT NULL,
    expert_title VARCHAR(255) DEFAULT NULL,
    expert_note TEXT DEFAULT NULL,
    expert_approved BOOLEAN DEFAULT FALSE,
    
    -- Zaman Damgaları
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexler
    INDEX idx_post_id (post_id),
    INDEX idx_is_featured (is_featured),
    INDEX idx_is_sponsored (is_sponsored),
    INDEX idx_expert_approved (expert_approved)
);
```

### 4. wp_kg_migrations

Migration takibi için kullanılır.

```sql
CREATE TABLE wp_kg_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT UNSIGNED NOT NULL,
    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch (batch)
);
```

---

## 📦 Model Kullanımı

### BaseModel

Tüm meta model sınıfları `BaseModel`'den türer. Temel CRUD işlemlerini sağlar.

```php
<?php
namespace KG_Core\Models;

abstract class BaseModel {
    protected static $table = '';           // Tablo adı
    protected static $post_type = '';       // Post tipi
    protected static $field_types = [];     // Alan tipleri
    
    // Temel metodlar
    public static function get($post_id);
    public static function getWithCache($post_id);
    public static function save($post_id, array $data);
    public static function delete($post_id);
    public static function exists($post_id);
    public static function bulkGet(array $post_ids);
    public static function count(array $filters = []);
}
```

### RecipeMeta Kullanımı

```php
use KG_Core\Models\RecipeMeta;

// Veri çekme
$recipe_meta = RecipeMeta::get(123);
echo $recipe_meta['prep_time'];  // 30
echo $recipe_meta['difficulty']; // 'kolay'

// Cache ile çekme (önerilen)
$recipe_meta = RecipeMeta::getWithCache(123);

// Veri kaydetme
$data = [
    'prep_time' => 30,
    'cook_time' => 45,
    'difficulty' => 'orta',
    'ingredients' => [
        ['name' => 'Süt', 'amount' => '200ml'],
        ['name' => 'Un', 'amount' => '150g']
    ],
    'is_featured' => true,
    'rating' => 4.5
];
RecipeMeta::save(123, $data);

// Bulk veri çekme (N+1 problemi önleme)
$post_ids = [123, 124, 125];
$meta_data = RecipeMeta::bulkGet($post_ids);
// Sonuç: ['123' => [...], '124' => [...], '125' => [...]]

// Veri silme
RecipeMeta::delete(123);

// Varlık kontrolü
if (RecipeMeta::exists(123)) {
    // Meta verisi var
}

// Sayma
$featured_count = RecipeMeta::count(['is_featured' => true]);

// Sorgulama (filtreleme)
$recipes = RecipeMeta::query([
    'is_featured' => true,
    'difficulty' => 'kolay',
    'min_rating' => 4.0,
    'limit' => 10,
    'orderby' => 'rating',
    'order' => 'DESC'
]);
```

### IngredientMeta Kullanımı

```php
use KG_Core\Models\IngredientMeta;

// Veri çekme
$ingredient_meta = IngredientMeta::get(456);
echo $ingredient_meta['start_age'];    // 6 (ay)
echo $ingredient_meta['allergy_risk']; // 'low'

// Veri kaydetme
$data = [
    'start_age' => 8,
    'allergy_risk' => 'medium',
    'calories_100g' => 52.0,
    'prep_methods' => [
        ['method' => 'Haşlama', 'duration' => '10 dk'],
        ['method' => 'Buhar', 'duration' => '8 dk']
    ],
    'is_featured' => true
];
IngredientMeta::save(456, $data);

// Sorgulama
$ingredients = IngredientMeta::query([
    'is_featured' => true,
    'allergy_risk' => 'low',
    'max_start_age' => 6,
    'limit' => 20
]);
```

### PostMeta Kullanımı

```php
use KG_Core\Models\PostMeta;

// Veri çekme
$post_meta = PostMeta::get(789);
echo $post_meta['is_sponsored'];  // true
echo $post_meta['sponsor_name'];  // 'ABC Marka'

// Veri kaydetme
$data = [
    'is_featured' => true,
    'is_sponsored' => true,
    'sponsor_name' => 'ABC Marka',
    'sponsor_url' => 'https://example.com',
    'sponsor_logo_id' => 123,
    'has_discount' => true,
    'discount_text' => '%20 İndirim Kodu: ABC20'
];
PostMeta::save(789, $data);
```

---

## 🚩 Feature Flags

Feature flag sistemi, custom table geçişini aşamalı yapmak için kullanılır.

### Kullanılabilir Flagler

```php
use KG_Core\Config\FeatureFlags;

// 1. dual_write (Varsayılan: true)
// Hem wp_postmeta hem custom table'a yazar
$enabled = FeatureFlags::isEnabled('dual_write');
$enabled = FeatureFlags::useDualWrite(); // Kısayol

// 2. read_from_custom_table (Varsayılan: true)
// Custom table'dan okur, fallback olarak wp_postmeta kullanır
$enabled = FeatureFlags::isEnabled('read_from_custom_table');
$enabled = FeatureFlags::useCustomTables(); // Kısayol

// 3. custom_table_only (Varsayılan: false)
// Sadece custom table kullanır, wp_postmeta'ya yazmaz
$enabled = FeatureFlags::isEnabled('custom_table_only');
$enabled = FeatureFlags::customTableOnly(); // Kısayol
```

### Flag Yönetimi

```php
// Flag'i etkinleştirme
FeatureFlags::enable('dual_write');

// Flag'i devre dışı bırakma
FeatureFlags::disable('dual_write');

// Flag değerini ayarlama
FeatureFlags::set('dual_write', true);

// Tüm flagleri görme
$flags = FeatureFlags::getAll();
// Sonuç: ['dual_write' => true, 'read_from_custom_table' => true, ...]

// Varsayılanlara sıfırlama
FeatureFlags::reset();
```

### Kullanım Senaryoları

#### Senaryo 1: İlk Kurulum (Dual-Write Modu)
```php
FeatureFlags::enable('dual_write');
FeatureFlags::disable('read_from_custom_table');
// Sonuç: Yazma hem wp_postmeta hem custom table'a
//        Okuma sadece wp_postmeta'dan
```

#### Senaryo 2: Migration Sonrası Test (Okuma Geçişi)
```php
FeatureFlags::enable('dual_write');
FeatureFlags::enable('read_from_custom_table');
// Sonuç: Yazma hem wp_postmeta hem custom table'a
//        Okuma önce custom table'dan, fallback wp_postmeta
```

#### Senaryo 3: Tam Geçiş (Custom Table Only)
```php
FeatureFlags::disable('dual_write');
FeatureFlags::enable('read_from_custom_table');
FeatureFlags::enable('custom_table_only');
// Sonuç: Yazma ve okuma sadece custom table'dan
```

---

## 💻 WP-CLI Komutları

WP-CLI komutları ile migration sürecini yönetebilirsiniz.

### Schema Yönetimi

```bash
# Tabloları oluşturma
wp kg schema create

# Tablo durumunu kontrol etme
wp kg schema status

# Tabloları silme (UYARI: Tüm veri silinir!)
wp kg schema drop --yes
```

### Migration Komutları

#### Migrate
Veriyi wp_postmeta'dan custom table'a taşır.

```bash
# Tüm post tiplerini migrate etme
wp kg migrate all

# Sadece tarifleri migrate etme
wp kg migrate recipes

# Sadece malzemeleri migrate etme
wp kg migrate ingredients

# Sadece blog yazılarını migrate etme
wp kg migrate posts

# Batch size belirtme (varsayılan: 50)
wp kg migrate all --batch-size=100

# Dry-run (Ne olacağını göster ama migrate etme)
wp kg migrate all --dry-run
wp kg migrate recipes --dry-run
```

**Örnek Çıktı:**
```
Migration started
Batch size: 50

Processing Recipes...
  Migrated: 245
  Skipped:  12
  
Processing Ingredients...
  Migrated: 189
  Skipped:  8

Processing Posts...
  Migrated: 1523
  Skipped:  45

==================================================
✓ Migration complete!
Total migrated: 1957
```

#### Verify
Migration coverage'ını kontrol eder.

```bash
# Tüm tipleri verify etme
wp kg verify all

# Sadece tarifleri verify etme
wp kg verify recipes
```

**Örnek Çıktı:**
```
Verifying Migration Coverage

✓ Recipe: All migrated
⚠ Ingredient: 5 records not migrated
  Missing IDs: 123, 456, 789, 101, 202

✓ Post: All migrated
```

#### Status
Genel migration durumunu gösterir.

```bash
wp kg status
```

**Örnek Çıktı:**
```
=== KG Core Migration Status ===

Database Schema:
✓ All custom tables exist
  • kg_recipe_meta: 245 rows
  • kg_ingredient_meta: 189 rows
  • kg_post_meta: 1,523 rows
  • kg_migrations: 3 rows

Feature Flags:
  • dual_write: ✓ enabled
  • read_from_custom_table: ✓ enabled
  • custom_table_only: ✗ disabled

Migration Coverage:
  Recipe:
    [==============================] 100.0%
    245 / 245 records
  Ingredient:
    [==========================    ] 90.5%
    171 / 189 records
  Post:
    [==============================] 100.0%
    1523 / 1523 records
```

#### Rollback
Custom table verilerini temizler.

```bash
# Sadece tarif verilerini temizleme
wp kg rollback recipes

# Tüm veriyi temizleme
wp kg rollback all --yes
```

**UYARI:** Bu komut custom table'daki verileri siler. wp_postmeta'daki verilere dokunmaz.

---

## ⚡ Dual-Write Sistemi

Dual-write sistemi, MetaSyncService tarafından yönetilir ve MetaBox save işlemleri sırasında otomatik çalışır.

### Nasıl Çalışır?

1. **WordPress Admin'de Kaydetme:**
   - Kullanıcı tarif/malzeme/post düzenler ve kaydeder
   - MetaBox `save_post` hook'u tetiklenir
   - Meta veriler wp_postmeta'ya kaydedilir

2. **Dual-Write Sync:**
   - MetaSyncService tetiklenir
   - FeatureFlags kontrol edilir
   - Eğer dual-write aktifse:
     - Meta veriler custom table'a da kaydedilir
     - Her iki veri kaynağı senkronize olur

### MetaSyncService Kullanımı

```php
use KG_Core\Services\MetaSyncService;

// Tarif senkronizasyonu
MetaSyncService::syncRecipe(123);

// Malzeme senkronizasyonu
MetaSyncService::syncIngredient(456);

// Post senkronizasyonu
MetaSyncService::syncPost(789);
```

### MetaBox Entegrasyonu

RecipeMetaBox, IngredientMetaBox ve PostMetaBox sınıfları otomatik olarak dual-write yapar:

```php
// includes/Admin/RecipeMetaBox.php
add_action('save_post_recipe', function($post_id) {
    // 1. wp_postmeta'ya kaydet
    update_post_meta($post_id, '_kg_prep_time', $prep_time);
    update_post_meta($post_id, '_kg_cook_time', $cook_time);
    // ...
    
    // 2. Dual-write sync (feature flag kontrolü ile)
    if (FeatureFlags::useDualWrite()) {
        MetaSyncService::syncRecipe($post_id);
    }
});
```

---

## 🔄 Migration Süreci

### Adım Adım Geçiş Kılavuzu

#### Faz 1: Hazırlık (1 hafta)

**1. Plugin Güncellemesi**
```bash
# Plugin'i son sürüme güncelleyin
git pull origin main
```

**2. Backup**
```bash
# Veritabanı yedeği alın
wp db export backup-$(date +%Y%m%d).sql
```

**3. Tabloları Oluşturma**
```bash
# Custom tabloları oluşturun
wp kg schema create

# Oluşturulduğunu doğrulayın
wp kg schema status
```

**4. Feature Flag Ayarları (İlk Dual-Write)**
```bash
# WordPress Admin Panel'den
# Settings > KG Core > Feature Flags
# dual_write: ✓ Enabled
# read_from_custom_table: ✗ Disabled
```

Bu aşamada:
- Yazma: wp_postmeta + custom table
- Okuma: Sadece wp_postmeta

#### Faz 2: Migration (2-3 gün)

**1. Dry-Run Test**
```bash
# Ne migrate edileceğini görelim
wp kg migrate all --dry-run
```

**2. Migration Başlatma**
```bash
# Önce küçük bir batch ile test edelim
wp kg migrate recipes --batch-size=10

# Sorun yoksa tüm veriyi migrate edelim
wp kg migrate all --batch-size=100
```

**3. Verification**
```bash
# Migration tamamlandığını doğrulayalım
wp kg verify all

# Genel durumu görelim
wp kg status
```

**4. Eksik Kayıtları Tamamlama**
```bash
# Eğer eksik kayıtlar varsa tekrar migrate edelim
wp kg migrate recipes
```

#### Faz 3: Geçiş Testi (1 hafta)

**1. Read Switch (Staging'de Test)**
```bash
# Feature flag'i değiştirelim
# dual_write: ✓ Enabled
# read_from_custom_table: ✓ Enabled
```

**2. Test Senaryoları**
- [ ] Tarif listeleme
- [ ] Tarif detay sayfası
- [ ] Malzeme listeleme
- [ ] Malzeme detay sayfası
- [ ] Blog yazısı listeleme
- [ ] Filtreleme (zorluk, alerji riski, vb.)
- [ ] Puanlama sistemi
- [ ] Uzman onaylı içerikler
- [ ] Cache mekanizması

**3. Performans Ölçümü**
```bash
# Query Performance Monitor
# Slow Query Log analizi
# Response time karşılaştırması
```

**4. A/B Test (Opsiyonel)**
- Kullanıcıların %10'una custom table
- Kullanıcıların %90'ına wp_postmeta
- Metrikler: Response time, error rate, user experience

#### Faz 4: Tam Geçiş (Production)

**1. Production'da Read Switch**
```bash
# Production ortamında flag'leri değiştirelim
# dual_write: ✓ Enabled
# read_from_custom_table: ✓ Enabled
```

**2. Monitoring (1 hafta)**
- Error log takibi
- Performance metrikleri
- User feedback

**3. Final Switch (Custom Table Only)**
```bash
# Artık sadece custom table kullanalım
# dual_write: ✗ Disabled
# read_from_custom_table: ✓ Enabled
# custom_table_only: ✓ Enabled
```

#### Faz 5: Temizlik (Opsiyonel)

**⚠️ UYARI:** Bu adım geri dönüşü olmayan bir adımdır!

```bash
# wp_postmeta'daki _kg_* meta verilerini temizleme
# Manuel SQL ile yapılmalıdır:

# Önce backup alalım
wp db export before-cleanup-$(date +%Y%m%d).sql

# Meta verileri silelim (UYARI!)
DELETE FROM wp_postmeta WHERE meta_key LIKE '_kg_%';
```

---

## 🔧 Troubleshooting

### Yaygın Sorunlar ve Çözümleri

#### Problem 1: Custom Tablolar Oluşturulmuyor

**Belirtiler:**
```bash
wp kg schema status
# ✗ kg_recipe_meta: Does not exist
```

**Çözüm:**
```bash
# 1. Veritabanı bağlantısını kontrol edin
wp db check

# 2. Kullanıcı yetkilerini kontrol edin
# MySQL kullanıcısının CREATE TABLE yetkisi olmalı

# 3. Manuel oluşturma
wp kg schema create

# 4. Hala oluşmazsa SQL hatalarını kontrol edin
wp config get table_prefix
```

#### Problem 2: Migration Sırasında Hata

**Belirtiler:**
```bash
wp kg migrate recipes
# Error: Failed to save recipe meta
```

**Çözüm:**
```bash
# 1. Veritabanı günlüklerini kontrol edin
tail -f /var/log/mysql/error.log

# 2. PHP error log'larını kontrol edin
tail -f /var/log/php-fpm/error.log

# 3. WordPress debug'u açın
wp config set WP_DEBUG true --raw
wp config set WP_DEBUG_LOG true --raw

# 4. Tek bir kayıtla test edin
# PHP console'da:
use KG_Core\Database\DataMigration;
$result = DataMigration::migrateSinglePost(123, 'recipe');
var_dump($result);
```

#### Problem 3: Veri Uyuşmazlığı

**Belirtiler:**
```bash
wp kg verify all
# ⚠ Recipe: 50 records not migrated
```

**Çözüm:**
```bash
# 1. Hangi kayıtların eksik olduğunu görelim
wp kg verify recipes

# 2. Eksik kayıtları tekrar migrate edelim
wp kg migrate recipes

# 3. Hala eksikse manuel kontrol edelim
# MySQL console:
SELECT p.ID, p.post_title 
FROM wp_posts p
LEFT JOIN wp_kg_recipe_meta m ON p.ID = m.post_id
WHERE p.post_type = 'recipe' 
AND p.post_status = 'publish'
AND m.post_id IS NULL;
```

#### Problem 4: Performance Düşüklüğü

**Belirtiler:**
- Yavaş sayfa yüklemeleri
- Yüksek veritabanı yükü

**Çözüm:**
```bash
# 1. Index'leri kontrol edin
SHOW INDEX FROM wp_kg_recipe_meta;

# 2. Cache'i temizleyin
wp cache flush

# 3. Object cache kullanın
# wp-config.php'ye ekleyin:
define('WP_CACHE', true);

# 4. Query performansını ölçün
wp db query "EXPLAIN SELECT * FROM wp_kg_recipe_meta WHERE is_featured = 1"
```

#### Problem 5: Dual-Write Çalışmıyor

**Belirtiler:**
- wp_postmeta güncellenirken custom table güncellenmiyor

**Çözüm:**
```php
// 1. Feature flag kontrolü
use KG_Core\Config\FeatureFlags;
var_dump(FeatureFlags::useDualWrite()); // true olmalı

// 2. MetaSyncService çağrılıyor mu?
// RecipeMetaBox.php'de:
add_action('save_post_recipe', function($post_id) {
    error_log("Saving recipe: " . $post_id);
    
    // Meta kaydetme...
    
    if (FeatureFlags::useDualWrite()) {
        error_log("Syncing to custom table: " . $post_id);
        MetaSyncService::syncRecipe($post_id);
    }
});

// 3. Log'ları kontrol edin
tail -f wp-content/debug.log
```

#### Problem 6: JSON Veriler Bozuk

**Belirtiler:**
```php
$meta = RecipeMeta::get(123);
var_dump($meta['ingredients']); // string yerine null
```

**Çözüm:**
```php
// 1. Veriyi kontrol edin
global $wpdb;
$row = $wpdb->get_row("SELECT ingredients FROM wp_kg_recipe_meta WHERE post_id = 123");
var_dump($row->ingredients);

// 2. JSON geçerliliğini kontrol edin
$json = $row->ingredients;
$decoded = json_decode($json);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Error: " . json_last_error_msg();
}

// 3. Migration'ı tekrar çalıştırın
use KG_Core\Database\DataMigration;
DataMigration::forceMigrate(123, 'recipe');
```

---

## 📁 Dosya Yapısı

```
kg-core/
├── includes/
│   ├── CLI/
│   │   └── MigrationCommands.php          # WP-CLI komutları
│   │
│   ├── Config/
│   │   └── FeatureFlags.php               # Feature flag sistemi
│   │
│   ├── Database/
│   │   ├── Schema.php                     # Tablo şemaları
│   │   ├── DataMigration.php              # Migration logic
│   │   └── MigrationRunner.php            # Migration tracker
│   │
│   ├── Models/
│   │   ├── BaseModel.php                  # Abstract base model
│   │   ├── RecipeMeta.php                 # Recipe meta model
│   │   ├── IngredientMeta.php             # Ingredient meta model
│   │   └── PostMeta.php                   # Post meta model
│   │
│   ├── Services/
│   │   └── MetaSyncService.php            # Dual-write sync service
│   │
│   └── Admin/
│       ├── RecipeMetaBox.php              # Recipe meta box (dual-write)
│       ├── IngredientMetaBox.php          # Ingredient meta box (dual-write)
│       ├── PostMetaBox.php                # Post meta box (dual-write)
│       └── DataMigrationPage.php          # Admin migration UI
│
├── tests/
│   ├── test-custom-tables.php             # Custom table system tests
│   ├── test-data-migration.php            # Migration tests
│   └── test-api-consistency.php           # API consistency tests
│
└── docs/
    └── CUSTOM_TABLE_MIGRATION.md          # Bu döküman
```

### Sınıf Sorumlulukları

#### Schema.php
- Tablo tanımları (CREATE TABLE)
- Tablo oluşturma/silme
- Tablo durumu kontrolü

#### DataMigration.php
- wp_postmeta → custom table migration
- Meta key mappings
- Type conversions
- Batch processing
- Verification

#### MigrationRunner.php
- Migration tracking (wp_kg_migrations)
- Batch yönetimi
- Migration history

#### BaseModel.php
- CRUD operations (get, save, delete)
- Serialization/Deserialization (JSON)
- Caching
- Bulk operations

#### RecipeMeta.php / IngredientMeta.php / PostMeta.php
- Field type definitions
- Custom queries
- Post type specific logic

#### MetaSyncService.php
- Dual-write sync
- wp_postmeta → custom table synchronization
- Feature flag integration

#### FeatureFlags.php
- Feature flag management
- Migration phase control

#### MigrationCommands.php
- WP-CLI command registration
- CLI progress reporting
- Batch execution

---

## 📊 Best Practices

### 1. Migration Öncesi

- ✅ **Backup alın**: Hem veritabanını hem de dosyaları yedekleyin
- ✅ **Staging ortamında test edin**: Production'a geçmeden önce mutlaka test edin
- ✅ **Monitoring hazırlayın**: Error tracking ve performance monitoring aktif olmalı

### 2. Migration Sırasında

- ✅ **Batch size'ı ayarlayın**: Sunucu kapasitesine göre optimize edin
- ✅ **Progress takibi yapın**: `wp kg status` ile düzenli kontrol edin
- ✅ **Dry-run kullanın**: Gerçek migration öncesi ne olacağını görün
- ✅ **Yavaş ilerleyin**: Önce küçük batch'lerle test edin

### 3. Migration Sonrası

- ✅ **Verification yapın**: `wp kg verify all` ile doğrulayın
- ✅ **Performance ölçümü**: Önce/sonra karşılaştırması yapın
- ✅ **Error log takibi**: İlk günlerde yakından takip edin
- ✅ **Rollback planı hazır olsun**: Sorun çıkarsa geri dönüş için

### 4. Genel Öneriler

- ✅ **Cache kullanın**: `getWithCache()` metodunu tercih edin
- ✅ **Bulk operations**: N+1 problemi için `bulkGet()` kullanın
- ✅ **Index'leri optimize edin**: Sık kullanılan sorguları analiz edin
- ✅ **Feature flag'leri doğru kullanın**: Aşamalı geçiş yapın

---

## 🆘 Destek

Sorun yaşıyorsanız:

1. **Documentation**: Bu dökümanı dikkatlice okuyun
2. **Troubleshooting**: Yukarıdaki Troubleshooting bölümünü inceleyin
3. **Logs**: Error log'ları kontrol edin
4. **Test**: Staging ortamında reproduce etmeye çalışın
5. **Issue**: GitHub'da issue açın (detaylı açıklama ile)

---

## 📝 Changelog

### v1.0.0 (2024-01-21)
- ✅ Initial custom table implementation
- ✅ Schema definitions (kg_recipe_meta, kg_ingredient_meta, kg_post_meta)
- ✅ BaseModel abstract class
- ✅ Model classes (RecipeMeta, IngredientMeta, PostMeta)
- ✅ DataMigration system
- ✅ Feature flag system
- ✅ Dual-write integration
- ✅ WP-CLI commands
- ✅ Comprehensive tests (165 tests)
- ✅ Turkish documentation

---

## 📚 Kaynaklar

- [WordPress Database Schema](https://codex.wordpress.org/Database_Description)
- [WP-CLI Commands](https://developer.wordpress.org/cli/commands/)
- [MySQL JSON Functions](https://dev.mysql.com/doc/refman/8.0/en/json-functions.html)
- [WordPress Metadata API](https://developer.wordpress.org/plugins/metadata/)

---

**Son Güncelleme:** 21 Ocak 2024  
**Versiyon:** 1.0.0  
**Yazar:** KG Core Development Team

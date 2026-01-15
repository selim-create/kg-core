# Ingredient CPT Field Consolidation - Implementation Guide

## Özet
Bu döküman, Ingredient (Malzeme) Custom Post Type'ında gerçekleştirilen alan birleştirme işlemini açıklar.

## Problem
756 ingredient kaydında mükerrer alanlar vardı:
- Kategori: Meta field VE taxonomy
- Besin değerleri: Eski format VE 100g formatı
- Alerjen bilgisi: 3 farklı alan
- Hazırlama talimatları: 5 benzer alan

## Çözüm
1. Mükerrer alanlar kaldırıldı
2. Tek kaynak (taxonomy/100g format) kullanıldı
3. AI zenginleştirme butonu eklendi
4. Veri migrasyon sistemi oluşturuldu

## Değişiklikler

### 1. IngredientMetaBox.php
**Kaldırılan Alanlar:**
- `kg_category` select (taxonomy yeterli)
- "Besin Değerleri (Genel)" bölümü tamamen
- `kg_is_allergen` checkbox
- `kg_allergen_type` text input
- `kg_prep_methods_list` textarea
- `kg_prep_tips` textarea
- `kg_cooking_suggestions` textarea

**Kalan Alanlar:**
- 100g başına besin değerleri
- `allergen` taxonomy checkboxes
- `_kg_preparation_tips` (tek hazırlama alanı)
- `_kg_prep_methods` (array formatında)

### 2. IngredientEnricher.php (YENİ)
**Özellikler:**
- Eksik alanları tespit eder
- AI ile sadece eksik alanları doldurur
- "Yeniden Oluştur" seçeneği
- Ingredient edit sayfasında sidebar'da gösterilir

**Kullanım:**
```
Ingredient edit sayfası → Sağ sidebar → "🤖 AI Zenginleştirme"
→ "🤖 Eksik Alanları Doldur" VEYA "🔄 Yeniden Oluştur"
```

### 3. FieldConsolidation.php (YENİ)
**Migrasyon İşlemleri:**

#### Kategori Migrasyonu
```php
_kg_category meta → ingredient-category taxonomy
```

#### Besin Değerleri Migrasyonu
```php
_kg_calories → _kg_ing_calories_100g
_kg_protein  → _kg_ing_protein_100g
_kg_carbs    → _kg_ing_carbs_100g
_kg_fat      → _kg_ing_fat_100g
_kg_fiber    → _kg_ing_fiber_100g
_kg_vitamins → _kg_ing_vitamins (aynı isim)
```

#### Temizlenen Alanlar
```php
_kg_category
_kg_calories, _kg_protein, _kg_carbs, _kg_fat, _kg_fiber
_kg_is_allergen
_kg_allergen_type
_kg_prep_methods_list
_kg_prep_tips
_kg_cooking_suggestions
```

### 4. AIService.php
**Prompt Güncellemeleri:**
- `category` → "ingredient-category taxonomy olarak atanacak" açıklaması
- `nutrition` → 100g başına format vurgusu
- `nutrition.sugar` → Yeni alan eklendi
- `nutrition.minerals` → Yeni alan eklendi

### 5. IngredientGenerator.php
**Değişiklikler:**
- `_kg_category` meta save kaldırıldı (sadece taxonomy)
- Nutrition keys güncellendi (_kg_ing_* formatı)
- `sugar` ve `minerals` alanları eklendi

### 6. IngredientController.php (API)
**Değişiklikler:**
- `category` → taxonomy'den alınıyor
- `nutrition` → tek obje (100g formatı)
- `nutrition_per_100g` → kaldırıldı (mükerrer)
- `allergen_info.is_allergen` → kaldırıldı
- `allergen_info.allergen_type` → kaldırıldı
- `prep_methods_list` → kaldırıldı
- `prep_tips` → kaldırıldı
- `cooking_suggestions` → kaldırıldı

## Migrasyon Nasıl Çalıştırılır

### Adım 1: Önizleme
```
WordPress Admin → Tarif Migration
→ "🧬 Malzeme (Ingredient) Alan Birleştirme" bölümü
→ "🔍 Önizleme Yap" butonuna tıkla
```

**Önizleme Sonucu:**
- Toplam ingredient sayısı
- Migrate edilecek kategori sayısı
- Migrate edilecek besin değeri sayısı
- Temizlenecek alan bulunduran kayıt sayısı

### Adım 2: Migrasyon
```
→ "▶️ Migrasyon Çalıştır" butonuna tıkla
→ Onay ver
→ Sonuçları bekle
```

**Migrasyon Sonucu:**
- İşlenen ingredient sayısı
- Migrate edilen kategori sayısı
- Migrate edilen besin değeri sayısı
- Hatalar (varsa)

### Adım 3: Doğrulama
1. Rastgele bir ingredient aç
2. Kategori taxonomy'nin atandığını kontrol et
3. 100g besin değerlerinin dolu olduğunu kontrol et
4. API yanıtını kontrol et: `/wp-json/kg/v1/ingredients/{slug}`

## Final Alan Yapısı

### Meta Fields
```php
// Temel
_kg_is_featured
_kg_start_age

// İçerik
_kg_benefits
_kg_faq (JSON)

// Besin Değerleri (100g başına)
_kg_ing_calories_100g
_kg_ing_protein_100g
_kg_ing_carbs_100g
_kg_ing_fat_100g
_kg_ing_fiber_100g
_kg_ing_sugar_100g
_kg_ing_vitamins
_kg_ing_minerals

// Alerjen
_kg_allergy_risk
_kg_cross_contamination
_kg_allergy_symptoms
_kg_alternatives

// Hazırlama
_kg_prep_methods (array)
_kg_prep_by_age (JSON)
_kg_preparation_tips
_kg_selection_tips
_kg_pro_tips

// Mevsim & Saklama
_kg_season
_kg_storage_tips

// Uyumlu İkililer
_kg_pairings (JSON)

// Görsel
_kg_image_source
_kg_image_credit
_kg_image_credit_url
```

### Taxonomies
```php
ingredient-category
allergen
```

## API Response Örneği

### Eski Format (Mükerrer)
```json
{
  "category": "Meyveler",
  "nutrition": {
    "calories": "52",
    "protein": "0.3"
  },
  "nutrition_per_100g": {
    "calories": "52",
    "protein": "0.3"
  },
  "allergen_info": {
    "is_allergen": false,
    "allergen_type": ""
  }
}
```

### Yeni Format (Temiz)
```json
{
  "category": "Meyveler",
  "nutrition": {
    "calories": "52",
    "protein": "0.3",
    "carbs": "14",
    "fat": "0.2",
    "fiber": "2.4",
    "sugar": "10",
    "vitamins": "C, A",
    "minerals": "Potasyum"
  },
  "allergen_info": {
    "cross_contamination_risk": "Düşük",
    "allergy_symptoms": "...",
    "alternative_ingredients": "..."
  }
}
```

## Test Çalıştırma
```bash
cd /home/runner/work/kg-core/kg-core
php tests/test-ingredient-consolidation.php
```

**Beklenen Sonuç:**
```
=== Test Summary ===
All critical components have been checked.
✅ All tests passing
```

## Geri Alma (Rollback)

Eğer migrasyon sorun çıkarırsa:

1. **Veritabanı Backup'ını Geri Yükle**
   ```sql
   -- Migration öncesi backup alındıysa
   ```

2. **Kod Değişikliklerini Geri Al**
   ```bash
   git revert a3cc1eb  # Son commit
   git revert a42c84c  # Önceki commit
   git revert 417b0b0  # İlk commit
   ```

3. **Veya Branch'i Değiştir**
   ```bash
   git checkout main  # Ana branch'e dön
   ```

## Sık Sorulan Sorular

**S: Eski API yanıtları çalışmaya devam edecek mi?**
C: Evet, `nutrition` alanı hala mevcut. Eski formatı kullanan clientler çalışmaya devam eder.

**S: Migration geri alınabilir mi?**
C: Kısmen. Eski alanlar temizleniyor ama veriler yeni alanlara taşınıyor. Database backup önerilir.

**S: Tüm ingredient'ları migrate etmek zorunlu mu?**
C: Hayır, ancak önerilir. Yeni ingredient'lar otomatik olarak yeni formatı kullanacak.

**S: AI enrichment butonu nasıl çalışır?**
C: Sadece boş alanları doldurur. Dolu alanları değiştirmez (force_all=false modunda).

**S: Migration ne kadar sürer?**
C: 756 ingredient için ~5 dakika (her kayıt için ~0.4 saniye).

## İletişim
Sorular için: selim-create/kg-core GitHub repository

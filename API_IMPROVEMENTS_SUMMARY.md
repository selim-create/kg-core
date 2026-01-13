# API İyileştirmeleri - Uygulama Özeti

## Genel Bakış

Bu PR, frontend'in ihtiyaç duyduğu eksik verileri sağlamak için API endpoint'lerini güncelledi ve RankMath SEO entegrasyonunu ekledi.

## Yapılan Değişiklikler

### 1. Helper Sınıfı Güncellemeleri

**Dosya:** `includes/Utils/Helper.php`

- ✅ `decode_html_entities()` metodu eklendi
  - HTML entity'lerini düzgün bir şekilde decode eder
  - UTF-8 desteği ile çalışır
  - Tüm API response'larında kullanılabilir

### 2. RecipeController API Güncellemeleri

**Dosya:** `includes/API/RecipeController.php`

#### Yeni Metod: get_seo_data()
- RankMath SEO verilerini çeker
- Fallback değerler sağlar (SEO verisi yoksa post title + site name kullanır)
- Desteklenen alanlar:
  - SEO Title
  - SEO Description
  - Focus Keywords
  - Canonical URL
  - Open Graph (Facebook) - Title, Description, Image
  - Twitter Card - Title, Description

#### Yeni Alanlar (prepare_recipe_data)
**Temel Bilgiler:**
- `meal_type` - Öğün tipi (Kahvaltı, Öğle, Akşam, Ara Öğün)
- `cook_time` - Pişirme süresi
- `serving_size` - Porsiyon bilgisi
- `difficulty` - Zorluk seviyesi (Kolay, Orta, Zor)
- `freezable` - Dondurulabilir mi? (boolean)
- `storage_info` - Saklama bilgisi

**Genişletilmiş Beslenme Değerleri:**
- `carbs` - Karbonhidrat
- `fat` - Yağ
- `sugar` - Şeker
- `sodium` - Sodyum
- `minerals` - Mineraller

**SEO Objesi:**
- `seo` - Tüm SEO verilerini içeren obje

**HTML Entity Decoding:**
- `title` - Artık HTML entity'leri decode edilmiş olarak döner

### 3. IngredientController API Güncellemeleri

**Dosya:** `includes/API/IngredientController.php`

#### Yeni Metod: get_seo_data()
- RecipeController ile aynı şekilde çalışır
- RankMath SEO verilerini ingredient'ler için sağlar

#### Yeni Alanlar (prepare_ingredient_data)
**Besin Değerleri (100g başına):**
- `nutrition_per_100g` - Yeni obje
  - `calories` - Kalori (100g başına)
  - `protein` - Protein (100g başına)
  - `carbs` - Karbonhidrat (100g başına)
  - `fat` - Yağ (100g başına)
  - `fiber` - Lif (100g başına)
  - `sugar` - Şeker (100g başına)
  - `vitamins` - Vitaminler
  - `minerals` - Mineraller

**Alerjen Bilgileri:**
- `allergen_info` - Yeni obje
  - `is_allergen` - Alerjen mi? (boolean)
  - `allergen_type` - Alerjen tipi (Süt, Yumurta, Gluten vb.)
  - `cross_contamination_risk` - Çapraz bulaşma riski
  - `allergy_symptoms` - Alerji semptomları
  - `alternative_ingredients` - Alternatif malzemeler

**Hazırlama Bilgileri:**
- `prep_methods_list` - Hazırlama yöntemleri listesi (array)
- `prep_tips` - Hazırlama ipuçları
- `cooking_suggestions` - Pişirme önerileri

**HTML Alanları:**
- `description_html` - Zengin metin editörü ile formatlı açıklama
- `benefits_html` - Zengin metin editörü ile formatlı faydalar

**SEO Objesi:**
- `seo` - Tüm SEO verilerini içeren obje

**HTML Entity Decoding:**
- `name` - Malzeme adı
- `category` - Kategori adı

### 4. RecipeMetaBox Admin UI Güncellemeleri

**Dosya:** `includes/Admin/RecipeMetaBox.php`

#### Yeni UI Alanları:
1. **Temel Bilgiler Bölümü:**
   - Pişirme Süresi (text input)
   - Öğün Tipi (select dropdown)
   - Porsiyon Bilgisi (text input)
   - Zorluk Seviyesi (select dropdown)
   - Dondurulabilir mi? (checkbox)
   - Saklama Bilgisi (textarea)

2. **Beslenme Değerleri Bölümü:**
   - Karbonhidrat (text input)
   - Yağ (text input)
   - Şeker (text input)
   - Sodyum (text input)
   - Mineraller (text input)

#### Kayıt İşleyicileri:
- Tüm yeni alanlar için `save_custom_meta_data()` metoduna kayıt işleyicileri eklendi
- Veri sanitizasyonu yapılıyor

### 5. IngredientMetaBox Admin UI Güncellemeleri

**Dosya:** `includes/Admin/IngredientMetaBox.php`

#### Yeni UI Alanları:
1. **Besin Değerleri (100g başına) Bölümü:**
   - Kalori (100g) (text input)
   - Protein (100g) (text input)
   - Karbonhidrat (100g) (text input)
   - Yağ (100g) (text input)
   - Lif (100g) (text input)
   - Şeker (100g) (text input)
   - Vitaminler (text input)
   - Mineraller (text input)

2. **Alerjen Bilgileri Bölümü:**
   - Alerjen mi? (checkbox)
   - Alerjen Tipi (text input)
   - Çapraz Bulaşma Riski (text input)
   - Alerji Semptomları (textarea)
   - Alternatif Malzemeler (textarea)

3. **Hazırlama Bilgileri Bölümü:**
   - Hazırlama Yöntemleri Listesi (textarea - her satıra bir yöntem)
   - Hazırlama İpuçları (textarea)
   - Pişirme Önerileri (textarea)

#### Kayıt İşleyicileri:
- Tüm yeni alanlar için `save_custom_meta_data()` metoduna kayıt işleyicileri eklendi
- Veri sanitizasyonu yapılıyor

## API Response Örnekleri

### Recipe Endpoint (`/wp-json/kg/v1/recipes/{slug}`)

```json
{
  "id": 123,
  "title": "Bal Kabaklı Bebek Çorbası",
  "slug": "bal-kabakli-bebek-corbasi",
  "content": "...",
  "excerpt": "...",
  "image": "...",
  "prep_time": "15 dk",
  "cook_time": "20 dk",
  "meal_type": "Öğle",
  "serving_size": "2 porsiyon",
  "difficulty": "Kolay",
  "freezable": true,
  "storage_info": "Buzdolabında 2 gün saklanabilir",
  "ingredients": [...],
  "instructions": [...],
  "nutrition": {
    "calories": "80 kcal",
    "protein": "2g",
    "carbs": "15g",
    "fat": "1g",
    "fiber": "3g",
    "sugar": "5g",
    "sodium": "50mg",
    "vitamins": "A, C, E",
    "minerals": "Kalsiyum, Demir"
  },
  "allergens": [],
  "age_groups": ["6-12 ay"],
  "diet_types": ["Püre", "Vegan"],
  "seo": {
    "title": "Bal Kabaklı Bebek Çorbası Tarifi | KidsGourmet",
    "description": "6 aydan itibaren verilebilen, A vitamini açısından zengin bal kabaklı bebek çorbası tarifi.",
    "focus_keywords": "bal kabaklı çorba, bebek çorbası, 6 ay bebek tarifi",
    "og_title": "...",
    "og_description": "...",
    "og_image": "https://..."
  }
}
```

### Ingredient Endpoint (`/wp-json/kg/v1/ingredients/{slug}`)

```json
{
  "id": 456,
  "name": "Brokoli",
  "slug": "brokoli",
  "description": "Kısa açıklama...",
  "description_html": "<p>HTML formatlı detaylı açıklama...</p>",
  "benefits": "Faydaları...",
  "benefits_html": "<p>HTML formatlı faydalar...</p>",
  "image": "...",
  "start_age": "6 ay",
  "allergy_risk": "Düşük",
  "season": "Kış",
  "category": "Sebzeler",
  "nutrition_per_100g": {
    "calories": "34 kcal",
    "protein": "2.8g",
    "carbs": "7g",
    "fat": "0.4g",
    "fiber": "2.6g",
    "sugar": "1.7g",
    "vitamins": "C, K, A",
    "minerals": "Potasyum, Kalsiyum"
  },
  "allergen_info": {
    "is_allergen": false,
    "allergen_type": null,
    "cross_contamination_risk": "Düşük",
    "allergy_symptoms": "",
    "alternative_ingredients": ""
  },
  "prep_methods_list": [
    "Buharda pişirme (en sağlıklı)",
    "Haşlama",
    "Fırında kavurma"
  ],
  "prep_tips": "Sapları da kullanılabilir, püre yapılabilir.",
  "cooking_suggestions": "...",
  "prep_by_age": [...],
  "pairings": [...],
  "seo": {
    "title": "Brokoli - Bebek Beslenmesi Rehberi | KidsGourmet",
    "description": "Bebekler için brokoli: Ne zaman verilir, nasıl hazırlanır, faydaları nelerdir?",
    "focus_keywords": "bebeklere brokoli, brokoli bebek, 6 ay brokoli"
  }
}
```

## Kullanım Talimatları

### Admin Panelinde Yeni Alanları Doldurma

1. **Tarifler için:**
   - WordPress Admin → Tarifler → Tarif Düzenle
   - "Tarif Detayları" meta box'ında yeni alanları doldurun
   - Kaydet

2. **Malzemeler için:**
   - WordPress Admin → Malzemeler → Malzeme Düzenle
   - "Malzeme Detayları" meta box'ında yeni alanları doldurun
   - Kaydet

### RankMath SEO Entegrasyonu

1. RankMath SEO eklentisini yükleyin ve aktif edin
2. Her tarif/malzeme için SEO ayarlarını yapın:
   - Focus Keywords
   - SEO Title
   - Meta Description
   - Open Graph ayarları
   - Twitter Card ayarları

3. API otomatik olarak bu verileri döndürecektir

### Fallback Davranışı

Eğer RankMath SEO verileri yoksa:
- `title` → `{Post Title} - KidsGourmet`
- `description` → Post excerpt'in ilk 30 kelimesi

## Test Sonuçları

```
=== Test Summary ===
Passed: 71
Failed: 0
Success Rate: 100%
```

Tüm testler başarılı! ✓

### Test Komutları

```bash
# Statik kod analizi
php test-api-improvements-static.php

# WordPress ortamında test (WordPress kurulu ise)
php test-api-improvements.php
```

## Geriye Dönük Uyumluluk

✅ Mevcut API response'ları bozulmadı
✅ Eski meta alanları korundu
✅ Sadece yeni alanlar eklendi
✅ Frontend'de kademeli entegrasyon mümkün

## İleri Adımlar

1. Frontend'de yeni API alanlarını kullanın
2. RankMath SEO eklentisini yapılandırın
3. Mevcut tarif ve malzemelere yeni meta verilerini ekleyin
4. SEO verilerini frontend'de render edin (meta tags, JSON-LD vb.)

## Yardımcı Linkler

- [RankMath SEO Dokümantasyonu](https://rankmath.com/kb/)
- [WordPress Meta API](https://developer.wordpress.org/reference/functions/get_post_meta/)
- [REST API Handbook](https://developer.wordpress.org/rest-api/)

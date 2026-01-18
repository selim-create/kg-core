# İçerik Embed Sistemi - Uygulama Özeti

## Genel Bakış

WordPress editöründe standart post içerisine tarif, malzeme, araç ve keşfet yazılarını embed olarak ekleyebilme özelliği başarıyla uygulandı. Sistem shortcode tabanlı çalışır ve REST API üzerinden frontend'e tam veri iletir.

## Oluşturulan Dosyalar

### Backend (PHP)

1. **`includes/Shortcodes/ContentEmbed.php`** (11.76 KB)
   - Shortcode sistemi (`[kg-embed]`)
   - REST API field kaydı (`embedded_content`)
   - İçerik extraction ve parsing
   - Tip-bazlı veri getirme (recipe, ingredient, tool, post)
   - Yardımcı metodlar (age group renkleri, görseller, yazar bilgisi, okuma süresi)

2. **`includes/Admin/EmbedSelector.php`** (8.13 KB)
   - Admin UI button ve modal
   - AJAX search endpoint
   - İçerik seçimi ve shortcode ekleme

### Frontend Assets

3. **`assets/css/embed-selector.css`** (4.13 KB)
   - Modal tasarımı
   - Tab sistemi stilleri
   - Responsive tasarım
   - İtem seçim stilleri

4. **`assets/js/embed-selector.js`** (8.7 KB)
   - Modal açma/kapama
   - Tab geçişleri
   - AJAX arama
   - Çoklu seçim
   - Shortcode ekleme

### Test Dosyaları

5. **`tests/test-content-embed-system.php`** (12.12 KB)
   - Kapsamlı test suite
   - 12 farklı test senaryosu
   - REST API testleri

6. **`tests/validate-content-embed-static.php`** (9.14 KB)
   - Statik kod analizi
   - Dosya yapısı kontrolü
   - Kullanım örnekleri

## Kullanım

### Shortcode Formatları

```
[kg-embed type="recipe" id="123"]
[kg-embed type="ingredient" id="456"]
[kg-embed type="tool" id="789"]
[kg-embed type="post" id="101"]

Çoklu embed:
[kg-embed type="recipe" ids="123,456,789"]
```

### Desteklenen Tipler

- **recipe** - Tarifler
- **ingredient** - Malzemeler
- **tool** - Araçlar
- **post** - Keşfet yazıları (standart WordPress post)

### Admin UI Kullanımı

1. WordPress post editör ekranında "İçerik Embed Et" butonuna tıklayın
2. İçerik tipi sekmesini seçin (Tarifler, Malzemeler, Araçlar, Keşfet)
3. Arama kutusunu kullanarak içerik arayın
4. Checkbox'larla istediğiniz içerikleri seçin
5. "Embed Ekle" butonuna basarak shortcode'u editöre ekleyin

## REST API Yanıt Yapısı

### Örnek Yanıt

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
          "slug": "havuclu-bebek-puresi",
          "excerpt": "Bebeğiniz için lezzetli...",
          "image": "https://...",
          "url": "/tarifler/havuclu-bebek-puresi",
          "embed_type": "recipe",
          "prep_time": "15 dk",
          "age_group": "6-8 Ay",
          "age_group_color": "#FFAB91",
          "diet_types": ["Vejetaryen"],
          "allergens": [],
          "is_featured": false
        }
      ]
    }
  ]
}
```

### İçerik Tiplerine Göre Alanlar

#### Recipe (Tarif)
- `id`, `title`, `slug`, `excerpt`, `image`, `url`, `embed_type`
- `prep_time` - Hazırlama süresi
- `age_group` - Yaş grubu
- `age_group_color` - Yaş grubu rengi (HEX)
- `diet_types` - Diyet tipleri (array)
- `allergens` - Alerjenler (array)
- `is_featured` - Öne çıkan mı (boolean)

#### Ingredient (Malzeme)
- `id`, `title`, `slug`, `excerpt`, `image`, `url`, `embed_type`
- `start_age` - Başlama yaşı
- `benefits` - Faydaları
- `allergy_risk` - Alerji riski
- `allergens` - Alerjenler (array)
- `season` - Mevsim

#### Tool (Araç)
- `id`, `title`, `slug`, `excerpt`, `image`, `url`, `embed_type`
- `tool_type` - Araç tipi
- `tool_icon` - Araç ikonu
- `tool_types` - Araç tipleri (array)
- `is_active` - Aktif mi (boolean)

#### Post (Keşfet)
- `id`, `title`, `slug`, `excerpt`, `image`, `url`, `embed_type`
- `category` - Kategori (object: name, slug)
- `author` - Yazar (object: name, avatar)
- `date` - Yayın tarihi (Y-m-d)
- `read_time` - Okuma süresi (örn: "5 dk")

## Teknik Detaylar

### Pozisyon Hesaplama

Sistem, shortcode'un içerikte kaçıncı paragraftan sonra görüneceğini otomatik hesaplar:
- HTML `<p>` etiketlerini sayar
- `\n\n` (çift satır sonu) karakterlerini paragraf olarak kabul eder
- Position değeri REST API'de döndürülür

### AJAX Endpoint

**Action:** `kg_search_embeddable_content`

**Parametreler:**
- `type` - İçerik tipi (recipe, ingredient, tool, post)
- `search` - Arama terimi (opsiyonel)
- `nonce` - Güvenlik nonce

**Yanıt:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 123,
        "title": "Havuçlu Püre",
        "image": "https://...",
        "meta": "15 dk • 6-8 Ay",
        "icon": "dashicons-food"
      }
    ]
  }
}
```

## Güvenlik

- Nonce doğrulaması her AJAX isteğinde
- Sadece `publish` durumundaki içerikler embed edilebilir
- XSS koruması için HTML entity decoding
- Input sanitization ve validation

## Performans

- Sadece REST API isteklerinde embed extraction
- Minimal veritabanı sorguları
- Önbellek dostu yapı
- Lazy loading desteği

## Browser Uyumluluğu

- Modern tarayıcılar (Chrome, Firefox, Safari, Edge)
- IE11+ desteği
- Responsive tasarım
- Mobile-friendly modal

## Test Coverage

### Kapsanan Alanlar
- ✅ Shortcode kaydı
- ✅ Tek ve çoklu embed
- ✅ Tüm içerik tipleri (recipe, ingredient, tool, post)
- ✅ REST API field kaydı
- ✅ Embed extraction
- ✅ Pozisyon hesaplama
- ✅ Type-specific data getters
- ✅ AJAX search endpoint
- ✅ Admin UI bileşenleri
- ✅ Syntax validation
- ✅ Class structure
- ✅ Integration checks

## Sonraki Adımlar (İsteğe Bağlı)

1. **Önizleme Özelliği**: Modal'da seçilen içeriğin önizlemesini gösterme
2. **Sıralama**: Embed edilecek içeriklerin sıralamasını belirleme
3. **Önbellek**: Sık kullanılan embed verilerini önbellekleme
4. **Analitik**: Hangi içeriklerin daha çok embed edildiğini takip etme
5. **Gutenberg Bloğu**: Gutenberg için özel embed bloğu

## Katkıda Bulunanlar

- Backend geliştirme: KG Core Team
- Frontend geliştirme: KG Core Team
- Test & QA: KG Core Team

## Lisans

Bu özellik KG Core plugin'in bir parçasıdır ve plugin ile aynı lisansa tabidir.

---

**Son Güncelleme:** 2026-01-18
**Versiyon:** 1.0.0

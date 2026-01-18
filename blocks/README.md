# KG Core - Gutenberg Blocks

Bu dizin, KidsGourmet içerik embed sistemi için Gutenberg bloklarını içerir.

## Bloklar

### KG İçerik Embed (`kg-core/embed`)

WordPress Gutenberg editöründe kullanılabilen, içerik arama ve embed etme bloğu.

**Özellikler:**
- 4 farklı içerik tipi için tab sistemi (Tarif, Malzeme, Araç, Yazı)
- Canlı arama (debounced AJAX)
- Çoklu seçim desteği
- Seçilen içeriklerin önizlemesi
- Server-side rendering için shortcode çıktısı

## Geliştirme

### Gereksinimler

- Node.js 14+
- npm 7+

### Kurulum

```bash
npm install
```

### Build

Blokları derlemek için:

```bash
npm run build:blocks
```

### Geliştirme Modu

Dosya değişikliklerini izlemek ve otomatik derlemek için:

```bash
npm run start:blocks
```

## Dosya Yapısı

```
blocks/
├── kg-embed/              # Kaynak dosyalar
│   ├── block.json         # Block metadata
│   ├── index.js           # Block kaydı
│   ├── edit.js            # Editor componenti (React)
│   ├── save.js            # Kaydetme fonksiyonu
│   └── editor.scss        # Editor stilleri
└── build/                 # Derlenmiş dosyalar (git'e eklenmez)
    └── kg-embed/
        ├── block.json     # Kopyalanan metadata
        ├── index.js       # Derlenmiş JavaScript
        ├── index.css      # Derlenmiş CSS
        └── index.asset.php # Bağımlılıklar ve versiyon
```

## Kullanım

1. WordPress admin panelinde bir Post (Yazı) düzenleyin
2. "+" butonuna basarak blok ekleyici açın
3. "KG İçerik Embed" bloğunu arayın ve ekleyin
4. İstediğiniz içerik tipini seçin (Tarif, Malzeme, Araç, Yazı)
5. Arama yapın ve içerikleri seçin
6. Sayfayı kaydedin

## Teknik Detaylar

### React Bileşenleri

Block edit componenti şunları kullanır:
- `@wordpress/element` - React wrapper
- `@wordpress/components` - UI bileşenleri (TextControl, Spinner, Button)
- `@wordpress/i18n` - Çeviri desteği
- Native fetch API - AJAX istekleri

### AJAX Endpoint

**Action:** `kg_block_search_content`

**Parametreler:**
- `nonce` - Security nonce
- `type` - İçerik tipi (recipe, ingredient, tool, post)
- `search` - Arama terimi

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 123,
        "title": "İçerik Başlığı",
        "image": "https://...",
        "meta": "Meta bilgisi",
        "icon": "dashicons-food"
      }
    ]
  }
}
```

### Server-side Rendering

Block kaydedildiğinde şu shortcode formatında çıktı verilir:

```html
<div class="kg-embed-placeholder" data-type="recipe" data-ids="123,456">
  [kg-embed type="recipe" ids="123,456"]
</div>
```

Bu shortcode `ContentEmbed` sınıfı tarafından REST API'de işlenir ve frontend'e embed verileri olarak döner.

## Sorun Giderme

### Build Hataları

Eğer build sırasında hata alıyorsanız:

```bash
# node_modules'u temizle ve yeniden kur
rm -rf node_modules package-lock.json
npm install
npm run build:blocks
```

### Block Görünmüyor

1. WordPress admin panelinde Eklentiler sayfasından KG Core eklentisinin aktif olduğunu kontrol edin
2. Tarayıcı konsolunu kontrol edin (F12)
3. Build dosyalarının var olduğunu kontrol edin: `blocks/build/kg-embed/`

### AJAX Çalışmıyor

1. Tarayıcı Network sekmesinde AJAX isteklerini kontrol edin
2. WordPress nonce'un doğru oluşturulduğunu kontrol edin
3. PHP error log'unu kontrol edin

## Lisans

GPL-2.0-or-later

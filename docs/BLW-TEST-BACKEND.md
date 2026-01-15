# BLW Hazırlık Testi - Backend Dokümantasyonu

## Genel Bakış

KidsGourmet projesinin "Akıllı Asistan" özelliği kapsamında, WHO standartlarında bir BLW (Baby-Led Weaning) Hazırlık Testi için gerekli backend altyapısı oluşturulmuştur.

## API Endpoints

### Tool Endpoints

#### 1. GET /kg/v1/tools
Tüm araçları listeler.

**Response:**
```json
[
  {
    "id": 123,
    "title": "BLW Hazırlık Testi",
    "slug": "blw-test",
    "description": "Bebeğinizin BLW'ye hazır olup olmadığını test edin",
    "tool_type": "blw_test",
    "icon": "fa-baby",
    "requires_auth": false,
    "thumbnail": "https://..."
  }
]
```

#### 2. GET /kg/v1/tools/{slug}
Tek bir aracı getirir.

**Response:** Yukarıdaki ile aynı format, tek obje.

#### 3. GET /kg/v1/tools/blw-test/config
BLW test konfigürasyonunu (sorular, sonuç kategorileri) getirir.

**Response:**
```json
{
  "questions": [
    {
      "id": "q1_sitting",
      "category": "physical_readiness",
      "question": "Bebeğiniz desteksiz oturabiliyor mu?",
      "description": "Bebeğin sırtı dik ve baş kontrolü tam olmalı",
      "icon": "fa-baby",
      "weight": 80,
      "options": [
        {
          "id": "sitting_yes",
          "text": "Evet, rahatça oturuyor",
          "value": 100,
          "is_red_flag": false,
          "red_flag_message": ""
        }
      ]
    }
  ],
  "result_buckets": [
    {
      "id": "ready",
      "min_score": 80,
      "max_score": 100,
      "title": "BLW'ye Hazır Görünüyorsunuz!",
      "subtitle": "Bebeğiniz tüm kriterleri karşılıyor",
      "color": "green",
      "icon": "fa-check-circle",
      "description": "Tebrikler! Bebeğiniz...",
      "action_items": ["..."],
      "next_steps": ["..."]
    }
  ],
  "disclaimer_text": "<p>Bu test yalnızca genel bilgilendirme...</p>",
  "emergency_text": "<p><strong>ACİL DURUM:</strong>...</p>"
}
```

#### 4. POST /kg/v1/tools/blw-test/submit
BLW test sonucu gönderir.

**Request Body:**
```json
{
  "answers": {
    "q1_sitting": "sitting_yes",
    "q2_head_control": "head_yes",
    ...
  },
  "child_id": "uuid-of-child", // opsiyonel
  "register": true, // opsiyonel, kayıt olmak için
  "email": "user@example.com", // register=true ise gerekli
  "password": "SecurePass123", // register=true ise gerekli
  "name": "Ahmet Yılmaz" // opsiyonel
}
```

**Response:**
```json
{
  "score": 85.5,
  "result": {
    "id": "ready",
    "min_score": 80,
    "max_score": 100,
    "title": "BLW'ye Hazır Görünüyorsunuz!",
    "subtitle": "...",
    "color": "green",
    "icon": "fa-check-circle",
    "description": "...",
    "action_items": ["..."],
    "next_steps": ["..."]
  },
  "red_flags": [
    {
      "question": "Bebeğiniz kaç aylık?",
      "message": "WHO, ek gıdaya 6. aydan önce başlanmamasını önermektedir..."
    }
  ],
  "timestamp": "2024-01-15T10:30:00+00:00",
  "token": "jwt-token-here", // sadece register=true ise
  "user_id": 123 // sadece register=true ise
}
```

### User BLW Endpoints

#### 5. GET /kg/v1/user/blw-results
Kullanıcının tüm BLW sonuçlarını getirir.

**Headers:** `Authorization: Bearer {jwt-token}`

**Response:**
```json
[
  {
    "id": "uuid-v4",
    "child_id": "child-uuid",
    "score": 85.5,
    "result_category": "ready",
    "red_flags": [...],
    "timestamp": "2024-01-15T10:30:00+00:00"
  }
]
```

#### 6. GET /kg/v1/user/children/{child_id}/blw-results
Belirli bir çocuğun BLW sonuçlarını getirir.

**Headers:** `Authorization: Bearer {jwt-token}`

**Response:** Yukarıdaki ile aynı format, sadece ilgili çocuğa ait sonuçlar.

## Skorlama Mantığı

```
toplam_skor = sum(cevap_puani * soru_agirligi) / sum(soru_agirliklari)
```

### Kategori Ağırlıkları

- **physical_readiness** (Fiziksel Hazırlık): ~%70
  - Oturma: ağırlık 80
  - Baş kontrolü: ağırlık 75
  - Dil refleksi: ağırlık 70
  - İlgi: ağırlık 60
  - Kavrama: ağırlık 70

- **safety** (Güvenlik): ~%30
  - Yaş: ağırlık 50
  - Tıbbi durumlar: ağırlık 40
  - İlk yardım: ağırlık 35
  - Gözetim: ağırlık 45

- **environment** (Çevre): %10
  - Mama sandalyesi: ağırlık 30

### Sonuç Kategorileri

- **ready** (80-100 puan): "BLW'ye Hazır Görünüyorsunuz!" - Yeşil
- **almost_ready** (55-79 puan): "Neredeyse Hazır!" - Sarı
- **not_ready** (0-54 puan): "Biraz Daha Zaman" - Kırmızı

## Özellikler

### 1. Kayıtsız Kullanıcı Akışı
Test yapılabilir, sonuç görülebilir, sonra kayıt olunabilir.

### 2. Kayıt + Test Tek Adımda
`POST /tools/blw-test/submit?register=true` ile test ve kayıt tek adımda yapılır.

### 3. Çocuk Profili Entegrasyonu
Sonuçlar `child_id` ile çocuk profiline kaydedilir.

### 4. Kırmızı Bayraklar
Kritik cevaplarda (4 ayın altı, tıbbi durumlar vb.) uyarılar gösterilir.

### 5. Admin Yönetimi
ACF ile sorular ve sonuçlar admin panelden düzenlenebilir (hardcode değil).

## ACF Alanları

### Tool Temel Alanları
- `tool_type`: Araç tipi (select)
- `tool_icon`: FontAwesome class (text)
- `is_active`: Aktif/Pasif (true/false)
- `requires_auth`: Giriş gerekli mi (true/false)

### BLW Test Alanları (tool_type=blw_test için)
- `blw_questions`: Sorular (repeater)
  - `id`: Soru ID
  - `category`: Kategori
  - `question`: Soru metni
  - `description`: Açıklama
  - `icon`: İkon
  - `weight`: Ağırlık (0-100)
  - `options`: Seçenekler (repeater)
    - `id`: Seçenek ID
    - `text`: Metin
    - `value`: Değer (0-100)
    - `is_red_flag`: Kırmızı bayrak (true/false)
    - `red_flag_message`: Uyarı mesajı

- `result_buckets`: Sonuç kategorileri (repeater)
  - `id`: Kategori ID
  - `min_score`: Min puan
  - `max_score`: Max puan
  - `title`: Başlık
  - `subtitle`: Alt başlık
  - `color`: Renk
  - `icon`: İkon
  - `description`: Açıklama
  - `action_items`: Aksiyon maddeleri (textarea)
  - `next_steps`: Sonraki adımlar (textarea)

- `disclaimer_text`: Sorumluluk reddi (wysiwyg)
- `emergency_text`: Acil durum metni (wysiwyg)

## Veritabanı Şeması

### User Meta
- `_kg_blw_results`: Array of BLW test results
  ```php
  [
    [
      'id' => 'uuid-v4',
      'child_id' => 'child-uuid or null',
      'score' => 85.5,
      'result_category' => 'ready',
      'red_flags' => [...],
      'timestamp' => '2024-01-15T10:30:00+00:00'
    ]
  ]
  ```

## Güvenlik

- JWT authentication opsiyonel
- Kayıtsız kullanıcılar test yapabilir ama sonuçlar kaydedilmez
- Çocuk verilerine erişim sadece ebeveynle sınırlı
- XSS koruması için tüm kullanıcı girdileri sanitize edilir
- Red flag mesajları kullanıcılara kritik durumlarda uyarı verir

## Test Etme

Test dosyası: `/tests/test-blw-backend.php`

```bash
php tests/test-blw-backend.php
```

Tüm testler başarılı geçmeli (43/43 passed).

## Örnek Kullanım Senaryoları

### Senaryo 1: Kayıtsız Kullanıcı
1. `GET /tools/blw-test/config` - Test sorularını al
2. Kullanıcı soruları cevaplar
3. `POST /tools/blw-test/submit` - Sonucu al (kaydedilmez)
4. Kullanıcı kayıt olmaya karar verir
5. Frontend'de kayıt formunu göster

### Senaryo 2: Kayıt + Test Birlikte
1. `GET /tools/blw-test/config` - Test sorularını al
2. Kullanıcı soruları cevaplar
3. Kullanıcı kayıt bilgilerini girer
4. `POST /tools/blw-test/submit` (register=true, email, password) - Kayıt + Test
5. Token ve sonuç döner

### Senaryo 3: Kayıtlı Kullanıcı + Çocuk Profili
1. Kullanıcı giriş yapar (JWT token alır)
2. `GET /tools/blw-test/config` - Test sorularını al
3. Kullanıcı çocuğunu seçer veya ekler
4. `POST /tools/blw-test/submit` (child_id dahil, JWT token ile)
5. Sonuç kaydedilir
6. `GET /user/children/{child_id}/blw-results` - Çocuğun geçmiş sonuçlarını göster

## WHO Standartları

Test, Dünya Sağlık Örgütü'nün (WHO) ek gıda önerilerine uygun olarak tasarlanmıştır:
- Minimum 6 ay yaş önerisi
- Fiziksel hazırlık işaretleri (oturma, baş kontrolü)
- Güvenlik önlemleri (gözetim, ilk yardım)
- Kırmızı bayrak durumları (premature, tıbbi sorunlar)

## Sonuç

Bu implementasyon, BLW hazırlık testinin tüm backend gereksinimlerini karşılamaktadır:
✅ Tool CPT genişletildi
✅ ToolController oluşturuldu
✅ UserController'a BLW endpoints eklendi
✅ Main plugin file güncellendi
✅ WHO standartlarında 10 soru
✅ 3 sonuç kategorisi
✅ Ağırlıklı skorlama
✅ Kırmızı bayrak sistemi
✅ Kayıtsız kullanıcı desteği
✅ Kayıt + Test birlikte
✅ Çocuk profili entegrasyonu
✅ ACF ile admin yönetimi

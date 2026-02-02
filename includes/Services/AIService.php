<?php
namespace KG_Core\Services;

class AIService {
    private $provider;
    private $api_key;
    private $model;
    
    public function __construct() {
        $this->provider = get_option('kg_ai_provider', 'openai');
        $this->api_key = get_option('kg_openai_api_key', '') ?: get_option('kg_ai_api_key', '');
        $this->model = get_option('kg_ai_model', 'gpt-4o-mini');
    }
    
    /**
     * Generate ingredient content using AI
     * 
     * @param string $ingredient_name Name of the ingredient in Turkish
     * @return array|WP_Error Parsed ingredient data or error
     */
    public function generateIngredientContent($ingredient_name) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'AI API anahtarı ayarlanmamış.');
        }
        
        $prompt = $this->buildIngredientPrompt($ingredient_name);
        
        try {
            switch ($this->provider) {
                case 'openai':
                    $response = $this->callOpenAI($prompt);
                    break;
                case 'anthropic':
                    $response = $this->callAnthropic($prompt);
                    break;
                case 'gemini':
                    $response = $this->callGemini($prompt);
                    break;
                default:
                    return new \WP_Error('invalid_provider', 'Geçersiz AI sağlayıcı.');
            }
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $this->parseIngredientResponse($response);
            
        } catch (\Exception $e) {
            error_log('KG Core AI Error: ' . $e->getMessage());
            return new \WP_Error('ai_error', 'AI yanıt hatası: ' . $e->getMessage());
        }
    }
    
    /**
     * Build prompt for ingredient content generation
     * 
     * @param string $name Ingredient name
     * @return string Formatted prompt
     */
    private function buildIngredientPrompt($name) {
        $prompt = "Sen bebek beslenmesi konusunda uzman bir diyetisyen ve çocuk doktorusun. ";
        $prompt .= "Aşağıdaki malzeme hakkında Türkçe olarak detaylı ve bilimsel bilgi ver.\n\n";
        $prompt .= "Malzeme: {$name}\n\n";
        $prompt .= "Lütfen yanıtını SADECE aşağıdaki JSON formatında ver (başka açıklama ekleme):\n\n";
        
        $json_template = [
            'title' => 'Malzeme Adı (Türkçe)',
            'excerpt' => 'SEO için 150-160 karakter meta açıklama',
            'content' => '3-4 paragraf detaylı açıklama (HTML <p> etiketleri ile)',
            'category' => 'Meyveler|Sebzeler|Proteinler|Tahıllar|Süt Ürünleri|Baklagiller|Yağlar|Sıvılar|Baharatlar|Özel Ürünler (Bu alan ingredient-category taxonomy olarak atanacak)',
            'start_age' => 6,
            'benefits' => 'Sağlık faydaları detaylı açıklama (HTML formatında)',
            'allergy_risk' => 'Düşük|Orta|Yüksek',
            'allergens' => ['varsa alerjen listesi'],
            'cross_contamination' => 'Düşük|Orta|Yüksek (alerjen değilse boş bırak)',
            'allergy_symptoms' => 'Alerji belirtileri ve semptomları detaylı açıklama (alerjen değilse boş bırak)',
            'alternatives' => 'Alternatif malzemeler listesi ve açıklama (alerjen değilse boş bırak)',
            'season' => ['İlkbahar', 'Yaz'], // Array of seasons - can select multiple
            'storage_tips' => 'Saklama koşulları',
            'selection_tips' => 'Nasıl seçilir? Olgunluk belirtileri',
            'pro_tips' => 'Püf noktası ve önemli ipuçları',
            'preparation_tips' => 'Bebekler için hazırlama ipuçları',
            'prep_methods' => ['Püre', 'Haşlama', 'Buhar', 'Ezme'],
            'prep_by_age' => [
                [
                    'age' => '6-9 Ay',
                    'method' => 'Püre',
                    'text' => 'Bu yaş grubu için detaylı hazırlama talimatı'
                ],
                [
                    'age' => '9+ Ay (BLW)',
                    'method' => 'Parmak Yiyecek',
                    'text' => 'BLW için detaylı hazırlama talimatı'
                ]
            ],
            
            // YENİ: Uyumlu İkililer (ZORUNLU DOLDURULMALI)
            'pairings' => [
                ['emoji' => '🍌', 'name' => 'Muz'],
                ['emoji' => '🥚', 'name' => 'Yumurta'],
                ['emoji' => '🍠', 'name' => 'Tatlı Patates'],
                ['emoji' => '🥛', 'name' => 'Yoğurt']
            ],
            
            // Besin Değerleri (100g başına)
            'nutrition' => [
                'calories' => '100g için kalori değeri (sayı)',
                'protein' => '100g için gram cinsinden protein (sayı)',
                'carbs' => '100g için gram cinsinden karbonhidrat (sayı)',
                'fat' => '100g için gram cinsinden yağ (sayı)',
                'fiber' => '100g için gram cinsinden lif (sayı)',
                'sugar' => '100g için gram cinsinden şeker (sayı)',
                'vitamins' => 'Başlıca vitaminler (örn: A, C, K)',
                'minerals' => 'Başlıca mineraller (örn: Potasyum, Kalsiyum)'
            ],
            
            'faq' => [
                ['question' => 'Bebeklere ne zaman verilebilir?', 'answer' => 'Detaylı cevap'],
                ['question' => 'Alerji riski var mı?', 'answer' => 'Detaylı cevap'],
                ['question' => 'Nasıl saklanmalı?', 'answer' => 'Detaylı cevap']
            ],
            
            // YENİ: SEO Meta (RankMath için - %80+ skor hedefi)
            'seo' => [
                'title' => 'Bebeklere [Malzeme] Ne Zaman Verilir? Faydaları ve Hazırlama | KidsGourmet - 60 karakter civarında olmalı',
                'description' => 'Bebeklere [malzeme] kaç ayda verilir, faydaları nelerdir, nasıl hazırlanır? Uzman önerileri ve püf noktaları ile tam rehber. - 150-160 karakter arasında, odak kelimeyi içermeli',
                'focus_keyword' => 'bebeklere [malzeme] ne zaman verilir',
                'keywords' => ['bebek beslenmesi', 'ek gıda', '[malzeme]', 'bebeklere [malzeme]', '[malzeme] faydaları']
            ],
            
            'image_search_query' => 'İngilizce görsel arama terimi (örn: "fresh carrots baby food")'
        ];
        
        $prompt .= json_encode($json_template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt .= "\n\n⚠️ KRİTİK: BAŞLANGIÇ YAŞI KURALLARI (MUTLAKA UYULMALI!)\n";
        $prompt .= "start_age alanı çocuğun HAYATİ GÜVENLİĞİ için kritiktir. ASLA tahminle doldurma!\n\n";
        $prompt .= "YASAKLI / GEÇ VERİLMESİ GEREKEN MALZEMELER:\n";
        $prompt .= "- Bal: start_age = 12 (Botulizm riski - kesinlikle 1 yaş altına YASAK)\n";
        $prompt .= "- Çay, kahve, kafeinli içecekler: start_age = 24 (tercihen hiç verilmemeli)\n";
        $prompt .= "- İnek sütü (içecek olarak): start_age = 12\n";
        $prompt .= "- Çilek, kivi, ananas, narenciye (alerjik meyveler): start_age = 8 minimum\n";
        $prompt .= "- Tam yumurta (sarısı+beyazı): start_age = 8\n";
        $prompt .= "- Yumurta beyazı tek başına: start_age = 12\n";
        $prompt .= "- Kuruyemişler (bütün halde): start_age = 36 (boğulma riski)\n";
        $prompt .= "- Kuruyemiş ezmesi: start_age = 6\n";
        $prompt .= "- Deniz ürünleri (kabuklu): start_age = 12\n";
        $prompt .= "- Şeker, tuz eklenmiş gıdalar: start_age = 24+\n";
        $prompt .= "- Kakao, çikolata: start_age = 12\n";
        $prompt .= "- Mantar: start_age = 12\n\n";
        $prompt .= "GÜVENLİ ERKEN BAŞLANGIÇ (6 ay):\n";
        $prompt .= "- Avokado, muz, elma (pişmiş), armut (pişmiş)\n";
        $prompt .= "- Havuç, kabak, patates, tatlı patates\n";
        $prompt .= "- Pirinç, yulaf\n\n";
        $prompt .= "8 AY BAŞLANGIÇ:\n";
        $prompt .= "- Yoğurt, peynir\n";
        $prompt .= "- Mercimek, nohut (iyi pişmiş)\n";
        $prompt .= "- Çilek, kivi (az miktarda test ile)\n";
        $prompt .= "- Domates (çekirdeksiz, kabuğu soyulmuş)\n";
        $prompt .= "- Tavuk, hindi, dana\n\n";
        $prompt .= "ÖNEMLİ: prep_by_age alanındaki en erken yaş grubu ile start_age AYNI OLMALI!\n";
        $prompt .= "Eğer prep_by_age'de en erken '8-9 Ay' yazıyorsa, start_age = 8 olmalı!\n\n";
        
        $prompt .= "ÖNEMLİ MEVSİM KURALLARI:\n";
        $prompt .= "- Mevsim alanı için BİRDEN FAZLA mevsim seçilebilir (array olarak döndür)\n";
        $prompt .= "- Türkiye'nin mevsim koşullarına ve DOĞAL ÜRETİM dönemine göre değerlendir\n";
        $prompt .= "- 'Tüm Yıl' sadece gerçekten her mevsimde TAZE olarak bulunabilen malzemeler için seçilmeli\n";
        $prompt .= "- SERADA YETİŞTİRİLEN değil, DOĞAL MEVSİMİNE göre belirle!\n\n";
        $prompt .= "ÖRNEK MEVSİMLER (Türkiye):\n";
        $prompt .= "- Lahana, ıspanak, pırasa, kereviz: ['Kış'] veya ['Sonbahar', 'Kış']\n";
        $prompt .= "- Çilek: ['İlkbahar'] veya ['İlkbahar', 'Yaz']\n";
        $prompt .= "- Kiraz, kayısı, şeftali: ['Yaz']\n";
        $prompt .= "- Domates, biber, patlıcan: ['Yaz', 'Sonbahar']\n";
        $prompt .= "- Elma, armut: ['Sonbahar', 'Kış']\n";
        $prompt .= "- Havuç, patates, soğan: ['Tüm Yıl'] (gerçekten tüm yıl taze bulunabilir)\n";
        $prompt .= "- Muz, avokado (ithal): ['Tüm Yıl']\n\n";
        
        $prompt .= "ZORUNLU KATEGORİLER (sadece bunlardan biri seçilmeli):\n";
        $prompt .= "Baharatlar | Baklagiller | Meyveler | Özel Ürünler | Proteinler | Sebzeler | Sıvılar | Süt Ürünleri | Tahıllar | Yağlar\n";
        $prompt .= "Bu kategoriler dışında bir değer KABUL EDİLMEZ!\n\n";
        
        $prompt .= "HAZIRLAMA YÖNTEMLERİ MANTIK KURALLARI:\n\n";
        $prompt .= "SIVILAR (çay, su, meyve suyu, et suyu):\n";
        $prompt .= "- prep_methods = ['Demleme', 'Soğutma', 'Seyreltme', 'Kaynatma']\n";
        $prompt .= "- ASLA 'Püre', 'Ezme', 'Rendeleme' yazma!\n\n";
        $prompt .= "BAHARATLAR (tarçın, zerdeçal, kimyon):\n";
        $prompt .= "- prep_methods = ['Toz halinde ekleme', 'Kaynatma ile infüzyon']\n";
        $prompt .= "- ASLA 'Haşlama', 'Püre' yazma!\n\n";
        $prompt .= "MEYVELER:\n";
        $prompt .= "- prep_methods = ['Püre', 'Ezme', 'Dilim', 'Rendelenmiş', 'Parmak yiyecek']\n\n";
        $prompt .= "SEBZELER:\n";
        $prompt .= "- prep_methods = ['Püre', 'Haşlama', 'Buhar', 'Fırın', 'Parmak yiyecek']\n\n";
        $prompt .= "PROTEİNLER (et, tavuk, balık):\n";
        $prompt .= "- prep_methods = ['Haşlama', 'Buhar', 'Fırın', 'Kıyma', 'Püre']\n\n";
        $prompt .= "SÜT ÜRÜNLERİ:\n";
        $prompt .= "- prep_methods = ['Doğrudan servis', 'Karıştırma', 'Rendelenmiş']\n\n";
        $prompt .= "TAHILLAR:\n";
        $prompt .= "- prep_methods = ['Haşlama', 'Kaynatma', 'Püre', 'Lapası']\n\n";
        $prompt .= "YAĞLAR:\n";
        $prompt .= "- prep_methods = ['Çiğ ekleme', 'Pişirme yağı olarak']\n\n";
        $prompt .= "⚠️ Her malzemenin kategorisiyle UYUMLU yöntemler seç!\n";
        $prompt .= "Mantıksız kombinasyonlar (örn: 'Çay püresi') KABUL EDİLEMEZ!\n\n";
        
        $prompt .= "DİĞER ÖNEMLİ KURALLAR:\n";
        $prompt .= "1. ÖNEMLİ: 'pairings' alanı ZORUNLUDUR ve mutlaka 3-5 uyumlu malzeme içermelidir!\n";
        $prompt .= "   Bebek beslenmesinde birlikte verilebilecek, lezzet ve besin değeri açısından uyumlu malzemeleri listele.\n";
        $prompt .= "   Format: [{'emoji': '🍌', 'name': 'Muz'}, {'emoji': '🥛', 'name': 'Yoğurt'}]\n";
        $prompt .= "2. 'seo' alanındaki 'focus_keyword' malzeme adını içermeli ve doğal bir soru formatında olmalı.\n";
        $prompt .= "3. 'seo' alanındaki 'title' 50-60 karakter arasında, çekici ve bilgilendirici olmalı, odak kelimeyi başta içermeli.\n";
        $prompt .= "4. 'seo' alanındaki 'description' tam 150-160 karakter olmalı, odak kelimeyi ve harekete geçirici bir çağrı içermeli.\n";
        $prompt .= "5. 'prep_by_age' alanında her yaş grubu için spesifik ve pratik tavsiyeler ver.\n";
        $prompt .= "6. 'nutrition' alanındaki tüm değerler 100g başına olmalı.\n";
        $prompt .= "7. Tüm içerik Türkçe olmalı, sadece emoji'ler evrensel.\n";
        $prompt .= "8. Bilimsel ve güvenilir bilgiler ver, abartılı ifadelerden kaçın.\n";
        $prompt .= "9. Alerjen olmayan malzemeler için 'cross_contamination', 'allergy_symptoms' ve 'alternatives' alanlarını boş bırak.\n";
        $prompt .= "10. Content alanı için en az 3-4 paragraf detaylı, SEO-dostu, bilgilendirici içerik yaz. Her paragraf <p> etiketi ile sarılmalı.\n";
        
        $prompt .= "\n\n⚠️ ÖNEMLİ HATIRLATMALAR:\n";
        $prompt .= "1. 'pairings' alanı ZORUNLUDUR! Mutlaka 3-5 uyumlu malzeme içermelidir.\n";
        $prompt .= "   Format: [{\"emoji\": \"🍌\", \"name\": \"Muz\"}, {\"emoji\": \"🥛\", \"name\": \"Yoğurt\"}]\n";
        $prompt .= "   Bu alan BOŞ BIRAKILAMAZ!\n";
        $prompt .= "2. 'season' alanı array olmalı: [\"Kış\"] veya [\"Yaz\", \"Sonbahar\"]\n";
        $prompt .= "3. Tüm JSON alanları doldurulmalı, boş bırakılmamalı.\n";
        
        return $prompt;
    }
    
    /**
     * Call OpenAI API
     * 
     * @param string $prompt The prompt to send
     * @return string|WP_Error API response or error
     */
    private function callOpenAI($prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sen bebek beslenmesi konusunda uzman bir asistansın. Yanıtlarını her zaman JSON formatında veriyorsun.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Bilinmeyen hata';
            return new \WP_Error('openai_error', 'OpenAI API hatası: ' . $error_msg);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return new \WP_Error('openai_parse_error', 'OpenAI yanıtı ayrıştırılamadı.');
        }
        
        return $body['choices'][0]['message']['content'];
    }
    
    /**
     * Call Anthropic Claude API
     * 
     * @param string $prompt The prompt to send
     * @return string|WP_Error API response or error
     */
    private function callAnthropic($prompt) {
        $url = 'https://api.anthropic.com/v1/messages';
        
        $body = [
            'model' => $this->model,
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Bilinmeyen hata';
            return new \WP_Error('anthropic_error', 'Anthropic API hatası: ' . $error_msg);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['content'][0]['text'])) {
            return new \WP_Error('anthropic_parse_error', 'Anthropic yanıtı ayrıştırılamadı.');
        }
        
        return $body['content'][0]['text'];
    }
    
    /**
     * Call Google Gemini API
     * 
     * @param string $prompt The prompt to send
     * @return string|WP_Error API response or error
     */
    private function callGemini($prompt) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->api_key;
        
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2000
            ]
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Bilinmeyen hata';
            return new \WP_Error('gemini_error', 'Gemini API hatası: ' . $error_msg);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return new \WP_Error('gemini_parse_error', 'Gemini yanıtı ayrıştırılamadı.');
        }
        
        return $body['candidates'][0]['content']['parts'][0]['text'];
    }
    
    /**
     * Parse AI response to extract ingredient data
     * 
     * @param string $response Raw AI response
     * @return array|WP_Error Parsed data or error
     */
    private function parseIngredientResponse($response) {
        // Remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_parse_error', 'AI yanıtı JSON olarak ayrıştırılamadı: ' . json_last_error_msg());
        }
        
        // Pairings validasyonu - AI'dan gelmezse boş array set et ve logla
        if (!isset($data['pairings']) || !is_array($data['pairings']) || empty($data['pairings'])) {
            error_log('KG Core: pairings alanı AI yanıtında bulunamadı veya boş. Raw response: ' . substr($response, 0, 500));
            // Boş array set et - update_single_field'deki !empty() kontrolü nedeniyle kaydedilmeyecek
            $data['pairings'] = [];
        }
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KG Core AI Response pairings: ' . print_r($data['pairings'] ?? 'NOT SET', true));
        }
        
        // Validate required fields
        $required = ['title', 'excerpt', 'content', 'start_age'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new \WP_Error('missing_field', "Gerekli alan eksik: {$field}");
            }
        }
        
        return $data;
    }
    
    /**
     * Generate recipe content using AI
     * 
     * @param array $recipe_data Recipe data containing title, content, ingredients, instructions
     * @return array|WP_Error Parsed recipe data or error
     */
    public function generateRecipeContent($recipe_data) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'AI API anahtarı ayarlanmamış.');
        }
        
        $prompt = $this->buildRecipePrompt($recipe_data);
        
        try {
            switch ($this->provider) {
                case 'openai':
                    $response = $this->callOpenAI($prompt);
                    break;
                case 'anthropic':
                    $response = $this->callAnthropic($prompt);
                    break;
                case 'gemini':
                    $response = $this->callGemini($prompt);
                    break;
                default:
                    return new \WP_Error('invalid_provider', 'Geçersiz AI sağlayıcı.');
            }
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $this->parseRecipeResponse($response);
            
        } catch (\Exception $e) {
            error_log('KG Core AI Recipe Error: ' . $e->getMessage());
            return new \WP_Error('ai_error', 'AI yanıt hatası: ' . $e->getMessage());
        }
    }
    
    /**
     * Build prompt for recipe content generation
     * 
     * @param array $recipe_data Recipe data
     * @return string Formatted prompt
     */
    private function buildRecipePrompt($recipe_data) {
        $title = $recipe_data['title'] ?? '';
        $content = $recipe_data['content'] ?? '';
        $ingredients = $recipe_data['ingredients'] ?? [];
        $instructions = $recipe_data['instructions'] ?? [];
        
        $ingredients_list = is_array($ingredients) ? implode("\n", array_map(function($ing) {
            if (is_array($ing)) {
                return "- " . ($ing['item'] ?? $ing['name'] ?? '');
            }
            return "- " . $ing;
        }, $ingredients)) : $ingredients;
        
        $instructions_list = is_array($instructions) ? implode("\n", array_map(function($inst, $idx) {
            if (is_array($inst)) {
                return ($idx + 1) . ". " . ($inst['step'] ?? $inst['text'] ?? '');
            }
            return ($idx + 1) . ". " . $inst;
        }, $instructions, array_keys($instructions))) : $instructions;
        
        $prompt = "Sen bebek ve çocuk beslenmesi konusunda uzman bir diyetisyen ve pediatristsin.\n";
        $prompt .= "Aşağıdaki tarif bilgilerine göre eksik alanları Türkçe olarak doldur.\n\n";
        $prompt .= "Tarif Adı: {$title}\n";
        $prompt .= "Mevcut Açıklama: {$content}\n";
        $prompt .= "Malzemeler:\n{$ingredients_list}\n\n";
        $prompt .= "Hazırlanış:\n{$instructions_list}\n\n";
        $prompt .= "Lütfen SADECE aşağıdaki JSON formatında yanıt ver (başka açıklama ekleme):\n\n";
        
        $json_template = [
            'prep_time' => 'Hazırlama süresi dakika cinsinden (örn: "15 dakika", "100 dakika") - saat değil',
            'cook_time' => 'Pişirme süresi dakika cinsinden (örn: "30 dakika")',
            'serving_size' => 'Porsiyon bilgisi (örn: "4 porsiyon", "2 kişilik")',
            'difficulty' => 'Sadece: "Kolay", "Orta", "Zor"',
            'freezable' => true,
            'storage_info' => 'Saklama bilgisi (örn: "Buzdolabında 2 gün saklanabilir")',
            'substitutes' => [
                ['original' => 'Süt', 'substitute' => 'Badem sütü', 'note' => 'Laktoz intoleransı için']
            ],
            'nutrition' => [
                'calories' => '250 kcal',
                'protein' => '8g',
                'carbs' => '30g',
                'fat' => '12g',
                'fiber' => '4g',
                'sugar' => '6g',
                'sodium' => '150mg',
                'vitamins' => 'A, C, D, E',
                'minerals' => 'Kalsiyum, Demir, Çinko'
            ],
            'special_notes' => 'Süt: Anne sütü veya formül ile...\nNot: İlk denemede...\nİpucu: Pürüzsüz kıvam için...',
            'age_groups' => ['Mevcut taxonomy sluglarından seçilecek - yeni eklenmeyecek'],
            'allergens' => ['Mevcut listeden veya yeni (baş harfi büyük Türkçe)'],
            'diet_types' => ['Mevcut taxonomy sluglarından seçilecek'],
            'meal_types' => ['Mevcut taxonomy sluglarından seçilecek'],
            'special_conditions' => ['Gerekirse yeni eklenebilir'],
            'seo' => [
                'focus_keyword' => 'Ana anahtar kelime',
                'title' => 'Max 60 karakter, çekici ve bilgilendirici',
                'description' => '150-160 karakter, call-to-action içermeli'
            ],
            'content' => '1 paragraf açıklama (sadece mevcut content boşsa doldurulacak)'
        ];
        
        $prompt .= json_encode($json_template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt .= "\n\n⚠️ ÖNEMLİ KURALLAR:\n";
        $prompt .= "1. Mevcut veriler DEĞİŞTİRİLMEYECEK, sadece BOŞ alanlar doldurulacak\n";
        $prompt .= "2. Hazırlama ve pişirme süreleri DAKİKA cinsinden (saat değil)\n";
        $prompt .= "3. Zorluk seviyesi sadece: Kolay, Orta, Zor\n";
        $prompt .= "4. Dondurulabilir mi: true veya false\n";
        $prompt .= "5. İkame malzemeler array formatında\n";
        $prompt .= "6. Beslenme değerleri porsiyon başına\n";
        $prompt .= "7. SEO title max 60 karakter\n";
        $prompt .= "8. SEO description 150-160 karakter\n";
        $prompt .= "9. Content sadece mevcut content boşsa doldurulacak (1 paragraf)\n\n";
        
        $prompt .= "TAXONOMY KURALLARI:\n\n";
        $prompt .= "Yaş Grupları (age-group) - Sadece mevcut sluglardan seçilecek:\n";
        $prompt .= "- hazirlk-evresi-0-6-ay (Hazırlık Evresi 0-6 Ay)\n";
        $prompt .= "- baslangic-tadim-6-8-ay (Başlangıç & Tadım 6-8 Ay)\n";
        $prompt .= "- kesif-puturlye-gecis-9-11-ay (Keşif & Pütürlüye Geçiş 9-11 Ay)\n";
        $prompt .= "- aile-sofrasina-gecis-12-24-ay (Aile Sofrasına Geçiş 12-24 Ay)\n";
        $prompt .= "- cocuk-gurme-2-yas (Çocuk Gurme 2+ Yaş)\n\n";
        
        $prompt .= "Alerjenler (allergen) - Mevcut liste (yoksa baş harfi büyük Türkçe formatında yeni eklenebilir):\n";
        $prompt .= "Ağaç yemişleri, Antep fıstığı, Badem, Baharatlar, Bal, Balık, Balık proteini, Buğday, Buğday (gluten), ";
        $prompt .= "Ceviz, Çilek, Çörekotu, Damla Sakızı Reçinesi, Deniz ürünleri, Domates, Et, Et proteinleri, Fındık, ";
        $prompt .= "Fındık proteini, Fındıkgiller, Fıstık, Gluten, Hardal, Kabuklu Deniz Ürünleri, Kakao, Kazein, Kestane, ";
        $prompt .= "Keten Tohumu, Kuruyemiş, Laktoz, Lektin, Mahlep Tohumları, Mısır, Pirinç, Polen, Sığır eti, Sirke, Soya, ";
        $prompt .= "Soya Lesitini, Susam, Süt, Süt Proteini, Tahin, Tavuk, Yer Fıstığı, Yumurta, Yumurta Akı\n\n";
        
        $prompt .= "Diet Type (diet-type) - Sadece mevcut sluglardan seçilecek:\n";
        $prompt .= "baby-led-weaning, dusuk-yag, etcil, glutensiz, laktozsuz, pesketaryen, rafine-sekersiz, ";
        $prompt .= "sekersiz, tuzsuz, vegan, vejetaryen, yuksek-protein\n\n";
        
        $prompt .= "Meal Type (meal-type) - Sadece mevcut sluglardan seçilecek:\n";
        $prompt .= "aksam-yemegi, ara-ogun-ikindi, ara-ogun-kusluk, atistirmalik, beslenme-cantasi, corba, ";
        $prompt .= "icecek, kahvalti, meze, ogle-yemegi, salata, sos, tatli\n\n";
        
        $prompt .= "Özel Durumlar (special-condition) - Gerekirse yeni eklenebilir:\n";
        $prompt .= "Alerjik Bebek, Bağışıklık Dostu, Diş Çıkarma Dönemi, Kabızlık Giderici\n\n";
        
        $prompt .= "DİĞER ÖNEMLİ NOTLAR:\n";
        $prompt .= "- Tüm içerik Türkçe olmalı\n";
        $prompt .= "- Bilimsel ve güvenilir bilgiler ver\n";
        $prompt .= "- Malzeme listesine göre alerjenleri belirle\n";
        $prompt .= "- İkame malzemelerde alerjik malzemeler için alternatif öner\n";
        $prompt .= "- Beslenme değerleri gerçekçi olmalı\n";
        
        return $prompt;
    }
    
    /**
     * Parse AI response to extract recipe data
     * 
     * @param string $response Raw AI response
     * @return array|WP_Error Parsed data or error
     */
    private function parseRecipeResponse($response) {
        // Remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_parse_error', 'AI yanıtı JSON olarak ayrıştırılamadı: ' . json_last_error_msg());
        }
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KG Core AI Recipe Response: ' . print_r($data, true));
        }
        
        return $data;
    }
}

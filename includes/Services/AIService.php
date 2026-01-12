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
            'category' => 'Meyveler|Sebzeler|Proteinler|Tahıllar|Süt Ürünleri',
            'start_age' => 6,
            'benefits' => 'Sağlık faydaları detaylı açıklama (HTML formatında)',
            'allergy_risk' => 'Düşük|Orta|Yüksek',
            'allergens' => ['varsa alerjen listesi'],
            'season' => 'İlkbahar|Yaz|Sonbahar|Kış|Tüm Yıl',
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
            
            'nutrition' => [
                'calories' => '100g için kalori değeri',
                'protein' => 'gram cinsinden protein',
                'carbs' => 'gram cinsinden karbonhidrat',
                'fat' => 'gram cinsinden yağ',
                'fiber' => 'gram cinsinden lif',
                'vitamins' => 'A, C, D, E, K vb.'
            ],
            
            'faq' => [
                ['question' => 'Bebeklere ne zaman verilebilir?', 'answer' => 'Detaylı cevap'],
                ['question' => 'Alerji riski var mı?', 'answer' => 'Detaylı cevap'],
                ['question' => 'Nasıl saklanmalı?', 'answer' => 'Detaylı cevap']
            ],
            
            // YENİ: SEO Meta (RankMath için)
            'seo' => [
                'title' => 'SEO başlığı - Bebeklere [Malzeme] Ne Zaman Verilir? | KidsGourmet',
                'description' => '150-160 karakter SEO açıklaması',
                'focus_keyword' => 'bebeklere [malzeme]',
                'keywords' => ['bebek beslenmesi', 'ek gıda', '[malzeme]', 'bebeklere [malzeme]']
            ],
            
            'image_search_query' => 'İngilizce görsel arama terimi (örn: "fresh carrots baby food")'
        ];
        
        $prompt .= json_encode($json_template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt .= "\n\nÖNEMLİ KURALLAR:\n";
        $prompt .= "1. 'pairings' alanını MUTLAKA 4-6 uyumlu malzeme ile doldur. Gerçekten bu malzeme ile iyi giden besinleri yaz.\n";
        $prompt .= "2. 'seo' alanındaki 'focus_keyword' malzeme adını içermeli.\n";
        $prompt .= "3. 'prep_by_age' alanında her yaş grubu için spesifik ve pratik tavsiyeler ver.\n";
        $prompt .= "4. Tüm içerik Türkçe olmalı, sadece emoji'ler evrensel.\n";
        $prompt .= "5. Bilimsel ve güvenilir bilgiler ver, abartılı ifadelerden kaçın.\n";
        
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
        
        // Validate required fields
        $required = ['title', 'excerpt', 'content', 'start_age'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new \WP_Error('missing_field', "Gerekli alan eksik: {$field}");
            }
        }
        
        return $data;
    }
}

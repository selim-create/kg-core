<?php
namespace KG_Core\Migration;

use KG_Core\Services\AIService;

/**
 * AIEnhancer - Use AI to fill missing recipe data
 */
class AIEnhancer {
    
    private $ai_service;
    
    public function __construct() {
        $this->ai_service = new AIService();
    }
    
    /**
     * Enhance recipe data with AI
     * 
     * @param array $recipeData Recipe data from parser
     * @return array Enhanced data with AI-generated fields
     */
    public function enhance($recipeData) {
        $result = [
            'prep_time' => '',
            'calories' => '',
            'protein' => '',
            'fiber' => '',
            'vitamins' => '',
            'substitutes' => [],
            'allergens' => [],
            'diet_types' => [],
            'meal_types' => [],
            'main_ingredient' => '',
            'cross_sell_url' => '',
            'cross_sell_title' => '',
            'seo_description' => '',
        ];
        
        // Build AI prompt
        $prompt = $this->buildPrompt($recipeData);
        
        // Call AI
        $aiResponse = $this->callAI($prompt);
        
        if (is_wp_error($aiResponse)) {
            error_log('KG Migration AI Error: ' . $aiResponse->get_error_message());
            return $result;
        }
        
        // Parse AI response
        $parsed = $this->parseAIResponse($aiResponse);
        
        if ($parsed) {
            $result = array_merge($result, $parsed);
        }
        
        return $result;
    }
    
    /**
     * Build AI prompt for recipe enhancement
     * 
     * @param array $recipeData Recipe data
     * @return string Prompt
     */
    private function buildPrompt($recipeData) {
        $title = isset($recipeData['title']) ? $recipeData['title'] : '';
        $ingredients = isset($recipeData['ingredients']) ? $recipeData['ingredients'] : [];
        $instructions = isset($recipeData['instructions']) ? $recipeData['instructions'] : [];
        
        $ingredientsList = '';
        if (!empty($ingredients)) {
            foreach ($ingredients as $ing) {
                $name = isset($ing['name']) ? $ing['name'] : $ing;
                $ingredientsList .= "- {$name}\n";
            }
        }
        
        $instructionsList = '';
        if (!empty($instructions)) {
            $instructionsList = implode("\n", array_map(function($inst, $idx) {
                $text = isset($inst['text']) ? $inst['text'] : $inst;
                return ($idx + 1) . ". {$text}";
            }, $instructions, array_keys($instructions)));
        }
        
        $prompt = "Sen bebek ve çocuk beslenmesi uzmanısın. Aşağıdaki tarif için eksik bilgileri doldur.\n\n";
        $prompt .= "Tarif: {$title}\n\n";
        $prompt .= "Malzemeler:\n{$ingredientsList}\n";
        $prompt .= "Hazırlanışı:\n{$instructionsList}\n\n";
        $prompt .= "Lütfen yanıtını SADECE aşağıdaki JSON formatında ver (başka açıklama ekleme):\n\n";
        
        $jsonTemplate = [
            'prep_time' => 'Hazırlama süresi (sadece sayı, dakika cinsinden, örn: 20)',
            'calories' => '100g için kalori değeri (sadece sayı)',
            'protein' => 'Protein miktarı gram cinsinden (sadece sayı)',
            'fiber' => 'Lif miktarı gram cinsinden (sadece sayı)',
            'vitamins' => 'Vitamin listesi virgülle ayrılmış (örn: A, C, D)',
            'substitutes' => [
                [
                    'original' => 'Orijinal malzeme',
                    'substitute' => 'İkame malzeme',
                    'note' => 'İkame notu',
                ]
            ],
            'allergens' => ['Alerjen isimleri listesi, örn: Süt, Yumurta'],
            'diet_types' => ['Diyet tipleri, örn: Vegan, Glutensiz'],
            'meal_types' => ['Öğün tipleri, örn: Kahvaltı, Ana Yemek, Atıştırmalık'],
            'main_ingredient' => 'Ana malzeme adı (tek kelime, örn: Havuç, Brokoli)',
            'cross_sell_title' => 'Artan malzemelerle kendinize harika bir [ana malzeme] yemeği yapabilirsiniz.',
            'seo_description' => '150-160 karakter SEO açıklaması (meta description)'
        ];
        
        $prompt .= json_encode($jsonTemplate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt .= "\n\nÖNEMLİ:\n";
        $prompt .= "1. Tüm sayısal değerler tam sayı olmalı\n";
        $prompt .= "2. allergens array'i Türkçe alerjen isimleri içermeli\n";
        $prompt .= "3. diet_types için: Vegan, Vejetaryen, Glutensiz, Laktoz İçermez gibi\n";
        $prompt .= "4. meal_types için: Kahvaltı, Öğle Yemeği, Akşam Yemeği, Atıştırmalık, Tatlı\n";
        $prompt .= "5. main_ingredient tek bir malzeme adı olmalı\n";
        $prompt .= "6. cross_sell_title formatı: 'Artan malzemelerle kendinize harika bir [ana malzeme] yemeği yapabilirsiniz.'\n";
        $prompt .= "7. seo_description maksimum 160 karakter\n";
        
        return $prompt;
    }
    
    /**
     * Call OpenAI API
     * 
     * @param string $prompt Prompt text
     * @return string|WP_Error AI response or error
     */
    private function callAI($prompt) {
        $api_key = get_option('kg_openai_api_key', '') ?: get_option('kg_ai_api_key', '');
        
        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'AI API anahtarı ayarlanmamış.');
        }
        
        $model = get_option('kg_ai_model', 'gpt-4o-mini');
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sen bebek ve çocuk beslenmesi uzmanısın. Yanıtlarını her zaman JSON formatında veriyorsun.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1500
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
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
     * Parse AI response JSON
     * 
     * @param string $response AI response
     * @return array|null Parsed data or null
     */
    private function parseAIResponse($response) {
        // Remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('KG Migration: AI response JSON parse error: ' . json_last_error_msg());
            return null;
        }
        
        // Generate cross_sell_url from main_ingredient
        if (!empty($data['main_ingredient'])) {
            $ingredient = urlencode($data['main_ingredient']);
            $data['cross_sell_url'] = "https://www.tariften.com/recipes?q={$ingredient}";
        }
        
        return $data;
    }
    
    /**
     * Map allergen names to taxonomy terms
     * 
     * @param array $allergenNames Array of allergen names
     * @return array Array of term IDs
     */
    public function mapAllergens($allergenNames) {
        $termIds = [];
        
        foreach ($allergenNames as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }
            
            // Try to find existing term
            $term = get_term_by('name', $name, 'allergen');
            
            if (!$term) {
                // Try slug version
                $slug = sanitize_title($name);
                $term = get_term_by('slug', $slug, 'allergen');
            }
            
            if ($term && !is_wp_error($term)) {
                $termIds[] = $term->term_id;
            }
        }
        
        return $termIds;
    }
    
    /**
     * Map diet type names to taxonomy terms
     * 
     * @param array $dietTypeNames Array of diet type names
     * @return array Array of term IDs
     */
    public function mapDietTypes($dietTypeNames) {
        $termIds = [];
        
        foreach ($dietTypeNames as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }
            
            $term = get_term_by('name', $name, 'diet-type');
            
            if (!$term) {
                $slug = sanitize_title($name);
                $term = get_term_by('slug', $slug, 'diet-type');
            }
            
            if ($term && !is_wp_error($term)) {
                $termIds[] = $term->term_id;
            }
        }
        
        return $termIds;
    }
    
    /**
     * Map meal type names to taxonomy terms
     * 
     * @param array $mealTypeNames Array of meal type names
     * @return array Array of term IDs
     */
    public function mapMealTypes($mealTypeNames) {
        $termIds = [];
        
        foreach ($mealTypeNames as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }
            
            $term = get_term_by('name', $name, 'meal-type');
            
            if (!$term) {
                $slug = sanitize_title($name);
                $term = get_term_by('slug', $slug, 'meal-type');
            }
            
            if ($term && !is_wp_error($term)) {
                $termIds[] = $term->term_id;
            }
        }
        
        return $termIds;
    }
}

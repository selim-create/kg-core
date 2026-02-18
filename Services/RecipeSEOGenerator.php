<?php
namespace KG_Core\Services;

/**
 * RecipeSEOGenerator - Generate SEO metadata for recipes using AI
 * Handles RankMath and Yoast SEO fields
 */
class RecipeSEOGenerator {
    /**
     * Maximum tokens for AI response
     */
    const MAX_TOKENS = 800;
    
    private $model;
    
    public function __construct() {
        $this->model = get_option('kg_ai_model', 'gpt-4o-mini');
    }
    
    /**
     * Get API key (retrieved only when needed)
     * 
     * @return string API key
     */
    private function getApiKey() {
        return get_option('kg_openai_api_key', '') ?: get_option('kg_ai_api_key', '');
    }
    
    /**
     * Generate SEO metadata for a recipe
     * 
     * @param int $recipeId Recipe post ID
     * @param array $recipeData Optional recipe data (ingredients, description, etc.)
     * @return array|WP_Error SEO data or error
     */
    public function generateSEO($recipeId, $recipeData = []) {
        $apiKey = $this->getApiKey();
        
        if (empty($apiKey)) {
            return new \WP_Error('no_api_key', __('AI API anahtarı ayarlanmamış.', 'kg-core'));
        }
        
        // Get recipe information
        $title = get_the_title($recipeId);
        $content = get_post_field('post_content', $recipeId);
        $excerpt = get_post_field('post_excerpt', $recipeId);
        
        // Get age group
        $ageGroup = '';
        $terms = get_the_terms($recipeId, 'age-group');
        if ($terms && !is_wp_error($terms)) {
            $term = array_shift($terms);
            $ageGroup = $term->name;
        }
        
        // Get main ingredient if available
        $mainIngredient = '';
        $ingredients = get_post_meta($recipeId, '_kg_ingredients', true);
        if (!empty($ingredients) && is_array($ingredients)) {
            $mainIngredient = $ingredients[0]['name'] ?? '';
        }
        
        // Build prompt
        $prompt = $this->buildPrompt($title, $content, $excerpt, $ageGroup, $mainIngredient, $recipeData);
        
        try {
            $response = $this->callOpenAI($prompt, $apiKey);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return $this->parseResponse($response);
            
        } catch (\Exception $e) {
            error_log('KG Core SEO Generator Error: ' . $e->getMessage());
            return new \WP_Error('ai_error', 'AI yanıt hatası: ' . $e->getMessage());
        }
    }
    
    /**
     * Save SEO metadata to recipe post
     * 
     * @param int $recipeId Recipe post ID
     * @param array $seoData SEO data
     * @return bool Success
     */
    public function saveSEO($recipeId, $seoData) {
        if (empty($seoData)) {
            return false;
        }
        
        // RankMath SEO meta
        if (!empty($seoData['focus_keyword'])) {
            update_post_meta($recipeId, 'rank_math_focus_keyword', sanitize_text_field($seoData['focus_keyword']));
        }
        
        if (!empty($seoData['meta_title'])) {
            update_post_meta($recipeId, 'rank_math_title', sanitize_text_field($seoData['meta_title']));
        }
        
        if (!empty($seoData['meta_description'])) {
            update_post_meta($recipeId, 'rank_math_description', sanitize_text_field($seoData['meta_description']));
        }
        
        // Yoast SEO meta (as fallback)
        if (!empty($seoData['meta_title'])) {
            update_post_meta($recipeId, '_yoast_wpseo_title', sanitize_text_field($seoData['meta_title']));
        }
        
        if (!empty($seoData['meta_description'])) {
            update_post_meta($recipeId, '_yoast_wpseo_metadesc', sanitize_text_field($seoData['meta_description']));
        }
        
        if (!empty($seoData['focus_keyword'])) {
            update_post_meta($recipeId, '_yoast_wpseo_focuskw', sanitize_text_field($seoData['focus_keyword']));
        }
        
        return true;
    }
    
    /**
     * Build SEO generation prompt
     * 
     * @param string $title Recipe title
     * @param string $content Recipe content
     * @param string $excerpt Recipe excerpt
     * @param string $ageGroup Age group
     * @param string $mainIngredient Main ingredient
     * @param array $recipeData Additional recipe data
     * @return string Prompt
     */
    private function buildPrompt($title, $content, $excerpt, $ageGroup, $mainIngredient, $recipeData) {
        $prompt = "Sen bebek beslenmesi ve SEO konusunda uzman bir dijital pazarlama uzmanısın.\n\n";
        $prompt .= "Aşağıdaki tarif için RankMath SEO optimizasyonu gerekli:\n\n";
        $prompt .= "Tarif Başlığı: {$title}\n";
        
        if ($mainIngredient) {
            $prompt .= "Ana Malzeme: {$mainIngredient}\n";
        }
        
        if ($ageGroup) {
            $prompt .= "Yaş Grubu: {$ageGroup}\n";
        }
        
        if ($excerpt) {
            $prompt .= "Kısa Açıklama: " . substr(strip_tags($excerpt), 0, 200) . "\n";
        }
        
        if ($content) {
            $prompt .= "İçerik Özeti: " . substr(strip_tags($content), 0, 300) . "\n";
        }
        
        $prompt .= "\nLütfen yanıtını SADECE aşağıdaki JSON formatında ver (başka açıklama ekleme):\n\n";
        
        $json_template = [
            'focus_keyword' => 'Ana anahtar kelime (tarif adını içermeli)',
            'meta_title' => 'SEO başlığı (maksimum 60 karakter)',
            'meta_description' => 'SEO açıklaması (150-160 karakter, çağrı içermeli)'
        ];
        
        $prompt .= json_encode($json_template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $prompt .= "\n\nÖRNEK ÇIKTI:\n";
        $prompt .= "{\n";
        $prompt .= "  \"focus_keyword\": \"bebekler için brokoli çorbası\",\n";
        $prompt .= "  \"meta_title\": \"Brokoli Çorbası Tarifi | Besleyici ve Sağlıklı\",\n";
        $prompt .= "  \"meta_description\": \"Lif kaynağı yüksek lezzetli Brokoli Çorbası. Çocuklarınız ve bebekleriniz için ideal tarif. Keşfet!\"\n";
        $prompt .= "}\n";
        
        $prompt .= "\nÖNEMLİ KURALLAR:\n";
        $prompt .= "1. Focus keyword doğal ve aranabilir olmalı, tarif adını içermeli.\n";
        $prompt .= "2. Meta title çekici, bilgilendirici ve 60 karakteri geçmemeli.\n";
        $prompt .= "3. Meta description tarifi özetlemeli, çağrı içermeli, 150-160 karakter arası olmalı.\n";
        $prompt .= "4. Tüm alanlar bebek/çocuk beslenmesi odaklı olmalı.\n";
        $prompt .= "5. Türkçe karakter kullanımına dikkat et.\n";
        
        return $prompt;
    }
    
    /**
     * Call OpenAI API
     * 
     * @param string $prompt The prompt to send
     * @param string $apiKey API key
     * @return string|WP_Error API response or error
     */
    private function callOpenAI($prompt, $apiKey) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sen SEO uzmanısın. Yanıtlarını her zaman JSON formatında veriyorsun.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => self::MAX_TOKENS
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
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
     * Parse AI response to extract SEO data
     * 
     * @param string $response Raw AI response
     * @return array|WP_Error Parsed data or error
     */
    private function parseResponse($response) {
        // Remove markdown code blocks if present
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*/', '', $response);
        $response = trim($response);
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_parse_error', 'AI yanıtı JSON olarak ayrıştırılamadı: ' . json_last_error_msg());
        }
        
        // Validate required fields
        $required = ['focus_keyword', 'meta_title', 'meta_description'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return new \WP_Error('missing_field', "Gerekli SEO alanı eksik: {$field}");
            }
        }
        
        return $data;
    }
}

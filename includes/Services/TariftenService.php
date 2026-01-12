<?php
namespace KG_Core\Services;

class TariftenService {
    
    private $api_base = 'https://api.tariften.com/wp-json/tariften/v1';
    
    /**
     * Malzemeye göre tarif ara
     */
    public function getRecipesByIngredient($ingredient, $limit = 3) {
        $url = $this->api_base . '/recipes/by-ingredient?' . http_build_query([
            'ingredient' => $ingredient,
            'limit' => $limit
        ]);
        
        $response = wp_remote_get($url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        // Check HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return ['success' => false, 'message' => 'API hatası: HTTP ' . $status_code];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'JSON ayrıştırma hatası'];
        }
        
        if (empty($body) || !isset($body['recipes'])) {
            return ['success' => false, 'message' => 'Öneri bulunamadı'];
        }
        
        return ['success' => true, 'recipes' => $body['recipes']];
    }
    
    /**
     * Slug ile tarif detayı al
     */
    public function getRecipeBySlug($slug) {
        $url = $this->api_base . '/recipes/search?slug=' . urlencode($slug);
        
        $response = wp_remote_get($url, ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        // Check HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        if (empty($body) || empty($body['data'])) {
            return null;
        }
        
        return $body['data'][0] ?? null;
    }
}

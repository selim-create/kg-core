<?php
namespace KG_Core\Services;

class ImageService {
    private $unsplash_key;
    private $pexels_key;
    private $preferred_api;
    private $openai_api_key;
    
    public function __construct() {
        $this->unsplash_key = get_option('kg_unsplash_api_key', '');
        $this->pexels_key = get_option('kg_pexels_api_key', '');
        $this->preferred_api = get_option('kg_preferred_image_api', 'unsplash');
        $this->openai_api_key = get_option('kg_ai_api_key', '');
    }
    
    /**
     * Generate image using DALL-E 3
     * 
     * @param string $ingredient_name Name of ingredient in Turkish
     * @return array|null Image data with URL and source or null on failure
     */
    public function generateImage($ingredient_name) {
        if (empty($this->openai_api_key)) {
            error_log('KG Core: OpenAI API key not configured for DALL-E');
            return null;
        }
        
        $prompt = $this->buildImagePrompt($ingredient_name);
        
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openai_api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'standard',
                'style' => 'natural'
            ]),
            'timeout' => 90
        ]);
        
        if (is_wp_error($response)) {
            error_log('KG Core DALL-E Error: ' . $response->get_error_message());
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
            error_log('KG Core DALL-E HTTP Error: ' . $error_msg);
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['data'][0]['url'])) {
            error_log('KG Core DALL-E Error: No image URL in response');
            return null;
        }
        
        return [
            'url' => $body['data'][0]['url'],
            'source' => 'dall-e-3',
            'credit' => 'AI Generated (DALL-E 3)',
            'credit_url' => ''
        ];
    }
    
    /**
     * Build DALL-E 3 prompt for ingredient image
     * 
     * @param string $ingredient_name Ingredient name in Turkish
     * @return string Formatted prompt in English
     */
    private function buildImagePrompt($ingredient_name) {
        $english_name = $this->translateToEnglish($ingredient_name);
        
        return "Professional food photography of fresh {$english_name}. " .
               "Clean pure white background, no shadows on background. " .
               "Soft diffused studio lighting from upper left at 45 degrees. " .
               "Single ingredient only, no props, no plates, no decorations. " .
               "High-end cookbook style, appetizing and vibrant colors. " .
               "Photorealistic, sharp focus, centered composition. " .
               "Suitable for baby food nutrition guide website.";
    }
    
    /**
     * Translate Turkish ingredient name to English
     * 
     * @param string $name Turkish ingredient name
     * @return string English ingredient name
     */
    private function translateToEnglish($name) {
        $translations = [
            'elma' => 'red apple', 
            'muz' => 'ripe banana', 
            'armut' => 'pear',
            'havuç' => 'fresh carrots with green tops', 
            'patates' => 'potatoes',
            'avokado' => 'avocado halved', 
            'brokoli' => 'broccoli florets',
            'yumurta' => 'brown eggs', 
            'tavuk göğsü' => 'raw chicken breast',
            'somon' => 'fresh salmon fillet', 
            'yoğurt' => 'plain yogurt in bowl',
            'pirinç' => 'white rice grains', 
            'yulaf' => 'oat flakes',
            'şeftali' => 'fresh peach',
            'kayısı' => 'fresh apricots',
            'erik' => 'fresh plums',
            'kiraz' => 'fresh cherries',
            'çilek' => 'fresh strawberries',
            'üzüm' => 'fresh grapes',
            'karpuz' => 'fresh watermelon slice',
            'kavun' => 'fresh cantaloupe',
            'portakal' => 'fresh oranges',
            'mandalina' => 'fresh mandarins',
            'kivi' => 'fresh kiwi fruit',
            'hurma' => 'fresh persimmon',
            'incir' => 'fresh figs',
            'nar' => 'fresh pomegranate',
            'tatlı patates' => 'sweet potatoes',
            'kabak' => 'zucchini squash',
            'balkabağı' => 'pumpkin',
            'karnabahar' => 'cauliflower',
            'ıspanak' => 'fresh spinach leaves',
            'pırasa' => 'fresh leeks',
            'bezelye' => 'fresh green peas',
            'fasulye' => 'green beans',
            'mercimek' => 'red lentils',
            'domates' => 'fresh tomatoes',
            'salatalık' => 'fresh cucumber',
            'biber' => 'bell peppers',
            'patlıcan' => 'eggplant'
        ];
        
        $lower = mb_strtolower($name, 'UTF-8');
        return $translations[$lower] ?? $name;
    }
    
    /**
     * Fetch image for given search query
     * 
     * @param string $query Search query (in English)
     * @return array|null Image data or null if not found
     */
    public function fetchImage($query) {
        if (empty($query)) {
            return null;
        }
        
        // Try preferred API first
        if ($this->preferred_api === 'dall-e' && !empty($this->openai_api_key)) {
            $result = $this->generateImage($query);
            if ($result !== null) {
                return $result;
            }
        } elseif ($this->preferred_api === 'unsplash' && !empty($this->unsplash_key)) {
            $result = $this->fetchFromUnsplash($query);
            if ($result !== null) {
                return $result;
            }
        } elseif ($this->preferred_api === 'pexels' && !empty($this->pexels_key)) {
            $result = $this->fetchFromPexels($query);
            if ($result !== null) {
                return $result;
            }
        }
        
        // Fallback to other APIs
        if ($this->preferred_api === 'dall-e') {
            if (!empty($this->unsplash_key)) {
                $result = $this->fetchFromUnsplash($query);
                if ($result !== null) return $result;
            }
            if (!empty($this->pexels_key)) {
                return $this->fetchFromPexels($query);
            }
        } elseif ($this->preferred_api === 'unsplash' && !empty($this->pexels_key)) {
            return $this->fetchFromPexels($query);
        } elseif ($this->preferred_api === 'pexels' && !empty($this->unsplash_key)) {
            return $this->fetchFromUnsplash($query);
        }
        
        return null;
    }
    
    /**
     * Fetch image from Unsplash
     * 
     * @param string $query Search query
     * @return array|null Image data or null
     */
    private function fetchFromUnsplash($query) {
        if (empty($this->unsplash_key)) {
            return null;
        }
        
        $url = 'https://api.unsplash.com/search/photos?' . http_build_query([
            'query' => $query,
            'per_page' => 1,
            'orientation' => 'landscape',
            'content_filter' => 'high'
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Client-ID ' . $this->unsplash_key
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('KG Core Unsplash Error: ' . $response->get_error_message());
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('KG Core Unsplash HTTP Error: ' . $status_code);
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['results'])) {
            return null;
        }
        
        $photo = $body['results'][0];
        
        return [
            'url' => $photo['urls']['regular'],
            'credit' => $photo['user']['name'],
            'credit_url' => $photo['user']['links']['html'],
            'source' => 'unsplash'
        ];
    }
    
    /**
     * Fetch image from Pexels
     * 
     * @param string $query Search query
     * @return array|null Image data or null
     */
    private function fetchFromPexels($query) {
        if (empty($this->pexels_key)) {
            return null;
        }
        
        $url = 'https://api.pexels.com/v1/search?' . http_build_query([
            'query' => $query,
            'per_page' => 1,
            'orientation' => 'landscape'
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => $this->pexels_key
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('KG Core Pexels Error: ' . $response->get_error_message());
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('KG Core Pexels HTTP Error: ' . $status_code);
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['photos'])) {
            return null;
        }
        
        $photo = $body['photos'][0];
        
        return [
            'url' => $photo['src']['large'],
            'credit' => $photo['photographer'],
            'credit_url' => $photo['photographer_url'],
            'source' => 'pexels'
        ];
    }
    
    /**
     * Download image to WordPress media library
     * 
     * @param string $url Image URL
     * @param string $filename Desired filename
     * @return int|WP_Error Attachment ID or error
     */
    public function downloadToMediaLibrary($url, $filename) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download file to temp location
        $temp_file = download_url($url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        // Sanitize filename and ensure proper extension
        $filename = sanitize_file_name($filename);
        
        // If no extension, add .png for DALL-E or .jpg for others
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            $filename .= '.png'; // Default to PNG for AI-generated images
        }
        
        // Prepare file array
        $file_array = [
            'name' => $filename,
            'tmp_name' => $temp_file
        ];
        
        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, 0);
        
        // Remove temp file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Mark AI-generated images
        update_post_meta($attachment_id, '_kg_ai_generated', true);
        
        return $attachment_id;
    }
}

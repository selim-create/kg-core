<?php
namespace KG_Core\Services;

class ImageService {
    private $unsplash_key;
    private $pexels_key;
    private $preferred_api;
    
    public function __construct() {
        $this->unsplash_key = get_option('kg_unsplash_api_key', '');
        $this->pexels_key = get_option('kg_pexels_api_key', '');
        $this->preferred_api = get_option('kg_preferred_image_api', 'unsplash');
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
        if ($this->preferred_api === 'unsplash' && !empty($this->unsplash_key)) {
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
        
        // Fallback to other API
        if ($this->preferred_api === 'unsplash' && !empty($this->pexels_key)) {
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
        
        // Sanitize filename
        $filename = sanitize_file_name($filename);
        
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
        
        return $attachment_id;
    }
}

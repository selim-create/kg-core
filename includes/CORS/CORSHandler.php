<?php
namespace KG_Core\CORS;

class CORSHandler {
    
    public function __construct() {
        // REST API için CORS headers
        add_action('rest_api_init', [$this, 'add_cors_support'], 15);
        
        // Preflight OPTIONS istekleri için
        add_action('init', [$this, 'handle_preflight']);
        
        // WordPress standard endpoint'leri için JWT auth desteği
        add_filter('rest_authentication_errors', [$this, 'enable_jwt_for_wp_endpoints'], 100);
    }
    
    /**
     * İzin verilen origin'ler
     */
    private function get_allowed_origins() {
        $default_origins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'https://kidsgourmet.com.tr',
            'https://www.kidsgourmet.com.tr',
        ];
        
        // Allow filtering for environment-specific configuration
        return apply_filters('kg_core_allowed_origins', $default_origins);
    }
    
    /**
     * CORS headers ekle
     */
    public function add_cors_support() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        
        add_filter('rest_pre_serve_request', function($value) {
            $origin = $this->get_origin();
            
            if ($origin && in_array($origin, $this->get_allowed_origins(), true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
            }
            
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            
            return $value;
        });
    }
    
    /**
     * OPTIONS preflight isteklerini yönet
     */
    public function handle_preflight() {
        // Validate REQUEST_METHOD exists and is OPTIONS
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
            return;
        }
        
        $origin = $this->get_origin();
        
        if ($origin && in_array($origin, $this->get_allowed_origins(), true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        header('Content-Type: text/plain');
        header('Content-Length: 0');
        status_header(200);
        exit();
    }
    
    /**
     * Request origin'i al
     */
    private function get_origin() {
        if (function_exists('get_http_origin')) {
            return esc_url_raw(get_http_origin());
        }
        
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // Sanitize to prevent header injection attacks
            return esc_url_raw($_SERVER['HTTP_ORIGIN']);
        }
        
        return null;
    }
    
    /**
     * WordPress media ve comments endpoint'leri için JWT auth desteği
     */
    public function enable_jwt_for_wp_endpoints($result) {
        // Eğer zaten authenticate olduysa devam et
        if (true === $result || is_wp_error($result)) {
            return $result;
        }
        
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        
        // Media ve comments endpoint'leri için JWT auth aktifleştir
        $jwt_endpoints = ['/wp/v2/media', '/wp/v2/comments'];
        $needs_jwt = false;
        
        foreach ($jwt_endpoints as $endpoint) {
            if (strpos($request_uri, $endpoint) !== false) {
                $needs_jwt = true;
                break;
            }
        }
        
        if (!$needs_jwt) {
            return $result;
        }
        
        // JWT token kontrol et
        $token = \KG_Core\Auth\JWTHandler::get_token_from_request();
        
        if (!$token) {
            return $result;
        }
        
        $payload = \KG_Core\Auth\JWTHandler::validate_token($token);
        
        if ($payload && isset($payload['user_id'])) {
            wp_set_current_user($payload['user_id']);
            return true;
        }
        
        return $result;
    }
}

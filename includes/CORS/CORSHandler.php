<?php
namespace KG_Core\CORS;

class CORSHandler {
    
    public function __construct() {
        // REST API için CORS headers - daha erken priority ile
        add_action('rest_api_init', [$this, 'add_cors_support'], 5);
        
        // Preflight OPTIONS istekleri için - çok erken hook
        add_action('init', [$this, 'handle_preflight'], 1);
        
        // Alternatif: send_headers hook'u ile de dene
        add_action('send_headers', [$this, 'send_cors_headers'], 1);
        
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
            'http://localhost:3002',
            'https://kidsgourmet.com.tr',
            'https://www.kidsgourmet.com.tr',
            'https://api.kidsgourmet.com.tr',
            'https://kidsgourmet-web.vercel.app',
        ];
        
        // Allow filtering for environment-specific configuration
        return apply_filters('kg_core_allowed_origins', $default_origins);
    }
    
    /**
     * Origin'in izin verilip verilmediğini kontrol et
     */
    private function is_allowed_origin($origin) {
        if (empty($origin)) {
            return false;
        }
        
        $allowed = $this->get_allowed_origins();
        
        // Tam eşleşme kontrolü
        if (in_array($origin, $allowed, true)) {
            return true;
        }
        
        // Trailing slash ile kontrol
        $origin_clean = rtrim($origin, '/');
        foreach ($allowed as $allowed_origin) {
            if (rtrim($allowed_origin, '/') === $origin_clean) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * CORS headers ekle
     */
    public function add_cors_support() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        
        add_filter('rest_pre_serve_request', function($value) {
            $origin = $this->get_origin();
            
            if ($origin && $this->is_allowed_origin($origin)) {
                header('Access-Control-Allow-Origin: ' . $origin);
            }
            
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, Content-Disposition, X-Requested-With, X-WP-Nonce, Cache-Control, Pragma');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            header('Vary: Origin');
            
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
        
        if ($origin && $this->is_allowed_origin($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Content-Disposition, X-Requested-With, X-WP-Nonce, Cache-Control, Pragma');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        header('Vary: Origin');
        header('Content-Type: text/plain');
        header('Content-Length: 0');
        status_header(200);
        exit();
    }
    
    /**
     * Request origin'i al
     */
    private function get_origin() {
        // Önce WordPress fonksiyonunu dene
        if (function_exists('get_http_origin')) {
            $origin = get_http_origin();
            if (!empty($origin)) {
                return esc_url_raw($origin);
            }
        }
        
        // HTTP_ORIGIN header'ını kontrol et
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            return esc_url_raw($_SERVER['HTTP_ORIGIN']);
        }
        
        // Referer'dan origin çıkarmayı dene (fallback)
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $referer_parts = wp_parse_url($_SERVER['HTTP_REFERER']);
            if ($referer_parts && isset($referer_parts['scheme']) && isset($referer_parts['host'])) {
                $origin = $referer_parts['scheme'] . '://' . $referer_parts['host'];
                if (isset($referer_parts['port'])) {
                    $origin .= ':' . $referer_parts['port'];
                }
                return esc_url_raw($origin);
            }
        }
        
        return null;
    }
    
    /**
     * REST API istekleri için CORS header'larını gönder
     */
    public function send_cors_headers() {
        // Sadece REST API istekleri için
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (strpos($request_uri, '/wp-json/') === false) {
            return;
        }
        
        $origin = $this->get_origin();
        
        if ($origin && $this->is_allowed_origin($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, Content-Disposition, X-Requested-With, X-WP-Nonce, Cache-Control, Pragma');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            header('Vary: Origin');
        }
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

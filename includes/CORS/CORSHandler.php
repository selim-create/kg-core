<?php
namespace KG_Core\CORS;

class CORSHandler {
    
    public function __construct() {
        // REST API için CORS headers
        add_action('rest_api_init', [$this, 'add_cors_support'], 15);
        
        // Preflight OPTIONS istekleri için
        add_action('init', [$this, 'handle_preflight']);
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
}

<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;

class MediaController {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        // Custom avatar upload endpoint
        register_rest_route('kg/v1', '/user/avatar', [
            'methods'  => 'POST',
            'callback' => [$this, 'upload_avatar'],
            'permission_callback' => [$this, 'check_authentication'],
        ]);
    }
    
    public function check_authentication($request) {
        $token = JWTHandler::get_token_from_request();
        
        if (!$token) {
            return false;
        }

        $payload = JWTHandler::validate_token($token);
        
        if (!$payload) {
            return false;
        }

        $request->set_param('authenticated_user_id', $payload['user_id']);
        return true;
    }
    
    public function upload_avatar($request) {
        $user_id = $request->get_param('authenticated_user_id');
        
        if (empty($_FILES['file'])) {
            return new \WP_Error('no_file', 'Dosya bulunamadı', ['status' => 400]);
        }
        
        $file = $_FILES['file'];
        
        // Dosya tipi kontrolü
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            return new \WP_Error('invalid_type', 'Sadece resim dosyaları yüklenebilir', ['status' => 400]);
        }
        
        // Dosya boyutu kontrolü (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return new \WP_Error('file_too_large', 'Dosya boyutu 2MB\'dan küçük olmalı', ['status' => 400]);
        }
        
        // WordPress ile yükle
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('file', 0);
        
        if (is_wp_error($attachment_id)) {
            return new \WP_Error('upload_failed', $attachment_id->get_error_message(), ['status' => 500]);
        }
        
        // Kullanıcı meta güncelle
        update_user_meta($user_id, 'kg_avatar_id', $attachment_id);
        
        $url = wp_get_attachment_url($attachment_id);
        
        return new \WP_REST_Response([
            'id' => $attachment_id,
            'url' => $url,
            'source_url' => $url,
        ], 200);
    }
}

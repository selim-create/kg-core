<?php
namespace KG_Core\Auth;

/**
 * Google OAuth Handler
 * Google ile giriş işlemlerini yönetir
 */
class GoogleAuth {
    
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    public function __construct() {
        $this->client_id = get_option('kg_google_client_id', '');
        $this->client_secret = get_option('kg_google_client_secret', '');
        $this->redirect_uri = home_url('/wp-json/kg/v1/auth/google/callback');
    }
    
    /**
     * Google OAuth aktif mi kontrol et
     */
    public static function is_enabled() {
        return (bool) get_option('kg_google_auth_enabled', false);
    }
    
    /**
     * Google ID Token'ı doğrula
     * Frontend'den gelen token'ı Google API ile doğrular
     */
    public function verify_id_token($id_token) {
        $client_id = $this->client_id;
        
        if (empty($client_id)) {
            return new \WP_Error('config_error', 'Google Client ID yapılandırılmamış.');
        }
        
        // Google'ın token doğrulama endpoint'i
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            return new \WP_Error('google_error', 'Google API\'ye bağlanılamadı.');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new \WP_Error('invalid_token', 'Geçersiz Google token.');
        }
        
        // Client ID doğrulaması
        if ($body['aud'] !== $client_id) {
            return new \WP_Error('invalid_audience', 'Token bu uygulama için geçerli değil.');
        }
        
        // Token süresi dolmuş mu?
        if (isset($body['exp']) && $body['exp'] < time()) {
            return new \WP_Error('expired_token', 'Token süresi dolmuş.');
        }
        
        return [
            'email' => $body['email'],
            'email_verified' => $body['email_verified'] ?? false,
            'name' => $body['name'] ?? '',
            'picture' => $body['picture'] ?? '',
            'google_id' => $body['sub'],
        ];
    }
    
    /**
     * Google kullanıcısını WordPress kullanıcısına eşle veya oluştur
     */
    public function get_or_create_user($google_data) {
        $email = $google_data['email'];
        $google_id = $google_data['google_id'];
        
        // Önce Google ID ile ara (user meta'da)
        $users = get_users([
            'meta_key' => 'google_id',
            'meta_value' => $google_id,
            'number' => 1,
        ]);
        
        if (!empty($users)) {
            return $users[0];
        }
        
        // Email ile ara
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Mevcut kullanıcıya Google ID ekle
            update_user_meta($user->ID, 'google_id', $google_id);
            
            // Avatar'ı güncelle (varsa)
            if (!empty($google_data['picture'])) {
                update_user_meta($user->ID, 'google_avatar', $google_data['picture']);
            }
            
            return $user;
        }
        
        // Yeni kullanıcı oluştur
        $username = $this->generate_unique_username($email, $google_data['name']);
        $password = wp_generate_password(24, true, true);
        
        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $google_data['name'] ?: $username,
            'role' => 'subscriber',
        ]);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Meta bilgilerini kaydet
        update_user_meta($user_id, 'google_id', $google_id);
        update_user_meta($user_id, 'google_avatar', $google_data['picture'] ?? '');
        update_user_meta($user_id, 'registered_via', 'google');
        
        return get_user_by('id', $user_id);
    }
    
    /**
     * Benzersiz kullanıcı adı oluştur
     */
    private function generate_unique_username($email, $name = '') {
        // Önce isimden dene
        if (!empty($name)) {
            $base = sanitize_user(strtolower(str_replace(' ', '', $name)), true);
            if (!empty($base) && !username_exists($base)) {
                return $base;
            }
        }
        
        // Email'den kullanıcı adı oluştur
        $base = strstr($email, '@', true);
        $base = sanitize_user($base, true);
        
        if (!username_exists($base)) {
            return $base;
        }
        
        // Numara ekleyerek benzersiz yap
        $counter = 1;
        while (username_exists($base . $counter)) {
            $counter++;
        }
        
        return $base . $counter;
    }
}

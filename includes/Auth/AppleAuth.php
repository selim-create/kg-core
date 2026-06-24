<?php
namespace KG_Core\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Apple Sign-In Handler
 * Apple ile giriş işlemlerini yönetir
 */
class AppleAuth {

    /** Apple private relay email domain */
    const APPLE_PRIVATE_RELAY_DOMAIN = '@privaterelay.appleid.com';

    private $bundle_id;
    private $service_id;
    private $team_id;
    private $key_id;

    public function __construct() {
        $this->bundle_id  = get_option( 'kg_apple_bundle_id', '' );
        $this->service_id = get_option( 'kg_apple_service_id', '' );
        $this->team_id    = get_option( 'kg_apple_team_id', '' );
        $this->key_id     = get_option( 'kg_apple_key_id', '' );
    }

    /**
     * Apple Sign-In aktif mi kontrol et
     */
    public static function is_enabled() {
        return (bool) get_option( 'kg_apple_auth_enabled', false );
    }

    /**
     * Apple Identity Token'ı doğrula
     * Frontend'den gelen ES256-imzalı JWT'yi Apple'ın public key'leri ile doğrular
     *
     * @param string $identity_token Apple'dan gelen identity_token
     * @return array|\WP_Error Doğrulanmış payload verisi veya WP_Error
     */
    public function verify_identity_token( $identity_token ) {
        // Bundle ID veya Service ID yapılandırılmış mı kontrol et
        if ( empty( $this->bundle_id ) && empty( $this->service_id ) ) {
            return new \WP_Error(
                'config_error',
                'Apple Bundle ID veya Service ID yapılandırılmamış.'
            );
        }

        // Apple JWKS'yi al (24 saat önbelleğe al)
        $jwks = get_transient( 'kg_apple_jwks' );

        if ( false === $jwks ) {
            $response = wp_remote_get( 'https://appleid.apple.com/auth/keys', [
                'timeout' => 10,
            ] );

            if ( is_wp_error( $response ) ) {
                return new \WP_Error(
                    'apple_jwks_error',
                    'Apple public key\'leri alınamadı: ' . $response->get_error_message()
                );
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== (int) $status_code ) {
                return new \WP_Error(
                    'apple_jwks_error',
                    'Apple public key\'leri alınamadı (HTTP ' . $status_code . ').'
                );
            }

            $jwks = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( empty( $jwks['keys'] ) ) {
                return new \WP_Error(
                    'apple_jwks_error',
                    'Apple JWKS geçersiz format.'
                );
            }

            set_transient( 'kg_apple_jwks', $jwks, DAY_IN_SECONDS );
        }

        // JWT header'ını decode ederek kid (key ID) değerini al
        $token_parts = explode( '.', $identity_token );
        if ( count( $token_parts ) !== 3 ) {
            return new \WP_Error(
                'invalid_token',
                'Geçersiz Apple identity token formatı.'
            );
        }

        $header_raw = $this->base64url_decode( $token_parts[0] );
        $header     = $header_raw ? json_decode( $header_raw ) : null;

        if ( ! $header || empty( $header->kid ) ) {
            return new \WP_Error(
                'invalid_token',
                'Apple token header geçersiz.'
            );
        }

        // Token'daki kid'e göre JWKS'den ilgili anahtarı bul
        $matching_key = null;
        foreach ( $jwks['keys'] as $key ) {
            if ( isset( $key['kid'] ) && $key['kid'] === $header->kid ) {
                $matching_key = $key;
                break;
            }
        }

        if ( null === $matching_key ) {
            // Önbelleği temizle ve yeniden dene (key rotation durumunda)
            delete_transient( 'kg_apple_jwks' );
            return new \WP_Error(
                'invalid_token',
                'Apple token için eşleşen public key bulunamadı.'
            );
        }

        // Firebase JWT ile doğrula (imza + exp kontrolü)
        try {
            $key_set = JWK::parseKeySet( [ 'keys' => [ $matching_key ] ] );
            $payload = JWT::decode( $identity_token, $key_set );
        } catch ( \Firebase\JWT\ExpiredException $e ) {
            return new \WP_Error(
                'expired_token',
                'Apple token süresi dolmuş.'
            );
        } catch ( \Exception $e ) {
            return new \WP_Error(
                'invalid_token',
                'Apple token doğrulanamadı: ' . $e->getMessage()
            );
        }

        // iss (issuer) doğrulaması
        if ( ( $payload->iss ?? '' ) !== 'https://appleid.apple.com' ) {
            return new \WP_Error(
                'invalid_issuer',
                'Apple token issuer geçersiz.'
            );
        }

        // aud (audience) doğrulaması — Bundle ID veya Service ID eşleşmeli
        $token_aud      = $payload->aud ?? '';
        $valid_audience = false;

        if ( ! empty( $this->bundle_id ) && $token_aud === $this->bundle_id ) {
            $valid_audience = true;
        }
        if ( ! empty( $this->service_id ) && $token_aud === $this->service_id ) {
            $valid_audience = true;
        }

        if ( ! $valid_audience ) {
            return new \WP_Error(
                'invalid_audience',
                'Apple token bu uygulama için geçerli değil.'
            );
        }

        // email_verified kontrolü (Apple bazen string "true" döner)
        $email_verified = $payload->email_verified ?? false;
        $email_verified = ( $email_verified === true || $email_verified === 'true' );

        // İsim bilgisi Apple'ın döndürmediği durumlarda boş string
        $name = '';
        if ( isset( $payload->name ) ) {
            $name = trim( ( $payload->name->firstName ?? '' ) . ' ' . ( $payload->name->lastName ?? '' ) );
        }

        $email = $payload->email ?? '';

        return [
            'email'            => $email,
            'email_verified'   => $email_verified,
            'name'             => $name,
            'apple_id'         => $payload->sub,
            'is_private_email' => isset( $payload->is_private_email )
                                    ? ( $payload->is_private_email === true || $payload->is_private_email === 'true' )
                                    : $this->is_private_relay_email( $email ),
        ];
    }

    /**
     * Apple kullanıcısını WordPress kullanıcısına eşle veya oluştur
     *
     * @param array      $apple_data        verify_identity_token()'dan dönen veri
     * @param array|null $name_from_request İlk girişte client'ın gönderdiği isim {'given_name': ..., 'family_name': ...}
     * @return \WP_User|\WP_Error
     */
    public function get_or_create_user( $apple_data, $name_from_request = null ) {
        $email    = $apple_data['email'];
        $apple_id = $apple_data['apple_id'];

        // Önce Apple ID ile kullanıcı ara (user meta'da)
        $users = get_users( [
            'meta_key'   => 'apple_id',
            'meta_value' => $apple_id,
            'number'     => 1,
        ] );

        if ( ! empty( $users ) ) {
            return $users[0];
        }

        // Email ile ara
        if ( ! empty( $email ) ) {
            $user = get_user_by( 'email', $email );

            if ( $user ) {
                // Mevcut kullanıcıya Apple ID linkle
                update_user_meta( $user->ID, 'apple_id', $apple_id );
                update_user_meta( $user->ID, 'apple_email_is_private', $apple_data['is_private_email'] );
                return $user;
            }
        }

        // Kullanıcı tam adını belirle
        $display_name = $this->resolve_display_name( $apple_data['name'], $name_from_request, $email );

        // Yeni kullanıcı oluştur
        $username = $this->generate_unique_username( $email, $display_name );
        $password = wp_generate_password( 24, true, true );

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $display_name ?: $username,
            'role'         => 'kg_parent',
        ] );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Meta bilgilerini kaydet
        update_user_meta( $user_id, 'apple_id', $apple_id );
        update_user_meta( $user_id, 'apple_email_is_private', $apple_data['is_private_email'] );
        update_user_meta( $user_id, 'registered_via', 'apple' );
        update_user_meta( $user_id, 'apple_first_signin', true );

        return get_user_by( 'id', $user_id );
    }

    /**
     * Görüntülenecek ismi belirle
     * Apple ilk girişte isim gönderir, sonraki girişlerde göndermez
     */
    private function resolve_display_name( $name_from_token, $name_from_request, $email ) {
        // Token'dan gelen isim (nadiren dolu olur)
        if ( ! empty( $name_from_token ) ) {
            return sanitize_text_field( $name_from_token );
        }

        // Client'ın gönderdiği isim (ilk girişte Apple tarafından gönderilir)
        if ( is_array( $name_from_request ) ) {
            $given  = sanitize_text_field( $name_from_request['given_name'] ?? $name_from_request['firstName'] ?? '' );
            $family = sanitize_text_field( $name_from_request['family_name'] ?? $name_from_request['lastName'] ?? '' );
            $full   = trim( $given . ' ' . $family );
            if ( ! empty( $full ) ) {
                return $full;
            }
        }

        // Email'den kullanıcı adı türet (isim hiç gelmediyse)
        if ( ! empty( $email ) && ! $this->is_private_relay_email( $email ) ) {
            return strstr( $email, '@', true );
        }

        return '';
    }

    /**
     * Benzersiz kullanıcı adı oluştur
     */
    private function generate_unique_username( $email, $name = '' ) {
        // Önce isimden dene
        if ( ! empty( $name ) ) {
            $base = sanitize_user( strtolower( str_replace( ' ', '', $name ) ), true );
            if ( ! empty( $base ) && ! username_exists( $base ) ) {
                return $base;
            }
        }

        // Email'den kullanıcı adı oluştur
        $base = strstr( $email, '@', true );
        $base = sanitize_user( $base, true );

        if ( ! empty( $base ) && ! username_exists( $base ) ) {
            return $base;
        }

        // Private relay email durumunda rastgele bir temel kullan
        if ( empty( $base ) || $this->is_private_relay_email( $email ) ) {
            $base = 'apple_user';
        }

        // Numara ekleyerek benzersiz yap
        $counter = 1;
        while ( username_exists( $base . $counter ) ) {
            $counter++;
        }

        return $base . $counter;
    }

    /**
     * Email adresinin Apple private relay adresi olup olmadığını kontrol et
     *
     * @param string $email
     * @return bool
     */
    private function is_private_relay_email( $email ) {
        return strpos( $email, self::APPLE_PRIVATE_RELAY_DOMAIN ) !== false;
    }

    /**
     * Base64URL decode (RFC 4648)
     *
     * @param string $data Base64URL encoded string
     * @return string|false Decoded string, or false on failure
     */
    private function base64url_decode( $data ) {
        $remainder = strlen( $data ) % 4;
        if ( $remainder ) {
            $data .= str_repeat( '=', 4 - $remainder );
        }
        return base64_decode( strtr( $data, '-_', '+/' ), true );
    }
}

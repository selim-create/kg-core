<?php
namespace KG_Core\Auth;

/**
 * Simple JWT Handler without external dependencies
 * For production, consider using firebase/php-jwt library
 */
class JWTHandler {

    private static $secret_key = null;

    /**
     * Get or generate secret key
     */
    private static function get_secret_key() {
        if ( self::$secret_key !== null ) {
            return self::$secret_key;
        }

        // Try to get from WordPress constant or option
        if ( defined( 'KG_JWT_SECRET' ) ) {
            self::$secret_key = KG_JWT_SECRET;
        } else {
            $secret = get_option( 'kg_jwt_secret' );
            if ( ! $secret ) {
                $secret = wp_generate_password( 64, true, true );
                update_option( 'kg_jwt_secret', $secret );
            }
            self::$secret_key = $secret;
        }

        return self::$secret_key;
    }

    /**
     * Generate JWT token for user
     */
    public static function generate_token( $user_id, $expiration_hours = 24 ) {
        $issued_at = time();
        $expiration = $issued_at + ( $expiration_hours * 3600 );

        $payload = [
            'iss' => get_bloginfo( 'url' ),
            'iat' => $issued_at,
            'exp' => $expiration,
            'user_id' => $user_id,
        ];

        return self::encode( $payload );
    }

    /**
     * Validate and decode JWT token
     */
    public static function validate_token( $token ) {
        try {
            $payload = self::decode( $token );
            
            if ( ! $payload ) {
                return false;
            }

            // Check expiration
            if ( isset( $payload['exp'] ) && $payload['exp'] < time() ) {
                return false;
            }

            // Check user exists
            if ( ! isset( $payload['user_id'] ) ) {
                return false;
            }

            $user = get_user_by( 'id', $payload['user_id'] );
            if ( ! $user ) {
                return false;
            }

            return $payload;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get user ID from token
     */
    public static function get_user_id_from_token( $token ) {
        $payload = self::validate_token( $token );
        return $payload ? $payload['user_id'] : null;
    }

    /**
     * Simple JWT encode (Header.Payload.Signature)
     */
    private static function encode( $payload ) {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $header_encoded = self::base64_url_encode( json_encode( $header ) );
        $payload_encoded = self::base64_url_encode( json_encode( $payload ) );
        
        $signature = hash_hmac(
            'sha256',
            $header_encoded . '.' . $payload_encoded,
            self::get_secret_key(),
            true
        );
        $signature_encoded = self::base64_url_encode( $signature );

        return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
    }

    /**
     * Simple JWT decode
     */
    private static function decode( $token ) {
        $parts = explode( '.', $token );
        
        if ( count( $parts ) !== 3 ) {
            return false;
        }

        list( $header_encoded, $payload_encoded, $signature_encoded ) = $parts;

        // Verify signature
        $signature = hash_hmac(
            'sha256',
            $header_encoded . '.' . $payload_encoded,
            self::get_secret_key(),
            true
        );
        $signature_check = self::base64_url_encode( $signature );

        if ( $signature_encoded !== $signature_check ) {
            return false;
        }

        $payload = json_decode( self::base64_url_decode( $payload_encoded ), true );
        
        return $payload;
    }

    /**
     * Base64 URL encode
     */
    private static function base64_url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Base64 URL decode
     */
    private static function base64_url_decode( $data ) {
        return base64_decode( strtr( $data, '-_', '+/' ) );
    }

    /**
     * Extract token from request headers
     */
    public static function get_token_from_request() {
        // Try WordPress function first
        if ( function_exists( 'apache_request_headers' ) ) {
            $headers = apache_request_headers();
        } elseif ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
        } else {
            // Manual extraction from $_SERVER
            $headers = [];
            foreach ( $_SERVER as $key => $value ) {
                if ( substr( $key, 0, 5 ) === 'HTTP_' ) {
                    $header_key = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $key, 5 ) ) ) ) );
                    $headers[$header_key] = $value;
                }
            }
        }
        
        if ( isset( $headers['Authorization'] ) ) {
            $auth_header = $headers['Authorization'];
            if ( preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Invalidate token (for logout)
     * Note: With stateless JWT, we can't truly invalidate tokens
     * In production, implement a blacklist or use refresh tokens
     */
    public static function invalidate_token( $token ) {
        // Store in blacklist (implement as needed)
        // For now, just return true
        return true;
    }
}

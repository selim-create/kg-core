<?php
namespace KG_Core\Utils;

use KG_Core\Auth\JWTHandler;

class Helper {

    /**
     * Debugging helper to log data to debug.log
     * Usage: \KG_Core\Utils\Helper::log($data);
     */
    public static function log( $data ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_array( $data ) || is_object( $data ) ) {
                error_log( print_r( $data, true ) );
            } else {
                error_log( $data );
            }
        }
    }
    
    /**
     * Example: Get Current User IP
     */
    public static function get_client_ip() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }

    /**
     * Get current user from JWT token
     */
    public static function get_current_user_from_token() {
        $token = JWTHandler::get_token_from_request();
        
        if ( ! $token ) {
            return null;
        }

        $user_id = JWTHandler::get_user_id_from_token( $token );
        
        if ( ! $user_id ) {
            return null;
        }

        return get_user_by( 'id', $user_id );
    }

    /**
     * Sanitize recipe data for API
     */
    public static function sanitize_recipe_data( $data ) {
        $sanitized = [];

        if ( isset( $data['title'] ) ) {
            $sanitized['title'] = sanitize_text_field( $data['title'] );
        }

        if ( isset( $data['content'] ) ) {
            $sanitized['content'] = wp_kses_post( $data['content'] );
        }

        if ( isset( $data['prep_time'] ) ) {
            $sanitized['prep_time'] = absint( $data['prep_time'] );
        }

        if ( isset( $data['ingredients'] ) && is_array( $data['ingredients'] ) ) {
            $sanitized['ingredients'] = array_map( 'sanitize_text_field', $data['ingredients'] );
        }

        return $sanitized;
    }

    /**
     * Format age in months for display
     * @param int $months Age in months
     * @return string Formatted age string
     */
    public static function format_age_for_display( $months ) {
        if ( $months < 12 ) {
            return $months . ' ay';
        }

        $years = floor( $months / 12 );
        $remaining_months = $months % 12;

        if ( $remaining_months === 0 ) {
            return $years . ' yaş';
        }

        return $years . ' yaş ' . $remaining_months . ' ay';
    }

    /**
     * Calculate child age in months from birth date
     * @param string $birth_date Birth date in Y-m-d format
     * @return int Age in months
     */
    public static function calculate_child_age( $birth_date ) {
        if ( empty( $birth_date ) ) {
            return 0;
        }

        try {
            $birth = new \DateTime( $birth_date );
            $now = new \DateTime();
            $interval = $birth->diff( $now );

            $months = ( $interval->y * 12 ) + $interval->m;
            
            return $months;
        } catch ( \Exception $e ) {
            // Invalid date format, return 0
            return 0;
        }
    }

    /**
     * Get age-appropriate recipes for a child
     * @param string $birth_date Child's birth date
     * @return array Recipe IDs
     */
    public static function get_age_appropriate_recipes( $birth_date ) {
        $age_in_months = self::calculate_child_age( $birth_date );
        
        // Determine age group based on months
        $age_group_slug = '';
        if ( $age_in_months >= 4 && $age_in_months < 6 ) {
            $age_group_slug = '4-6-ay';
        } elseif ( $age_in_months >= 6 && $age_in_months < 12 ) {
            $age_group_slug = '6-12-ay';
        } elseif ( $age_in_months >= 12 && $age_in_months < 24 ) {
            $age_group_slug = '12-24-ay';
        } elseif ( $age_in_months >= 24 ) {
            $age_group_slug = '2-yas-uzeri';
        }

        if ( empty( $age_group_slug ) ) {
            return [];
        }

        $args = [
            'post_type' => 'recipe',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'age-group',
                    'field' => 'slug',
                    'terms' => $age_group_slug,
                ],
            ],
        ];

        $query = new \WP_Query( $args );
        return $query->posts;
    }

    /**
     * Decode HTML entities safely
     * Handles double-encoded entities like &amp;amp;
     * 
     * We run html_entity_decode twice because some WordPress functions
     * or database imports may double-encode entities. For example:
     * - First decode: &amp;amp; → &amp;
     * - Second decode: &amp; → &
     * This ensures clean output regardless of encoding depth.
     * 
     * @param string $text Text to decode
     * @return string Decoded text
     */
    public static function decode_html_entities( $text ) {
        if ( empty( $text ) ) {
            return '';
        }
        
        // Run html_entity_decode twice to handle double-encoded entities
        $decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $decoded = html_entity_decode( $decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        
        return $decoded;
    }

    /**
     * Format date in Turkish
     * Converts English month names to Turkish
     * 
     * @param string $date Date string or timestamp
     * @param string $format Date format (default: 'd F Y')
     * @return string Formatted Turkish date
     */
    public static function format_turkish_date( $date, $format = 'd F Y' ) {
        $turkish_months = [
            'January' => 'Ocak',
            'February' => 'Şubat',
            'March' => 'Mart',
            'April' => 'Nisan',
            'May' => 'Mayıs',
            'June' => 'Haziran',
            'July' => 'Temmuz',
            'August' => 'Ağustos',
            'September' => 'Eylül',
            'October' => 'Ekim',
            'November' => 'Kasım',
            'December' => 'Aralık'
        ];
        
        // Convert date to timestamp if needed
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        
        // Format date in English
        $english_date = date($format, $timestamp);
        
        // Replace English months with Turkish
        return str_replace(array_keys($turkish_months), array_values($turkish_months), $english_date);
    }
    
    /**
     * Get user avatar URL with priority: custom > google > gravatar
     * 
     * @param int $user_id User ID
     * @param int $size Avatar size in pixels (default: 96)
     * @return string Avatar URL
     */
    public static function get_user_avatar_url( $user_id, $size = 96 ) {
        if ( ! $user_id ) {
            return get_avatar_url( 0, [ 'size' => $size ] );
        }
        
        // 1. Custom avatar - check _kg_avatar_id user meta
        $avatar_id = get_user_meta( $user_id, '_kg_avatar_id', true );
        if ( $avatar_id ) {
            $avatar_url = wp_get_attachment_url( $avatar_id );
            if ( $avatar_url ) {
                return $avatar_url;
            }
        }
        
        // 2. Google avatar - check google_avatar user meta
        $google_avatar = get_user_meta( $user_id, 'google_avatar', true );
        if ( ! empty( $google_avatar ) ) {
            return $google_avatar;
        }
        
        // 3. Gravatar - fallback to WordPress default
        return get_avatar_url( $user_id, [ 'size' => $size ] );
    }
}
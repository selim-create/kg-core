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

        $birth = new \DateTime( $birth_date );
        $now = new \DateTime();
        $interval = $birth->diff( $now );

        $months = ( $interval->y * 12 ) + $interval->m;
        
        return $months;
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
}
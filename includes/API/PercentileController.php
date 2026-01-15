<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Tools\WHOGrowthData;

class PercentileController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Calculate percentile (public)
        register_rest_route( 'kg/v1', '/tools/percentile/calculate', [
            'methods'  => 'POST',
            'callback' => [ $this, 'calculate_percentile' ],
            'permission_callback' => '__return_true',
        ]);

        // Save percentile result
        register_rest_route( 'kg/v1', '/tools/percentile/save', [
            'methods'  => 'POST',
            'callback' => [ $this, 'save_percentile_result' ],
            'permission_callback' => '__return_true',
        ]);

        // Get user's percentile results
        register_rest_route( 'kg/v1', '/user/percentile-results', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_user_percentile_results' ],
            'permission_callback' => [ $this, 'check_auth' ],
        ]);

        // Get child's percentile history
        register_rest_route( 'kg/v1', '/user/children/(?P<child_id>[a-zA-Z0-9-]+)/percentile-results', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_child_percentile_results' ],
            'permission_callback' => [ $this, 'check_auth' ],
        ]);
    }

    public function calculate_percentile( $request ) {
        $gender = sanitize_text_field( $request->get_param( 'gender' ) );
        $birth_date = sanitize_text_field( $request->get_param( 'birth_date' ) );
        $measurement_date = sanitize_text_field( $request->get_param( 'measurement_date' ) );
        $weight_kg = floatval( $request->get_param( 'weight_kg' ) );
        $height_cm = floatval( $request->get_param( 'height_cm' ) );
        $head_cm = floatval( $request->get_param( 'head_circumference_cm' ) );

        // Validate
        if ( ! in_array( $gender, [ 'male', 'female' ] ) ) {
            return new \WP_Error( 'invalid_gender', 'Cinsiyet male veya female olmalı', [ 'status' => 400 ] );
        }

        if ( empty( $birth_date ) || empty( $measurement_date ) ) {
            return new \WP_Error( 'missing_dates', 'Doğum ve ölçüm tarihi gerekli', [ 'status' => 400 ] );
        }

        // Calculate age in days
        $birth = new \DateTime( $birth_date );
        $measure = new \DateTime( $measurement_date );
        $age_in_days = $birth->diff( $measure )->days;
        $age_in_months = floor( $age_in_days / 30.4375 );

        if ( $age_in_days < 0 ) {
            return new \WP_Error( 'invalid_dates', 'Ölçüm tarihi doğum tarihinden önce olamaz', [ 'status' => 400 ] );
        }

        if ( $age_in_days > 1856 ) { // ~5 years
            return new \WP_Error( 'age_limit', 'WHO standartları 0-5 yaş için geçerlidir', [ 'status' => 400 ] );
        }

        $percentiles = [];
        $red_flags = [];

        $who_data = new WHOGrowthData();

        // Weight-for-age
        if ( $weight_kg > 0 ) {
            $wfa = $who_data->calculate_weight_for_age( $gender, $age_in_days, $weight_kg );
            if ( $wfa ) {
                $percentiles[] = $this->format_percentile_result( 'weight_for_age', $weight_kg, $wfa );
                
                if ( $wfa['percentile'] < 3 ) {
                    $red_flags[] = [
                        'type' => 'weight',
                        'message' => 'Kilo yaşa göre çok düşük (<%3 persentil). Pediatristinize danışmanızı öneririz.',
                        'severity' => 'critical'
                    ];
                } elseif ( $wfa['percentile'] > 97 ) {
                    $red_flags[] = [
                        'type' => 'weight',
                        'message' => 'Kilo yaşa göre çok yüksek (>%97 persentil). Pediatristinize danışmanızı öneririz.',
                        'severity' => 'warning'
                    ];
                }
            }
        }

        // Height/Length-for-age
        if ( $height_cm > 0 ) {
            $hfa = $who_data->calculate_height_for_age( $gender, $age_in_days, $height_cm );
            if ( $hfa ) {
                $percentiles[] = $this->format_percentile_result( 'height_for_age', $height_cm, $hfa );
                
                if ( $hfa['percentile'] < 3 ) {
                    $red_flags[] = [
                        'type' => 'height',
                        'message' => 'Boy yaşa göre çok düşük (<%3 persentil). Pediatristinize danışmanızı öneririz.',
                        'severity' => 'critical'
                    ];
                }
            }
        }

        // Head circumference-for-age
        if ( $head_cm > 0 ) {
            $hcfa = $who_data->calculate_head_for_age( $gender, $age_in_days, $head_cm );
            if ( $hcfa ) {
                $percentiles[] = $this->format_percentile_result( 'head_for_age', $head_cm, $hcfa );
                
                if ( $hcfa['percentile'] < 3 ) {
                    $red_flags[] = [
                        'type' => 'head',
                        'message' => 'Baş çevresi yaşa göre çok düşük (mikrosefali riski). Pediatristinize danışmanızı öneririz.',
                        'severity' => 'critical'
                    ];
                } elseif ( $hcfa['percentile'] > 97 ) {
                    $red_flags[] = [
                        'type' => 'head',
                        'message' => 'Baş çevresi yaşa göre çok yüksek (makrosefali riski). Pediatristinize danışmanızı öneririz.',
                        'severity' => 'critical'
                    ];
                }
            }
        }

        // Weight-for-height (if both available)
        if ( $weight_kg > 0 && $height_cm > 0 ) {
            $wfh = $who_data->calculate_weight_for_height( $gender, $height_cm, $weight_kg );
            if ( $wfh ) {
                $percentiles[] = $this->format_percentile_result( 'weight_for_height', $weight_kg, $wfh );
            }
        }

        return new \WP_REST_Response([
            'age_in_days' => $age_in_days,
            'age_in_months' => $age_in_months,
            'percentiles' => $percentiles,
            'red_flags' => $red_flags,
            'measurement' => [
                'gender' => $gender,
                'birth_date' => $birth_date,
                'measurement_date' => $measurement_date,
                'weight_kg' => $weight_kg ?: null,
                'height_cm' => $height_cm ?: null,
                'head_circumference_cm' => $head_cm ?: null,
            ],
            'created_at' => current_time( 'c' ),
        ], 200);
    }

    public function save_percentile_result( $request ) {
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $register = $request->get_param( 'register' );
        $measurement = $request->get_param( 'measurement' );
        $percentiles = $request->get_param( 'percentiles' );
        $red_flags = $request->get_param( 'red_flags' );

        // Check if user is authenticated
        $token = JWTHandler::get_token_from_request();
        $user_id = null;

        if ( $token ) {
            $payload = JWTHandler::validate_token( $token );
            if ( $payload ) {
                $user_id = $payload['user_id'];
            }
        }

        // Handle registration if requested
        if ( $register && ! $user_id ) {
            $email = sanitize_email( $request->get_param( 'email' ) );
            $password = $request->get_param( 'password' );
            $name = sanitize_text_field( $request->get_param( 'name' ) );

            if ( empty( $email ) || empty( $password ) ) {
                return new \WP_Error( 'registration_failed', 'Email ve şifre gerekli', [ 'status' => 400 ] );
            }

            if ( ! is_email( $email ) ) {
                return new \WP_Error( 'invalid_email', 'Geçerli bir email adresi giriniz', [ 'status' => 400 ] );
            }

            if ( email_exists( $email ) ) {
                return new \WP_Error( 'email_exists', 'Bu email adresi ile kayıtlı kullanıcı mevcut', [ 'status' => 409 ] );
            }

            if ( strlen( $password ) < 8 ) {
                return new \WP_Error( 'weak_password', 'Şifre en az 8 karakter olmalı', [ 'status' => 400 ] );
            }

            // Create user
            $user_id = wp_create_user( $email, $password, $email );

            if ( is_wp_error( $user_id ) ) {
                return new \WP_Error( 'registration_failed', 'Kullanıcı oluşturulamadı', [ 'status' => 500 ] );
            }

            $user = get_user_by( 'id', $user_id );
            $user->set_role( 'kg_parent' );

            if ( ! empty( $name ) ) {
                wp_update_user( [
                    'ID' => $user_id,
                    'display_name' => $name,
                ] );
            }

            // Create child profile
            $child_name = sanitize_text_field( $request->get_param( 'child_name' ) );
            $child_birth_date = sanitize_text_field( $request->get_param( 'child_birth_date' ) );
            $created_child_id = null;

            if ( ! empty( $child_name ) ) {
                $children = get_user_meta( $user_id, '_kg_children', true );
                if ( ! is_array( $children ) ) {
                    $children = [];
                }

                // Generate UUID for child
                if ( function_exists( 'wp_generate_uuid4' ) ) {
                    $created_child_id = wp_generate_uuid4();
                } else {
                    $created_child_id = sprintf(
                        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
                        mt_rand( 0, 0xffff ),
                        mt_rand( 0, 0x0fff ) | 0x4000,
                        mt_rand( 0, 0x3fff ) | 0x8000,
                        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
                    );
                }

                $new_child = [
                    'id' => $created_child_id,
                    'name' => $child_name,
                    'birth_date' => $child_birth_date ?: '',
                    'gender' => isset( $measurement['gender'] ) ? $measurement['gender'] : 'unspecified',
                    'created_at' => current_time( 'c' ),
                ];

                $children[] = $new_child;
                update_user_meta( $user_id, '_kg_children', $children );

                if ( empty( $child_id ) ) {
                    $child_id = $created_child_id;
                }
            }

            // Generate token
            $new_token = JWTHandler::generate_token( $user_id );
            $response_data = [
                'token' => $new_token,
                'user_id' => $user_id,
            ];

            if ( $created_child_id ) {
                $response_data['child_id'] = $created_child_id;
                $response_data['child_name'] = $child_name;
            }
        }

        // Save result if user is authenticated
        if ( ! $user_id ) {
            return new \WP_Error( 'unauthorized', 'Sonuç kaydetmek için giriş yapmalısınız', [ 'status' => 401 ] );
        }

        $this->save_result( $user_id, $child_id, $measurement, $percentiles, $red_flags );

        $response = [
            'success' => true,
            'message' => 'Persentil sonucu kaydedildi',
        ];

        if ( isset( $response_data ) ) {
            $response = array_merge( $response, $response_data );
        }

        return new \WP_REST_Response( $response, 200 );
    }

    public function get_user_percentile_results( $request ) {
        $token = JWTHandler::get_token_from_request();
        $payload = JWTHandler::validate_token( $token );

        if ( ! $payload ) {
            return new \WP_Error( 'unauthorized', 'Geçersiz token', [ 'status' => 401 ] );
        }

        $user_id = $payload['user_id'];
        $results = get_user_meta( $user_id, '_kg_percentile_results', true );

        if ( ! is_array( $results ) ) {
            $results = [];
        }

        // Sort by timestamp descending
        usort( $results, function( $a, $b ) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        });

        return new \WP_REST_Response( $results, 200 );
    }

    public function get_child_percentile_results( $request ) {
        $child_id = $request->get_param( 'child_id' );
        $token = JWTHandler::get_token_from_request();
        $payload = JWTHandler::validate_token( $token );

        if ( ! $payload ) {
            return new \WP_Error( 'unauthorized', 'Geçersiz token', [ 'status' => 401 ] );
        }

        $user_id = $payload['user_id'];
        $results = get_user_meta( $user_id, '_kg_percentile_results', true );

        if ( ! is_array( $results ) ) {
            $results = [];
        }

        // Filter by child_id
        $child_results = array_filter( $results, function( $result ) use ( $child_id ) {
            return isset( $result['child_id'] ) && $result['child_id'] === $child_id;
        });

        // Sort by timestamp descending
        $child_results = array_values( $child_results );
        usort( $child_results, function( $a, $b ) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        });

        return new \WP_REST_Response( $child_results, 200 );
    }

    public function check_auth() {
        $token = JWTHandler::get_token_from_request();
        $payload = JWTHandler::validate_token( $token );
        return ! ! $payload;
    }

    private function format_percentile_result( $type, $value, $calc ) {
        $category = $this->get_category( $calc['percentile'] );
        
        return [
            'measurement_type' => $type,
            'value' => $value,
            'percentile' => round( $calc['percentile'], 1 ),
            'z_score' => round( $calc['z_score'], 2 ),
            'category' => $category,
            'interpretation' => $this->get_interpretation( $type, $category ),
        ];
    }

    private function get_category( $percentile ) {
        if ( $percentile < 3 ) return 'very_low';
        if ( $percentile < 15 ) return 'low';
        if ( $percentile <= 85 ) return 'normal';
        if ( $percentile <= 97 ) return 'high';
        return 'very_high';
    }

    private function get_interpretation( $type, $category ) {
        $texts = [
            'weight_for_age' => [
                'very_low' => 'Kilo yaşa göre çok düşük',
                'low' => 'Kilo yaşa göre düşük',
                'normal' => 'Kilo yaşa göre normal aralıkta',
                'high' => 'Kilo yaşa göre yüksek',
                'very_high' => 'Kilo yaşa göre çok yüksek',
            ],
            'height_for_age' => [
                'very_low' => 'Boy yaşa göre çok düşük',
                'low' => 'Boy yaşa göre düşük',
                'normal' => 'Boy yaşa göre normal aralıkta',
                'high' => 'Boy yaşa göre uzun',
                'very_high' => 'Boy yaşa göre çok uzun',
            ],
            'head_for_age' => [
                'very_low' => 'Baş çevresi yaşa göre çok küçük',
                'low' => 'Baş çevresi yaşa göre küçük',
                'normal' => 'Baş çevresi normal aralıkta',
                'high' => 'Baş çevresi yaşa göre büyük',
                'very_high' => 'Baş çevresi yaşa göre çok büyük',
            ],
            'weight_for_height' => [
                'very_low' => 'Boya göre çok zayıf',
                'low' => 'Boya göre zayıf',
                'normal' => 'Kilo-boy oranı ideal',
                'high' => 'Boya göre kilolu',
                'very_high' => 'Boya göre obez',
            ],
        ];
        
        return $texts[$type][$category] ?? 'Değerlendirme yapılamadı';
    }

    private function save_result( $user_id, $child_id, $measurement, $percentiles, $red_flags ) {
        $results = get_user_meta( $user_id, '_kg_percentile_results', true );
        
        if ( ! is_array( $results ) ) {
            $results = [];
        }

        // Generate UUID
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            $uuid = wp_generate_uuid4();
        } else {
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
                mt_rand( 0, 0xffff ),
                mt_rand( 0, 0x0fff ) | 0x4000,
                mt_rand( 0, 0x3fff ) | 0x8000,
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
            );
        }

        // Calculate age from measurement
        $birth = new \DateTime( $measurement['birth_date'] );
        $measure = new \DateTime( $measurement['measurement_date'] );
        $age_in_days = $birth->diff( $measure )->days;

        $result_entry = [
            'id' => $uuid,
            'child_id' => $child_id ?: null,
            'measurement' => $measurement,
            'age_in_days' => $age_in_days,
            'percentiles' => $percentiles,
            'red_flags' => $red_flags ?: [],
            'created_at' => current_time( 'c' ),
        ];

        $results[] = $result_entry;
        update_user_meta( $user_id, '_kg_percentile_results', $results );

        return $result_entry;
    }
}

<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Tools\WHOGrowthData;

/**
 * GrowthController - Büyüme takibi API endpoint'leri
 *
 * Endpoints:
 *   GET  /kg/v1/health/growth            - Büyüme kayıtlarını listele + persentil
 *   POST /kg/v1/health/growth            - Yeni büyüme ölçümü ekle
 *   PUT  /kg/v1/health/growth/{id}       - Ölçüm kaydını güncelle
 *   DELETE /kg/v1/health/growth/{id}     - Ölçüm kaydını sil
 *   GET  /kg/v1/health/growth/chart-data - Grafik verisi + WHO referans eğrileri
 */
class GrowthController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * REST API route'larını kaydet
     */
    public function register_routes() {
        // GET /kg/v1/health/growth
        register_rest_route( 'kg/v1', '/health/growth', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_growth_records' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args'                => [
                'child_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'description'       => 'Child UUID',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // POST /kg/v1/health/growth
        register_rest_route( 'kg/v1', '/health/growth', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'add_growth_record' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args'                => [
                'child_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ( $param ) {
                        return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
                    },
                ],
                'weight_kg' => [
                    'required'          => false,
                    'type'              => 'number',
                    'sanitize_callback' => 'floatval',
                ],
                'height_cm' => [
                    'required'          => false,
                    'type'              => 'number',
                    'sanitize_callback' => 'floatval',
                ],
                'head_circumference_cm' => [
                    'required'          => false,
                    'type'              => 'number',
                    'sanitize_callback' => 'floatval',
                ],
                'notes' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ],
            ],
        ] );

        // PUT /kg/v1/health/growth/{id}
        register_rest_route( 'kg/v1', '/health/growth/(?P<id>[a-zA-Z0-9-]+)', [
            'methods'             => 'PUT',
            'callback'            => [ $this, 'update_growth_record' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args'                => [
                'id' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'date' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'weight_kg' => [
                    'required' => false,
                    'type'     => 'number',
                ],
                'height_cm' => [
                    'required' => false,
                    'type'     => 'number',
                ],
                'head_circumference_cm' => [
                    'required' => false,
                    'type'     => 'number',
                ],
                'notes' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // DELETE /kg/v1/health/growth/{id}
        register_rest_route( 'kg/v1', '/health/growth/(?P<id>[a-zA-Z0-9-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_growth_record' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args'                => [
                'id' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ] );

        // GET /kg/v1/health/growth/chart-data
        register_rest_route( 'kg/v1', '/health/growth/chart-data', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_chart_data' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args'                => [
                'child_id' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'weight_for_age',
                    'enum'              => [ 'weight_for_age', 'height_for_age', 'head_for_age' ],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );
    }

    /**
     * JWT kimlik doğrulaması
     */
    public function check_authentication( $request ) {
        $token = JWTHandler::get_token_from_request();

        if ( ! $token ) {
            return false;
        }

        $payload = JWTHandler::validate_token( $token );

        if ( ! $payload ) {
            return false;
        }

        $request->set_param( 'authenticated_user_id', $payload['user_id'] );
        return true;
    }

    /**
     * GET /kg/v1/health/growth
     * Belirli bir çocuğun büyüme kayıtlarını + en son kaydı + persentili döndür
     */
    public function get_growth_records( $request ) {
        $user_id  = $request->get_param( 'authenticated_user_id' );
        $child_id = $request->get_param( 'child_id' );

        $all_records = $this->get_user_growth_records( $user_id );
        $records     = $this->filter_records_by_child( $all_records, $child_id );

        // Tarihe göre sırala (eskiden yeniye)
        usort( $records, function ( $a, $b ) {
            return strcmp( $a['date'], $b['date'] );
        } );

        $latest     = ! empty( $records ) ? end( $records ) : null;
        $percentile = null;

        if ( $latest ) {
            $percentile = $this->calculate_percentile_for_record( $user_id, $child_id, $latest );
        }

        return new \WP_REST_Response(
            [
                'records'    => array_values( $records ),
                'latest'     => $latest,
                'percentile' => $percentile,
            ],
            200
        );
    }

    /**
     * POST /kg/v1/health/growth
     * Yeni büyüme ölçümü kaydet
     */
    public function add_growth_record( $request ) {
        $user_id  = $request->get_param( 'authenticated_user_id' );
        $child_id = $request->get_param( 'child_id' );

        $record = [
            'id'                    => $this->generate_uuid(),
            'child_id'              => $child_id,
            'date'                  => $request->get_param( 'date' ),
            'weight_kg'             => $request->get_param( 'weight_kg' ) ? (float) $request->get_param( 'weight_kg' ) : null,
            'height_cm'             => $request->get_param( 'height_cm' ) ? (float) $request->get_param( 'height_cm' ) : null,
            'head_circumference_cm' => $request->get_param( 'head_circumference_cm' ) ? (float) $request->get_param( 'head_circumference_cm' ) : null,
            'notes'                 => $request->get_param( 'notes' ) ?: '',
        ];

        $all_records   = $this->get_user_growth_records( $user_id );
        $all_records[] = $record;
        update_user_meta( $user_id, '_kg_growth_records', $all_records );

        return new \WP_REST_Response(
            [
                'success' => true,
                'record'  => $record,
                'message' => 'Ölçüm kaydedildi.',
            ],
            201
        );
    }

    /**
     * PUT /kg/v1/health/growth/{id}
     * Ölçüm kaydını güncelle
     */
    public function update_growth_record( $request ) {
        $user_id   = $request->get_param( 'authenticated_user_id' );
        $record_id = $request->get_param( 'id' );

        $all_records = $this->get_user_growth_records( $user_id );
        $found       = false;

        foreach ( $all_records as &$record ) {
            if ( isset( $record['id'] ) && $record['id'] === $record_id ) {
                if ( null !== $request->get_param( 'date' ) ) {
                    $record['date'] = $request->get_param( 'date' );
                }
                if ( null !== $request->get_param( 'weight_kg' ) ) {
                    $record['weight_kg'] = (float) $request->get_param( 'weight_kg' );
                }
                if ( null !== $request->get_param( 'height_cm' ) ) {
                    $record['height_cm'] = (float) $request->get_param( 'height_cm' );
                }
                if ( null !== $request->get_param( 'head_circumference_cm' ) ) {
                    $record['head_circumference_cm'] = (float) $request->get_param( 'head_circumference_cm' );
                }
                if ( null !== $request->get_param( 'notes' ) ) {
                    $record['notes'] = $request->get_param( 'notes' );
                }
                $found          = true;
                $updated_record = $record;
                break;
            }
        }
        unset( $record );

        if ( ! $found ) {
            return new \WP_Error( 'record_not_found', 'Ölçüm kaydı bulunamadı.', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_growth_records', $all_records );

        return new \WP_REST_Response( [
            'success' => true,
            'record'  => $updated_record,
            'message' => 'Ölçüm güncellendi.',
        ], 200 );
    }

    /**
     * DELETE /kg/v1/health/growth/{id}
     * Ölçüm kaydını sil
     */
    public function delete_growth_record( $request ) {
        $user_id   = $request->get_param( 'authenticated_user_id' );
        $record_id = $request->get_param( 'id' );

        $all_records   = $this->get_user_growth_records( $user_id );
        $initial_count = count( $all_records );

        $all_records = array_values( array_filter( $all_records, function ( $record ) use ( $record_id ) {
            return ! isset( $record['id'] ) || $record['id'] !== $record_id;
        } ) );

        if ( count( $all_records ) === $initial_count ) {
            return new \WP_Error( 'record_not_found', 'Ölçüm kaydı bulunamadı.', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_growth_records', $all_records );

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Ölçüm silindi.',
        ], 200 );
    }

    /**
     * GET /kg/v1/health/growth/chart-data
     * Grafik verisi: çocuğun tüm ölçümleri + WHO referans eğrileri
     */
    public function get_chart_data( $request ) {
        $user_id  = $request->get_param( 'authenticated_user_id' );
        $child_id = $request->get_param( 'child_id' );
        $type     = $request->get_param( 'type' );

        // Çocuk bilgilerini al
        $child = $this->get_child_by_id( $user_id, $child_id );

        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Çocuk profili bulunamadı.', [ 'status' => 404 ] );
        }

        $gender     = $child['gender'] ?? 'male';
        $birth_date = $child['birth_date'] ?? '';

        if ( empty( $birth_date ) ) {
            return new \WP_Error( 'missing_birth_date', 'Çocuğun doğum tarihi eksik.', [ 'status' => 400 ] );
        }

        // Ölçümleri al ve tarihe göre sırala
        $all_records = $this->get_user_growth_records( $user_id );
        $records     = $this->filter_records_by_child( $all_records, $child_id );

        usort( $records, function ( $a, $b ) {
            return strcmp( $a['date'], $b['date'] );
        } );

        $who_data    = new WHOGrowthData();
        $measurements = [];

        foreach ( $records as $record ) {
            $value = $this->get_value_for_type( $record, $type );
            if ( null === $value ) {
                continue;
            }

            $age_days   = $this->calculate_age_days( $birth_date, $record['date'] );
            $who_result = $this->calculate_who_result( $who_data, $gender, $age_days, $value, $type );

            $measurements[] = [
                'age_days'   => $age_days,
                'value'      => $value,
                'percentile' => $who_result ? round( $who_result['percentile'], 1 ) : null,
                'z_score'    => $who_result ? round( $who_result['z_score'], 2 ) : null,
                'date'       => $record['date'],
            ];
        }

        // WHO referans eğrilerini oluştur
        $reference_curves = $this->build_reference_curves( $who_data, $gender, $type );

        return new \WP_REST_Response(
            [
                'child'            => [
                    'name'       => $child['name'] ?? '',
                    'gender'     => $gender,
                    'birth_date' => $birth_date,
                ],
                'type'             => $type,
                'measurements'     => $measurements,
                'reference_curves' => $reference_curves,
            ],
            200
        );
    }

    // -------------------------------------------------------------------------
    // Private helper methods
    // -------------------------------------------------------------------------

    /**
     * Kullanıcının tüm büyüme kayıtlarını getir
     */
    private function get_user_growth_records( $user_id ) {
        $records = get_user_meta( $user_id, '_kg_growth_records', true );
        return is_array( $records ) ? $records : [];
    }

    /**
     * Kayıtları child_id'ye göre filtrele
     */
    private function filter_records_by_child( $records, $child_id ) {
        return array_values(
            array_filter( $records, function ( $r ) use ( $child_id ) {
                return isset( $r['child_id'] ) && $r['child_id'] === $child_id;
            } )
        );
    }

    /**
     * Kullanıcının _kg_children meta'sından child_id ile çocuğu bul
     */
    private function get_child_by_id( $user_id, $child_id ) {
        $children = get_user_meta( $user_id, '_kg_children', true );

        if ( ! is_array( $children ) ) {
            return null;
        }

        foreach ( $children as $child ) {
            if ( isset( $child['id'] ) && $child['id'] === $child_id ) {
                return $child;
            }
        }

        return null;
    }

    /**
     * En son kayıt için persentil hesapla
     */
    private function calculate_percentile_for_record( $user_id, $child_id, $record ) {
        $child = $this->get_child_by_id( $user_id, $child_id );

        if ( ! $child || empty( $child['birth_date'] ) ) {
            return null;
        }

        $gender     = $child['gender'] ?? 'male';
        $birth_date = $child['birth_date'];
        $age_days   = $this->calculate_age_days( $birth_date, $record['date'] );
        $age_months = (int) round( $age_days / 30.4375 );
        $who_data   = new WHOGrowthData();

        $result = [
            'age_months'                  => $age_months,
            'calculated_at'               => current_time( 'c' ),
            'weight_percentile'           => null,
            'height_percentile'           => null,
            'head_circumference_percentile' => null,
        ];

        if ( ! empty( $record['weight_kg'] ) ) {
            $wfa = $who_data->calculate_weight_for_age( $gender, $age_days, (float) $record['weight_kg'] );
            if ( $wfa ) {
                $result['weight_percentile'] = round( $wfa['percentile'], 1 );
            }
        }

        if ( ! empty( $record['height_cm'] ) ) {
            $hfa = $who_data->calculate_height_for_age( $gender, $age_days, (float) $record['height_cm'] );
            if ( $hfa ) {
                $result['height_percentile'] = round( $hfa['percentile'], 1 );
            }
        }

        if ( ! empty( $record['head_circumference_cm'] ) ) {
            $hcfa = $who_data->calculate_head_for_age( $gender, $age_days, (float) $record['head_circumference_cm'] );
            if ( $hcfa ) {
                $result['head_circumference_percentile'] = round( $hcfa['percentile'], 1 );
            }
        }

        return $result;
    }

    /**
     * Grafik tipi için kayıttaki değeri getir
     */
    private function get_value_for_type( $record, $type ) {
        switch ( $type ) {
            case 'weight_for_age':
                return isset( $record['weight_kg'] ) && $record['weight_kg'] > 0
                    ? (float) $record['weight_kg'] : null;
            case 'height_for_age':
                return isset( $record['height_cm'] ) && $record['height_cm'] > 0
                    ? (float) $record['height_cm'] : null;
            case 'head_for_age':
                return isset( $record['head_circumference_cm'] ) && $record['head_circumference_cm'] > 0
                    ? (float) $record['head_circumference_cm'] : null;
            default:
                return null;
        }
    }

    /**
     * WHOGrowthData ile z-score ve persentil hesapla
     */
    private function calculate_who_result( $who_data, $gender, $age_days, $value, $type ) {
        switch ( $type ) {
            case 'weight_for_age':
                return $who_data->calculate_weight_for_age( $gender, $age_days, $value );
            case 'height_for_age':
                return $who_data->calculate_height_for_age( $gender, $age_days, $value );
            case 'head_for_age':
                return $who_data->calculate_head_for_age( $gender, $age_days, $value );
            default:
                return null;
        }
    }

    /**
     * WHO LMS tablosundan referans eğrilerini oluştur
     * Persentil değerleri: P3, P15, P50, P85, P97
     * Formül: M * (1 + L*S*z)^(1/L)  L≠0;  M * exp(S*z)  L=0
     */
    private function build_reference_curves( $who_data, $gender, $type ) {
        $file_map = [
            'weight_for_age' => [
                'male'   => 'wfa_boys_0_5.json',
                'female' => 'wfa_girls_0_5.json',
            ],
            'height_for_age' => [
                'male'   => 'lhfa_boys_0_5.json',
                'female' => 'lhfa_girls_0_5.json',
            ],
            'head_for_age' => [
                'male'   => 'hcfa_boys_0_5.json',
                'female' => 'hcfa_girls_0_5.json',
            ],
        ];

        if ( ! isset( $file_map[ $type ] ) ) {
            return null;
        }

        $gender_key = ( 'female' === $gender ) ? 'female' : 'male';
        $filename   = $file_map[ $type ][ $gender_key ];
        $data_dir   = KG_CORE_PATH . 'data/who-growth-tables/';
        $filepath   = $data_dir . $filename;

        if ( ! file_exists( $filepath ) ) {
            return null;
        }

        $raw = file_get_contents( $filepath );
        $data = json_decode( $raw, true );

        if ( empty( $data ) ) {
            return null;
        }

        // z-score değerleri (standart normal dağılım)
        $z_scores = [
            'p3'  => -1.881,
            'p15' => -1.036,
            'p50' =>  0.0,
            'p85' =>  1.036,
            'p97' =>  1.881,
        ];

        $curves = array_fill_keys( array_keys( $z_scores ), [] );

        foreach ( $data as $row ) {
            $age = $row['age'];
            $L   = $row['L'];
            $M   = $row['M'];
            $S   = $row['S'];

            foreach ( $z_scores as $key => $z ) {
                if ( abs( $L ) > PHP_FLOAT_EPSILON ) {
                    $value = $M * pow( 1 + $L * $S * $z, 1.0 / $L );
                } else {
                    $value = $M * exp( $S * $z );
                }

                $curves[ $key ][] = [
                    'age_days' => $age,
                    'value'    => round( $value, 3 ),
                ];
            }
        }

        return $curves;
    }

    /**
     * İki tarih arasındaki gün farkını hesapla
     */
    private function calculate_age_days( $birth_date, $measurement_date ) {
        $birth     = new \DateTime( $birth_date );
        $measured  = new \DateTime( $measurement_date );
        $diff      = $birth->diff( $measured );
        return max( 0, $diff->days );
    }

    /**
     * UUID v4 oluştur
     */
    private function generate_uuid() {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int( 0, 0xffff ), random_int( 0, 0xffff ),
            random_int( 0, 0xffff ),
            random_int( 0, 0x0fff ) | 0x4000,
            random_int( 0, 0x3fff ) | 0x8000,
            random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff )
        );
    }
}

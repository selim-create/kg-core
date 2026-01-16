<?php
namespace KG_Core\API;

class SponsoredToolController {

    /**
     * Average number of diapers per pack
     * Used for calculating monthly pack requirements
     */
    private const DIAPERS_PER_PACK = 50;

    /**
     * Air Quality Analysis Constants
     */
    private const DEFAULT_HOME_TYPE = 'apartment';
    private const DEFAULT_HEATING_TYPE = 'central';
    private const DEFAULT_VENTILATION = 'daily';
    private const DEFAULT_COOKING_FREQUENCY = 'medium';
    private const DEFAULT_HOME_RISK_SCORE = 15;
    private const DEFAULT_HEATING_RISK_SCORE = 15;
    private const MIN_RECOMMENDATIONS_COUNT = 3;
    
    private const VALID_HOME_TYPES = ['apartment', 'ground_floor', 'house', 'villa'];
    private const VALID_HEATING_TYPES = ['stove', 'natural_gas', 'central', 'electric', 'air_conditioner'];
    private const VALID_SEASONS = ['winter', 'spring', 'summer', 'autumn'];
    private const VALID_VENTILATION_FREQUENCIES = ['multiple_daily', 'daily', 'rarely'];
    private const VALID_COOKING_FREQUENCIES = ['high', 'medium', 'low'];

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Bath Planner endpoints
        register_rest_route( 'kg/v1', '/tools/bath-planner/config', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_bath_planner_config' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'kg/v1', '/tools/bath-planner/generate', [
            'methods'  => 'POST',
            'callback' => [ $this, 'generate_bath_routine' ],
            'permission_callback' => '__return_true',
        ]);

        // Hygiene Calculator endpoint
        register_rest_route( 'kg/v1', '/tools/hygiene-calculator/calculate', [
            'methods'  => 'POST',
            'callback' => [ $this, 'calculate_hygiene_needs' ],
            'permission_callback' => '__return_true',
        ]);

        // Diaper Calculator endpoints
        register_rest_route( 'kg/v1', '/tools/diaper-calculator/calculate', [
            'methods'  => 'POST',
            'callback' => [ $this, 'calculate_diaper_needs' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'kg/v1', '/tools/diaper-calculator/rash-risk', [
            'methods'  => 'POST',
            'callback' => [ $this, 'assess_rash_risk' ],
            'permission_callback' => '__return_true',
        ]);

        // Air Quality Guide endpoint
        register_rest_route( 'kg/v1', '/tools/air-quality/analyze', [
            'methods'  => 'POST',
            'callback' => [ $this, 'analyze_air_quality' ],
            'permission_callback' => '__return_true',
        ]);

        // Stain Encyclopedia endpoints
        register_rest_route( 'kg/v1', '/tools/stain-encyclopedia/search', [
            'methods'  => 'GET',
            'callback' => [ $this, 'search_stains' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'kg/v1', '/tools/stain-encyclopedia/popular', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_popular_stains' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'kg/v1', '/tools/stain-encyclopedia/(?P<slug>[a-zA-Z0-9_-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_stain_detail' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get bath planner configuration
     */
    public function get_bath_planner_config( $request ) {
        $tool = $this->get_tool_by_slug( 'bath-planner' );
        
        if ( is_wp_error( $tool ) ) {
            return $tool;
        }

        $sponsor_data = $this->get_sponsor_data( $tool->ID );

        $config = [
            'tool_info' => [
                'id' => $tool->ID,
                'title' => $tool->post_title,
                'description' => $tool->post_content,
                'icon' => get_post_meta( $tool->ID, '_kg_tool_icon', true ) ?: 'fa-bath',
            ],
            'skin_types' => [
                [
                    'id' => 'normal',
                    'label' => 'Normal Cilt',
                ],
                [
                    'id' => 'dry',
                    'label' => 'Kuru Cilt',
                ],
                [
                    'id' => 'sensitive',
                    'label' => 'Hassas Cilt',
                ],
                [
                    'id' => 'oily',
                    'label' => 'Yağlı Cilt',
                ],
            ],
            'seasons' => [
                [
                    'id' => 'spring',
                    'label' => 'İlkbahar',
                ],
                [
                    'id' => 'summer',
                    'label' => 'Yaz',
                ],
                [
                    'id' => 'autumn',
                    'label' => 'Sonbahar',
                ],
                [
                    'id' => 'winter',
                    'label' => 'Kış',
                ],
            ],
            'frequency_options' => [
                [
                    'id' => '2-3',
                    'label' => 'Haftada 2-3 kez',
                    'description' => 'Yenidoğanlar için önerilen',
                ],
                [
                    'id' => '3-4',
                    'label' => 'Haftada 3-4 kez',
                    'description' => '3-6 aylık bebekler için',
                ],
                [
                    'id' => '4-5',
                    'label' => 'Haftada 4-5 kez',
                    'description' => '6-12 aylık bebekler için',
                ],
                [
                    'id' => 'daily',
                    'label' => 'Her gün',
                    'description' => '12 ay üzeri için',
                ],
            ],
            'age_groups' => [
                [
                    'id' => '0-3months',
                    'label' => '0-3 Ay',
                    'frequency' => '2-3 kez/hafta',
                ],
                [
                    'id' => '3-6months',
                    'label' => '3-6 Ay',
                    'frequency' => '3-4 kez/hafta',
                ],
                [
                    'id' => '6-12months',
                    'label' => '6-12 Ay',
                    'frequency' => '4-5 kez/hafta',
                ],
                [
                    'id' => '12months+',
                    'label' => '12+ Ay',
                    'frequency' => 'Günlük',
                ],
            ],
            'bath_types' => [
                [
                    'id' => 'sponge',
                    'label' => 'Sünger Banyosu',
                    'suitable_for' => '0-3months',
                ],
                [
                    'id' => 'tub',
                    'label' => 'Küvet Banyosu',
                    'suitable_for' => '3months+',
                ],
                [
                    'id' => 'shower',
                    'label' => 'Duş',
                    'suitable_for' => '12months+',
                ],
            ],
            'sponsor' => $sponsor_data,
        ];

        return new \WP_REST_Response( $config, 200 );
    }

    /**
     * Generate bath routine plan
     */
    public function generate_bath_routine( $request ) {
        // Accept both baby_age_months and child_age_months for backwards compatibility
        $age_months = (int) $request->get_param( 'baby_age_months' );
        if ( ! $age_months ) {
            $age_months = (int) $request->get_param( 'child_age_months' );
        }
        
        $skin_type = $request->get_param( 'skin_type' ) ?: 'normal';
        
        // Accept both season and activity_level for backwards compatibility
        $season = $request->get_param( 'season' );
        if ( ! $season ) {
            // Map activity_level to season if season not provided
            $activity_level = $request->get_param( 'activity_level' ) ?: 'moderate';
            $season = 'spring'; // Default season
        }
        
        $has_eczema = (bool) $request->get_param( 'has_eczema' );

        if ( $age_months < 0 ) {
            return new \WP_Error( 'invalid_age', 'Geçerli bir yaş değeri giriniz', [ 'status' => 400 ] );
        }

        // Determine frequency based on age and season/activity
        $activity_level = $request->get_param( 'activity_level' ) ?: 'moderate';
        $frequency = $this->calculate_bath_frequency( $age_months, $activity_level );
        
        // Get appropriate products based on skin type
        $products = $this->get_bath_products( $skin_type, $age_months );

        // Generate routine steps
        $routine = $this->generate_routine_steps( $age_months, $skin_type );
        
        // Generate weekly schedule
        $weekly_schedule = $this->generate_weekly_schedule( $age_months, $season, $has_eczema );
        
        // Get warnings
        $warnings = $this->get_warnings( $skin_type, $season, $has_eczema );
        
        // Get product recommendations as string array
        $product_recommendations = $this->get_product_recommendations_list( $skin_type, $has_eczema );

        $tool = $this->get_tool_by_slug( 'bath-planner' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'recommended_frequency' => $frequency,
            'weekly_schedule' => $weekly_schedule,
            'tips' => $this->get_bath_tips( $age_months, $skin_type ),
            'warnings' => $warnings,
            'product_recommendations' => $product_recommendations,
            'products' => $products,
            'routine' => $routine,
            'sponsor' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Calculate hygiene needs
     */
    public function calculate_hygiene_needs( $request ) {
        // Parametre uyumluluğu - her iki adı da kabul et
        $baby_age_months = $request->get_param( 'baby_age_months' );
        if ( $baby_age_months === null ) {
            $baby_age_months = $request->get_param( 'child_age_months' );
        }
        $baby_age_months = (int) $baby_age_months;
        
        $daily_diaper_changes = $request->get_param( 'daily_diaper_changes' );
        $daily_diaper_changes = $daily_diaper_changes !== null ? (int) $daily_diaper_changes : 6;
        
        $outdoor_hours = $request->get_param( 'outdoor_hours' );
        $outdoor_hours = $outdoor_hours !== null ? (float) $outdoor_hours : 2;
        
        $meal_count = $request->get_param( 'meal_count' );
        $meal_count = $meal_count !== null ? (int) $meal_count : 3;

        // Validation
        if ( $baby_age_months < 0 || $baby_age_months > 36 ) {
            return new \WP_Error( 'invalid_age', 'Geçerli bir yaş değeri giriniz (0-36 ay)', [ 'status' => 400 ] );
        }

        // Mendil hesaplama mantığı
        $wipes_per_diaper_change = $this->get_wipes_per_diaper_change( $baby_age_months );
        $wipes_per_meal = $this->get_wipes_per_meal( $baby_age_months );
        $wipes_per_outdoor_hour = $this->get_wipes_per_outdoor_hour( $baby_age_months );
        
        // Günlük mendil ihtiyacı hesaplama
        $daily_wipes_needed = 
            ($daily_diaper_changes * $wipes_per_diaper_change) + 
            ($meal_count * $wipes_per_meal) + 
            ($outdoor_hours * $wipes_per_outdoor_hour);
        
        $daily_wipes_needed = (int) ceil( $daily_wipes_needed );
        $weekly_wipes_needed = $daily_wipes_needed * 7;
        $monthly_wipes_needed = $daily_wipes_needed * 30;

        // Sponsor data
        $tool = $this->get_tool_by_slug( 'hygiene-calculator' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        // Frontend'in beklediği formatta response
        $result = [
            'daily_wipes_needed' => $daily_wipes_needed,
            'weekly_wipes_needed' => $weekly_wipes_needed,
            'monthly_wipes_needed' => $monthly_wipes_needed,
            'recommendations' => $this->get_hygiene_recommendations_detailed( $baby_age_months, $daily_diaper_changes, $outdoor_hours, $meal_count ),
            'carry_bag_essentials' => $this->get_carry_bag_essentials( $baby_age_months, $outdoor_hours ),
            'sponsor' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Calculate diaper needs
     */
    public function calculate_diaper_needs( $request ) {
        // Backward compatibility - accept both parameter names
        $weight_kg = $request->get_param( 'baby_weight_kg' );
        if ( $weight_kg === null ) {
            $weight_kg = $request->get_param( 'weight_kg' );
        }
        $weight_kg = (float) $weight_kg;

        $age_months = $request->get_param( 'baby_age_months' );
        if ( $age_months === null ) {
            $age_months = $request->get_param( 'child_age_months' );
        }
        $age_months = (int) $age_months;

        $daily_changes = $request->get_param( 'daily_changes' );
        $feeding_type = $request->get_param( 'feeding_type' ) ?: 'mixed';

        if ( $age_months < 0 || $weight_kg <= 0 ) {
            return new \WP_Error( 'invalid_input', 'Geçerli yaş ve kilo değerleri giriniz', [ 'status' => 400 ] );
        }

        // Use daily_changes if provided, otherwise calculate based on age and feeding type
        if ( $daily_changes === null ) {
            $daily_changes = $this->calculate_daily_diapers( $age_months, $feeding_type );
        } else {
            $daily_changes = (int) $daily_changes;
        }

        $recommended_size = $this->get_diaper_size( $weight_kg, $age_months );

        $tool = $this->get_tool_by_slug( 'diaper-calculator' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'recommended_size' => $recommended_size,
            'size_range' => $this->get_size_weight_range( $weight_kg ),
            'daily_count' => $daily_changes,
            'monthly_count' => $daily_changes * 30,
            'monthly_packs' => $this->calculate_monthly_packs( $daily_changes ),
            'pack_type' => $this->get_recommended_pack_type( $daily_changes ),
            'size_change_alert' => $this->get_size_change_alert( $weight_kg, $age_months ),
            'tips' => $this->get_diaper_tips( $age_months ),
            'sponsor' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Assess diaper rash risk
     */
    public function assess_rash_risk( $request ) {
        // Check for legacy format (factors object)
        $factors = $request->get_param( 'factors' );
        
        if ( is_array( $factors ) && ! empty( $factors ) ) {
            // Use legacy format
            return $this->assess_rash_risk_legacy( $factors, $request );
        }
        
        // New format - direct parameters
        $change_frequency = $request->get_param( 'change_frequency' );
        $night_diaper_hours = $request->get_param( 'night_diaper_hours' );
        $humidity_level = $request->get_param( 'humidity_level' ) ?: 'normal';
        $has_diarrhea = (bool) $request->get_param( 'has_diarrhea' );

        // Default values if not provided
        if ( $change_frequency === null ) {
            $change_frequency = 3;
        } else {
            $change_frequency = (float) $change_frequency;
        }
        
        if ( $night_diaper_hours === null ) {
            $night_diaper_hours = 8;
        } else {
            $night_diaper_hours = (float) $night_diaper_hours;
        }

        return $this->calculate_rash_risk_new( $change_frequency, $night_diaper_hours, $humidity_level, $has_diarrhea, $request );
    }

    /**
     * Legacy rash risk assessment (for backward compatibility)
     */
    private function assess_rash_risk_legacy( $factors, $request ) {
        $risk_score = 0;
        $risk_factors = [];

        // Assess risk factors
        if ( isset( $factors['change_frequency'] ) && $factors['change_frequency'] === 'infrequent' ) {
            $risk_score += 30;
            $risk_factors[] = 'Bezler yeterince sık değiştirilmiyor';
        }

        if ( isset( $factors['skin_type'] ) && $factors['skin_type'] === 'sensitive' ) {
            $risk_score += 20;
            $risk_factors[] = 'Hassas cilt';
        }

        if ( isset( $factors['recent_antibiotics'] ) && $factors['recent_antibiotics'] === true ) {
            $risk_score += 25;
            $risk_factors[] = 'Son zamanlarda antibiyotik kullanımı';
        }

        if ( isset( $factors['diet_change'] ) && $factors['diet_change'] === true ) {
            $risk_score += 15;
            $risk_factors[] = 'Diyet değişikliği';
        }

        if ( isset( $factors['diarrhea'] ) && $factors['diarrhea'] === true ) {
            $risk_score += 35;
            $risk_factors[] = 'İshal';
        }

        // Determine risk level
        $risk_level = 'low';
        if ( $risk_score >= 60 ) {
            $risk_level = 'high';
        } elseif ( $risk_score >= 30 ) {
            $risk_level = 'medium';
        }

        $tool = $this->get_tool_by_slug( 'diaper-calculator' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'risk_level' => $risk_level,
            'risk_score' => $risk_score,
            'risk_factors' => $risk_factors,
            'prevention_tips' => $this->get_rash_prevention_tips( $risk_level ),
            'sponsor' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * New rash risk calculation based on direct parameters
     */
    private function calculate_rash_risk_new( $change_frequency, $night_diaper_hours, $humidity_level, $has_diarrhea, $request ) {
        $risk_score = 0;
        $risk_factors = [];

        // Bez değişim sıklığı (saat cinsinden)
        if ( $change_frequency >= 5 ) {
            $risk_score += 35;
            $risk_factors[] = 'Bez değişim aralığı çok uzun (5+ saat)';
        } elseif ( $change_frequency >= 4 ) {
            $risk_score += 20;
            $risk_factors[] = 'Bez değişim aralığı uzun (4+ saat)';
        }

        // Gece bezi kullanım süresi
        if ( $night_diaper_hours >= 12 ) {
            $risk_score += 30;
            $risk_factors[] = 'Gece bezi çok uzun süre kalıyor (12+ saat)';
        } elseif ( $night_diaper_hours >= 10 ) {
            $risk_score += 15;
            $risk_factors[] = 'Gece bezi uzun süre kalıyor (10+ saat)';
        }

        // Nem seviyesi
        if ( $humidity_level === 'high' ) {
            $risk_score += 25;
            $risk_factors[] = 'Ortam nemi yüksek';
        }

        // İshal
        if ( $has_diarrhea ) {
            $risk_score += 40;
            $risk_factors[] = 'Aktif ishal durumu mevcut';
        }

        // Risk seviyesi belirleme
        $risk_level = 'low';
        if ( $risk_score >= 60 ) {
            $risk_level = 'high';
        } elseif ( $risk_score >= 30 ) {
            $risk_level = 'medium';
        }

        $tool = $this->get_tool_by_slug( 'diaper-calculator' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'risk_level' => $risk_level,
            'risk_score' => $risk_score,
            'risk_factors' => $risk_factors,
            'prevention_tips' => $this->get_rash_prevention_tips( $risk_level ),
            'sponsor' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Analyze air quality
     */
    public function analyze_air_quality( $request ) {
        // Çocuk bilgileri
        $child_age_months = (int) $request->get_param( 'child_age_months' );
        $has_newborn = (bool) $request->get_param( 'has_newborn' );
        if ( ! $has_newborn && $child_age_months > 0 && $child_age_months < 3 ) {
            $has_newborn = true;
        }
        $has_respiratory_issues = (bool) $request->get_param( 'respiratory_issues' );
        
        // Ev ortamı parametreleri (Frontend'in gönderdiği) with validation
        $home_type = sanitize_text_field( $request->get_param( 'home_type' ) );
        if ( ! in_array( $home_type, self::VALID_HOME_TYPES, true ) ) {
            $home_type = self::DEFAULT_HOME_TYPE;
        }
        
        $heating_type = sanitize_text_field( $request->get_param( 'heating_type' ) );
        if ( ! in_array( $heating_type, self::VALID_HEATING_TYPES, true ) ) {
            $heating_type = self::DEFAULT_HEATING_TYPE;
        }
        
        $has_pets = (bool) $request->get_param( 'has_pets' );
        $has_smoker = (bool) $request->get_param( 'has_smoker' );
        
        $season = sanitize_text_field( $request->get_param( 'season' ) );
        if ( ! in_array( $season, self::VALID_SEASONS, true ) ) {
            $season = $this->get_current_season();
        }
        
        // Ek parametreler (opsiyonel) with validation
        $ventilation_frequency = sanitize_text_field( $request->get_param( 'ventilation_frequency' ) );
        if ( ! in_array( $ventilation_frequency, self::VALID_VENTILATION_FREQUENCIES, true ) ) {
            $ventilation_frequency = self::DEFAULT_VENTILATION;
        }
        
        $cooking_frequency = sanitize_text_field( $request->get_param( 'cooking_frequency' ) );
        if ( ! in_array( $cooking_frequency, self::VALID_COOKING_FREQUENCIES, true ) ) {
            $cooking_frequency = self::DEFAULT_COOKING_FREQUENCY;
        }
        
        // Opsiyonel: Dış mekan AQI (geriye dönük uyumluluk)
        $external_aqi = $request->get_param( 'aqi' );
        
        // İç mekan risk skoru hesapla
        $indoor_risk = $this->calculate_indoor_air_risk(
            $home_type, $heating_type, $has_pets, $has_smoker,
            $season, $ventilation_frequency, $cooking_frequency,
            $has_newborn, $has_respiratory_issues
        );
        
        // Risk faktörlerini topla
        $risk_factors = $this->get_indoor_risk_factors(
            $home_type, $heating_type, $has_pets, $has_smoker,
            $season, $ventilation_frequency, $cooking_frequency
        );
        
        // Çocuk yaşına ve duruma göre öneriler
        $recommendations = $this->get_child_air_quality_recommendations(
            $child_age_months, $indoor_risk['risk_level'],
            $has_respiratory_issues, $season, $has_pets, $has_smoker
        );
        
        // Mevsimsel uyarılar
        $seasonal_alerts = $this->get_air_quality_seasonal_alerts(
            $season, $child_age_months, $has_respiratory_issues, $heating_type
        );
        
        $tool = $this->get_tool_by_slug( 'hava-kalitesi' );
        if ( is_wp_error( $tool ) ) {
            $tool = $this->get_tool_by_slug( 'air-quality' );
        }
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;
        
        $result = [
            'risk_level' => $indoor_risk['risk_level'],
            'risk_score' => $indoor_risk['score'],
            'risk_factors' => $risk_factors,
            'recommendations' => $recommendations,
            'seasonal_alerts' => $seasonal_alerts,
            'indoor_tips' => $this->get_indoor_air_tips(),
            'sponsor' => $sponsor_data,
        ];
        
        // Geriye dönük uyumluluk: Eğer AQI gönderildiyse dış mekan verilerini de ekle
        if ( $external_aqi !== null && $external_aqi !== '' ) {
            $aqi = (int) $external_aqi;
            if ( $aqi >= 0 && $aqi <= 500 ) {
                $result['external_aqi'] = [
                    'aqi' => $aqi,
                    'quality_level' => $this->get_air_quality_level( $aqi ),
                    'is_safe_for_outdoor' => $this->is_safe_outdoor( $aqi, $has_newborn, $has_respiratory_issues ),
                ];
            }
        }
        
        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Search stain encyclopedia
     */
    public function search_stains( $request ) {
        $query = $request->get_param( 'q' );
        $category = $request->get_param( 'category' );

        // For now, return mock data. In a real implementation, this would query a stain database
        $stains = $this->get_stain_database();

        // Filter by query with Turkish character normalization
        if ( ! empty( $query ) ) {
            $normalized_query = $this->normalize_turkish( $query );
            $stains = array_filter( $stains, function( $stain ) use ( $query, $normalized_query ) {
                $normalized_name = $this->normalize_turkish( $stain['name'] );
                
                return stripos( $stain['name'], $query ) !== false || 
                       stripos( $normalized_name, $normalized_query ) !== false;
            });
        }

        // Filter by category
        if ( ! empty( $category ) ) {
            $stains = array_filter( $stains, function( $stain ) use ( $category ) {
                return $stain['category'] === $category;
            });
        }

        $tool = $this->get_tool_by_slug( 'stain-encyclopedia' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'total' => count( $stains ),
            'stains' => array_values( $stains ),
            'categories' => $this->get_stain_categories(),
            'sponsor' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Get popular stains
     */
    public function get_popular_stains( $request ) {
        // Popular stains as defined in frontend
        $popular_slugs = [
            'domates-lekesi',
            'cikolata-lekesi',
            'muz-lekesi',
            'havuc-lekesi',
            'cim-lekesi',
            'kaka-lekesi',
            'kusmuk-lekesi',
            'anne-sutu-lekesi',
        ];

        $all_stains = $this->get_stain_database();
        $popular_stains = [];

        foreach ( $all_stains as $stain ) {
            if ( in_array( $stain['slug'], $popular_slugs ) ) {
                $popular_stains[] = [
                    'slug' => $stain['slug'],
                    'name' => $stain['name'],
                    'emoji' => $stain['emoji'],
                ];
            }
        }

        $result = [
            'stains' => $popular_stains,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Get stain detail
     */
    public function get_stain_detail( $request ) {
        $slug = $request->get_param( 'slug' );
        
        $stains = $this->get_stain_database();
        $stain = null;

        foreach ( $stains as $s ) {
            if ( $s['slug'] === $slug ) {
                $stain = $s;
                break;
            }
        }

        if ( ! $stain ) {
            return new \WP_Error( 'stain_not_found', 'Leke bulunamadı', [ 'status' => 404 ] );
        }

        $tool = $this->get_tool_by_slug( 'stain-encyclopedia' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $stain['sponsor'] = $sponsor_data;

        return new \WP_REST_Response( $stain, 200 );
    }

    // Helper methods

    private function get_tool_by_slug( $slug ) {
        $args = [
            'post_type' => 'tool',
            'name' => $slug,
            'post_status' => 'publish',
            'posts_per_page' => 1,
        ];

        $tools = get_posts( $args );

        if ( empty( $tools ) ) {
            return new \WP_Error( 'tool_not_found', 'Araç bulunamadı', [ 'status' => 404 ] );
        }

        return $tools[0];
    }

    private function get_sponsor_data( $tool_id ) {
        $is_sponsored = get_post_meta( $tool_id, '_kg_tool_is_sponsored', true );
        
        if ( $is_sponsored !== '1' ) {
            return null;
        }

        $sponsor_logo_id = get_post_meta( $tool_id, '_kg_tool_sponsor_logo', true );
        $sponsor_light_logo_id = get_post_meta( $tool_id, '_kg_tool_sponsor_light_logo', true );
        $gam_impression = get_post_meta( $tool_id, '_kg_tool_gam_impression_url', true );
        $gam_click = get_post_meta( $tool_id, '_kg_tool_gam_click_url', true );

        return [
            'is_sponsored' => true,
            'sponsor_name' => get_post_meta( $tool_id, '_kg_tool_sponsor_name', true ),
            'sponsor_logo' => $sponsor_logo_id ? wp_get_attachment_url( $sponsor_logo_id ) : null,
            'sponsor_light_logo' => $sponsor_light_logo_id ? wp_get_attachment_url( $sponsor_light_logo_id ) : null,
            'sponsor_tagline' => get_post_meta( $tool_id, '_kg_tool_sponsor_tagline', true ),
            'sponsor_cta_text' => get_post_meta( $tool_id, '_kg_tool_sponsor_cta_text', true ),
            'sponsor_cta_url' => get_post_meta( $tool_id, '_kg_tool_sponsor_cta_url', true ),
            'gam_impression_url' => $gam_impression ?: null,
            'gam_click_url' => $gam_click ?: null,
        ];
    }

    private function calculate_bath_frequency( $age_months, $activity_level ) {
        if ( $age_months < 3 ) {
            return '2-3 kez/hafta';
        } elseif ( $age_months < 6 ) {
            return '3-4 kez/hafta';
        } elseif ( $age_months < 12 ) {
            return $activity_level === 'high' ? 'Günlük' : '4-5 kez/hafta';
        } else {
            return 'Günlük';
        }
    }

    private function get_bath_products( $skin_type, $age_months ) {
        $products = [
            [
                'type' => 'Şampuan',
                'recommendation' => $skin_type === 'sensitive' ? 'Parfümsüz, hipoalerjenik bebek şampuanı' : 'Hafif bebek şampuanı',
            ],
            [
                'type' => 'Vücut Yıkama',
                'recommendation' => $skin_type === 'dry' ? 'Nemlendirici içeren bebek duş jeli' : 'Hafif bebek duş jeli',
            ],
        ];

        if ( $skin_type === 'dry' || $skin_type === 'sensitive' ) {
            $products[] = [
                'type' => 'Nemlendirici',
                'recommendation' => 'Parfümsüz bebek losyonu veya krem',
            ];
        }

        return $products;
    }

    private function generate_routine_steps( $age_months, $skin_type ) {
        return [
            [
                'step' => 1,
                'title' => 'Hazırlık',
                'description' => 'Su sıcaklığını kontrol edin (36-37°C), tüm malzemeleri hazırlayın',
            ],
            [
                'step' => 2,
                'title' => 'Yıkama',
                'description' => 'Yumuşak hareketlerle yıkayın, gözlere dikkat edin',
            ],
            [
                'step' => 3,
                'title' => 'Durulama',
                'description' => 'Ürün kalıntılarını iyice durulayın',
            ],
            [
                'step' => 4,
                'title' => 'Kurutma',
                'description' => 'Yumuşak havlu ile hafifçe kurutucu hareketlerle kurulayın',
            ],
            [
                'step' => 5,
                'title' => 'Bakım',
                'description' => $skin_type === 'dry' ? 'Nemlendirici uygulayın' : 'Gerekirse nemlendirici uygulayın',
            ],
        ];
    }

    private function get_bath_tips( $age_months, $skin_type ) {
        $tips = [
            'Bebeği banyoda asla yalnız bırakmayın',
            'Su sıcaklığını her zaman dirsekle test edin',
            'Sabun kullanımını minimumda tutun',
        ];

        if ( $skin_type === 'sensitive' ) {
            $tips[] = 'Parfümlü ürünlerden kaçının';
        }

        if ( $age_months < 3 ) {
            $tips[] = 'Göbek kordonunun düşmesine kadar sünger banyosu tercih edin';
        }

        return $tips;
    }

    private function calculate_daily_diapers( $age_months, $feeding_type = 'mixed' ) {
        if ( $age_months < 1 ) {
            return $feeding_type === 'breast' ? 10 : 8;
        } elseif ( $age_months < 3 ) {
            return 8;
        } elseif ( $age_months < 6 ) {
            return 6;
        } elseif ( $age_months < 12 ) {
            return 5;
        } else {
            return 4;
        }
    }

    private function calculate_daily_wipes( $age_months, $lifestyle ) {
        $base = $this->calculate_daily_diapers( $age_months ) * 3;
        
        if ( $lifestyle === 'active' ) {
            $base += 5;
        }

        return $base;
    }

    private function calculate_bath_products( $age_months ) {
        return [
            'shampoo' => '200ml aylık',
            'body_wash' => '250ml aylık',
            'lotion' => '200ml aylık',
        ];
    }

    private function calculate_laundry( $age_months ) {
        if ( $age_months < 3 ) {
            return 2; // per day
        } elseif ( $age_months < 6 ) {
            return 1.5;
        } else {
            return 1;
        }
    }

    private function calculate_estimated_cost( $monthly_needs ) {
        // Basic cost estimation - can be made more sophisticated
        return [
            'diapers' => $monthly_needs['diapers'] * 1.5 . ' TL',
            'wipes' => $monthly_needs['wipes'] * 0.5 . ' TL',
            'total_estimated' => ( $monthly_needs['diapers'] * 1.5 + $monthly_needs['wipes'] * 0.5 ) . ' TL',
        ];
    }

    private function get_hygiene_recommendations( $age_months ) {
        return [
            'Bebek bezini her 2-3 saatte bir kontrol edin',
            'Islak mendil yerine su ve pamuk tercih edebilirsiniz',
            'Kıyafetleri bebek deterjanı ile yıkayın',
        ];
    }

    private function get_diaper_size( $weight_kg, $age_months ) {
        if ( $weight_kg < 4 ) {
            return '0 (Yenidoğan)';
        } elseif ( $weight_kg < 6 ) {
            return '1 (Mini)';
        } elseif ( $weight_kg < 9 ) {
            return '2 (Midi)';
        } elseif ( $weight_kg < 12 ) {
            return '3 (Maxi)';
        } elseif ( $weight_kg < 16 ) {
            return '4 (Maxi+)';
        } else {
            return '5 (Junior)';
        }
    }

    private function get_change_frequency( $age_months ) {
        if ( $age_months < 3 ) {
            return 'Her 2-3 saatte bir veya kirlendiğinde';
        } elseif ( $age_months < 12 ) {
            return 'Her 3-4 saatte bir veya kirlendiğinde';
        } else {
            return 'Her 4-5 saatte bir veya kirlendiğinde';
        }
    }

    private function get_diaper_tips( $age_months ) {
        return [
            'Bez değiştirirken her seferinde temizleyin',
            'Kırmızılık için düzenli kontrol yapın',
            'Gece için özel gece bezi kullanabilirsiniz',
            'Boyut geçişini kilo ve bebek konforuna göre yapın',
        ];
    }

    private function get_rash_prevention_tips( $risk_level ) {
        $tips = [
            'Bezleri sık değiştirin',
            'Her bez değişiminde iyice temizleyin ve kurulayın',
            'Hava alsın - günde birkaç kez bezsiz vakit geçirin',
        ];

        if ( $risk_level === 'high' || $risk_level === 'moderate' ) {
            $tips[] = 'Bariyer krem kullanın';
            $tips[] = 'Parfümlü ıslak mendillerden kaçının';
        }

        return $tips;
    }

    private function get_rash_treatment( $risk_level ) {
        if ( $risk_level === 'high' ) {
            return [
                'Çinko oksit içeren pişik kremi uygulayın',
                'Bebeği daha sık temizleyin ve kurulayın',
                'Bez markasını değiştirmeyi deneyin',
                '48 saat içinde iyileşme olmazsa doktora başvurun',
            ];
        } elseif ( $risk_level === 'moderate' ) {
            return [
                'Hafif bariyer krem kullanın',
                'Bez değişim sıklığını artırın',
                'Durumu izleyin',
            ];
        } else {
            return [
                'Normal rutin bakımınıza devam edin',
                'Önleyici bakım için bariyer krem kullanabilirsiniz',
            ];
        }
    }

    private function get_air_quality_level( $aqi ) {
        if ( $aqi <= 50 ) {
            return [
                'level' => 'İyi',
                'color' => 'green',
                'description' => 'Hava kalitesi tatmin edici',
            ];
        } elseif ( $aqi <= 100 ) {
            return [
                'level' => 'Orta',
                'color' => 'yellow',
                'description' => 'Hassas gruplar için kabul edilebilir',
            ];
        } elseif ( $aqi <= 150 ) {
            return [
                'level' => 'Hassas Gruplar İçin Sağlıksız',
                'color' => 'orange',
                'description' => 'Hassas gruplar etkilenebilir',
            ];
        } elseif ( $aqi <= 200 ) {
            return [
                'level' => 'Sağlıksız',
                'color' => 'red',
                'description' => 'Herkes etkilenmeye başlayabilir',
            ];
        } elseif ( $aqi <= 300 ) {
            return [
                'level' => 'Çok Sağlıksız',
                'color' => 'purple',
                'description' => 'Sağlık uyarısı',
            ];
        } else {
            return [
                'level' => 'Tehlikeli',
                'color' => 'maroon',
                'description' => 'Acil sağlık uyarısı',
            ];
        }
    }

    private function get_air_quality_recommendations( $aqi, $has_newborn, $respiratory_issues ) {
        $recommendations = [];

        if ( $aqi > 100 || ( $has_newborn && $aqi > 50 ) || ( $respiratory_issues && $aqi > 50 ) ) {
            $recommendations[] = 'Dış mekan aktivitelerini sınırlayın';
            $recommendations[] = 'Pencereleri kapalı tutun';
            $recommendations[] = 'Hava temizleyici kullanın';
        }

        if ( $aqi > 150 ) {
            $recommendations[] = 'Dışarı çıkmayın';
            $recommendations[] = 'İç mekanda fiziksel aktiviteyi azaltın';
        }

        if ( $respiratory_issues && $aqi > 100 ) {
            $recommendations[] = 'Doktorunuza danışın';
        }

        if ( empty( $recommendations ) ) {
            $recommendations[] = 'Normal aktivitelerinize devam edebilirsiniz';
            $recommendations[] = 'Dış mekan aktiviteleri için güvenli';
        }

        return $recommendations;
    }

    private function is_safe_outdoor( $aqi, $has_newborn, $respiratory_issues ) {
        if ( $respiratory_issues && $aqi > 100 ) {
            return false;
        }
        
        if ( $has_newborn && $aqi > 150 ) {
            return false;
        }

        if ( $aqi > 200 ) {
            return false;
        }

        return true;
    }

    private function get_indoor_air_tips() {
        return [
            'Günde en az 2-3 kez 10-15 dakika havalandırma yapın',
            'Hava temizleyici kullanın (HEPA filtreli tercih edin)',
            'İç mekanda sigara içilmemesini sağlayın',
            'Ev bitkileri hava kalitesini doğal yoldan iyileştirir',
            'Nem oranını %40-60 arasında tutun',
            'Kimyasal temizlik ürünleri yerine doğal alternatifler tercih edin',
            'Halı ve tekstil ürünlerini düzenli temizleyin',
            'Yatak ve yastıkları düzenli havalandırın',
            'Mutfakta aspiratör kullanmayı unutmayın',
            'Banyo ve nemli alanları iyi havalandırın',
        ];
    }

    private function get_stain_database() {
        // Comprehensive stain database with 40+ stains
        return [
            // FOOD STAINS (20 stains)
            [
                'id' => 1,
                'slug' => 'domates-lekesi',
                'name' => 'Domates Lekesi',
                'emoji' => '🍅',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla domatesi hemen kazıyarak temizleyin.',
                        'tip' => 'Lekeyi ovuşturmayın, daha fazla yayılmasına neden olur.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Lekeyi ters taraftan soğuk su ile durulayın.',
                        'tip' => 'Sıcak su lekeyi sabitler, mutlaka soğuk su kullanın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bulaşık deterjanı veya sıvı çamaşır deterjanı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15-30 dakika bekletin, sonra normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su veya kurutucu kullanmayın, leke kalıcı hale gelir.',
                    'Beyaz kumaşlarda limon suyu dikkatli kullanılmalıdır.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Beyaz sirke',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 2,
                'slug' => 'cikolata-lekesi',
                'name' => 'Çikolata Lekesi',
                'emoji' => '🍫',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla çikolatayı plastik bir kaşıkla kazıyın.',
                        'tip' => 'Metal kullanmayın, kumaşa zarar verebilir.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla lekeyi durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan veya leke çıkarıcı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Yağ içerdiği için tam çıkmayabilir, ısrarcı olun.',
                    'İlk yıkamada çıkmazsa tekrarlayın, kurutucuya atmayın.',
                ],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                    'Bulaşık deterjanı',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 3,
                'slug' => 'muz-lekesi',
                'name' => 'Muz Lekesi',
                'emoji' => '🍌',
                'category' => 'food',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla muzu hemen kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Limon suyu veya beyaz sirke uygulayın.',
                        'tip' => 'Muz okside olarak kararır, asit yardımcı olur.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '10-15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Leke çıkarıcı ile yıkayın ve güneşte kurutun.',
                        'tip' => 'Güneş ışığı doğal ağartıcı görevi görür.',
                    ],
                ],
                'warnings' => [
                    'Muz lekeleri zamanla koyulaşır, hemen müdahale edin.',
                    'Tamamen kurumuş muz lekeleri çıkması çok zordur.',
                ],
                'related_ingredients' => [
                    'Limon suyu',
                    'Beyaz sirke',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 4,
                'slug' => 'havuc-lekesi',
                'name' => 'Havuç Lekesi',
                'emoji' => '🥕',
                'category' => 'food',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla havucu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla lekeyi durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Gliserin veya sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Oksijenli leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Beta-karoten içerir, çıkması zor olabilir.',
                    'Birden fazla yıkama gerekebilir.',
                ],
                'related_ingredients' => [
                    'Gliserin',
                    'Sıvı deterjan',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 5,
                'slug' => 'mama-lekesi',
                'name' => 'Mama Lekesi',
                'emoji' => '🥣',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla mamayı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bebek deterjanı ile ön yıkama yapın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sebze mamaları renk bırakabilir.',
                ],
                'related_ingredients' => [
                    'Bebek deterjanı',
                    'Leke çıkarıcı sprey',
                ],
            ],
            [
                'id' => 6,
                'slug' => 'sut-lekesi',
                'name' => 'Süt Lekesi',
                'emoji' => '🥛',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla hemen durulayın.',
                        'tip' => 'Sıcak su protein pıhtılaşmasına neden olur.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Mutlaka soğuk su kullanın.',
                    'Süt lekeleri zamanla koku yapar, hemen temizleyin.',
                ],
                'related_ingredients' => [
                    'Sıvı deterjan',
                    'Bebek deterjanı',
                ],
            ],
            [
                'id' => 7,
                'slug' => 'yumurta-lekesi',
                'name' => 'Yumurta Lekesi',
                'emoji' => '🥚',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla yumurtayı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                        'tip' => 'Sıcak su proteini pıhtılaştırır.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Amonyak solüsyonu veya enzimli deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin ve normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Asla sıcak su kullanmayın.',
                ],
                'related_ingredients' => [
                    'Enzimli deterjan',
                    'Amonyak',
                ],
            ],
            [
                'id' => 8,
                'slug' => 'bal-lekesi',
                'name' => 'Bal Lekesi',
                'emoji' => '🍯',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla balı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Ilık suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayıp ovalayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Yapışkandır, hemen temizleyin.',
                ],
                'related_ingredients' => [
                    'Sıvı deterjan',
                ],
            ],
            [
                'id' => 9,
                'slug' => 'yogurt-lekesi',
                'name' => 'Yoğurt Lekesi',
                'emoji' => '🥛',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla yoğurdu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Enzimli deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Soğuk su kullanın.',
                ],
                'related_ingredients' => [
                    'Enzimli deterjan',
                    'Bebek deterjanı',
                ],
            ],
            [
                'id' => 10,
                'slug' => 'kirmizi-meyve-lekesi',
                'name' => 'Kırmızı Meyve Lekesi',
                'emoji' => '🍓',
                'category' => 'food',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla meyveyi temizleyin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Kaynar su dökmeyin, sabitler.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Limon suyu veya sirke uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekleyin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Soğuk suyla durulayın ve leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Tanin içerir, çıkması zordur.',
                    'Hemen müdahale edin.',
                ],
                'related_ingredients' => [
                    'Limon suyu',
                    'Beyaz sirke',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 11,
                'slug' => 'uzum-suyu-lekesi',
                'name' => 'Üzüm Suyu Lekesi',
                'emoji' => '🍇',
                'category' => 'food',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Lekeyi hemen emici bir bezle silin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla bolca durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Beyaz sirke veya limon suyu uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '20 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Oksijenli leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Koyu renkli meyve suları kalıcıdır.',
                    'Erken müdahale kritiktir.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Limon suyu',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 12,
                'slug' => 'ispanak-lekesi',
                'name' => 'Ispanak Lekesi',
                'emoji' => '🥬',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla ıspanağı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan veya gliserin uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin ve yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Yeşil pigment kalıcı olabilir.',
                ],
                'related_ingredients' => [
                    'Gliserin',
                    'Sıvı deterjan',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 13,
                'slug' => 'bezelye-lekesi',
                'name' => 'Bezelye Lekesi',
                'emoji' => '🫛',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla bezelyeyi temizleyin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Sıvı deterjan',
                    'Bebek deterjanı',
                ],
            ],
            [
                'id' => 14,
                'slug' => 'kabak-lekesi',
                'name' => 'Kabak Lekesi',
                'emoji' => '🎃',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla kabağı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bebek deterjanı ile yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Bebek deterjanı',
                    'Sıvı deterjan',
                ],
            ],
            [
                'id' => 15,
                'slug' => 'patates-lekesi',
                'name' => 'Patates Lekesi',
                'emoji' => '🥔',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla patates püresini kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan ile yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Sıvı deterjan',
                ],
            ],
            [
                'id' => 16,
                'slug' => 'yag-lekesi',
                'name' => 'Yağ Lekesi',
                'emoji' => '🫒',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla yağı emici kağıtla silin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Talk pudrası veya mısır nişastası serpin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '30 dakika bekleyin ve tozu fırçalayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Bulaşık deterjanı uygulayıp ovalayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Sıcak suyla yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Kurutmadan önce lekenin çıktığından emin olun.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Talk pudrası',
                    'Mısır nişastası',
                ],
            ],
            [
                'id' => 17,
                'slug' => 'ketcap-lekesi',
                'name' => 'Ketçap Lekesi',
                'emoji' => '🍅',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla ketçabı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Beyaz sirke uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekleyin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su kullanmayın.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 18,
                'slug' => 'zerdecal-lekesi',
                'name' => 'Zerdeçal/Curry Lekesi',
                'emoji' => '🟡',
                'category' => 'food',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla baharatı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Gliserin veya alkol uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '1 saat bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Güneşte 2-3 saat bekletin.',
                        'tip' => 'Güneş ışığı zerdeçal pigmentini parçalar.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Oksijenli leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'En zor lekelerden biridir.',
                    'Birden fazla işlem gerekebilir.',
                ],
                'related_ingredients' => [
                    'Gliserin',
                    'Alkol',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 19,
                'slug' => 'nar-lekesi',
                'name' => 'Nar Lekesi',
                'emoji' => '🍒',
                'category' => 'food',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Lekeyi hemen emici bezle silin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla bol miktarda durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Beyaz sirke veya limon suyu uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Oksijenli leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Nar suyu çok kalıcıdır.',
                    'Anında müdahale şarttır.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Limon suyu',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 20,
                'slug' => 'avokado-lekesi',
                'name' => 'Avokado Lekesi',
                'emoji' => '🥑',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla avokadoyu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Yağlı olabilir, gerekirse tekrarlayın.',
                ],
                'related_ingredients' => [
                    'Sıvı deterjan',
                    'Bulaşık deterjanı',
                ],
            ],

            // BODILY FLUID STAINS (8 stains)
            [
                'id' => 21,
                'slug' => 'kaka-lekesi',
                'name' => 'Kaka Lekesi',
                'emoji' => '💩',
                'category' => 'bodily',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Katı kısmı plastik kaşık veya spatula ile kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla bol miktarda durulayın.',
                        'tip' => 'Sıcak su protein pıhtılaşmasına neden olur.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Oksijen bazlı leke çıkarıcı veya enzimli deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '1-2 saat bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => '60°C\'de yıkayın.',
                    ],
                    [
                        'step' => 6,
                        'instruction' => 'Güneşte kurutun.',
                        'tip' => 'Güneş ışığı doğal ağartıcı ve dezenfektan görevi görür.',
                    ],
                ],
                'warnings' => [
                    'Asla sıcak suyla başlamayın, leke sabitlenir.',
                    'Kuru temizlemeye vermeyin, profesyonel temizlik gerekebilir.',
                ],
                'related_ingredients' => [
                    'Oksijen bazlı leke çıkarıcı',
                    'Enzimli bebek deterjanı',
                    'Karbonat',
                ],
            ],
            [
                'id' => 22,
                'slug' => 'kusmuk-lekesi',
                'name' => 'Kusmuk Lekesi',
                'emoji' => '🤮',
                'category' => 'bodily',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Katı kısmı dikkatlice kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Karbonat serperek kokuyu nötralize edin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekleyin ve karbonatı vakumlayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Enzimli deterjan uygulayın ve 30 dakika bekletin.',
                    ],
                    [
                        'step' => 6,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Koku kalıcı olabilir, iyi havalandırın.',
                    'Soğuk su kullanın.',
                ],
                'related_ingredients' => [
                    'Karbonat',
                    'Enzimli deterjan',
                    'Beyaz sirke',
                ],
            ],
            [
                'id' => 23,
                'slug' => 'anne-sutu-lekesi',
                'name' => 'Anne Sütü Lekesi',
                'emoji' => '🍼',
                'category' => 'bodily',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla hemen durulayın.',
                        'tip' => 'Sıcak su protein pıhtılaşmasına neden olur.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Bebek deterjanı uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Hafifçe ovalayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Mutlaka soğuk su kullanın.',
                    'Zamanla koku ve renk değişimi olabilir, hemen temizleyin.',
                ],
                'related_ingredients' => [
                    'Bebek deterjanı',
                    'Enzimli deterjan',
                ],
            ],
            [
                'id' => 24,
                'slug' => 'tukuruk-lekesi',
                'name' => 'Tükürük Lekesi',
                'emoji' => '💧',
                'category' => 'bodily',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Bebek deterjanı',
                    'Sıvı deterjan',
                ],
            ],
            [
                'id' => 25,
                'slug' => 'idrar-lekesi',
                'name' => 'İdrar Lekesi',
                'emoji' => '💧',
                'category' => 'bodily',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla bol miktarda durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Beyaz sirke ve su karışımı (1:2) uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Enzimli deterjan ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Koku kalıcı olabilir, sirke kullanımı önemli.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Enzimli deterjan',
                    'Karbonat',
                ],
            ],
            [
                'id' => 26,
                'slug' => 'kan-lekesi',
                'name' => 'Kan Lekesi',
                'emoji' => '🩸',
                'category' => 'bodily',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla hemen durulayın.',
                        'tip' => 'Sıcak su kanı pıhtılaştırır ve çıkması imkansız hale gelir.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Tuzlu soğuk su içinde bekletin (30 dakika).',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Oksijenli su veya hidrojen peroksit uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Köpürme bitince durulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Kesinlikle sıcak su kullanmayın.',
                    'Kurutmadan önce lekenin çıktığından emin olun.',
                ],
                'related_ingredients' => [
                    'Tuz',
                    'Hidrojen peroksit',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 27,
                'slug' => 'ter-lekesi',
                'name' => 'Ter Lekesi',
                'emoji' => '💦',
                'category' => 'bodily',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Beyaz sirke uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sararmış ter lekeleri için limon suyu ve güneş yardımcı olur.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Limon suyu',
                ],
            ],
            [
                'id' => 28,
                'slug' => 'goz-yasi-lekesi',
                'name' => 'Gözyaşı Lekesi',
                'emoji' => '😢',
                'category' => 'bodily',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Bebek deterjanı',
                ],
            ],

            // OUTDOOR STAINS (4 stains)
            [
                'id' => 29,
                'slug' => 'cim-lekesi',
                'name' => 'Çim Lekesi',
                'emoji' => '🌿',
                'category' => 'outdoor',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Kuru fırça ile fazla çim kalıntılarını temizleyin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Alkol veya beyaz sirke uygulayın.',
                        'tip' => 'Alkol klorofili çözer.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Enzimli deterjan ile ovalayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Sıcak suyla yıkayın.',
                    ],
                ],
                'warnings' => [
                    'En zor lekelerden biridir.',
                    'Birden fazla işlem gerekebilir.',
                ],
                'related_ingredients' => [
                    'Alkol',
                    'Beyaz sirke',
                    'Enzimli deterjan',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 30,
                'slug' => 'toprak-lekesi',
                'name' => 'Toprak/Çamur Lekesi',
                'emoji' => '🟤',
                'category' => 'outdoor',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Toprağın tamamen kurumasını bekleyin.',
                        'tip' => 'Islak toprak daha fazla yayılır.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Kuru fırça ile fazla toprağı temizleyin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Islakken temizlemeye çalışmayın.',
                ],
                'related_ingredients' => [
                    'Sıvı deterjan',
                    'Leke çıkarıcı',
                ],
            ],
            [
                'id' => 31,
                'slug' => 'kum-lekesi',
                'name' => 'Kum Lekesi',
                'emoji' => '🏖️',
                'category' => 'outdoor',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Kumun tamamen kurumasını bekleyin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Silkeleyin veya vakumlayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Bebek deterjanı',
                ],
            ],
            [
                'id' => 32,
                'slug' => 'cicek-poleni-lekesi',
                'name' => 'Çiçek Poleni Lekesi',
                'emoji' => '🌸',
                'category' => 'outdoor',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Poleni silkelemeyin, bantla yapıştırarak alın.',
                        'tip' => 'Silkelemek lekeyi yayar.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Vakum ile emmeyi deneyin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Ovalamayın veya silkelemeyin.',
                ],
                'related_ingredients' => [
                    'Leke çıkarıcı',
                    'Alkol',
                ],
            ],

            // CRAFT/ART STAINS (4 stains)
            [
                'id' => 33,
                'slug' => 'boya-lekesi',
                'name' => 'Boya Lekesi',
                'emoji' => '🎨',
                'category' => 'craft',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Boya tipini belirleyin (su bazlı mı, yağlı mı).',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Su bazlı boya için: Soğuk suyla hemen durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Yağlı boya için: Terpentin veya solvent uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Sıvı deterjan ile ovalayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Sıcak suyla yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Kurumuş boya çıkmayabilir.',
                    'Hemen müdahale edin.',
                ],
                'related_ingredients' => [
                    'Alkol',
                    'Terpentin (yağlı boya için)',
                    'Sıvı deterjan',
                ],
            ],
            [
                'id' => 34,
                'slug' => 'keceli-kalem-lekesi',
                'name' => 'Keçeli Kalem Lekesi',
                'emoji' => '✏️',
                'category' => 'craft',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Leke altına emici bir bez yerleştirin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Alkol veya dezenfektan ile hafifçe silin.',
                        'tip' => 'Lekeyi kumaşa değil, altındaki beze transfer edin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Temiz yüzeye geçene kadar tekrarlayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Kalıcı marker tamamen çıkmayabilir.',
                    'Bazı markerlarda solvent gerekir.',
                ],
                'related_ingredients' => [
                    'Alkol',
                    'Dezenfektan',
                    'Leke çıkarıcı sprey',
                ],
            ],
            [
                'id' => 35,
                'slug' => 'pastel-boya-lekesi',
                'name' => 'Pastel Boya Lekesi',
                'emoji' => '🖍️',
                'category' => 'craft',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla boyayı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Bulaşık deterjanı uygulayın.',
                        'tip' => 'Pastel yağlı olduğu için yağ çözücü gerekir.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıcak suyla ovalayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Leke çıkarıcı sprey uygulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Yağ içeriği nedeniyle inatçıdır.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Leke çıkarıcı sprey',
                    'Talk pudrası',
                ],
            ],
            [
                'id' => 36,
                'slug' => 'oyun-hamuru-lekesi',
                'name' => 'Oyun Hamuru Lekesi',
                'emoji' => '🟢',
                'category' => 'craft',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Hamuru tamamen kurumasını bekleyin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Kuru hamuru fırça ile kazıyın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Kalan lekeye beyaz sirke uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekleyin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Renkli hamurlar boya bırakabilir.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Sıvı deterjan',
                ],
            ],

            // HOUSEHOLD STAINS (4 stains)
            [
                'id' => 37,
                'slug' => 'krem-lekesi',
                'name' => 'Krem/Losyon Lekesi',
                'emoji' => '🧴',
                'category' => 'household',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla kremi bir kaşıkla kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Talk pudrası veya mısır nişastası serpin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '20 dakika bekleyin ve tozu fırçalayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Bulaşık deterjanı uygulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Yağ bazlı kremler daha zor çıkar.',
                ],
                'related_ingredients' => [
                    'Talk pudrası',
                    'Bulaşık deterjanı',
                    'Mısır nişastası',
                ],
            ],
            [
                'id' => 38,
                'slug' => 'dis-macunu-lekesi',
                'name' => 'Diş Macunu Lekesi',
                'emoji' => '🦷',
                'category' => 'household',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla macunu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Beyaz sirke uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '10 dakika bekleyin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkama programında yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Sıvı deterjan',
                ],
            ],
            [
                'id' => 39,
                'slug' => 'bebek-yagi-lekesi',
                'name' => 'Bebek Yağı Lekesi',
                'emoji' => '🍼',
                'category' => 'household',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla yağı emici kağıtla silin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Talk pudrası veya mısır nişastası bol miktarda serpin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '30-60 dakika bekleyin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Tozu fırçalayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Bulaşık deterjanı ile ovalayın.',
                    ],
                    [
                        'step' => 6,
                        'instruction' => 'Sıcak suyla yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Yağ lekeleri kurutucuya atılırsa kalıcı olur.',
                ],
                'related_ingredients' => [
                    'Talk pudrası',
                    'Mısır nişastası',
                    'Bulaşık deterjanı',
                ],
            ],
            [
                'id' => 40,
                'slug' => 'pisik-kremi-lekesi',
                'name' => 'Pişik Kremi Lekesi',
                'emoji' => '🧴',
                'category' => 'household',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla kremi kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Bulaşık deterjanı bol miktarda uygulayın.',
                        'tip' => 'Çinko oksit ve vazelin içerir, çok yağlıdır.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıcak suyla ovalayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Leke çıkarıcı sprey uygulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Sıcak suyla yıkayın.',
                    ],
                    [
                        'step' => 6,
                        'instruction' => 'Gerekirse tekrarlayın.',
                    ],
                ],
                'warnings' => [
                    'En zor çıkan lekelerden biridir.',
                    'Birden fazla yıkama gerekebilir.',
                    'Kurutmadan önce lekenin çıktığından emin olun.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Leke çıkarıcı sprey',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
        ];
    }

    private function get_stain_categories() {
        return [
            [
                'id' => 'food',
                'label' => 'Yemek Lekeleri',
            ],
            [
                'id' => 'bodily',
                'label' => 'Vücut Sıvıları',
            ],
            [
                'id' => 'outdoor',
                'label' => 'Dış Mekan',
            ],
            [
                'id' => 'craft',
                'label' => 'Sanat/Oyun',
            ],
            [
                'id' => 'household',
                'label' => 'Ev İçi',
            ],
        ];
    }

    /**
     * Normalize Turkish characters for search
     */
    private function normalize_turkish( $text ) {
        static $search = null;
        static $replace = null;
        
        if ( $search === null ) {
            $search = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'];
            $replace = ['c', 'g', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'o', 's', 'u'];
        }
        
        return strtolower( str_replace( $search, $replace, $text ) );
    }

    /**
     * Haftalık banyo takvimi oluştur
     */
    private function generate_weekly_schedule( $age_months, $season, $has_eczema ) {
        $days = [ 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar' ];
        $schedule = [];
        
        // Yaşa göre banyo günlerini belirle
        $bath_days = $this->get_bath_days_for_age( $age_months, $has_eczema );
        
        foreach ( $days as $index => $day ) {
            $is_bath_day = in_array( $index, $bath_days );
            $schedule[] = [
                'day' => $day,
                'bath' => $is_bath_day,
                'note' => $is_bath_day ? $this->get_day_note( $season, $has_eczema ) : null,
            ];
        }
        
        return $schedule;
    }

    /**
     * Yaşa göre banyo günlerini belirle
     */
    private function get_bath_days_for_age( $age_months, $has_eczema ) {
        // Egzama varsa banyo sıklığını azalt
        if ( $has_eczema ) {
            if ( $age_months < 3 ) {
                return [ 0, 3, 5 ]; // 3 gün (Pzt, Per, Cmt)
            } elseif ( $age_months < 6 ) {
                return [ 0, 2, 4, 6 ]; // 4 gün
            } elseif ( $age_months < 12 ) {
                return [ 0, 2, 3, 5, 6 ]; // 5 gün
            } else {
                return [ 0, 1, 2, 3, 4, 5, 6 ]; // Her gün
            }
        } else {
            if ( $age_months < 3 ) {
                return [ 1, 3, 5 ]; // 3 gün (Salı, Perşembe, Cumartesi)
            } elseif ( $age_months < 6 ) {
                return [ 0, 2, 4, 6 ]; // 4 gün
            } elseif ( $age_months < 12 ) {
                return [ 0, 1, 3, 4, 6 ]; // 5 gün
            } else {
                return [ 0, 1, 2, 3, 4, 5, 6 ]; // Her gün
            }
        }
    }

    /**
     * Gün notu oluştur
     */
    private function get_day_note( $season, $has_eczema ) {
        if ( $has_eczema ) {
            return 'Ilık su ve kısa süreli banyo';
        }
        
        if ( $season === 'winter' ) {
            return 'Banyodan sonra cildi iyi nemlendirin';
        }
        
        return null;
    }

    /**
     * Egzama ve mevsime göre uyarılar
     */
    private function get_warnings( $skin_type, $season, $has_eczema ) {
        $warnings = [];
        
        if ( $has_eczema ) {
            $warnings[] = 'Egzamalı ciltlerde banyo süresini 5-10 dakika ile sınırlayın';
            $warnings[] = 'Banyo sonrası 3 dakika içinde nemlendirici uygulayın';
            $warnings[] = 'Ilık su kullanın, sıcak su cildi kurutabilir';
        }
        
        if ( $season === 'winter' ) {
            $warnings[] = 'Kış aylarında banyo sıklığını azaltmayı düşünün';
            $warnings[] = 'Banyodan sonra cildi iyi nemlendirin';
        }
        
        if ( $skin_type === 'dry' ) {
            $warnings[] = 'Sabun kullanımını minimumda tutun';
            $warnings[] = 'Yağlı banyo ürünleri tercih edin';
        }
        
        return $warnings;
    }

    /**
     * Ürün önerilerini string array olarak döndür
     */
    private function get_product_recommendations_list( $skin_type, $has_eczema ) {
        $recommendations = [];
        
        if ( $has_eczema || $skin_type === 'sensitive' ) {
            $recommendations[] = 'Parfümsüz, hipoalerjenik bebek şampuanı';
            $recommendations[] = 'Oat (yulaf) bazlı banyo yağı';
            $recommendations[] = 'Seramid içeren nemlendirici';
        } else {
            $recommendations[] = 'Hafif bebek şampuanı';
            $recommendations[] = 'Bebek duş jeli';
        }
        
        if ( $skin_type === 'dry' ) {
            $recommendations[] = 'Nemlendirici içeren banyo köpüğü';
            $recommendations[] = 'Yoğun nemlendirici krem veya balm';
        }
        
        $recommendations[] = 'Yumuşak pamuklu havlu';
        $recommendations[] = 'Banyo termometresi';
        
        return $recommendations;
    }

    /**
     * Bez değişimi başına mendil sayısı (yaşa göre)
     */
    private function get_wipes_per_diaper_change( $age_months ) {
        if ( $age_months < 3 ) {
            return 4; // Yenidoğanlar için daha fazla
        } elseif ( $age_months < 12 ) {
            return 3; // 3-12 ay arası
        } else {
            return 2; // Büyük bebekler için daha az
        }
    }

    /**
     * Öğün başına mendil sayısı (yaşa göre)
     */
    private function get_wipes_per_meal( $age_months ) {
        if ( $age_months < 6 ) {
            return 1; // Sadece süt, az kirlilik
        } elseif ( $age_months < 9 ) {
            return 2; // Ek gıdaya yeni başlayanlar
        } elseif ( $age_months < 12 ) {
            return 3; // Aktif yemek yiyenler (BLW vs.)
        } else {
            return 4; // Kendi yemeye çalışanlar, çok dağınık
        }
    }

    /**
     * Dış mekan saati başına ekstra mendil
     */
    private function get_wipes_per_outdoor_hour( $age_months ) {
        if ( $age_months < 6 ) {
            return 1;
        } elseif ( $age_months < 12 ) {
            return 1.5;
        } else {
            return 2; // Aktif bebekler, parkta oyun vs.
        }
    }

    /**
     * Detaylı hijyen önerileri
     */
    private function get_hygiene_recommendations_detailed( $age_months, $diaper_changes, $outdoor_hours, $meal_count ) {
        $recommendations = [];

        // Genel öneriler
        $recommendations[] = 'Islak mendilleri serin ve kuru bir yerde saklayın';
        $recommendations[] = 'Hassas ciltler için parfümsüz mendil tercih edin';

        // Yaşa özel öneriler
        if ( $age_months < 3 ) {
            $recommendations[] = 'Yenidoğan cildi çok hassastır, %99 su içerikli mendiller tercih edin';
            $recommendations[] = 'Her bez değişiminde nazikçe temizleyin, ovalamayın';
        } elseif ( $age_months < 6 ) {
            $recommendations[] = 'Pişik önleyici bariyer krem kullanmayı unutmayın';
        }

        // Ek gıda döneminde
        if ( $age_months >= 6 && $meal_count >= 3 ) {
            $recommendations[] = 'Yemek sonrası yüz ve elleri ıslak mendille temizleyin';
            $recommendations[] = 'Mama önlüğü kullanarak kıyafet kirliliğini azaltın';
        }

        // Bez değişim sıklığına göre
        if ( $diaper_changes < 5 ) {
            $recommendations[] = 'Bez değişim sıklığını artırmayı düşünün, pişik riskini azaltır';
        } elseif ( $diaper_changes > 8 ) {
            $recommendations[] = 'Bez değişim sıklığınız ideal! Cilt sağlığı için harika';
        }

        // Dış mekan aktivitesine göre
        if ( $outdoor_hours >= 3 ) {
            $recommendations[] = 'Dışarıda geçirilen süre fazla, çantada yedek mendil paketi bulundurun';
            $recommendations[] = 'Güneş koruyucu uyguladıktan sonra eller için ayrı mendil kullanın';
        }

        return $recommendations;
    }

    /**
     * Çantada bulundurulması gerekenler
     */
    private function get_carry_bag_essentials( $age_months, $outdoor_hours ) {
        $essentials = [];

        // Temel ihtiyaçlar
        $essentials[] = 'Islak mendil paketi (mini seyahat boy)';
        $essentials[] = 'Yedek bez (en az 2-3 adet)';
        $essentials[] = 'Bez değiştirme altlığı';
        $essentials[] = 'Pişik kremi';

        // Yaşa göre eklemeler
        if ( $age_months >= 6 ) {
            $essentials[] = 'Yedek önlük';
            $essentials[] = 'Atıştırmalık kabı';
        }

        if ( $age_months >= 9 ) {
            $essentials[] = 'El temizleme jeli (alkol içermeyen)';
        }

        // Dış mekan süresine göre
        if ( $outdoor_hours >= 2 ) {
            $essentials[] = 'Ekstra mendil paketi';
            $essentials[] = 'Küçük çöp poşetleri';
        }

        if ( $outdoor_hours >= 4 ) {
            $essentials[] = 'Yedek kıyafet seti';
            $essentials[] = 'İkinci bez paketi';
        }

        // Mevsimsel (opsiyonel - gelecekte eklenebilir)
        $essentials[] = 'Nemlendirici krem';
        $essentials[] = 'Güneş koruyucu (6 ay üzeri için)';

        return $essentials;
    }

    /**
     * Bez numarasının kilo aralığını döndür
     */
    private function get_size_weight_range( $weight_kg ) {
        if ( $weight_kg < 4 ) {
            return '2-4 kg';
        } elseif ( $weight_kg < 6 ) {
            return '4-6 kg';
        } elseif ( $weight_kg < 9 ) {
            return '6-9 kg';
        } elseif ( $weight_kg < 12 ) {
            return '9-12 kg';
        } elseif ( $weight_kg < 16 ) {
            return '12-16 kg';
        } else {
            return '16+ kg';
        }
    }

    /**
     * Aylık paket sayısını hesapla
     */
    private function calculate_monthly_packs( $daily_count ) {
        $monthly_diapers = $daily_count * 30;
        $packs_needed = ceil( $monthly_diapers / self::DIAPERS_PER_PACK );
        
        return $packs_needed;
    }

    /**
     * Önerilen paket tipini döndür
     */
    private function get_recommended_pack_type( $daily_count ) {
        $monthly_diapers = $daily_count * 30;
        
        if ( $monthly_diapers >= 200 ) {
            return 'Mega Paket (Ekonomik)';
        } elseif ( $monthly_diapers >= 120 ) {
            return 'Jumbo Paket';
        } else {
            return 'Standart Paket';
        }
    }

    /**
     * Numara değişikliği uyarısı
     * 
     * @param float $weight_kg Baby's weight in kg
     * @param int $age_months Baby's age in months (reserved for future age-specific alerts)
     */
    private function get_size_change_alert( $weight_kg, $age_months ) {
        // Üst sınıra yaklaşıyorsa uyarı ver
        if ( $weight_kg >= 3.5 && $weight_kg < 4 ) {
            return 'Bebeğiniz yakında 1 (Mini) numaraya geçebilir';
        } elseif ( $weight_kg >= 5.5 && $weight_kg < 6 ) {
            return 'Bebeğiniz yakında 2 (Midi) numaraya geçebilir';
        } elseif ( $weight_kg >= 8.5 && $weight_kg < 9 ) {
            return 'Bebeğiniz yakında 3 (Maxi) numaraya geçebilir';
        } elseif ( $weight_kg >= 11.5 && $weight_kg < 12 ) {
            return 'Bebeğiniz yakında 4 (Maxi+) numaraya geçebilir';
        } elseif ( $weight_kg >= 15.5 && $weight_kg < 16 ) {
            return 'Bebeğiniz yakında 5 (Junior) numaraya geçebilir';
        }
        
        return null;
    }

    /**
     * Mevcut mevsimi belirle
     */
    private function get_current_season() {
        $month = (int) date( 'n' );
        if ( $month >= 3 && $month <= 5 ) {
            return 'spring';
        } elseif ( $month >= 6 && $month <= 8 ) {
            return 'summer';
        } elseif ( $month >= 9 && $month <= 11 ) {
            return 'autumn';
        } else {
            return 'winter';
        }
    }

    /**
     * İç mekan hava kalitesi risk skoru hesapla
     */
    private function calculate_indoor_air_risk( $home_type, $heating_type, $has_pets, $has_smoker, $season, $ventilation, $cooking, $has_newborn, $has_respiratory ) {
        $score = 0;
        
        // Ev tipi risk puanları
        $home_scores = [
            'apartment' => 15,
            'ground_floor' => 25,
            'house' => 10,
            'villa' => 5,
        ];
        $score += $home_scores[$home_type] ?? self::DEFAULT_HOME_RISK_SCORE;
        
        // Isıtma sistemi risk puanları
        $heating_scores = [
            'stove' => 35,
            'natural_gas' => 20,
            'central' => 10,
            'electric' => 5,
            'air_conditioner' => 15,
        ];
        $score += $heating_scores[$heating_type] ?? self::DEFAULT_HEATING_RISK_SCORE;
        
        // Evcil hayvan riski
        if ( $has_pets ) {
            $score += 15;
        }
        
        // Sigara riski (en yüksek risk faktörü)
        if ( $has_smoker ) {
            $score += 30;
        }
        
        // Mevsimsel risk
        $season_scores = [
            'winter' => 15,
            'autumn' => 10,
            'spring' => 10,
            'summer' => 5,
        ];
        $score += $season_scores[$season] ?? 10;
        
        // Havalandırma etkisi (azaltıcı)
        $ventilation_reduction = [
            'multiple_daily' => -15,
            'daily' => -10,
            'rarely' => 0,
        ];
        $score += $ventilation_reduction[$ventilation] ?? -10;
        
        // Mutfak aktivitesi
        $cooking_scores = [
            'high' => 10,
            'medium' => 5,
            'low' => 0,
        ];
        $score += $cooking_scores[$cooking] ?? 5;
        
        // Hassas gruplar için ek risk
        if ( $has_newborn ) {
            $score += 10;
        }
        if ( $has_respiratory ) {
            $score += 10;
        }
        
        // Skoru 0-100 arasında normalize et
        $score = max( 0, min( 100, $score ) );
        
        // Risk seviyesini belirle
        if ( $score <= 30 ) {
            $risk_level = 'low';
        } elseif ( $score <= 60 ) {
            $risk_level = 'medium';
        } else {
            $risk_level = 'high';
        }
        
        return [
            'score' => $score,
            'risk_level' => $risk_level,
        ];
    }

    /**
     * İç mekan risk faktörlerini topla
     */
    private function get_indoor_risk_factors( $home_type, $heating_type, $has_pets, $has_smoker, $season, $ventilation, $cooking ) {
        $factors = [];
        
        // Sigara - en kritik faktör
        if ( $has_smoker ) {
            $factors[] = [
                'factor' => 'Sigara Dumanı',
                'impact' => 'Çocukların solunum sistemine ciddi zarar verir. Pasif içicilik riski çok yüksektir.',
                'severity' => 'high',
                'category' => 'lifestyle',
            ];
        }
        
        // Isıtma sistemi riskleri
        if ( $heating_type === 'stove' ) {
            $factors[] = [
                'factor' => 'Soba Isıtma',
                'impact' => 'Karbonmonoksit ve partikül madde salınımı riski. Düzenli havalandırma şarttır.',
                'severity' => 'high',
                'category' => 'heating',
            ];
        } elseif ( $heating_type === 'natural_gas' ) {
            $factors[] = [
                'factor' => 'Doğalgaz Kombi',
                'impact' => 'Yanma ürünleri ve nem dengesini etkileyebilir. Düzenli bakım önemlidir.',
                'severity' => 'medium',
                'category' => 'heating',
            ];
        }
        
        // Evcil hayvan
        if ( $has_pets ) {
            $factors[] = [
                'factor' => 'Evcil Hayvan',
                'impact' => 'Tüy ve toz akarı alerjisi riski. Düzenli temizlik ve havalandırma gerekir.',
                'severity' => 'medium',
                'category' => 'environment',
            ];
        }
        
        // Ev tipi
        if ( $home_type === 'ground_floor' ) {
            $factors[] = [
                'factor' => 'Zemin Kat',
                'impact' => 'Nem ve küf riski daha yüksektir. Düzenli nem kontrolü yapın.',
                'severity' => 'medium',
                'category' => 'environment',
            ];
        } elseif ( $home_type === 'apartment' ) {
            $factors[] = [
                'factor' => 'Apartman Dairesi',
                'impact' => 'Havalandırma sınırlı olabilir. Pencere açma imkanı değerlendirin.',
                'severity' => 'low',
                'category' => 'environment',
            ];
        }
        
        // Mevsimsel
        if ( $season === 'winter' ) {
            $factors[] = [
                'factor' => 'Kış Mevsimi',
                'impact' => 'Kapalı ortamda geçirilen süre artar, hava kalitesi düşebilir.',
                'severity' => 'medium',
                'category' => 'external',
            ];
        } elseif ( $season === 'spring' ) {
            $factors[] = [
                'factor' => 'İlkbahar Polenleri',
                'impact' => 'Polen alerjisi riski. Pencere açarken dikkatli olun.',
                'severity' => 'low',
                'category' => 'external',
            ];
        }
        
        // Havalandırma
        if ( $ventilation === 'rarely' ) {
            $factors[] = [
                'factor' => 'Yetersiz Havalandırma',
                'impact' => 'Kirli hava birikimi ve nem problemi. Günde en az 2-3 kez havalandırın.',
                'severity' => 'medium',
                'category' => 'lifestyle',
            ];
        }
        
        // Mutfak
        if ( $cooking === 'high' ) {
            $factors[] = [
                'factor' => 'Yoğun Mutfak Aktivitesi',
                'impact' => 'Pişirme dumanı ve nem. Aspiratör kullanımı ve havalandırma önemli.',
                'severity' => 'low',
                'category' => 'lifestyle',
            ];
        }
        
        return $factors;
    }

    /**
     * Çocuk yaşına ve duruma göre hava kalitesi önerileri
     */
    private function get_child_air_quality_recommendations( $child_age_months, $risk_level, $has_respiratory, $season, $has_pets, $has_smoker ) {
        $recommendations = [];
        
        // Sigara varsa en öncelikli uyarı
        if ( $has_smoker ) {
            $recommendations[] = 'Evde sigara içilmemesi çocuğunuzun sağlığı için kritik öneme sahiptir';
            $recommendations[] = 'Sigara içildikten sonra en az 30 dakika odaya girmemesini sağlayın';
            $recommendations[] = 'Sigara içen kişi ellerini ve yüzünü yıkamadan çocuğa yaklaşmamalıdır';
        }
        
        // Risk seviyesine göre öneriler
        if ( $risk_level === 'high' ) {
            $recommendations[] = 'Hava temizleyici cihaz kullanmayı düşünün (HEPA filtreli)';
            $recommendations[] = 'Günde en az 3-4 kez 10-15 dakika havalandırma yapın';
            $recommendations[] = 'Nem oranını %40-60 arasında tutun';
        } elseif ( $risk_level === 'medium' ) {
            $recommendations[] = 'Günde en az 2-3 kez havalandırma yapın';
            $recommendations[] = 'Çocuğun odasında hava kalitesini özellikle takip edin';
        }
        
        // Yaşa göre öneriler
        if ( $child_age_months < 6 ) {
            $recommendations[] = 'Yenidoğan ve küçük bebekler hava kirliliğine çok hassastır';
            $recommendations[] = 'Bebeğin odasını her zaman temiz ve iyi havalandırılmış tutun';
            $recommendations[] = 'Parfümlü ürünler ve oda spreyleri kullanmaktan kaçının';
        } elseif ( $child_age_months < 12 ) {
            $recommendations[] = 'Bebeğin emeklemeye başlamasıyla zemin temizliği daha önemli hale gelir';
            $recommendations[] = 'Toz toplayan eşyaları minimize edin';
        } elseif ( $child_age_months < 36 ) {
            $recommendations[] = 'Çocuğunuzun aktif olduğu alanlarda düzenli temizlik yapın';
            $recommendations[] = 'Oyuncakları düzenli olarak temizleyin';
        }
        
        // Solunum sorunu varsa
        if ( $has_respiratory ) {
            $recommendations[] = 'Doktorunuzla düzenli takip yapın';
            $recommendations[] = 'Ani hava kalitesi değişikliklerinde dikkatli olun';
            $recommendations[] = 'Acil durum ilaçlarını her zaman ulaşılabilir tutun';
        }
        
        // Evcil hayvan varsa
        if ( $has_pets ) {
            $recommendations[] = 'Evcil hayvanları çocuğun yatak odasına sokmayın';
            $recommendations[] = 'Evcil hayvanları düzenli olarak tımar edin';
            $recommendations[] = 'HEPA filtreli elektrikli süpürge kullanın';
        }
        
        // Mevsimsel öneriler
        if ( $season === 'winter' ) {
            $recommendations[] = 'Kış aylarında ısıtma sistemini düzenli kontrol ettirin';
            $recommendations[] = 'Odaları aşırı ısıtmaktan kaçının, ideal oda sıcaklığı 20-22°C';
        } elseif ( $season === 'summer' ) {
            $recommendations[] = 'Klima filtrelerini düzenli temizleyin';
            $recommendations[] = 'Sabah erken ve akşam geç saatlerde havalandırın';
        }
        
        // Genel öneriler
        if ( empty( $recommendations ) || count( $recommendations ) < self::MIN_RECOMMENDATIONS_COUNT ) {
            $recommendations[] = 'Düzenli havalandırma yapın';
            $recommendations[] = 'Toz ve nem kontrolünü sağlayın';
            $recommendations[] = 'Doğal temizlik ürünleri tercih edin';
        }
        
        return array_unique( $recommendations );
    }

    /**
     * Mevsimsel hava kalitesi uyarıları
     */
    private function get_air_quality_seasonal_alerts( $season, $child_age_months, $has_respiratory, $heating_type ) {
        $alerts = [];
        
        switch ( $season ) {
            case 'winter':
                $alerts[] = 'Kış aylarında kapalı ortamda geçirilen süre arttığından hava kalitesine dikkat edin';
                if ( $heating_type === 'stove' || $heating_type === 'natural_gas' ) {
                    $alerts[] = 'Isıtma sisteminizden kaynaklı karbonmonoksit riski için dedektör kullanın';
                }
                $alerts[] = 'Soğuk havalarda kısa süreli ama sık havalandırma yapın';
                break;
                
            case 'spring':
                $alerts[] = 'İlkbahar aylarında polen seviyesi yüksektir, alerji belirtilerini takip edin';
                $alerts[] = 'Polen yoğunluğu yüksek saatlerde (10:00-16:00) pencere açmaktan kaçının';
                if ( $has_respiratory || $child_age_months < 12 ) {
                    $alerts[] = 'Hassas çocuklar için antihistaminik ilaç bulundurun (doktor onaylı)';
                }
                break;
                
            case 'summer':
                $alerts[] = 'Yaz aylarında ozon seviyesi artabilir, sıcak saatlerde dışarı çıkmayı sınırlayın';
                $alerts[] = 'Klima kullanıyorsanız filtreleri ayda bir kontrol edin';
                $alerts[] = 'Sivrisinek kovucu spreyleri çocuğun yakınında kullanmaktan kaçının';
                break;
                
            case 'autumn':
                $alerts[] = 'Sonbahar aylarında nem kontrolü önemlidir, küf oluşumuna dikkat edin';
                $alerts[] = 'Isıtma sezonuna geçmeden sistemlerinizi kontrol ettirin';
                break;
        }
        
        // Yaşa özel mevsimsel uyarılar
        if ( $child_age_months < 6 ) {
            $alerts[] = 'Küçük bebekler mevsim geçişlerinde daha hassastır, oda sıcaklığını sabit tutun';
        }
        
        return $alerts;
    }
}

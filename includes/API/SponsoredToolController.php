<?php
namespace KG_Core\API;

class SponsoredToolController {

    /**
     * Average number of diapers per pack
     * Used for calculating monthly pack requirements
     */
    private const DIAPERS_PER_PACK = 50;

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
        $aqi = (int) $request->get_param( 'aqi' );
        $has_newborn = (bool) $request->get_param( 'has_newborn' );
        $respiratory_issues = (bool) $request->get_param( 'respiratory_issues' );

        if ( $aqi < 0 || $aqi > 500 ) {
            return new \WP_Error( 'invalid_aqi', 'Geçerli bir AQI değeri giriniz (0-500)', [ 'status' => 400 ] );
        }

        // Determine air quality level
        $quality_level = $this->get_air_quality_level( $aqi );
        
        // Get recommendations based on AQI and child conditions
        $recommendations = $this->get_air_quality_recommendations( $aqi, $has_newborn, $respiratory_issues );

        $tool = $this->get_tool_by_slug( 'air-quality' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'aqi' => $aqi,
            'quality_level' => $quality_level,
            'is_safe_for_outdoor' => $this->is_safe_outdoor( $aqi, $has_newborn, $respiratory_issues ),
            'recommendations' => $recommendations,
            'indoor_tips' => $this->get_indoor_air_tips(),
            'sponsor' => $sponsor_data,
        ];

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

        // Filter by query
        if ( ! empty( $query ) ) {
            $stains = array_filter( $stains, function( $stain ) use ( $query ) {
                return stripos( $stain['name'], $query ) !== false || 
                       stripos( $stain['description'], $query ) !== false;
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
            'Düzenli havalandırma yapın (hava kalitesi iyiyken)',
            'Hava temizleyici kullanın',
            'İç mekanda sigara içilmemesini sağlayın',
            'Ev bitkilerini kullanın',
            'Nem oranını %40-60 arasında tutun',
        ];
    }

    private function get_stain_database() {
        // Mock stain database - in real implementation this would come from a database
        return [
            [
                'slug' => 'mama-lekesi',
                'name' => 'Mama Lekesi',
                'category' => 'food',
                'description' => 'Sebze veya meyve bazlı mama lekeleri',
                'difficulty' => 'easy',
                'removal_steps' => [
                    'Fazla mamayı kazıyın',
                    'Soğuk suyla durulayın',
                    'Leke çıkarıcı sprey uygulayın',
                    '30 dakika bekleyin',
                    'Normal yıkama programında yıkayın',
                ],
                'products' => [
                    'Leke çıkarıcı sprey',
                    'Bebek deterjanı',
                ],
                'tips' => [
                    'Hemen müdahale edin',
                    'Sıcak su kullanmayın',
                ],
            ],
            [
                'slug' => 'kaka-lekesi',
                'name' => 'Kaka Lekesi',
                'category' => 'bodily',
                'description' => 'Bebek dışkısı lekeleri',
                'difficulty' => 'moderate',
                'removal_steps' => [
                    'Katı kısmı temizleyin',
                    'Soğuk suyla bol miktarda durulayın',
                    'Oksijen bazlı leke çıkarıcı uygulayın',
                    '1-2 saat bekleyin',
                    '60°C\'de yıkayın',
                    'Güneşte kurutun',
                ],
                'products' => [
                    'Oksijen bazlı leke çıkarıcı',
                    'Bebek deterjanı',
                ],
                'tips' => [
                    'Asla sıcak suyla başlamayın',
                    'Güneş ışığı doğal ağartıcıdır',
                ],
            ],
            [
                'slug' => 'kirmizi-meyve',
                'name' => 'Kırmızı Meyve Lekesi',
                'category' => 'food',
                'description' => 'Çilek, ahududu gibi kırmızı meyveler',
                'difficulty' => 'hard',
                'removal_steps' => [
                    'Fazla meyveyi temizleyin',
                    'Kaynar su dökülerek sabitlemeyin',
                    'Limon suyu veya sirke uygulayın',
                    '15 dakika bekleyin',
                    'Soğuk suyla durulayın',
                    'Leke çıkarıcı ile yıkayın',
                ],
                'products' => [
                    'Limon suyu veya beyaz sirke',
                    'Oksijen bazlı leke çıkarıcı',
                ],
                'tips' => [
                    'Hemen müdahale edin',
                    'Tanin içerir, zordur',
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
                'id' => 'other',
                'label' => 'Diğer',
            ],
        ];
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
}

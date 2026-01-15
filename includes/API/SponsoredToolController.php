<?php
namespace KG_Core\API;

class SponsoredToolController {

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
                'icon' => get_field( 'tool_icon', $tool->ID ),
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
            'sponsor_data' => $sponsor_data,
        ];

        return new \WP_REST_Response( $config, 200 );
    }

    /**
     * Generate bath routine plan
     */
    public function generate_bath_routine( $request ) {
        $child_age_months = (int) $request->get_param( 'child_age_months' );
        $skin_type = $request->get_param( 'skin_type' ) ?: 'normal';
        $activity_level = $request->get_param( 'activity_level' ) ?: 'moderate';

        if ( $child_age_months < 0 ) {
            return new \WP_Error( 'invalid_age', 'Geçerli bir yaş değeri giriniz', [ 'status' => 400 ] );
        }

        // Determine frequency based on age and activity level
        $frequency = $this->calculate_bath_frequency( $child_age_months, $activity_level );
        
        // Get appropriate products based on skin type
        $products = $this->get_bath_products( $skin_type, $child_age_months );

        // Generate routine steps
        $routine = $this->generate_routine_steps( $child_age_months, $skin_type );

        $tool = $this->get_tool_by_slug( 'bath-planner' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'recommended_frequency' => $frequency,
            'products' => $products,
            'routine' => $routine,
            'tips' => $this->get_bath_tips( $child_age_months, $skin_type ),
            'sponsor_data' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Calculate hygiene needs
     */
    public function calculate_hygiene_needs( $request ) {
        $child_age_months = (int) $request->get_param( 'child_age_months' );
        $lifestyle = $request->get_param( 'lifestyle' ) ?: 'moderate';

        if ( $child_age_months < 0 ) {
            return new \WP_Error( 'invalid_age', 'Geçerli bir yaş değeri giriniz', [ 'status' => 400 ] );
        }

        $daily_needs = [
            'diapers' => $this->calculate_daily_diapers( $child_age_months ),
            'wipes' => $this->calculate_daily_wipes( $child_age_months, $lifestyle ),
            'bath_products' => $this->calculate_bath_products( $child_age_months ),
            'laundry_loads' => $this->calculate_laundry( $child_age_months ),
        ];

        $monthly_needs = [
            'diapers' => $daily_needs['diapers'] * 30,
            'wipes' => $daily_needs['wipes'] * 30,
            'bath_products' => $daily_needs['bath_products'],
            'laundry_loads' => $daily_needs['laundry_loads'] * 30,
        ];

        $tool = $this->get_tool_by_slug( 'hygiene-calculator' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'daily_needs' => $daily_needs,
            'monthly_needs' => $monthly_needs,
            'estimated_cost' => $this->calculate_estimated_cost( $monthly_needs ),
            'recommendations' => $this->get_hygiene_recommendations( $child_age_months ),
            'sponsor_data' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Calculate diaper needs
     */
    public function calculate_diaper_needs( $request ) {
        $child_age_months = (int) $request->get_param( 'child_age_months' );
        $weight_kg = (float) $request->get_param( 'weight_kg' );
        $feeding_type = $request->get_param( 'feeding_type' ) ?: 'mixed';

        if ( $child_age_months < 0 || $weight_kg <= 0 ) {
            return new \WP_Error( 'invalid_input', 'Geçerli yaş ve kilo değerleri giriniz', [ 'status' => 400 ] );
        }

        $daily_count = $this->calculate_daily_diapers( $child_age_months, $feeding_type );
        $recommended_size = $this->get_diaper_size( $weight_kg, $child_age_months );

        $tool = $this->get_tool_by_slug( 'diaper-calculator' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'daily_count' => $daily_count,
            'weekly_count' => $daily_count * 7,
            'monthly_count' => $daily_count * 30,
            'recommended_size' => $recommended_size,
            'change_frequency' => $this->get_change_frequency( $child_age_months ),
            'tips' => $this->get_diaper_tips( $child_age_months ),
            'sponsor_data' => $sponsor_data,
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Assess diaper rash risk
     */
    public function assess_rash_risk( $request ) {
        $factors = $request->get_param( 'factors' ) ?: [];
        
        if ( ! is_array( $factors ) ) {
            return new \WP_Error( 'invalid_input', 'Factors array required', [ 'status' => 400 ] );
        }

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
            $risk_level = 'moderate';
        }

        $tool = $this->get_tool_by_slug( 'diaper-calculator' );
        $sponsor_data = ! is_wp_error( $tool ) ? $this->get_sponsor_data( $tool->ID ) : null;

        $result = [
            'risk_level' => $risk_level,
            'risk_score' => $risk_score,
            'risk_factors' => $risk_factors,
            'prevention_tips' => $this->get_rash_prevention_tips( $risk_level ),
            'treatment_recommendations' => $this->get_rash_treatment( $risk_level ),
            'sponsor_data' => $sponsor_data,
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
            'sponsor_data' => $sponsor_data,
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
            'sponsor_data' => $sponsor_data,
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

        $stain['sponsor_data'] = $sponsor_data;

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

        return [
            'is_sponsored' => true,
            'sponsor_name' => get_post_meta( $tool_id, '_kg_tool_sponsor_name', true ),
            'sponsor_url' => get_post_meta( $tool_id, '_kg_tool_sponsor_url', true ),
            'sponsor_logo' => [
                'id' => $sponsor_logo_id ? absint( $sponsor_logo_id ) : null,
                'url' => $sponsor_logo_id ? wp_get_attachment_url( $sponsor_logo_id ) : null,
            ],
            'sponsor_light_logo' => [
                'id' => $sponsor_light_logo_id ? absint( $sponsor_light_logo_id ) : null,
                'url' => $sponsor_light_logo_id ? wp_get_attachment_url( $sponsor_light_logo_id ) : null,
            ],
            'sponsor_tagline' => get_post_meta( $tool_id, '_kg_tool_sponsor_tagline', true ),
            'sponsor_cta' => [
                'text' => get_post_meta( $tool_id, '_kg_tool_sponsor_cta_text', true ),
                'url' => get_post_meta( $tool_id, '_kg_tool_sponsor_cta_url', true ),
            ],
            'gam_impression_url' => get_post_meta( $tool_id, '_kg_tool_gam_impression_url', true ),
            'gam_click_url' => get_post_meta( $tool_id, '_kg_tool_gam_click_url', true ),
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
}

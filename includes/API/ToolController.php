<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;

class ToolController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // List all tools
        register_rest_route( 'kg/v1', '/tools', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_tools' ],
            'permission_callback' => '__return_true',
        ]);

        // Get single tool
        register_rest_route( 'kg/v1', '/tools/(?P<slug>[a-zA-Z0-9_-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_tool' ],
            'permission_callback' => '__return_true',
        ]);

        // Get BLW test configuration
        register_rest_route( 'kg/v1', '/tools/blw-test/config', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_blw_test_config' ],
            'permission_callback' => '__return_true',
        ]);

        // Submit BLW test result
        register_rest_route( 'kg/v1', '/tools/blw-test/submit', [
            'methods'  => 'POST',
            'callback' => [ $this, 'submit_blw_test' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get all tools
     */
    public function get_tools( $request ) {
        $args = [
            'post_type' => 'tool',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];

        $tools = get_posts( $args );
        $result = [];

        foreach ( $tools as $tool ) {
            $is_active = get_field( 'is_active', $tool->ID );
            
            // Skip inactive tools
            if ( ! $is_active ) {
                continue;
            }

            $result[] = [
                'id' => $tool->ID,
                'title' => $tool->post_title,
                'slug' => $tool->post_name,
                'description' => $tool->post_content,
                'tool_type' => get_field( 'tool_type', $tool->ID ),
                'icon' => get_field( 'tool_icon', $tool->ID ),
                'requires_auth' => (bool) get_field( 'requires_auth', $tool->ID ),
                'thumbnail' => get_the_post_thumbnail_url( $tool->ID, 'medium' ),
            ];
        }

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Get single tool by slug
     */
    public function get_tool( $request ) {
        $slug = $request->get_param( 'slug' );

        $args = [
            'post_type' => 'tool',
            'name' => $slug,
            'post_status' => 'publish',
            'posts_per_page' => 1,
        ];

        $tools = get_posts( $args );

        if ( empty( $tools ) ) {
            return new \WP_Error( 'tool_not_found', 'Tool not found', [ 'status' => 404 ] );
        }

        $tool = $tools[0];
        $is_active = get_field( 'is_active', $tool->ID );

        if ( ! $is_active ) {
            return new \WP_Error( 'tool_inactive', 'This tool is currently inactive', [ 'status' => 403 ] );
        }

        $result = [
            'id' => $tool->ID,
            'title' => $tool->post_title,
            'slug' => $tool->post_name,
            'description' => $tool->post_content,
            'tool_type' => get_field( 'tool_type', $tool->ID ),
            'icon' => get_field( 'tool_icon', $tool->ID ),
            'requires_auth' => (bool) get_field( 'requires_auth', $tool->ID ),
            'thumbnail' => get_the_post_thumbnail_url( $tool->ID, 'medium' ),
        ];

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Get BLW test configuration
     */
    public function get_blw_test_config( $request ) {
        // Find BLW test tool
        $args = [
            'post_type' => 'tool',
            'name' => 'blw-test',
            'post_status' => 'publish',
            'posts_per_page' => 1,
        ];

        $tools = get_posts( $args );

        if ( empty( $tools ) ) {
            // Return default configuration if tool not found
            return new \WP_REST_Response( $this->get_default_blw_config(), 200 );
        }

        $tool = $tools[0];
        $questions = get_field( 'blw_questions', $tool->ID );
        $result_buckets = get_field( 'result_buckets', $tool->ID );
        $disclaimer = get_field( 'disclaimer_text', $tool->ID );
        $emergency = get_field( 'emergency_text', $tool->ID );

        // If fields are not set, use default
        if ( empty( $questions ) || empty( $result_buckets ) ) {
            return new \WP_REST_Response( $this->get_default_blw_config(), 200 );
        }

        // Process questions to ensure proper format
        $formatted_questions = [];
        if ( is_array( $questions ) ) {
            foreach ( $questions as $q ) {
                $formatted_options = [];
                if ( isset( $q['options'] ) && is_array( $q['options'] ) ) {
                    foreach ( $q['options'] as $opt ) {
                        $formatted_options[] = [
                            'id' => $opt['id'],
                            'text' => $opt['text'],
                            'value' => (int) $opt['value'],
                            'is_red_flag' => (bool) ( $opt['is_red_flag'] ?? false ),
                            'red_flag_message' => $opt['red_flag_message'] ?? '',
                        ];
                    }
                }

                $formatted_questions[] = [
                    'id' => $q['id'],
                    'category' => $q['category'],
                    'question' => $q['question'],
                    'description' => $q['description'] ?? '',
                    'icon' => $q['icon'] ?? 'fa-question-circle',
                    'weight' => (int) ( $q['weight'] ?? 50 ),
                    'options' => $formatted_options,
                ];
            }
        }

        // Process result buckets
        $formatted_buckets = [];
        if ( is_array( $result_buckets ) ) {
            foreach ( $result_buckets as $bucket ) {
                $action_items = [];
                if ( ! empty( $bucket['action_items'] ) ) {
                    $action_items = array_filter( array_map( 'trim', explode( "\n", $bucket['action_items'] ) ) );
                }

                $next_steps = [];
                if ( ! empty( $bucket['next_steps'] ) ) {
                    $next_steps = array_filter( array_map( 'trim', explode( "\n", $bucket['next_steps'] ) ) );
                }

                $formatted_buckets[] = [
                    'id' => $bucket['id'],
                    'min_score' => (int) $bucket['min_score'],
                    'max_score' => (int) $bucket['max_score'],
                    'title' => $bucket['title'],
                    'subtitle' => $bucket['subtitle'] ?? '',
                    'color' => $bucket['color'] ?? 'green',
                    'icon' => $bucket['icon'] ?? 'fa-check-circle',
                    'description' => $bucket['description'] ?? '',
                    'action_items' => $action_items,
                    'next_steps' => $next_steps,
                ];
            }
        }

        return new \WP_REST_Response( [
            'questions' => $formatted_questions,
            'result_buckets' => $formatted_buckets,
            'disclaimer_text' => $disclaimer ?: '',
            'emergency_text' => $emergency ?: '',
        ], 200 );
    }

    /**
     * Submit BLW test result
     */
    public function submit_blw_test( $request ) {
        $answers = $request->get_param( 'answers' );
        $child_id = $request->get_param( 'child_id' );
        $register = $request->get_param( 'register' );

        // Validate answers
        if ( empty( $answers ) || ! is_array( $answers ) ) {
            return new \WP_Error( 'invalid_answers', 'Answers are required and must be an array', [ 'status' => 400 ] );
        }

        // Get test configuration
        $config_response = $this->get_blw_test_config( $request );
        $config = $config_response->get_data();
        $questions = $config['questions'];

        // Calculate score
        $score_result = $this->calculate_score( $answers, $questions );
        
        if ( is_wp_error( $score_result ) ) {
            return $score_result;
        }

        $total_score = $score_result['score'];
        $red_flags = $score_result['red_flags'];

        // Find matching result bucket
        $result_bucket = null;
        foreach ( $config['result_buckets'] as $bucket ) {
            if ( $total_score >= $bucket['min_score'] && $total_score <= $bucket['max_score'] ) {
                $result_bucket = $bucket;
                break;
            }
        }

        if ( ! $result_bucket ) {
            return new \WP_Error( 'no_result_bucket', 'No matching result category found', [ 'status' => 500 ] );
        }

        // Prepare result
        $result = [
            'score' => round( $total_score, 2 ),
            'result' => $result_bucket,
            'red_flags' => $red_flags,
            'timestamp' => current_time( 'c' ),
        ];

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
                return new \WP_Error( 'registration_failed', 'Email and password are required for registration', [ 'status' => 400 ] );
            }

            // Create user
            $user_id = wp_create_user( $email, $password, $email );

            if ( is_wp_error( $user_id ) ) {
                return new \WP_Error( 'registration_failed', $user_id->get_error_message(), [ 'status' => 400 ] );
            }

            // Set user role
            $user = get_user_by( 'id', $user_id );
            $user->set_role( 'kg_parent' );

            if ( ! empty( $name ) ) {
                wp_update_user( [
                    'ID' => $user_id,
                    'display_name' => $name,
                ] );
            }

            // Generate token
            $new_token = JWTHandler::generate_token( $user_id );
            $result['token'] = $new_token;
            $result['user_id'] = $user_id;
        }

        // Save result if user is authenticated
        if ( $user_id ) {
            $this->save_blw_result( $user_id, $child_id, $result );
        }

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Calculate BLW test score
     */
    private function calculate_score( $answers, $questions ) {
        $weighted_sum = 0;
        $total_weight = 0;
        $red_flags = [];

        foreach ( $questions as $question ) {
            $question_id = $question['id'];
            
            if ( ! isset( $answers[ $question_id ] ) ) {
                continue;
            }

            $selected_option_id = $answers[ $question_id ];
            $weight = $question['weight'];

            // Find the selected option
            $selected_option = null;
            foreach ( $question['options'] as $option ) {
                if ( $option['id'] === $selected_option_id ) {
                    $selected_option = $option;
                    break;
                }
            }

            if ( ! $selected_option ) {
                continue;
            }

            // Check for red flags
            if ( $selected_option['is_red_flag'] && ! empty( $selected_option['red_flag_message'] ) ) {
                $red_flags[] = [
                    'question' => $question['question'],
                    'message' => $selected_option['red_flag_message'],
                ];
            }

            // Calculate weighted score
            $weighted_sum += $selected_option['value'] * $weight;
            $total_weight += $weight;
        }

        if ( $total_weight === 0 ) {
            return new \WP_Error( 'invalid_calculation', 'No valid questions answered', [ 'status' => 400 ] );
        }

        $score = $weighted_sum / $total_weight;

        return [
            'score' => $score,
            'red_flags' => $red_flags,
        ];
    }

    /**
     * Save BLW test result to user meta
     */
    private function save_blw_result( $user_id, $child_id, $result ) {
        $blw_results = get_user_meta( $user_id, '_kg_blw_results', true );
        
        if ( ! is_array( $blw_results ) ) {
            $blw_results = [];
        }

        $result_entry = [
            'id' => wp_generate_uuid4(),
            'child_id' => $child_id ?: null,
            'score' => $result['score'],
            'result_category' => $result['result']['id'],
            'red_flags' => $result['red_flags'],
            'timestamp' => $result['timestamp'],
        ];

        $blw_results[] = $result_entry;
        update_user_meta( $user_id, '_kg_blw_results', $blw_results );

        return $result_entry;
    }

    /**
     * Get default BLW test configuration (WHO standards)
     */
    private function get_default_blw_config() {
        return [
            'questions' => [
                // Physical Readiness Questions (70% weight)
                [
                    'id' => 'q1_sitting',
                    'category' => 'physical_readiness',
                    'question' => 'Bebeğiniz desteksiz oturabiliyor mu?',
                    'description' => 'Bebeğin sırtı dik ve baş kontrolü tam olmalı',
                    'icon' => 'fa-baby',
                    'weight' => 80,
                    'options' => [
                        [
                            'id' => 'sitting_yes',
                            'text' => 'Evet, rahatça oturuyor',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'sitting_support',
                            'text' => 'Destekle oturuyor',
                            'value' => 50,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'sitting_no',
                            'text' => 'Hayır, henüz oturamıyor',
                            'value' => 0,
                            'is_red_flag' => true,
                            'red_flag_message' => 'Bebeğinizin desteksiz oturabilmesi BLW için önemli bir ön koşuldur. Pediatristinizle görüşün.',
                        ],
                    ],
                ],
                [
                    'id' => 'q2_head_control',
                    'category' => 'physical_readiness',
                    'question' => 'Bebeğinizin baş kontrolü tam mı?',
                    'description' => 'Başını sağa sola çevirebilmeli',
                    'icon' => 'fa-head-side',
                    'weight' => 75,
                    'options' => [
                        [
                            'id' => 'head_yes',
                            'text' => 'Evet, tam kontrol var',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'head_partial',
                            'text' => 'Kısmen var',
                            'value' => 40,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'head_no',
                            'text' => 'Hayır, henüz yok',
                            'value' => 0,
                            'is_red_flag' => true,
                            'red_flag_message' => 'Baş kontrolü BLW için kritik öneme sahiptir. Pediatristinize danışın.',
                        ],
                    ],
                ],
                [
                    'id' => 'q3_tongue_reflex',
                    'category' => 'physical_readiness',
                    'question' => 'Dil itme refleksi kayboldu mu?',
                    'description' => 'Bebek katı yiyecekleri ağzında tutabiliyor mu?',
                    'icon' => 'fa-utensils',
                    'weight' => 70,
                    'options' => [
                        [
                            'id' => 'reflex_gone',
                            'text' => 'Evet, kayboldu',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'reflex_uncertain',
                            'text' => 'Emin değilim',
                            'value' => 50,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'reflex_present',
                            'text' => 'Hayır, hala var',
                            'value' => 20,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                    ],
                ],
                [
                    'id' => 'q4_interest',
                    'category' => 'physical_readiness',
                    'question' => 'Bebeğiniz yemeklere ilgi gösteriyor mu?',
                    'description' => 'Masadaki yiyeceklere uzanıyor, izliyor mu?',
                    'icon' => 'fa-eye',
                    'weight' => 60,
                    'options' => [
                        [
                            'id' => 'interest_high',
                            'text' => 'Evet, çok ilgili',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'interest_some',
                            'text' => 'Bazen ilgi gösteriyor',
                            'value' => 60,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'interest_no',
                            'text' => 'Hayır, ilgisiz',
                            'value' => 30,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                    ],
                ],
                [
                    'id' => 'q5_grasp',
                    'category' => 'physical_readiness',
                    'question' => 'Bebeğiniz yiyecekleri tutup ağzına götürebiliyor mu?',
                    'description' => 'El-göz-ağız koordinasyonu',
                    'icon' => 'fa-hand-paper',
                    'weight' => 70,
                    'options' => [
                        [
                            'id' => 'grasp_yes',
                            'text' => 'Evet, yapabiliyor',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'grasp_trying',
                            'text' => 'Denemeye başladı',
                            'value' => 70,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'grasp_no',
                            'text' => 'Hayır, yapamıyor',
                            'value' => 30,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                    ],
                ],
                // Safety Questions (30% weight)
                [
                    'id' => 'q6_age',
                    'category' => 'safety',
                    'question' => 'Bebeğiniz kaç aylık?',
                    'description' => 'WHO 6 ay ve sonrasını öneriyor',
                    'icon' => 'fa-calendar',
                    'weight' => 50,
                    'options' => [
                        [
                            'id' => 'age_6plus',
                            'text' => '6 ay ve üzeri',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'age_5',
                            'text' => '5-6 ay arası',
                            'value' => 40,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'age_below5',
                            'text' => '5 ayın altında',
                            'value' => 0,
                            'is_red_flag' => true,
                            'red_flag_message' => 'WHO, ek gıdaya 6. aydan önce başlanmamasını önermektedir. Lütfen pediatristinize danışın.',
                        ],
                    ],
                ],
                [
                    'id' => 'q7_medical',
                    'category' => 'safety',
                    'question' => 'Bebeğinizin ek gıdayı engelleyecek tıbbi durumu var mı?',
                    'description' => 'Reflü, yutma güçlüğü, premature vb.',
                    'icon' => 'fa-stethoscope',
                    'weight' => 40,
                    'options' => [
                        [
                            'id' => 'medical_no',
                            'text' => 'Hayır, yok',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'medical_mild',
                            'text' => 'Hafif bir durum var',
                            'value' => 50,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'medical_yes',
                            'text' => 'Evet, ciddi bir durum var',
                            'value' => 0,
                            'is_red_flag' => true,
                            'red_flag_message' => 'Tıbbi durumlar nedeniyle mutlaka pediatrist gözetiminde hareket edin.',
                        ],
                    ],
                ],
                [
                    'id' => 'q8_first_aid',
                    'category' => 'safety',
                    'question' => 'Bebek ilk yardım bilginiz var mı?',
                    'description' => 'Boğulma durumunda Heimlich manevrası',
                    'icon' => 'fa-medkit',
                    'weight' => 35,
                    'options' => [
                        [
                            'id' => 'firstaid_yes',
                            'text' => 'Evet, eğitim aldım',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'firstaid_basic',
                            'text' => 'Temel bilgim var',
                            'value' => 70,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'firstaid_no',
                            'text' => 'Hayır, bilgim yok',
                            'value' => 30,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                    ],
                ],
                [
                    'id' => 'q9_supervision',
                    'category' => 'safety',
                    'question' => 'Bebeği yemek sırasında sürekli gözetleyebilir misiniz?',
                    'description' => 'Yanından ayrılmadan gözetim şart',
                    'icon' => 'fa-user-shield',
                    'weight' => 45,
                    'options' => [
                        [
                            'id' => 'supervision_yes',
                            'text' => 'Evet, her zaman',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'supervision_mostly',
                            'text' => 'Çoğu zaman',
                            'value' => 60,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'supervision_no',
                            'text' => 'Bazen ayrılmam gerekebilir',
                            'value' => 20,
                            'is_red_flag' => true,
                            'red_flag_message' => 'BLW sırasında sürekli gözetim kritik öneme sahiptir. Yemek sırasında bebeğin yanından ayrılmayın.',
                        ],
                    ],
                ],
                // Environment Question
                [
                    'id' => 'q10_highchair',
                    'category' => 'environment',
                    'question' => 'Uygun bir mama sandalyeniz var mı?',
                    'description' => 'Bebek dik oturmalı, ayakları desteklenmeli',
                    'icon' => 'fa-chair',
                    'weight' => 30,
                    'options' => [
                        [
                            'id' => 'chair_yes',
                            'text' => 'Evet, uygun sandalye var',
                            'value' => 100,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'chair_planning',
                            'text' => 'Almayı planlıyorum',
                            'value' => 60,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                        [
                            'id' => 'chair_no',
                            'text' => 'Hayır, yok',
                            'value' => 30,
                            'is_red_flag' => false,
                            'red_flag_message' => '',
                        ],
                    ],
                ],
            ],
            'result_buckets' => [
                [
                    'id' => 'ready',
                    'min_score' => 80,
                    'max_score' => 100,
                    'title' => "BLW'ye Hazır Görünüyorsunuz!",
                    'subtitle' => 'Bebeğiniz tüm kriterleri karşılıyor',
                    'color' => 'green',
                    'icon' => 'fa-check-circle',
                    'description' => 'Tebrikler! Bebeğiniz Baby-Led Weaning (BLW) için fiziksel ve gelişimsel olarak hazır görünüyor. Güvenli bir şekilde BLW yolculuğuna başlayabilirsiniz.',
                    'action_items' => [
                        'Yumuşak, kolay tutulabilir parmak yiyeceklerle başlayın',
                        'İlk haftalarda küçük porsiyonlar sunun',
                        'Bebeğinizin kendi hızında keşfetmesine izin verin',
                        'Her öğünde ailenizle birlikte yemek yiyin',
                    ],
                    'next_steps' => [
                        'BLW için uygun ilk yiyecekleri araştırın',
                        'Bebek ilk yardım bilgilerinizi tazeleyin',
                        'Yemek ortamınızı BLW için hazırlayın',
                    ],
                ],
                [
                    'id' => 'almost_ready',
                    'min_score' => 55,
                    'max_score' => 79,
                    'title' => 'Neredeyse Hazır!',
                    'subtitle' => 'Birkaç alanda biraz daha gelişim gerekli',
                    'color' => 'yellow',
                    'icon' => 'fa-clock',
                    'description' => "Bebeğiniz BLW'ye başlamak için iyi bir yolda, ancak bazı alanlarda biraz daha gelişim göstermesi veya siz ebeveyn olarak hazırlık yapmanız faydalı olacaktır.",
                    'action_items' => [
                        'Bebeğinizle birlikte yemek masasında vakit geçirin',
                        'Oturma becerilerini güçlendiren aktiviteler yapın',
                        'Bebek ilk yardım eğitimi almayı düşünün',
                        'Pediatristinizle BLW hakkında konuşun',
                    ],
                    'next_steps' => [
                        '1-2 hafta daha bekleyin ve gelişimi izleyin',
                        'Püreleştirilmiş yiyeceklerle başlayıp yavaşça BLW\'ye geçiş yapın',
                        'Pediatristinizden onay alın',
                    ],
                ],
                [
                    'id' => 'not_ready',
                    'min_score' => 0,
                    'max_score' => 54,
                    'title' => 'Biraz Daha Zaman Gerekli',
                    'subtitle' => 'Bebeğiniz henüz hazır değil',
                    'color' => 'red',
                    'icon' => 'fa-info-circle',
                    'description' => "Bebeğiniz BLW'ye başlamak için henüz fiziksel veya gelişimsel olarak hazır görünmüyor. Acele etmeyin, her bebek kendi hızında gelişir.",
                    'action_items' => [
                        'Pediatristinizle bebeğinizin gelişimi hakkında konuşun',
                        'Baş kontrolü ve oturma becerilerini destekleyin',
                        'Bebeğinizin yiyeceklere olan ilgisini gözlemleyin',
                        'Bebek ilk yardım konusunda kendinizi eğitin',
                    ],
                    'next_steps' => [
                        'Birkaç hafta bekleyin ve testi tekrar yapın',
                        'Geleneksel kaşıkla besleme ile devam edin',
                        'Bebeğinizin gelişim dönüm noktalarını takip edin',
                    ],
                ],
            ],
            'disclaimer_text' => '<p>Bu test yalnızca genel bilgilendirme amaçlıdır ve tıbbi tavsiye yerine geçmez. BLW\'ye başlamadan önce mutlaka çocuk doktorunuzla görüşün. Her bebek farklıdır ve bireysel değerlendirme önemlidir.</p>',
            'emergency_text' => '<p><strong>ACİL DURUM:</strong> Boğulma durumunda 112\'yi arayın. Heimlich manevrası hakkında bilgi sahibi olun. BLW sırasında bebeğinizi asla yalnız bırakmayın.</p>',
        ];
    }
}

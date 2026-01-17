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

        // Get single tool - exclude food-trials path (handled by FoodTrialController)
        // Using negative lookahead to exclude paths that start with "food-trials"
        register_rest_route( 'kg/v1', '/tools/(?P<slug>(?!food-trials)[a-zA-Z0-9_-]+)', [
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

        // === NEW SMART ASSISTANT TOOLS ===

        // Ingredient Guide - Check if ingredient is suitable
        register_rest_route( 'kg/v1', '/tools/ingredient-guide/check', [
            'methods'  => 'GET',
            'callback' => [ $this, 'ingredient_guide_check' ],
            'permission_callback' => '__return_true',
        ]);

        // Solid Food Readiness - Get configuration
        register_rest_route( 'kg/v1', '/tools/solid-food-readiness/config', [
            'methods'  => 'GET',
            'callback' => [ $this, 'solid_food_readiness_config' ],
            'permission_callback' => '__return_true',
        ]);

        // Solid Food Readiness - Submit test
        register_rest_route( 'kg/v1', '/tools/solid-food-readiness/submit', [
            'methods'  => 'POST',
            'callback' => [ $this, 'solid_food_readiness_submit' ],
            'permission_callback' => '__return_true',
        ]);

        // Food Check - Quick suitability check
        register_rest_route( 'kg/v1', '/tools/food-check', [
            'methods'  => 'GET',
            'callback' => [ $this, 'food_check' ],
            'permission_callback' => '__return_true',
        ]);

        // Allergen Planner - Get configuration
        register_rest_route( 'kg/v1', '/tools/allergen-planner/config', [
            'methods'  => 'GET',
            'callback' => [ $this, 'allergen_planner_config' ],
            'permission_callback' => '__return_true',
        ]);

        // Allergen Planner - Generate introduction plan
        register_rest_route( 'kg/v1', '/tools/allergen-planner/generate', [
            'methods'  => 'POST',
            'callback' => [ $this, 'allergen_planner_generate' ],
            'permission_callback' => '__return_true',
        ]);

        // Water Calculator - Calculate daily fluid needs
        register_rest_route( 'kg/v1', '/tools/water-calculator', [
            'methods'  => 'GET',
            'callback' => [ $this, 'water_calculator' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get field value from post meta
     * First tries with _kg_ prefix, then falls back to without prefix for backward compatibility
     * 
     * @param string $field_name Field name
     * @param int $post_id Post ID
     * @return mixed Field value
     */
    private function get_tool_field( $field_name, $post_id ) {
        // Try with _kg_ prefix first
        $value = get_post_meta( $post_id, '_kg_' . $field_name, true );
        if ( $value !== '' && $value !== false ) {
            return $value;
        }
        
        // Fallback to without prefix (backward compatibility)
        return get_post_meta( $post_id, $field_name, true );
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
            $is_active = $this->get_tool_field( 'is_active', $tool->ID );
            
            // Skip inactive tools
            if ( ! $is_active ) {
                continue;
            }

            $result[] = [
                'id' => $tool->ID,
                'title' => $tool->post_title,
                'slug' => $tool->post_name,
                'description' => $tool->post_content,
                'tool_type' => $this->get_tool_field( 'tool_type', $tool->ID ),
                'icon' => $this->get_tool_field( 'tool_icon', $tool->ID ),
                'requires_auth' => (bool) $this->get_tool_field( 'requires_auth', $tool->ID ),
                'thumbnail' => get_the_post_thumbnail_url( $tool->ID, 'medium' ),
                'is_sponsored' => (bool) $this->get_tool_field( 'tool_is_sponsored', $tool->ID ),
                'sponsor_name' => $this->get_tool_field( 'tool_sponsor_name', $tool->ID ) ?: null,
                'sponsor_url' => $this->get_tool_field( 'tool_sponsor_url', $tool->ID ) ?: null,
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
        $is_active = $this->get_tool_field( 'is_active', $tool->ID );

        if ( ! $is_active ) {
            return new \WP_Error( 'tool_inactive', 'This tool is currently inactive', [ 'status' => 403 ] );
        }

        $result = [
            'id' => $tool->ID,
            'title' => $tool->post_title,
            'slug' => $tool->post_name,
            'description' => $tool->post_content,
            'tool_type' => $this->get_tool_field( 'tool_type', $tool->ID ),
            'icon' => $this->get_tool_field( 'tool_icon', $tool->ID ),
            'requires_auth' => (bool) $this->get_tool_field( 'requires_auth', $tool->ID ),
            'thumbnail' => get_the_post_thumbnail_url( $tool->ID, 'medium' ),
            'is_sponsored' => (bool) $this->get_tool_field( 'tool_is_sponsored', $tool->ID ),
            'sponsor_name' => $this->get_tool_field( 'tool_sponsor_name', $tool->ID ) ?: null,
            'sponsor_url' => $this->get_tool_field( 'tool_sponsor_url', $tool->ID ) ?: null,
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
        $questions = $this->get_tool_field( 'blw_questions', $tool->ID );
        $result_buckets = $this->get_tool_field( 'result_buckets', $tool->ID );
        $disclaimer = $this->get_tool_field( 'disclaimer_text', $tool->ID );
        $emergency = $this->get_tool_field( 'emergency_text', $tool->ID );

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

            // Validate email format first
            if ( ! is_email( $email ) ) {
                return new \WP_Error( 'invalid_email', 'Please provide a valid email address', [ 'status' => 400 ] );
            }

            // Check if email already exists
            if ( email_exists( $email ) ) {
                return new \WP_Error( 'email_exists', 'An account with this email already exists. Please login instead.', [ 'status' => 409 ] );
            }

            // Validate password strength
            if ( strlen( $password ) < 8 ) {
                return new \WP_Error( 'weak_password', 'Password must be at least 8 characters long', [ 'status' => 400 ] );
            }

            // Create user
            $user_id = wp_create_user( $email, $password, $email );

            if ( is_wp_error( $user_id ) ) {
                // Return generic error to avoid exposing system details
                return new \WP_Error( 'registration_failed', 'Unable to create user account. Please try again.', [ 'status' => 500 ] );
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

            // Create child profile if child_name provided
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
                    'gender' => 'unspecified',
                    'allergies' => [],
                    'feeding_style' => 'mixed',
                    'created_at' => current_time( 'c' ),
                ];

                $children[] = $new_child;
                update_user_meta( $user_id, '_kg_children', $children );
                
                // Use created child_id for BLW result if no child_id was provided
                if ( empty( $child_id ) ) {
                    $child_id = $created_child_id;
                }
            }

            // Generate token
            $new_token = JWTHandler::generate_token( $user_id );
            $result['token'] = $new_token;
            $result['user_id'] = $user_id;
            
            // Include created child info in response
            if ( $created_child_id ) {
                $result['child_id'] = $created_child_id;
                $result['child_name'] = $child_name;
            }
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

        // Generate UUID - fallback for WordPress < 4.7
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

        $result_entry = [
            'id' => $uuid,
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

    // === NEW SMART ASSISTANT TOOL METHODS ===

    /**
     * Ingredient Guide - Check if ingredient is suitable for child's age
     */
    public function ingredient_guide_check( $request ) {
        $ingredient_slug = $request->get_param( 'ingredient_slug' );
        $child_age_months = (int) $request->get_param( 'child_age_months' );

        if ( empty( $ingredient_slug ) ) {
            return new \WP_Error( 'missing_ingredient_slug', 'ingredient_slug parametresi gereklidir', [ 'status' => 400 ] );
        }

        if ( $child_age_months < 0 ) {
            return new \WP_Error( 'invalid_age', 'Geçerli bir yaş değeri giriniz', [ 'status' => 400 ] );
        }

        // Get ingredient by slug
        $args = [
            'name' => $ingredient_slug,
            'post_type' => 'ingredient',
            'post_status' => 'publish',
            'numberposts' => 1,
        ];

        $posts = get_posts( $args );

        if ( empty( $posts ) ) {
            return new \WP_Error( 'ingredient_not_found', 'Malzeme bulunamadı', [ 'status' => 404 ] );
        }

        $ingredient_id = $posts[0]->ID;
        
        // Get ingredient details
        $ingredient = [
            'id' => $ingredient_id,
            'name' => get_the_title( $ingredient_id ),
            'slug' => get_post_field( 'post_name', $ingredient_id ),
            'image' => get_the_post_thumbnail_url( $ingredient_id, 'medium' ),
        ];

        $start_age = (int) get_post_meta( $ingredient_id, '_kg_start_age', true );
        $allergy_risk = get_post_meta( $ingredient_id, '_kg_allergy_risk', true );
        
        // Determine suitability
        $is_suitable = $child_age_months >= $start_age;
        
        // Get warnings
        $warnings = [];
        if ( ! $is_suitable ) {
            $warnings[] = sprintf( 'Bu malzeme %d aylıktan itibaren verilebilir', $start_age );
        }
        if ( in_array( $allergy_risk, [ 'Yüksek', 'Orta' ] ) ) {
            $warnings[] = 'Alerji riski taşır - Dikkatli tanıtılmalı';
        }

        // Get preparation methods by age
        $prep_by_age_raw = get_post_meta( $ingredient_id, '_kg_prep_by_age', true );
        $prep_by_age = ! empty( $prep_by_age_raw ) ? maybe_unserialize( $prep_by_age_raw ) : [];

        // Get preparation method for child's age
        $preparation_method = '';
        foreach ( $prep_by_age as $prep ) {
            if ( isset( $prep['age_range'] ) && isset( $prep['method'] ) ) {
                $preparation_method .= $prep['age_range'] . ': ' . $prep['method'] . ' | ';
            }
        }
        $preparation_method = rtrim( $preparation_method, ' | ' );

        // Get pairings
        $pairings_raw = get_post_meta( $ingredient_id, '_kg_pairings', true );
        $pairings = ! empty( $pairings_raw ) ? maybe_unserialize( $pairings_raw ) : [];

        // Get tips
        $tips = get_post_meta( $ingredient_id, '_kg_pro_tips', true );

        // Get related recipes
        $related_recipes = $this->get_recipes_by_ingredient( $ingredient_id );

        return new \WP_REST_Response( [
            'ingredient' => $ingredient,
            'is_suitable' => $is_suitable,
            'start_age_months' => $start_age,
            'allergy_risk' => $allergy_risk ?: 'Düşük',
            'warnings' => $warnings,
            'preparation_method' => $preparation_method,
            'prep_by_age' => $prep_by_age,
            'tips' => $tips ?: '',
            'pairings' => $pairings,
            'related_recipes' => $related_recipes,
        ], 200 );
    }

    /**
     * Get recipes that use this ingredient
     */
    private function get_recipes_by_ingredient( $ingredient_id, $limit = 5 ) {
        $ingredient_name = get_the_title( $ingredient_id );
        
        $args = [
            'post_type' => 'recipe',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => $ingredient_name,
        ];

        $query = new \WP_Query( $args );
        $recipes = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $recipes[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'slug' => get_post_field( 'post_name', get_the_ID() ),
                    'image' => get_the_post_thumbnail_url( get_the_ID(), 'medium' ),
                ];
            }
        }
        wp_reset_postdata();

        return $recipes;
    }

    /**
     * Solid Food Readiness - Get configuration
     */
    public function solid_food_readiness_config( $request ) {
        $config = \KG_Core\Services\SolidFoodReadinessChecker::get_config();
        return new \WP_REST_Response( $config, 200 );
    }

    /**
     * Solid Food Readiness - Submit test
     */
    public function solid_food_readiness_submit( $request ) {
        $answers = $request->get_param( 'answers' );
        
        $result = \KG_Core\Services\SolidFoodReadinessChecker::submit( $answers );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Check if user is authenticated and wants to save result
        $token = JWTHandler::get_token_from_request();
        if ( $token ) {
            $payload = JWTHandler::validate_token( $token );
            if ( $payload ) {
                $user_id = $payload['user_id'];
                $child_id = $request->get_param( 'child_id' );

                // Save result to user meta
                $results = get_user_meta( $user_id, '_kg_solid_food_readiness_results', true );
                if ( ! is_array( $results ) ) {
                    $results = [];
                }

                $result_entry = [
                    'id' => $this->generate_uuid(),
                    'child_id' => $child_id ?: null,
                    'score' => $result['score'],
                    'result_category' => $result['result']['id'],
                    'red_flags' => isset( $result['red_flags'] ) ? $result['red_flags'] : [],
                    'answers' => $answers,
                    'timestamp' => $result['timestamp'],
                ];

                $results[] = $result_entry;
                update_user_meta( $user_id, '_kg_solid_food_readiness_results', $results );
            }
        }

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Food Check - Quick suitability check
     */
    public function food_check( $request ) {
        $query = $request->get_param( 'query' );
        $child_age_months = (int) $request->get_param( 'child_age_months' );

        $result = \KG_Core\Services\FoodSuitabilityChecker::check( $query, $child_age_months );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Allergen Planner - Get configuration
     */
    public function allergen_planner_config( $request ) {
        $config = \KG_Core\Services\AllergenPlanner::get_config();
        return new \WP_REST_Response( $config, 200 );
    }

    /**
     * Allergen Planner - Generate introduction plan
     */
    public function allergen_planner_generate( $request ) {
        $allergen_id = $request->get_param( 'allergen_id' );
        
        if ( empty( $allergen_id ) ) {
            return new \WP_Error( 'missing_allergen_id', 'allergen_id parametresi gereklidir', [ 'status' => 400 ] );
        }

        $params = [
            'child_id' => $request->get_param( 'child_id' ),
            'previous_reactions' => $request->get_param( 'previous_reactions' ),
        ];

        $plan = \KG_Core\Services\AllergenPlanner::generate_plan( $allergen_id, $params );
        
        if ( is_wp_error( $plan ) ) {
            return $plan;
        }

        // Check if user is authenticated and wants to save plan
        $token = JWTHandler::get_token_from_request();
        if ( $token ) {
            $payload = JWTHandler::validate_token( $token );
            if ( $payload ) {
                $user_id = $payload['user_id'];
                $child_id = $request->get_param( 'child_id' );

                // Save plan to user meta
                $plans = get_user_meta( $user_id, '_kg_allergen_plans', true );
                if ( ! is_array( $plans ) ) {
                    $plans = [];
                }

                $plan_entry = [
                    'id' => $this->generate_uuid(),
                    'child_id' => $child_id ?: null,
                    'allergen_id' => $allergen_id,
                    'plan' => $plan,
                    'created_at' => current_time( 'c' ),
                ];

                $plans[] = $plan_entry;
                update_user_meta( $user_id, '_kg_allergen_plans', $plans );
            }
        }

        return new \WP_REST_Response( $plan, 200 );
    }

    /**
     * Water Calculator - Calculate daily fluid needs
     */
    public function water_calculator( $request ) {
        $weight_kg = (float) $request->get_param( 'weight_kg' );
        $age_months = (int) $request->get_param( 'age_months' );
        $weather = $request->get_param( 'weather' ) ?: 'normal';
        $is_breastfed = (bool) $request->get_param( 'is_breastfed' );

        $result = \KG_Core\Services\WaterCalculator::calculate( $weight_kg, $age_months, $weather, $is_breastfed );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Generate UUID helper
     */
    private function generate_uuid() {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}

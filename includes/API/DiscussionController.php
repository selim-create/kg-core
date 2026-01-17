<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;

class DiscussionController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Get all circles (with user follow status)
        register_rest_route( 'kg/v1', '/circles', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_circles' ],
            'permission_callback' => '__return_true',
        ]);

        // Update user's followed circles
        register_rest_route( 'kg/v1', '/user/circles', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_user_circles' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/circles', [
            'methods'  => 'POST',
            'callback' => [ $this, 'update_user_circles' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Follow/Unfollow a single circle
        register_rest_route( 'kg/v1', '/circles/(?P<id>\d+)/follow', [
            'methods'  => 'POST',
            'callback' => [ $this, 'follow_circle' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/circles/(?P<id>\d+)/unfollow', [
            'methods'  => 'POST',
            'callback' => [ $this, 'unfollow_circle' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Create new discussion (question)
        register_rest_route( 'kg/v1', '/discussions', [
            'methods'  => 'POST',
            'callback' => [ $this, 'create_discussion' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Get discussions list (with filtering)
        register_rest_route( 'kg/v1', '/discussions', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_discussions' ],
            'permission_callback' => '__return_true',
        ]);

        // Get single discussion with comments
        register_rest_route( 'kg/v1', '/discussions/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_discussion' ],
            'permission_callback' => '__return_true',
        ]);

        // Get user's own pending discussions
        register_rest_route( 'kg/v1', '/user/discussions', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_user_discussions' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Personalized feed
        register_rest_route( 'kg/v1', '/feed', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_personalized_feed' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Add comment to discussion
        register_rest_route( 'kg/v1', '/discussions/(?P<id>\d+)/comments', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_comment' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Get comments for discussion
        register_rest_route( 'kg/v1', '/discussions/(?P<id>\d+)/comments', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_comments' ],
            'permission_callback' => '__return_true',
        ]);

        // Top contributors endpoint (Haftanın Anneleri)
        register_rest_route( 'kg/v1', '/community/top-contributors', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_top_contributors' ],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'limit' => [
                    'default' => 5,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0 && $param <= 20;
                    }
                ],
                'period' => [
                    'default' => 'week',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function( $param ) {
                        return in_array( $param, [ 'week', 'month', 'all' ] );
                    }
                ]
            ]
        ]);
    }

    /**
     * Authentication check
     */
    public function check_authentication( $request ) {
        $token = JWTHandler::get_token_from_request();
        
        if ( ! $token ) {
            return false;
        }

        $payload = JWTHandler::validate_token( $token );
        
        if ( !  $payload ) {
            return false;
        }

        $request->set_param( 'authenticated_user_id', $payload['user_id'] );
        return true;
    }

    private function get_authenticated_user_id( $request ) {
        return $request->get_param( 'authenticated_user_id' );
    }

    /**
     * GET /kg/v1/circles - Get all circles with follow status
     */
    public function get_circles( $request ) {
        $terms = get_terms( [
            'taxonomy' => 'community_circle',
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => '_kg_circle_order',
            'order' => 'ASC',
        ] );

        if ( is_wp_error( $terms ) ) {
            return new \WP_Error( 'terms_error', 'Çemberler yüklenemedi', [ 'status' => 500 ] );
        }

        // Check if user is authenticated for follow status
        $user_circles = [];
        $token = JWTHandler::get_token_from_request();
        if ( $token ) {
            $payload = JWTHandler::validate_token( $token );
            if ( $payload ) {
                $user_circles = get_user_meta( $payload['user_id'], '_kg_followed_circles', true ) ?: [];
            }
        }

        $circles = [];
        foreach ( $terms as $term ) {
            $circles[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => get_term_meta( $term->term_id, '_kg_circle_description', true ) ?: '',
                'icon' => get_term_meta( $term->term_id, '_kg_circle_icon', true ) ?: '💬',
                'color_code' => get_term_meta( $term->term_id, '_kg_circle_color_code', true ) ?: '#E8E8E8',
                'order' => (int) ( get_term_meta( $term->term_id, '_kg_circle_order', true ) ?: 10 ),
                'is_following' => in_array( $term->term_id, $user_circles ),
                'discussion_count' => $this->get_circle_discussion_count( $term->term_id ),
            ];
        }

        return new \WP_REST_Response( $circles, 200 );
    }

    private function get_circle_discussion_count( $term_id ) {
        $count = get_posts( [
            'post_type' => 'discussion',
            'post_status' => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'community_circle',
                    'field' => 'term_id',
                    'terms' => $term_id,
                ],
            ],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ] );
        return count( $count );
    }

    /**
     * GET /kg/v1/user/circles - Get user's followed circles
     */
    public function get_user_circles( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $circle_ids = get_user_meta( $user_id, '_kg_followed_circles', true ) ?: [];

        if ( empty( $circle_ids ) ) {
            return new \WP_REST_Response( [], 200 );
        }

        $terms = get_terms( [
            'taxonomy' => 'community_circle',
            'include' => $circle_ids,
            'hide_empty' => false,
        ] );

        $circles = [];
        foreach ( $terms as $term ) {
            $circles[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'icon' => get_term_meta( $term->term_id, '_kg_circle_icon', true ) ?: '💬',
                'color_code' => get_term_meta( $term->term_id, '_kg_circle_color_code', true ) ?: '#E8E8E8',
            ];
        }

        return new \WP_REST_Response( $circles, 200 );
    }

    /**
     * POST /kg/v1/user/circles - Update user's followed circles
     */
    public function update_user_circles( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $circle_ids = $request->get_param( 'circle_ids' );

        if ( !  is_array( $circle_ids ) ) {
            return new \WP_Error( 'invalid_data', 'circle_ids array gerekli', [ 'status' => 400 ] );
        }

        // Validate that all IDs are valid term IDs
        $valid_ids = [];
        foreach ( $circle_ids as $id ) {
            $term = get_term( absint( $id ), 'community_circle' );
            if ( $term && ! is_wp_error( $term ) ) {
                $valid_ids[] = $term->term_id;
            }
        }

        update_user_meta( $user_id, '_kg_followed_circles', $valid_ids );

        return new \WP_REST_Response( [
            'message' => 'Çemberler güncellendi',
            'followed_circles' => $valid_ids,
        ], 200 );
    }

    /**
     * POST /kg/v1/circles/{id}/follow
     */
    public function follow_circle( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $circle_id = absint( $request->get_param( 'id' ) );

        $term = get_term( $circle_id, 'community_circle' );
        if ( ! $term || is_wp_error( $term ) ) {
            return new \WP_Error( 'invalid_circle', 'Çember bulunamadı', [ 'status' => 404 ] );
        }

        $circles = get_user_meta( $user_id, '_kg_followed_circles', true ) ?: [];
        
        if ( !  in_array( $circle_id, $circles ) ) {
            $circles[] = $circle_id;
            update_user_meta( $user_id, '_kg_followed_circles', $circles );
        }

        return new \WP_REST_Response( [
            'message' => 'Çember takip edildi',
            'circle_id' => $circle_id,
        ], 200 );
    }

    /**
     * POST /kg/v1/circles/{id}/unfollow
     */
    public function unfollow_circle( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $circle_id = absint( $request->get_param( 'id' ) );

        $circles = get_user_meta( $user_id, '_kg_followed_circles', true ) ?: [];
        $circles = array_filter( $circles, function( $id ) use ( $circle_id ) {
            return $id !== $circle_id;
        } );

        update_user_meta( $user_id, '_kg_followed_circles', array_values( $circles ) );

        return new \WP_REST_Response( [
            'message' => 'Çember takipten çıkarıldı',
            'circle_id' => $circle_id,
        ], 200 );
    }

    /**
     * POST /kg/v1/discussions - Create new discussion
     * CRITICAL: Forces pending status for non-admins
     */
    public function create_discussion( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $content = wp_kses_post( $request->get_param( 'content' ) );
        $circle_id = absint( $request->get_param( 'circle_id' ) );
        $is_anonymous = (bool) $request->get_param( 'is_anonymous' );

        // Validation
        if ( empty( $title ) ) {
            return new \WP_Error( 'missing_title', 'Soru başlığı gerekli', [ 'status' => 400 ] );
        }

        if ( empty( $content ) ) {
            return new \WP_Error( 'missing_content', 'Soru içeriği gerekli', [ 'status' => 400 ] );
        }

        if ( empty( $circle_id ) ) {
            return new \WP_Error( 'missing_circle', 'Çember seçimi gerekli', [ 'status' => 400 ] );
        }

        // Validate circle exists
        $circle = get_term( $circle_id, 'community_circle' );
        if ( ! $circle || is_wp_error( $circle ) ) {
            return new \WP_Error( 'invalid_circle', 'Geçersiz çember', [ 'status' => 400 ] );
        }

        // Create discussion - ALWAYS pending for non-admins
        $post_data = [
            'post_type' => 'discussion',
            'post_title' => $title,
            'post_content' => $content,
            'post_author' => $user_id,
            'post_status' => 'pending', // FORCED pending status
        ];

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return new \WP_Error( 'creation_failed', 'Soru oluşturulamadı', [ 'status' => 500 ] );
        }

        // Set circle taxonomy
        wp_set_object_terms( $post_id, $circle_id, 'community_circle' );

        // Set post meta
        update_post_meta( $post_id, '_is_anonymous', $is_anonymous );
        update_post_meta( $post_id, '_is_featured_question', false );
        update_post_meta( $post_id, '_expert_answered', false );

        return new \WP_REST_Response( [
            'id' => $post_id,
            'message' => 'Sorunuz başarıyla gönderildi.  Uzmanlarımız tarafından incelendikten sonra yayına alınacaktır.',
            'status' => 'pending',
        ], 201 );
    }

    /**
     * GET /kg/v1/discussions - Get discussions list
     */
    public function get_discussions( $request ) {
        $circle_id = $request->get_param( 'circle_id' );
        $page = absint( $request->get_param( 'page' ) ) ?: 1;
        $per_page = absint( $request->get_param( 'per_page' ) ) ?: 10;
        $featured_only = (bool) $request->get_param( 'featured_only' );
        $expert_answered = $request->get_param( 'expert_answered' );

        $args = [
            'post_type' => 'discussion',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        // Filter by circle
        if ( $circle_id ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'community_circle',
                    'field' => 'term_id',
                    'terms' => absint( $circle_id ),
                ],
            ];
        }

        // Filter by featured
        if ( $featured_only ) {
            $args['meta_query'][] = [
                'key' => '_is_featured_question',
                'value' => '1',
                'compare' => '=',
            ];
        }

        // Filter by expert answered
        if ( $expert_answered !== null ) {
            $args['meta_query'][] = [
                'key' => '_expert_answered',
                'value' => $expert_answered ?  '1' : '0',
                'compare' => '=',
            ];
        }

        $query = new \WP_Query( $args );
        $discussions = [];

        foreach ( $query->posts as $post ) {
            $discussions[] = $this->prepare_discussion_response( $post );
        }

        return new \WP_REST_Response( [
            'discussions' => $discussions,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $page,
        ], 200 );
    }

    /**
     * GET /kg/v1/discussions/{id} - Get single discussion
     */
    public function get_discussion( $request ) {
        $discussion_id = absint( $request->get_param( 'id' ) );
        $post = get_post( $discussion_id );

        if ( ! $post || $post->post_type !== 'discussion' ) {
            return new \WP_Error( 'not_found', 'Soru bulunamadı', [ 'status' => 404 ] );
        }

        // Don't show pending posts to non-authors
        if ( $post->post_status !== 'publish' ) {
            $token = JWTHandler::get_token_from_request();
            $is_author = false;
            
            if ( $token ) {
                $payload = JWTHandler::validate_token( $token );
                if ( $payload && $payload['user_id'] == $post->post_author ) {
                    $is_author = true;
                }
            }

            if ( ! $is_author ) {
                return new \WP_Error( 'not_found', 'Soru bulunamadı', [ 'status' => 404 ] );
            }
        }

        $discussion = $this->prepare_discussion_response( $post, true );

        return new \WP_REST_Response( $discussion, 200 );
    }

    /**
     * GET /kg/v1/user/discussions - Get user's own discussions (including pending)
     */
    public function get_user_discussions( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $status = $request->get_param( 'status' ) ?: 'any';

        $args = [
            'post_type' => 'discussion',
            'post_status' => $status === 'any' ? [ 'publish', 'pending', 'draft' ] : $status,
            'author' => $user_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new \WP_Query( $args );
        $discussions = [];

        foreach ( $query->posts as $post ) {
            $discussions[] = $this->prepare_discussion_response( $post );
        }

        return new \WP_REST_Response( $discussions, 200 );
    }

    /**
     * GET /kg/v1/feed - Personalized feed based on followed circles
     */
    public function get_personalized_feed( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $circle_id = $request->get_param( 'circle_id' );
        $page = absint( $request->get_param( 'page' ) ) ?: 1;
        $per_page = absint( $request->get_param( 'per_page' ) ) ?: 10;
        $content_type = $request->get_param( 'type' ); // 'discussions', 'recipes', or null for both

        // Get user's followed circles
        $followed_circles = get_user_meta( $user_id, '_kg_followed_circles', true ) ?: [];

        // If specific circle requested, use that instead
        if ( $circle_id ) {
            $followed_circles = [ absint( $circle_id ) ];
        }

        $result = [
            'discussions' => [],
            'recipes' => [],
            'total' => 0,
        ];

        // Get discussions
        if ( !  $content_type || $content_type === 'discussions' ) {
            $discussion_args = [
                'post_type' => 'discussion',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'date',
                'order' => 'DESC',
            ];

            if ( !  empty( $followed_circles ) ) {
                $discussion_args['tax_query'] = [
                    [
                        'taxonomy' => 'community_circle',
                        'field' => 'term_id',
                        'terms' => $followed_circles,
                    ],
                ];
            }

            $discussion_query = new \WP_Query( $discussion_args );
            
            foreach ( $discussion_query->posts as $post ) {
                $result['discussions'][] = $this->prepare_discussion_response( $post );
            }
        }

        // Get recipes if they're in followed circles
        if ( ( !  $content_type || $content_type === 'recipes' ) && ! empty( $followed_circles ) ) {
            $recipe_args = [
                'post_type' => 'recipe',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'date',
                'order' => 'DESC',
                'tax_query' => [
                    [
                        'taxonomy' => 'community_circle',
                        'field' => 'term_id',
                        'terms' => $followed_circles,
                    ],
                ],
            ];

            $recipe_query = new \WP_Query( $recipe_args );
            
            foreach ( $recipe_query->posts as $post ) {
                $result['recipes'][] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'slug' => $post->post_name,
                    'excerpt' => get_the_excerpt( $post ),
                    'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
                    'type' => 'recipe',
                ];
            }
        }

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * POST /kg/v1/discussions/{id}/comments - Add comment
     */
    public function add_comment( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $discussion_id = absint( $request->get_param( 'id' ) );
        $content = wp_kses_post( $request->get_param( 'content' ) );
        $parent_id = absint( $request->get_param( 'parent_id' ) ) ?: 0;

        $post = get_post( $discussion_id );
        if ( ! $post || $post->post_type !== 'discussion' || $post->post_status !== 'publish' ) {
            return new \WP_Error( 'not_found', 'Soru bulunamadı', [ 'status' => 404 ] );
        }

        if ( empty( $content ) ) {
            return new \WP_Error( 'missing_content', 'Yorum içeriği gerekli', [ 'status' => 400 ] );
        }

        $user = get_user_by( 'id', $user_id );
        
        $comment_data = [
            'comment_post_ID' => $discussion_id,
            'comment_content' => $content,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'user_id' => $user_id,
            'comment_parent' => $parent_id,
            'comment_approved' => 1,
        ];

        $comment_id = wp_insert_comment( $comment_data );

        if ( ! $comment_id ) {
            return new \WP_Error( 'comment_failed', 'Yorum eklenemedi', [ 'status' => 500 ] );
        }

        // Check if user is expert and update discussion meta
        $user_roles = $user->roles;
        if ( in_array( 'administrator', $user_roles ) || in_array( 'expert', $user_roles ) ) {
            update_post_meta( $discussion_id, '_expert_answered', true );
            update_comment_meta( $comment_id, '_is_expert_comment', true );
        }

        return new \WP_REST_Response( [
            'id' => $comment_id,
            'message' => 'Yorum eklendi',
        ], 201 );
    }

    /**
     * GET /kg/v1/discussions/{id}/comments - Get comments
     */
    public function get_comments( $request ) {
        $discussion_id = absint( $request->get_param( 'id' ) );

        $post = get_post( $discussion_id );
        if ( ! $post || $post->post_type !== 'discussion' ) {
            return new \WP_Error( 'not_found', 'Soru bulunamadı', [ 'status' => 404 ] );
        }

        $comments = get_comments( [
            'post_id' => $discussion_id,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'ASC',
        ] );

        $result = [];
        foreach ( $comments as $comment ) {
            $is_expert = (bool) get_comment_meta( $comment->comment_ID, '_is_expert_comment', true );
            
            // Check user role for expert badge
            if ( !  $is_expert && $comment->user_id ) {
                $user = get_user_by( 'id', $comment->user_id );
                if ( $user && ( in_array( 'administrator', $user->roles ) || in_array( 'expert', $user->roles ) ) ) {
                    $is_expert = true;
                }
            }

            $result[] = [
                'id' => $comment->comment_ID,
                'content' => $comment->comment_content,
                'author' => [
                    'id' => $comment->user_id,
                    'name' => $comment->comment_author,
                    'avatar' => get_avatar_url( $comment->user_id ?: $comment->comment_author_email ),
                ],
                'is_expert_comment' => $is_expert,
                'parent_id' => (int) $comment->comment_parent,
                'created_at' => $comment->comment_date,
            ];
        }

        // Sort:  expert comments first
        usort( $result, function( $a, $b ) {
            if ( $a['is_expert_comment'] && !  $b['is_expert_comment'] ) return -1;
            if ( !  $a['is_expert_comment'] && $b['is_expert_comment'] ) return 1;
            return 0;
        } );

        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * Prepare discussion response object
     */
    private function prepare_discussion_response( $post, $include_content = false ) {
        $author = get_user_by( 'id', $post->post_author );
        $is_anonymous = (bool) get_post_meta( $post->ID, '_is_anonymous', true );
        
        // Get circle
        $circles = wp_get_object_terms( $post->ID, 'community_circle' );
        $circle = !  empty( $circles ) ? $circles[0] : null;

        $response = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'excerpt' => wp_trim_words( $post->post_content, 30 ),
            'status' => $post->post_status,
            'author' => $is_anonymous ? [
                'id' => 0,
                'name' => 'Anonim',
                'avatar' => null,
            ] : [
                'id' => $author->ID,
                'name' => $author->display_name,
                'avatar' => get_avatar_url( $author->ID ),
            ],
            'circle' => $circle ?  [
                'id' => $circle->term_id,
                'name' => $circle->name,
                'slug' => $circle->slug,
                'icon' => get_term_meta( $circle->term_id, '_kg_circle_icon', true ) ?: '💬',
                'color_code' => get_term_meta( $circle->term_id, '_kg_circle_color_code', true ) ?: '#E8E8E8',
            ] :  null,
            'is_featured' => (bool) get_post_meta( $post->ID, '_is_featured_question', true ),
            'expert_answered' => (bool) get_post_meta( $post->ID, '_expert_answered', true ),
            'comment_count' => get_comments_number( $post->ID ),
            'created_at' => $post->post_date,
            'type' => 'discussion',
        ];

        if ( $include_content ) {
            $response['content'] = apply_filters( 'the_content', $post->post_content );
        }

        return $response;
    }

    /**
     * GET /kg/v1/community/top-contributors
     * Haftanın en aktif kullanıcılarını (annelerini) döndürür
     * Sıralama: Toplam tartışma + yorum sayısı
     */
    public function get_top_contributors( $request ) {
        global $wpdb;
        
        $limit = $request->get_param( 'limit' ) ?: 5;
        $period = $request->get_param( 'period' ) ?: 'week';
        
        // Calculate the date interval based on period (validated by WordPress)
        $days_interval = 0;
        if ( $period === 'week' ) {
            $days_interval = 7;
        } elseif ( $period === 'month' ) {
            $days_interval = 30;
        }
        // If period is 'all', days_interval remains 0 (no date filter)
        
        // Build the query based on whether we need date filtering
        if ( $days_interval > 0 ) {
            // Query with date filtering
            $query = $wpdb->prepare("
                SELECT 
                    u.ID as user_id,
                    u.display_name as name,
                    (
                        SELECT COUNT(*) 
                        FROM {$wpdb->posts} p 
                        WHERE p.post_author = u.ID 
                        AND p.post_type = 'discussion' 
                        AND p.post_status = 'publish'
                        AND p.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                    ) as discussion_count,
                    (
                        SELECT COUNT(*) 
                        FROM {$wpdb->comments} c 
                        WHERE c.user_id = u.ID 
                        AND c.comment_approved = '1'
                        AND c.comment_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                    ) as comment_count
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities'
                WHERE um.meta_value NOT LIKE %s
                AND um.meta_value NOT LIKE %s
                AND um.meta_value NOT LIKE %s
                HAVING (discussion_count + comment_count) > 0
                ORDER BY (discussion_count + comment_count) DESC
                LIMIT %d
            ", $days_interval, $days_interval, '%administrator%', '%kg_expert%', '%editor%', $limit );
        } else {
            // Query without date filtering (all time)
            $query = $wpdb->prepare("
                SELECT 
                    u.ID as user_id,
                    u.display_name as name,
                    (
                        SELECT COUNT(*) 
                        FROM {$wpdb->posts} p 
                        WHERE p.post_author = u.ID 
                        AND p.post_type = 'discussion' 
                        AND p.post_status = 'publish'
                    ) as discussion_count,
                    (
                        SELECT COUNT(*) 
                        FROM {$wpdb->comments} c 
                        WHERE c.user_id = u.ID 
                        AND c.comment_approved = '1'
                    ) as comment_count
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities'
                WHERE um.meta_value NOT LIKE %s
                AND um.meta_value NOT LIKE %s
                AND um.meta_value NOT LIKE %s
                HAVING (discussion_count + comment_count) > 0
                ORDER BY (discussion_count + comment_count) DESC
                LIMIT %d
            ", '%administrator%', '%kg_expert%', '%editor%', $limit );
        }
        
        $results = $wpdb->get_results( $query );
        
        $contributors = [];
        $rank = 1;
        
        foreach ( $results as $row ) {
            $user_id = (int) $row->user_id;
            
            // Avatar URL'ini al
            $avatar_id = get_user_meta( $user_id, '_kg_avatar_id', true );
            $avatar_url = null;
            
            if ( $avatar_id ) {
                $avatar_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
            }
            
            if ( ! $avatar_url ) {
                // Google avatar kontrolü
                $google_avatar = get_user_meta( $user_id, 'google_avatar', true );
                if ( $google_avatar ) {
                    $avatar_url = $google_avatar;
                } else {
                    // Gravatar fallback
                    $avatar_url = get_avatar_url( $user_id, [ 'size' => 96 ] );
                }
            }
            
            $contribution_count = (int) $row->discussion_count + (int) $row->comment_count;
            
            $contributors[] = [
                'id' => $user_id,
                'name' => $row->name,
                'avatar' => $avatar_url,
                'contribution_count' => $contribution_count,
                'discussion_count' => (int) $row->discussion_count,
                'comment_count' => (int) $row->comment_count,
                'rank' => $rank,
            ];
            
            $rank++;
        }
        
        return new \WP_REST_Response( $contributors, 200 );
    }
}
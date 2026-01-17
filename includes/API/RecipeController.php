<?php
namespace KG_Core\API;

class RecipeController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // GET /wp-json/kg/v1/recipes (All recipes with pagination)
        register_rest_route( 'kg/v1', '/recipes', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_recipes' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /wp-json/kg/v1/recipes/featured
        register_rest_route( 'kg/v1', '/recipes/featured', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_featured_recipes' ],
            'permission_callback' => '__return_true', // Public
        ]);

        // GET /wp-json/kg/v1/recipes/{slug} (Single recipe by slug)
        register_rest_route( 'kg/v1', '/recipes/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_recipe_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /wp-json/kg/v1/recipes/by-age/{age} (Filter by age group)
        register_rest_route( 'kg/v1', '/recipes/by-age/(?P<age>[a-zA-Z0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_recipes_by_age' ],
            'permission_callback' => '__return_true',
        ]);
        
        // GET /wp-json/kg/v1/recipes/filter (Advanced Filtering)
        register_rest_route( 'kg/v1', '/recipes/filter', [
            'methods'  => 'GET',
            'callback' => [ $this, 'filter_recipes' ],
            'permission_callback' => '__return_true',
        ]);
        
        // POST /wp-json/kg/v1/recipes/{id}/rate
        register_rest_route( 'kg/v1', '/recipes/(?P<id>\d+)/rate', [
            'methods'  => 'POST',
            'callback' => [ $this, 'rate_recipe' ],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'rating' => [
                    'required' => true,
                    'validate_callback' => function( $value ) {
                        return is_numeric( $value ) && $value >= 1 && $value <= 5;
                    },
                    'sanitize_callback' => 'floatval'
                ]
            ]
        ]);
        
        // GET /wp-json/kg/v1/recipes/{id}/related
        register_rest_route( 'kg/v1', '/recipes/(?P<id>\d+)/related', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_related_recipes' ],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => [
                    'default' => 4,
                    'validate_callback' => function( $value ) {
                        return is_numeric( $value ) && $value >= 1 && $value <= 10;
                    },
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
    }

    public function get_featured_recipes( $request ) {
        $args = [
            'post_type'      => 'recipe',
            'posts_per_page' => 5,
            // Meta query ile öne çıkanları bul (ACF yerine post meta)
            'meta_query'     => [
                [
                    'key'     => '_kg_is_featured', // Özel meta anahtarı
                    'value'   => '1',
                    'compare' => '='
                ]
            ]
        ];

        $query = new \WP_Query( $args );
        $recipes = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $recipes[] = $this->prepare_recipe_data( get_the_ID() );
            }
        }
        wp_reset_postdata();

        return new \WP_REST_Response( $recipes, 200 );
    }

    // Helper to format data for Next.js (ACF'siz)
    private function prepare_recipe_data( $post_id, $full_detail = false ) {
        // Standart WP fonksiyonları ile meta verisini çek
        $prep_time = get_post_meta($post_id, '_kg_prep_time', true);
        
        // Malzemeler genellikle JSON string veya serialized array olarak saklanır
        $ingredients_raw = get_post_meta($post_id, '_kg_ingredients', true);
        $ingredients = !empty($ingredients_raw) ? maybe_unserialize($ingredients_raw) : [];

        // Adımlar
        $instructions_raw = get_post_meta($post_id, '_kg_instructions', true);
        $instructions = !empty($instructions_raw) ? maybe_unserialize($instructions_raw) : [];
        
        // Use Helper class for HTML entity decoding
        $title = \KG_Core\Utils\Helper::decode_html_entities( get_the_title( $post_id ) );
        
        // Age group bilgisini detaylı al (renk kodu dahil)
        $age_group_terms = wp_get_post_terms( $post_id, 'age-group', ['fields' => 'all'] );
        $age_group = '';
        $age_group_color = '';
        if ( ! empty( $age_group_terms ) && ! is_wp_error( $age_group_terms ) ) {
            $first_term = $age_group_terms[0];
            $age_group = \KG_Core\Utils\Helper::decode_html_entities( $first_term->name );
            $age_group_color = get_term_meta( $first_term->term_id, '_kg_color_code', true );
        }
        
        // Meal type taxonomy'den al
        $meal_type_terms = wp_get_post_terms( $post_id, 'meal-type', ['fields' => 'names'] );
        $meal_type = ! empty( $meal_type_terms ) && ! is_wp_error( $meal_type_terms ) 
            ? \KG_Core\Utils\Helper::decode_html_entities( $meal_type_terms[0] ) 
            : '';
        
        // Diet types
        $diet_type_terms = wp_get_post_terms( $post_id, 'diet-type', ['fields' => 'names'] );
        $diet_types = [];
        if ( ! empty( $diet_type_terms ) && ! is_wp_error( $diet_type_terms ) ) {
            foreach ( $diet_type_terms as $dt ) {
                $diet_types[] = \KG_Core\Utils\Helper::decode_html_entities( $dt );
            }
        }
        
        // Author bilgisi
        $post = get_post( $post_id );
        $author_id = $post->post_author;
        $author_data = null;
        if ( $author_id ) {
            $author = get_userdata( $author_id );
            if ( $author ) {
                $author_data = [
                    'id' => $author_id,
                    'name' => $author->display_name,
                    'slug' => $author->user_nicename,
                    'avatar' => \KG_Core\Utils\Helper::get_user_avatar_url( $author_id ),
                ];
            }
        }
        
        // Expert bilgisi (her zaman döndür, sadece full_detail'de değil)
        $expert_data = [
            'name' => get_post_meta( $post_id, '_kg_expert_name', true ),
            'title' => get_post_meta( $post_id, '_kg_expert_title', true ),
            'approved' => get_post_meta( $post_id, '_kg_expert_approved', true ) === '1',
        ];
        
        // Get rating data with base rating fallback
        $real_rating = get_post_meta( $post_id, '_kg_rating', true );
        $real_count = get_post_meta( $post_id, '_kg_rating_count', true );
        $base_rating = $this->get_base_rating( $post_id );
        $base_count = $this->get_base_rating_count( $post_id );
        
        // Use real rating if exists, otherwise use base
        $display_rating = ! empty( $real_rating ) ? $real_rating : $base_rating;
        $display_count = ! empty( $real_count ) ? $real_count : $base_count;
        
        $data = [
            'id'              => $post_id,
            'title'           => $title,
            'slug'            => get_post_field( 'post_name', $post_id ),
            'excerpt'         => get_the_excerpt( $post_id ),
            'image'           => get_the_post_thumbnail_url( $post_id, 'large' ),
            'prep_time'       => $prep_time,
            'ingredients'     => $ingredients,
            'instructions'    => $instructions,
            
            // YENİ ALANLAR (Card görünümü için)
            'age_group'       => $age_group,
            'age_group_color' => $age_group_color,
            'meal_type'       => $meal_type,
            'diet_types'      => $diet_types,
            'author'          => $author_data,
            'expert'          => $expert_data,
            
            // Rating fields
            'rating'          => round( floatval( $display_rating ), 1 ),
            'rating_count'    => intval( $display_count ),
            
            // Mevcut alanlar
            'age_groups'      => wp_get_post_terms( $post_id, 'age-group', ['fields' => 'names'] ),
            'allergens'       => wp_get_post_terms( $post_id, 'allergen', ['fields' => 'names'] ),
            'is_featured'     => get_post_meta( $post_id, '_kg_is_featured', true ) === '1',
        ];

        // Add full details if requested
        if ( $full_detail ) {
            $data['content'] = get_the_content( null, false, $post_id );
            
            // Extended nutrition data
            $data['nutrition'] = [
                'calories' => get_post_meta( $post_id, '_kg_calories', true ),
                'protein'  => get_post_meta( $post_id, '_kg_protein', true ),
                'carbs'    => get_post_meta( $post_id, '_kg_carbs', true ),
                'fat'      => get_post_meta( $post_id, '_kg_fat', true ),
                'fiber'    => get_post_meta( $post_id, '_kg_fiber', true ),
                'sugar'    => get_post_meta( $post_id, '_kg_sugar', true ),
                'sodium'   => get_post_meta( $post_id, '_kg_sodium', true ),
                'vitamins' => get_post_meta( $post_id, '_kg_vitamins', true ),
                'minerals' => get_post_meta( $post_id, '_kg_minerals', true ),
            ];
            
            // New fields as per requirements
            $data['cook_time'] = get_post_meta( $post_id, '_kg_cook_time', true );
            $data['serving_size'] = get_post_meta( $post_id, '_kg_serving_size', true );
            $data['difficulty'] = get_post_meta( $post_id, '_kg_difficulty', true );
            $data['freezable'] = get_post_meta( $post_id, '_kg_freezable', true ) === '1';
            $data['storage_info'] = get_post_meta( $post_id, '_kg_storage_info', true );
            
            $data['video_url'] = get_post_meta( $post_id, '_kg_video_url', true );
            
            // Special Notes
            $data['special_notes'] = get_post_meta( $post_id, '_kg_special_notes', true );
            
            // Extended expert data with note and image
            $expert_note = get_post_meta( $post_id, '_kg_expert_note', true );
            $expert_image = '';
            $expert_name = get_post_meta( $post_id, '_kg_expert_name', true );
            if ( ! empty( $expert_name ) ) {
                // Try to find user by multiple methods for avatar
                $expert_user = get_user_by( 'login', $expert_name );
                if ( ! $expert_user ) {
                    $expert_user = get_user_by( 'slug', sanitize_title( $expert_name ) );
                }
                if ( $expert_user ) {
                    $expert_image = get_avatar_url( $expert_user->ID, ['size' => 96] );
                }
            }
            
            // Update expert data with additional fields
            $data['expert'] = [
                'name' => $expert_name,
                'title' => get_post_meta( $post_id, '_kg_expert_title', true ),
                'note' => $expert_note,
                'image' => $expert_image,
                'approved' => get_post_meta( $post_id, '_kg_expert_approved', true ) === '1',
            ];
            
            $substitutes_raw = get_post_meta( $post_id, '_kg_substitutes', true );
            $data['substitutes'] = !empty($substitutes_raw) ? maybe_unserialize($substitutes_raw) : [];
            
            $data['related_recipes'] = $this->get_related_recipes( $post_id );
            $data['cross_sell'] = $this->get_cross_sell_suggestion( $post_id );
            
            // Add SEO data
            $data['seo'] = $this->get_seo_data( $post_id );
        }
        
        return $data;
    }
    
    public function filter_recipes( $request ) {
        // Implementation for advanced filtering
        return []; 
    }

    /**
     * Get all recipes with pagination and filtering
     * 
     * NOTE: API parameter names use hyphens (age-group, diet-type, etc.)
     * This is consistent with REST API conventions and WordPress taxonomy slugs
     */
    public function get_recipes( $request ) {
        $page = $request->get_param( 'page' ) ?: 1;
        $per_page = $request->get_param( 'per_page' ) ?: 12;
        
        // Existing filters (using hyphenated parameter names)
        $age_group = $request->get_param( 'age-group' );
        $diet_type = $request->get_param( 'diet-type' );
        $allergen = $request->get_param( 'allergen' );
        
        // NEW FILTERS
        $meal_type = $request->get_param( 'meal-type' );
        $special_condition = $request->get_param( 'special-condition' );
        $ingredient = $request->get_param( 'ingredient' );
        $search = $request->get_param( 'search' );
        
        // Sıralama
        $orderby = $request->get_param( 'orderby' ) ?: 'date';
        $order = $request->get_param( 'order' ) ?: 'DESC';
        
        $args = [
            'post_type'      => 'recipe',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ];
        
        // Sıralama
        switch ( $orderby ) {
            case 'popular':
                $args['meta_key'] = '_kg_rating_count';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'prep_time':
                $args['meta_key'] = '_kg_prep_time';
                $args['orderby'] = 'meta_value';
                $args['order'] = $order;
                break;
            case 'date':
            default:
                $args['orderby'] = 'date';
                $args['order'] = $order;
                break;
        }
        
        // Arama
        if ( ! empty( $search ) ) {
            $args['s'] = sanitize_text_field( $search );
        }

        // Taxonomy query
        $tax_query = [];
        
        if ( $age_group ) {
            // Birden fazla yaş grubu virgülle ayrılmış olabilir
            $age_groups = array_map( 'trim', explode( ',', $age_group ) );
            $tax_query[] = [
                'taxonomy' => 'age-group',
                'field'    => 'slug',
                'terms'    => $age_groups,
                'operator' => 'IN',
            ];
        }
        
        if ( $diet_type ) {
            $diet_types = array_map( 'trim', explode( ',', $diet_type ) );
            $tax_query[] = [
                'taxonomy' => 'diet-type',
                'field'    => 'slug',
                'terms'    => $diet_types,
                'operator' => 'IN',
            ];
        }
        
        if ( $meal_type ) {
            $meal_types = array_map( 'trim', explode( ',', $meal_type ) );
            $tax_query[] = [
                'taxonomy' => 'meal-type',
                'field'    => 'slug',
                'terms'    => $meal_types,
                'operator' => 'IN',
            ];
        }
        
        if ( $special_condition ) {
            $conditions = array_map( 'trim', explode( ',', $special_condition ) );
            $tax_query[] = [
                'taxonomy' => 'special-condition',
                'field'    => 'slug',
                'terms'    => $conditions,
                'operator' => 'IN',
            ];
        }
        
        if ( $allergen ) {
            // Alerjen filtresi NOT IN olmalı (bu alerjeni İÇERMEYENLER)
            $allergens = array_map( 'trim', explode( ',', $allergen ) );
            $tax_query[] = [
                'taxonomy' => 'allergen',
                'field'    => 'slug',
                'terms'    => $allergens,
                'operator' => 'NOT IN',
            ];
        }
        
        if ( ! empty( $tax_query ) ) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
        }
        
        // Malzeme araması (meta query)
        if ( ! empty( $ingredient ) ) {
            $args['meta_query'] = [
                [
                    'key'     => '_kg_ingredients',
                    'value'   => sanitize_text_field( $ingredient ),
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query = new \WP_Query( $args );
        $recipes = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $recipes[] = $this->prepare_recipe_data( get_the_ID() );
            }
        }
        wp_reset_postdata();

        // DÜZGÜN PAGİNATİON RESPONSE
        return new \WP_REST_Response( [
            'recipes'     => $recipes,
            'total'       => $query->found_posts,
            'page'        => (int) $page,
            'per_page'    => (int) $per_page,
            'total_pages' => $query->max_num_pages,
        ], 200 );
    }

    /**
     * Get single recipe by slug with full details
     */
    public function get_recipe_by_slug( $request ) {
        $slug = $request->get_param( 'slug' );
        
        $args = [
            'name'        => $slug,
            'post_type'   => 'recipe',
            'post_status' => 'publish',
            'numberposts' => 1,
        ];

        $posts = get_posts( $args );
        
        if ( empty( $posts ) ) {
            return new \WP_Error( 'recipe_not_found', 'Recipe not found', [ 'status' => 404 ] );
        }

        $recipe = $this->prepare_recipe_data( $posts[0]->ID, true );
        
        return new \WP_REST_Response( $recipe, 200 );
    }

    /**
     * Get recipes filtered by age group
     */
    public function get_recipes_by_age( $request ) {
        $age = $request->get_param( 'age' );
        $per_page = $request->get_param( 'per_page' ) ?: 10;
        
        $args = [
            'post_type'      => 'recipe',
            'posts_per_page' => $per_page,
            'post_status'    => 'publish',
            'tax_query'      => [
                [
                    'taxonomy' => 'age-group',
                    'field'    => 'slug',
                    'terms'    => $age,
                ],
            ],
        ];

        $query = new \WP_Query( $args );
        $recipes = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $recipes[] = $this->prepare_recipe_data( get_the_ID() );
            }
        }
        wp_reset_postdata();

        return new \WP_REST_Response( $recipes, 200 );
    }

    /**
     * Get related recipes based on shared taxonomies
     * Can be called as REST endpoint or as internal method
     */
    public function get_related_recipes( $request_or_id, $limit = 3 ) {
        // Handle both REST request and direct ID calls
        if ( is_object( $request_or_id ) && method_exists( $request_or_id, 'get_param' ) ) {
            // Called as REST endpoint
            $recipe_id = $request_or_id->get_param( 'id' );
            $limit = $request_or_id->get_param( 'limit' ) ?: 4;
            $is_rest_call = true;
        } else {
            // Called internally
            $recipe_id = $request_or_id;
            $is_rest_call = false;
        }
        
        $recipe = get_post( $recipe_id );
        if ( ! $recipe || $recipe->post_type !== 'recipe' ) {
            if ( $is_rest_call ) {
                return new \WP_Error( 'recipe_not_found', 'Recipe not found', [ 'status' => 404 ] );
            }
            return [];
        }
        
        // Get recipe taxonomies for matching
        $age_groups = wp_get_post_terms( $recipe_id, 'age-group', ['fields' => 'ids'] );
        $meal_types = wp_get_post_terms( $recipe_id, 'meal-type', ['fields' => 'ids'] );
        
        $args = [
            'post_type' => 'recipe',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => [ $recipe_id ],
        ];
        
        // Only add tax_query if we have taxonomies to match
        if ( ! empty( $age_groups ) || ! empty( $meal_types ) ) {
            $args['tax_query'] = [
                'relation' => 'OR',
            ];
            
            if ( ! empty( $age_groups ) ) {
                $args['tax_query'][] = [
                    'taxonomy' => 'age-group',
                    'field' => 'term_id',
                    'terms' => $age_groups,
                ];
            }
            
            if ( ! empty( $meal_types ) ) {
                $args['tax_query'][] = [
                    'taxonomy' => 'meal-type',
                    'field' => 'term_id',
                    'terms' => $meal_types,
                ];
            }
        }
        
        $query = new \WP_Query( $args );
        $related = [];
        
        foreach ( $query->posts as $post ) {
            $related[] = $this->prepare_recipe_card_data( $post->ID );
        }
        
        // If not enough related recipes, fill with random recipes
        if ( count( $related ) < $limit ) {
            $remaining = $limit - count( $related );
            $exclude_ids = array_merge( [ $recipe_id ], array_column( $related, 'id' ) );
            
            $random_args = [
                'post_type' => 'recipe',
                'post_status' => 'publish',
                'posts_per_page' => $remaining,
                'post__not_in' => $exclude_ids,
                'orderby' => 'rand',
            ];
            
            $random_query = new \WP_Query( $random_args );
            foreach ( $random_query->posts as $post ) {
                $related[] = $this->prepare_recipe_card_data( $post->ID );
            }
        }
        
        if ( $is_rest_call ) {
            return new \WP_REST_Response( $related, 200 );
        }
        
        return $related;
    }
    
    /**
     * Prepare simplified recipe card data for listings
     */
    private function prepare_recipe_card_data( $post_id ) {
        // Get age group info
        $age_group_terms = wp_get_post_terms( $post_id, 'age-group', ['fields' => 'all'] );
        $age_group = '';
        $age_group_color = '';
        if ( ! empty( $age_group_terms ) && ! is_wp_error( $age_group_terms ) ) {
            $first_term = $age_group_terms[0];
            $age_group = \KG_Core\Utils\Helper::decode_html_entities( $first_term->name );
            $age_group_color = get_term_meta( $first_term->term_id, '_kg_color_code', true );
        }
        
        // Get meal type
        $meal_type_terms = wp_get_post_terms( $post_id, 'meal-type', ['fields' => 'names'] );
        $meal_type = ! empty( $meal_type_terms ) && ! is_wp_error( $meal_type_terms ) 
            ? \KG_Core\Utils\Helper::decode_html_entities( $meal_type_terms[0] ) 
            : '';
        
        // Get rating data with base rating fallback
        $real_rating = get_post_meta( $post_id, '_kg_rating', true );
        $real_count = get_post_meta( $post_id, '_kg_rating_count', true );
        $base_rating = $this->get_base_rating( $post_id );
        $base_count = $this->get_base_rating_count( $post_id );
        
        // Use real rating if exists, otherwise use base
        $display_rating = ! empty( $real_rating ) ? $real_rating : $base_rating;
        $display_count = ! empty( $real_count ) ? $real_count : $base_count;
        
        return [
            'id'    => $post_id,
            'title' => \KG_Core\Utils\Helper::decode_html_entities( get_the_title( $post_id ) ),
            'slug'  => get_post_field( 'post_name', $post_id ),
            'image' => get_the_post_thumbnail_url( $post_id, 'medium' ),
            'age_group' => $age_group,
            'age_group_color' => $age_group_color,
            'meal_type' => $meal_type,
            'prep_time' => get_post_meta( $post_id, '_kg_prep_time', true ),
            'rating' => round( floatval( $display_rating ), 1 ),
            'rating_count' => intval( $display_count ),
        ];
    }

    /**
     * Get related recipes based on shared taxonomies (legacy internal method)
     * @deprecated Use get_related_recipes instead
     */
    private function get_related_recipes_legacy( $post_id, $limit = 3 ) {
        $age_groups = wp_get_post_terms( $post_id, 'age-group', ['fields' => 'ids'] );
        
        if ( empty( $age_groups ) ) {
            return [];
        }

        $args = [
            'post_type'      => 'recipe',
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'post__not_in'   => [ $post_id ],
            'tax_query'      => [
                [
                    'taxonomy' => 'age-group',
                    'field'    => 'term_id',
                    'terms'    => $age_groups,
                ],
            ],
        ];

        $query = new \WP_Query( $args );
        $related = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $related[] = [
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                    'slug'  => get_post_field( 'post_name', get_the_ID() ),
                    'image' => get_the_post_thumbnail_url( get_the_ID(), 'medium' ),
                ];
            }
        }
        wp_reset_postdata();

        return $related;
    }
    
    /**
     * Get or generate deterministic base rating for a recipe
     * @param int $post_id Recipe post ID
     * @return float Base rating (4.0-4.9)
     */
    private function get_base_rating( $post_id ) {
        $base_rating = get_post_meta( $post_id, '_kg_base_rating', true );
        
        if ( empty( $base_rating ) ) {
            $base_rating = 4.0 + ( ( $post_id % 10 ) / 10 ); // 4.0 - 4.9
            update_post_meta( $post_id, '_kg_base_rating', $base_rating );
        }
        
        return floatval( $base_rating );
    }
    
    /**
     * Get or generate deterministic base rating count for a recipe
     * @param int $post_id Recipe post ID
     * @return int Base rating count (10-150)
     */
    private function get_base_rating_count( $post_id ) {
        $base_count = get_post_meta( $post_id, '_kg_base_rating_count', true );
        
        if ( empty( $base_count ) ) {
            $base_count = 10 + ( $post_id % 141 ); // 10 - 150
            update_post_meta( $post_id, '_kg_base_rating_count', $base_count );
        }
        
        return intval( $base_count );
    }

    /**
     * Get cross-sell suggestion for Tariften.com
     */
    private function get_cross_sell_suggestion( $post_id ) {
        $cross_sell_url = get_post_meta( $post_id, '_kg_cross_sell_url', true );
        $cross_sell_title = get_post_meta( $post_id, '_kg_cross_sell_title', true );
        
        if ( empty( $cross_sell_url ) ) {
            return null;
        }

        return [
            'url'   => $cross_sell_url,
            'title' => $cross_sell_title ?: 'Tariften.com\'da daha fazla tarif keşfedin',
        ];
    }

    /**
     * Get SEO data from RankMath plugin
     * @param int $post_id Post ID
     * @return array SEO data
     */
    private function get_seo_data( $post_id ) {
        $seo_data = array();
        
        // RankMath SEO verileri
        $seo_data['title'] = get_post_meta( $post_id, 'rank_math_title', true );
        $seo_data['description'] = get_post_meta( $post_id, 'rank_math_description', true );
        $seo_data['focus_keywords'] = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
        $seo_data['canonical_url'] = get_post_meta( $post_id, 'rank_math_canonical_url', true );
        
        // Open Graph
        $seo_data['og_title'] = get_post_meta( $post_id, 'rank_math_facebook_title', true );
        $seo_data['og_description'] = get_post_meta( $post_id, 'rank_math_facebook_description', true );
        $seo_data['og_image'] = get_post_meta( $post_id, 'rank_math_facebook_image', true );
        
        // Twitter Card
        $seo_data['twitter_title'] = get_post_meta( $post_id, 'rank_math_twitter_title', true );
        $seo_data['twitter_description'] = get_post_meta( $post_id, 'rank_math_twitter_description', true );
        
        // Fallback to defaults if empty
        if ( empty( $seo_data['title'] ) ) {
            $site_name = get_option( 'blogname' ) ?: 'KidsGourmet';
            $seo_data['title'] = get_the_title( $post_id ) . ' - ' . $site_name;
        }
        if ( empty( $seo_data['description'] ) ) {
            $seo_data['description'] = wp_trim_words( get_the_excerpt( $post_id ), 30 );
        }
        
        return $seo_data;
    }
    
    /**
     * Rate a recipe
     * POST /wp-json/kg/v1/recipes/{id}/rate
     */
    public function rate_recipe( $request ) {
        $recipe_id = $request->get_param( 'id' );
        $rating = $request->get_param( 'rating' );
        $user_id = get_current_user_id();
        
        // Verify recipe exists
        $recipe = get_post( $recipe_id );
        if ( ! $recipe || $recipe->post_type !== 'recipe' ) {
            return new \WP_Error( 'recipe_not_found', 'Recipe not found', [ 'status' => 404 ] );
        }
        
        // Get existing real ratings
        $all_ratings = get_post_meta( $recipe_id, '_kg_ratings', true );
        if ( ! is_array( $all_ratings ) ) {
            $all_ratings = [];
        }
        
        // Store user's rating
        $all_ratings[ $user_id ] = floatval( $rating );
        
        // Get base rating for calculation using helper methods
        $base_rating = $this->get_base_rating( $recipe_id );
        $base_count = $this->get_base_rating_count( $recipe_id );
        
        // Calculate new average including base rating weight
        $real_count = count( $all_ratings );
        $real_sum = array_sum( $all_ratings );
        
        // Weighted average: (base_rating * base_count + real_sum) / (base_count + real_count)
        $total_count = intval( $base_count ) + $real_count;
        $weighted_sum = ( floatval( $base_rating ) * intval( $base_count ) ) + $real_sum;
        $average = $weighted_sum / $total_count;
        
        // Update meta fields
        update_post_meta( $recipe_id, '_kg_ratings', $all_ratings );
        update_post_meta( $recipe_id, '_kg_rating', round( $average, 1 ) );
        update_post_meta( $recipe_id, '_kg_rating_count', $total_count );
        
        return new \WP_REST_Response( [
            'success' => true,
            'rating' => round( $average, 1 ),
            'rating_count' => $total_count,
            'user_rating' => floatval( $rating )
        ], 200 );
    }
}
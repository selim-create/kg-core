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
        
        // GET /wp-json/kg/v1/recipes/featured
        register_rest_route( 'kg/v1', '/recipes/featured', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_featured_recipes' ],
            'permission_callback' => '__return_true', // Public
        ]);
        
        // GET /wp-json/kg/v1/recipes/filter (Advanced Filtering)
        register_rest_route( 'kg/v1', '/recipes/filter', [
            'methods'  => 'GET',
            'callback' => [ $this, 'filter_recipes' ],
            'permission_callback' => '__return_true',
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
        
        $data = [
            'id'            => $post_id,
            'title'         => get_the_title( $post_id ),
            'slug'          => get_post_field( 'post_name', $post_id ),
            'excerpt'       => get_the_excerpt( $post_id ),
            'image'         => get_the_post_thumbnail_url( $post_id, 'large' ),
            'prep_time'     => $prep_time,
            'ingredients'   => $ingredients,
            'instructions'  => $instructions,
            'age_groups'    => wp_get_post_terms( $post_id, 'age-group', ['fields' => 'names'] ),
            'allergens'     => wp_get_post_terms( $post_id, 'allergen', ['fields' => 'names'] ),
            'diet_types'    => wp_get_post_terms( $post_id, 'diet-type', ['fields' => 'names'] ),
            'is_featured'   => get_post_meta( $post_id, '_kg_is_featured', true ) === '1',
        ];

        // Add full details if requested
        if ( $full_detail ) {
            $data['content'] = get_the_content( null, false, $post_id );
            $data['nutrition'] = [
                'calories' => get_post_meta( $post_id, '_kg_calories', true ),
                'protein'  => get_post_meta( $post_id, '_kg_protein', true ),
                'fiber'    => get_post_meta( $post_id, '_kg_fiber', true ),
                'vitamins' => get_post_meta( $post_id, '_kg_vitamins', true ),
            ];
            $data['video_url'] = get_post_meta( $post_id, '_kg_video_url', true );
            
            $substitutes_raw = get_post_meta( $post_id, '_kg_substitutes', true );
            $data['substitutes'] = !empty($substitutes_raw) ? maybe_unserialize($substitutes_raw) : [];
            
            $data['expert'] = [
                'name'     => get_post_meta( $post_id, '_kg_expert_name', true ),
                'title'    => get_post_meta( $post_id, '_kg_expert_title', true ),
                'approved' => get_post_meta( $post_id, '_kg_expert_approved', true ) === '1',
            ];
            
            $data['related_recipes'] = $this->get_related_recipes( $post_id );
            $data['cross_sell'] = $this->get_cross_sell_suggestion( $post_id );
        } else {
            $data['expert'] = [
                'name'     => get_post_meta( $post_id, '_kg_expert_name', true ),
                'approved' => get_post_meta( $post_id, '_kg_expert_approved', true ) === '1',
            ];
        }
        
        return $data;
    }
    
    public function filter_recipes( $request ) {
        // Implementation for advanced filtering
        return []; 
    }

    /**
     * Get all recipes with pagination and filtering
     */
    public function get_recipes( $request ) {
        $page = $request->get_param( 'page' ) ?: 1;
        $per_page = $request->get_param( 'per_page' ) ?: 10;
        $age_group = $request->get_param( 'age_group' );
        $diet_type = $request->get_param( 'diet_type' );
        $allergen = $request->get_param( 'allergen' );
        
        $args = [
            'post_type'      => 'recipe',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ];

        // Add tax query if filters provided
        $tax_query = [];
        if ( $age_group ) {
            $tax_query[] = [
                'taxonomy' => 'age-group',
                'field'    => 'slug',
                'terms'    => $age_group,
            ];
        }
        if ( $diet_type ) {
            $tax_query[] = [
                'taxonomy' => 'diet-type',
                'field'    => 'slug',
                'terms'    => $diet_type,
            ];
        }
        if ( $allergen ) {
            $tax_query[] = [
                'taxonomy' => 'allergen',
                'field'    => 'slug',
                'terms'    => $allergen,
                'operator' => 'NOT IN',
            ];
        }
        
        if ( !empty( $tax_query ) ) {
            $tax_query['relation'] = 'AND';
            $args['tax_query'] = $tax_query;
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

        return new \WP_REST_Response( [
            'recipes' => $recipes,
            'total'   => $query->found_posts,
            'pages'   => $query->max_num_pages,
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
     */
    private function get_related_recipes( $post_id, $limit = 3 ) {
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
}
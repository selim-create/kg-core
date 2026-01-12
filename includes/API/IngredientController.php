<?php
namespace KG_Core\API;

class IngredientController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // GET /wp-json/kg/v1/ingredients (All ingredients)
        register_rest_route( 'kg/v1', '/ingredients', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_ingredients' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /wp-json/kg/v1/ingredients/{slug} (Single ingredient by slug)
        register_rest_route( 'kg/v1', '/ingredients/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_ingredient_by_slug' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /wp-json/kg/v1/ingredients/search (Search ingredients)
        register_rest_route( 'kg/v1', '/ingredients/search', [
            'methods'  => 'GET',
            'callback' => [ $this, 'search_ingredients' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get all ingredients
     */
    public function get_ingredients( $request ) {
        $per_page = $request->get_param( 'per_page' ) ?: 20;
        $page = $request->get_param( 'page' ) ?: 1;
        
        $args = [
            'post_type'      => 'ingredient',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $query = new \WP_Query( $args );
        $ingredients = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $ingredients[] = $this->prepare_ingredient_data( get_the_ID(), false );
            }
        }
        wp_reset_postdata();

        return new \WP_REST_Response( [
            'ingredients' => $ingredients,
            'total'       => $query->found_posts,
            'pages'       => $query->max_num_pages,
        ], 200 );
    }

    /**
     * Get single ingredient by slug with full details
     */
    public function get_ingredient_by_slug( $request ) {
        $slug = $request->get_param( 'slug' );
        
        $args = [
            'name'        => $slug,
            'post_type'   => 'ingredient',
            'post_status' => 'publish',
            'numberposts' => 1,
        ];

        $posts = get_posts( $args );
        
        if ( empty( $posts ) ) {
            return new \WP_Error( 'ingredient_not_found', 'Ingredient not found', [ 'status' => 404 ] );
        }

        $ingredient = $this->prepare_ingredient_data( $posts[0]->ID, true );
        
        return new \WP_REST_Response( $ingredient, 200 );
    }

    /**
     * Search ingredients
     */
    public function search_ingredients( $request ) {
        $query_string = $request->get_param( 'q' );
        
        if ( empty( $query_string ) ) {
            return new \WP_Error( 'missing_query', 'Search query is required', [ 'status' => 400 ] );
        }

        $args = [
            'post_type'      => 'ingredient',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            's'              => $query_string,
        ];

        $query = new \WP_Query( $args );
        $ingredients = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $ingredients[] = $this->prepare_ingredient_data( get_the_ID(), false );
            }
        }
        wp_reset_postdata();

        return new \WP_REST_Response( $ingredients, 200 );
    }

    /**
     * Prepare ingredient data for API response
     */
    private function prepare_ingredient_data( $post_id, $full_detail = false ) {
        $data = [
            'id'          => $post_id,
            'name'        => get_the_title( $post_id ),
            'slug'        => get_post_field( 'post_name', $post_id ),
            'description' => get_the_excerpt( $post_id ),
            'image'       => get_the_post_thumbnail_url( $post_id, 'large' ),
            'start_age'   => get_post_meta( $post_id, '_kg_start_age', true ),
        ];

        if ( $full_detail ) {
            $data['description'] = get_the_content( null, false, $post_id );
            $data['benefits'] = get_post_meta( $post_id, '_kg_benefits', true );
            
            $prep_methods_raw = get_post_meta( $post_id, '_kg_prep_methods', true );
            $data['prep_methods'] = !empty($prep_methods_raw) ? maybe_unserialize($prep_methods_raw) : [];
            
            $data['allergy_risk'] = get_post_meta( $post_id, '_kg_allergy_risk', true );
            $data['season'] = get_post_meta( $post_id, '_kg_season', true );
            $data['storage_tips'] = get_post_meta( $post_id, '_kg_storage_tips', true );
            
            $faq_raw = get_post_meta( $post_id, '_kg_faq', true );
            $data['faq'] = !empty($faq_raw) ? maybe_unserialize($faq_raw) : [];
            
            $data['related_recipes'] = $this->get_recipes_by_ingredient( $post_id );
        }

        return $data;
    }

    /**
     * Get recipes that use this ingredient
     */
    private function get_recipes_by_ingredient( $ingredient_id, $limit = 5 ) {
        $ingredient_name = get_the_title( $ingredient_id );
        
        // Search in recipe ingredients meta field
        $args = [
            'post_type'      => 'recipe',
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            's'              => $ingredient_name,
        ];

        $query = new \WP_Query( $args );
        $recipes = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $recipes[] = [
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                    'slug'  => get_post_field( 'post_name', get_the_ID() ),
                    'image' => get_the_post_thumbnail_url( get_the_ID(), 'medium' ),
                ];
            }
        }
        wp_reset_postdata();

        return $recipes;
    }
}

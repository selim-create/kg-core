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

        // GET /wp-json/kg/v1/ingredients/search (Search ingredients) - Register BEFORE slug route
        register_rest_route( 'kg/v1', '/ingredients/search', [
            'methods'  => 'GET',
            'callback' => [ $this, 'search_ingredients' ],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ]
        ]);

        // GET /wp-json/kg/v1/ingredients/{slug} (Single ingredient by slug) - Exclude 'search' keyword
        register_rest_route( 'kg/v1', '/ingredients/(?P<slug>(?!search)[a-zA-Z0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_ingredient_by_slug' ],
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
        // Use Helper class for HTML entity decoding
        $name = \KG_Core\Utils\Helper::decode_html_entities( get_the_title( $post_id ) );
        
        // Get category from taxonomy instead of meta field
        $category_terms = wp_get_post_terms( $post_id, 'ingredient-category' );
        $category = '';
        if ( !is_wp_error($category_terms) && !empty($category_terms) ) {
            $category = \KG_Core\Utils\Helper::decode_html_entities( $category_terms[0]->name );
        }
        
        $data = [
            'id'          => $post_id,
            'name'        => $name,
            'slug'        => get_post_field( 'post_name', $post_id ),
            'description' => get_the_excerpt( $post_id ),
            'image'       => get_the_post_thumbnail_url( $post_id, 'large' ),
            'start_age'   => get_post_meta( $post_id, '_kg_start_age', true ),
            'category'    => $category,
        ];

        if ( $full_detail ) {
            $data['description'] = get_the_content( null, false, $post_id );
            
            // Description and benefits as HTML
            $data['description_html'] = apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );
            $data['benefits'] = get_post_meta( $post_id, '_kg_benefits', true );
            $data['benefits_html'] = wpautop( get_post_meta( $post_id, '_kg_benefits', true ) );
            
            $prep_methods_raw = get_post_meta( $post_id, '_kg_prep_methods', true );
            $data['prep_methods'] = !empty($prep_methods_raw) ? maybe_unserialize($prep_methods_raw) : [];
            
            // New fields: prep_by_age
            $prep_by_age_raw = get_post_meta( $post_id, '_kg_prep_by_age', true );
            $data['prep_by_age'] = !empty($prep_by_age_raw) ? maybe_unserialize($prep_by_age_raw) : [];
            
            $pairings_raw = get_post_meta( $post_id, '_kg_pairings', true );
            $data['pairings'] = !empty($pairings_raw) ? maybe_unserialize($pairings_raw) : [];
            
            $data['selection_tips'] = get_post_meta( $post_id, '_kg_selection_tips', true );
            $data['pro_tips'] = get_post_meta( $post_id, '_kg_pro_tips', true );
            $data['preparation_tips'] = get_post_meta( $post_id, '_kg_preparation_tips', true );
            
            $data['allergy_risk'] = get_post_meta( $post_id, '_kg_allergy_risk', true );
            $data['season'] = get_post_meta( $post_id, '_kg_season', true );
            $data['storage_tips'] = get_post_meta( $post_id, '_kg_storage_tips', true );
            
            // Consolidated Nutrition data (100g per serving - primary source)
            $data['nutrition'] = [
                'calories' => get_post_meta( $post_id, '_kg_ing_calories_100g', true ),
                'protein' => get_post_meta( $post_id, '_kg_ing_protein_100g', true ),
                'carbs' => get_post_meta( $post_id, '_kg_ing_carbs_100g', true ),
                'fat' => get_post_meta( $post_id, '_kg_ing_fat_100g', true ),
                'fiber' => get_post_meta( $post_id, '_kg_ing_fiber_100g', true ),
                'sugar' => get_post_meta( $post_id, '_kg_ing_sugar_100g', true ),
                'vitamins' => get_post_meta( $post_id, '_kg_ing_vitamins', true ),
                'minerals' => get_post_meta( $post_id, '_kg_ing_minerals', true ),
            ];
            
            // Allergen info (simplified - taxonomy-based)
            $data['allergen_info'] = [
                'cross_contamination_risk' => get_post_meta( $post_id, '_kg_cross_contamination', true ),
                'allergy_symptoms' => get_post_meta( $post_id, '_kg_allergy_symptoms', true ),
                'alternative_ingredients' => get_post_meta( $post_id, '_kg_alternatives', true ),
            ];
            
            $faq_raw = get_post_meta( $post_id, '_kg_faq', true );
            $data['faq'] = !empty($faq_raw) ? maybe_unserialize($faq_raw) : [];
            
            // Allergens taxonomy
            $allergen_terms = wp_get_post_terms( $post_id, 'allergen' );
            $data['allergens'] = [];
            if ( !is_wp_error($allergen_terms) && !empty($allergen_terms) ) {
                foreach ( $allergen_terms as $term ) {
                    $data['allergens'][] = $term->name;
                }
            }
            
            // Image metadata
            $thumbnail_id = get_post_thumbnail_id( $post_id );
            if ( $thumbnail_id ) {
                $data['image_credit'] = get_post_meta( $post_id, '_kg_image_credit', true );
                $data['image_source'] = get_post_meta( $post_id, '_kg_image_source', true );
                $data['ai_generated'] = get_post_meta( $thumbnail_id, '_kg_ai_generated', true ) ? true : false;
            }
            
            $data['related_recipes'] = $this->get_recipes_by_ingredient( $post_id );
            
            // Add SEO data
            $data['seo'] = $this->get_seo_data( $post_id );
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
}

<?php
namespace KG_Core\API;

class RecipeController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
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
    private function prepare_recipe_data( $post_id ) {
        // Standart WP fonksiyonları ile meta verisini çek
        // true parametresi tekil değer döndürür, false ise array döner (repeater gibi yapılar için)
        $prep_time = get_post_meta($post_id, '_kg_prep_time', true);
        
        // Malzemeler genellikle JSON string veya serialized array olarak saklanır
        $ingredients_raw = get_post_meta($post_id, '_kg_ingredients', true);
        $ingredients = !empty($ingredients_raw) ? maybe_unserialize($ingredients_raw) : [];

        // Adımlar
        $instructions_raw = get_post_meta($post_id, '_kg_instructions', true);
        $instructions = !empty($instructions_raw) ? maybe_unserialize($instructions_raw) : [];
        
        return [
            'id'            => $post_id,
            'title'         => get_the_title( $post_id ),
            'slug'          => get_post_field( 'post_name', $post_id ),
            'image'         => get_the_post_thumbnail_url( $post_id, 'large' ),
            'prep_time'     => $prep_time,
            'ingredients'   => $ingredients,
            'instructions'  => $instructions,
            'age_groups'    => wp_get_post_terms( $post_id, 'age-group', ['fields' => 'names'] ),
            'expert'        => [
                'name' => 'Dyt. Ayşe Yılmaz', // Bu da bir meta alanından gelebilir
                'approved' => true
            ]
        ];
    }
    
    public function filter_recipes( $request ) {
        // Implementation for advanced filtering
        return []; 
    }
}
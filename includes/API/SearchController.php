<?php
namespace KG_Core\API;

class SearchController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'kg/v1', '/search', [
            'methods'  => 'GET',
            'callback' => [ $this, 'search_items' ],
            'permission_callback' => '__return_true',
        ]);
    }

    public function search_items( $request ) {
        $query = $request->get_param( 'q' );
        $type = $request->get_param( 'type' ) ?: 'all'; // 'recipe', 'ingredient', or 'all'
        $age_filter = $request->get_param( 'age_group' );
        
        if ( empty( $query ) ) {
            return new \WP_Error( 'missing_query', 'Search query is required', [ 'status' => 400 ] );
        }

        $post_types = [];
        if ( $type === 'recipe' ) {
            $post_types = ['recipe'];
        } elseif ( $type === 'ingredient' ) {
            $post_types = ['ingredient'];
        } else {
            $post_types = ['recipe', 'ingredient'];
        }

        $args = [
            'post_type' => $post_types,
            's'         => $query,
            'posts_per_page' => 20,
            'post_status' => 'publish',
        ];

        // Add age filter if specified (only for recipes)
        if ( $age_filter && in_array( 'recipe', $post_types ) ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'age-group',
                    'field'    => 'slug',
                    'terms'    => $age_filter,
                ],
            ];
        }

        $search_query = new \WP_Query( $args );
        $results = [];

        if ( $search_query->have_posts() ) {
            while ( $search_query->have_posts() ) {
                $search_query->the_post();
                $post_type = get_post_type();
                
                $result = [
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                    'slug'  => get_post_field( 'post_name', get_the_ID() ),
                    'type'  => $post_type,
                    'image' => get_the_post_thumbnail_url( get_the_ID(), 'medium' ),
                ];

                // Add type-specific data
                if ( $post_type === 'recipe' ) {
                    $result['prep_time'] = get_post_meta( get_the_ID(), '_kg_prep_time', true );
                    $result['age_groups'] = wp_get_post_terms( get_the_ID(), 'age-group', ['fields' => 'names'] );
                } elseif ( $post_type === 'ingredient' ) {
                    $result['start_age'] = get_post_meta( get_the_ID(), '_kg_start_age', true );
                }

                $results[] = $result;
            }
        }
        wp_reset_postdata();
        
        return new \WP_REST_Response( [
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'total' => $search_query->found_posts,
        ], 200 );
    }
}
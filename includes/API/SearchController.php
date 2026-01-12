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
        
        // Placeholder search logic (Standard WP Query)
        // In production, this might connect to Algolia or Meilisearch
        $args = [
            'post_type' => ['recipe', 'post'], // Search in recipes and blog posts
            's'         => $query,
            'posts_per_page' => 10
        ];

        $search_query = new \WP_Query( $args );
        $results = [];

        if ( $search_query->have_posts() ) {
            while ( $search_query->have_posts() ) {
                $search_query->the_post();
                $results[] = [
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                    'type'  => get_post_type(),
                    'link'  => get_permalink()
                ];
            }
        }
        
        return new \WP_REST_Response( $results, 200 );
    }
}
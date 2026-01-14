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
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Search query string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'all',
                    'enum' => ['all', 'recipe', 'ingredient', 'post', 'discussion'],
                    'description' => 'Filter by content type',
                ],
                'age_group' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Filter recipes by age group slug',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 50,
                    'description' => 'Results per page',
                ],
            ],
        ]);
    }

    public function search_items( $request ) {
        $query = $request->get_param( 'q' );
        $type = $request->get_param( 'type' ) ?: 'all';
        $age_filter = $request->get_param( 'age_group' );
        $per_page = $request->get_param( 'per_page' ) ?: 20;
        
        if ( empty( $query ) ) {
            return new \WP_Error( 'missing_query', 'Search query is required', [ 'status' => 400 ] );
        }

        // Extended post types support
        $post_types = [];
        switch ( $type ) {
            case 'recipe':
                $post_types = ['recipe'];
                break;
            case 'ingredient':
                $post_types = ['ingredient'];
                break;
            case 'post':
                $post_types = ['post'];
                break;
            case 'discussion':
                $post_types = ['discussion'];
                break;
            default: // 'all'
                $post_types = ['recipe', 'ingredient', 'post', 'discussion'];
                break;
        }

        $args = [
            'post_type' => $post_types,
            's'         => $query,
            'posts_per_page' => $per_page,
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
        
        // Categorized results structure
        $categorized = [
            'recipes' => [],
            'ingredients' => [],
            'posts' => [],
            'discussions' => [],
        ];

        if ( $search_query->have_posts() ) {
            while ( $search_query->have_posts() ) {
                $search_query->the_post();
                $post_id = get_the_ID();
                $post_type = get_post_type();
                
                $result = [
                    'id'    => $post_id,
                    'title' => html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ),
                    'slug'  => get_post_field( 'post_name', $post_id ),
                    'type'  => $post_type,
                    'image' => get_the_post_thumbnail_url( $post_id, 'medium' ) ?: null,
                    'excerpt' => wp_trim_words( get_the_excerpt(), 20, '...' ),
                ];

                // Add type-specific data
                switch ( $post_type ) {
                    case 'recipe':
                        $result['prep_time'] = get_post_meta( $post_id, '_kg_prep_time', true ) ?: null;
                        $result['cook_time'] = get_post_meta( $post_id, '_kg_cook_time', true ) ?: null;
                        $result['age_groups'] = wp_get_post_terms( $post_id, 'age-group', ['fields' => 'names'] );
                        $result['meal_types'] = wp_get_post_terms( $post_id, 'meal-type', ['fields' => 'names'] );
                        $categorized['recipes'][] = $result;
                        break;
                        
                    case 'ingredient':
                        $result['start_age'] = get_post_meta( $post_id, '_kg_start_age', true ) ?: null;
                        $result['allergy_risk'] = get_post_meta( $post_id, '_kg_allergy_risk', true ) ?: null;
                        $result['season'] = get_post_meta( $post_id, '_kg_season', true ) ?: null;
                        $categorized['ingredients'][] = $result;
                        break;
                        
                    case 'post':
                        $result['author'] = get_the_author();
                        $result['author_avatar'] = get_avatar_url( get_the_author_meta('ID'), ['size' => 48] );
                        $result['date'] = get_the_date( 'c' );
                        $result['read_time'] = $this->calculate_read_time( get_the_content() );
                        $categories = wp_get_post_categories( $post_id, ['fields' => 'names'] );
                        $result['categories'] = $categories;
                        $categorized['posts'][] = $result;
                        break;
                        
                    case 'discussion':
                        $result['author'] = get_the_author();
                        $result['author_avatar'] = get_avatar_url( get_the_author_meta('ID'), ['size' => 48] );
                        $result['date'] = get_the_date( 'c' );
                        $result['comment_count'] = get_comments_number( $post_id );
                        $circles = wp_get_post_terms( $post_id, 'circle', ['fields' => 'names'] );
                        $result['circles'] = $circles;
                        $categorized['discussions'][] = $result;
                        break;
                }

                $results[] = $result;
            }
        }
        wp_reset_postdata();
        
        // Calculate counts
        $counts = [
            'total' => count( $results ),
            'recipes' => count( $categorized['recipes'] ),
            'ingredients' => count( $categorized['ingredients'] ),
            'posts' => count( $categorized['posts'] ),
            'discussions' => count( $categorized['discussions'] ),
        ];
        
        return new \WP_REST_Response( [
            'success' => true,
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'categorized' => $categorized,
            'counts' => $counts,
            'total' => $search_query->found_posts,
        ], 200 );
    }
    
    /**
     * Calculate estimated read time for content
     * 
     * @param string $content Post content
     * @return string Formatted read time (e.g., "5 dk")
     */
    private function calculate_read_time( $content ) {
        $word_count = str_word_count( strip_tags( $content ) );
        $minutes = max( 1, ceil( $word_count / 200 ) ); // Average 200 words per minute
        return $minutes . ' dk';
    }
}
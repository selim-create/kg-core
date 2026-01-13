<?php
namespace KG_Core\API;

class FeaturedController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // GET /wp-json/kg/v1/featured
        register_rest_route( 'kg/v1', '/featured', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_featured_content' ],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => [
                    'default' => 5,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0 && $param <= 50;
                    }
                ],
                'type' => [
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function( $param ) {
                        return in_array( $param, [ 'all', 'recipe', 'post', 'question', 'sponsor' ] );
                    }
                ]
            ]
        ]);
    }

    public function get_featured_content( $request ) {
        $limit = $request->get_param( 'limit' );
        $type = $request->get_param( 'type' );
        
        $featured = [];
        
        // Featured recipes
        if ( $type === 'all' || $type === 'recipe' ) {
            $recipes = $this->get_featured_recipes( $limit );
            $featured = array_merge( $featured, $recipes );
        }
        
        // Featured posts (blog posts / guides)
        if ( $type === 'all' || $type === 'post' ) {
            $posts = $this->get_featured_posts( $limit );
            $featured = array_merge( $featured, $posts );
        }
        
        // Featured questions (discussions)
        if ( $type === 'all' || $type === 'question' ) {
            $questions = $this->get_featured_questions( $limit );
            $featured = array_merge( $featured, $questions );
        }
        
        // Sponsored content
        if ( $type === 'all' || $type === 'sponsor' ) {
            $sponsors = $this->get_sponsored_content( $limit );
            $featured = array_merge( $featured, $sponsors );
        }
        
        // Sort by date descending
        usort( $featured, function( $a, $b ) {
            return strtotime( $b['date'] ) - strtotime( $a['date'] );
        });
        
        // Limit final results
        $featured = array_slice( $featured, 0, $limit );
        
        return new \WP_REST_Response( [
            'success' => true,
            'data' => $featured
        ], 200 );
    }
    
    private function get_featured_recipes( $limit ) {
        $args = [
            'post_type' => 'recipe',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_kg_is_featured',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $query = new \WP_Query( $args );
        $recipes = [];
        
        foreach ( $query->posts as $post ) {
            $age_groups = wp_get_post_terms( $post->ID, 'age-group' );
            $age_group = !empty( $age_groups ) ? $age_groups[0] : null;
            
            // Get age group color from term meta
            $age_group_color = '#87CEEB'; // Default color
            if ( $age_group ) {
                $stored_color = get_term_meta( $age_group->term_id, '_kg_color_code', true );
                if ( $stored_color ) {
                    $age_group_color = $stored_color;
                }
            }
            
            $meal_types = wp_get_post_terms( $post->ID, 'meal-type' );
            $diet_types = wp_get_post_terms( $post->ID, 'diet-type' );
            
            // Decode HTML entities for title and excerpt
            $title = \KG_Core\Utils\Helper::decode_html_entities( $post->post_title );
            $excerpt = \KG_Core\Utils\Helper::decode_html_entities( wp_trim_words( $post->post_excerpt, 20 ) );
            
            $recipes[] = [
                'id' => $post->ID,
                'type' => 'recipe',
                'title' => $title,
                'slug' => $post->post_name,
                'image' => get_the_post_thumbnail_url( $post->ID, 'large' ),
                'excerpt' => $excerpt,
                'date' => get_the_date( 'c', $post->ID ),
                'meta' => [
                    'age_group' => $age_group ? $age_group->name : '',
                    'age_group_color' => $age_group_color,
                    'prep_time' => get_post_meta( $post->ID, '_kg_prep_time', true ) ?: '15 dk',
                    'rating' => (float) get_post_meta( $post->ID, '_kg_rating', true ) ?: 0,
                    'rating_count' => (int) get_post_meta( $post->ID, '_kg_rating_count', true ) ?: 0,
                    'meal_type' => !empty( $meal_types ) ? $meal_types[0]->name : '',
                    'diet_types' => array_map( function( $t ) { return $t->name; }, $diet_types ),
                    'expert' => [
                        'name' => get_post_meta( $post->ID, '_kg_expert_name', true ) ?: '',
                        'title' => get_post_meta( $post->ID, '_kg_expert_title', true ) ?: '',
                        'approved' => get_post_meta( $post->ID, '_kg_expert_approved', true ) === '1'
                    ]
                ]
            ];
        }
        
        wp_reset_postdata();
        return $recipes;
    }
    
    private function get_featured_posts( $limit ) {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_kg_is_featured',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $query = new \WP_Query( $args );
        $posts = [];
        
        foreach ( $query->posts as $post ) {
            // Get author info
            $author = get_userdata( $post->post_author );
            $author_name = $author ? $author->display_name : 'KidsGourmet Editörü';
            
            // Get category
            $categories = get_the_category( $post->ID );
            $category_name = !empty( $categories ) ? $categories[0]->name : '';
            
            // Calculate read time
            $content = strip_tags( $post->post_content );
            $word_count = str_word_count( $content );
            $read_time = ceil( $word_count / 200 ) . ' dk';
            
            // Decode HTML entities
            $title = \KG_Core\Utils\Helper::decode_html_entities( $post->post_title );
            $excerpt = \KG_Core\Utils\Helper::decode_html_entities( wp_trim_words( $post->post_excerpt, 20 ) );
            
            $posts[] = [
                'id' => $post->ID,
                'type' => 'post',
                'title' => $title,
                'slug' => $post->post_name,
                'image' => get_the_post_thumbnail_url( $post->ID, 'large' ),
                'excerpt' => $excerpt,
                'date' => get_the_date( 'c', $post->ID ),
                'meta' => [
                    'category' => $category_name,
                    'author' => $author_name,
                    'read_time' => $read_time
                ]
            ];
        }
        
        wp_reset_postdata();
        return $posts;
    }
    
    private function get_featured_questions( $limit ) {
        $args = [
            'post_type' => 'discussion',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_kg_is_featured',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $query = new \WP_Query( $args );
        $questions = [];
        
        foreach ( $query->posts as $post ) {
            // Get author info
            $author = get_userdata( $post->post_author );
            $author_name = $author ? $author->display_name : 'Anonim';
            
            // Generate initials from author name
            $name_parts = explode( ' ', $author_name );
            $initials = '';
            if ( count( $name_parts ) >= 2 ) {
                $initials = strtoupper( substr( $name_parts[0], 0, 1 ) . substr( $name_parts[1], 0, 1 ) );
            } else {
                $initials = strtoupper( substr( $author_name, 0, 2 ) );
            }
            
            // Get answer count (comments count)
            $answer_count = get_post_meta( $post->ID, '_kg_answer_count', true );
            if ( !$answer_count ) {
                $answer_count = get_comments_number( $post->ID );
            }
            
            // Decode HTML entities
            $title = \KG_Core\Utils\Helper::decode_html_entities( $post->post_title );
            
            $questions[] = [
                'id' => $post->ID,
                'type' => 'question',
                'title' => $title,
                'slug' => $post->post_name,
                'date' => get_the_date( 'c', $post->ID ),
                'meta' => [
                    'author_name' => $author_name,
                    'author_initials' => $initials,
                    'answer_count' => (int) $answer_count
                ]
            ];
        }
        
        wp_reset_postdata();
        return $questions;
    }
    
    private function get_sponsored_content( $limit ) {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_kg_is_sponsored',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $query = new \WP_Query( $args );
        $sponsors = [];
        
        foreach ( $query->posts as $post ) {
            // Get sponsor data
            $sponsor_name = get_post_meta( $post->ID, '_kg_sponsor_name', true );
            $sponsor_url = get_post_meta( $post->ID, '_kg_sponsor_url', true );
            
            // Get sponsor logos and convert to URLs
            $sponsor_logo_id = get_post_meta( $post->ID, '_kg_sponsor_logo', true );
            $sponsor_light_logo_id = get_post_meta( $post->ID, '_kg_sponsor_light_logo', true );
            
            $sponsor_logo = '';
            if ( $sponsor_logo_id ) {
                $logo_url = wp_get_attachment_url( $sponsor_logo_id );
                $sponsor_logo = $logo_url ? (string) $logo_url : '';
            }
            
            $sponsor_light_logo = '';
            if ( $sponsor_light_logo_id ) {
                $light_logo_url = wp_get_attachment_url( $sponsor_light_logo_id );
                $sponsor_light_logo = $light_logo_url ? (string) $light_logo_url : '';
            }
            
            // Get category
            $categories = get_the_category( $post->ID );
            $category_name = !empty( $categories ) ? $categories[0]->name : '';
            
            // Check for discount
            $has_discount = get_post_meta( $post->ID, '_kg_has_discount', true ) === '1';
            $discount_text = get_post_meta( $post->ID, '_kg_discount_text', true ) ?: 'İndirim';
            
            // Decode HTML entities
            $title = \KG_Core\Utils\Helper::decode_html_entities( $post->post_title );
            $excerpt = \KG_Core\Utils\Helper::decode_html_entities( wp_trim_words( $post->post_excerpt, 20 ) );
            
            $sponsors[] = [
                'id' => $post->ID,
                'type' => 'sponsor',
                'title' => $title,
                'slug' => $post->post_name,
                'image' => get_the_post_thumbnail_url( $post->ID, 'large' ),
                'excerpt' => $excerpt,
                'date' => get_the_date( 'c', $post->ID ),
                'meta' => [
                    'sponsor_name' => $sponsor_name ?: '',
                    'sponsor_logo' => $sponsor_logo,
                    'sponsor_light_logo' => $sponsor_light_logo,
                    'sponsor_url' => $sponsor_url ?: '',
                    'category' => $category_name,
                    'has_discount' => $has_discount,
                    'discount_text' => $discount_text
                ]
            ];
        }
        
        wp_reset_postdata();
        return $sponsors;
    }
}

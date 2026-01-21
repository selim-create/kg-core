<?php
namespace KG_Core\API;

class PostController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // GET /wp-json/kg/v1/posts (All posts)
        register_rest_route( 'kg/v1', '/posts', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_posts' ],
            'permission_callback' => '__return_true',
        ]);

        // GET /wp-json/kg/v1/posts/{slug} (Single post by slug)
        register_rest_route( 'kg/v1', '/posts/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_post_by_slug' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get all posts
     */
    public function get_posts( $request ) {
        $per_page = $request->get_param( 'per_page' ) ?: 20;
        $page = $request->get_param( 'page' ) ?: 1;
        
        $args = [
            'post_type'      => 'post',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query( $args );
        $posts = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $posts[] = $this->prepare_post_data( get_the_ID(), false );
            }
        }
        wp_reset_postdata();

        return new \WP_REST_Response( [
            'posts' => $posts,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ], 200 );
    }

    /**
     * Get single post by slug with full details
     */
    public function get_post_by_slug( $request ) {
        $slug = $request->get_param( 'slug' );
        
        $args = [
            'name'        => $slug,
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 1,
        ];

        $posts = get_posts( $args );
        
        if ( empty( $posts ) ) {
            return new \WP_Error( 'post_not_found', 'Post not found', [ 'status' => 404 ] );
        }

        $post = $this->prepare_post_data( $posts[0]->ID, true );

        return new \WP_REST_Response( $post, 200 );
    }

    /**
     * Prepare post data for API response
     */
    private function prepare_post_data( $post_id, $full_detail = false ) {
        $post = get_post( $post_id );
        
        // Get author info
        $author = get_userdata( $post->post_author );
        $author_data = null;
        if ( $author ) {
            $author_data = [
                'id' => $post->post_author,
                'name' => $author->display_name,
                'slug' => $author->user_nicename,
                'avatar' => \KG_Core\Utils\Helper::get_user_avatar_url( $post->post_author ),
            ];
        }
        
        // Get category
        $categories = get_the_category( $post_id );
        $category_data = null;
        if ( !empty( $categories ) ) {
            $category_data = [
                'name' => \KG_Core\Utils\Helper::decode_html_entities( $categories[0]->name ),
                'slug' => $categories[0]->slug,
            ];
        }
        
        // Calculate read time
        $content = strip_tags( $post->post_content );
        $word_count = str_word_count( $content );
        $read_time = max( 1, ceil( $word_count / 200 ) );
        
        // Expert data
        $expert_data = $this->get_expert_data( $post_id, $full_detail );
        
        // Decode HTML entities
        $title = \KG_Core\Utils\Helper::decode_html_entities( $post->post_title );
        $excerpt_text = $post->post_excerpt ?: $post->post_content;
        $excerpt = \KG_Core\Utils\Helper::decode_html_entities( wp_trim_words( $excerpt_text, 20 ) );
        
        $data = [
            'id' => $post_id,
            'title' => $title,
            'slug' => $post->post_name,
            'excerpt' => $excerpt,
            'image' => get_the_post_thumbnail_url( $post_id, 'large' ),
            'date' => $post->post_date,
            'author' => $author_data,
            'category' => $category_data,
            'read_time' => $read_time,
            'expert' => $expert_data,
            'is_featured' => get_post_meta( $post_id, '_kg_is_featured', true ) === '1',
            'is_sponsored' => get_post_meta( $post_id, '_kg_is_sponsored', true ) === '1',
        ];
        
        // Add full details if requested
        if ( $full_detail ) {
            $data['content'] = apply_filters( 'the_content', $post->post_content );
            
            // Sponsor data
            if ( $data['is_sponsored'] ) {
                $data['sponsor'] = [
                    'name' => get_post_meta( $post_id, '_kg_sponsor_name', true ),
                    'url' => get_post_meta( $post_id, '_kg_sponsor_url', true ),
                    'logo' => wp_get_attachment_url( get_post_meta( $post_id, '_kg_sponsor_logo', true ) ),
                    'light_logo' => wp_get_attachment_url( get_post_meta( $post_id, '_kg_sponsor_light_logo', true ) ),
                    'direct_redirect' => get_post_meta( $post_id, '_kg_direct_redirect', true ) === '1',
                    'gam_impression_url' => get_post_meta( $post_id, '_kg_gam_impression_url', true ),
                    'gam_click_url' => get_post_meta( $post_id, '_kg_gam_click_url', true ),
                    'has_discount' => get_post_meta( $post_id, '_kg_has_discount', true ) === '1',
                    'discount_text' => get_post_meta( $post_id, '_kg_discount_text', true ),
                ];
            }
        }
        
        return $data;
    }

    /**
     * Get expert data for a post
     */
    private function get_expert_data( $post_id, $include_note = false ) {
        $expert_user_id = get_post_meta( $post_id, '_kg_expert_user_id', true );
        $expert_name = get_post_meta( $post_id, '_kg_expert_name', true );
        $expert_title = get_post_meta( $post_id, '_kg_expert_title', true );
        $expert_slug = '';
        $expert_image = '';
        
        if ( ! empty( $expert_user_id ) ) {
            $expert_user = get_user_by( 'ID', $expert_user_id );
            if ( $expert_user ) {
                $expert_slug = $expert_user->user_nicename;
                $expert_image = \KG_Core\Utils\Helper::get_user_avatar_url( $expert_user_id );
                if ( empty( $expert_name ) ) {
                    $expert_name = $expert_user->display_name;
                }
            }
        }
        
        $data = [
            'name' => $expert_name,
            'title' => $expert_title,
            'approved' => get_post_meta( $post_id, '_kg_expert_approved', true ) === '1',
            'slug' => $expert_slug,
            'image' => $expert_image,
            'user_id' => $expert_user_id ? intval( $expert_user_id ) : null,
        ];
        
        if ( $include_note ) {
            $data['note'] = get_post_meta( $post_id, '_kg_expert_note', true );
        }
        
        return $data;
    }
}

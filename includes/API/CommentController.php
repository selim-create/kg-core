<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;

/**
 * CommentController - Generic comment endpoints for recipes and posts
 */
class CommentController {
    
    /**
     * Allowed post types for comments
     */
    private const ALLOWED_COMMENT_TYPES = [ 'recipe', 'post', 'discussion' ];

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // GET comments for any post type
        register_rest_route( 'kg/v1', '/comments', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_comments' ],
            'permission_callback' => '__return_true',
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // POST comment to any post type
        register_rest_route( 'kg/v1', '/comments', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_comment' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'content' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'parent_id' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                ],
            ],
        ]);
        
        // Recipe-specific comment endpoints
        register_rest_route( 'kg/v1', '/recipes/(?P<id>\d+)/comments', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_recipe_comments' ],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route( 'kg/v1', '/recipes/(?P<id>\d+)/comments', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_recipe_comment' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
        
        // Post/Blog-specific comment endpoints
        register_rest_route( 'kg/v1', '/posts/(?P<id>\d+)/comments', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_post_comments' ],
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route( 'kg/v1', '/posts/(?P<id>\d+)/comments', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_post_comment' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
    }

    public function check_authentication( $request ) {
        $token = JWTHandler::get_token_from_request();
        
        if ( ! $token ) {
            return false;
        }

        $payload = JWTHandler::validate_token( $token );
        
        if ( ! $payload ) {
            return false;
        }

        $request->set_param( 'authenticated_user_id', $payload['user_id'] );
        return true;
    }
    
    private function get_authenticated_user_id( $request ) {
        return $request->get_param( 'authenticated_user_id' );
    }

    /**
     * Get comments - generic endpoint
     */
    public function get_comments( $request ) {
        $post_id = absint( $request->get_param( 'post_id' ) );
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'not_found', 'İçerik bulunamadı', [ 'status' => 404 ] );
        }
        
        // Allow comments for recipe, post, discussion
        if ( ! in_array( $post->post_type, self::ALLOWED_COMMENT_TYPES ) ) {
            return new \WP_Error( 'invalid_type', 'Bu içerik türü için yorum desteklenmiyor', [ 'status' => 400 ] );
        }
        
        return $this->fetch_comments( $post_id );
    }

    /**
     * Add comment - generic endpoint
     */
    public function add_comment( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $post_id = absint( $request->get_param( 'post_id' ) );
        $content = wp_kses_post( $request->get_param( 'content' ) );
        $parent_id = absint( $request->get_param( 'parent_id' ) ) ?: 0;
        
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'not_found', 'İçerik bulunamadı', [ 'status' => 404 ] );
        }
        
        // Allow comments for recipe, post, discussion
        if ( ! in_array( $post->post_type, self::ALLOWED_COMMENT_TYPES ) ) {
            return new \WP_Error( 'invalid_type', 'Bu içerik türü için yorum desteklenmiyor', [ 'status' => 400 ] );
        }
        
        return $this->insert_comment( $post_id, $user_id, $content, $parent_id );
    }
    
    /**
     * Get recipe comments
     */
    public function get_recipe_comments( $request ) {
        $post_id = absint( $request->get_param( 'id' ) );
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'recipe' || $post->post_status !== 'publish' ) {
            return new \WP_Error( 'not_found', 'Tarif bulunamadı', [ 'status' => 404 ] );
        }
        
        return $this->fetch_comments( $post_id );
    }
    
    /**
     * Add recipe comment
     */
    public function add_recipe_comment( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $post_id = absint( $request->get_param( 'id' ) );
        $content = wp_kses_post( $request->get_param( 'content' ) );
        $parent_id = absint( $request->get_param( 'parent_id' ) ) ?: 0;
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'recipe' || $post->post_status !== 'publish' ) {
            return new \WP_Error( 'not_found', 'Tarif bulunamadı', [ 'status' => 404 ] );
        }
        
        return $this->insert_comment( $post_id, $user_id, $content, $parent_id );
    }
    
    /**
     * Get post/blog comments
     */
    public function get_post_comments( $request ) {
        $post_id = absint( $request->get_param( 'id' ) );
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
            return new \WP_Error( 'not_found', 'Yazı bulunamadı', [ 'status' => 404 ] );
        }
        
        return $this->fetch_comments( $post_id );
    }
    
    /**
     * Add post/blog comment
     */
    public function add_post_comment( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $post_id = absint( $request->get_param( 'id' ) );
        $content = wp_kses_post( $request->get_param( 'content' ) );
        $parent_id = absint( $request->get_param( 'parent_id' ) ) ?: 0;
        
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
            return new \WP_Error( 'not_found', 'Yazı bulunamadı', [ 'status' => 404 ] );
        }
        
        return $this->insert_comment( $post_id, $user_id, $content, $parent_id );
    }
    
    /**
     * Fetch comments for a post
     */
    private function fetch_comments( $post_id ) {
        $comments = get_comments( [
            'post_id' => $post_id,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'ASC',
        ] );
        
        $result = [];
        foreach ( $comments as $comment ) {
            $result[] = $this->format_comment( $comment );
        }
        
        return new \WP_REST_Response( $result, 200 );
    }
    
    /**
     * Insert a new comment
     */
    private function insert_comment( $post_id, $user_id, $content, $parent_id ) {
        if ( empty( $content ) ) {
            return new \WP_Error( 'missing_content', 'Yorum içeriği gerekli', [ 'status' => 400 ] );
        }
        
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new \WP_Error( 'invalid_user', 'Geçersiz kullanıcı', [ 'status' => 400 ] );
        }
        
        $comment_data = [
            'comment_post_ID' => $post_id,
            'comment_content' => $content,
            'user_id' => $user_id,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_parent' => $parent_id,
            'comment_approved' => 1,
        ];
        
        $comment_id = wp_insert_comment( $comment_data );
        
        if ( ! $comment_id || is_wp_error( $comment_id ) ) {
            return new \WP_Error( 'comment_failed', 'Yorum eklenemedi', [ 'status' => 500 ] );
        }
        
        // Update comment count meta - increment by 1 for efficiency
        $current_count = (int) get_post_meta( $post_id, '_kg_comment_count', true );
        update_post_meta( $post_id, '_kg_comment_count', $current_count + 1 );
        
        $comment = get_comment( $comment_id );
        
        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Yorum eklendi',
            'comment' => $this->format_comment( $comment ),
        ], 201 );
    }
    
    /**
     * Format comment for API response
     */
    private function format_comment( $comment ) {
        $user_id = $comment->user_id;
        
        // Get user avatar using priority: custom > google > gravatar
        $avatar_url = \KG_Core\Utils\Helper::get_user_avatar_url( $user_id );
        
        return [
            'id' => (int) $comment->comment_ID,
            'content' => $comment->comment_content,
            'date' => $comment->comment_date,
            'parent_id' => (int) $comment->comment_parent,
            'author' => [
                'id' => (int) $user_id,
                'name' => $comment->comment_author,
                'avatar' => $avatar_url,
            ],
        ];
    }
}

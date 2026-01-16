<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Roles\RoleManager;

class ExpertController {
    
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }
    
    public function register_routes() {
        // Expert dashboard endpoint
        register_rest_route( 'kg/v1', '/expert/dashboard', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_dashboard' ],
            'permission_callback' => [ $this, 'check_expert_permission' ],
        ]);
        
        // Expert list endpoint (Public)
        register_rest_route( 'kg/v1', '/experts', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_experts_list' ],
            'permission_callback' => '__return_true', // Public endpoint
        ]);
    }
    
    /**
     * Check if user has expert permission
     */
    public function check_expert_permission( $request ) {
        $token = JWTHandler::get_token_from_request();
        
        if ( ! $token ) {
            return new \WP_Error( 'not_authenticated', 'Authentication required', [ 'status' => 401 ] );
        }
        
        $payload = JWTHandler::validate_token( $token );
        
        if ( ! $payload ) {
            return new \WP_Error( 'invalid_token', 'Invalid token', [ 'status' => 401 ] );
        }
        
        $user_id = $payload['user_id'];
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return new \WP_Error( 'user_not_found', 'User not found', [ 'status' => 404 ] );
        }

        // Authorized roles
        $allowed_roles = [ 'administrator', 'editor', 'kg_expert' ];
        $has_permission = array_intersect( $allowed_roles, $user->roles );
        
        if ( empty( $has_permission ) ) {
            return new \WP_Error( 'forbidden', 'You do not have permission to access this resource', [ 'status' => 403 ] );
        }
        
        // Store user ID in request for later use
        $request->set_param( 'authenticated_user_id', $user_id );
        
        return true;
    }
    
    /**
     * Get authenticated user ID from request
     */
    private function get_authenticated_user_id( $request ) {
        return $request->get_param( 'authenticated_user_id' );
    }
    
    /**
     * Get list of all expert users
     * Public endpoint - no authentication required
     * Only returns users with kg_expert role
     */
    public function get_experts_list( $request ) {
        // SADECE kg_expert rolündeki kullanıcıları getir
        $users = get_users([
            'role' => 'kg_expert',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        
        $experts = [];
        
        foreach ( $users as $user ) {
            $user_id = $user->ID;
            
            // Get user meta
            $biography = get_user_meta( $user_id, '_kg_biography', true );
            $expertise = get_user_meta( $user_id, '_kg_expertise', true );
            $social_links = get_user_meta( $user_id, '_kg_social_links', true );
            $show_email = get_user_meta( $user_id, '_kg_show_email', true );
            $avatar_id = get_user_meta( $user_id, '_kg_avatar_id', true );
            
            // Get avatar URL - önce custom avatar, yoksa gravatar
            $avatar_url = null;
            if ( $avatar_id ) {
                $avatar_url = wp_get_attachment_image_url( $avatar_id, 'medium' );
            }
            if ( ! $avatar_url ) {
                $avatar_url = get_avatar_url( $user_id, [ 'size' => 256 ] );
            }
            
            // Get user statistics
            $stats = $this->get_expert_stats( $user_id );
            
            $expert_data = [
                'id' => $user_id,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'avatar_url' => $avatar_url,
                'biography' => $biography ?: '',
                'expertise' => is_array( $expertise ) ? $expertise : [],
                'social_links' => is_array( $social_links ) ? $social_links : (object)[],
                'stats' => $stats,
            ];
            
            // Include email only if user opted in
            if ( $show_email ) {
                $expert_data['email'] = $user->user_email;
            }
            
            $experts[] = $expert_data;
        }
        
        return new \WP_REST_Response( $experts, 200 );
    }
    
    /**
     * Get statistics for an expert user
     */
    private function get_expert_stats( $user_id ) {
        // Count user's recipes
        $recipes_query = new \WP_Query([
            'post_type' => 'recipe',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => false, // We need found_posts
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $total_recipes = $recipes_query->found_posts;
        wp_reset_postdata();
        
        // Count user's blog posts
        $posts_query = new \WP_Query([
            'post_type' => 'post',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => false, // We need found_posts
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $total_posts = $posts_query->found_posts;
        wp_reset_postdata();
        
        // Count user's answers (comments on discussions)
        $total_answers = get_comments([
            'user_id' => $user_id,
            'post_type' => 'discussion',
            'status' => 'approve',
            'count' => true,
        ]);
        
        // Count user's questions (discussions authored)
        $questions_query = new \WP_Query([
            'post_type' => 'discussion',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => false, // We need found_posts
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $total_questions = $questions_query->found_posts;
        wp_reset_postdata();
        
        return [
            'total_recipes' => $total_recipes,
            'total_blog_posts' => $total_posts,
            'total_posts' => $total_posts, // Alias for compatibility
            'total_answers' => (int) $total_answers,
            'total_questions' => $total_questions,
        ];
    }
    
    /**
     * Get expert dashboard data
     */
    public function get_dashboard( $request ) {
        // Pending questions (pending discussions)
        $pending_questions = new \WP_Query([
            'post_type' => 'discussion',
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        // Pending comments
        $pending_comments = get_comments([
            'status' => 'hold',
            'count' => true,
        ]);

        // Questions answered today
        $today_start = date('Y-m-d 00:00:00');
        $today_answers = new \WP_Query([
            'post_type' => 'discussion',
            'post_status' => 'publish',
            'date_query' => [
                'after' => $today_start,
            ],
            'meta_query' => [
                [
                    'key' => '_expert_answered',
                    'value' => '1',
                ],
            ],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        // Weekly statistics
        $week_start = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $weekly_questions = new \WP_Query([
            'post_type' => 'discussion',
            'post_status' => 'publish',
            'date_query' => [
                'after' => $week_start,
            ],
            'meta_query' => [
                [
                    'key' => '_expert_answered',
                    'value' => '1',
                ],
            ],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        return new \WP_REST_Response([
            'pending_questions' => $pending_questions->found_posts,
            'pending_comments' => (int) $pending_comments,
            'today_answers' => $today_answers->found_posts,
            'weekly_stats' => [
                'questions_answered' => $weekly_questions->found_posts,
                'comments_moderated' => 0, // Placeholder
            ],
        ], 200);
    }
    
    /**
     * Get count of pending questions
     */
    private function get_pending_questions_count() {
        $args = [
            'post_type' => 'discussion',
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        
        $query = new \WP_Query( $args );
        return $query->found_posts;
    }
    
    /**
     * Get count of pending comments
     */
    private function get_pending_comments_count() {
        $args = [
            'status' => 'hold',
            'count' => true,
        ];
        
        return get_comments( $args );
    }
    
    /**
     * Get count of answers given today by expert
     */
    private function get_today_answers_count( $user_id ) {
        $today = date( 'Y-m-d' );
        
        $args = [
            'user_id' => $user_id,
            'status' => 'approve',
            'date_query' => [
                [
                    'after' => $today . ' 00:00:00',
                    'before' => $today . ' 23:59:59',
                    'inclusive' => true,
                ],
            ],
            'count' => true,
        ];
        
        return get_comments( $args );
    }
    
    /**
     * Get weekly statistics for expert
     */
    private function get_weekly_stats( $user_id ) {
        $week_ago = date( 'Y-m-d', strtotime( '-7 days' ) );
        
        // Get comments count for the week
        $args = [
            'user_id' => $user_id,
            'status' => 'approve',
            'date_query' => [
                [
                    'after' => $week_ago . ' 00:00:00',
                    'inclusive' => true,
                ],
            ],
            'count' => true,
        ];
        
        $comments_count = get_comments( $args );
        
        // Get posts count for the week (if expert can create posts)
        $posts_args = [
            'author' => $user_id,
            'post_status' => 'publish',
            'date_query' => [
                [
                    'after' => $week_ago . ' 00:00:00',
                    'inclusive' => true,
                ],
            ],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        
        $posts_query = new \WP_Query( $posts_args );
        
        return [
            'answers_count' => $comments_count,
            'posts_count' => $posts_query->found_posts,
            'period' => 'last_7_days',
        ];
    }
}

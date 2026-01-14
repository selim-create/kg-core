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

        // Yetkili roller
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
     * Get expert dashboard data
     */
    public function get_dashboard( $request ) {
        // Bekleyen sorular (pending discussions)
        $pending_questions = new \WP_Query([
            'post_type' => 'discussion',
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        // Bekleyen yorumlar
        $pending_comments = get_comments([
            'status' => 'hold',
            'count' => true,
        ]);

        // Bugün cevaplanan sorular
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

        // Haftalık istatistikler
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

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
            return false;
        }
        
        $payload = JWTHandler::validate_token( $token );
        
        if ( ! $payload ) {
            return false;
        }
        
        $user_id = $payload['user_id'];
        
        // Store user ID in request for later use
        $request->set_param( 'authenticated_user_id', $user_id );
        
        // Check if user has expert permission
        return RoleManager::has_expert_permission( $user_id );
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
        $user_id = $this->get_authenticated_user_id( $request );
        
        // Get pending questions (discussions with status 'pending')
        $pending_questions = $this->get_pending_questions_count();
        
        // Get pending comments (comments with status 'hold')
        $pending_comments = $this->get_pending_comments_count();
        
        // Get today's answers by this expert
        $today_answers = $this->get_today_answers_count( $user_id );
        
        // Get weekly stats
        $weekly_stats = $this->get_weekly_stats( $user_id );
        
        return new \WP_REST_Response( [
            'pending_questions' => $pending_questions,
            'pending_comments' => $pending_comments,
            'today_answers' => $today_answers,
            'weekly_stats' => $weekly_stats,
        ], 200 );
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

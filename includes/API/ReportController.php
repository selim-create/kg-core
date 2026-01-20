<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;

class ReportController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Submit a report
        register_rest_route( 'kg/v1', '/community/report', [
            'methods'  => 'POST',
            'callback' => [ $this, 'submit_report' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'content_type' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => [ 'discussion', 'comment' ],
                ],
                'content_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'reason' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => [ 'spam', 'inappropriate', 'harassment', 'misinformation', 'other' ],
                ],
                'description' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        // Get reports (admin only)
        register_rest_route( 'kg/v1', '/admin/reports', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_reports' ],
            'permission_callback' => [ $this, 'check_admin_authentication' ],
            'args' => [
                'status' => [
                    'type' => 'string',
                    'enum' => [ 'pending', 'reviewed', 'resolved', 'dismissed' ],
                ],
                'content_type' => [
                    'type' => 'string',
                    'enum' => [ 'discussion', 'comment' ],
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);
    }

    /**
     * Check user authentication via JWT
     */
    public function check_authentication( $request ) {
        $jwt = new JWTHandler();
        $user_id = $jwt->validate_token( $request );
        
        if ( ! $user_id ) {
            return new \WP_Error( 
                'unauthorized', 
                __( 'Yetkisiz erişim. Lütfen giriş yapın.', 'kg-core' ), 
                [ 'status' => 401 ] 
            );
        }
        
        return true;
    }

    /**
     * Check admin authentication
     */
    public function check_admin_authentication( $request ) {
        $jwt = new JWTHandler();
        $user_id = $jwt->validate_token( $request );
        
        if ( ! $user_id ) {
            return new \WP_Error( 
                'unauthorized', 
                __( 'Yetkisiz erişim.', 'kg-core' ), 
                [ 'status' => 401 ] 
            );
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user || ! in_array( 'administrator', $user->roles ) ) {
            return new \WP_Error(
                'forbidden',
                __( 'Bu işlem için yetkiniz yok.', 'kg-core' ),
                [ 'status' => 403 ]
            );
        }
        
        return true;
    }

    /**
     * Submit a report
     */
    public function submit_report( $request ) {
        global $wpdb;

        $jwt = new JWTHandler();
        $user_id = $jwt->validate_token( $request );

        $content_type = $request->get_param( 'content_type' );
        $content_id = $request->get_param( 'content_id' );
        $reason = $request->get_param( 'reason' );
        $description = $request->get_param( 'description' );

        // Rate limiting: max 10 reports per user per day
        $reports_today = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}kg_reports 
             WHERE user_id = %d 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            $user_id
        ) );

        if ( $reports_today >= 10 ) {
            return new \WP_Error(
                'rate_limit_exceeded',
                __( 'Günlük rapor limitinize ulaştınız. Lütfen yarın tekrar deneyin.', 'kg-core' ),
                [ 'status' => 429 ]
            );
        }

        // Check if content exists
        if ( $content_type === 'discussion' ) {
            $post = get_post( $content_id );
            if ( ! $post || $post->post_type !== 'discussion' ) {
                return new \WP_Error(
                    'invalid_content',
                    __( 'Geçersiz tartışma ID.', 'kg-core' ),
                    [ 'status' => 404 ]
                );
            }
        } else if ( $content_type === 'comment' ) {
            $comment = get_comment( $content_id );
            if ( ! $comment ) {
                return new \WP_Error(
                    'invalid_content',
                    __( 'Geçersiz yorum ID.', 'kg-core' ),
                    [ 'status' => 404 ]
                );
            }
        }

        // Check for duplicate report
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kg_reports 
             WHERE user_id = %d 
             AND content_type = %s 
             AND content_id = %d",
            $user_id,
            $content_type,
            $content_id
        ) );

        if ( $existing ) {
            return new \WP_Error(
                'duplicate_report',
                __( 'Bu içeriği daha önce rapor ettiniz.', 'kg-core' ),
                [ 'status' => 409 ]
            );
        }

        // Insert report
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'kg_reports',
            [
                'user_id' => $user_id,
                'content_type' => $content_type,
                'content_id' => $content_id,
                'reason' => $reason,
                'description' => $description,
                'status' => 'pending',
            ],
            [ '%d', '%s', '%d', '%s', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            return new \WP_Error(
                'database_error',
                __( 'Rapor gönderilemedi. Lütfen tekrar deneyin.', 'kg-core' ),
                [ 'status' => 500 ]
            );
        }

        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Raporunuz alındı, teşekkür ederiz.', 'kg-core' ),
        ] );
    }

    /**
     * Get reports (admin only)
     */
    public function get_reports( $request ) {
        global $wpdb;

        $status = $request->get_param( 'status' );
        $content_type = $request->get_param( 'content_type' );
        $page = $request->get_param( 'page' ) ?: 1;
        $per_page = $request->get_param( 'per_page' ) ?: 20;
        $offset = ( $page - 1 ) * $per_page;

        $where = [ '1=1' ];
        $where_values = [];

        if ( $status ) {
            $where[] = 'status = %s';
            $where_values[] = $status;
        }

        if ( $content_type ) {
            $where[] = 'content_type = %s';
            $where_values[] = $content_type;
        }

        $where_clause = implode( ' AND ', $where );

        // Get total count
        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}kg_reports WHERE {$where_clause}",
            ...$where_values
        ) );

        // Get reports
        $reports = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kg_reports 
             WHERE {$where_clause}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            array_merge( $where_values, [ $per_page, $offset ] )
        ), ARRAY_A );

        // Add user and content info
        foreach ( $reports as &$report ) {
            $user = get_user_by( 'ID', $report['user_id'] );
            $report['user'] = $user ? [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
            ] : null;

            if ( $report['content_type'] === 'discussion' ) {
                $post = get_post( $report['content_id'] );
                $report['content'] = $post ? [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => $post->post_status,
                ] : null;
            } else if ( $report['content_type'] === 'comment' ) {
                $comment = get_comment( $report['content_id'] );
                $report['content'] = $comment ? [
                    'id' => $comment->comment_ID,
                    'content' => wp_trim_words( $comment->comment_content, 20 ),
                    'status' => $comment->comment_approved,
                ] : null;
            }

            // Add reviewed_by user info
            if ( $report['reviewed_by'] ) {
                $reviewer = get_user_by( 'ID', $report['reviewed_by'] );
                $report['reviewed_by_user'] = $reviewer ? [
                    'id' => $reviewer->ID,
                    'name' => $reviewer->display_name,
                ] : null;
            }
        }

        return rest_ensure_response( [
            'reports' => $reports,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil( $total / $per_page ),
        ] );
    }
}

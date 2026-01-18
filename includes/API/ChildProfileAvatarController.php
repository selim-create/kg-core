<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Services\ChildAvatarService;
use KG_Core\Services\RateLimiter;

/**
 * ChildProfileAvatarController
 * 
 * Handles child profile avatar upload, retrieval, and deletion
 */
class ChildProfileAvatarController {
    
    private $avatar_service;
    
    public function __construct() {
        $this->avatar_service = new ChildAvatarService();
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }
    
    public function register_routes() {
        // Upload avatar
        register_rest_route( 'kg/v1', '/child-profiles/(?P<child_uuid>[a-zA-Z0-9-]+)/avatar', [
            'methods'  => 'POST',
            'callback' => [ $this, 'upload_avatar' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_uuid' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Child profile UUID',
                ],
            ],
        ]);
        
        // Get avatar (signed URL)
        register_rest_route( 'kg/v1', '/child-profiles/(?P<child_uuid>[a-zA-Z0-9-]+)/avatar', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_avatar' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_uuid' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Child profile UUID',
                ],
            ],
        ]);
        
        // Delete avatar
        register_rest_route( 'kg/v1', '/child-profiles/(?P<child_uuid>[a-zA-Z0-9-]+)/avatar', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'delete_avatar' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_uuid' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Child profile UUID',
                ],
            ],
        ]);
        
        // Serve avatar file (signed URL endpoint)
        register_rest_route( 'kg/v1', '/child-profiles/avatar-file', [
            'methods'  => 'GET',
            'callback' => [ $this, 'serve_avatar_file' ],
            'permission_callback' => '__return_true', // Public but requires valid signed URL
            'args' => [
                'path' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'expires' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'token' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
    }
    
    /**
     * Upload child avatar
     */
    public function upload_avatar( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_uuid = $request->get_param( 'child_uuid' );
        
        // Rate limiting: Max 5 uploads per minute
        $rate_check = RateLimiter::check( 'avatar_upload', $user_id, 5, 60 );
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }
        
        // Get child from user meta
        $result = $this->find_child_in_user_meta( $user_id, $child_uuid );
        if ( ! $result ) {
            return new \WP_Error(
                'child_not_found',
                'Child profile not found',
                [ 'status' => 404 ]
            );
        }
        
        $child = $result['child'];
        $child_index = $result['index'];
        $children = $result['all_children'];
        
        // Check if file was uploaded
        if ( empty( $_FILES['avatar'] ) ) {
            return new \WP_Error(
                'no_file',
                'No avatar file provided',
                [ 'status' => 400 ]
            );
        }
        
        // Delete old avatar if exists
        if ( ! empty( $child['avatar_path'] ) ) {
            $this->avatar_service->delete_avatar( $child['avatar_path'] );
        }
        
        // Upload new avatar
        $upload_result = $this->avatar_service->upload_avatar(
            $_FILES['avatar'],
            $user_id,
            $child_uuid
        );
        
        if ( is_wp_error( $upload_result ) ) {
            return $upload_result;
        }
        
        // Update child in user meta
        $children[$child_index]['avatar_path'] = $upload_result['path'];
        update_user_meta( $user_id, '_kg_children', $children );
        
        // Generate signed URL for response
        $signed_url = $this->avatar_service->get_signed_url( $upload_result['path'] );
        
        return new \WP_REST_Response([
            'message' => 'Avatar uploaded successfully',
            'avatar' => [
                'path' => $upload_result['path'],
                'url' => $signed_url,
                'uploaded_at' => $upload_result['uploaded_at'],
            ],
        ], 200 );
    }
    
    /**
     * Get child avatar (returns signed URL)
     */
    public function get_avatar( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_uuid = $request->get_param( 'child_uuid' );
        
        // Get child from user meta
        $result = $this->find_child_in_user_meta( $user_id, $child_uuid );
        if ( ! $result ) {
            return new \WP_Error(
                'child_not_found',
                'Child profile not found',
                [ 'status' => 404 ]
            );
        }
        
        $child = $result['child'];
        
        if ( ! isset( $child['avatar_path'] ) || empty( $child['avatar_path'] ) ) {
            return new \WP_Error(
                'no_avatar',
                'No avatar found for this child',
                [ 'status' => 404 ]
            );
        }
        
        // Generate signed URL
        $signed_url = $this->avatar_service->get_signed_url( $child['avatar_path'] );
        
        if ( is_wp_error( $signed_url ) ) {
            return $signed_url;
        }
        
        return new \WP_REST_Response([
            'url' => $signed_url,
            'expires_in' => 900, // 15 minutes
        ], 200 );
    }
    
    /**
     * Delete child avatar
     */
    public function delete_avatar( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_uuid = $request->get_param( 'child_uuid' );
        
        // Get child from user meta
        $result = $this->find_child_in_user_meta( $user_id, $child_uuid );
        if ( ! $result ) {
            return new \WP_Error(
                'child_not_found',
                'Child profile not found',
                [ 'status' => 404 ]
            );
        }
        
        $child = $result['child'];
        $child_index = $result['index'];
        $children = $result['all_children'];
        
        // Delete avatar file
        if ( ! empty( $child['avatar_path'] ) ) {
            $this->avatar_service->delete_avatar( $child['avatar_path'] );
        }
        
        // Update child in user meta - remove avatar_path
        $children[$child_index]['avatar_path'] = null;
        update_user_meta( $user_id, '_kg_children', $children );
        
        return new \WP_REST_Response([
            'message' => 'Avatar deleted successfully',
        ], 200 );
    }
    
    /**
     * Serve avatar file (for signed URLs)
     */
    public function serve_avatar_file( $request ) {
        $encoded_path = $request->get_param( 'path' );
        $expires = $request->get_param( 'expires' );
        $token = $request->get_param( 'token' );
        
        $result = $this->avatar_service->verify_and_serve( $encoded_path, $expires, $token );
        
        // If we reach here, there was an error (verify_and_serve exits on success)
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // This should never be reached
        return new \WP_Error(
            'unknown_error',
            'An unknown error occurred',
            [ 'status' => 500 ]
        );
    }
    
    /**
     * Check authentication
     */
    public function check_authentication( $request ) {
        $auth_header = $request->get_header( 'Authorization' );
        
        if ( ! $auth_header ) {
            return new \WP_Error(
                'rest_forbidden',
                'Authorization header is missing',
                [ 'status' => 401 ]
            );
        }
        
        $token = str_replace( 'Bearer ', '', $auth_header );
        $payload = JWTHandler::validate_token( $token );
        
        if ( ! $payload ) {
            return new \WP_Error(
                'rest_forbidden',
                'Invalid or expired token',
                [ 'status' => 401 ]
            );
        }
        
        // Store user_id in request for later use
        $request->set_param( 'authenticated_user_id', $payload['user_id'] );
        
        return true;
    }
    
    /**
     * Get authenticated user ID
     */
    private function get_authenticated_user_id( $request ) {
        // First check if already set by check_authentication
        $user_id = $request->get_param( 'authenticated_user_id' );
        if ( $user_id ) {
            return $user_id;
        }
        
        // Fallback: extract from token
        $auth_header = $request->get_header( 'Authorization' );
        if ( ! $auth_header ) {
            return null;
        }
        
        $token = str_replace( 'Bearer ', '', $auth_header );
        return JWTHandler::get_user_id_from_token( $token );
    }
    
    /**
     * Find child in user meta by UUID
     * 
     * @param int $user_id User ID
     * @param string $child_uuid Child UUID
     * @return array|null [child_data, index, all_children] or null if not found
     */
    private function find_child_in_user_meta( $user_id, $child_uuid ) {
        $children = get_user_meta( $user_id, '_kg_children', true );
        
        if ( ! is_array( $children ) ) {
            return null;
        }
        
        foreach ( $children as $index => $child ) {
            if ( isset( $child['id'] ) && $child['id'] === $child_uuid ) {
                return [ 'child' => $child, 'index' => $index, 'all_children' => $children ];
            }
        }
        
        return null;
    }
}

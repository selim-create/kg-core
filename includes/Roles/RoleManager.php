<?php
namespace KG_Core\Roles;

/**
 * Role Manager Class
 * Handles custom WordPress roles and capabilities for RBAC
 */
class RoleManager {
    
    public function __construct() {
        add_action( 'init', [ $this, 'register_custom_roles' ] );
    }
    
    /**
     * Register custom WordPress roles
     */
    public function register_custom_roles() {
        // Check if kg_expert role already exists
        if ( ! get_role( 'kg_expert' ) ) {
            add_role( 'kg_expert', 'KG Uzman', [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
                'upload_files' => true,
                'kg_answer_questions' => true,
                'kg_moderate_comments' => true,
                'kg_view_expert_dashboard' => true,
            ]);
        }
    }
    
    /**
     * Check if user has expert permission
     * 
     * @param int $user_id User ID
     * @return bool True if user has expert permission
     */
    public static function has_expert_permission( $user_id ) {
        if ( ! $user_id ) {
            return false;
        }
        
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        $allowed_roles = [ 'administrator', 'editor', 'kg_expert' ];
        
        return ! empty( array_intersect( $allowed_roles, $user->roles ) );
    }
    
    /**
     * Check if user is an administrator
     * 
     * @param int $user_id User ID
     * @return bool True if user is administrator
     */
    public static function is_administrator( $user_id ) {
        if ( ! $user_id ) {
            return false;
        }
        
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        return in_array( 'administrator', $user->roles );
    }
}

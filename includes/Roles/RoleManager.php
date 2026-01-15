<?php
namespace KG_Core\Roles;

/**
 * Role Manager Class
 * Handles custom WordPress roles and capabilities for RBAC
 */
class RoleManager {
    
    public function __construct() {
        add_action( 'init', [ $this, 'register_custom_roles' ] );
        add_filter( 'pre_option_default_role', [ $this, 'set_default_role' ] );
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
        
        // Add kg_parent role
        if ( ! get_role( 'kg_parent' ) ) {
            add_role( 'kg_parent', 'KG Ebeveyn', [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
                'upload_files' => true,
                'kg_manage_children' => true,
                'kg_ask_questions' => true,
                'kg_create_collections' => true,
            ]);
        }
    }
    
    /**
     * Set default role for new users
     * 
     * @param string $default_role Current default role
     * @return string Modified default role
     */
    public function set_default_role( $default_role ) {
        return 'kg_parent';
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
    
    /**
     * Check if user is a parent
     * 
     * @param int $user_id User ID
     * @return bool True if user has parent role
     */
    public static function is_parent( $user_id ) {
        if ( ! $user_id ) {
            return false;
        }
        
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        return in_array( 'kg_parent', $user->roles );
    }
    
    /**
     * Check if user is an expert
     * Experts include: kg_expert, author, editor, administrator
     * 
     * @param int $user_id User ID
     * @return bool True if user is an expert
     */
    public static function is_expert( $user_id ) {
        if ( ! $user_id ) {
            return false;
        }
        
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        $expert_roles = [ 'kg_expert', 'author', 'editor', 'administrator' ];
        
        return ! empty( array_intersect( $expert_roles, $user->roles ) );
    }
    
    /**
     * Get public profile path for user
     * Returns "uzman" for experts, "profil" for parents
     * 
     * @param int $user_id User ID
     * @return string Public profile path segment
     */
    public static function get_public_profile_path( $user_id ) {
        if ( self::is_expert( $user_id ) ) {
            return 'uzman';
        }
        
        return 'profil';
    }
}

<?php
namespace KG_Core\API;

/**
 * REST API Filters
 * 
 * Modifies WordPress REST API responses to include custom avatar URLs.
 * This ensures that user-uploaded profile photos (_kg_avatar_id) are returned
 * instead of Gravatar URLs in REST API responses.
 * 
 * Priority:
 * 1. _kg_avatar_id (custom uploaded avatar)
 * 2. google_avatar (Google login avatar)
 * 3. Gravatar (WordPress default)
 */
class RestApiFilters {
    
    public function __construct() {
        // User endpoint filter
        add_filter('rest_prepare_user', [$this, 'filter_user_avatar'], 10, 3);
        
        // Post type filters
        add_filter('rest_prepare_post', [$this, 'filter_post_author_avatar'], 10, 3);
        add_filter('rest_prepare_recipe', [$this, 'filter_post_author_avatar'], 10, 3);
        add_filter('rest_prepare_discussion', [$this, 'filter_post_author_avatar'], 10, 3);
        add_filter('rest_prepare_ingredient', [$this, 'filter_post_author_avatar'], 10, 3);
        
        // Pre-get avatar filter (affects all avatar URLs including embedded)
        add_filter('pre_get_avatar_data', [$this, 'filter_avatar_data'], 10, 2);
    }
    
    /**
     * Filter user avatar in REST API user responses
     * 
     * @param \WP_REST_Response $response The response object.
     * @param \WP_User $user User object.
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Modified response.
     */
    public function filter_user_avatar($response, $user, $request) {
        $data = $response->get_data();
        
        $custom_avatar = $this->get_custom_avatar_url($user->ID);
        
        if ($custom_avatar) {
            // Override all avatar sizes with custom avatar
            $data['avatar_urls'] = [
                '24' => $custom_avatar,
                '48' => $custom_avatar,
                '96' => $custom_avatar,
            ];
            
            // Add custom field for direct access
            $data['custom_avatar'] = $custom_avatar;
        }
        
        $response->set_data($data);
        return $response;
    }
    
    /**
     * Filter post author avatar in REST API post responses
     * 
     * @param \WP_REST_Response $response The response object.
     * @param \WP_Post $post Post object.
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Modified response.
     */
    public function filter_post_author_avatar($response, $post, $request) {
        $data = $response->get_data();
        
        $author_id = $post->post_author;
        if ($author_id) {
            $custom_avatar = $this->get_custom_avatar_url($author_id);
            
            if ($custom_avatar) {
                $data['author_avatar'] = $custom_avatar;
                $data['author_custom_avatar'] = $custom_avatar;
            }
        }
        
        $response->set_data($data);
        return $response;
    }
    
    /**
     * Filter avatar data before WordPress generates avatar URL
     * This affects get_avatar_url() and embedded author data
     * 
     * @param array $args Arguments passed to get_avatar_data(), after processing.
     * @param mixed $id_or_email User ID, email, or object.
     * @return array Modified arguments.
     */
    public function filter_avatar_data($args, $id_or_email) {
        // Get user ID from various input types
        $user_id = $this->get_user_id_from_id_or_email($id_or_email);
        
        if (!$user_id) {
            return $args;
        }
        
        // Check for custom avatar
        $avatar_id = get_user_meta($user_id, '_kg_avatar_id', true);
        if ($avatar_id) {
            $avatar_url = wp_get_attachment_url($avatar_id);
            if ($avatar_url) {
                $args['url'] = $avatar_url;
                $args['found_avatar'] = true;
                return $args;
            }
        }
        
        // Check for Google avatar
        $google_avatar = get_user_meta($user_id, 'google_avatar', true);
        if (!empty($google_avatar)) {
            $args['url'] = $google_avatar;
            $args['found_avatar'] = true;
            return $args;
        }
        
        // Let WordPress use Gravatar as fallback
        return $args;
    }
    
    /**
     * Get custom avatar URL for a user
     * 
     * @param int $user_id User ID.
     * @param int $size Avatar size in pixels (default: 96).
     * @return string|null Avatar URL or null if not found.
     */
    private function get_custom_avatar_url($user_id, $size = 96) {
        if (!$user_id) {
            return null;
        }
        
        // Use Helper class if available
        if (class_exists('\KG_Core\Utils\Helper') && method_exists('\KG_Core\Utils\Helper', 'get_user_avatar_url')) {
            return \KG_Core\Utils\Helper::get_user_avatar_url($user_id, $size);
        }
        
        // Fallback implementation
        // 1. Custom avatar
        $avatar_id = get_user_meta($user_id, '_kg_avatar_id', true);
        if ($avatar_id) {
            $avatar_url = wp_get_attachment_url($avatar_id);
            if ($avatar_url) {
                return $avatar_url;
            }
        }
        
        // 2. Google avatar
        $google_avatar = get_user_meta($user_id, 'google_avatar', true);
        if (!empty($google_avatar)) {
            return $google_avatar;
        }
        
        // 3. Return null to let WordPress use Gravatar
        return null;
    }
    
    /**
     * Extract user ID from various input types
     * 
     * @param mixed $id_or_email User ID, email, or object.
     * @return int|null User ID or null if not found.
     */
    private function get_user_id_from_id_or_email($id_or_email) {
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }
        
        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? $user->ID : null;
        }
        
        if ($id_or_email instanceof \WP_User) {
            return $id_or_email->ID;
        }
        
        if ($id_or_email instanceof \WP_Post) {
            return (int) $id_or_email->post_author;
        }
        
        if ($id_or_email instanceof \WP_Comment) {
            if (!empty($id_or_email->user_id)) {
                return (int) $id_or_email->user_id;
            }
            $user = get_user_by('email', $id_or_email->comment_author_email);
            return $user ? $user->ID : null;
        }
        
        return null;
    }
}

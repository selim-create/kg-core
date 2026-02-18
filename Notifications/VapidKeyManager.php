<?php
namespace KG_Core\Notifications;

/**
 * VapidKeyManager - Manage VAPID keys for Web Push notifications
 */
class VapidKeyManager {
    
    /**
     * Get VAPID public key
     * 
     * @return string|null Public key or null if not generated
     */
    public function get_public_key() {
        return get_option('kg_vapid_public_key', null);
    }
    
    /**
     * Get VAPID private key (internal use only)
     * 
     * @return string|null Private key or null if not generated
     */
    public function get_private_key() {
        return get_option('kg_vapid_private_key', null);
    }
    
    /**
     * Get VAPID subject (mailto: or https:// URL)
     * 
     * @return string Subject
     */
    public function get_subject() {
        $subject = get_option('kg_vapid_subject', null);
        
        if (!$subject) {
            // Default to site admin email
            $subject = 'mailto:' . get_option('admin_email');
            update_option('kg_vapid_subject', $subject);
        }
        
        return $subject;
    }
    
    /**
     * Generate new VAPID keys
     * 
     * @return array|WP_Error Array with public and private keys, or WP_Error on failure
     */
    public function generate_keys() {
        // Check if web-push library is available
        if (!class_exists('\Minishlink\WebPush\VAPID')) {
            return new \WP_Error(
                'library_missing',
                'Web Push library not found. Please run: composer require minishlink/web-push'
            );
        }
        
        try {
            // Generate VAPID keys
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            
            // Store keys in WordPress options
            update_option('kg_vapid_public_key', $keys['publicKey']);
            update_option('kg_vapid_private_key', $keys['privateKey']);
            
            // Set default subject if not set
            if (!get_option('kg_vapid_subject')) {
                $subject = 'mailto:' . get_option('admin_email');
                update_option('kg_vapid_subject', $subject);
            }
            
            return [
                'public_key' => $keys['publicKey'],
                'private_key' => $keys['privateKey'],
                'subject' => $this->get_subject()
            ];
            
        } catch (\Exception $e) {
            return new \WP_Error('generation_failed', $e->getMessage());
        }
    }
    
    /**
     * Check if VAPID keys exist
     * 
     * @return bool True if keys exist, false otherwise
     */
    public function keys_exist() {
        return !empty($this->get_public_key()) && !empty($this->get_private_key());
    }
    
    /**
     * Initialize VAPID keys if they don't exist
     * 
     * @return bool|WP_Error True if keys exist or were generated, WP_Error on failure
     */
    public function ensure_keys_exist() {
        if ($this->keys_exist()) {
            return true;
        }
        
        $result = $this->generate_keys();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return true;
    }
    
    /**
     * Update VAPID subject
     * 
     * @param string $subject New subject (mailto: or https://)
     * @return bool|WP_Error True on success, WP_Error on validation failure
     */
    public function update_subject($subject) {
        // Validate subject format
        if (!preg_match('/^(mailto:|https:\/\/)/', $subject)) {
            return new \WP_Error(
                'invalid_subject',
                'Subject must start with mailto: or https://'
            );
        }
        
        update_option('kg_vapid_subject', $subject);
        return true;
    }
    
    /**
     * Get all VAPID configuration (for admin display only)
     * 
     * @return array VAPID configuration
     */
    public function get_config() {
        return [
            'public_key' => $this->get_public_key(),
            'has_private_key' => !empty($this->get_private_key()),
            'subject' => $this->get_subject(),
            'keys_exist' => $this->keys_exist()
        ];
    }
}

<?php
namespace KG_Core\Notifications;

/**
 * EmailConfig - Configure WordPress email sender information
 * 
 * Customizes the "From" name and email address for all WordPress emails
 */
class EmailConfig {
    
    public function __construct() {
        add_filter('wp_mail_from', [$this, 'custom_mail_from']);
        add_filter('wp_mail_from_name', [$this, 'custom_mail_from_name']);
    }
    
    /**
     * Set custom "From" email address
     * Uses the admin email from WordPress settings
     * 
     * @param string $email Original from email
     * @return string Modified from email
     */
    public function custom_mail_from($email) {
        // Get admin email from WordPress settings
        $admin_email = get_option('admin_email');
        
        // Return admin email if available, otherwise fall back to original
        return !empty($admin_email) ? $admin_email : $email;
    }
    
    /**
     * Set custom "From" name
     * 
     * @param string $name Original from name
     * @return string Modified from name
     */
    public function custom_mail_from_name($name) {
        return 'KidsGourmet';
    }
}

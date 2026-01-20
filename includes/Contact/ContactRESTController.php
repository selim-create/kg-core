<?php
namespace KG_Core\Contact;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * ContactRESTController - REST API endpoint for contact form
 */
class ContactRESTController extends WP_REST_Controller {
    
    public function __construct() {
        $this->namespace = 'kg/v1';
        $this->rest_base = 'contact';
        
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/submit', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_submit'],
                'permission_callback' => '__return_true',
                'args' => [
                    'name' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Ad Soyad', 'kg-core'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'email' => [
                        'required' => true,
                        'type' => 'string',
                        'format' => 'email',
                        'description' => __('E-posta adresi', 'kg-core'),
                        'sanitize_callback' => 'sanitize_email',
                    ],
                    'subject' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Konu', 'kg-core'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'message' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Mesaj', 'kg-core'),
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                    'request_type' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'general',
                        'description' => __('İstek türü', 'kg-core'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * Handle contact form submission
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_submit(WP_REST_Request $request) {
        $name = $request->get_param('name');
        $email = $request->get_param('email');
        $subject = $request->get_param('subject');
        $message = $request->get_param('message');
        $request_type = $request->get_param('request_type') ?? 'general';
        
        // Validate email
        if (!is_email($email)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Geçersiz e-posta adresi.', 'kg-core'),
                'code' => 'invalid_email'
            ], 400);
        }
        
        // Prepare email content
        $to = 'iletisim@kidsgourmet.com.tr';
        $email_subject = '[KidsGourmet İletişim] ' . $subject;
        
        $email_body = "Yeni bir iletişim formu mesajı alındı:\n\n";
        $email_body .= "Ad Soyad: {$name}\n";
        $email_body .= "E-posta: {$email}\n";
        $email_body .= "İstek Türü: {$request_type}\n";
        $email_body .= "Konu: {$subject}\n\n";
        $email_body .= "Mesaj:\n{$message}\n\n";
        $email_body .= "---\n";
        $email_body .= "Bu mesaj KidsGourmet web sitesi iletişim formu üzerinden gönderilmiştir.";
        
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: KidsGourmet <noreply@kidsgourmet.com.tr>',
            'Reply-To: ' . $name . ' <' . $email . '>',
        ];
        
        // Send email
        $sent = wp_mail($to, $email_subject, $email_body, $headers);
        
        if ($sent) {
            // Log the contact submission (optional)
            $this->log_contact_submission($name, $email, $subject, $request_type);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.', 'kg-core'),
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Mesaj gönderilirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.', 'kg-core'),
                'code' => 'mail_error'
            ], 500);
        }
    }
    
    /**
     * Log contact form submission
     */
    private function log_contact_submission($name, $email, $subject, $request_type) {
        // Simple logging to options or custom table
        $log_entry = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'request_type' => $request_type,
            'timestamp' => current_time('mysql'),
            'ip' => $this->get_client_ip(),
        ];
        
        $logs = get_option('kg_contact_logs', []);
        array_unshift($logs, $log_entry);
        
        // Keep only last 100 entries
        $logs = array_slice($logs, 0, 100);
        
        update_option('kg_contact_logs', $logs);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '';
    }
}

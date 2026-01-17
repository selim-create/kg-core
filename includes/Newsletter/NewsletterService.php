<?php
namespace KG_Core\Newsletter;

use KG_Core\Notifications\EmailService;
use KG_Core\Notifications\TemplateEngine;

/**
 * NewsletterService - Business logic for newsletter subscriptions
 * 
 * Handles newsletter subscription workflow and email sending
 */
class NewsletterService {
    
    private $repository;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new NewsletterRepository();
    }
    
    /**
     * Subscribe a new email to newsletter
     * 
     * @param string $email
     * @param string|null $name
     * @param string $source
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function subscribe($email, $name = null, $source = 'website') {
        // Validate email
        if (!is_email($email)) {
            return [
                'success' => false,
                'message' => __('Geçersiz e-posta adresi.', 'kg-core'),
                'code' => 'invalid_email'
            ];
        }
        
        // Check if already subscribed
        $existing = $this->repository->findByEmail($email);
        
        if ($existing) {
            if ($existing->is_active()) {
                return [
                    'success' => false,
                    'message' => __('Bu e-posta adresi zaten bültenimize kayıtlı.', 'kg-core'),
                    'code' => 'already_subscribed'
                ];
            } elseif ($existing->is_pending()) {
                // Resend confirmation email
                $this->sendConfirmationEmail($existing);
                return [
                    'success' => true,
                    'message' => __('Onay e-postası tekrar gönderildi. Lütfen e-postanızı kontrol edin.', 'kg-core'),
                    'data' => ['status' => 'pending']
                ];
            } elseif ($existing->is_unsubscribed()) {
                // Reactivate subscription
                $existing->status = 'pending';
                $existing->confirmation_token = $this->generate_token();
                $existing->subscribed_at = current_time('mysql');
                $existing->unsubscribed_at = null;
                
                $this->repository->update($existing);
                $this->sendConfirmationEmail($existing);
                
                return [
                    'success' => true,
                    'message' => __('Abonelik talebi alındı. Lütfen e-postanızı kontrol ederek onaylayın.', 'kg-core'),
                    'data' => ['status' => 'pending']
                ];
            }
        }
        
        // Create new subscriber
        $subscriber = new NewsletterSubscriber();
        $subscriber->email = sanitize_email($email);
        $subscriber->name = !empty($name) ? sanitize_text_field($name) : null;
        $subscriber->status = 'pending';
        $subscriber->source = sanitize_text_field($source);
        $subscriber->confirmation_token = $this->generate_token();
        $subscriber->ip_address = $this->get_client_ip();
        $subscriber->user_agent = !empty($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;
        
        $subscriber_id = $this->repository->create($subscriber);
        
        if (!$subscriber_id) {
            return [
                'success' => false,
                'message' => __('Abonelik kaydedilemedi. Lütfen daha sonra tekrar deneyin.', 'kg-core'),
                'code' => 'create_failed'
            ];
        }
        
        $subscriber->id = $subscriber_id;
        
        // Send confirmation email
        $email_sent = $this->sendConfirmationEmail($subscriber);
        
        if (!$email_sent) {
            return [
                'success' => false,
                'message' => __('Onay e-postası gönderilemedi. Lütfen daha sonra tekrar deneyin.', 'kg-core'),
                'code' => 'email_failed'
            ];
        }
        
        return [
            'success' => true,
            'message' => __('Abonelik talebi alındı! Lütfen e-postanızı kontrol ederek onaylayın.', 'kg-core'),
            'data' => [
                'id' => $subscriber_id,
                'status' => 'pending'
            ]
        ];
    }
    
    /**
     * Confirm subscription with token
     * 
     * @param string $token
     * @return bool
     */
    public function confirm($token) {
        $subscriber = $this->repository->findByToken($token);
        
        if (!$subscriber) {
            return false;
        }
        
        if ($subscriber->is_active()) {
            return true; // Already confirmed
        }
        
        $subscriber->status = 'active';
        $subscriber->confirmed_at = current_time('mysql');
        $subscriber->confirmation_token = null; // Clear token after use
        
        $updated = $this->repository->update($subscriber);
        
        if ($updated) {
            // Send welcome email
            $this->sendWelcomeEmail($subscriber);
        }
        
        return $updated;
    }
    
    /**
     * Unsubscribe by email
     * 
     * @param string $email
     * @return bool
     */
    public function unsubscribe($email) {
        $subscriber = $this->repository->findByEmail($email);
        
        if (!$subscriber) {
            return false;
        }
        
        $subscriber->status = 'unsubscribed';
        $subscriber->unsubscribed_at = current_time('mysql');
        
        return $this->repository->update($subscriber);
    }
    
    /**
     * Send confirmation email
     * 
     * @param NewsletterSubscriber $subscriber
     * @return bool
     */
    public function sendConfirmationEmail(NewsletterSubscriber $subscriber) {
        if (empty($subscriber->confirmation_token)) {
            return false;
        }
        
        // Get site URL and build confirmation URL
        $site_url = get_site_url();
        $confirmation_url = rest_url('kg/v1/newsletter/confirm/' . $subscriber->confirmation_token);
        
        // Get template
        $template_key = 'newsletter_confirmation';
        
        // Prepare placeholders
        $placeholders = [
            'confirmation_url' => $confirmation_url,
        ];
        
        // Send email using EmailService
        if (class_exists('\KG_Core\Notifications\EmailService')) {
            $email_service = new EmailService();
            return $email_service->send_template_email(
                $subscriber->email,
                $template_key,
                $placeholders
            );
        }
        
        return false;
    }
    
    /**
     * Send welcome email after confirmation
     * 
     * @param NewsletterSubscriber $subscriber
     * @return bool
     */
    public function sendWelcomeEmail(NewsletterSubscriber $subscriber) {
        // Get site URL
        $app_url = get_site_url();
        
        // Get template
        $template_key = 'newsletter_welcome';
        
        // Prepare placeholders
        $placeholders = [
            'app_url' => $app_url,
        ];
        
        // Send email using EmailService
        if (class_exists('\KG_Core\Notifications\EmailService')) {
            $email_service = new EmailService();
            return $email_service->send_template_email(
                $subscriber->email,
                $template_key,
                $placeholders
            );
        }
        
        return false;
    }
    
    /**
     * Check if email is subscribed and active
     * 
     * @param string $email
     * @return bool
     */
    public function isSubscribed($email) {
        $subscriber = $this->repository->findByEmail($email);
        return $subscriber && $subscriber->is_active();
    }
    
    /**
     * Generate random token
     * 
     * @return string
     */
    private function generate_token() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Get client IP address
     * 
     * @return string
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
        
        // Validate and sanitize IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        
        return $ip ? substr($ip, 0, 45) : '';
    }
}

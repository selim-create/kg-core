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
        try {
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
                    // Try to resend confirmation email (non-blocking)
                    $email_sent = false;
                    try {
                        $email_sent = $this->sendConfirmationEmail($existing);
                    } catch (\Exception $e) {
                        error_log(sprintf('Newsletter: Resend email exception: %s', $e->getMessage()));
                    }
                    
                    return [
                        'success' => true,
                        'message' => $email_sent
                            ? __('Onay e-postası tekrar gönderildi. Lütfen e-postanızı kontrol edin.', 'kg-core')
                            : __('Zaten kayıtlısınız. Onay e-postası kısa süre içinde tekrar gönderilecektir.', 'kg-core'),
                        'data' => ['status' => 'pending', 'email_sent' => $email_sent]
                    ];
                } elseif ($existing->is_unsubscribed()) {
                    // Reactivate subscription
                    $existing->status = 'pending';
                    $existing->confirmation_token = $this->generate_token();
                    $existing->subscribed_at = current_time('mysql');
                    $existing->unsubscribed_at = null;
                    
                    $this->repository->update($existing);
                    
                    // Try to send confirmation email (non-blocking)
                    $email_sent = false;
                    try {
                        $email_sent = $this->sendConfirmationEmail($existing);
                    } catch (\Exception $e) {
                        error_log(sprintf('Newsletter: Reactivation email exception: %s', $e->getMessage()));
                    }
                    
                    return [
                        'success' => true,
                        'message' => $email_sent
                            ? __('Abonelik talebi alındı. Lütfen e-postanızı kontrol ederek onaylayın.', 'kg-core')
                            : __('Abonelik talebiniz alındı. Onay e-postası kısa süre içinde gönderilecektir.', 'kg-core'),
                        'data' => ['status' => 'pending', 'email_sent' => $email_sent]
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
                error_log('Newsletter: Failed to create subscriber in database');
                return [
                    'success' => false,
                    'message' => __('Abonelik kaydedilemedi. Lütfen daha sonra tekrar deneyin.', 'kg-core'),
                    'code' => 'create_failed'
                ];
            }
            
            $subscriber->id = $subscriber_id;
            
            // Try to send confirmation email (non-blocking)
            $email_sent = false;
            try {
                $email_sent = $this->sendConfirmationEmail($subscriber);
            } catch (\Exception $e) {
                error_log(sprintf('Newsletter: Email send exception: %s', $e->getMessage()));
            }
            
            if (!$email_sent) {
                error_log(sprintf('Newsletter: Failed to send confirmation email to %s', $subscriber->email));
                // Don't fail the subscription, just log the error
            }
            
            // Always return success if database insert was successful
            return [
                'success' => true,
                'message' => $email_sent 
                    ? __('Abonelik talebi alındı! Lütfen e-postanızı kontrol ederek onaylayın.', 'kg-core')
                    : __('Abonelik kaydınız alındı! Onay e-postası kısa süre içinde gönderilecektir.', 'kg-core'),
                'data' => [
                    'id' => $subscriber_id,
                    'status' => 'pending',
                    'email_sent' => $email_sent
                ]
            ];
        } catch (\Exception $e) {
            error_log(sprintf(
                'Newsletter subscribe error: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            
            return [
                'success' => false,
                'message' => __('Bir hata oluştu. Lütfen daha sonra tekrar deneyin.', 'kg-core'),
                'code' => 'internal_error'
            ];
        }
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
        try {
            if (empty($subscriber->confirmation_token)) {
                error_log('Newsletter: No confirmation token for email send');
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
            
            error_log('Newsletter: EmailService class not found');
            return false;
            
        } catch (\Exception $e) {
            error_log(sprintf(
                'Newsletter: sendConfirmationEmail error: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            return false;
        }
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

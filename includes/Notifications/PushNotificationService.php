<?php
namespace KG_Core\Notifications;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * PushNotificationService - Send Web Push notifications
 */
class PushNotificationService {
    
    private $vapid_manager;
    private $subscription_manager;
    
    public function __construct() {
        $this->vapid_manager = new VapidKeyManager();
        $this->subscription_manager = new PushSubscriptionManager();
    }
    
    /**
     * Send push notification to a user
     * 
     * @param int $user_id User ID
     * @param array $payload Notification payload
     * @return array|WP_Error Array of results or WP_Error on failure
     */
    public function send_to_user($user_id, $payload) {
        // Ensure VAPID keys exist
        $keys_check = $this->vapid_manager->ensure_keys_exist();
        if (is_wp_error($keys_check)) {
            return $keys_check;
        }
        
        // Get user's active subscriptions
        $subscriptions = $this->subscription_manager->get_user_subscriptions($user_id);
        
        if (empty($subscriptions)) {
            return new \WP_Error('no_subscriptions', 'User has no active push subscriptions');
        }
        
        // Check if web-push library is available
        if (!class_exists('\Minishlink\WebPush\WebPush')) {
            return new \WP_Error(
                'library_missing',
                'Web Push library not found. Please run: composer require minishlink/web-push'
            );
        }
        
        $auth = [
            'VAPID' => [
                'subject' => $this->vapid_manager->get_subject(),
                'publicKey' => $this->vapid_manager->get_public_key(),
                'privateKey' => $this->vapid_manager->get_private_key(),
            ],
        ];
        
        $webPush = new WebPush($auth);
        $results = [];
        
        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'keys' => [
                        'p256dh' => $sub['p256dh_key'],
                        'auth' => $sub['auth_key']
                    ]
                ]);
                
                $webPush->queueNotification(
                    $subscription,
                    json_encode($payload)
                );
                
            } catch (\Exception $e) {
                $results[] = [
                    'subscription_id' => $sub['id'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Send all queued notifications
        try {
            $reports = $webPush->flush();
            
            foreach ($reports as $report) {
                $sub_id = null;
                
                // Find subscription ID by endpoint
                foreach ($subscriptions as $sub) {
                    if ($sub['endpoint'] === $report->getEndpoint()) {
                        $sub_id = $sub['id'];
                        break;
                    }
                }
                
                if ($report->isSuccess()) {
                    $results[] = [
                        'subscription_id' => $sub_id,
                        'success' => true
                    ];
                    
                    // Update last_used_at
                    if ($sub_id) {
                        $this->subscription_manager->update_last_used($sub_id);
                    }
                } else {
                    $results[] = [
                        'subscription_id' => $sub_id,
                        'success' => false,
                        'error' => $report->getReason()
                    ];
                    
                    // Mark subscription as inactive if expired or invalid
                    if ($sub_id && $this->is_subscription_error($report->getReason())) {
                        $this->subscription_manager->mark_inactive($sub_id);
                    }
                }
            }
            
        } catch (\Exception $e) {
            return new \WP_Error('send_failed', $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Send vaccine reminder push notification
     * 
     * @param int $user_id User ID
     * @param string $child_name Child's name
     * @param string $vaccine_name Vaccine name
     * @param int $child_id Child ID
     * @param string $vaccine_code Vaccine code
     * @param int $days_remaining Days until vaccine
     * @return array|WP_Error Results or error
     */
    public function send_vaccine_reminder($user_id, $child_name, $vaccine_name, $child_id, $vaccine_code, $days_remaining) {
        $payload = [
            'title' => 'Aşı Hatırlatması',
            'body' => "{$child_name}'nin {$vaccine_name} aşısına {$days_remaining} gün kaldı!",
            'icon' => '/icons/vaccine-192.png',
            'badge' => '/icons/badge-72.png',
            'tag' => "vaccine-reminder-{$child_id}-{$vaccine_code}",
            'requireInteraction' => true,
            'data' => [
                'type' => 'vaccine_reminder',
                'child_id' => $child_id,
                'vaccine_code' => $vaccine_code,
                'url' => '/dashboard/saglik/asilar'
            ],
            'actions' => [
                ['action' => 'view', 'title' => 'Görüntüle'],
                ['action' => 'dismiss', 'title' => 'Kapat']
            ]
        ];
        
        return $this->send_to_user($user_id, $payload);
    }
    
    /**
     * Send test push notification
     * 
     * @param int $user_id User ID
     * @return array|WP_Error Results or error
     */
    public function send_test($user_id) {
        $payload = [
            'title' => 'Test Bildirimi',
            'body' => 'Bu bir test bildirimidir. Push notification sistemi çalışıyor!',
            'icon' => '/icons/test-192.png',
            'badge' => '/icons/badge-72.png',
            'tag' => 'test-notification',
            'data' => [
                'type' => 'test',
                'url' => '/dashboard'
            ]
        ];
        
        return $this->send_to_user($user_id, $payload);
    }
    
    /**
     * Check if error indicates subscription should be deactivated
     * 
     * @param string $error Error message
     * @return bool True if subscription should be deactivated
     */
    private function is_subscription_error($error) {
        $deactivate_errors = [
            'expired',
            'invalid',
            'gone',
            'not found',
            '410'
        ];
        
        $error_lower = strtolower($error);
        foreach ($deactivate_errors as $check) {
            if (strpos($error_lower, $check) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

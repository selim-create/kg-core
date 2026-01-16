<?php
namespace KG_Core\Cron;

use KG_Core\Health\VaccineRecordManager;
use KG_Core\Notifications\NotificationManager;

/**
 * VaccineReminderCron - Daily cron job for vaccine reminders
 */
class VaccineReminderCron {
    
    /**
     * Constructor - Register cron hooks
     */
    public function __construct() {
        // Register cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedule']);
        
        // Hook cron jobs
        add_action('kg_vaccine_reminder_daily', [$this, 'process_reminders']);
        add_action('kg_weekly_vaccine_digest', [$this, 'process_weekly_digest']);
        add_action('kg_cleanup_subscriptions', [$this, 'cleanup_old_subscriptions']);
        
        // Schedule daily cron if not scheduled
        if (!wp_next_scheduled('kg_vaccine_reminder_daily')) {
            wp_schedule_event(strtotime('02:00:00'), 'kg_daily_2am', 'kg_vaccine_reminder_daily');
        }
        
        // Schedule weekly digest cron (Mondays at 9 AM)
        if (!wp_next_scheduled('kg_weekly_vaccine_digest')) {
            $next_monday = strtotime('next Monday 09:00:00');
            wp_schedule_event($next_monday, 'weekly', 'kg_weekly_vaccine_digest');
        }
        
        // Schedule subscription cleanup (weekly, Sundays at 3 AM)
        if (!wp_next_scheduled('kg_cleanup_subscriptions')) {
            $next_sunday = strtotime('next Sunday 03:00:00');
            wp_schedule_event($next_sunday, 'weekly', 'kg_cleanup_subscriptions');
        }
    }
    
    /**
     * Add custom cron schedule (daily at 2 AM)
     * 
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_schedule($schedules) {
        $schedules['kg_daily_2am'] = array(
            'interval' => 86400, // 24 hours
            'display'  => __('Daily at 2 AM', 'kg-core')
        );
        return $schedules;
    }
    
    /**
     * Process vaccine reminders
     * Main cron job logic - runs daily at 2 AM
     */
    public function process_reminders() {
        global $wpdb;
        
        $this->log('Starting vaccine reminder cron job');
        
        $record_manager = new VaccineRecordManager();
        $notification_manager = new NotificationManager();
        
        $today = date('Y-m-d');
        $three_days_ahead = date('Y-m-d', strtotime('+3 days'));
        $one_day_ahead = date('Y-m-d', strtotime('+1 day'));
        
        // Get all upcoming vaccines
        $table = $wpdb->prefix . 'kg_vaccine_records';
        
        // 1. Process 3-day reminders
        $this->log('Processing 3-day reminders...');
        $three_day_reminders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE status = 'upcoming' 
            AND scheduled_date = %s 
            AND reminder_sent_3day = 0",
            $three_days_ahead
        ), ARRAY_A);
        
        $this->log('Found ' . count($three_day_reminders) . ' vaccines due in 3 days');
        
        foreach ($three_day_reminders as $record) {
            $this->send_reminder($record, 3, $notification_manager);
            
            // Mark 3-day reminder as sent
            $wpdb->update(
                $table,
                ['reminder_sent_3day' => 1],
                ['id' => $record['id']],
                ['%d'],
                ['%d']
            );
        }
        
        // 2. Process 1-day reminders
        $this->log('Processing 1-day reminders...');
        $one_day_reminders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE status = 'upcoming' 
            AND scheduled_date = %s 
            AND reminder_sent_1day = 0",
            $one_day_ahead
        ), ARRAY_A);
        
        $this->log('Found ' . count($one_day_reminders) . ' vaccines due in 1 day');
        
        foreach ($one_day_reminders as $record) {
            $this->send_reminder($record, 1, $notification_manager);
            
            // Mark 1-day reminder as sent
            $wpdb->update(
                $table,
                ['reminder_sent_1day' => 1],
                ['id' => $record['id']],
                ['%d'],
                ['%d']
            );
        }
        
        // 3. Process overdue vaccines (3 days past due date)
        $this->log('Processing overdue reminders...');
        $three_days_ago = date('Y-m-d', strtotime('-3 days'));
        
        $overdue_vaccines = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE status = 'upcoming' 
            AND scheduled_date = %s",
            $three_days_ago
        ), ARRAY_A);
        
        $this->log('Found ' . count($overdue_vaccines) . ' overdue vaccines');
        
        foreach ($overdue_vaccines as $record) {
            $this->send_overdue_reminder($record, $notification_manager);
        }
        
        // 4. Process follow-up for completed vaccines (next day after done)
        $this->log('Processing side effect follow-ups...');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $completed_vaccines = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE status = 'done' 
            AND actual_date = %s
            AND side_effect_severity = 'none'",
            $yesterday
        ), ARRAY_A);
        
        $this->log('Found ' . count($completed_vaccines) . ' vaccines completed yesterday');
        
        foreach ($completed_vaccines as $record) {
            $this->send_side_effect_followup($record, $notification_manager);
        }
        
        $this->log('Vaccine reminder cron job completed');
    }
    
    /**
     * Send reminder notification
     * 
     * @param array $record Vaccine record
     * @param int $days_before Days before scheduled date
     * @param NotificationManager $notification_manager Notification manager instance
     */
    private function send_reminder($record, $days_before, $notification_manager) {
        // Get child and parent info
        $child_info = $this->get_child_info($record['child_id'], $record['user_id']);
        $vaccine_info = $this->get_vaccine_info($record['vaccine_code']);
        
        if (!$child_info || !$vaccine_info) {
            $this->log('Failed to get child or vaccine info for record #' . $record['id']);
            return;
        }
        
        $user = get_user_by('id', $record['user_id']);
        if (!$user) {
            $this->log('User not found: ' . $record['user_id']);
            return;
        }
        
        $template_key = $days_before === 3 ? 'vaccine_reminder_3day' : 'vaccine_reminder_1day';
        
        $placeholders = [
            'parent_name' => $user->display_name,
            'child_name' => $child_info['name'],
            'vaccine_name' => $vaccine_info['name'],
            'vaccine_code' => $record['vaccine_code'],
            'scheduled_date' => date('d F Y', strtotime($record['scheduled_date'])),
            'days_remaining' => $days_before,
            'app_url' => home_url(),
            'unsubscribe_url' => home_url('/hesap/bildirim-tercihleri')
        ];
        
        // Schedule email notification
        $scheduled_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Send in 1 hour
        $notification_manager->schedule_vaccine_reminder(
            $record['user_id'],
            $record['child_id'],
            $record['vaccine_code'],
            $record['scheduled_date'],
            $days_before
        );
        
        // Send push notification immediately
        $push_service = new \KG_Core\Notifications\PushNotificationService();
        $push_result = $push_service->send_vaccine_reminder(
            $record['user_id'],
            $child_info['name'],
            $vaccine_info['name'],
            $record['child_id'],
            $record['vaccine_code'],
            $days_before
        );
        
        if (is_wp_error($push_result)) {
            $this->log('Push notification failed: ' . $push_result->get_error_message());
        } else {
            $this->log('Push notification sent successfully');
        }
        
        $this->log("Scheduled {$days_before}-day reminder for user #{$record['user_id']}, vaccine: {$record['vaccine_code']}");
    }
    
    /**
     * Send overdue reminder
     * 
     * @param array $record Vaccine record
     * @param NotificationManager $notification_manager Notification manager instance
     */
    private function send_overdue_reminder($record, $notification_manager) {
        $child_info = $this->get_child_info($record['child_id'], $record['user_id']);
        $vaccine_info = $this->get_vaccine_info($record['vaccine_code']);
        
        if (!$child_info || !$vaccine_info) {
            return;
        }
        
        $user = get_user_by('id', $record['user_id']);
        if (!$user) {
            return;
        }
        
        $placeholders = [
            'parent_name' => $user->display_name,
            'child_name' => $child_info['name'],
            'vaccine_name' => $vaccine_info['name'],
            'vaccine_code' => $record['vaccine_code'],
            'scheduled_date' => date('d F Y', strtotime($record['scheduled_date'])),
            'app_url' => home_url(),
        ];
        
        $notification_manager->send_immediate_notification(
            $record['user_id'],
            'vaccine_overdue',
            $placeholders
        );
        
        $this->log("Sent overdue reminder for user #{$record['user_id']}, vaccine: {$record['vaccine_code']}");
    }
    
    /**
     * Send side effect follow-up
     * 
     * @param array $record Vaccine record
     * @param NotificationManager $notification_manager Notification manager instance
     */
    private function send_side_effect_followup($record, $notification_manager) {
        $child_info = $this->get_child_info($record['child_id'], $record['user_id']);
        $vaccine_info = $this->get_vaccine_info($record['vaccine_code']);
        
        if (!$child_info || !$vaccine_info) {
            return;
        }
        
        $user = get_user_by('id', $record['user_id']);
        if (!$user) {
            return;
        }
        
        $placeholders = [
            'parent_name' => $user->display_name,
            'child_name' => $child_info['name'],
            'vaccine_name' => $vaccine_info['name'],
            'vaccine_code' => $record['vaccine_code'],
            'app_url' => home_url(),
        ];
        
        $notification_manager->send_immediate_notification(
            $record['user_id'],
            'vaccine_side_effect_followup',
            $placeholders
        );
        
        $this->log("Sent side effect follow-up for user #{$record['user_id']}, vaccine: {$record['vaccine_code']}");
    }
    
    /**
     * Get child information
     * 
     * @param string $child_id Child UUID
     * @param int $user_id User ID
     * @return array|null Child info
     */
    private function get_child_info($child_id, $user_id) {
        $children = get_user_meta($user_id, 'kg_children', true);
        
        if (empty($children) || !is_array($children)) {
            return null;
        }
        
        foreach ($children as $child) {
            if (isset($child['id']) && $child['id'] === $child_id) {
                return $child;
            }
        }
        
        return null;
    }
    
    /**
     * Get vaccine information
     * 
     * @param string $vaccine_code Vaccine code
     * @return array|null Vaccine info
     */
    private function get_vaccine_info($vaccine_code) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'kg_vaccine_master';
        
        $vaccine = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE code = %s",
            $vaccine_code
        ), ARRAY_A);
        
        return $vaccine;
    }
    
    /**
     * Process weekly vaccine digest
     * Runs every Monday at 9 AM
     */
    public function process_weekly_digest() {
        global $wpdb;
        
        $this->log('Starting weekly vaccine digest cron job');
        
        // Get users who have weekly_digest enabled
        $prefs_table = $wpdb->prefix . 'kg_notification_preferences';
        $users = $wpdb->get_results(
            "SELECT user_id FROM {$prefs_table} WHERE weekly_digest = 1",
            ARRAY_A
        );
        
        $this->log('Found ' . count($users) . ' users with weekly digest enabled');
        
        foreach ($users as $user_row) {
            $this->send_weekly_digest($user_row['user_id']);
        }
        
        $this->log('Weekly vaccine digest cron job completed');
    }
    
    /**
     * Send weekly digest to a user
     * 
     * @param int $user_id User ID
     */
    private function send_weekly_digest($user_id) {
        global $wpdb;
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $records_table = $wpdb->prefix . 'kg_vaccine_records';
        
        // Get vaccines from last week
        $last_week = date('Y-m-d', strtotime('-7 days'));
        $completed_last_week = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$records_table} 
            WHERE user_id = %d 
            AND status = 'done' 
            AND actual_date >= %s",
            $user_id,
            $last_week
        ), ARRAY_A);
        
        // Get upcoming vaccines for next week
        $next_week = date('Y-m-d', strtotime('+7 days'));
        $upcoming_next_week = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$records_table} 
            WHERE user_id = %d 
            AND status = 'upcoming' 
            AND scheduled_date BETWEEN CURDATE() AND %s",
            $user_id,
            $next_week
        ), ARRAY_A);
        
        // Get overdue vaccines
        $overdue = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$records_table} 
            WHERE user_id = %d 
            AND status = 'upcoming' 
            AND scheduled_date < CURDATE()",
            $user_id
        ), ARRAY_A);
        
        // Only send if there's something to report
        if (empty($completed_last_week) && empty($upcoming_next_week) && empty($overdue)) {
            return;
        }
        
        $this->log("Sending weekly digest to user #{$user_id}");
        
        // TODO: Implement email template for weekly digest
        // For now, just log it
    }
    
    /**
     * Cleanup old push subscriptions
     * Runs weekly on Sundays at 3 AM
     */
    public function cleanup_old_subscriptions() {
        $this->log('Starting subscription cleanup cron job');
        
        $subscription_manager = new \KG_Core\Notifications\PushSubscriptionManager();
        $count = $subscription_manager->cleanup_old_subscriptions(90); // 90 days
        
        $this->log("Cleaned up {$count} old subscriptions");
        $this->log('Subscription cleanup cron job completed');
    }
    
    /**
     * Log message
     * 
     * @param string $message Log message
     */
    private function log($message) {
        error_log('[KG Vaccine Cron] ' . $message);
    }
}

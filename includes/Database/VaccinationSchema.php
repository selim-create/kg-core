<?php
namespace KG_Core\Database;

/**
 * VaccinationSchema - Create vaccination tracker database tables
 */
class VaccinationSchema {
    
    /**
     * Create all vaccination-related tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 1. kg_vaccine_master - Vaccine Definitions (Admin Managed)
        $sql_vaccine_master = "CREATE TABLE {$prefix}kg_vaccine_master (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            name_short VARCHAR(100),
            description TEXT,
            timing_rule JSON NOT NULL COMMENT 'Zamanlama kuralı',
            min_age_days INT UNSIGNED DEFAULT 0,
            max_age_days INT UNSIGNED DEFAULT NULL,
            is_mandatory BOOLEAN DEFAULT TRUE,
            depends_on VARCHAR(50) DEFAULT NULL COMMENT 'Bağımlı olduğu önceki doz kodu',
            brand_options JSON DEFAULT NULL COMMENT 'Marka seçenekleri (özel aşılar için)',
            schedule_version VARCHAR(20) DEFAULT 'TR_2026_v1',
            source_url VARCHAR(500) DEFAULT NULL,
            sort_order INT UNSIGNED DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_mandatory (is_mandatory),
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) $charset_collate;";
        
        dbDelta($sql_vaccine_master);
        
        // 2. kg_vaccine_records - User Vaccine Records
        $sql_vaccine_records = "CREATE TABLE {$prefix}kg_vaccine_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            child_id VARCHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            vaccine_code VARCHAR(50) NOT NULL,
            status ENUM('upcoming', 'done', 'skipped', 'delayed') DEFAULT 'upcoming',
            scheduled_date DATE NOT NULL,
            actual_date DATE DEFAULT NULL,
            notes TEXT,
            side_effects JSON DEFAULT NULL COMMENT '{\"fever\": true, \"irritability\": false, \"swelling\": false}',
            side_effect_severity ENUM('none', 'mild', 'moderate', 'severe') DEFAULT 'none',
            reminder_sent_3day BOOLEAN DEFAULT FALSE,
            reminder_sent_1day BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_child (child_id),
            INDEX idx_user (user_id),
            INDEX idx_vaccine (vaccine_code),
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_date),
            INDEX idx_reminders (reminder_sent_3day, reminder_sent_1day)
        ) $charset_collate;";
        
        dbDelta($sql_vaccine_records);
        
        // 3. kg_email_templates - Email Templates
        $sql_email_templates = "CREATE TABLE {$prefix}kg_email_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            category ENUM('vaccination', 'growth', 'nutrition', 'system', 'marketing') NOT NULL,
            subject VARCHAR(500) NOT NULL,
            body_html LONGTEXT NOT NULL,
            body_text TEXT,
            placeholders JSON COMMENT 'Kullanılabilir placeholder listesi',
            trigger_type VARCHAR(50) DEFAULT NULL,
            trigger_config JSON DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (template_key),
            INDEX idx_category (category),
            INDEX idx_trigger (trigger_type),
            INDEX idx_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql_email_templates);
        
        // 4. kg_email_logs - Email Logs
        $sql_email_logs = "CREATE TABLE {$prefix}kg_email_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            template_key VARCHAR(100) NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            status ENUM('pending', 'sent', 'failed', 'bounced') DEFAULT 'pending',
            error_message TEXT,
            metadata JSON COMMENT 'child_id, vaccine_code gibi ek bilgiler',
            sent_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_template (template_key),
            INDEX idx_status (status),
            INDEX idx_sent (sent_at)
        ) $charset_collate;";
        
        dbDelta($sql_email_logs);
        
        // 5. kg_notification_queue - Notification Queue
        $sql_notification_queue = "CREATE TABLE {$prefix}kg_notification_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            channel ENUM('email', 'push', 'sms') NOT NULL,
            template_key VARCHAR(100) NOT NULL,
            payload JSON NOT NULL,
            scheduled_at DATETIME NOT NULL,
            status ENUM('pending', 'processing', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
            attempts INT UNSIGNED DEFAULT 0,
            last_attempt_at DATETIME DEFAULT NULL,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_channel (channel),
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_at),
            INDEX idx_processing (status, scheduled_at)
        ) $charset_collate;";
        
        dbDelta($sql_notification_queue);
        
        // 6. kg_push_subscriptions - Push Notification Subscriptions
        $sql_push_subscriptions = "CREATE TABLE {$prefix}kg_push_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh_key VARCHAR(500) NOT NULL,
            auth_key VARCHAR(500) NOT NULL,
            user_agent VARCHAR(500),
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql_push_subscriptions);
        
        // Seed default email templates
        self::seed_email_templates();
    }
    
    /**
     * Seed default email templates
     */
    private static function seed_email_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_email_templates';
        
        // Check if templates already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 0) {
            return; // Already seeded
        }
        
        $templates = [
            [
                'template_key' => 'vaccine_reminder_3day',
                'name' => 'Aşı Hatırlatma - 3 Gün Önce',
                'category' => 'vaccination',
                'subject' => '🔔 {{child_name}} için aşı zamanı yaklaşıyor!',
                'body_html' => '<h2>Merhaba {{parent_name}},</h2><p>{{child_name}}\'in <strong>{{vaccine_name}}</strong> aşısına <strong>{{days_remaining}} gün</strong> kaldı.</p><p><strong>Planlanan tarih:</strong> {{scheduled_date}}</p><p>Aşı sonrası olası yan etkiler için hazırlıklı olmayı unutmayın:</p><ul><li>Ateş ölçer</li><li>Ateş düşürücü (doktor önerisine göre)</li><li>Soğuk kompres</li></ul><p><a href=\'{{app_url}}/saglik/asilar\'>Aşı Takvimini Görüntüle →</a></p><hr><p style=\'font-size:12px;color:#666;\'>Bu e-postayı almak istemiyorsanız <a href=\'{{unsubscribe_url}}\'>buradan</a> abonelikten çıkabilirsiniz.</p>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in {{vaccine_name}} aşısına {{days_remaining}} gün kaldı. Planlanan tarih: {{scheduled_date}}',
                'placeholders' => '["parent_name", "child_name", "vaccine_name", "days_remaining", "scheduled_date", "app_url", "unsubscribe_url"]',
                'trigger_type' => 'vaccine_upcoming',
                'trigger_config' => '{"days_before": 3}',
                'is_active' => true
            ],
            [
                'template_key' => 'vaccine_reminder_1day',
                'name' => 'Aşı Hatırlatma - 1 Gün Önce',
                'category' => 'vaccination',
                'subject' => '⏰ Yarın {{child_name}}\'in aşı günü!',
                'body_html' => '<h2>Merhaba {{parent_name}},</h2><p>{{child_name}}\'in <strong>{{vaccine_name}}</strong> aşısı <strong>yarın</strong>!</p><p><strong>Tarih:</strong> {{scheduled_date}}</p><p>Randevunuzu kontrol etmeyi unutmayın.</p><p><a href=\'{{app_url}}/saglik/asilar\'>Aşı Takvimini Görüntüle →</a></p>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in {{vaccine_name}} aşısı yarın! Tarih: {{scheduled_date}}',
                'placeholders' => '["parent_name", "child_name", "vaccine_name", "scheduled_date", "app_url"]',
                'trigger_type' => 'vaccine_upcoming',
                'trigger_config' => '{"days_before": 1}',
                'is_active' => true
            ],
            [
                'template_key' => 'vaccine_overdue',
                'name' => 'Aşı Gecikme Hatırlatması',
                'category' => 'vaccination',
                'subject' => '📋 {{child_name}}\'in aşısı gecikmiş görünüyor',
                'body_html' => '<h2>Merhaba {{parent_name}},</h2><p>{{child_name}}\'in <strong>{{vaccine_name}}</strong> aşısı planlanan tarihi ({{scheduled_date}}) geçmiş görünüyor.</p><p>Eğer aşı yapıldıysa lütfen takvimde işaretlemeyi unutmayın.</p><p>Henüz yapılmadıysa, en kısa sürede Aile Sağlığı Merkezinizle iletişime geçmenizi öneririz.</p><p><a href=\'{{app_url}}/saglik/asilar\'>Aşı Takvimini Güncelle →</a></p>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in {{vaccine_name}} aşısı gecikmiş görünüyor. Tarih: {{scheduled_date}}',
                'placeholders' => '["parent_name", "child_name", "vaccine_name", "scheduled_date", "app_url"]',
                'trigger_type' => 'vaccine_overdue',
                'trigger_config' => '{"days_after": 3}',
                'is_active' => true
            ],
            [
                'template_key' => 'vaccine_side_effect_followup',
                'name' => 'Aşı Sonrası Takip',
                'category' => 'vaccination',
                'subject' => '{{child_name}}\'in aşı sonrası durumu nasıl?',
                'body_html' => '<h2>Merhaba {{parent_name}},</h2><p>{{child_name}}\'e dün <strong>{{vaccine_name}}</strong> aşısı yapıldı.</p><p>Aşı sonrası herhangi bir yan etki gözlemlediniz mi?</p><ul><li>Ateş</li><li>Huzursuzluk</li><li>Enjeksiyon yerinde şişlik</li></ul><p>Bu bilgileri kaydetmeniz, hem sizin hem de diğer ebeveynlerin faydasına olacaktır.</p><p><a href=\'{{app_url}}/saglik/asilar/yan-etki?vaccine={{vaccine_code}}\'>Yan Etki Bildir →</a></p>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'e dün {{vaccine_name}} aşısı yapıldı. Yan etki gözlemlediniz mi?',
                'placeholders' => '["parent_name", "child_name", "vaccine_name", "vaccine_code", "app_url"]',
                'trigger_type' => 'vaccine_done',
                'trigger_config' => '{"days_after": 1}',
                'is_active' => true
            ]
        ];
        
        foreach ($templates as $template) {
            $wpdb->insert($table, $template);
        }
    }
    
    /**
     * Drop all vaccination tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_push_subscriptions");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_notification_queue");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_email_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_email_templates");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_vaccine_records");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_vaccine_master");
    }
}

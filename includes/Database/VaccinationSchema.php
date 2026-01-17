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
        
        try {
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
            
            // Suppress dbDelta output
            @dbDelta($sql_vaccine_master);
        
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
        
        // Suppress dbDelta output
        @dbDelta($sql_vaccine_records);
        
        // 3. kg_vaccine_side_effects - Detailed Side Effect Records
        $sql_vaccine_side_effects = "CREATE TABLE {$prefix}kg_vaccine_side_effects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vaccine_record_id BIGINT UNSIGNED NOT NULL,
            child_id VARCHAR(36) NOT NULL,
            vaccine_code VARCHAR(50) NOT NULL,
            side_effects JSON NOT NULL COMMENT 'Array of side effects with details',
            severity ENUM('mild', 'moderate', 'severe') NOT NULL,
            reported_at DATETIME NOT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_record (vaccine_record_id),
            INDEX idx_child (child_id),
            INDEX idx_vaccine (vaccine_code),
            INDEX idx_severity (severity),
            INDEX idx_reported (reported_at),
            FOREIGN KEY (vaccine_record_id) REFERENCES {$prefix}kg_vaccine_records(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Suppress dbDelta output
        @dbDelta($sql_vaccine_side_effects);
        
        // 4. kg_email_templates - Email Templates
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
        
        // Suppress dbDelta output
        @dbDelta($sql_email_templates);
        
        // 5. kg_email_logs - Email Logs
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
        
        // Suppress dbDelta output
        @dbDelta($sql_email_logs);
        
        // 6. kg_notification_queue - Notification Queue
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
        
        // Suppress dbDelta output
        @dbDelta($sql_notification_queue);
        
        // 7. kg_push_subscriptions - Push Notification Subscriptions
        $sql_push_subscriptions = "CREATE TABLE {$prefix}kg_push_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            endpoint TEXT NOT NULL,
            p256dh_key VARCHAR(500) NOT NULL,
            auth_key VARCHAR(500) NOT NULL,
            user_agent VARCHAR(500),
            device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
            is_active BOOLEAN DEFAULT TRUE,
            last_used_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_active (is_active),
            INDEX idx_last_used (last_used_at)
        ) $charset_collate;";
        
        // Suppress dbDelta output
        @dbDelta($sql_push_subscriptions);
        
        // 8. kg_notification_preferences - Notification Preferences
        $sql_notification_preferences = "CREATE TABLE {$prefix}kg_notification_preferences (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL UNIQUE,
            email_enabled BOOLEAN DEFAULT TRUE,
            push_enabled BOOLEAN DEFAULT TRUE,
            vaccine_reminder_3day BOOLEAN DEFAULT TRUE,
            vaccine_reminder_1day BOOLEAN DEFAULT TRUE,
            vaccine_overdue BOOLEAN DEFAULT TRUE,
            growth_tracking BOOLEAN DEFAULT TRUE,
            weekly_digest BOOLEAN DEFAULT FALSE,
            quiet_hours_start TIME DEFAULT NULL,
            quiet_hours_end TIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id)
        ) $charset_collate;";
        
        // Suppress dbDelta output
        @dbDelta($sql_notification_preferences);
        
        // Seed default email templates
        self::seed_email_templates();
        
        } catch ( \Exception $e ) {
            error_log( 'VaccinationSchema::create_tables Error: ' . $e->getMessage() );
        } catch ( \Error $e ) {
            error_log( 'VaccinationSchema::create_tables Fatal Error: ' . $e->getMessage() );
        }
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
            // ===== VACCINATION TEMPLATES =====
            [
                'template_key' => 'vaccine_reminder_3day',
                'name' => 'Aşı Hatırlatma - 3 Gün Önce',
                'category' => 'vaccination',
                'subject' => '🔔 {{child_name}} için aşı zamanı yaklaşıyor!',
                'body_html' => '<h2 style="color: #4CAF50; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}}\'in <strong>{{vaccine_name}}</strong> aşısına <strong style="color: #4CAF50;">{{days_remaining}} gün</strong> kaldı.</p>
                    <div style="background: #f0f9f0; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0 0 10px 0; font-weight: bold; color: #4CAF50;">📅 Planlanan Tarih:</p>
                        <p style="font-size: 18px; margin: 0; font-weight: bold; color: #333;">{{scheduled_date}}</p>
                    </div>
                    <h3 style="color: #4CAF50; margin: 30px 0 15px 0;">Aşı Sonrası Hazırlık:</h3>
                    <ul style="line-height: 1.8; color: #666;">
                        <li>Ateş ölçer</li>
                        <li>Ateş düşürücü (doktor önerisine göre)</li>
                        <li>Soğuk kompres</li>
                        <li>Bol sıvı tüketimi</li>
                    </ul>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/saglik/asilar" style="display: inline-block; background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Aşı Takvimini Görüntüle</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in {{vaccine_name}} aşısına {{days_remaining}} gün kaldı. Planlanan tarih: {{scheduled_date}}',
                'placeholders' => '["parent_name", "child_name", "vaccine_name", "days_remaining", "scheduled_date", "app_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'vaccine_reminder_1day',
                'name' => 'Aşı Hatırlatma - 1 Gün Önce',
                'category' => 'vaccination',
                'subject' => '⏰ Yarın {{child_name}}\'in aşı günü!',
                'body_html' => '<h2 style="color: #4CAF50; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}}\'in <strong>{{vaccine_name}}</strong> aşısı <strong style="color: #FF9800;">yarın</strong>!</p>
                    <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #FF9800;">
                        <p style="margin: 0 0 10px 0; font-weight: bold; color: #FF9800;">⏰ Yarın</p>
                        <p style="font-size: 18px; margin: 0; font-weight: bold; color: #333;">{{scheduled_date}}</p>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Randevunuzu kontrol etmeyi unutmayın. Aşı kartını yanınıza almayı ihmal etmeyin.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/saglik/asilar" style="display: inline-block; background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Aşı Takvimini Görüntüle</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in {{vaccine_name}} aşısı yarın! Tarih: {{scheduled_date}}',
                'placeholders' => '["parent_name", "child_name", "vaccine_name", "scheduled_date", "app_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'vaccine_overdue',
                'name' => 'Aşı Gecikme Hatırlatması',
                'category' => 'vaccination',
                'subject' => '📋 {{child_name}}\'in aşısı gecikmiş görünüyor',
                'body_html' => '<h2 style="color: #f44336; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}}\'in <strong>{{vaccine_name}}</strong> aşısı planlanan tarihi geçmiş görünüyor.</p>
                    <div style="background: #ffebee; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f44336;">
                        <p style="margin: 0 0 10px 0; font-weight: bold; color: #f44336;">Planlanan Tarih:</p>
                        <p style="font-size: 18px; margin: 0; font-weight: bold; color: #333;">{{scheduled_date}}</p>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Eğer aşı yapıldıysa lütfen takvimde işaretlemeyi unutmayın.</p>
                    <p style="color: #666; line-height: 1.6;">Henüz yapılmadıysa, en kısa sürede Aile Sağlığı Merkezinizle iletişime geçmenizi öneririz.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/saglik/asilar" style="display: inline-block; background: #f44336; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Aşı Takvimini Güncelle</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in {{vaccine_name}} aşısı gecikmiş görünüyor. Tarih: {{scheduled_date}}',
                'placeholders' => '["parent_name", "child_name", "vaccine_name", "scheduled_date", "app_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'vaccine_side_effect_followup',
                'name' => 'Aşı Sonrası Takip',
                'category' => 'vaccination',
                'subject' => '💊 {{child_name}}\'in aşı sonrası durumu nasıl?',
                'body_html' => '<h2 style="color: #4CAF50; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}}\'e dün <strong>{{vaccine_name}}</strong> aşısı yapıldı.</p>
                    <p style="color: #666; line-height: 1.6;">Aşı sonrası herhangi bir yan etki gözlemlediniz mi?</p>
                    <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin: 0 0 15px 0; color: #333;">Yaygın Yan Etkiler:</h3>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #666;">
                            <li>Hafif ateş</li>
                            <li>Huzursuzluk</li>
                            <li>Enjeksiyon yerinde kızarıklık/şişlik</li>
                            <li>İştahsızlık</li>
                        </ul>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Bu bilgileri kaydetmeniz, hem sizin hem de diğer ebeveynlerin faydasına olacaktır.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/saglik/asilar" style="display: inline-block; background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Yan Etki Bildir</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'e dün {{vaccine_name}} aşısı yapıldı. Yan etki gözlemlediniz mi?',
                'placeholders' => '["parent_name", "child_name", "vaccine_name", "app_url"]',
                'is_active' => true
            ],
            
            // ===== GROWTH TEMPLATES =====
            [
                'template_key' => 'growth_measurement_reminder',
                'name' => 'Aylık Ölçüm Hatırlatması',
                'category' => 'growth',
                'subject' => '📏 {{child_name}} için aylık ölçüm zamanı!',
                'body_html' => '<h2 style="color: #2196F3; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}}\'in aylık büyüme ölçümünü kaydetme zamanı geldi!</p>
                    <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin: 0 0 15px 0; color: #2196F3;">Ölçülecekler:</h3>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #666;">
                            <li>Boy (cm)</li>
                            <li>Kilo (kg)</li>
                            <li>Baş çevresi (cm)</li>
                        </ul>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Düzenli ölçümler, bebeğinizin sağlıklı gelişimini takip etmenize yardımcı olur.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/saglik/buyume" style="display: inline-block; background: #2196F3; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Ölçüm Kaydet</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in aylık büyüme ölçümünü kaydetme zamanı!',
                'placeholders' => '["parent_name", "child_name", "app_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'growth_percentile_alert',
                'name' => 'Persentil Değişikliği Uyarısı',
                'category' => 'growth',
                'subject' => '📊 {{child_name}}\'in büyüme grafiğinde değişiklik',
                'body_html' => '<h2 style="color: #2196F3; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}}\'in son büyüme ölçümlerinde önemli bir değişiklik tespit ettik.</p>
                    <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #FF9800;">
                        <p style="margin: 0; color: #666; line-height: 1.6;">Persentil değerinde beklenmedik bir değişim görülmektedir. Bu durum hakkında çocuk doktorunuza danışmanızı öneririz.</p>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Detaylı büyüme grafiğini ve önerilerimizi incelemek için:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/saglik/buyume" style="display: inline-block; background: #2196F3; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Büyüme Grafiğini Görüntüle</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in büyüme grafiğinde önemli bir değişiklik tespit ettik.',
                'placeholders' => '["parent_name", "child_name", "app_url"]',
                'is_active' => true
            ],
            
            // ===== NUTRITION TEMPLATES =====
            [
                'template_key' => 'nutrition_new_food_suggestion',
                'name' => 'Yeni Besin Önerisi',
                'category' => 'nutrition',
                'subject' => '🍎 {{child_name}} için yeni besin önerilerimiz var!',
                'body_html' => '<h2 style="color: #FF9800; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}}\'in yaşına uygun yeni besinler deneme zamanı!</p>
                    <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin: 0 0 15px 0; color: #FF9800;">Bu Ay Deneyebilirsiniz:</h3>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #666;">
                            <li>{{food_1}}</li>
                            <li>{{food_2}}</li>
                            <li>{{food_3}}</li>
                        </ul>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Yeni besinleri tek tek ve 3 gün ara ile denemeyi unutmayın.</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/beslenme/besinler" style="display: inline-block; background: #FF9800; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Besin Rehberini İncele</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}}\'in yaşına uygun yeni besinler deneme zamanı!',
                'placeholders' => '["parent_name", "child_name", "food_1", "food_2", "food_3", "app_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'nutrition_allergy_reminder',
                'name' => '3 Gün Kuralı Hatırlatması',
                'category' => 'nutrition',
                'subject' => '⏱️ {{food_name}} için 3 gün tamamlandı',
                'body_html' => '<h2 style="color: #FF9800; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}}\'e verdiğiniz <strong>{{food_name}}</strong> için 3 günlük deneme süresi tamamlandı.</p>
                    <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0 0 15px 0; font-weight: bold; color: #4CAF50;">Herhangi bir alerji belirtisi gözlemlediniz mi?</p>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #666;">
                            <li>Cilt döküntüsü</li>
                            <li>Karın ağrısı</li>
                            <li>İshal veya kabızlık</li>
                            <li>Kusma</li>
                        </ul>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Herhangi bir sorun yoksa, bu besini güvenle verebilir ve yeni bir besin deneyebilirsiniz!</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/beslenme/besinler" style="display: inline-block; background: #FF9800; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Yeni Besin Dene</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{food_name}} için 3 günlük deneme süresi tamamlandı.',
                'placeholders' => '["parent_name", "child_name", "food_name", "app_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'nutrition_weekly_menu',
                'name' => 'Haftalık Menü Özeti',
                'category' => 'nutrition',
                'subject' => '📅 Bu haftanın menüsü hazır!',
                'body_html' => '<h2 style="color: #FF9800; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">{{child_name}} için bu haftanın beslenme önerilerini hazırladık!</p>
                    <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0 0 15px 0; font-weight: bold; color: #FF9800;">Bu hafta {{recipe_count}} yeni tarif bulacaksınız:</p>
                        <p style="margin: 0; color: #666; line-height: 1.6;">Kahvaltılar, ara öğünler ve ana yemekler için kolaylıkla hazırlayabileceğiniz, besleyici tarifler.</p>
                    </div>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/tarifler" style="display: inline-block; background: #FF9800; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Tarifleri İncele</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, {{child_name}} için bu haftanın beslenme önerilerini hazırladık!',
                'placeholders' => '["parent_name", "child_name", "recipe_count", "app_url"]',
                'is_active' => true
            ],
            
            // ===== SYSTEM TEMPLATES =====
            [
                'template_key' => 'system_welcome',
                'name' => 'Hoşgeldin E-postası',
                'category' => 'system',
                'subject' => '🎉 KidsGourmet\'e hoş geldiniz!',
                'body_html' => '<h2 style="color: #607D8B; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">KidsGourmet ailesine hoş geldiniz! 🎉</p>
                    <p style="color: #666; line-height: 1.6;">Bebeğinizin sağlıklı büyümesi için ihtiyacınız olan her şey burada:</p>
                    <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #666;">
                            <li>Yaşa uygun tarifler</li>
                            <li>Aşı takip sistemi</li>
                            <li>Büyüme grafiği</li>
                            <li>Uzman tavsiyeleri</li>
                        </ul>
                    </div>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}" style="display: inline-block; background: #607D8B; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Keşfetmeye Başla</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, KidsGourmet ailesine hoş geldiniz!',
                'placeholders' => '["parent_name", "app_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'system_password_reset',
                'name' => 'Şifre Sıfırlama',
                'category' => 'system',
                'subject' => '🔒 Şifre sıfırlama talebi',
                'body_html' => '<h2 style="color: #607D8B; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">Şifrenizi sıfırlama talebinde bulundunuz.</p>
                    <div style="background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #FF9800;">
                        <p style="margin: 0; color: #666; line-height: 1.6;">Eğer bu talebi siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.</p>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Şifrenizi sıfırlamak için aşağıdaki butona tıklayın:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{reset_url}}" style="display: inline-block; background: #607D8B; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Şifremi Sıfırla</a>
                    </div>
                    <p style="color: #999; font-size: 12px; line-height: 1.6;">Bu bağlantı 24 saat geçerlidir.</p>',
                'body_text' => 'Merhaba {{parent_name}}, şifrenizi sıfırlama talebinde bulundunuz.',
                'placeholders' => '["parent_name", "reset_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'system_email_verification',
                'name' => 'E-posta Doğrulama',
                'category' => 'system',
                'subject' => '✉️ E-posta adresinizi doğrulayın',
                'body_html' => '<h2 style="color: #607D8B; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">KidsGourmet hesabınızı oluşturduğunuz için teşekkür ederiz!</p>
                    <p style="color: #666; line-height: 1.6;">Hesabınızı aktifleştirmek için e-posta adresinizi doğrulamanız gerekiyor:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{verification_url}}" style="display: inline-block; background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">E-postamı Doğrula</a>
                    </div>
                    <p style="color: #999; font-size: 12px; line-height: 1.6;">Doğrulama kodu: {{verification_code}}</p>',
                'body_text' => 'Merhaba {{parent_name}}, e-posta adresinizi doğrulamak için: {{verification_url}}',
                'placeholders' => '["parent_name", "verification_url", "verification_code"]',
                'is_active' => true
            ],
            [
                'template_key' => 'system_account_deletion',
                'name' => 'Hesap Silme Onayı',
                'category' => 'system',
                'subject' => '⚠️ Hesap silme talebi',
                'body_html' => '<h2 style="color: #f44336; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">KidsGourmet hesabınızı silme talebinde bulundunuz.</p>
                    <div style="background: #ffebee; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f44336;">
                        <p style="margin: 0 0 10px 0; font-weight: bold; color: #f44336;">Uyarı:</p>
                        <p style="margin: 0; color: #666; line-height: 1.6;">Bu işlem geri alınamaz. Tüm verileriniz kalıcı olarak silinecektir.</p>
                    </div>
                    <p style="color: #666; line-height: 1.6;">Hesabınızı silmeyi onaylamak için:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{confirmation_url}}" style="display: inline-block; background: #f44336; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Hesabımı Sil</a>
                    </div>
                    <p style="color: #999; font-size: 12px; line-height: 1.6;">Eğer bu talebi siz yapmadıysanız, lütfen derhal şifrenizi değiştirin.</p>',
                'body_text' => 'Merhaba {{parent_name}}, hesabınızı silme talebinde bulundunuz.',
                'placeholders' => '["parent_name", "confirmation_url"]',
                'is_active' => true
            ],
            
            // ===== MARKETING TEMPLATES =====
            [
                'template_key' => 'marketing_newsletter',
                'name' => 'Haftalık Bülten',
                'category' => 'marketing',
                'subject' => '📰 Bu haftanın en popüler tarifleri',
                'body_html' => '<h2 style="color: #E91E63; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">Bu hafta KidsGourmet\'de neler oldu?</p>
                    <div style="background: #fce4ec; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin: 0 0 15px 0; color: #E91E63;">🌟 En Çok Beğenilenler:</h3>
                        <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #666;">
                            <li>{{popular_recipe_1}}</li>
                            <li>{{popular_recipe_2}}</li>
                            <li>{{popular_recipe_3}}</li>
                        </ul>
                    </div>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}/tarifler" style="display: inline-block; background: #E91E63; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Tüm Tarifleri Gör</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, bu hafta KidsGourmet\'de en çok beğenilen tarifler.',
                'placeholders' => '["parent_name", "popular_recipe_1", "popular_recipe_2", "popular_recipe_3", "app_url"]',
                'is_active' => true
            ],
            [
                'template_key' => 'marketing_new_feature',
                'name' => 'Yeni Özellik Duyurusu',
                'category' => 'marketing',
                'subject' => '🎊 Yeni özellik: {{feature_name}}',
                'body_html' => '<h2 style="color: #E91E63; margin: 0 0 20px 0;">Merhaba {{parent_name}},</h2>
                    <p style="font-size: 16px; line-height: 1.6; color: #333;">Size harika bir haberimiz var! 🎉</p>
                    <div style="background: #fce4ec; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h3 style="margin: 0 0 15px 0; color: #E91E63;">{{feature_name}}</h3>
                        <p style="margin: 0; color: #666; line-height: 1.6;">{{feature_description}}</p>
                    </div>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{{app_url}}{{feature_url}}" style="display: inline-block; background: #E91E63; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">Hemen Dene</a>
                    </div>',
                'body_text' => 'Merhaba {{parent_name}}, yeni özellik: {{feature_name}}',
                'placeholders' => '["parent_name", "feature_name", "feature_description", "app_url", "feature_url"]',
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
        
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_notification_preferences");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_push_subscriptions");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_notification_queue");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_email_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_email_templates");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_vaccine_side_effects");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_vaccine_records");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}kg_vaccine_master");
    }
}

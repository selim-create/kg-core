<?php
namespace KG_Core\Admin;

/**
 * KGCoreAdminMenu - Main admin menu for KG Core
 * 
 * Creates the main "KG Core" menu item in WordPress admin
 * Other admin pages will be registered as submenus under this
 */
class KGCoreAdminMenu {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu'], 5); // Priority 5 to load early
    }
    
    /**
     * Register main menu
     */
    public function register_menu() {
        add_menu_page(
            'KG Core',                          // Page title
            'KG Core',                          // Menu title
            'manage_options',                   // Capability
            'kg-core',                          // Menu slug
            [$this, 'render_dashboard'],       // Callback
            'dashicons-database',               // Icon
            26                                   // Position
        );
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'kg-core'));
        }
        
        global $wpdb;
        
        // Get some statistics
        $ingredient_count = wp_count_posts('ingredient');
        $recipe_count = wp_count_posts('recipe');
        $total_users = count_users();
        
        // Email stats
        $email_logs_table = $wpdb->prefix . 'kg_email_logs';
        $email_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM {$email_logs_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Vaccine stats
        $vaccine_records_table = $wpdb->prefix . 'kg_vaccine_records';
        $vaccine_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed
            FROM {$vaccine_records_table}
        ");
        
        ?>
        <div class="wrap">
            <h1>🎯 KG Core Dashboard</h1>
            <p class="description">KidsGourmet Core Plugin - Merkezi Yönetim Paneli</p>
            
            <!-- Statistics Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0;">
                
                <!-- Content Stats -->
                <div style="background: white; padding: 20px; border-left: 4px solid #2196F3; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 15px 0; color: #2196F3;">📚 İçerik</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #333;">
                        <?php echo $recipe_count->publish ?? 0; ?>
                    </p>
                    <p style="color: #666; margin: 5px 0 0 0;">Yayınlanmış Tarif</p>
                    <hr style="margin: 15px 0; border: none; border-top: 1px solid #eee;">
                    <p style="font-size: 24px; font-weight: bold; margin: 0; color: #333;">
                        <?php echo $ingredient_count->publish ?? 0; ?>
                    </p>
                    <p style="color: #666; margin: 5px 0 0 0;">Malzeme</p>
                </div>
                
                <!-- User Stats -->
                <div style="background: white; padding: 20px; border-left: 4px solid #4CAF50; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 15px 0; color: #4CAF50;">👥 Kullanıcılar</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #333;">
                        <?php echo $total_users['total_users'] ?? 0; ?>
                    </p>
                    <p style="color: #666; margin: 5px 0 0 0;">Toplam Kullanıcı</p>
                </div>
                
                <!-- Email Stats -->
                <div style="background: white; padding: 20px; border-left: 4px solid #FF9800; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 15px 0; color: #FF9800;">📧 E-posta (30 Gün)</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #333;">
                        <?php echo $email_stats->sent ?? 0; ?>
                    </p>
                    <p style="color: #666; margin: 5px 0 0 0;">Gönderildi</p>
                    <hr style="margin: 15px 0; border: none; border-top: 1px solid #eee;">
                    <?php if (($email_stats->failed ?? 0) > 0): ?>
                        <p style="color: #f44336; margin: 0;">
                            ⚠️ <?php echo $email_stats->failed; ?> Başarısız
                        </p>
                    <?php else: ?>
                        <p style="color: #4CAF50; margin: 0;">
                            ✓ Hata Yok
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Vaccine Stats -->
                <div style="background: white; padding: 20px; border-left: 4px solid #9C27B0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 15px 0; color: #9C27B0;">💉 Aşı Takibi</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #333;">
                        <?php echo $vaccine_stats->upcoming ?? 0; ?>
                    </p>
                    <p style="color: #666; margin: 5px 0 0 0;">Bekleyen Aşı</p>
                    <hr style="margin: 15px 0; border: none; border-top: 1px solid #eee;">
                    <p style="font-size: 24px; font-weight: bold; margin: 0; color: #333;">
                        <?php echo $vaccine_stats->completed ?? 0; ?>
                    </p>
                    <p style="color: #666; margin: 5px 0 0 0;">Tamamlanan</p>
                </div>
                
                <!-- Custom Tables Status -->
                <?php 
                $table_status = [];
                $db_version = 'N/A';
                
                if ( class_exists( '\KG_Core\Database\Schema' ) ) {
                    $table_status = \KG_Core\Database\Schema::getTableStatus();
                    $db_version = get_option( \KG_Core\Database\Schema::DB_VERSION_OPTION, 'Not Set' );
                }
                
                $all_tables_exist = !empty($table_status) && count(array_filter($table_status, function($t) { 
                    return $t['exists']; 
                })) === count($table_status);
                ?>
                <div style="background: white; padding: 20px; border-left: 4px solid #00BCD4; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 15px 0; color: #00BCD4;">🗄️ Custom Tables</h3>
                    
                    <?php if ( $all_tables_exist ): ?>
                        <p style="font-size: 24px; font-weight: bold; margin: 0; color: #4CAF50;">
                            ✅ Aktif
                        </p>
                        <p style="color: #666; margin: 5px 0 15px 0;">Tüm tablolar mevcut</p>
                    <?php else: ?>
                        <p style="font-size: 24px; font-weight: bold; margin: 0; color: #f44336;">
                            ❌ Eksik
                        </p>
                        <p style="color: #666; margin: 5px 0 15px 0;">Bazı tablolar eksik</p>
                    <?php endif; ?>
                    
                    <div style="font-size: 12px; color: #666;">
                        <?php foreach ( $table_status as $table_name => $status ): ?>
                            <div style="margin: 5px 0; display: flex; justify-content: space-between; align-items: center;">
                                <span>
                                    <?php echo $status['exists'] ? '✅' : '❌'; ?>
                                    <strong><?php echo esc_html( $table_name ); ?></strong>
                                </span>
                                <?php if ( $status['exists'] ): ?>
                                    <span style="background: #e3f2fd; padding: 2px 8px; border-radius: 3px;">
                                        <?php echo number_format( $status['row_count'] ); ?> satır
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr style="margin: 10px 0; border: none; border-top: 1px solid #eee;">
                        <p style="margin: 5px 0;">
                            <strong>DB Version:</strong> <?php echo esc_html( $db_version ); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div style="background: white; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
                <h2 style="margin-top: 0;">⚡ Hızlı Erişim</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <a href="<?php echo admin_url('admin.php?page=kg-email-templates'); ?>" class="button button-primary button-large" style="text-align: center;">
                        📧 E-posta Şablonları
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=kg-notification-logs'); ?>" class="button button-secondary button-large" style="text-align: center;">
                        📊 Bildirim Logları
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=kg-ai-settings'); ?>" class="button button-secondary button-large" style="text-align: center;">
                        🤖 AI Ayarları
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=kg-bulk-seeder'); ?>" class="button button-secondary button-large" style="text-align: center;">
                        ⚡ Toplu AI Oluştur
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=kg-recipe-migration'); ?>" class="button button-secondary button-large" style="text-align: center;">
                        🔄 Tarif Migration
                    </a>
                </div>
            </div>
            
            <!-- System Info -->
            <div style="background: #f5f5f5; padding: 15px; margin-top: 20px; border-radius: 4px;">
                <h3 style="margin-top: 0;">ℹ️ Sistem Bilgisi</h3>
                <ul style="columns: 2; margin: 0;">
                    <li><strong>Plugin Versiyonu:</strong> <?php echo KG_CORE_VERSION; ?></li>
                    <li><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></li>
                    <li><strong>PHP:</strong> <?php echo PHP_VERSION; ?></li>
                    <li><strong>MySQL:</strong> <?php echo $wpdb->db_version(); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}

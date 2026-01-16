<?php
namespace KG_Core\Admin;

use KG_Core\Health\VaccineStatsCalculator;

/**
 * VaccineStatsAdminPage - Admin page for vaccine statistics
 */
class VaccineStatsAdminPage {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'kg-vaccines',
            'Aşı İstatistikleri',
            'İstatistikler',
            'manage_options',
            'kg-vaccine-stats',
            [$this, 'render_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        $stats_calculator = new VaccineStatsCalculator();
        $global_stats = $stats_calculator->get_global_stats();
        
        ?>
        <div class="wrap">
            <h1>Aşı İstatistikleri</h1>
            
            <div class="card" style="max-width: 1200px;">
                <h2>Genel İstatistikler</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 36px; font-weight: bold; color: #2271b1;">
                            <?php echo number_format($global_stats['total_users']); ?>
                        </div>
                        <div style="color: #666; margin-top: 5px;">Toplam Kullanıcı</div>
                    </div>
                    
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 36px; font-weight: bold; color: #2271b1;">
                            <?php echo number_format($global_stats['total_children']); ?>
                        </div>
                        <div style="color: #666; margin-top: 5px;">Toplam Çocuk</div>
                    </div>
                    
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 36px; font-weight: bold; color: #2271b1;">
                            <?php echo number_format($global_stats['total_vaccines']); ?>
                        </div>
                        <div style="color: #666; margin-top: 5px;">Toplam Aşı Kaydı</div>
                    </div>
                    
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 36px; font-weight: bold; color: <?php echo $global_stats['completion_rate'] >= 70 ? '#00a32a' : '#d63638'; ?>;">
                            <?php echo $global_stats['completion_rate']; ?>%
                        </div>
                        <div style="color: #666; margin-top: 5px;">Tamamlanma Oranı</div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($global_stats['most_common_vaccines'])): ?>
            <div class="card" style="max-width: 1200px; margin-top: 20px;">
                <h2>En Çok Yapılan Aşılar</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 60%;">Aşı Adı</th>
                            <th style="width: 20%;">Aşı Kodu</th>
                            <th style="width: 20%; text-align: right;">Yapılan Sayısı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($global_stats['most_common_vaccines'] as $vaccine): ?>
                        <tr>
                            <td><?php echo esc_html($vaccine['vaccine_name']); ?></td>
                            <td><code><?php echo esc_html($vaccine['vaccine_code']); ?></code></td>
                            <td style="text-align: right;"><strong><?php echo number_format($vaccine['count']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="card" style="max-width: 1200px; margin-top: 20px;">
                <h2>Yan Etki İstatistikleri</h2>
                <p>Aşı yan etki istatistiklerine <a href="<?php echo admin_url('admin.php?page=kg-vaccine-admin'); ?>">Aşı Yönetimi</a> sayfasından erişebilirsiniz.</p>
            </div>
            
            <div class="card" style="max-width: 1200px; margin-top: 20px;">
                <h2>Bildirim İstatistikleri</h2>
                <?php
                global $wpdb;
                $logs_table = $wpdb->prefix . 'kg_email_logs';
                
                // Get notification counts
                $total_emails = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}");
                $sent_emails = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE status = 'sent'");
                $failed_emails = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE status = 'failed'");
                
                $push_table = $wpdb->prefix . 'kg_push_subscriptions';
                $active_subscriptions = $wpdb->get_var("SELECT COUNT(*) FROM {$push_table} WHERE is_active = 1");
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 28px; font-weight: bold; color: #2271b1;">
                            <?php echo number_format($total_emails); ?>
                        </div>
                        <div style="color: #666; margin-top: 5px;">Toplam Email</div>
                    </div>
                    
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 28px; font-weight: bold; color: #00a32a;">
                            <?php echo number_format($sent_emails); ?>
                        </div>
                        <div style="color: #666; margin-top: 5px;">Gönderilen Email</div>
                    </div>
                    
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 28px; font-weight: bold; color: #d63638;">
                            <?php echo number_format($failed_emails); ?>
                        </div>
                        <div style="color: #666; margin-top: 5px;">Başarısız Email</div>
                    </div>
                    
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 5px; text-align: center;">
                        <div style="font-size: 28px; font-weight: bold; color: #2271b1;">
                            <?php echo number_format($active_subscriptions); ?>
                        </div>
                        <div style="color: #666; margin-top: 5px;">Aktif Push Abonesi</div>
                    </div>
                </div>
            </div>
            
            <div class="card" style="max-width: 1200px; margin-top: 20px;">
                <h2>Raporlar</h2>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=kg-notification-logs'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-email"></span> Bildirim Logları
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=kg-push-notifications'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-bell"></span> Push Ayarları
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}

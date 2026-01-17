<?php
namespace KG_Core\Admin;

use KG_Core\Notifications\VapidKeyManager;
use KG_Core\Notifications\PushSubscriptionManager;
use KG_Core\Notifications\PushNotificationService;

/**
 * PushNotificationAdminPage - Admin page for push notification settings
 */
class PushNotificationAdminPage {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_kg_regenerate_vapid_keys', [$this, 'regenerate_vapid_keys']);
        add_action('admin_post_kg_update_vapid_subject', [$this, 'update_vapid_subject']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'kg-vaccines',
            'Push Bildirimleri',
            'Push Bildirimleri',
            'manage_options',
            'kg-push-notifications',
            [$this, 'render_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        $vapid_manager = new VapidKeyManager();
        $subscription_manager = new PushSubscriptionManager();
        
        $config = $vapid_manager->get_config();
        $active_subscriptions = $subscription_manager->get_active_count();
        
        ?>
        <div class="wrap">
            <h1>Push Bildirim Ayarları</h1>
            
            <?php if (isset($_GET['vapid_regenerated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>VAPID anahtarları başarıyla yeniden oluşturuldu!</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['subject_updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>VAPID subject başarıyla güncellendi!</p>
                </div>
            <?php endif; ?>
            
            <div class="card" style="max-width: 800px;">
                <h2>VAPID Anahtarları</h2>
                <p>Web Push bildirimleri için VAPID anahtarları gereklidir. Bu anahtarlar güvenli bir şekilde saklanır ve frontend tarafından kullanılır.</p>
                
                <table class="form-table">
                    <tr>
                        <th>Durum:</th>
                        <td>
                            <?php if ($config['keys_exist']): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                <strong style="color: green;">Anahtarlar Mevcut</strong>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: orange;"></span>
                                <strong style="color: orange;">Anahtarlar Eksik</strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Public Key:</th>
                        <td>
                            <input type="text" class="regular-text" value="<?php echo esc_attr($config['public_key']); ?>" readonly>
                            <p class="description">Frontend'de subscription için kullanılır.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Private Key:</th>
                        <td>
                            <span class="dashicons dashicons-lock"></span>
                            <strong>Gizli</strong>
                            <p class="description">Güvenlik nedeniyle private key gösterilmez.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Subject:</th>
                        <td>
                            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                <input type="hidden" name="action" value="kg_update_vapid_subject">
                                <?php wp_nonce_field('kg_update_vapid_subject'); ?>
                                <input type="text" name="subject" class="regular-text" value="<?php echo esc_attr($config['subject']); ?>" placeholder="mailto:admin@example.com">
                                <button type="submit" class="button">Güncelle</button>
                                <p class="description">mailto: veya https:// ile başlamalıdır.</p>
                            </form>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('VAPID anahtarlarını yeniden oluşturmak istediğinizden emin misiniz? Mevcut tüm subscriptionlar geçersiz olacaktır.');">
                        <input type="hidden" name="action" value="kg_regenerate_vapid_keys">
                        <?php wp_nonce_field('kg_regenerate_vapid_keys'); ?>
                        <button type="submit" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span> Anahtarları Yeniden Oluştur
                        </button>
                    </form>
                </p>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>İstatistikler</h2>
                <table class="form-table">
                    <tr>
                        <th>Aktif Subscriptions:</th>
                        <td>
                            <strong style="font-size: 18px;"><?php echo number_format($active_subscriptions); ?></strong>
                            <p class="description">Şu anda aktif push notification abonelikleri.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Test Bildirimi</h2>
                <p>Kendi hesabınıza test bildirimi gönderin.</p>
                <button type="button" class="button button-primary" onclick="sendTestPushNotification()">
                    <span class="dashicons dashicons-bell"></span> Test Bildirimi Gönder
                </button>
                <div id="test-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Kullanım Talimatları</h2>
                <ol>
                    <li>VAPID public key'i frontend'e iletin (API endpoint: <code>/kg/v1/notifications/vapid-public-key</code>)</li>
                    <li>Kullanıcılar tarayıcılarında push notification izni verdiğinde subscription bilgilerini backend'e gönderin</li>
                    <li>Backend otomatik olarak aşı hatırlatmalarını push notification ile gönderecektir</li>
                    <li>Kullanıcılar bildirim tercihlerini hesap ayarlarından yönetebilir</li>
                </ol>
            </div>
        </div>
        
        <script>
        function sendTestPushNotification() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.innerHTML = '<p>Gönderiliyor...</p>';
            
            fetch('/wp-json/kg/v1/notifications/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + (localStorage.getItem('kg_auth_token') || '')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<p style="color: green;">✓ Test bildirimi başarıyla gönderildi!</p>';
                } else {
                    resultDiv.innerHTML = '<p style="color: red;">✗ Hata: ' + (data.error || 'Bilinmeyen hata') + '</p>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<p style="color: red;">✗ İstek hatası: ' + error.message + '</p>';
            });
        }
        </script>
        <?php
    }
    
    /**
     * Regenerate VAPID keys
     */
    public function regenerate_vapid_keys() {
        check_admin_referer('kg_regenerate_vapid_keys');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $vapid_manager = new VapidKeyManager();
        $result = $vapid_manager->generate_keys();
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        wp_redirect(admin_url('admin.php?page=kg-push-notifications&vapid_regenerated=1'));
        exit;
    }
    
    /**
     * Update VAPID subject
     */
    public function update_vapid_subject() {
        check_admin_referer('kg_update_vapid_subject');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $subject = sanitize_text_field($_POST['subject']);
        
        $vapid_manager = new VapidKeyManager();
        $result = $vapid_manager->update_subject($subject);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        wp_redirect(admin_url('admin.php?page=kg-push-notifications&subject_updated=1'));
        exit;
    }
}

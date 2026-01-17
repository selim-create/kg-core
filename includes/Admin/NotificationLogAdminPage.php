<?php
namespace KG_Core\Admin;

/**
 * NotificationLogAdminPage - Logs and queue viewer
 * 
 * Admin interface for viewing email logs and notification queue
 */
class NotificationLogAdminPage {

    private $email_logs_table;
    private $queue_table;

    public function __construct() {
        global $wpdb;
        $this->email_logs_table = $wpdb->prefix . 'kg_email_logs';
        $this->queue_table = $wpdb->prefix . 'kg_notification_queue';
        
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_kg_retry_notification', [$this, 'handle_retry_failed']);
    }

    /**
     * Register admin menu
     */
    public function add_menu() {
        add_submenu_page(
            'kg-core',
            __('Bildirim Logları', 'kg-core'),
            __('Bildirim Logları', 'kg-core'),
            'manage_options',
            'kg-notification-logs',
            [$this, 'render_page']
        );
    }

    /**
     * Render main page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'kg-core'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'email_logs';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Bildirim Logları', 'kg-core') . '</h1>';

        // Display admin notices
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $class = 'notice notice-success is-dismissible';
            $text = '';
            
            switch ($message) {
                case 'retried':
                    $text = __('Bildirim yeniden kuyruğa eklendi.', 'kg-core');
                    break;
            }
            
            if ($text) {
                echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($text) . '</p></div>';
            }
        }

        // Tabs
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=kg-notification-logs&tab=email_logs')) . '" class="nav-tab ' . ($active_tab === 'email_logs' ? 'nav-tab-active' : '') . '">';
        echo esc_html__('E-posta Logları', 'kg-core');
        echo '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=kg-notification-logs&tab=queue')) . '" class="nav-tab ' . ($active_tab === 'queue' ? 'nav-tab-active' : '') . '">';
        echo esc_html__('Bildirim Kuyruğu', 'kg-core');
        echo '</a>';
        echo '</h2>';

        echo '<div style="margin-top: 20px;">';
        
        if ($active_tab === 'queue') {
            $this->render_queue_tab();
        } else {
            $this->render_email_logs_tab();
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render email logs tab
     */
    private function render_email_logs_tab() {
        global $wpdb;

        $per_page = 50;
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $offset = ($paged - 1) * $per_page;

        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        $where = [];
        $where_values = [];

        if ($status_filter) {
            $where[] = "status = %s";
            $where_values[] = $status_filter;
        }

        if ($search) {
            $where[] = "(recipient_email LIKE %s OR subject LIKE %s)";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        if (!empty($where_values)) {
            $where_sql = $wpdb->prepare($where_sql, $where_values);
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->email_logs_table} {$where_sql}");
        
        $logs = $wpdb->get_results(
            "SELECT * FROM {$this->email_logs_table} {$where_sql} ORDER BY created_at DESC LIMIT {$offset}, {$per_page}",
            ARRAY_A
        );

        $total_pages = ceil($total_items / $per_page);

        ?>
        <div style="margin: 20px 0;">
            <form method="get" action="">
                <input type="hidden" name="page" value="kg-notification-logs">
                <input type="hidden" name="tab" value="email_logs">
                
                <select name="status" onchange="this.form.submit();">
                    <option value="" <?php selected($status_filter, ''); ?>><?php esc_html_e('Tüm Durumlar', 'kg-core'); ?></option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Beklemede', 'kg-core'); ?></option>
                    <option value="sent" <?php selected($status_filter, 'sent'); ?>><?php esc_html_e('Gönderildi', 'kg-core'); ?></option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Başarısız', 'kg-core'); ?></option>
                    <option value="bounced" <?php selected($status_filter, 'bounced'); ?>><?php esc_html_e('Geri Döndü', 'kg-core'); ?></option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('E-posta veya konu ara...', 'kg-core'); ?>">
                <button type="submit" class="button"><?php esc_html_e('Filtrele', 'kg-core'); ?></button>
                
                <?php if ($status_filter || $search): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kg-notification-logs&tab=email_logs')); ?>" class="button">
                        <?php esc_html_e('Filtreyi Temizle', 'kg-core'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php esc_html_e('ID', 'kg-core'); ?></th>
                    <th style="width: 200px;"><?php esc_html_e('Alıcı', 'kg-core'); ?></th>
                    <th><?php esc_html_e('Konu', 'kg-core'); ?></th>
                    <th style="width: 150px;"><?php esc_html_e('Şablon', 'kg-core'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Durum', 'kg-core'); ?></th>
                    <th style="width: 150px;"><?php esc_html_e('Gönderim Zamanı', 'kg-core'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Tarih', 'kg-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            <?php esc_html_e('Log kaydı bulunamadı.', 'kg-core'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php 
                        $status_class = [
                            'sent' => 'color: green;',
                            'failed' => 'color: red;',
                            'bounced' => 'color: orange;',
                            'pending' => 'color: gray;'
                        ];
                        $status_style = isset($status_class[$log['status']]) ? $status_class[$log['status']] : '';
                        ?>
                        <tr>
                            <td><?php echo esc_html($log['id']); ?></td>
                            <td><?php echo esc_html($log['recipient_email']); ?></td>
                            <td>
                                <strong><?php echo esc_html($log['subject']); ?></strong>
                                <?php if ($log['error_message']): ?>
                                    <br><small style="color: red;" title="<?php echo esc_attr($log['error_message']); ?>">
                                        <?php echo esc_html(wp_trim_words($log['error_message'], 10)); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($log['template_key']); ?></code></td>
                            <td style="<?php echo esc_attr($status_style); ?>">
                                <strong><?php echo esc_html(ucfirst($log['status'])); ?></strong>
                            </td>
                            <td><?php echo $log['sent_at'] ? esc_html($log['sent_at']) : '-'; ?></td>
                            <td><?php echo esc_html($log['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render notification queue tab
     */
    private function render_queue_tab() {
        global $wpdb;

        $per_page = 50;
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $offset = ($paged - 1) * $per_page;

        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $channel_filter = isset($_GET['channel']) ? sanitize_text_field($_GET['channel']) : '';

        $where = [];
        $where_values = [];

        if ($status_filter) {
            $where[] = "status = %s";
            $where_values[] = $status_filter;
        }

        if ($channel_filter) {
            $where[] = "channel = %s";
            $where_values[] = $channel_filter;
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        if (!empty($where_values)) {
            $where_sql = $wpdb->prepare($where_sql, $where_values);
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$this->queue_table} {$where_sql}");
        
        $queue_items = $wpdb->get_results(
            "SELECT * FROM {$this->queue_table} {$where_sql} ORDER BY scheduled_at DESC LIMIT {$offset}, {$per_page}",
            ARRAY_A
        );

        $total_pages = ceil($total_items / $per_page);

        ?>
        <div style="margin: 20px 0;">
            <form method="get" action="">
                <input type="hidden" name="page" value="kg-notification-logs">
                <input type="hidden" name="tab" value="queue">
                
                <select name="status" onchange="this.form.submit();">
                    <option value="" <?php selected($status_filter, ''); ?>><?php esc_html_e('Tüm Durumlar', 'kg-core'); ?></option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Beklemede', 'kg-core'); ?></option>
                    <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php esc_html_e('İşleniyor', 'kg-core'); ?></option>
                    <option value="sent" <?php selected($status_filter, 'sent'); ?>><?php esc_html_e('Gönderildi', 'kg-core'); ?></option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Başarısız', 'kg-core'); ?></option>
                    <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php esc_html_e('İptal', 'kg-core'); ?></option>
                </select>
                
                <select name="channel" onchange="this.form.submit();">
                    <option value="" <?php selected($channel_filter, ''); ?>><?php esc_html_e('Tüm Kanallar', 'kg-core'); ?></option>
                    <option value="email" <?php selected($channel_filter, 'email'); ?>><?php esc_html_e('E-posta', 'kg-core'); ?></option>
                    <option value="push" <?php selected($channel_filter, 'push'); ?>><?php esc_html_e('Push', 'kg-core'); ?></option>
                    <option value="sms" <?php selected($channel_filter, 'sms'); ?>><?php esc_html_e('SMS', 'kg-core'); ?></option>
                </select>
                
                <button type="submit" class="button"><?php esc_html_e('Filtrele', 'kg-core'); ?></button>
                
                <?php if ($status_filter || $channel_filter): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kg-notification-logs&tab=queue')); ?>" class="button">
                        <?php esc_html_e('Filtreyi Temizle', 'kg-core'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php esc_html_e('ID', 'kg-core'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Kanal', 'kg-core'); ?></th>
                    <th style="width: 150px;"><?php esc_html_e('Şablon', 'kg-core'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Durum', 'kg-core'); ?></th>
                    <th style="width: 150px;"><?php esc_html_e('Planlanma', 'kg-core'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Deneme', 'kg-core'); ?></th>
                    <th><?php esc_html_e('Hata Mesajı', 'kg-core'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('İşlem', 'kg-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue_items)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            <?php esc_html_e('Kuyruk boş.', 'kg-core'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queue_items as $item): ?>
                        <?php 
                        $status_class = [
                            'sent' => 'color: green;',
                            'failed' => 'color: red;',
                            'cancelled' => 'color: orange;',
                            'pending' => 'color: gray;',
                            'processing' => 'color: blue;'
                        ];
                        $status_style = isset($status_class[$item['status']]) ? $status_class[$item['status']] : '';
                        ?>
                        <tr>
                            <td><?php echo esc_html($item['id']); ?></td>
                            <td><?php echo esc_html(ucfirst($item['channel'])); ?></td>
                            <td><code><?php echo esc_html($item['template_key']); ?></code></td>
                            <td style="<?php echo esc_attr($status_style); ?>">
                                <strong><?php echo esc_html(ucfirst($item['status'])); ?></strong>
                            </td>
                            <td><?php echo esc_html($item['scheduled_at']); ?></td>
                            <td><?php echo esc_html($item['attempts']); ?></td>
                            <td>
                                <?php if ($item['error_message']): ?>
                                    <small style="color: red;" title="<?php echo esc_attr($item['error_message']); ?>">
                                        <?php echo esc_html(wp_trim_words($item['error_message'], 15)); ?>
                                    </small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['status'] === 'failed'): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('kg_retry_notification_' . $item['id'], 'kg_retry_notification_nonce'); ?>
                                        <input type="hidden" name="action" value="kg_retry_notification">
                                        <input type="hidden" name="notification_id" value="<?php echo esc_attr($item['id']); ?>">
                                        <button type="submit" class="button button-small">
                                            <?php esc_html_e('Tekrar Dene', 'kg-core'); ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Handle retry failed notification
     */
    public function handle_retry_failed() {
        $notification_id = absint($_POST['notification_id']);
        check_admin_referer('kg_retry_notification_' . $notification_id, 'kg_retry_notification_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;

        $wpdb->update(
            $this->queue_table,
            [
                'status' => 'pending',
                'attempts' => 0,
                'error_message' => null,
                'last_attempt_at' => null,
                'scheduled_at' => current_time('mysql')
            ],
            ['id' => $notification_id]
        );

        wp_redirect(add_query_arg(['tab' => 'queue', 'message' => 'retried'], admin_url('admin.php?page=kg-notification-logs')));
        exit;
    }
}

<?php
namespace KG_Core\Admin;

use KG_Core\Newsletter\NewsletterRepository;
use KG_Core\Newsletter\NewsletterSubscriber;

/**
 * NewsletterAdminPage - Admin page for managing newsletter subscribers
 * 
 * Displays subscriber list with filtering, search, and bulk actions
 */
class NewsletterAdminPage {
    
    private $repository;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new \KG_Core\Newsletter\NewsletterRepository();
        
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_kg_newsletter_bulk_action', [$this, 'handle_bulk_action']);
        add_action('admin_post_kg_newsletter_add', [$this, 'handle_add_subscriber']);
    }
    
    /**
     * Register admin menu
     */
    public function add_menu() {
        add_submenu_page(
            'kg-core',
            __('Bülten Aboneleri', 'kg-core'),
            __('Bülten Aboneleri', 'kg-core'),
            'manage_options',
            'kg-newsletter-subscribers',
            [$this, 'render_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'kg-core'));
        }
        
        // Get filter parameters
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        
        // Build filters
        $filters = [
            'limit' => $per_page,
            'offset' => ($paged - 1) * $per_page,
        ];
        
        if (!empty($status)) {
            $filters['status'] = $status;
        }
        
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        
        // Get subscribers and count
        $subscribers = $this->repository->getAll($filters);
        $total = $this->repository->count($filters);
        
        // Get statistics
        $stats = [
            'total' => $this->repository->count(),
            'active' => $this->repository->count(['status' => 'active']),
            'pending' => $this->repository->count(['status' => 'pending']),
            'unsubscribed' => $this->repository->count(['status' => 'unsubscribed']),
        ];
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Bülten Aboneleri', 'kg-core'); ?></h1>
            <a href="#" class="page-title-action" id="kg-add-subscriber-btn"><?php esc_html_e('Yeni Ekle', 'kg-core'); ?></a>
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['message'])): ?>
                <?php if ($_GET['message'] === 'deleted'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e('Seçili aboneler silindi.', 'kg-core'); ?></p>
                    </div>
                <?php elseif ($_GET['message'] === 'added'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e('Yeni abone başarıyla eklendi.', 'kg-core'); ?></p>
                    </div>
                <?php elseif ($_GET['message'] === 'exported'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e('Aboneler CSV olarak dışa aktarıldı.', 'kg-core'); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="kg-newsletter-stats" style="display: flex; gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 15px 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;"><?php esc_html_e('Toplam Abone', 'kg-core'); ?></div>
                    <div style="font-size: 24px; font-weight: 600;"><?php echo esc_html($stats['total']); ?></div>
                </div>
                <div style="background: #fff; padding: 15px 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;"><?php esc_html_e('Aktif', 'kg-core'); ?></div>
                    <div style="font-size: 24px; font-weight: 600; color: #00a32a;"><?php echo esc_html($stats['active']); ?></div>
                </div>
                <div style="background: #fff; padding: 15px 20px; border-left: 4px solid #dba617; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;"><?php esc_html_e('Bekleyen', 'kg-core'); ?></div>
                    <div style="font-size: 24px; font-weight: 600; color: #dba617;"><?php echo esc_html($stats['pending']); ?></div>
                </div>
                <div style="background: #fff; padding: 15px 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;"><?php esc_html_e('İptal Edilmiş', 'kg-core'); ?></div>
                    <div style="font-size: 24px; font-weight: 600; color: #d63638;"><?php echo esc_html($stats['unsubscribed']); ?></div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="kg-newsletter-subscribers">
                        
                        <select name="status">
                            <option value=""><?php esc_html_e('Tüm Durumlar', 'kg-core'); ?></option>
                            <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Aktif', 'kg-core'); ?></option>
                            <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Bekleyen', 'kg-core'); ?></option>
                            <option value="unsubscribed" <?php selected($status, 'unsubscribed'); ?>><?php esc_html_e('İptal Edilmiş', 'kg-core'); ?></option>
                        </select>
                        
                        <button type="submit" class="button"><?php esc_html_e('Filtrele', 'kg-core'); ?></button>
                    </form>
                </div>
                
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="kg-newsletter-subscribers">
                        <?php if (!empty($status)): ?>
                            <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>">
                        <?php endif; ?>
                        
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('E-posta veya isim ara...', 'kg-core'); ?>">
                        <button type="submit" class="button"><?php esc_html_e('Ara', 'kg-core'); ?></button>
                    </form>
                </div>
                
                <?php if (!empty($subscribers)): ?>
                    <div class="alignright actions">
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=kg_newsletter_export&' . http_build_query(['status' => $status, 's' => $search]))); ?>" class="button">
                            <?php esc_html_e('CSV Olarak Dışa Aktar', 'kg-core'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Table -->
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('kg_newsletter_bulk_action', 'kg_newsletter_nonce'); ?>
                <input type="hidden" name="action" value="kg_newsletter_bulk_action">
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th><?php esc_html_e('E-posta', 'kg-core'); ?></th>
                            <th><?php esc_html_e('İsim', 'kg-core'); ?></th>
                            <th><?php esc_html_e('Durum', 'kg-core'); ?></th>
                            <th><?php esc_html_e('Kaynak', 'kg-core'); ?></th>
                            <th><?php esc_html_e('Kayıt Tarihi', 'kg-core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subscribers)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px;">
                                    <?php esc_html_e('Abone bulunamadı.', 'kg-core'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subscribers as $subscriber): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="subscribers[]" value="<?php echo esc_attr($subscriber->id); ?>">
                                    </th>
                                    <td><strong><?php echo esc_html($subscriber->email); ?></strong></td>
                                    <td><?php echo esc_html($subscriber->name ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $status_labels = [
                                            'active' => '<span style="color: #00a32a;">●</span> ' . __('Aktif', 'kg-core'),
                                            'pending' => '<span style="color: #dba617;">●</span> ' . __('Bekleyen', 'kg-core'),
                                            'unsubscribed' => '<span style="color: #d63638;">●</span> ' . __('İptal Edilmiş', 'kg-core'),
                                        ];
                                        echo wp_kses_post($status_labels[$subscriber->status] ?? $subscriber->status);
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($subscriber->source ?? 'website'); ?></td>
                                    <td><?php echo esc_html(mysql2date('d.m.Y H:i', $subscriber->subscribed_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($subscribers)): ?>
                    <div class="tablenav bottom">
                        <div class="alignleft actions">
                            <select name="bulk_action">
                                <option value=""><?php esc_html_e('Toplu İşlemler', 'kg-core'); ?></option>
                                <option value="delete"><?php esc_html_e('Sil', 'kg-core'); ?></option>
                            </select>
                            <button type="submit" class="button action"><?php esc_html_e('Uygula', 'kg-core'); ?></button>
                        </div>
                        
                        <?php
                        $total_pages = ceil($total / $per_page);
                        if ($total_pages > 1):
                        ?>
                            <div class="tablenav-pages">
                                <?php
                                echo paginate_links([
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => __('&laquo;'),
                                    'next_text' => __('&raquo;'),
                                    'total' => $total_pages,
                                    'current' => $paged,
                                ]);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Add Subscriber Modal -->
        <div id="kg-add-subscriber-modal" style="display: none;">
            <div style="background: #fff; padding: 20px; max-width: 500px; margin: 50px auto; border-radius: 4px; box-shadow: 0 3px 10px rgba(0,0,0,0.2);">
                <h2><?php esc_html_e('Yeni Abone Ekle', 'kg-core'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('kg_newsletter_add', 'kg_newsletter_add_nonce'); ?>
                    <input type="hidden" name="action" value="kg_newsletter_add">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="subscriber_email"><?php esc_html_e('E-posta', 'kg-core'); ?> *</label></th>
                            <td><input type="email" name="email" id="subscriber_email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="subscriber_name"><?php esc_html_e('İsim', 'kg-core'); ?></label></th>
                            <td><input type="text" name="name" id="subscriber_name" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="subscriber_status"><?php esc_html_e('Durum', 'kg-core'); ?></label></th>
                            <td>
                                <select name="status" id="subscriber_status">
                                    <option value="pending"><?php esc_html_e('Bekleyen', 'kg-core'); ?></option>
                                    <option value="active"><?php esc_html_e('Aktif', 'kg-core'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Ekle', 'kg-core'); ?></button>
                        <button type="button" class="button" id="kg-cancel-add"><?php esc_html_e('İptal', 'kg-core'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all checkbox
            $('#cb-select-all').on('click', function() {
                $('input[name="subscribers[]"]').prop('checked', this.checked);
            });
            
            // Add subscriber modal
            $('#kg-add-subscriber-btn').on('click', function(e) {
                e.preventDefault();
                $('#kg-add-subscriber-modal').show();
            });
            
            $('#kg-cancel-add').on('click', function() {
                $('#kg-add-subscriber-modal').hide();
            });
        });
        </script>
        
        <style>
        #kg-add-subscriber-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 100000;
        }
        </style>
        <?php
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_action() {
        check_admin_referer('kg_newsletter_bulk_action', 'kg_newsletter_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }
        
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $subscribers = isset($_POST['subscribers']) ? array_map('absint', $_POST['subscribers']) : [];
        
        if (empty($bulk_action) || empty($subscribers)) {
            wp_redirect(admin_url('admin.php?page=kg-newsletter-subscribers'));
            exit;
        }
        
        if ($bulk_action === 'delete') {
            foreach ($subscribers as $subscriber_id) {
                $this->repository->delete($subscriber_id);
            }
            
            wp_redirect(add_query_arg('message', 'deleted', admin_url('admin.php?page=kg-newsletter-subscribers')));
            exit;
        }
        
        wp_redirect(admin_url('admin.php?page=kg-newsletter-subscribers'));
        exit;
    }
    
    /**
     * Handle add subscriber
     */
    public function handle_add_subscriber() {
        check_admin_referer('kg_newsletter_add', 'kg_newsletter_add_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
        
        if (empty($email)) {
            wp_redirect(admin_url('admin.php?page=kg-newsletter-subscribers'));
            exit;
        }
        
        $subscriber = new NewsletterSubscriber();
        $subscriber->email = $email;
        $subscriber->name = $name;
        $subscriber->status = $status;
        $subscriber->source = 'admin';
        
        if ($status === 'active') {
            $subscriber->confirmed_at = current_time('mysql');
        }
        
        $this->repository->create($subscriber);
        
        wp_redirect(add_query_arg('message', 'added', admin_url('admin.php?page=kg-newsletter-subscribers')));
        exit;
    }
}

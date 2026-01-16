<?php
namespace KG_Core\Admin;

use KG_Core\Health\VaccineManager;

/**
 * VaccineAdminPage - Vaccine master data management
 * 
 * Admin interface for managing vaccine definitions in kg_vaccine_master table
 */
class VaccineAdminPage {

    private $table_name;
    private $vaccine_manager;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'kg_vaccine_master';
        $this->vaccine_manager = new VaccineManager();
        
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_kg_add_vaccine', [$this, 'handle_add_vaccine']);
        add_action('admin_post_kg_edit_vaccine', [$this, 'handle_edit_vaccine']);
        add_action('admin_post_kg_delete_vaccine', [$this, 'handle_delete_vaccine']);
        add_action('admin_post_kg_toggle_vaccine', [$this, 'handle_toggle_vaccine']);
        add_action('admin_post_kg_load_vaccines', [$this, 'handle_load_from_json']);
    }

    /**
     * Register admin menu
     */
    public function add_menu() {
        // Add top-level menu page if it doesn't exist
        global $admin_page_hooks;
        if (!isset($admin_page_hooks['kg-health'])) {
            add_menu_page(
                __('KidsGourmet Health', 'kg-core'),
                __('KG Health', 'kg-core'),
                'manage_options',
                'kg-health',
                [$this, 'render_page'],
                'dashicons-heart',
                25
            );
        }
        
        // Add as submenu
        add_submenu_page(
            'kg-health',
            __('Aşı Yönetimi', 'kg-core'),
            __('Aşı Yönetimi', 'kg-core'),
            'manage_options',
            'kg-health',
            [$this, 'render_page']
        );
    }

    /**
     * Render main page
     */
    public function render_page() {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'kg-core'));
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $vaccine_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Aşı Yönetimi', 'kg-core') . '</h1>';

        // Display admin notices
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $class = 'notice notice-success is-dismissible';
            $text = '';
            
            switch ($message) {
                case 'added':
                    $text = __('Aşı başarıyla eklendi.', 'kg-core');
                    break;
                case 'updated':
                    $text = __('Aşı başarıyla güncellendi.', 'kg-core');
                    break;
                case 'deleted':
                    $text = __('Aşı başarıyla silindi.', 'kg-core');
                    break;
                case 'toggled':
                    $text = __('Aşı durumu güncellendi.', 'kg-core');
                    break;
                case 'loaded':
                    $text = __('Aşı verileri JSON dosyasından başarıyla yüklendi.', 'kg-core');
                    break;
                case 'load_error':
                    $class = 'notice notice-error is-dismissible';
                    $error_msg = isset($_GET['error_msg']) ? urldecode($_GET['error_msg']) : '';
                    $text = __('Aşı verileri yüklenirken hata oluştu: ', 'kg-core') . $error_msg;
                    break;
                case 'error':
                    $class = 'notice notice-error is-dismissible';
                    $text = __('İşlem sırasında bir hata oluştu.', 'kg-core');
                    break;
            }
            
            if ($text) {
                echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($text) . '</p></div>';
            }
        }

        if ($action === 'edit' && $vaccine_id) {
            $vaccine = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $vaccine_id
            ), ARRAY_A);
            
            if ($vaccine) {
                $this->render_form($vaccine);
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Aşı bulunamadı.', 'kg-core') . '</p></div>';
                $this->render_list();
            }
        } elseif ($action === 'add') {
            $this->render_form();
        } else {
            $this->render_list();
        }

        echo '</div>';
    }

    /**
     * Render vaccine list
     */
    private function render_list() {
        global $wpdb;

        $vaccines = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY sort_order ASC, code ASC",
            ARRAY_A
        );

        ?>
        <div style="margin: 20px 0;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=kg-health&action=add')); ?>" class="button button-primary">
                <?php esc_html_e('Yeni Aşı Ekle', 'kg-core'); ?>
            </a>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-left: 10px;">
                <?php wp_nonce_field('kg_load_vaccines', 'kg_load_vaccines_nonce'); ?>
                <input type="hidden" name="action" value="kg_load_vaccines">
                <button type="submit" class="button" onclick="return confirm('JSON dosyasından aşı verilerini yüklemek istediğinize emin misiniz? Mevcut veriler güncellenecektir.');">
                    <?php esc_html_e('JSON\'dan Yükle', 'kg-core'); ?>
                </button>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php esc_html_e('ID', 'kg-core'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Kod', 'kg-core'); ?></th>
                    <th><?php esc_html_e('Aşı Adı', 'kg-core'); ?></th>
                    <th><?php esc_html_e('Kısa Ad', 'kg-core'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Zorunlu', 'kg-core'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Aktif', 'kg-core'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Sıra', 'kg-core'); ?></th>
                    <th style="width: 200px;"><?php esc_html_e('İşlemler', 'kg-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vaccines)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            <?php esc_html_e('Henüz aşı tanımı bulunmuyor. "JSON\'dan Yükle" butonunu kullanarak başlayabilirsiniz.', 'kg-core'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vaccines as $vaccine): ?>
                        <tr>
                            <td><?php echo esc_html($vaccine['id']); ?></td>
                            <td><code><?php echo esc_html($vaccine['code']); ?></code></td>
                            <td><strong><?php echo esc_html($vaccine['name']); ?></strong></td>
                            <td><?php echo esc_html($vaccine['name_short']); ?></td>
                            <td><?php echo $vaccine['is_mandatory'] ? '✓' : '-'; ?></td>
                            <td>
                                <?php if ($vaccine['is_active']): ?>
                                    <span style="color: green;">✓ Aktif</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($vaccine['sort_order']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kg-health&action=edit&id=' . $vaccine['id'])); ?>" class="button button-small">
                                    <?php esc_html_e('Düzenle', 'kg-core'); ?>
                                </a>
                                
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('kg_toggle_vaccine_' . $vaccine['id'], 'kg_toggle_vaccine_nonce'); ?>
                                    <input type="hidden" name="action" value="kg_toggle_vaccine">
                                    <input type="hidden" name="vaccine_id" value="<?php echo esc_attr($vaccine['id']); ?>">
                                    <button type="submit" class="button button-small">
                                        <?php echo $vaccine['is_active'] ? esc_html__('Pasifleştir', 'kg-core') : esc_html__('Aktifleştir', 'kg-core'); ?>
                                    </button>
                                </form>
                                
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('kg_delete_vaccine_' . $vaccine['id'], 'kg_delete_vaccine_nonce'); ?>
                                    <input type="hidden" name="action" value="kg_delete_vaccine">
                                    <input type="hidden" name="vaccine_id" value="<?php echo esc_attr($vaccine['id']); ?>">
                                    <button type="submit" class="button button-small" onclick="return confirm('Bu aşıyı silmek istediğinize emin misiniz?');">
                                        <?php esc_html_e('Sil', 'kg-core'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render add/edit form
     * 
     * @param array|null $vaccine Vaccine data for editing, null for adding
     */
    private function render_form($vaccine = null) {
        $is_edit = !empty($vaccine);
        $form_action = $is_edit ? 'kg_edit_vaccine' : 'kg_add_vaccine';
        $nonce_action = $is_edit ? 'kg_edit_vaccine_' . $vaccine['id'] : 'kg_add_vaccine';

        ?>
        <div style="max-width: 800px;">
            <h2><?php echo $is_edit ? esc_html__('Aşı Düzenle', 'kg-core') : esc_html__('Yeni Aşı Ekle', 'kg-core'); ?></h2>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field($nonce_action, 'kg_vaccine_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($form_action); ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="vaccine_id" value="<?php echo esc_attr($vaccine['id']); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="code"><?php esc_html_e('Aşı Kodu', 'kg-core'); ?> *</label></th>
                        <td>
                            <input type="text" name="code" id="code" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($vaccine['code']) : ''; ?>" 
                                   <?php echo $is_edit ? 'readonly' : 'required'; ?>>
                            <p class="description"><?php esc_html_e('Örnek: BCG, HEPATIT_B_1, DaBT_IPA_HIB_1', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="name"><?php esc_html_e('Aşı Adı', 'kg-core'); ?> *</label></th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($vaccine['name']) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="name_short"><?php esc_html_e('Kısa Ad', 'kg-core'); ?></label></th>
                        <td>
                            <input type="text" name="name_short" id="name_short" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($vaccine['name_short']) : ''; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="description"><?php esc_html_e('Açıklama', 'kg-core'); ?></label></th>
                        <td>
                            <textarea name="description" id="description" class="large-text" rows="3"><?php echo $is_edit ? esc_textarea($vaccine['description']) : ''; ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="timing_rule"><?php esc_html_e('Zamanlama Kuralı (JSON)', 'kg-core'); ?> *</label></th>
                        <td>
                            <textarea name="timing_rule" id="timing_rule" class="large-text code" rows="5" required><?php echo $is_edit ? esc_textarea($vaccine['timing_rule']) : '{"type":"age_based","age_months":0}'; ?></textarea>
                            <p class="description"><?php esc_html_e('Örnek: {"type":"age_based","age_months":2}', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="min_age_days"><?php esc_html_e('Min. Yaş (gün)', 'kg-core'); ?></label></th>
                        <td>
                            <input type="number" name="min_age_days" id="min_age_days" class="small-text" min="0"
                                   value="<?php echo $is_edit ? esc_attr($vaccine['min_age_days']) : '0'; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="max_age_days"><?php esc_html_e('Max. Yaş (gün)', 'kg-core'); ?></label></th>
                        <td>
                            <input type="number" name="max_age_days" id="max_age_days" class="small-text" min="0"
                                   value="<?php echo $is_edit && $vaccine['max_age_days'] ? esc_attr($vaccine['max_age_days']) : ''; ?>">
                            <p class="description"><?php esc_html_e('Boş bırakılabilir', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="is_mandatory"><?php esc_html_e('Zorunlu Aşı', 'kg-core'); ?></label></th>
                        <td>
                            <input type="checkbox" name="is_mandatory" id="is_mandatory" value="1" 
                                   <?php checked($is_edit ? $vaccine['is_mandatory'] : true); ?>>
                            <label for="is_mandatory"><?php esc_html_e('Bu aşı zorunlu aşı takviminde yer alır', 'kg-core'); ?></label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="depends_on"><?php esc_html_e('Bağımlı Olduğu Aşı', 'kg-core'); ?></label></th>
                        <td>
                            <input type="text" name="depends_on" id="depends_on" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($vaccine['depends_on']) : ''; ?>">
                            <p class="description"><?php esc_html_e('Önceki doz kodu (örn: DaBT_IPA_HIB_1)', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="schedule_version"><?php esc_html_e('Takvim Versiyonu', 'kg-core'); ?></label></th>
                        <td>
                            <input type="text" name="schedule_version" id="schedule_version" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($vaccine['schedule_version']) : 'TR_2026_v1'; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="sort_order"><?php esc_html_e('Sıralama', 'kg-core'); ?></label></th>
                        <td>
                            <input type="number" name="sort_order" id="sort_order" class="small-text" min="0"
                                   value="<?php echo $is_edit ? esc_attr($vaccine['sort_order']) : '0'; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="is_active"><?php esc_html_e('Aktif', 'kg-core'); ?></label></th>
                        <td>
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   <?php checked($is_edit ? $vaccine['is_active'] : true); ?>>
                            <label for="is_active"><?php esc_html_e('Bu aşı aktif', 'kg-core'); ?></label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $is_edit ? esc_html__('Güncelle', 'kg-core') : esc_html__('Ekle', 'kg-core'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kg-health')); ?>" class="button">
                        <?php esc_html_e('İptal', 'kg-core'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle add vaccine
     */
    public function handle_add_vaccine() {
        check_admin_referer('kg_add_vaccine', 'kg_vaccine_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;

        $data = [
            'code' => sanitize_text_field($_POST['code']),
            'name' => sanitize_text_field($_POST['name']),
            'name_short' => !empty($_POST['name_short']) ? sanitize_text_field($_POST['name_short']) : sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'timing_rule' => wp_unslash($_POST['timing_rule']),
            'min_age_days' => absint($_POST['min_age_days']),
            'max_age_days' => !empty($_POST['max_age_days']) ? absint($_POST['max_age_days']) : null,
            'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
            'depends_on' => !empty($_POST['depends_on']) ? sanitize_text_field($_POST['depends_on']) : null,
            'schedule_version' => sanitize_text_field($_POST['schedule_version']),
            'sort_order' => absint($_POST['sort_order']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($this->table_name, $data);

        if ($result) {
            wp_redirect(add_query_arg('message', 'added', admin_url('admin.php?page=kg-health')));
        } else {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=kg-health')));
        }
        exit;
    }

    /**
     * Handle edit vaccine
     */
    public function handle_edit_vaccine() {
        $vaccine_id = absint($_POST['vaccine_id']);
        check_admin_referer('kg_edit_vaccine_' . $vaccine_id, 'kg_vaccine_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'name_short' => !empty($_POST['name_short']) ? sanitize_text_field($_POST['name_short']) : sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'timing_rule' => wp_unslash($_POST['timing_rule']),
            'min_age_days' => absint($_POST['min_age_days']),
            'max_age_days' => !empty($_POST['max_age_days']) ? absint($_POST['max_age_days']) : null,
            'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
            'depends_on' => !empty($_POST['depends_on']) ? sanitize_text_field($_POST['depends_on']) : null,
            'schedule_version' => sanitize_text_field($_POST['schedule_version']),
            'sort_order' => absint($_POST['sort_order']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->update($this->table_name, $data, ['id' => $vaccine_id]);

        if ($result !== false) {
            wp_redirect(add_query_arg('message', 'updated', admin_url('admin.php?page=kg-health')));
        } else {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=kg-health')));
        }
        exit;
    }

    /**
     * Handle delete vaccine
     */
    public function handle_delete_vaccine() {
        $vaccine_id = absint($_POST['vaccine_id']);
        check_admin_referer('kg_delete_vaccine_' . $vaccine_id, 'kg_delete_vaccine_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;
        $result = $wpdb->delete($this->table_name, ['id' => $vaccine_id]);

        if ($result) {
            wp_redirect(add_query_arg('message', 'deleted', admin_url('admin.php?page=kg-health')));
        } else {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=kg-health')));
        }
        exit;
    }

    /**
     * Handle toggle vaccine active status
     */
    public function handle_toggle_vaccine() {
        $vaccine_id = absint($_POST['vaccine_id']);
        check_admin_referer('kg_toggle_vaccine_' . $vaccine_id, 'kg_toggle_vaccine_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;
        
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM {$this->table_name} WHERE id = %d",
            $vaccine_id
        ));

        $new_status = $current_status ? 0 : 1;
        
        $wpdb->update(
            $this->table_name,
            ['is_active' => $new_status, 'updated_at' => current_time('mysql')],
            ['id' => $vaccine_id]
        );

        wp_redirect(add_query_arg('message', 'toggled', admin_url('admin.php?page=kg-health')));
        exit;
    }

    /**
     * Handle load from JSON
     */
    public function handle_load_from_json() {
        check_admin_referer('kg_load_vaccines', 'kg_load_vaccines_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        $result = $this->vaccine_manager->load_vaccine_master_data();

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(['message' => 'load_error', 'error_msg' => urlencode($result->get_error_message())], admin_url('admin.php?page=kg-health')));
        } else {
            wp_redirect(add_query_arg('message', 'loaded', admin_url('admin.php?page=kg-health')));
        }
        exit;
    }
}

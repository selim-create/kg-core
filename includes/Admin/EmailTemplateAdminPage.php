<?php
namespace KG_Core\Admin;

use KG_Core\Notifications\EmailTemplateRenderer;

/**
 * EmailTemplateAdminPage - Email template management
 * 
 * Admin interface for managing email templates in kg_email_templates table
 */
class EmailTemplateAdminPage {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'kg_email_templates';
        
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_kg_add_email_template', [$this, 'handle_add_template']);
        add_action('admin_post_kg_edit_email_template', [$this, 'handle_edit_template']);
        add_action('admin_post_kg_delete_email_template', [$this, 'handle_delete_template']);
        add_action('admin_post_kg_test_email', [$this, 'handle_test_email']);
    }

    /**
     * Get Turkish category label
     * 
     * @param string $category Category key
     * @return string Turkish label
     */
    private function get_category_label($category) {
        $category_labels = [
            'vaccination' => 'Aşı',
            'growth' => 'Büyüme',
            'nutrition' => 'Beslenme',
            'system' => 'Sistem',
            'marketing' => 'Pazarlama'
        ];
        
        return $category_labels[$category] ?? ucfirst($category);
    }

    /**
     * Register admin menu
     */
    public function add_menu() {
        add_submenu_page(
            'kg-core',
            __('E-posta Şablonları', 'kg-core'),
            __('E-posta Şablonları', 'kg-core'),
            'manage_options',
            'kg-email-templates',
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
        $template_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('E-posta Şablonları', 'kg-core') . '</h1>';

        // Display admin notices
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $class = 'notice notice-success is-dismissible';
            $text = '';
            
            switch ($message) {
                case 'added':
                    $text = __('Şablon başarıyla eklendi.', 'kg-core');
                    break;
                case 'updated':
                    $text = __('Şablon başarıyla güncellendi.', 'kg-core');
                    break;
                case 'deleted':
                    $text = __('Şablon başarıyla silindi.', 'kg-core');
                    break;
                case 'test_sent':
                    $text = __('Test e-postası gönderildi. Lütfen gelen kutunuzu kontrol edin.', 'kg-core');
                    break;
                case 'test_failed':
                    $class = 'notice notice-error is-dismissible';
                    $text = __('Test e-postası gönderilemedi.', 'kg-core');
                    break;
                case 'template_not_found':
                    $class = 'notice notice-error is-dismissible';
                    $text = __('Şablon bulunamadı.', 'kg-core');
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

        if ($action === 'edit' && $template_id) {
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $template_id
            ), ARRAY_A);
            
            if ($template) {
                $this->render_form($template);
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Şablon bulunamadı.', 'kg-core') . '</p></div>';
                $this->render_list();
            }
        } elseif ($action === 'add') {
            $this->render_form();
        } elseif ($action === 'preview' && $template_id) {
            $this->render_preview($template_id);
        } else {
            $this->render_list();
        }

        echo '</div>';
    }

    /**
     * Render template list
     */
    private function render_list() {
        global $wpdb;

        $category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        
        $where = '';
        if ($category_filter) {
            $where = $wpdb->prepare(" WHERE category = %s", $category_filter);
        }

        $templates = $wpdb->get_results(
            "SELECT * FROM {$this->table_name}{$where} ORDER BY category ASC, name ASC",
            ARRAY_A
        );

        $categories = ['vaccination', 'growth', 'nutrition', 'system', 'marketing'];

        ?>
        <div style="margin: 20px 0;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=kg-email-templates&action=add')); ?>" class="button button-primary">
                <?php esc_html_e('Yeni Şablon Ekle', 'kg-core'); ?>
            </a>
            
            <select onchange="window.location.href=this.value;" style="margin-left: 10px;">
                <option value="<?php echo esc_url(admin_url('admin.php?page=kg-email-templates')); ?>" <?php selected($category_filter, ''); ?>>
                    <?php esc_html_e('Tüm Kategoriler', 'kg-core'); ?>
                </option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_url(admin_url('admin.php?page=kg-email-templates&category=' . $cat)); ?>" <?php selected($category_filter, $cat); ?>>
                        <?php echo esc_html($this->get_category_label($cat)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php esc_html_e('ID', 'kg-core'); ?></th>
                    <th style="width: 150px;"><?php esc_html_e('Anahtar', 'kg-core'); ?></th>
                    <th><?php esc_html_e('Şablon Adı', 'kg-core'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Kategori', 'kg-core'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Aktif', 'kg-core'); ?></th>
                    <th style="width: 250px;"><?php esc_html_e('İşlemler', 'kg-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">
                            <?php esc_html_e('Şablon bulunamadı.', 'kg-core'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><?php echo esc_html($template['id']); ?></td>
                            <td><code><?php echo esc_html($template['template_key']); ?></code></td>
                            <td><strong><?php echo esc_html($template['name']); ?></strong></td>
                            <td><?php echo esc_html($this->get_category_label($template['category'])); ?></td>
                            <td>
                                <?php if ($template['is_active']): ?>
                                    <span style="color: green;">✓ Aktif</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kg-email-templates&action=edit&id=' . $template['id'])); ?>" class="button button-small">
                                    <?php esc_html_e('Düzenle', 'kg-core'); ?>
                                </a>
                                
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kg-email-templates&action=preview&id=' . $template['id'])); ?>" class="button button-small" target="_blank">
                                    <?php esc_html_e('Önizle', 'kg-core'); ?>
                                </a>
                                
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('kg_test_email_' . $template['id'], 'kg_test_email_nonce'); ?>
                                    <input type="hidden" name="action" value="kg_test_email">
                                    <input type="hidden" name="template_id" value="<?php echo esc_attr($template['id']); ?>">
                                    <button type="submit" class="button button-small">
                                        <?php esc_html_e('Test Gönder', 'kg-core'); ?>
                                    </button>
                                </form>
                                
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                    <?php wp_nonce_field('kg_delete_email_template_' . $template['id'], 'kg_delete_email_template_nonce'); ?>
                                    <input type="hidden" name="action" value="kg_delete_email_template">
                                    <input type="hidden" name="template_id" value="<?php echo esc_attr($template['id']); ?>">
                                    <button type="submit" class="button button-small" onclick="return confirm('Bu şablonu silmek istediğinize emin misiniz?');">
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
     * @param array|null $template Template data for editing, null for adding
     */
    private function render_form($template = null) {
        $is_edit = !empty($template);
        $form_action = $is_edit ? 'kg_edit_email_template' : 'kg_add_email_template';
        $nonce_action = $is_edit ? 'kg_edit_email_template_' . $template['id'] : 'kg_add_email_template';

        ?>
        <div style="max-width: 900px;">
            <h2><?php echo $is_edit ? esc_html__('Şablon Düzenle', 'kg-core') : esc_html__('Yeni Şablon Ekle', 'kg-core'); ?></h2>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field($nonce_action, 'kg_email_template_nonce'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($form_action); ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="template_id" value="<?php echo esc_attr($template['id']); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="template_key"><?php esc_html_e('Şablon Anahtarı', 'kg-core'); ?> *</label></th>
                        <td>
                            <input type="text" name="template_key" id="template_key" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($template['template_key']) : ''; ?>" 
                                   <?php echo $is_edit ? 'readonly' : 'required'; ?>>
                            <p class="description"><?php esc_html_e('Örnek: vaccine_reminder_3day', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="name"><?php esc_html_e('Şablon Adı', 'kg-core'); ?> *</label></th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($template['name']) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="category"><?php esc_html_e('Kategori', 'kg-core'); ?> *</label></th>
                        <td>
                            <select name="category" id="category" required>
                                <option value="vaccination" <?php selected($is_edit ? $template['category'] : '', 'vaccination'); ?>><?php esc_html_e('Aşı', 'kg-core'); ?></option>
                                <option value="growth" <?php selected($is_edit ? $template['category'] : '', 'growth'); ?>><?php esc_html_e('Büyüme', 'kg-core'); ?></option>
                                <option value="nutrition" <?php selected($is_edit ? $template['category'] : '', 'nutrition'); ?>><?php esc_html_e('Beslenme', 'kg-core'); ?></option>
                                <option value="system" <?php selected($is_edit ? $template['category'] : '', 'system'); ?>><?php esc_html_e('Sistem', 'kg-core'); ?></option>
                                <option value="marketing" <?php selected($is_edit ? $template['category'] : '', 'marketing'); ?>><?php esc_html_e('Pazarlama', 'kg-core'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="subject"><?php esc_html_e('Konu Satırı', 'kg-core'); ?> *</label></th>
                        <td>
                            <input type="text" name="subject" id="subject" class="large-text" 
                                   value="<?php echo $is_edit ? esc_attr($template['subject']) : ''; ?>" required>
                            <p class="description"><?php esc_html_e('Placeholder kullanabilirsiniz: {{parent_name}}, {{child_name}}', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="body_html"><?php esc_html_e('HTML İçerik', 'kg-core'); ?> *</label></th>
                        <td>
                            <textarea name="body_html" id="body_html" class="large-text code" rows="15" required><?php echo $is_edit ? esc_textarea($template['body_html']) : ''; ?></textarea>
                            <p class="description"><?php esc_html_e('HTML e-posta içeriği', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="body_text"><?php esc_html_e('Düz Metin İçerik', 'kg-core'); ?></label></th>
                        <td>
                            <textarea name="body_text" id="body_text" class="large-text" rows="10"><?php echo $is_edit ? esc_textarea($template['body_text']) : ''; ?></textarea>
                            <p class="description"><?php esc_html_e('Fallback için düz metin versiyonu', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="placeholders"><?php esc_html_e('Kullanılabilir Placeholder\'lar', 'kg-core'); ?></label></th>
                        <td>
                            <textarea name="placeholders" id="placeholders" class="large-text code" rows="3"><?php echo $is_edit && $template['placeholders'] ? esc_textarea($template['placeholders']) : '["parent_name","child_name","app_url"]'; ?></textarea>
                            <p class="description"><?php esc_html_e('JSON array formatında. Örnek: ["parent_name","child_name","vaccine_name"]', 'kg-core'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="is_active"><?php esc_html_e('Aktif', 'kg-core'); ?></label></th>
                        <td>
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   <?php checked($is_edit ? $template['is_active'] : true); ?>>
                            <label for="is_active"><?php esc_html_e('Bu şablon aktif', 'kg-core'); ?></label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $is_edit ? esc_html__('Güncelle', 'kg-core') : esc_html__('Ekle', 'kg-core'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kg-email-templates')); ?>" class="button">
                        <?php esc_html_e('İptal', 'kg-core'); ?>
                    </a>
                </p>
            </form>
            
            <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <h3><?php esc_html_e('Yaygın Placeholder\'lar', 'kg-core'); ?></h3>
                <ul style="columns: 2;">
                    <li><code>{{parent_name}}</code> - Ebeveyn adı</li>
                    <li><code>{{child_name}}</code> - Çocuk adı</li>
                    <li><code>{{vaccine_name}}</code> - Aşı adı</li>
                    <li><code>{{vaccine_code}}</code> - Aşı kodu</li>
                    <li><code>{{scheduled_date}}</code> - Planlanan tarih</li>
                    <li><code>{{days_remaining}}</code> - Kalan gün</li>
                    <li><code>{{app_url}}</code> - Uygulama URL</li>
                    <li><code>{{unsubscribe_url}}</code> - Abonelikten çıkma URL</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render template preview
     * 
     * @param int $template_id Template ID
     */
    private function render_preview($template_id) {
        global $wpdb;

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $template_id
        ), ARRAY_A);

        if (!$template) {
            echo '<p>' . esc_html__('Şablon bulunamadı.', 'kg-core') . '</p>';
            return;
        }

        $sample_data = [
            '{{parent_name}}' => 'Ayşe Yılmaz',
            '{{child_name}}' => 'Ahmet',
            '{{vaccine_name}}' => 'KKK (Kızamık, Kızamıkçık, Kabakulak)',
            '{{vaccine_code}}' => 'KKK_1',
            '{{scheduled_date}}' => '15 Ocak 2025',
            '{{days_remaining}}' => '3',
            '{{app_url}}' => 'https://kidsgourmet.com.tr',
            '{{unsubscribe_url}}' => 'https://kidsgourmet.com.tr/unsubscribe'
        ];

        $preview_html = str_replace(array_keys($sample_data), array_values($sample_data), $template['body_html']);

        // Wrap preview with EmailTemplateRenderer for full HTML experience
        $full_preview = EmailTemplateRenderer::wrap_content($preview_html, $template['category']);
        
        // Replace unsubscribe_url placeholder in wrapped content (needed in footer)
        $full_preview = str_replace('{{unsubscribe_url}}', $sample_data['{{unsubscribe_url}}'], $full_preview);

        echo '<div style="max-width: 800px; margin: 0 auto; padding: 20px;">';
        echo '<h2>' . esc_html__('Şablon Önizleme', 'kg-core') . '</h2>';
        echo '<p><strong>' . esc_html__('Konu:', 'kg-core') . '</strong> ' . esc_html(str_replace(array_keys($sample_data), array_values($sample_data), $template['subject'])) . '</p>';
        echo '<hr style="margin: 20px 0;">';
        
        // Display full preview in iframe for safe rendering
        echo '<div style="background:#f5f5f5; padding:20px; border-radius:8px;">';
        echo '<iframe srcdoc="' . esc_attr($full_preview) . '" style="width:100%; height:800px; border:none; border-radius:8px; background:white;"></iframe>';
        echo '</div>';
        
        echo '<p style="margin-top: 20px;"><a href="' . esc_url(admin_url('admin.php?page=kg-email-templates')) . '" class="button">&larr; ' . esc_html__('Geri Dön', 'kg-core') . '</a></p>';
        echo '</div>';
    }

    /**
     * Handle add template
     */
    public function handle_add_template() {
        check_admin_referer('kg_add_email_template', 'kg_email_template_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;

        $data = [
            'template_key' => sanitize_text_field($_POST['template_key']),
            'name' => sanitize_text_field($_POST['name']),
            'category' => sanitize_text_field($_POST['category']),
            'subject' => sanitize_text_field($_POST['subject']),
            'body_html' => wp_kses_post($_POST['body_html']),
            'body_text' => sanitize_textarea_field($_POST['body_text']),
            'placeholders' => !empty($_POST['placeholders']) ? wp_unslash($_POST['placeholders']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->insert($this->table_name, $data);

        if ($result) {
            wp_redirect(add_query_arg('message', 'added', admin_url('admin.php?page=kg-email-templates')));
        } else {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=kg-email-templates')));
        }
        exit;
    }

    /**
     * Handle edit template
     */
    public function handle_edit_template() {
        $template_id = absint($_POST['template_id']);
        check_admin_referer('kg_edit_email_template_' . $template_id, 'kg_email_template_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'category' => sanitize_text_field($_POST['category']),
            'subject' => sanitize_text_field($_POST['subject']),
            'body_html' => wp_kses_post($_POST['body_html']),
            'body_text' => sanitize_textarea_field($_POST['body_text']),
            'placeholders' => !empty($_POST['placeholders']) ? wp_unslash($_POST['placeholders']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => current_time('mysql')
        ];

        $result = $wpdb->update($this->table_name, $data, ['id' => $template_id]);

        if ($result !== false) {
            wp_redirect(add_query_arg('message', 'updated', admin_url('admin.php?page=kg-email-templates')));
        } else {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=kg-email-templates')));
        }
        exit;
    }

    /**
     * Handle delete template
     */
    public function handle_delete_template() {
        $template_id = absint($_POST['template_id']);
        check_admin_referer('kg_delete_email_template_' . $template_id, 'kg_delete_email_template_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;
        $result = $wpdb->delete($this->table_name, ['id' => $template_id]);

        if ($result) {
            wp_redirect(add_query_arg('message', 'deleted', admin_url('admin.php?page=kg-email-templates')));
        } else {
            wp_redirect(add_query_arg('message', 'error', admin_url('admin.php?page=kg-email-templates')));
        }
        exit;
    }

    /**
     * Handle test email
     */
    public function handle_test_email() {
        $template_id = absint($_POST['template_id']);
        check_admin_referer('kg_test_email_' . $template_id, 'kg_test_email_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        global $wpdb;

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $template_id
        ), ARRAY_A);

        if (!$template) {
            wp_redirect(add_query_arg('message', 'template_not_found', admin_url('admin.php?page=kg-email-templates')));
            exit;
        }

        $current_user = wp_get_current_user();
        
        $sample_data = [
            '{{parent_name}}' => $current_user->display_name,
            '{{child_name}}' => 'Ahmet (TEST)',
            '{{vaccine_name}}' => 'KKK (Kızamık, Kızamıkçık, Kabakulak) - TEST',
            '{{vaccine_code}}' => 'KKK_1',
            '{{scheduled_date}}' => \KG_Core\Utils\Helper::format_turkish_date(time()),
            '{{days_remaining}}' => '3',
            '{{app_url}}' => 'https://kidsgourmet.com.tr',
            '{{unsubscribe_url}}' => 'https://kidsgourmet.com.tr/unsubscribe'
        ];

        $subject = str_replace(array_keys($sample_data), array_values($sample_data), $template['subject']);
        $body = str_replace(array_keys($sample_data), array_values($sample_data), $template['body_html']);

        // Wrap body with EmailTemplateRenderer for modern HTML design
        $wrapped_body = EmailTemplateRenderer::wrap_content($body, $template['category']);
        
        // Replace unsubscribe_url placeholder in wrapped content (needed in footer)
        $wrapped_body = str_replace('{{unsubscribe_url}}', $sample_data['{{unsubscribe_url}}'], $wrapped_body);

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $result = wp_mail($current_user->user_email, '[TEST] ' . $subject, $wrapped_body, $headers);

        if ($result) {
            wp_redirect(add_query_arg('message', 'test_sent', admin_url('admin.php?page=kg-email-templates')));
        } else {
            wp_redirect(add_query_arg('message', 'test_failed', admin_url('admin.php?page=kg-email-templates')));
        }
        exit;
    }
}

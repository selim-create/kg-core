<?php
namespace KG_Core\Admin;

/**
 * SocialMediaSettings - Admin page for managing social media URLs
 * 
 * Allows administrators to configure social media links used in email templates
 */
class SocialMediaSettings {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_kg_save_social_media', [$this, 'handle_save']);
    }

    /**
     * Register admin menu
     */
    public function add_menu() {
        add_submenu_page(
            'kg-core',
            __('Sosyal Medya Ayarları', 'kg-core'),
            __('Sosyal Medya', 'kg-core'),
            'manage_options',
            'kg-social-media',
            [$this, 'render_page']
        );
    }

    /**
     * Render settings page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Bu sayfaya erişim yetkiniz yok.', 'kg-core'));
        }

        // Get current values
        $instagram = get_option('kg_social_instagram', 'https://instagram.com/kidsgourmet');
        $facebook = get_option('kg_social_facebook', 'https://facebook.com/kidsgourmet');
        $twitter = get_option('kg_social_twitter', 'https://twitter.com/kidsgourmet');
        $youtube = get_option('kg_social_youtube', 'https://youtube.com/@kidsgourmet');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sosyal Medya Ayarları', 'kg-core'); ?></h1>
            
            <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Ayarlar başarıyla kaydedildi.', 'kg-core'); ?></p>
                </div>
            <?php endif; ?>

            <p><?php esc_html_e('E-posta şablonlarında kullanılacak sosyal medya URL\'lerini buradan ayarlayabilirsiniz.', 'kg-core'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('kg_save_social_media', 'kg_social_media_nonce'); ?>
                <input type="hidden" name="action" value="kg_save_social_media">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kg_social_instagram">
                                📷 <?php esc_html_e('Instagram', 'kg-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" name="kg_social_instagram" id="kg_social_instagram" 
                                   class="regular-text" value="<?php echo esc_attr($instagram); ?>" 
                                   placeholder="https://instagram.com/kidsgourmet">
                            <p class="description"><?php esc_html_e('Instagram profil URL\'niz', 'kg-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kg_social_facebook">
                                👍 <?php esc_html_e('Facebook', 'kg-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" name="kg_social_facebook" id="kg_social_facebook" 
                                   class="regular-text" value="<?php echo esc_attr($facebook); ?>" 
                                   placeholder="https://facebook.com/kidsgourmet">
                            <p class="description"><?php esc_html_e('Facebook sayfası URL\'niz', 'kg-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kg_social_twitter">
                                🐦 <?php esc_html_e('Twitter/X', 'kg-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" name="kg_social_twitter" id="kg_social_twitter" 
                                   class="regular-text" value="<?php echo esc_attr($twitter); ?>" 
                                   placeholder="https://twitter.com/kidsgourmet">
                            <p class="description"><?php esc_html_e('Twitter/X profil URL\'niz', 'kg-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kg_social_youtube">
                                ▶️ <?php esc_html_e('YouTube', 'kg-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" name="kg_social_youtube" id="kg_social_youtube" 
                                   class="regular-text" value="<?php echo esc_attr($youtube); ?>" 
                                   placeholder="https://youtube.com/@kidsgourmet">
                            <p class="description"><?php esc_html_e('YouTube kanal URL\'niz', 'kg-core'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Değişiklikleri Kaydet', 'kg-core'); ?>
                    </button>
                </p>
            </form>

            <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <h3><?php esc_html_e('Kullanım', 'kg-core'); ?></h3>
                <p><?php esc_html_e('Bu URL\'ler otomatik olarak tüm e-posta şablonlarında kullanılır. Değişiklikler anında etkili olur.', 'kg-core'); ?></p>
                <p>
                    <strong><?php esc_html_e('Not:', 'kg-core'); ?></strong> 
                    <?php esc_html_e('URL\'leri boş bırakırsanız varsayılan değerler kullanılır.', 'kg-core'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Handle form submission
     */
    public function handle_save() {
        check_admin_referer('kg_save_social_media', 'kg_social_media_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Bu işlemi yapmaya yetkiniz yok.', 'kg-core'));
        }

        // Sanitize and save URLs
        $instagram = !empty($_POST['kg_social_instagram']) ? esc_url_raw($_POST['kg_social_instagram']) : '';
        $facebook = !empty($_POST['kg_social_facebook']) ? esc_url_raw($_POST['kg_social_facebook']) : '';
        $twitter = !empty($_POST['kg_social_twitter']) ? esc_url_raw($_POST['kg_social_twitter']) : '';
        $youtube = !empty($_POST['kg_social_youtube']) ? esc_url_raw($_POST['kg_social_youtube']) : '';

        update_option('kg_social_instagram', $instagram);
        update_option('kg_social_facebook', $facebook);
        update_option('kg_social_twitter', $twitter);
        update_option('kg_social_youtube', $youtube);

        wp_redirect(add_query_arg('message', 'saved', admin_url('admin.php?page=kg-social-media')));
        exit;
    }
}

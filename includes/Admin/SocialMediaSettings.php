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
        $youtube = get_option('kg_social_youtube', 'https://youtube.com/@kidsgourmet');
        $twitter = get_option('kg_social_twitter', 'https://twitter.com/kidsgourmet');
        $tiktok = get_option('kg_social_tiktok', 'https://tiktok.com/@kidsgourmet');
        $pinterest = get_option('kg_social_pinterest', 'https://pinterest.com/kidsgourmet');
        $facebook = get_option('kg_social_facebook', 'https://facebook.com/kidsgourmet');
        $email_logo = get_option('kg_email_logo', '');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sosyal Medya Ayarları', 'kg-core'); ?></h1>
            
            <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Ayarlar başarıyla kaydedildi.', 'kg-core'); ?></p>
                </div>
            <?php endif; ?>

            <p><?php esc_html_e('E-posta şablonlarında kullanılacak sosyal medya URL\'lerini ve logo ayarlarını buradan ayarlayabilirsiniz.', 'kg-core'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('kg_save_social_media', 'kg_social_media_nonce'); ?>
                <input type="hidden" name="action" value="kg_save_social_media">

                <h2><?php esc_html_e('E-posta Logosu', 'kg-core'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kg_email_logo">
                                🖼️ <?php esc_html_e('Logo URL', 'kg-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" name="kg_email_logo" id="kg_email_logo" 
                                   class="regular-text" value="<?php echo esc_attr($email_logo); ?>" 
                                   placeholder="https://kidsgourmet.com.tr/logo.png">
                            <button type="button" class="button" id="kg_upload_logo_button">
                                <?php esc_html_e('Medya Kütüphanesinden Seç', 'kg-core'); ?>
                            </button>
                            <p class="description"><?php esc_html_e('E-posta başlığında gösterilecek logo URL\'si. Boş bırakırsanız metin logo kullanılır.', 'kg-core'); ?></p>
                            <?php if ($email_logo): ?>
                                <div id="kg_logo_preview" style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($email_logo); ?>" alt="Logo Preview" style="max-height: 80px; border: 1px solid #ddd; padding: 5px; background: #f9f9f9;">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Sosyal Medya Hesapları', 'kg-core'); ?></h2>
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
                            <label for="kg_social_tiktok">
                                🎵 <?php esc_html_e('TikTok', 'kg-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" name="kg_social_tiktok" id="kg_social_tiktok" 
                                   class="regular-text" value="<?php echo esc_attr($tiktok); ?>" 
                                   placeholder="https://tiktok.com/@kidsgourmet">
                            <p class="description"><?php esc_html_e('TikTok profil URL\'niz', 'kg-core'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="kg_social_pinterest">
                                📌 <?php esc_html_e('Pinterest', 'kg-core'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" name="kg_social_pinterest" id="kg_social_pinterest" 
                                   class="regular-text" value="<?php echo esc_attr($pinterest); ?>" 
                                   placeholder="https://pinterest.com/kidsgourmet">
                            <p class="description"><?php esc_html_e('Pinterest profil URL\'niz', 'kg-core'); ?></p>
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
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Değişiklikleri Kaydet', 'kg-core'); ?>
                    </button>
                </p>
            </form>

            <div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <h3><?php esc_html_e('Kullanım', 'kg-core'); ?></h3>
                <p><?php esc_html_e('Bu URL\'ler ve logo otomatik olarak tüm e-posta şablonlarında kullanılır. Değişiklikler anında etkili olur.', 'kg-core'); ?></p>
                <p>
                    <strong><?php esc_html_e('Not:', 'kg-core'); ?></strong> 
                    <?php esc_html_e('URL\'leri boş bırakırsanız varsayılan değerler kullanılır. Logo boşsa metin tabanlı logo gösterilir.', 'kg-core'); ?>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            $('#kg_upload_logo_button').on('click', function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: '<?php esc_html_e('E-posta Logosu Seç', 'kg-core'); ?>',
                    button: {
                        text: '<?php esc_html_e('Bu Logoyu Kullan', 'kg-core'); ?>'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#kg_email_logo').val(attachment.url);
                    
                    // Show preview
                    if ($('#kg_logo_preview').length) {
                        $('#kg_logo_preview img').attr('src', attachment.url);
                    } else {
                        $('#kg_email_logo').parent().append(
                            '<div id="kg_logo_preview" style="margin-top: 10px;">' +
                            '<img src="' + attachment.url + '" alt="Logo Preview" style="max-height: 80px; border: 1px solid #ddd; padding: 5px; background: #f9f9f9;">' +
                            '</div>'
                        );
                    }
                });
                
                mediaUploader.open();
            });
        });
        </script>
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
        $youtube = !empty($_POST['kg_social_youtube']) ? esc_url_raw($_POST['kg_social_youtube']) : '';
        $twitter = !empty($_POST['kg_social_twitter']) ? esc_url_raw($_POST['kg_social_twitter']) : '';
        $tiktok = !empty($_POST['kg_social_tiktok']) ? esc_url_raw($_POST['kg_social_tiktok']) : '';
        $pinterest = !empty($_POST['kg_social_pinterest']) ? esc_url_raw($_POST['kg_social_pinterest']) : '';
        $facebook = !empty($_POST['kg_social_facebook']) ? esc_url_raw($_POST['kg_social_facebook']) : '';
        $email_logo = !empty($_POST['kg_email_logo']) ? esc_url_raw($_POST['kg_email_logo']) : '';

        update_option('kg_social_instagram', $instagram);
        update_option('kg_social_youtube', $youtube);
        update_option('kg_social_twitter', $twitter);
        update_option('kg_social_tiktok', $tiktok);
        update_option('kg_social_pinterest', $pinterest);
        update_option('kg_social_facebook', $facebook);
        update_option('kg_email_logo', $email_logo);

        wp_redirect(add_query_arg('message', 'saved', admin_url('admin.php?page=kg-social-media')));
        exit;
    }
}

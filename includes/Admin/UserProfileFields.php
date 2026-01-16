<?php
namespace KG_Core\Admin;

class UserProfileFields {
    
    public function __construct() {
        // Add fields to user profile edit page
        add_action( 'show_user_profile', [ $this, 'add_expert_fields' ] );
        add_action( 'edit_user_profile', [ $this, 'add_expert_fields' ] );
        
        // Save the fields
        add_action( 'personal_options_update', [ $this, 'save_expert_fields' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_expert_fields' ] );
        
        // Enqueue media scripts for avatar upload
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }
    
    /**
     * Enqueue scripts for media uploader
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook === 'user-edit.php' || $hook === 'profile.php' ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'kg-admin-user-profile',
                plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/admin-user-profile.js',
                [ 'jquery' ],
                '1.0.1',
                true
            );
        }
    }
    
    /**
     * Add expert profile fields to user edit page
     */
    public function add_expert_fields( $user ) {
        // Check if user has expert role
        $is_expert = in_array( 'kg_expert', $user->roles ) || 
                     in_array( 'editor', $user->roles ) || 
                     in_array( 'administrator', $user->roles );
        
        // Get current values
        $biography = get_user_meta( $user->ID, '_kg_biography', true );
        $expertise = get_user_meta( $user->ID, '_kg_expertise', true );
        $social_links = get_user_meta( $user->ID, '_kg_social_links', true );
        $show_email = get_user_meta( $user->ID, '_kg_show_email', true );
        $avatar_id = get_user_meta( $user->ID, '_kg_avatar_id', true );
        $avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
        
        if ( ! is_array( $expertise ) ) {
            $expertise = [];
        }
        if ( ! is_array( $social_links ) ) {
            $social_links = [];
        }
        
        // Add nonce field for security
        wp_nonce_field( 'kg_save_expert_fields', 'kg_expert_fields_nonce' );
        ?>
        <h3>KidsGourmet Profil Bilgileri</h3>
        <table class="form-table">
            <!-- Custom Avatar -->
            <tr>
                <th><label for="kg_avatar">Profil Fotoğrafı</label></th>
                <td>
                    <div id="kg-avatar-preview" style="margin-bottom: 10px;">
                        <?php if ( $avatar_url ) : ?>
                            <img src="<?php echo esc_url( $avatar_url ); ?>" style="max-width: 150px; height: auto; border-radius: 50%;">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="kg_avatar_id" id="kg_avatar_id" value="<?php echo esc_attr( $avatar_id ); ?>">
                    <button type="button" class="button" id="kg-upload-avatar">Fotoğraf Seç</button>
                    <button type="button" class="button" id="kg-remove-avatar" <?php echo $avatar_id ? '' : 'style="display:none;"'; ?>>Fotoğrafı Kaldır</button>
                    <p class="description">Gravatar yerine kullanılacak özel profil fotoğrafı.</p>
                </td>
            </tr>
            
            <!-- Biography -->
            <tr>
                <th><label for="kg_biography">Biyografi</label></th>
                <td>
                    <textarea name="kg_biography" id="kg_biography" rows="5" cols="50" class="regular-text"><?php echo esc_textarea( $biography ); ?></textarea>
                    <p class="description">Uzman profil sayfasında görünecek biyografi.</p>
                </td>
            </tr>
            
            <!-- Show Email -->
            <tr>
                <th><label for="kg_show_email">E-posta Göster</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="kg_show_email" id="kg_show_email" value="1" <?php checked( $show_email, '1' ); ?>>
                        E-posta adresimi profil sayfamda göster
                    </label>
                </td>
            </tr>
            
            <?php if ( $is_expert ) : ?>
            <!-- Expertise (Experts only) -->
            <tr>
                <th><label for="kg_expertise">Uzmanlık Alanları</label></th>
                <td>
                    <input type="text" name="kg_expertise" id="kg_expertise" value="<?php echo esc_attr( implode( ', ', $expertise ) ); ?>" class="regular-text">
                    <p class="description">Virgülle ayırarak yazın. Örn: Bebek Beslenmesi, Alerji Yönetimi, BLW</p>
                </td>
            </tr>
            <?php endif; ?>
            
            <!-- Social Links -->
            <tr>
                <th>Sosyal Medya Hesapları</th>
                <td>
                    <p>
                        <label>Instagram:</label><br>
                        <input type="url" name="kg_social_instagram" value="<?php echo esc_url( $social_links['instagram'] ?? '' ); ?>" class="regular-text" placeholder="https://instagram.com/kullaniciadi">
                    </p>
                    <p>
                        <label>Facebook:</label><br>
                        <input type="url" name="kg_social_facebook" value="<?php echo esc_url( $social_links['facebook'] ?? '' ); ?>" class="regular-text" placeholder="https://facebook.com/kullaniciadi">
                    </p>
                    <p>
                        <label>Twitter:</label><br>
                        <input type="url" name="kg_social_twitter" value="<?php echo esc_url( $social_links['twitter'] ?? '' ); ?>" class="regular-text" placeholder="https://twitter.com/kullaniciadi">
                    </p>
                    <p>
                        <label>LinkedIn:</label><br>
                        <input type="url" name="kg_social_linkedin" value="<?php echo esc_url( $social_links['linkedin'] ?? '' ); ?>" class="regular-text" placeholder="https://linkedin.com/in/kullaniciadi">
                    </p>
                    <p>
                        <label>YouTube:</label><br>
                        <input type="url" name="kg_social_youtube" value="<?php echo esc_url( $social_links['youtube'] ?? '' ); ?>" class="regular-text" placeholder="https://youtube.com/@kanaladi">
                    </p>
                    <p>
                        <label>Website:</label><br>
                        <input type="url" name="kg_social_website" value="<?php echo esc_url( $social_links['website'] ?? '' ); ?>" class="regular-text" placeholder="https://example.com">
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save expert profile fields
     */
    public function save_expert_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }
        
        // Verify nonce for security
        if ( ! isset( $_POST['kg_expert_fields_nonce'] ) || 
             ! wp_verify_nonce( $_POST['kg_expert_fields_nonce'], 'kg_save_expert_fields' ) ) {
            return false;
        }
        
        // Save avatar
        if ( isset( $_POST['kg_avatar_id'] ) ) {
            $avatar_id = absint( $_POST['kg_avatar_id'] );
            if ( $avatar_id ) {
                update_user_meta( $user_id, '_kg_avatar_id', $avatar_id );
            } else {
                delete_user_meta( $user_id, '_kg_avatar_id' );
            }
        }
        
        // Save biography
        if ( isset( $_POST['kg_biography'] ) ) {
            update_user_meta( $user_id, '_kg_biography', sanitize_textarea_field( $_POST['kg_biography'] ) );
        }
        
        // Save show email
        $show_email = isset( $_POST['kg_show_email'] ) ? '1' : '';
        update_user_meta( $user_id, '_kg_show_email', $show_email );
        
        // Save expertise (parse comma-separated values)
        if ( isset( $_POST['kg_expertise'] ) ) {
            $expertise_raw = sanitize_text_field( $_POST['kg_expertise'] );
            $expertise = array_map( 'trim', explode( ',', $expertise_raw ) );
            $expertise = array_filter( $expertise ); // Remove empty values
            update_user_meta( $user_id, '_kg_expertise', $expertise );
        }
        
        // Save social links
        $social_links = [];
        $platforms = [ 'instagram', 'facebook', 'twitter', 'linkedin', 'youtube', 'website' ];
        foreach ( $platforms as $platform ) {
            $key = 'kg_social_' . $platform;
            if ( isset( $_POST[ $key ] ) && ! empty( $_POST[ $key ] ) ) {
                $social_links[ $platform ] = esc_url_raw( $_POST[ $key ] );
            }
        }
        update_user_meta( $user_id, '_kg_social_links', $social_links );
    }
}

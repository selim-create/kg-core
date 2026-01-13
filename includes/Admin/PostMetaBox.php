<?php
namespace KG_Core\Admin;

class PostMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_sponsor_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_sponsor_meta_data' ] );
    }

    public function add_sponsor_meta_box() {
        add_meta_box(
            'kg_sponsor_details',
            'Sponsorlu İçerik Bilgileri',
            [ $this, 'render_meta_box' ],
            'post',
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        // Get existing values
        $is_sponsored = get_post_meta( $post->ID, '_kg_is_sponsored', true );
        $sponsor_name = get_post_meta( $post->ID, '_kg_sponsor_name', true );
        $sponsor_url = get_post_meta( $post->ID, '_kg_sponsor_url', true );
        $sponsor_logo = get_post_meta( $post->ID, '_kg_sponsor_logo', true );
        $sponsor_light_logo = get_post_meta( $post->ID, '_kg_sponsor_light_logo', true );
        $direct_redirect = get_post_meta( $post->ID, '_kg_direct_redirect', true );
        $gam_impression_url = get_post_meta( $post->ID, '_kg_gam_impression_url', true );
        $gam_click_url = get_post_meta( $post->ID, '_kg_gam_click_url', true );

        // Security nonce
        wp_nonce_field( 'kg_sponsor_save', 'kg_sponsor_nonce' );
        ?>
        <div class="kg-sponsor-meta-box">
            <p>
                <label for="kg_is_sponsored">
                    <input type="checkbox" id="kg_is_sponsored" name="kg_is_sponsored" value="1" <?php checked( $is_sponsored, 1 ); ?>>
                    <strong>Sponsorlu Gönderi mi?</strong>
                </label>
            </p>

            <div id="kg-sponsor-fields" style="display: <?php echo $is_sponsored ? 'block' : 'none'; ?>;">
                <hr>
                
                <p>
                    <label for="kg_sponsor_name"><strong>Sponsor Adı:</strong></label><br>
                    <input type="text" id="kg_sponsor_name" name="kg_sponsor_name" value="<?php echo esc_attr( $sponsor_name ); ?>" style="width:100%;" placeholder="Marka adı">
                </p>

                <p>
                    <label for="kg_sponsor_url"><strong>Sponsor URL:</strong></label><br>
                    <input type="url" id="kg_sponsor_url" name="kg_sponsor_url" value="<?php echo esc_attr( $sponsor_url ); ?>" style="width:100%;" placeholder="https://example.com">
                </p>

                <p>
                    <label><strong>Sponsor Logosu:</strong></label><br>
                    <div class="kg-logo-upload">
                        <input type="hidden" id="kg_sponsor_logo" name="kg_sponsor_logo" value="<?php echo esc_attr( $sponsor_logo ); ?>">
                        <div class="kg-logo-preview" id="kg_sponsor_logo_preview">
                            <?php if ( $sponsor_logo ): 
                                $logo_url = wp_get_attachment_url( $sponsor_logo );
                            ?>
                                <img src="<?php echo esc_url( $logo_url ); ?>" style="max-width: 200px; height: auto;">
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button kg-upload-logo" data-target="kg_sponsor_logo">Logo Yükle</button>
                        <button type="button" class="button kg-remove-logo" data-target="kg_sponsor_logo" style="<?php echo $sponsor_logo ? '' : 'display:none;'; ?>">Logoyu Kaldır</button>
                    </div>
                </p>

                <p>
                    <label><strong>Sponsor Light Logosu:</strong></label><br>
                    <small>Koyu arka planlar için kullanılacak logo</small>
                    <div class="kg-logo-upload">
                        <input type="hidden" id="kg_sponsor_light_logo" name="kg_sponsor_light_logo" value="<?php echo esc_attr( $sponsor_light_logo ); ?>">
                        <div class="kg-logo-preview" id="kg_sponsor_light_logo_preview">
                            <?php if ( $sponsor_light_logo ): 
                                $light_logo_url = wp_get_attachment_url( $sponsor_light_logo );
                            ?>
                                <img src="<?php echo esc_url( $light_logo_url ); ?>" style="max-width: 200px; height: auto;">
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button kg-upload-logo" data-target="kg_sponsor_light_logo">Light Logo Yükle</button>
                        <button type="button" class="button kg-remove-logo" data-target="kg_sponsor_light_logo" style="<?php echo $sponsor_light_logo ? '' : 'display:none;'; ?>">Logoyu Kaldır</button>
                    </div>
                </p>

                <p>
                    <label><strong>Doğrudan Yönlendirme:</strong></label><br>
                    <label>
                        <input type="radio" name="kg_direct_redirect" value="1" <?php checked( $direct_redirect, '1' ); ?>>
                        Evet (Link sponsor sitesine yönlendirir)
                    </label><br>
                    <label>
                        <input type="radio" name="kg_direct_redirect" value="0" <?php checked( $direct_redirect, '0' ); ?> <?php if ( empty( $direct_redirect ) ) echo 'checked'; ?>>
                        Hayır (Link post detayına yönlendirir)
                    </label>
                </p>

                <h3>Google Ad Manager (GAM) Tracking</h3>

                <p>
                    <label for="kg_gam_impression_url"><strong>GAM Impression Pixel:</strong></label><br>
                    <input type="url" id="kg_gam_impression_url" name="kg_gam_impression_url" value="<?php echo esc_attr( $gam_impression_url ); ?>" style="width:100%;" placeholder="https://ad.doubleclick.net/...">
                    <small>GAM'dan alınan 1x1 pixel tracking linki</small>
                </p>

                <p>
                    <label for="kg_gam_click_url"><strong>GAM Click Tracker:</strong></label><br>
                    <input type="url" id="kg_gam_click_url" name="kg_gam_click_url" value="<?php echo esc_attr( $gam_click_url ); ?>" style="width:100%;" placeholder="https://ad.doubleclick.net/...adurl=">
                    <small>GAM'dan alınan, sonu <code>adurl=</code> ile biten base URL</small>
                </p>
            </div>
        </div>
        <?php
    }

    public function save_sponsor_meta_data( $post_id ) {
        // Autosave check
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        
        // Post type validation
        if ( get_post_type( $post_id ) !== 'post' ) return;
        
        // Nonce check
        if ( ! isset( $_POST['kg_sponsor_nonce'] ) || ! wp_verify_nonce( $_POST['kg_sponsor_nonce'], 'kg_sponsor_save' ) ) return;
        
        // Permission check
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Save is_sponsored checkbox
        $is_sponsored = isset( $_POST['kg_is_sponsored'] ) ? '1' : '0';
        update_post_meta( $post_id, '_kg_is_sponsored', $is_sponsored );

        // Save other fields only if sponsored
        if ( $is_sponsored === '1' ) {
            // Sponsor Name
            if ( isset( $_POST['kg_sponsor_name'] ) ) {
                update_post_meta( $post_id, '_kg_sponsor_name', sanitize_text_field( $_POST['kg_sponsor_name'] ) );
            }

            // Sponsor URL
            if ( isset( $_POST['kg_sponsor_url'] ) ) {
                update_post_meta( $post_id, '_kg_sponsor_url', esc_url_raw( $_POST['kg_sponsor_url'] ) );
            }

            // Sponsor Logo (Attachment ID)
            if ( isset( $_POST['kg_sponsor_logo'] ) ) {
                update_post_meta( $post_id, '_kg_sponsor_logo', absint( $_POST['kg_sponsor_logo'] ) );
            }

            // Sponsor Light Logo (Attachment ID)
            if ( isset( $_POST['kg_sponsor_light_logo'] ) ) {
                update_post_meta( $post_id, '_kg_sponsor_light_logo', absint( $_POST['kg_sponsor_light_logo'] ) );
            }

            // Direct Redirect
            if ( isset( $_POST['kg_direct_redirect'] ) ) {
                $direct_redirect = in_array( $_POST['kg_direct_redirect'], [ '0', '1' ] ) ? $_POST['kg_direct_redirect'] : '0';
                update_post_meta( $post_id, '_kg_direct_redirect', $direct_redirect );
            }

            // GAM Impression URL
            if ( isset( $_POST['kg_gam_impression_url'] ) ) {
                update_post_meta( $post_id, '_kg_gam_impression_url', esc_url_raw( $_POST['kg_gam_impression_url'] ) );
            }

            // GAM Click URL
            if ( isset( $_POST['kg_gam_click_url'] ) ) {
                update_post_meta( $post_id, '_kg_gam_click_url', esc_url_raw( $_POST['kg_gam_click_url'] ) );
            }
        } else {
            // If not sponsored, clear all sponsor fields
            delete_post_meta( $post_id, '_kg_sponsor_name' );
            delete_post_meta( $post_id, '_kg_sponsor_url' );
            delete_post_meta( $post_id, '_kg_sponsor_logo' );
            delete_post_meta( $post_id, '_kg_sponsor_light_logo' );
            delete_post_meta( $post_id, '_kg_direct_redirect' );
            delete_post_meta( $post_id, '_kg_gam_impression_url' );
            delete_post_meta( $post_id, '_kg_gam_click_url' );
        }
    }
}

<?php
namespace KG_Core\Admin;

class DiscussionMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post_discussion', [ $this, 'save_meta_data' ] );
    }

    public function add_meta_box() {
        add_meta_box(
            'kg_discussion_details',
            'Soru Ayarları',
            [ $this, 'render_meta_box' ],
            'discussion',
            'side',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        $is_featured = get_post_meta( $post->ID, '_kg_is_featured', true );
        $answer_count = get_post_meta( $post->ID, '_kg_answer_count', true );
        
        wp_nonce_field( 'kg_discussion_save', 'kg_discussion_nonce' );
        ?>
        <p>
            <label for="kg_is_featured">
                <input type="checkbox" id="kg_is_featured" name="kg_is_featured" value="1" <?php checked( $is_featured, '1' ); ?>>
                <strong>Öne Çıkan Soru mu?</strong>
            </label>
        </p>
        <p class="description">Bu soru anasayfada "Öne Çıkanlar" bölümünde görünecek.</p>
        
        <hr>
        
        <p>
            <label for="kg_answer_count"><strong>Cevap Sayısı (Override):</strong></label><br>
            <input type="number" id="kg_answer_count" name="kg_answer_count" value="<?php echo esc_attr( $answer_count ); ?>" min="0" style="width:100%;">
        </p>
        <p class="description">Boş bırakılırsa gerçek yorum sayısı kullanılır.</p>
        <?php
    }

    public function save_meta_data( $post_id ) {
        // Security checks
        if ( ! isset( $_POST['kg_discussion_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['kg_discussion_nonce'], 'kg_discussion_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Save featured status
        $is_featured = isset( $_POST['kg_is_featured'] ) ? '1' : '0';
        update_post_meta( $post_id, '_kg_is_featured', $is_featured );

        // Save answer count override
        if ( isset( $_POST['kg_answer_count'] ) && $_POST['kg_answer_count'] !== '' ) {
            update_post_meta( $post_id, '_kg_answer_count', absint( $_POST['kg_answer_count'] ) );
        } else {
            delete_post_meta( $post_id, '_kg_answer_count' );
        }
    }
}

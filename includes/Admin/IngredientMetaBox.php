<?php
namespace KG_Core\Admin;

class IngredientMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_custom_meta_data' ] );
    }

    public function add_custom_meta_boxes() {
        add_meta_box(
            'kg_ingredient_details',       // ID
            'Malzeme Detayları',           // Başlık
            [ $this, 'render_meta_box' ],  // Callback
            'ingredient',                  // Post Type
            'normal',                      // Konum
            'high'                         // Öncelik
        );
    }

    public function render_meta_box( $post ) {
        // Mevcut değerleri çek
        $start_age = get_post_meta( $post->ID, '_kg_start_age', true );
        $benefits = get_post_meta( $post->ID, '_kg_benefits', true );
        $allergy_risk = get_post_meta( $post->ID, '_kg_allergy_risk', true );
        $season = get_post_meta( $post->ID, '_kg_season', true );
        $storage_tips = get_post_meta( $post->ID, '_kg_storage_tips', true );
        
        // Güvenlik için nonce
        wp_nonce_field( 'kg_ingredient_save', 'kg_ingredient_nonce' );
        ?>
        <div class="kg-meta-box">
            <h3>Temel Bilgiler</h3>
            <p>
                <label for="kg_start_age"><strong>Başlangıç Ayı:</strong></label><br>
                <select id="kg_start_age" name="kg_start_age" style="width:100%;">
                    <option value="">Seçiniz</option>
                    <option value="4 ay" <?php selected( $start_age, '4 ay' ); ?>>4 ay</option>
                    <option value="6 ay" <?php selected( $start_age, '6 ay' ); ?>>6 ay</option>
                    <option value="8 ay" <?php selected( $start_age, '8 ay' ); ?>>8 ay</option>
                    <option value="10 ay" <?php selected( $start_age, '10 ay' ); ?>>10 ay</option>
                    <option value="12 ay" <?php selected( $start_age, '12 ay' ); ?>>12 ay (1 yaş)</option>
                    <option value="18 ay" <?php selected( $start_age, '18 ay' ); ?>>18 ay</option>
                    <option value="24 ay" <?php selected( $start_age, '24 ay' ); ?>>24 ay (2 yaş)</option>
                </select>
            </p>

            <p>
                <label for="kg_allergy_risk"><strong>Alerji Riski:</strong></label><br>
                <select id="kg_allergy_risk" name="kg_allergy_risk" style="width:100%;">
                    <option value="">Seçiniz</option>
                    <option value="Düşük" <?php selected( $allergy_risk, 'Düşük' ); ?>>Düşük</option>
                    <option value="Orta" <?php selected( $allergy_risk, 'Orta' ); ?>>Orta</option>
                    <option value="Yüksek" <?php selected( $allergy_risk, 'Yüksek' ); ?>>Yüksek</option>
                </select>
            </p>

            <h3>Faydalar ve Kullanım</h3>
            <p>
                <label for="kg_benefits"><strong>Faydaları:</strong></label><br>
                <textarea id="kg_benefits" name="kg_benefits" rows="4" style="width:100%;"><?php echo esc_textarea( $benefits ); ?></textarea>
                <small>Bebeğiniz için bu malzemenin sağlık faydalarını yazın</small>
            </p>

            <p>
                <label for="kg_prep_methods"><strong>Hazırlama Yöntemleri (Her satıra bir tane):</strong></label><br>
                <textarea id="kg_prep_methods" name="kg_prep_methods" rows="3" style="width:100%;"><?php 
                    $prep = get_post_meta( $post->ID, '_kg_prep_methods', true );
                    echo is_array($prep) ? implode("\n", $prep) : ''; 
                ?></textarea>
                <small>Örnek: Püre, Haşlama, Buhar, Fırında</small>
            </p>

            <h3>Mevsim ve Saklama</h3>
            <p>
                <label for="kg_season"><strong>Mevsim:</strong></label><br>
                <input type="text" id="kg_season" name="kg_season" value="<?php echo esc_attr( $season ); ?>" style="width:100%;">
                <small>Örnek: İlkbahar, Yaz, Sonbahar, Kış, Tüm Yıl</small>
            </p>

            <p>
                <label for="kg_storage_tips"><strong>Saklama Önerileri:</strong></label><br>
                <textarea id="kg_storage_tips" name="kg_storage_tips" rows="3" style="width:100%;"><?php echo esc_textarea( $storage_tips ); ?></textarea>
            </p>

            <h3>Sıkça Sorulan Sorular (FAQ)</h3>
            <p>
                <label for="kg_faq"><strong>SSS (JSON Format):</strong></label><br>
                <textarea id="kg_faq" name="kg_faq" rows="6" style="width:100%; font-family: monospace;"><?php 
                    $faq = get_post_meta( $post->ID, '_kg_faq', true );
                    if ( is_array($faq) ) {
                        echo esc_textarea( json_encode($faq, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) );
                    }
                ?></textarea>
                <small>Örnek: [{"question":"Ne zaman verilmeli?","answer":"6 aydan sonra"}]</small>
            </p>
        </div>
        <?php
    }

    public function save_custom_meta_data( $post_id ) {
        // Autosave kontrolü
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        
        // Post type validation
        if ( get_post_type( $post_id ) !== 'ingredient' ) return;
        
        // Nonce kontrolü
        if ( ! isset( $_POST['kg_ingredient_nonce'] ) || ! wp_verify_nonce( $_POST['kg_ingredient_nonce'], 'kg_ingredient_save' ) ) return;
        
        // Yetki kontrolü
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Save basic info
        if ( isset( $_POST['kg_start_age'] ) ) {
            update_post_meta( $post_id, '_kg_start_age', sanitize_text_field( $_POST['kg_start_age'] ) );
        }

        if ( isset( $_POST['kg_allergy_risk'] ) ) {
            update_post_meta( $post_id, '_kg_allergy_risk', sanitize_text_field( $_POST['kg_allergy_risk'] ) );
        }

        // Save benefits
        if ( isset( $_POST['kg_benefits'] ) ) {
            update_post_meta( $post_id, '_kg_benefits', sanitize_textarea_field( $_POST['kg_benefits'] ) );
        }

        // Save prep methods as array
        if ( isset( $_POST['kg_prep_methods'] ) ) {
            $prep_methods_array = array_filter(array_map('trim', explode("\n", $_POST['kg_prep_methods'])));
            update_post_meta( $post_id, '_kg_prep_methods', $prep_methods_array );
        }

        // Save season and storage
        if ( isset( $_POST['kg_season'] ) ) {
            update_post_meta( $post_id, '_kg_season', sanitize_text_field( $_POST['kg_season'] ) );
        }

        if ( isset( $_POST['kg_storage_tips'] ) ) {
            update_post_meta( $post_id, '_kg_storage_tips', sanitize_textarea_field( $_POST['kg_storage_tips'] ) );
        }

        // Save FAQ as JSON
        if ( isset( $_POST['kg_faq'] ) ) {
            $faq_json = stripslashes( $_POST['kg_faq'] );
            $faq = json_decode( $faq_json, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($faq) ) {
                update_post_meta( $post_id, '_kg_faq', $faq );
            } else {
                update_post_meta( $post_id, '_kg_faq', [] );
            }
        }
    }
}

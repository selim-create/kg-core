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
        
        // Convert old string format to numeric for backward compatibility
        if ( ! empty( $start_age ) && ! is_numeric( $start_age ) ) {
            // Extract number from old format like "6 ay"
            preg_match( '/(\d+)/', $start_age, $matches );
            if ( ! empty( $matches[1] ) ) {
                $start_age = $matches[1];
            }
        }
        
        $benefits = get_post_meta( $post->ID, '_kg_benefits', true );
        $allergy_risk = get_post_meta( $post->ID, '_kg_allergy_risk', true );
        $season = get_post_meta( $post->ID, '_kg_season', true );
        $storage_tips = get_post_meta( $post->ID, '_kg_storage_tips', true );
        $preparation_tips = get_post_meta( $post->ID, '_kg_preparation_tips', true );
        
        // Nutrition values
        $calories = get_post_meta( $post->ID, '_kg_calories', true );
        $protein = get_post_meta( $post->ID, '_kg_protein', true );
        $carbs = get_post_meta( $post->ID, '_kg_carbs', true );
        $fat = get_post_meta( $post->ID, '_kg_fat', true );
        $fiber = get_post_meta( $post->ID, '_kg_fiber', true );
        $vitamins = get_post_meta( $post->ID, '_kg_vitamins', true );
        
        // Get all allergen terms
        $allergen_terms = get_terms( [
            'taxonomy' => 'allergen',
            'hide_empty' => false,
        ] );
        
        // Get selected allergens for this ingredient
        $selected_allergens = wp_get_post_terms( $post->ID, 'allergen', [ 'fields' => 'ids' ] );
        
        // Güvenlik için nonce
        wp_nonce_field( 'kg_ingredient_save', 'kg_ingredient_nonce' );
        ?>
        <div class="kg-meta-box">
            <h3>Temel Bilgiler</h3>
            <p>
                <label for="kg_start_age"><strong>Başlangıç Yaşı (Ay):</strong></label><br>
                <select id="kg_start_age" name="kg_start_age" style="width:100%;">
                    <option value="">Seçiniz</option>
                    <option value="4" <?php selected( $start_age, '4' ); ?>>4 ay</option>
                    <option value="6" <?php selected( $start_age, '6' ); ?>>6 ay</option>
                    <option value="8" <?php selected( $start_age, '8' ); ?>>8 ay</option>
                    <option value="10" <?php selected( $start_age, '10' ); ?>>10 ay</option>
                    <option value="12" <?php selected( $start_age, '12' ); ?>>12 ay (1 yaş)</option>
                    <option value="18" <?php selected( $start_age, '18' ); ?>>18 ay</option>
                    <option value="24" <?php selected( $start_age, '24' ); ?>>24 ay (2 yaş)</option>
                    <option value="36" <?php selected( $start_age, '36' ); ?>>36 ay (3 yaş)</option>
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

            <h3>Alerjen Bilgileri</h3>
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                <p><strong>Bu malzemenin içerdiği alerjenler:</strong></p>
                <?php if ( ! empty( $allergen_terms ) && ! is_wp_error( $allergen_terms ) ) : ?>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <?php foreach ( $allergen_terms as $term ) : ?>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input 
                                    type="checkbox" 
                                    name="kg_allergens[]" 
                                    value="<?php echo esc_attr( $term->term_id ); ?>"
                                    <?php checked( in_array( $term->term_id, $selected_allergens ) ); ?>
                                >
                                <span><?php echo esc_html( $term->name ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p><em>Henüz alerjen tanımlanmamış.</em></p>
                <?php endif; ?>
            </div>

            <h3>Besin Değerleri (100g başına)</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <p>
                    <label for="kg_calories"><strong>Kalori (kcal):</strong></label><br>
                    <input type="text" id="kg_calories" name="kg_calories" value="<?php echo esc_attr( $calories ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_protein"><strong>Protein (g):</strong></label><br>
                    <input type="text" id="kg_protein" name="kg_protein" value="<?php echo esc_attr( $protein ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_carbs"><strong>Karbonhidrat (g):</strong></label><br>
                    <input type="text" id="kg_carbs" name="kg_carbs" value="<?php echo esc_attr( $carbs ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_fat"><strong>Yağ (g):</strong></label><br>
                    <input type="text" id="kg_fat" name="kg_fat" value="<?php echo esc_attr( $fat ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_fiber"><strong>Lif (g):</strong></label><br>
                    <input type="text" id="kg_fiber" name="kg_fiber" value="<?php echo esc_attr( $fiber ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_vitamins"><strong>Vitaminler:</strong></label><br>
                    <input type="text" id="kg_vitamins" name="kg_vitamins" value="<?php echo esc_attr( $vitamins ); ?>" style="width:100%;">
                    <small>Örnek: A, C, D, E, K</small>
                </p>
            </div>

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

            <p>
                <label for="kg_preparation_tips"><strong>Hazırlama İpuçları:</strong></label><br>
                <textarea id="kg_preparation_tips" name="kg_preparation_tips" rows="3" style="width:100%;"><?php echo esc_textarea( $preparation_tips ); ?></textarea>
                <small>Malzemeyi bebeklere hazırlarken dikkat edilmesi gerekenler</small>
            </p>

            <h3>Mevsim ve Saklama</h3>
            <p>
                <label for="kg_season"><strong>Mevsim:</strong></label><br>
                <input type="text" id="kg_season" name="kg_season" value="<?php echo esc_attr( $season ); ?>" style="width:100%;">
                <small>Örnek: İlkbahar, Yaz, Sonbahar, Kış, Tüm Yıl</small>
            </p>

            <p>
                <label for="kg_storage_tips"><strong>Saklama Koşulları:</strong></label><br>
                <textarea id="kg_storage_tips" name="kg_storage_tips" rows="3" style="width:100%;"><?php echo esc_textarea( $storage_tips ); ?></textarea>
                <small>Malzemenin nasıl saklanması gerektiği hakkında bilgiler</small>
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

        // Save allergen taxonomy terms
        if ( isset( $_POST['kg_allergens'] ) && is_array( $_POST['kg_allergens'] ) ) {
            $allergen_ids = array_map( 'intval', $_POST['kg_allergens'] );
            wp_set_post_terms( $post_id, $allergen_ids, 'allergen' );
        } else {
            // If no allergens selected, clear the taxonomy
            wp_set_post_terms( $post_id, [], 'allergen' );
        }

        // Save nutrition values
        if ( isset( $_POST['kg_calories'] ) ) {
            update_post_meta( $post_id, '_kg_calories', sanitize_text_field( $_POST['kg_calories'] ) );
        }
        if ( isset( $_POST['kg_protein'] ) ) {
            update_post_meta( $post_id, '_kg_protein', sanitize_text_field( $_POST['kg_protein'] ) );
        }
        if ( isset( $_POST['kg_carbs'] ) ) {
            update_post_meta( $post_id, '_kg_carbs', sanitize_text_field( $_POST['kg_carbs'] ) );
        }
        if ( isset( $_POST['kg_fat'] ) ) {
            update_post_meta( $post_id, '_kg_fat', sanitize_text_field( $_POST['kg_fat'] ) );
        }
        if ( isset( $_POST['kg_fiber'] ) ) {
            update_post_meta( $post_id, '_kg_fiber', sanitize_text_field( $_POST['kg_fiber'] ) );
        }
        if ( isset( $_POST['kg_vitamins'] ) ) {
            update_post_meta( $post_id, '_kg_vitamins', sanitize_text_field( $_POST['kg_vitamins'] ) );
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

        // Save preparation tips
        if ( isset( $_POST['kg_preparation_tips'] ) ) {
            update_post_meta( $post_id, '_kg_preparation_tips', sanitize_textarea_field( $_POST['kg_preparation_tips'] ) );
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
                // Log JSON parsing error
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KG Core: Invalid JSON in FAQ for ingredient ' . $post_id . ': ' . json_last_error_msg() );
                }
                update_post_meta( $post_id, '_kg_faq', [] );
            }
        }
    }
}

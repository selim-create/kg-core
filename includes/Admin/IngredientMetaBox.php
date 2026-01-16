<?php
namespace KG_Core\Admin;

class IngredientMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_custom_meta_data' ] );
        add_action( 'admin_notices', [ $this, 'show_validation_notices' ] );
    }
    
    /**
     * Show validation error notices
     */
    public function show_validation_notices() {
        global $post;
        
        if ( ! $post || get_post_type( $post ) !== 'ingredient' ) {
            return;
        }
        
        $errors = get_transient( 'kg_ingredient_validation_errors_' . $post->ID );
        
        if ( $errors && is_array( $errors ) ) {
            foreach ( $errors as $error ) {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Malzeme Hatası:</strong> ' . esc_html( $error ) . '</p></div>';
            }
            delete_transient( 'kg_ingredient_validation_errors_' . $post->ID );
        }
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
        $is_featured = get_post_meta( $post->ID, '_kg_is_featured', true );
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
        // Convert old string format to array for backward compatibility
        if ( ! empty( $season ) && ! is_array( $season ) ) {
            $season = array_filter( array_map( 'trim', explode( ',', $season ) ) );
        } elseif ( empty( $season ) ) {
            $season = [];
        }
        $storage_tips = get_post_meta( $post->ID, '_kg_storage_tips', true );
        $selection_tips = get_post_meta( $post->ID, '_kg_selection_tips', true );
        $pro_tips = get_post_meta( $post->ID, '_kg_pro_tips', true );
        $preparation_tips = get_post_meta( $post->ID, '_kg_preparation_tips', true );
        
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
                <label for="kg_is_featured">
                    <input type="checkbox" id="kg_is_featured" name="kg_is_featured" value="1" <?php checked( $is_featured, 1 ); ?>>
                    <strong>Öne Çıkan Malzeme mi?</strong>
                </label>
            </p>
            

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
                <p>
                    <label for="kg_cross_contamination"><strong>Çapraz Bulaşma Riski:</strong></label><br>
                    <input type="text" id="kg_cross_contamination" name="kg_cross_contamination" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_cross_contamination', true ) ); ?>" style="width:100%;">
                    <small>Örnek: Düşük, Orta, Yüksek</small>
                </p>
                <p>
                    <label for="kg_allergy_symptoms"><strong>Alerji Semptomları:</strong></label><br>
                    <textarea id="kg_allergy_symptoms" name="kg_allergy_symptoms" rows="3" style="width:100%;"><?php echo esc_textarea( get_post_meta( $post->ID, '_kg_allergy_symptoms', true ) ); ?></textarea>
                    <small>Bu malzemenin neden olabileceği alerji belirtileri</small>
                </p>
                <p>
                    <label for="kg_alternatives"><strong>Alternatif Malzemeler:</strong></label><br>
                    <textarea id="kg_alternatives" name="kg_alternatives" rows="3" style="width:100%;"><?php echo esc_textarea( get_post_meta( $post->ID, '_kg_alternatives', true ) ); ?></textarea>
                    <small>Alerji durumunda kullanılabilecek alternatifler</small>
                </p>
                
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
                    <label for="kg_ing_calories_100g"><strong>Kalori (kcal):</strong></label><br>
                    <input type="text" id="kg_ing_calories_100g" name="kg_ing_calories_100g" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_ing_calories_100g', true ) ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_ing_protein_100g"><strong>Protein (g):</strong></label><br>
                    <input type="text" id="kg_ing_protein_100g" name="kg_ing_protein_100g" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_ing_protein_100g', true ) ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_ing_carbs_100g"><strong>Karbonhidrat (g):</strong></label><br>
                    <input type="text" id="kg_ing_carbs_100g" name="kg_ing_carbs_100g" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_ing_carbs_100g', true ) ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_ing_fat_100g"><strong>Yağ (g):</strong></label><br>
                    <input type="text" id="kg_ing_fat_100g" name="kg_ing_fat_100g" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_ing_fat_100g', true ) ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_ing_fiber_100g"><strong>Lif (g):</strong></label><br>
                    <input type="text" id="kg_ing_fiber_100g" name="kg_ing_fiber_100g" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_ing_fiber_100g', true ) ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_ing_sugar_100g"><strong>Şeker (g):</strong></label><br>
                    <input type="text" id="kg_ing_sugar_100g" name="kg_ing_sugar_100g" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_ing_sugar_100g', true ) ); ?>" style="width:100%;">
                </p>
                <p>
                    <label for="kg_ing_vitamins"><strong>Vitaminler:</strong></label><br>
                    <input type="text" id="kg_ing_vitamins" name="kg_ing_vitamins" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_ing_vitamins', true ) ); ?>" style="width:100%;">
                    <small>Örnek: A, C, K</small>
                </p>
                <p>
                    <label for="kg_ing_minerals"><strong>Mineraller:</strong></label><br>
                    <input type="text" id="kg_ing_minerals" name="kg_ing_minerals" value="<?php echo esc_attr( get_post_meta( $post->ID, '_kg_ing_minerals', true ) ); ?>" style="width:100%;">
                    <small>Örnek: Potasyum, Kalsiyum</small>
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
            
            <p>
                <label for="kg_selection_tips"><strong>Seçim İpuçları:</strong></label><br>
                <textarea id="kg_selection_tips" name="kg_selection_tips" rows="3" style="width:100%;"><?php echo esc_textarea( $selection_tips ); ?></textarea>
                <small>Taze ve kaliteli malzeme nasıl seçilir</small>
            </p>
            
            <p>
                <label for="kg_pro_tips"><strong>Püf Noktaları:</strong></label><br>
                <textarea id="kg_pro_tips" name="kg_pro_tips" rows="3" style="width:100%;"><?php echo esc_textarea( $pro_tips ); ?></textarea>
                <small>Bebekler için özel ipuçları ve püf noktaları</small>
            </p>
            
            <h3>Yaşa Göre Hazırlama</h3>
            <p>
                <label for="kg_prep_by_age"><strong>Yaş Gruplarına Göre Hazırlama (JSON Format):</strong></label><br>
                <textarea id="kg_prep_by_age" name="kg_prep_by_age" rows="6" style="width:100%; font-family: monospace;"><?php 
                    $prep_by_age = get_post_meta( $post->ID, '_kg_prep_by_age', true );
                    if ( is_array($prep_by_age) ) {
                        echo esc_textarea( json_encode($prep_by_age, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) );
                    }
                ?></textarea>
                <small>Örnek: [{"age":"6-9 Ay","method":"Püre","text":"Haşlayıp püre yapın"}]</small>
            </p>
            
            <h3>Uyumlu İkililer</h3>
            <p>
                <label for="kg_pairings"><strong>Bu Malzeme İle Uyumlu İkililer (JSON Format):</strong></label><br>
                <textarea id="kg_pairings" name="kg_pairings" rows="4" style="width:100%; font-family: monospace;"><?php 
                    $pairings = get_post_meta( $post->ID, '_kg_pairings', true );
                    if ( is_array($pairings) ) {
                        echo esc_textarea( json_encode($pairings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) );
                    }
                ?></textarea>
                <small>Örnek: [{"emoji":"🍌","name":"Muz"},{"emoji":"🥚","name":"Yumurta"}]</small>
            </p>

            <h3>Mevsim ve Saklama</h3>
            <p><strong>Mevsim (Birden fazla seçilebilir):</strong></p>
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                <?php 
                $season_options = ['Tüm Yıl', 'İlkbahar', 'Yaz', 'Sonbahar', 'Kış'];
                foreach ($season_options as $season_option): 
                ?>
                    <label style="display: block; margin-bottom: 8px;">
                        <input 
                            type="checkbox" 
                            name="kg_season[]" 
                            value="<?php echo esc_attr($season_option); ?>"
                            <?php checked(in_array($season_option, $season)); ?>
                        >
                        <span><?php echo esc_html($season_option); ?></span>
                    </label>
                <?php endforeach; ?>
                <small style="display: block; margin-top: 8px;">
                    Türkiye'de bu malzemenin doğal olarak taze bulunduğu mevsimler. 
                    Serada yetiştirilen değil, doğal üretim mevsimi dikkate alınmalıdır.
                </small>
            </div>

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
        
        // Kategori zorunluluğu kontrolü (sadece publish durumunda)
        $post_status = get_post_status( $post_id );
        if ( $post_status === 'publish' ) {
            $category_terms = wp_get_post_terms( $post_id, 'ingredient-category' );
            if ( empty( $category_terms ) || is_wp_error( $category_terms ) ) {
                $this->add_validation_error( $post_id, 'Malzeme kategorisi seçilmeden yayınlama yapılamaz!' );
                wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
            }
        }

        // Save is_featured checkbox
        $is_featured = isset( $_POST['kg_is_featured'] ) ? '1' : '0';
        update_post_meta( $post_id, '_kg_is_featured', $is_featured );

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
        
        // Save nutrition per 100g fields
        if ( isset( $_POST['kg_ing_calories_100g'] ) ) {
            update_post_meta( $post_id, '_kg_ing_calories_100g', sanitize_text_field( $_POST['kg_ing_calories_100g'] ) );
        }
        if ( isset( $_POST['kg_ing_protein_100g'] ) ) {
            update_post_meta( $post_id, '_kg_ing_protein_100g', sanitize_text_field( $_POST['kg_ing_protein_100g'] ) );
        }
        if ( isset( $_POST['kg_ing_carbs_100g'] ) ) {
            update_post_meta( $post_id, '_kg_ing_carbs_100g', sanitize_text_field( $_POST['kg_ing_carbs_100g'] ) );
        }
        if ( isset( $_POST['kg_ing_fat_100g'] ) ) {
            update_post_meta( $post_id, '_kg_ing_fat_100g', sanitize_text_field( $_POST['kg_ing_fat_100g'] ) );
        }
        if ( isset( $_POST['kg_ing_fiber_100g'] ) ) {
            update_post_meta( $post_id, '_kg_ing_fiber_100g', sanitize_text_field( $_POST['kg_ing_fiber_100g'] ) );
        }
        if ( isset( $_POST['kg_ing_sugar_100g'] ) ) {
            update_post_meta( $post_id, '_kg_ing_sugar_100g', sanitize_text_field( $_POST['kg_ing_sugar_100g'] ) );
        }
        if ( isset( $_POST['kg_ing_vitamins'] ) ) {
            update_post_meta( $post_id, '_kg_ing_vitamins', sanitize_text_field( $_POST['kg_ing_vitamins'] ) );
        }
        if ( isset( $_POST['kg_ing_minerals'] ) ) {
            update_post_meta( $post_id, '_kg_ing_minerals', sanitize_text_field( $_POST['kg_ing_minerals'] ) );
        }
        
        // Save allergen info fields
        if ( isset( $_POST['kg_cross_contamination'] ) ) {
            update_post_meta( $post_id, '_kg_cross_contamination', sanitize_text_field( $_POST['kg_cross_contamination'] ) );
        }
        if ( isset( $_POST['kg_allergy_symptoms'] ) ) {
            update_post_meta( $post_id, '_kg_allergy_symptoms', sanitize_textarea_field( $_POST['kg_allergy_symptoms'] ) );
        }
        if ( isset( $_POST['kg_alternatives'] ) ) {
            update_post_meta( $post_id, '_kg_alternatives', sanitize_textarea_field( $_POST['kg_alternatives'] ) );
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
        
        if ( isset( $_POST['kg_selection_tips'] ) ) {
            update_post_meta( $post_id, '_kg_selection_tips', sanitize_textarea_field( $_POST['kg_selection_tips'] ) );
        }
        
        if ( isset( $_POST['kg_pro_tips'] ) ) {
            update_post_meta( $post_id, '_kg_pro_tips', sanitize_textarea_field( $_POST['kg_pro_tips'] ) );
        }

        // Save season and storage
        if ( isset( $_POST['kg_season'] ) && is_array( $_POST['kg_season'] ) ) {
            $season_array = array_map( 'sanitize_text_field', $_POST['kg_season'] );
            update_post_meta( $post_id, '_kg_season', $season_array );
        } else {
            // If no season selected, clear the field
            update_post_meta( $post_id, '_kg_season', [] );
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
                // Log and notify user of JSON error
                $error_msg = 'FAQ JSON formatı geçersiz: ' . json_last_error_msg();
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KG Core: ' . $error_msg . ' for ingredient ' . $post_id );
                }
                $this->add_validation_error( $post_id, $error_msg );
                update_post_meta( $post_id, '_kg_faq', [] );
            }
        }
        
        // Save prep_by_age as JSON
        if ( isset( $_POST['kg_prep_by_age'] ) ) {
            $prep_by_age_json = stripslashes( $_POST['kg_prep_by_age'] );
            $prep_by_age = json_decode( $prep_by_age_json, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($prep_by_age) ) {
                update_post_meta( $post_id, '_kg_prep_by_age', $prep_by_age );
            } else {
                $error_msg = 'Yaşa Göre Hazırlama JSON formatı geçersiz: ' . json_last_error_msg();
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KG Core: ' . $error_msg . ' for ingredient ' . $post_id );
                }
                $this->add_validation_error( $post_id, $error_msg );
                update_post_meta( $post_id, '_kg_prep_by_age', [] );
            }
        }
        
        // Save pairings as JSON
        if ( isset( $_POST['kg_pairings'] ) ) {
            $pairings_json = stripslashes( $_POST['kg_pairings'] );
            $pairings = json_decode( $pairings_json, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($pairings) ) {
                update_post_meta( $post_id, '_kg_pairings', $pairings );
            } else {
                $error_msg = 'Uyumlu İkililer JSON formatı geçersiz: ' . json_last_error_msg();
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KG Core: ' . $error_msg . ' for ingredient ' . $post_id );
                }
                $this->add_validation_error( $post_id, $error_msg );
                update_post_meta( $post_id, '_kg_pairings', [] );
            }
        }
    }
    
    /**
     * Add validation error to be displayed
     * 
     * @param int $post_id Post ID
     * @param string $message Error message
     */
    private function add_validation_error( $post_id, $message ) {
        $errors = get_transient( 'kg_ingredient_validation_errors_' . $post_id );
        
        if ( ! is_array( $errors ) ) {
            $errors = [];
        }
        
        $errors[] = $message;
        set_transient( 'kg_ingredient_validation_errors_' . $post_id, $errors, 60 );
    }
}

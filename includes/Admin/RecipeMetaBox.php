<?php
namespace KG_Core\Admin;

class RecipeMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_custom_meta_data' ] );
    }

    public function add_custom_meta_boxes() {
        add_meta_box(
            'kg_recipe_details',       // ID
            'Tarif Detayları',         // Başlık
            [ $this, 'render_meta_box' ], // Callback
            'recipe',                  // Post Type
            'normal',                  // Konum
            'high'                     // Öncelik
        );
    }

    public function render_meta_box( $post ) {
        // Mevcut değerleri çek
        $prep_time = get_post_meta( $post->ID, '_kg_prep_time', true );
        $is_featured = get_post_meta( $post->ID, '_kg_is_featured', true );
        $calories = get_post_meta( $post->ID, '_kg_calories', true );
        $protein = get_post_meta( $post->ID, '_kg_protein', true );
        $fiber = get_post_meta( $post->ID, '_kg_fiber', true );
        $vitamins = get_post_meta( $post->ID, '_kg_vitamins', true );
        $video_url = get_post_meta( $post->ID, '_kg_video_url', true );
        $expert_name = get_post_meta( $post->ID, '_kg_expert_name', true );
        $expert_title = get_post_meta( $post->ID, '_kg_expert_title', true );
        $expert_approved = get_post_meta( $post->ID, '_kg_expert_approved', true );
        $cross_sell_url = get_post_meta( $post->ID, '_kg_cross_sell_url', true );
        $cross_sell_title = get_post_meta( $post->ID, '_kg_cross_sell_title', true );
        
        // Güvenlik için nonce
        wp_nonce_field( 'kg_recipe_save', 'kg_recipe_nonce' );
        ?>
        <div class="kg-meta-box">
            <h3>Temel Bilgiler</h3>
            <p>
                <label for="kg_prep_time"><strong>Hazırlama Süresi (dk):</strong></label><br>
                <input type="text" id="kg_prep_time" name="kg_prep_time" value="<?php echo esc_attr( $prep_time ); ?>" style="width:100%;">
            </p>
            <p>
                <label for="kg_is_featured"><strong>Öne Çıkan Tarif:</strong></label>
                <input type="checkbox" id="kg_is_featured" name="kg_is_featured" value="1" <?php checked( $is_featured, 1 ); ?>>
            </p>
            
            <h3>Malzemeler ve Adımlar</h3>
            <p>
                <label for="kg_ingredients"><strong>Malzemeler (Her satıra bir tane):</strong></label><br>
                <textarea id="kg_ingredients" name="kg_ingredients" rows="5" style="width:100%;"><?php 
                    $ing = get_post_meta( $post->ID, '_kg_ingredients', true );
                    echo is_array($ing) ? implode("\n", $ing) : ''; 
                ?></textarea>
                <small>Örnek: 1 adet Yumurta</small>
            </p>
            
            <p>
                <label for="kg_instructions"><strong>Hazırlanış Adımları (JSON Format):</strong></label><br>
                <textarea id="kg_instructions" name="kg_instructions" rows="8" style="width:100%; font-family: monospace;"><?php 
                    $inst = get_post_meta( $post->ID, '_kg_instructions', true );
                    if ( is_array($inst) ) {
                        echo esc_textarea( json_encode($inst, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) );
                    }
                ?></textarea>
                <small>Örnek: [{"title":"Adım 1","text":"Havucu yıkayın","tip":"İnce dilimleyin"}]</small>
            </p>

            <p>
                <label for="kg_substitutes"><strong>İkame Malzemeler (JSON Format):</strong></label><br>
                <textarea id="kg_substitutes" name="kg_substitutes" rows="4" style="width:100%; font-family: monospace;"><?php 
                    $subs = get_post_meta( $post->ID, '_kg_substitutes', true );
                    if ( is_array($subs) ) {
                        echo esc_textarea( json_encode($subs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) );
                    }
                ?></textarea>
                <small>Örnek: [{"original":"Süt","substitute":"Badem sütü"}]</small>
            </p>

            <h3>Beslenme Değerleri</h3>
            <p>
                <label for="kg_calories"><strong>Kalori:</strong></label><br>
                <input type="text" id="kg_calories" name="kg_calories" value="<?php echo esc_attr( $calories ); ?>" style="width:100%;">
            </p>
            <p>
                <label for="kg_protein"><strong>Protein (g):</strong></label><br>
                <input type="text" id="kg_protein" name="kg_protein" value="<?php echo esc_attr( $protein ); ?>" style="width:100%;">
            </p>
            <p>
                <label for="kg_fiber"><strong>Lif (g):</strong></label><br>
                <input type="text" id="kg_fiber" name="kg_fiber" value="<?php echo esc_attr( $fiber ); ?>" style="width:100%;">
            </p>
            <p>
                <label for="kg_vitamins"><strong>Vitaminler:</strong></label><br>
                <input type="text" id="kg_vitamins" name="kg_vitamins" value="<?php echo esc_attr( $vitamins ); ?>" style="width:100%;">
                <small>Örnek: A, C, D, E</small>
            </p>

            <h3>Uzman Onayı</h3>
            <p>
                <label for="kg_expert_name"><strong>Uzman Adı:</strong></label><br>
                <input type="text" id="kg_expert_name" name="kg_expert_name" value="<?php echo esc_attr( $expert_name ); ?>" style="width:100%;">
            </p>
            <p>
                <label for="kg_expert_title"><strong>Uzman Ünvanı:</strong></label><br>
                <input type="text" id="kg_expert_title" name="kg_expert_title" value="<?php echo esc_attr( $expert_title ); ?>" style="width:100%;">
                <small>Örnek: Diyetisyen, Pediatrist</small>
            </p>
            <p>
                <label for="kg_expert_approved"><strong>Uzman Onaylı:</strong></label>
                <input type="checkbox" id="kg_expert_approved" name="kg_expert_approved" value="1" <?php checked( $expert_approved, 1 ); ?>>
            </p>

            <h3>Medya</h3>
            <p>
                <label for="kg_video_url"><strong>Video URL:</strong></label><br>
                <input type="url" id="kg_video_url" name="kg_video_url" value="<?php echo esc_attr( $video_url ); ?>" style="width:100%;">
                <small>YouTube, Vimeo vb. video linki</small>
            </p>

            <h3>Cross-Sell (Tariften.com)</h3>
            <p>
                <label for="kg_cross_sell_url"><strong>Tariften.com Linki:</strong></label><br>
                <input type="url" id="kg_cross_sell_url" name="kg_cross_sell_url" value="<?php echo esc_attr( $cross_sell_url ); ?>" style="width:100%;">
            </p>
            <p>
                <label for="kg_cross_sell_title"><strong>Cross-Sell Başlığı:</strong></label><br>
                <input type="text" id="kg_cross_sell_title" name="kg_cross_sell_title" value="<?php echo esc_attr( $cross_sell_title ); ?>" style="width:100%;">
                <small>Boş bırakılırsa varsayılan mesaj gösterilir</small>
            </p>
        </div>
        <?php
    }

    public function save_custom_meta_data( $post_id ) {
        // Autosave kontrolü
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        
        // Post type validation
        if ( get_post_type( $post_id ) !== 'recipe' ) return;
        
        // Nonce kontrolü
        if ( ! isset( $_POST['kg_recipe_nonce'] ) || ! wp_verify_nonce( $_POST['kg_recipe_nonce'], 'kg_recipe_save' ) ) return;
        
        // Yetki kontrolü
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Verileri kaydet - Temel bilgiler
        if ( isset( $_POST['kg_prep_time'] ) ) {
            update_post_meta( $post_id, '_kg_prep_time', sanitize_text_field( $_POST['kg_prep_time'] ) );
        }

        $is_featured = isset( $_POST['kg_is_featured'] ) ? '1' : '0';
        update_post_meta( $post_id, '_kg_is_featured', $is_featured );

        // Malzemeleri array olarak kaydet (satır satır bölerek)
        if ( isset( $_POST['kg_ingredients'] ) ) {
            $ingredients_array = array_filter(array_map('trim', explode("\n", $_POST['kg_ingredients'])));
            update_post_meta( $post_id, '_kg_ingredients', $ingredients_array );
        }

        // Instructions - JSON decode
        if ( isset( $_POST['kg_instructions'] ) ) {
            $instructions_json = stripslashes( $_POST['kg_instructions'] );
            $instructions = json_decode( $instructions_json, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($instructions) ) {
                update_post_meta( $post_id, '_kg_instructions', $instructions );
            } else {
                update_post_meta( $post_id, '_kg_instructions', [] );
            }
        }

        // Substitutes - JSON decode
        if ( isset( $_POST['kg_substitutes'] ) ) {
            $substitutes_json = stripslashes( $_POST['kg_substitutes'] );
            $substitutes = json_decode( $substitutes_json, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array($substitutes) ) {
                update_post_meta( $post_id, '_kg_substitutes', $substitutes );
            } else {
                update_post_meta( $post_id, '_kg_substitutes', [] );
            }
        }

        // Nutrition values
        if ( isset( $_POST['kg_calories'] ) ) {
            update_post_meta( $post_id, '_kg_calories', sanitize_text_field( $_POST['kg_calories'] ) );
        }
        if ( isset( $_POST['kg_protein'] ) ) {
            update_post_meta( $post_id, '_kg_protein', sanitize_text_field( $_POST['kg_protein'] ) );
        }
        if ( isset( $_POST['kg_fiber'] ) ) {
            update_post_meta( $post_id, '_kg_fiber', sanitize_text_field( $_POST['kg_fiber'] ) );
        }
        if ( isset( $_POST['kg_vitamins'] ) ) {
            update_post_meta( $post_id, '_kg_vitamins', sanitize_text_field( $_POST['kg_vitamins'] ) );
        }

        // Expert information
        if ( isset( $_POST['kg_expert_name'] ) ) {
            update_post_meta( $post_id, '_kg_expert_name', sanitize_text_field( $_POST['kg_expert_name'] ) );
        }
        if ( isset( $_POST['kg_expert_title'] ) ) {
            update_post_meta( $post_id, '_kg_expert_title', sanitize_text_field( $_POST['kg_expert_title'] ) );
        }
        $expert_approved = isset( $_POST['kg_expert_approved'] ) ? '1' : '0';
        update_post_meta( $post_id, '_kg_expert_approved', $expert_approved );

        // Media
        if ( isset( $_POST['kg_video_url'] ) ) {
            update_post_meta( $post_id, '_kg_video_url', esc_url_raw( $_POST['kg_video_url'] ) );
        }

        // Cross-sell
        if ( isset( $_POST['kg_cross_sell_url'] ) ) {
            update_post_meta( $post_id, '_kg_cross_sell_url', esc_url_raw( $_POST['kg_cross_sell_url'] ) );
        }
        if ( isset( $_POST['kg_cross_sell_title'] ) ) {
            update_post_meta( $post_id, '_kg_cross_sell_title', sanitize_text_field( $_POST['kg_cross_sell_title'] ) );
        }
    }
}
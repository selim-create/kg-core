<?php
namespace KG_Core\Admin;

class RecipeMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_custom_meta_data' ] );
        add_action( 'wp_ajax_kg_search_ingredients', [ $this, 'ajax_search_ingredients' ] );
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
        $ingredients = get_post_meta( $post->ID, '_kg_ingredients', true );
        $instructions = get_post_meta( $post->ID, '_kg_instructions', true );
        $substitutes = get_post_meta( $post->ID, '_kg_substitutes', true );
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
        
        // Ensure arrays are properly initialized
        if ( ! is_array( $ingredients ) ) $ingredients = [];
        if ( ! is_array( $instructions ) ) $instructions = [];
        if ( ! is_array( $substitutes ) ) $substitutes = [];
        
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
            
            <h3>Malzemeler</h3>
            <div id="kg-ingredients-repeater" class="kg-repeater">
                <div class="kg-repeater-items">
                    <?php
                    if ( ! empty( $ingredients ) ) {
                        foreach ( $ingredients as $index => $ingredient ) {
                            $this->render_ingredient_item( $index, $ingredient );
                        }
                    }
                    ?>
                </div>
                <button type="button" class="kg-add-item">Malzeme Ekle</button>
            </div>

            <h3>Hazırlanış Adımları</h3>
            <div id="kg-instructions-repeater" class="kg-repeater">
                <div class="kg-repeater-items">
                    <?php
                    if ( ! empty( $instructions ) ) {
                        foreach ( $instructions as $index => $instruction ) {
                            $this->render_instruction_item( $index, $instruction );
                        }
                    }
                    ?>
                </div>
                <button type="button" class="kg-add-item">Adım Ekle</button>
            </div>

            <h3>İkame Malzemeler</h3>
            <div id="kg-substitutes-repeater" class="kg-repeater">
                <div class="kg-repeater-items">
                    <?php
                    if ( ! empty( $substitutes ) ) {
                        foreach ( $substitutes as $index => $substitute ) {
                            $this->render_substitute_item( $index, $substitute );
                        }
                    }
                    ?>
                </div>
                <button type="button" class="kg-add-item">İkame Ekle</button>
            </div>

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

    /**
     * Render single ingredient item
     */
    private function render_ingredient_item( $index, $ingredient ) {
        $amount = isset( $ingredient['amount'] ) ? $ingredient['amount'] : '';
        $unit = isset( $ingredient['unit'] ) ? $ingredient['unit'] : 'adet';
        $name = isset( $ingredient['name'] ) ? $ingredient['name'] : '';
        $ingredient_id = isset( $ingredient['ingredient_id'] ) ? $ingredient['ingredient_id'] : '';
        ?>
        <div class="kg-repeater-item">
            <div class="kg-drag-handle"></div>
            <button type="button" class="kg-remove-item" title="Kaldır">×</button>
            <div class="kg-item-content">
                <div class="kg-ingredient-row">
                    <div>
                        <label>Miktar</label>
                        <input type="text" name="kg_ingredients[<?php echo $index; ?>][amount]" class="kg-ingredient-amount" value="<?php echo esc_attr( $amount ); ?>" placeholder="2">
                    </div>
                    <div>
                        <label>Birim</label>
                        <select name="kg_ingredients[<?php echo $index; ?>][unit]" class="kg-ingredient-unit">
                            <option value="adet" <?php selected( $unit, 'adet' ); ?>>Adet</option>
                            <option value="su bardağı" <?php selected( $unit, 'su bardağı' ); ?>>Su Bardağı</option>
                            <option value="yemek kaşığı" <?php selected( $unit, 'yemek kaşığı' ); ?>>Yemek Kaşığı</option>
                            <option value="çay kaşığı" <?php selected( $unit, 'çay kaşığı' ); ?>>Çay Kaşığı</option>
                            <option value="gram" <?php selected( $unit, 'gram' ); ?>>Gram</option>
                            <option value="ml" <?php selected( $unit, 'ml' ); ?>>ML</option>
                            <option value="kg" <?php selected( $unit, 'kg' ); ?>>KG</option>
                            <option value="litre" <?php selected( $unit, 'litre' ); ?>>Litre</option>
                            <option value="tutam" <?php selected( $unit, 'tutam' ); ?>>Tutam</option>
                        </select>
                    </div>
                    <div class="kg-autocomplete-wrap">
                        <label>Malzeme Adı</label>
                        <input type="text" name="kg_ingredients[<?php echo $index; ?>][name]" class="kg-ingredient-name" value="<?php echo esc_attr( $name ); ?>" placeholder="Un" autocomplete="off">
                        <input type="hidden" name="kg_ingredients[<?php echo $index; ?>][ingredient_id]" class="kg-ingredient-id" value="<?php echo esc_attr( $ingredient_id ); ?>">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render single instruction item
     */
    private function render_instruction_item( $index, $instruction ) {
        $id = isset( $instruction['id'] ) ? $instruction['id'] : ( $index + 1 );
        $title = isset( $instruction['title'] ) ? $instruction['title'] : '';
        $text = isset( $instruction['text'] ) ? $instruction['text'] : '';
        $tip = isset( $instruction['tip'] ) ? $instruction['tip'] : '';
        ?>
        <div class="kg-repeater-item">
            <div class="kg-drag-handle"></div>
            <button type="button" class="kg-remove-item" title="Kaldır">×</button>
            <div class="kg-item-content">
                <div class="kg-instruction-header">Adım <?php echo $id; ?></div>
                <div class="kg-instruction-fields">
                    <input type="hidden" name="kg_instructions[<?php echo $index; ?>][id]" class="kg-instruction-id" value="<?php echo esc_attr( $id ); ?>">
                    <div>
                        <label>Adım Başlığı</label>
                        <input type="text" name="kg_instructions[<?php echo $index; ?>][title]" class="kg-instruction-title" value="<?php echo esc_attr( $title ); ?>" placeholder="Malzemeleri hazırlayın">
                    </div>
                    <div>
                        <label>Açıklama</label>
                        <textarea name="kg_instructions[<?php echo $index; ?>][text]" class="kg-instruction-text" placeholder="Havuçları yıkayıp soyun, küçük küpler halinde doğrayın..." rows="3"><?php echo esc_textarea( $text ); ?></textarea>
                    </div>
                    <div>
                        <label>Püf Noktası <small>(opsiyonel)</small></label>
                        <input type="text" name="kg_instructions[<?php echo $index; ?>][tip]" class="kg-instruction-tip" value="<?php echo esc_attr( $tip ); ?>" placeholder="İnce rendeleyin">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render single substitute item
     */
    private function render_substitute_item( $index, $substitute ) {
        $original = isset( $substitute['original'] ) ? $substitute['original'] : '';
        $sub = isset( $substitute['substitute'] ) ? $substitute['substitute'] : '';
        $note = isset( $substitute['note'] ) ? $substitute['note'] : '';
        ?>
        <div class="kg-repeater-item">
            <div class="kg-drag-handle"></div>
            <button type="button" class="kg-remove-item" title="Kaldır">×</button>
            <div class="kg-item-content">
                <div class="kg-substitute-row">
                    <div>
                        <label>Orijinal Malzeme</label>
                        <input type="text" name="kg_substitutes[<?php echo $index; ?>][original]" class="kg-substitute-original" value="<?php echo esc_attr( $original ); ?>" placeholder="Süt">
                    </div>
                    <div>
                        <label>İkame Malzeme</label>
                        <input type="text" name="kg_substitutes[<?php echo $index; ?>][substitute]" class="kg-substitute-substitute" value="<?php echo esc_attr( $sub ); ?>" placeholder="Badem sütü">
                    </div>
                    <div>
                        <label>Not <small>(opsiyonel)</small></label>
                        <input type="text" name="kg_substitutes[<?php echo $index; ?>][note]" class="kg-substitute-note" value="<?php echo esc_attr( $note ); ?>" placeholder="Laktoz intoleransı için">
                    </div>
                </div>
            </div>
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

        // Malzemeleri array olarak kaydet
        if ( isset( $_POST['kg_ingredients'] ) && is_array( $_POST['kg_ingredients'] ) ) {
            $ingredients = [];
            foreach ( $_POST['kg_ingredients'] as $ingredient ) {
                if ( ! empty( $ingredient['name'] ) ) {
                    $ingredients[] = [
                        'amount' => sanitize_text_field( $ingredient['amount'] ),
                        'unit' => sanitize_text_field( $ingredient['unit'] ),
                        'name' => sanitize_text_field( $ingredient['name'] ),
                        'ingredient_id' => ! empty( $ingredient['ingredient_id'] ) ? intval( $ingredient['ingredient_id'] ) : null,
                    ];
                }
            }
            update_post_meta( $post_id, '_kg_ingredients', $ingredients );
        } else {
            update_post_meta( $post_id, '_kg_ingredients', [] );
        }

        // Instructions - Array olarak kaydet
        if ( isset( $_POST['kg_instructions'] ) && is_array( $_POST['kg_instructions'] ) ) {
            $instructions = [];
            foreach ( $_POST['kg_instructions'] as $instruction ) {
                if ( ! empty( $instruction['title'] ) || ! empty( $instruction['text'] ) ) {
                    $instructions[] = [
                        'id' => intval( $instruction['id'] ),
                        'title' => sanitize_text_field( $instruction['title'] ),
                        'text' => sanitize_textarea_field( $instruction['text'] ),
                        'tip' => sanitize_text_field( $instruction['tip'] ),
                    ];
                }
            }
            update_post_meta( $post_id, '_kg_instructions', $instructions );
        } else {
            update_post_meta( $post_id, '_kg_instructions', [] );
        }

        // Substitutes - Array olarak kaydet
        if ( isset( $_POST['kg_substitutes'] ) && is_array( $_POST['kg_substitutes'] ) ) {
            $substitutes = [];
            foreach ( $_POST['kg_substitutes'] as $substitute ) {
                if ( ! empty( $substitute['original'] ) || ! empty( $substitute['substitute'] ) ) {
                    $substitutes[] = [
                        'original' => sanitize_text_field( $substitute['original'] ),
                        'substitute' => sanitize_text_field( $substitute['substitute'] ),
                        'note' => sanitize_text_field( $substitute['note'] ),
                    ];
                }
            }
            update_post_meta( $post_id, '_kg_substitutes', $substitutes );
        } else {
            update_post_meta( $post_id, '_kg_substitutes', [] );
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

    /**
     * AJAX handler for ingredient search
     */
    public function ajax_search_ingredients() {
        // Check nonce
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'kg_metabox_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        $query = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';

        if ( empty( $query ) ) {
            wp_send_json_success( [] );
        }

        $args = [
            'post_type'      => 'ingredient',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            's'              => $query,
        ];

        $query_obj = new \WP_Query( $args );
        $results = [];

        if ( $query_obj->have_posts() ) {
            while ( $query_obj->have_posts() ) {
                $query_obj->the_post();
                $results[] = [
                    'id'   => get_the_ID(),
                    'name' => get_the_title(),
                ];
            }
        }
        wp_reset_postdata();

        wp_send_json_success( $results );
    }
}
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
        
        // Güvenlik için nonce
        wp_nonce_field( 'kg_recipe_save', 'kg_recipe_nonce' );
        ?>
        <div class="kg-meta-box">
            <p>
                <label for="kg_prep_time"><strong>Hazırlama Süresi (dk):</strong></label><br>
                <input type="text" id="kg_prep_time" name="kg_prep_time" value="<?php echo esc_attr( $prep_time ); ?>" style="width:100%;">
            </p>
            <p>
                <label for="kg_is_featured"><strong>Öne Çıkan Tarif:</strong></label>
                <input type="checkbox" id="kg_is_featured" name="kg_is_featured" value="1" <?php checked( $is_featured, 1 ); ?>>
            </p>
            
            <!-- Burada Ingredients ve Instructions için basit bir JSON editörü veya 
                 daha gelişmiş bir JS repeater yapısı kurulabilir. 
                 Basitlik adına Textarea örneği: -->
            <p>
                <label for="kg_ingredients"><strong>Malzemeler (Her satıra bir tane):</strong></label><br>
                <textarea id="kg_ingredients" name="kg_ingredients" rows="5" style="width:100%;"><?php 
                    $ing = get_post_meta( $post->ID, '_kg_ingredients', true );
                    echo is_array($ing) ? implode("\n", $ing) : ''; 
                ?></textarea>
                <small>Örnek: 1 adet Yumurta</small>
            </p>
        </div>
        <?php
    }

    public function save_custom_meta_data( $post_id ) {
        // Autosave kontrolü
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        
        // Nonce kontrolü
        if ( ! isset( $_POST['kg_recipe_nonce'] ) || ! wp_verify_nonce( $_POST['kg_recipe_nonce'], 'kg_recipe_save' ) ) return;
        
        // Yetki kontrolü
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Verileri kaydet
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
    }
}
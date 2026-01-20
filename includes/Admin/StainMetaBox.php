<?php
namespace KG_Core\Admin;

class StainMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_custom_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_custom_meta_data' ] );
    }

    public function add_custom_meta_boxes() {
        add_meta_box(
            'kg_stain_details',
            'Leke Detayları',
            [ $this, 'render_meta_box' ],
            'stain',
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        // Get current values
        $emoji = get_post_meta( $post->ID, '_kg_stain_emoji', true );
        $difficulty = get_post_meta( $post->ID, '_kg_stain_difficulty', true );
        $steps = get_post_meta( $post->ID, '_kg_stain_steps', true );
        $warnings = get_post_meta( $post->ID, '_kg_stain_warnings', true );
        $related_ingredients = get_post_meta( $post->ID, '_kg_stain_related_ingredients', true );

        // Decode JSON
        $steps_array = json_decode( $steps, true );
        $warnings_array = json_decode( $warnings, true );
        $ingredients_array = json_decode( $related_ingredients, true );

        // Ensure arrays
        if ( ! is_array( $steps_array ) ) $steps_array = [];
        if ( ! is_array( $warnings_array ) ) $warnings_array = [];
        if ( ! is_array( $ingredients_array ) ) $ingredients_array = [];

        // Security nonce
        wp_nonce_field( 'kg_stain_save', 'kg_stain_nonce' );
        ?>
        <div class="kg-stain-meta-box">
            <style>
                .kg-stain-meta-box { padding: 10px 0; }
                .kg-stain-meta-box h3 { margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .kg-stain-meta-box p { margin-bottom: 15px; }
                .kg-repeater-item { background: #f9f9f9; padding: 15px; margin-bottom: 10px; border-left: 3px solid #2271b1; position: relative; }
                .kg-repeater-item .remove-item { position: absolute; top: 10px; right: 10px; color: #b32d2e; cursor: pointer; text-decoration: none; }
                .kg-repeater-item .remove-item:hover { color: #dc3232; }
                .kg-add-item { margin-top: 10px; }
            </style>

            <h3>Temel Bilgiler</h3>
            <p>
                <label for="kg_stain_emoji"><strong>Emoji:</strong></label><br>
                <input type="text" id="kg_stain_emoji" name="kg_stain_emoji" value="<?php echo esc_attr( $emoji ); ?>" style="width:100px;" placeholder="🍅">
            </p>
            <p>
                <label for="kg_stain_difficulty"><strong>Zorluk Seviyesi:</strong></label><br>
                <select id="kg_stain_difficulty" name="kg_stain_difficulty" style="width:200px;">
                    <option value="easy" <?php selected( $difficulty, 'easy' ); ?>>Kolay</option>
                    <option value="medium" <?php selected( $difficulty, 'medium' ); ?>>Orta</option>
                    <option value="hard" <?php selected( $difficulty, 'hard' ); ?>>Zor</option>
                </select>
            </p>

            <h3>Temizlik Adımları</h3>
            <div id="kg-steps-repeater">
                <?php
                if ( ! empty( $steps_array ) ) {
                    foreach ( $steps_array as $index => $step ) {
                        ?>
                        <div class="kg-repeater-item">
                            <a href="#" class="remove-item" onclick="return removeRepeaterItem(this);">✕ Kaldır</a>
                            <p>
                                <label><strong>Adım <?php echo $index + 1; ?>:</strong></label><br>
                                <textarea name="kg_stain_steps[<?php echo $index; ?>][instruction]" rows="3" style="width:100%;"><?php echo esc_textarea( $step['instruction'] ?? '' ); ?></textarea>
                            </p>
                            <p>
                                <label><strong>İpucu (opsiyonel):</strong></label><br>
                                <input type="text" name="kg_stain_steps[<?php echo $index; ?>][tip]" value="<?php echo esc_attr( $step['tip'] ?? '' ); ?>" style="width:100%;">
                            </p>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <button type="button" class="button kg-add-item" onclick="addStepItem();">+ Adım Ekle</button>

            <h3>Uyarılar</h3>
            <div id="kg-warnings-repeater">
                <?php
                if ( ! empty( $warnings_array ) ) {
                    foreach ( $warnings_array as $index => $warning ) {
                        ?>
                        <div class="kg-repeater-item">
                            <a href="#" class="remove-item" onclick="return removeRepeaterItem(this);">✕ Kaldır</a>
                            <input type="text" name="kg_stain_warnings[]" value="<?php echo esc_attr( $warning ); ?>" style="width:95%;">
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <button type="button" class="button kg-add-item" onclick="addWarningItem();">+ Uyarı Ekle</button>

            <h3>Kullanılacak Malzemeler</h3>
            <div id="kg-ingredients-repeater">
                <?php
                if ( ! empty( $ingredients_array ) ) {
                    foreach ( $ingredients_array as $index => $ingredient ) {
                        ?>
                        <div class="kg-repeater-item">
                            <a href="#" class="remove-item" onclick="return removeRepeaterItem(this);">✕ Kaldır</a>
                            <input type="text" name="kg_stain_related_ingredients[]" value="<?php echo esc_attr( $ingredient ); ?>" style="width:95%;">
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <button type="button" class="button kg-add-item" onclick="addIngredientItem();">+ Malzeme Ekle</button>

            <script>
                function removeRepeaterItem(element) {
                    if (confirm('Bu öğeyi kaldırmak istediğinize emin misiniz?')) {
                        element.parentElement.remove();
                    }
                    return false;
                }

                function addStepItem() {
                    var container = document.getElementById('kg-steps-repeater');
                    var index = container.querySelectorAll('.kg-repeater-item').length;
                    var html = '<div class="kg-repeater-item">' +
                        '<a href="#" class="remove-item" onclick="return removeRepeaterItem(this);">✕ Kaldır</a>' +
                        '<p><label><strong>Adım ' + (index + 1) + ':</strong></label><br>' +
                        '<textarea name="kg_stain_steps[' + index + '][instruction]" rows="3" style="width:100%;"></textarea></p>' +
                        '<p><label><strong>İpucu (opsiyonel):</strong></label><br>' +
                        '<input type="text" name="kg_stain_steps[' + index + '][tip]" style="width:100%;"></p>' +
                        '</div>';
                    container.insertAdjacentHTML('beforeend', html);
                }

                function addWarningItem() {
                    var container = document.getElementById('kg-warnings-repeater');
                    var html = '<div class="kg-repeater-item">' +
                        '<a href="#" class="remove-item" onclick="return removeRepeaterItem(this);">✕ Kaldır</a>' +
                        '<input type="text" name="kg_stain_warnings[]" style="width:95%;">' +
                        '</div>';
                    container.insertAdjacentHTML('beforeend', html);
                }

                function addIngredientItem() {
                    var container = document.getElementById('kg-ingredients-repeater');
                    var html = '<div class="kg-repeater-item">' +
                        '<a href="#" class="remove-item" onclick="return removeRepeaterItem(this);">✕ Kaldır</a>' +
                        '<input type="text" name="kg_stain_related_ingredients[]" style="width:95%;">' +
                        '</div>';
                    container.insertAdjacentHTML('beforeend', html);
                }
            </script>
        </div>
        <?php
    }

    public function save_custom_meta_data( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['kg_stain_nonce'] ) || ! wp_verify_nonce( $_POST['kg_stain_nonce'], 'kg_stain_save' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Check post type
        if ( get_post_type( $post_id ) !== 'stain' ) {
            return;
        }

        // Save emoji
        if ( isset( $_POST['kg_stain_emoji'] ) ) {
            update_post_meta( $post_id, '_kg_stain_emoji', sanitize_text_field( $_POST['kg_stain_emoji'] ) );
        }

        // Save difficulty
        if ( isset( $_POST['kg_stain_difficulty'] ) ) {
            $difficulty = sanitize_text_field( $_POST['kg_stain_difficulty'] );
            if ( in_array( $difficulty, [ 'easy', 'medium', 'hard' ] ) ) {
                update_post_meta( $post_id, '_kg_stain_difficulty', $difficulty );
            }
        }

        // Save steps
        $steps = [];
        if ( isset( $_POST['kg_stain_steps'] ) && is_array( $_POST['kg_stain_steps'] ) ) {
            $step_number = 1;
            foreach ( $_POST['kg_stain_steps'] as $index => $step ) {
                if ( ! empty( $step['instruction'] ) ) {
                    $steps[] = [
                        'step' => $step_number++, // Use sequential counter for consistent numbering
                        'instruction' => sanitize_textarea_field( $step['instruction'] ),
                        'tip' => ! empty( $step['tip'] ) ? sanitize_text_field( $step['tip'] ) : '',
                    ];
                }
            }
        }
        update_post_meta( $post_id, '_kg_stain_steps', json_encode( $steps, JSON_UNESCAPED_UNICODE ) );

        // Save warnings
        $warnings = [];
        if ( isset( $_POST['kg_stain_warnings'] ) && is_array( $_POST['kg_stain_warnings'] ) ) {
            foreach ( $_POST['kg_stain_warnings'] as $warning ) {
                if ( ! empty( $warning ) ) {
                    $warnings[] = sanitize_text_field( $warning );
                }
            }
        }
        update_post_meta( $post_id, '_kg_stain_warnings', json_encode( $warnings, JSON_UNESCAPED_UNICODE ) );

        // Save related ingredients
        $ingredients = [];
        if ( isset( $_POST['kg_stain_related_ingredients'] ) && is_array( $_POST['kg_stain_related_ingredients'] ) ) {
            foreach ( $_POST['kg_stain_related_ingredients'] as $ingredient ) {
                if ( ! empty( $ingredient ) ) {
                    $ingredients[] = sanitize_text_field( $ingredient );
                }
            }
        }
        update_post_meta( $post_id, '_kg_stain_related_ingredients', json_encode( $ingredients, JSON_UNESCAPED_UNICODE ) );
    }
}

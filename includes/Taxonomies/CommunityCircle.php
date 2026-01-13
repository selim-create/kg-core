<? php
namespace KG_Core\Taxonomies;

class CommunityCircle {

    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'init', [ $this, 'insert_default_terms' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );
        
        // Admin form fields
        add_action( 'community_circle_add_form_fields', [ $this, 'add_term_fields' ] );
        add_action( 'community_circle_edit_form_fields', [ $this, 'edit_term_fields' ] );
        
        // Save meta data
        add_action( 'created_community_circle', [ $this, 'save_term_meta' ] );
        add_action( 'edited_community_circle', [ $this, 'save_term_meta' ] );
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x( 'Çemberler', 'taxonomy general name', 'kg-core' ),
            'singular_name'     => _x( 'Çember', 'taxonomy singular name', 'kg-core' ),
            'search_items'      => __( 'Çember Ara', 'kg-core' ),
            'all_items'         => __( 'Tüm Çemberler', 'kg-core' ),
            'edit_item'         => __( 'Çemberi Düzenle', 'kg-core' ),
            'update_item'       => __( 'Güncelle', 'kg-core' ),
            'add_new_item'      => __( 'Yeni Çember Ekle', 'kg-core' ),
            'new_item_name'     => __( 'Yeni Çember Adı', 'kg-core' ),
            'menu_name'         => __( 'Çemberler (Topluluk)', 'kg-core' ),
        ];

        $args = [
            'hierarchical'      => false, // Tag benzeri, hiyerarşik değil
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'cember' ],
            'show_in_rest'      => true,
            'rest_base'         => 'community-circles',
        ];

        // Hem 'discussion' hem de 'recipe' CPT'lerine bağlı
        register_taxonomy( 'community_circle', [ 'discussion', 'recipe' ], $args );
    }

    public function insert_default_terms() {
        $existing = get_terms( [
            'taxonomy' => 'community_circle',
            'hide_empty' => false,
        ] );

        if ( ! empty( $existing ) && !  is_wp_error( $existing ) ) {
            return;
        }

        $default_terms = [
            [
                'name' => '6-9 Ay Bebek',
                'slug' => '6-9-ay',
                'meta' => [
                    'description' => '6-9 aylık bebekler için ek gıda yolculuğu',
                    'icon' => '👶',
                    'color_code' => '#A8E6CF',
                    'order' => 1,
                ],
            ],
            [
                'name' => '9-12 Ay Bebek',
                'slug' => '9-12-ay',
                'meta' => [
                    'description' => '9-12 aylık bebekler için pütürlü gıdalar',
                    'icon' => '🍼',
                    'color_code' => '#FFB347',
                    'order' => 2,
                ],
            ],
            [
                'name' => '1-2 Yaş Çocuk',
                'slug' => '1-2-yas',
                'meta' => [
                    'description' => '1-2 yaş arası çocuklar için aile sofrasına geçiş',
                    'icon' => '🧒',
                    'color_code' => '#87CEEB',
                    'order' => 3,
                ],
            ],
            [
                'name' => 'Alerji ve İntoleranslar',
                'slug' => 'alerji',
                'meta' => [
                    'description' => 'Gıda alerjileri ve intoleranslar hakkında sorular',
                    'icon' => '⚠️',
                    'color_code' => '#FF6B6B',
                    'order' => 4,
                ],
            ],
            [
                'name' => 'Uyku ve Beslenme',
                'slug' => 'uyku-beslenme',
                'meta' => [
                    'description' => 'Uyku düzeni ve beslenme ilişkisi',
                    'icon' => '😴',
                    'color_code' => '#C9B1FF',
                    'order' => 5,
                ],
            ],
            [
                'name' => 'BLW (Baby Led Weaning)',
                'slug' => 'blw',
                'meta' => [
                    'description' => 'Bebek liderliğinde beslenme yöntemi',
                    'icon' => '🥕',
                    'color_code' => '#FFD93D',
                    'order' => 6,
                ],
            ],
            [
                'name' => 'Genel Sorular',
                'slug' => 'genel',
                'meta' => [
                    'description' => 'Diğer kategorilere uymayan genel sorular',
                    'icon' => '💬',
                    'color_code' => '#E8E8E8',
                    'order' => 99,
                ],
            ],
        ];

        foreach ( $default_terms as $term_data ) {
            $term = wp_insert_term( $term_data['name'], 'community_circle', [
                'slug' => $term_data['slug'],
            ] );

            if ( !  is_wp_error( $term ) ) {
                $term_id = $term['term_id'];
                foreach ( $term_data['meta'] as $key => $value ) {
                    update_term_meta( $term_id, '_kg_circle_' . $key, $value );
                }
            }
        }
    }

    public function add_term_fields() {
        ?>
        <div class="form-field">
            <label for="kg_circle_description"><?php _e( 'Açıklama', 'kg-core' ); ?></label>
            <textarea name="kg_circle_description" id="kg_circle_description" rows="3"></textarea>
            <p class="description"><?php _e( 'Çember açıklaması', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_circle_icon"><?php _e( 'İkon (Emoji)', 'kg-core' ); ?></label>
            <input type="text" name="kg_circle_icon" id="kg_circle_icon" value="" placeholder="👶">
            <p class="description"><?php _e( 'Çember için emoji ikon (örn: 👶, 🍼, ⚠️)', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_circle_color_code"><?php _e( 'Renk Kodu', 'kg-core' ); ?></label>
            <input type="text" name="kg_circle_color_code" id="kg_circle_color_code" value="" placeholder="#A8E6CF">
            <p class="description"><?php _e( 'HEX renk kodu', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_circle_order"><?php _e( 'Sıralama', 'kg-core' ); ?></label>
            <input type="number" name="kg_circle_order" id="kg_circle_order" value="10" min="0" max="100">
            <p class="description"><?php _e( 'Görüntüleme sırası (düşük sayı önce gösterilir)', 'kg-core' ); ?></p>
        </div>
        <?php
    }

    public function edit_term_fields( $term ) {
        $term_id = $term->term_id;
        $description = get_term_meta( $term_id, '_kg_circle_description', true );
        $icon = get_term_meta( $term_id, '_kg_circle_icon', true );
        $color_code = get_term_meta( $term_id, '_kg_circle_color_code', true );
        $order = get_term_meta( $term_id, '_kg_circle_order', true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="kg_circle_description"><?php _e( 'Açıklama', 'kg-core' ); ?></label></th>
            <td>
                <textarea name="kg_circle_description" id="kg_circle_description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
                <p class="description"><?php _e( 'Çember açıklaması', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="kg_circle_icon"><?php _e( 'İkon (Emoji)', 'kg-core' ); ?></label></th>
            <td>
                <input type="text" name="kg_circle_icon" id="kg_circle_icon" value="<?php echo esc_attr( $icon ); ?>" placeholder="👶">
                <p class="description"><? php _e( 'Çember için emoji ikon', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="kg_circle_color_code"><?php _e( 'Renk Kodu', 'kg-core' ); ?></label></th>
            <td>
                <input type="text" name="kg_circle_color_code" id="kg_circle_color_code" value="<?php echo esc_attr( $color_code ); ?>" placeholder="#A8E6CF">
                <p class="description"><?php _e( 'HEX renk kodu', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><label for="kg_circle_order"><?php _e( 'Sıralama', 'kg-core' ); ?></label></th>
            <td>
                <input type="number" name="kg_circle_order" id="kg_circle_order" value="<?php echo esc_attr( $order ?:  10 ); ?>" min="0" max="100">
                <p class="description"><? php _e( 'Görüntüleme sırası', 'kg-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_term_meta( $term_id ) {
        if ( isset( $_POST['kg_circle_description'] ) ) {
            update_term_meta( $term_id, '_kg_circle_description', sanitize_textarea_field( $_POST['kg_circle_description'] ) );
        }

        if ( isset( $_POST['kg_circle_icon'] ) ) {
            update_term_meta( $term_id, '_kg_circle_icon', sanitize_text_field( $_POST['kg_circle_icon'] ) );
        }

        if ( isset( $_POST['kg_circle_color_code'] ) ) {
            $color_code = sanitize_text_field( $_POST['kg_circle_color_code'] );
            if ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $color_code ) ) {
                update_term_meta( $term_id, '_kg_circle_color_code', $color_code );
            }
        }

        if ( isset( $_POST['kg_circle_order'] ) ) {
            update_term_meta( $term_id, '_kg_circle_order', absint( $_POST['kg_circle_order'] ) );
        }
    }

    public function register_rest_fields() {
        register_rest_field( 'community_circle', 'circle_meta', [
            'get_callback' => function( $term ) {
                return [
                    'description' => get_term_meta( $term['id'], '_kg_circle_description', true ) ?: '',
                    'icon' => get_term_meta( $term['id'], '_kg_circle_icon', true ) ?: '💬',
                    'color_code' => get_term_meta( $term['id'], '_kg_circle_color_code', true ) ?: '#E8E8E8',
                    'order' => (int) ( get_term_meta( $term['id'], '_kg_circle_order', true ) ?: 10 ),
                ];
            },
            'schema' => [
                'description' => __( 'Çember meta verileri', 'kg-core' ),
                'type' => 'object',
            ],
        ] );
    }
}
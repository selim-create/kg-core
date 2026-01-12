<?php
namespace KG_Core\Taxonomies;

class MealType {

    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'init', [ $this, 'insert_default_terms' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );
        
        // Admin form fields
        add_action( 'meal-type_add_form_fields', [ $this, 'add_term_fields' ] );
        add_action( 'meal-type_edit_form_fields', [ $this, 'edit_term_fields' ] );
        
        // Save meta data
        add_action( 'created_meal-type', [ $this, 'save_term_meta' ] );
        add_action( 'edited_meal-type', [ $this, 'save_term_meta' ] );
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x( 'Öğün Tipleri', 'taxonomy general name', 'kg-core' ),
            'singular_name'     => _x( 'Öğün Tipi', 'taxonomy singular name', 'kg-core' ),
            'search_items'      => __( 'Öğün Tipi Ara', 'kg-core' ),
            'all_items'         => __( 'Tüm Öğün Tipleri', 'kg-core' ),
            'edit_item'         => __( 'Düzenle', 'kg-core' ),
            'update_item'       => __( 'Güncelle', 'kg-core' ),
            'add_new_item'      => __( 'Yeni Ekle', 'kg-core' ),
            'new_item_name'     => __( 'Yeni Öğün Tipi Adı', 'kg-core' ),
            'menu_name'         => __( 'Öğün Tipleri', 'kg-core' ),
        ];

        $args = [
            'hierarchical'      => false, // Etiket gibi düz yapı
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'meal-type' ],
            'show_in_rest'      => true,
        ];

        // 'recipe' CPT'sine bağlıyoruz
        register_taxonomy( 'meal-type', [ 'recipe' ], $args );
    }

    public function insert_default_terms() {
        // Only insert if terms don't exist yet
        $existing = get_terms( [
            'taxonomy' => 'meal-type',
            'hide_empty' => false,
        ] );

        if ( ! empty( $existing ) ) {
            return;
        }

        $default_terms = [
            [
                'name' => 'Kahvaltı',
                'slug' => 'kahvalti',
                'meta' => [
                    'icon' => '🌅',
                    'time_range' => '07:00-09:00',
                    'color_code' => '#FFE4B5',
                ],
            ],
            [
                'name' => 'Ara Öğün (Kuşluk)',
                'slug' => 'ara-ogun-kusluk',
                'meta' => [
                    'icon' => '🍎',
                    'time_range' => '10:00-11:00',
                    'color_code' => '#98FB98',
                ],
            ],
            [
                'name' => 'Öğle Yemeği',
                'slug' => 'ogle-yemegi',
                'meta' => [
                    'icon' => '🍽️',
                    'time_range' => '12:00-13:00',
                    'color_code' => '#FFD700',
                ],
            ],
            [
                'name' => 'Ara Öğün (İkindi)',
                'slug' => 'ara-ogun-ikindi',
                'meta' => [
                    'icon' => '🧃',
                    'time_range' => '15:00-16:00',
                    'color_code' => '#DDA0DD',
                ],
            ],
            [
                'name' => 'Akşam Yemeği',
                'slug' => 'aksam-yemegi',
                'meta' => [
                    'icon' => '🌙',
                    'time_range' => '18:00-19:00',
                    'color_code' => '#87CEEB',
                ],
            ],
            [
                'name' => 'Beslenme Çantası',
                'slug' => 'beslenme-cantasi',
                'meta' => [
                    'icon' => '🎒',
                    'time_range' => 'Değişken',
                    'color_code' => '#F0E68C',
                ],
            ],
        ];

        foreach ( $default_terms as $term_data ) {
            $term = wp_insert_term( $term_data['name'], 'meal-type', [
                'slug' => $term_data['slug'],
            ] );

            if ( ! is_wp_error( $term ) ) {
                $term_id = $term['term_id'];
                foreach ( $term_data['meta'] as $key => $value ) {
                    update_term_meta( $term_id, '_kg_' . $key, $value );
                }
            }
        }
    }

    public function add_term_fields() {
        ?>
        <div class="form-field">
            <label for="kg_icon"><?php _e( 'İkon (Emoji)', 'kg-core' ); ?></label>
            <input type="text" name="kg_icon" id="kg_icon" value="" placeholder="🌅">
            <p class="description"><?php _e( 'Öğün tipi için emoji ikonu (örn: 🌅)', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_time_range"><?php _e( 'Zaman Aralığı', 'kg-core' ); ?></label>
            <input type="text" name="kg_time_range" id="kg_time_range" value="" placeholder="07:00-09:00">
            <p class="description"><?php _e( 'Önerilen saat aralığı (örn: 07:00-09:00)', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_color_code"><?php _e( 'Renk Kodu', 'kg-core' ); ?></label>
            <input type="text" name="kg_color_code" id="kg_color_code" value="" placeholder="#FFE4B5">
            <p class="description"><?php _e( 'HEX renk kodu (örn: #FFE4B5)', 'kg-core' ); ?></p>
        </div>
        <?php
    }

    public function edit_term_fields( $term ) {
        $term_id = $term->term_id;
        $icon = get_term_meta( $term_id, '_kg_icon', true );
        $time_range = get_term_meta( $term_id, '_kg_time_range', true );
        $color_code = get_term_meta( $term_id, '_kg_color_code', true );
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="kg_icon"><?php _e( 'İkon (Emoji)', 'kg-core' ); ?></label>
            </th>
            <td>
                <input type="text" name="kg_icon" id="kg_icon" value="<?php echo esc_attr( $icon ); ?>" placeholder="🌅">
                <p class="description"><?php _e( 'Öğün tipi için emoji ikonu (örn: 🌅)', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_time_range"><?php _e( 'Zaman Aralığı', 'kg-core' ); ?></label>
            </th>
            <td>
                <input type="text" name="kg_time_range" id="kg_time_range" value="<?php echo esc_attr( $time_range ); ?>" placeholder="07:00-09:00">
                <p class="description"><?php _e( 'Önerilen saat aralığı (örn: 07:00-09:00)', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_color_code"><?php _e( 'Renk Kodu', 'kg-core' ); ?></label>
            </th>
            <td>
                <input type="text" name="kg_color_code" id="kg_color_code" value="<?php echo esc_attr( $color_code ); ?>" placeholder="#FFE4B5">
                <p class="description"><?php _e( 'HEX renk kodu (örn: #FFE4B5)', 'kg-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_term_meta( $term_id ) {
        $meta_fields = [
            'kg_icon',
            'kg_time_range',
            'kg_color_code',
        ];

        foreach ( $meta_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_term_meta( $term_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
    }

    public function register_rest_fields() {
        register_rest_field( 'meal-type', 'meal_type_meta', [
            'get_callback' => function( $term ) {
                return [
                    'icon' => get_term_meta( $term['id'], '_kg_icon', true ),
                    'time_range' => get_term_meta( $term['id'], '_kg_time_range', true ),
                    'color_code' => get_term_meta( $term['id'], '_kg_color_code', true ),
                ];
            },
            'schema' => [
                'description' => __( 'Öğün tipi meta verileri', 'kg-core' ),
                'type'        => 'object',
            ],
        ] );
    }
}

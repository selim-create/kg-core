<?php
namespace KG_Core\Taxonomies;

class AgeGroup {

    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'init', [ $this, 'insert_default_terms' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );
        
        // Admin form fields
        add_action( 'age-group_add_form_fields', [ $this, 'add_term_fields' ] );
        add_action( 'age-group_edit_form_fields', [ $this, 'edit_term_fields' ] );
        
        // Save meta data
        add_action( 'created_age-group', [ $this, 'save_term_meta' ] );
        add_action( 'edited_age-group', [ $this, 'save_term_meta' ] );
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x( 'Yaş Grupları', 'taxonomy general name', 'kg-core' ),
            'singular_name'     => _x( 'Yaş Grubu', 'taxonomy singular name', 'kg-core' ),
            'search_items'      => __( 'Yaş Grubu Ara', 'kg-core' ),
            'all_items'         => __( 'Tüm Yaş Grupları', 'kg-core' ),
            'edit_item'         => __( 'Düzenle', 'kg-core' ),
            'update_item'       => __( 'Güncelle', 'kg-core' ),
            'add_new_item'      => __( 'Yeni Ekle', 'kg-core' ),
            'new_item_name'     => __( 'Yeni Yaş Grubu Adı', 'kg-core' ),
            'menu_name'         => __( 'Yaş Grupları', 'kg-core' ),
        ];

        $args = [
            'hierarchical'      => true, // Kategori gibi hiyerarşik
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'age-group' ],
            'show_in_rest'      => true,
        ];

        // Sadece 'recipe' CPT'sine bağlıyoruz
        register_taxonomy( 'age-group', [ 'recipe' ], $args );
    }

    public function insert_default_terms() {
        // Only insert if terms don't exist yet
        $existing = get_terms( [
            'taxonomy' => 'age-group',
            'hide_empty' => false,
        ] );

        if ( ! empty( $existing ) ) {
            return;
        }

        $default_terms = [
            [
                'name' => 'Hazırlık Evresi (0-6 Ay)',
                'slug' => '0-6-ay-sadece-sut',
                'meta' => [
                    'min_month' => 0,
                    'max_month' => 6,
                    'daily_meal_count' => 0,
                    'max_salt_limit' => '0g (Yasak)',
                    'texture_guide' => 'Sadece anne sütü veya formül mama',
                    'forbidden_list' => json_encode(['Tüm Katı Gıdalar']),
                    'color_code' => '#E8F5E9',
                    'warning_message' => 'Bu dönemde sadece anne sütü veya formül mama verilmelidir.',
                ],
            ],
            [
                'name' => 'Başlangıç & Tadım (6-8 Ay)',
                'slug' => '6-8-ay-baslangic',
                'meta' => [
                    'min_month' => 6,
                    'max_month' => 8,
                    'daily_meal_count' => 2,
                    'max_salt_limit' => '0g (Yasak)',
                    'texture_guide' => 'Yoğurt kıvamı, pürüzsüz püreler veya parmak boyutunda yumuşak parçalar (BLW)',
                    'forbidden_list' => json_encode(['Bal', 'İnek Sütü', 'Tuz', 'Şeker', 'Yumurta Beyazı']),
                    'color_code' => '#FFAB91',
                    'warning_message' => 'Her yeni gıdayı 3 gün arayla deneyin.',
                ],
            ],
            [
                'name' => 'Keşif & Pütürlüye Geçiş (9-11 Ay)',
                'slug' => '9-11-ay-kesif',
                'meta' => [
                    'min_month' => 9,
                    'max_month' => 11,
                    'daily_meal_count' => 3,
                    'max_salt_limit' => '0g (Yasak)',
                    'texture_guide' => 'Çatalla ezilmiş, minik küpler, elle tutulabilir Finger Foods',
                    'forbidden_list' => json_encode(['Bal', 'İnek Sütü (içecek olarak)', 'Tuz', 'Şeker']),
                    'color_code' => '#A5D6A7',
                    'warning_message' => 'Kendi kendine yeme (Self-feeding) teşvik edilebilir.',
                ],
            ],
            [
                'name' => 'Aile Sofrasına Geçiş (12-24 Ay)',
                'slug' => '12-24-ay-gecis',
                'meta' => [
                    'min_month' => 12,
                    'max_month' => 24,
                    'daily_meal_count' => 5,
                    'max_salt_limit' => '<1g/gün',
                    'texture_guide' => 'Aile yemekleri, küçük parçalar',
                    'forbidden_list' => json_encode(['Aşırı Tuz', 'İşlenmiş Şeker']),
                    'color_code' => '#90CAF9',
                    'warning_message' => 'Bal ve inek sütü artık serbest, ancak tuz miktarına dikkat.',
                ],
            ],
            [
                'name' => 'Çocuk Gurme (2+ Yaş)',
                'slug' => '2-yas-ve-uzeri',
                'meta' => [
                    'min_month' => 24,
                    'max_month' => 144,
                    'daily_meal_count' => 5,
                    'max_salt_limit' => '<2g/gün (2-4 yaş), <3g/gün (4+ yaş)',
                    'texture_guide' => 'Tüm dokular uygun',
                    'forbidden_list' => json_encode([]),
                    'color_code' => '#CE93D8',
                    'warning_message' => '',
                ],
            ],
        ];

        foreach ( $default_terms as $term_data ) {
            $term = wp_insert_term( $term_data['name'], 'age-group', [
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
            <label for="kg_min_month"><?php _e( 'Minimum Ay', 'kg-core' ); ?></label>
            <input type="number" name="kg_min_month" id="kg_min_month" value="" min="0" max="144">
            <p class="description"><?php _e( 'Yaş grubunun başlangıç ayı (örn: 6)', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_max_month"><?php _e( 'Maksimum Ay', 'kg-core' ); ?></label>
            <input type="number" name="kg_max_month" id="kg_max_month" value="" min="0" max="144">
            <p class="description"><?php _e( 'Yaş grubunun bitiş ayı (örn: 8)', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_daily_meal_count"><?php _e( 'Günlük Öğün Sayısı', 'kg-core' ); ?></label>
            <input type="number" name="kg_daily_meal_count" id="kg_daily_meal_count" value="" min="0" max="10">
            <p class="description"><?php _e( 'Bu yaş grubunda günlük kaç öğün verilmeli (örn: 2)', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_max_salt_limit"><?php _e( 'Tuz Limiti', 'kg-core' ); ?></label>
            <input type="text" name="kg_max_salt_limit" id="kg_max_salt_limit" value="">
            <p class="description"><?php _e( 'Maksimum tuz miktarı (örn: "0g (Yasak)" veya "<1g/gün")', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_texture_guide"><?php _e( 'Doku Rehberi', 'kg-core' ); ?></label>
            <textarea name="kg_texture_guide" id="kg_texture_guide" rows="3"></textarea>
            <p class="description"><?php _e( 'Bu yaş için uygun gıda dokusu (örn: "Yoğurt kıvamı, pürüzsüz püreler")', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_forbidden_list"><?php _e( 'Yasaklı Gıdalar (JSON Array)', 'kg-core' ); ?></label>
            <textarea name="kg_forbidden_list" id="kg_forbidden_list" rows="3"></textarea>
            <p class="description"><?php _e( 'Yasaklı gıdalar listesi (örn: ["Bal", "İnek Sütü", "Tuz"])', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_color_code"><?php _e( 'Renk Kodu', 'kg-core' ); ?></label>
            <input type="text" name="kg_color_code" id="kg_color_code" value="" placeholder="#A8E6CF">
            <p class="description"><?php _e( 'HEX renk kodu (örn: #A8E6CF)', 'kg-core' ); ?></p>
        </div>

        <div class="form-field">
            <label for="kg_warning_message"><?php _e( 'Uyarı Mesajı', 'kg-core' ); ?></label>
            <textarea name="kg_warning_message" id="kg_warning_message" rows="2"></textarea>
            <p class="description"><?php _e( 'Bu yaş grubu için özel uyarı mesajı', 'kg-core' ); ?></p>
        </div>
        <?php
    }

    public function edit_term_fields( $term ) {
        $term_id = $term->term_id;
        $min_month = get_term_meta( $term_id, '_kg_min_month', true );
        $max_month = get_term_meta( $term_id, '_kg_max_month', true );
        $daily_meal_count = get_term_meta( $term_id, '_kg_daily_meal_count', true );
        $max_salt_limit = get_term_meta( $term_id, '_kg_max_salt_limit', true );
        $texture_guide = get_term_meta( $term_id, '_kg_texture_guide', true );
        $forbidden_list = get_term_meta( $term_id, '_kg_forbidden_list', true );
        $color_code = get_term_meta( $term_id, '_kg_color_code', true );
        $warning_message = get_term_meta( $term_id, '_kg_warning_message', true );
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="kg_min_month"><?php _e( 'Minimum Ay', 'kg-core' ); ?></label>
            </th>
            <td>
                <input type="number" name="kg_min_month" id="kg_min_month" value="<?php echo esc_attr( $min_month ); ?>" min="0" max="144">
                <p class="description"><?php _e( 'Yaş grubunun başlangıç ayı (örn: 6)', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_max_month"><?php _e( 'Maksimum Ay', 'kg-core' ); ?></label>
            </th>
            <td>
                <input type="number" name="kg_max_month" id="kg_max_month" value="<?php echo esc_attr( $max_month ); ?>" min="0" max="144">
                <p class="description"><?php _e( 'Yaş grubunun bitiş ayı (örn: 8)', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_daily_meal_count"><?php _e( 'Günlük Öğün Sayısı', 'kg-core' ); ?></label>
            </th>
            <td>
                <input type="number" name="kg_daily_meal_count" id="kg_daily_meal_count" value="<?php echo esc_attr( $daily_meal_count ); ?>" min="0" max="10">
                <p class="description"><?php _e( 'Bu yaş grubunda günlük kaç öğün verilmeli (örn: 2)', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_max_salt_limit"><?php _e( 'Tuz Limiti', 'kg-core' ); ?></label>
            </th>
            <td>
                <input type="text" name="kg_max_salt_limit" id="kg_max_salt_limit" value="<?php echo esc_attr( $max_salt_limit ); ?>">
                <p class="description"><?php _e( 'Maksimum tuz miktarı (örn: "0g (Yasak)" veya "<1g/gün")', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_texture_guide"><?php _e( 'Doku Rehberi', 'kg-core' ); ?></label>
            </th>
            <td>
                <textarea name="kg_texture_guide" id="kg_texture_guide" rows="3" class="large-text"><?php echo esc_textarea( $texture_guide ); ?></textarea>
                <p class="description"><?php _e( 'Bu yaş için uygun gıda dokusu (örn: "Yoğurt kıvamı, pürüzsüz püreler")', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_forbidden_list"><?php _e( 'Yasaklı Gıdalar (JSON Array)', 'kg-core' ); ?></label>
            </th>
            <td>
                <textarea name="kg_forbidden_list" id="kg_forbidden_list" rows="3" class="large-text"><?php echo esc_textarea( $forbidden_list ); ?></textarea>
                <p class="description"><?php _e( 'Yasaklı gıdalar listesi (örn: ["Bal", "İnek Sütü", "Tuz"])', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_color_code"><?php _e( 'Renk Kodu', 'kg-core' ); ?></label>
            </th>
            <td>
                <input type="text" name="kg_color_code" id="kg_color_code" value="<?php echo esc_attr( $color_code ); ?>" placeholder="#A8E6CF">
                <p class="description"><?php _e( 'HEX renk kodu (örn: #A8E6CF)', 'kg-core' ); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kg_warning_message"><?php _e( 'Uyarı Mesajı', 'kg-core' ); ?></label>
            </th>
            <td>
                <textarea name="kg_warning_message" id="kg_warning_message" rows="2" class="large-text"><?php echo esc_textarea( $warning_message ); ?></textarea>
                <p class="description"><?php _e( 'Bu yaş grubu için özel uyarı mesajı', 'kg-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_term_meta( $term_id ) {
        // WordPress handles nonce verification for taxonomy term updates automatically
        // via check_admin_referer() in wp-admin/edit-tags.php
        
        // Get both values first for validation
        $min_month = isset( $_POST['kg_min_month'] ) ? absint( $_POST['kg_min_month'] ) : null;
        $max_month = isset( $_POST['kg_max_month'] ) ? absint( $_POST['kg_max_month'] ) : null;

        // Validate range if both values are provided
        if ( $min_month !== null && $max_month !== null && $max_month < $min_month ) {
            error_log( 'KG Core: max_month (' . $max_month . ') < min_month (' . $min_month . ') for term ' . $term_id . ', adjusting max_month to match min_month' );
            $max_month = $min_month;
        }

        // Save validated numeric fields
        if ( $min_month !== null ) {
            update_term_meta( $term_id, '_kg_min_month', $min_month );
        }

        if ( $max_month !== null ) {
            update_term_meta( $term_id, '_kg_max_month', $max_month );
        }

        if ( isset( $_POST['kg_daily_meal_count'] ) ) {
            $daily_meal_count = absint( $_POST['kg_daily_meal_count'] );
            update_term_meta( $term_id, '_kg_daily_meal_count', $daily_meal_count );
        }

        // Sanitize text fields
        if ( isset( $_POST['kg_max_salt_limit'] ) ) {
            update_term_meta( $term_id, '_kg_max_salt_limit', sanitize_text_field( $_POST['kg_max_salt_limit'] ) );
        }

        // Validate and sanitize HEX color code
        if ( isset( $_POST['kg_color_code'] ) ) {
            $color_code = sanitize_text_field( $_POST['kg_color_code'] );
            // Validate HEX color format
            if ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $color_code ) ) {
                update_term_meta( $term_id, '_kg_color_code', $color_code );
            } else {
                // Log error and use default color
                error_log( 'KG Core: Invalid HEX color code for age-group term ' . $term_id . ': ' . $color_code );
                update_term_meta( $term_id, '_kg_color_code', '#E8F5E9' );
            }
        }

        // Sanitize textarea fields
        if ( isset( $_POST['kg_texture_guide'] ) ) {
            update_term_meta( $term_id, '_kg_texture_guide', sanitize_textarea_field( $_POST['kg_texture_guide'] ) );
        }

        if ( isset( $_POST['kg_warning_message'] ) ) {
            update_term_meta( $term_id, '_kg_warning_message', sanitize_textarea_field( $_POST['kg_warning_message'] ) );
        }

        // Validate and sanitize JSON field
        if ( isset( $_POST['kg_forbidden_list'] ) ) {
            $forbidden_list = stripslashes( $_POST['kg_forbidden_list'] );
            // Validate JSON and decode once
            $decoded = json_decode( $forbidden_list );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                update_term_meta( $term_id, '_kg_forbidden_list', $forbidden_list );
            } else {
                // Log error and save empty array as fallback
                error_log( 'KG Core: Invalid JSON in forbidden_list for term ' . $term_id . ': ' . json_last_error_msg() );
                update_term_meta( $term_id, '_kg_forbidden_list', json_encode( [] ) );
            }
        }
    }

    public function register_rest_fields() {
        register_rest_field( 'age-group', 'age_group_meta', [
            'get_callback' => function( $term ) {
                $forbidden_list_json = get_term_meta( $term['id'], '_kg_forbidden_list', true );
                $forbidden_list = [];
                if ( $forbidden_list_json ) {
                    $decoded = json_decode( $forbidden_list_json, true );
                    if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                        $forbidden_list = $decoded;
                    }
                }

                return [
                    'min_month' => (int) get_term_meta( $term['id'], '_kg_min_month', true ),
                    'max_month' => (int) get_term_meta( $term['id'], '_kg_max_month', true ),
                    'daily_meal_count' => (int) get_term_meta( $term['id'], '_kg_daily_meal_count', true ),
                    'max_salt_limit' => get_term_meta( $term['id'], '_kg_max_salt_limit', true ),
                    'texture_guide' => get_term_meta( $term['id'], '_kg_texture_guide', true ),
                    'forbidden_list' => $forbidden_list,
                    'color_code' => get_term_meta( $term['id'], '_kg_color_code', true ),
                    'warning_message' => get_term_meta( $term['id'], '_kg_warning_message', true ),
                ];
            },
            'schema' => [
                'description' => __( 'Yaş grubu meta verileri', 'kg-core' ),
                'type'        => 'object',
            ],
        ] );
    }
}
<?php
namespace KG_Core\PostTypes;

class Tool {
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_tool_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_tool_meta' ] );
        add_filter( 'manage_tool_posts_columns', [ $this, 'add_custom_columns' ] );
        add_action( 'manage_tool_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
    }

    public function register_post_type() {
        register_post_type( 'tool', [
            'labels' => [
                'name' => 'Araçlar',
                'singular_name' => 'Araç'
            ],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-calculator'
        ]);
    }

    public function register_taxonomy() {
        register_taxonomy( 'tool_type', 'tool', [
            'labels' => [
                'name' => 'Araç Tipleri',
                'singular_name' => 'Araç Tipi'
            ],
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite' => [ 'slug' => 'tool-type' ],
        ]);
    }

    public function add_tool_meta_box() {
        add_meta_box(
            'kg_tool_settings',
            'Araç Ayarları',
            [ $this, 'render_tool_meta_box' ],
            'tool',
            'normal',
            'high'
        );
    }
    
    public function render_tool_meta_box( $post ) {
        // Get existing values
        $tool_type = get_post_meta( $post->ID, '_kg_tool_type', true );
        $tool_icon = get_post_meta( $post->ID, '_kg_tool_icon', true );
        $is_active = get_post_meta( $post->ID, '_kg_is_active', true );
        $requires_auth = get_post_meta( $post->ID, '_kg_requires_auth', true );
        
        // Security nonce
        wp_nonce_field( 'kg_tool_settings_save', 'kg_tool_settings_nonce' );
        
        // Tool types
        $tool_types = [
            'blw_test' => 'BLW Hazırlık Testi',
            'percentile' => 'Persentil Hesaplayıcı',
            'water_calculator' => 'Su İhtiyacı Hesaplayıcı',
            'meal_planner' => 'Yemek Planlayıcı',
            'bath_planner' => 'Banyo Rutini Planlayıcı',
            'hygiene_calculator' => 'Günlük Hijyen İhtiyacı Hesaplayıcı',
            'diaper_calculator' => 'Akıllı Bez Hesaplayıcı',
            'air_quality_guide' => 'Hava Kalitesi Rehberi',
            'stain_encyclopedia' => 'Leke Ansiklopedisi',
            'food_guide' => 'Ek Gıda Rehberi',
            'solid_food_readiness' => 'Ek Gıdaya Başlama Kontrolü',
            'food_checker' => 'Bu Gıda Verilir mi?',
            'allergen_planner' => 'Alerjen Deneme Planlayıcı',
            'food_trial_calendar' => 'Besin Deneme Takvimi',
        ];
        ?>
        <div class="kg-tool-settings-meta-box">
            <p>
                <label for="kg_tool_type"><strong>Araç Tipi:</strong> <span style="color: red;">*</span></label><br>
                <select id="kg_tool_type" name="kg_tool_type" style="width:100%;" required>
                    <option value="">Seçiniz...</option>
                    <?php foreach ( $tool_types as $key => $label ): ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $tool_type, $key ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label for="kg_tool_icon"><strong>Araç İkonu:</strong></label><br>
                <input type="text" id="kg_tool_icon" name="kg_tool_icon" value="<?php echo esc_attr( $tool_icon ?: 'fa-calculator' ); ?>" style="width:100%;" placeholder="fa-calculator">
                <small>FontAwesome class (örn: fa-utensils)</small>
            </p>
            
            <p>
                <label for="kg_is_active">
                    <input type="checkbox" id="kg_is_active" name="kg_is_active" value="1" <?php checked( $is_active, '1' ); ?>>
                    <strong>Aktif mi?</strong>
                </label>
            </p>
            
            <p>
                <label for="kg_requires_auth">
                    <input type="checkbox" id="kg_requires_auth" name="kg_requires_auth" value="1" <?php checked( $requires_auth, '1' ); ?>>
                    <strong>Giriş Gerekli mi?</strong>
                </label>
            </p>
            
            <p style="background: #f0f6fc; padding: 10px; border-left: 4px solid #0969da;">
                <strong>ℹ️ Not:</strong> BLW test soruları ve diğer araç konfigürasyonları API tarafından varsayılan değerler ile sunulmaktadır.
            </p>
        </div>
        <?php
    }
    
    public function save_tool_meta( $post_id ) {
        // Autosave check
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Post type validation
        if ( get_post_type( $post_id ) !== 'tool' ) {
            return;
        }
        
        // Nonce check
        if ( ! isset( $_POST['kg_tool_settings_nonce'] ) || ! wp_verify_nonce( $_POST['kg_tool_settings_nonce'], 'kg_tool_settings_save' ) ) {
            return;
        }
        
        // Permission check
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Save tool_type
        if ( isset( $_POST['kg_tool_type'] ) ) {
            update_post_meta( $post_id, '_kg_tool_type', sanitize_text_field( $_POST['kg_tool_type'] ) );
        }
        
        // Save tool_icon
        if ( isset( $_POST['kg_tool_icon'] ) ) {
            update_post_meta( $post_id, '_kg_tool_icon', sanitize_text_field( $_POST['kg_tool_icon'] ) );
        }
        
        // Save is_active checkbox
        $is_active = isset( $_POST['kg_is_active'] ) ? '1' : '0';
        update_post_meta( $post_id, '_kg_is_active', $is_active );
        
        // Save requires_auth checkbox
        $requires_auth = isset( $_POST['kg_requires_auth'] ) ? '1' : '0';
        update_post_meta( $post_id, '_kg_requires_auth', $requires_auth );
    }
    
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['kg_sponsored'] = __('Sponsorlu', 'kg-core');
                $new_columns['kg_active'] = __('Aktif', 'kg-core');
            }
        }
        
        return $new_columns;
    }
    
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'kg_sponsored':
                $is_sponsored = get_post_meta($post_id, '_kg_tool_is_sponsored', true);
                if ($is_sponsored) {
                    $sponsor_name = get_post_meta($post_id, '_kg_tool_sponsor_name', true);
                    echo '<span style="color: green; font-weight: bold;">✅ ' . esc_html($sponsor_name ?: 'Evet') . '</span>';
                } else {
                    echo '<span style="color: gray;">—</span>';
                }
                break;
                
            case 'kg_active':
                $is_active = get_post_meta( $post_id, '_kg_is_active', true );
                echo $is_active === '1' ? '<span style="color: green;">✅ Aktif</span>' : '<span style="color: red;">❌ Pasif</span>';
                break;
        }
    }
}
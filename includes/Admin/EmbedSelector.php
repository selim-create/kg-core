<?php
namespace KG_Core\Admin;

class EmbedSelector {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_kg_search_embeddable_content', [$this, 'ajax_search_content']);
        add_action('media_buttons', [$this, 'add_embed_button'], 15);
        add_action('admin_footer', [$this, 'render_embed_modal']);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        global $post_type;
        
        // Only load on post edit screens
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'post') {
            wp_enqueue_style(
                'kg-embed-selector-css',
                KG_CORE_URL . 'assets/css/embed-selector.css',
                [],
                KG_CORE_VERSION
            );
            
            wp_enqueue_script(
                'kg-embed-selector-js',
                KG_CORE_URL . 'assets/js/embed-selector.js',
                ['jquery'],
                KG_CORE_VERSION,
                true
            );
            
            wp_localize_script('kg-embed-selector-js', 'kgEmbedSelector', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kg_embed_selector_nonce'),
                'labels' => [
                    'title' => __('İçerik Embed Et', 'kg-core'),
                    'search' => __('Ara...', 'kg-core'),
                    'select' => __('Seç', 'kg-core'),
                    'selected' => __('Seçildi', 'kg-core'),
                    'insert' => __('Embed Ekle', 'kg-core'),
                    'cancel' => __('İptal', 'kg-core'),
                    'noResults' => __('Sonuç bulunamadı', 'kg-core'),
                    'loading' => __('Yükleniyor...', 'kg-core'),
                    'error' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'kg-core'),
                    'selectedCount' => __('%s öğe seçildi', 'kg-core'),
                ],
            ]);
        }
    }
    
    /**
     * Add embed button to media buttons
     */
    public function add_embed_button($editor_id) {
        global $post_type;
        
        // Only show on post edit screens
        if ($post_type !== 'post') {
            return;
        }
        
        echo '<button type="button" class="button kg-embed-button" id="kg-embed-button">';
        echo '<span class="dashicons dashicons-embed-generic" style="margin-top: 3px;"></span> ';
        echo esc_html__('İçerik Embed Et', 'kg-core');
        echo '</button>';
    }
    
    /**
     * Render embed modal
     */
    public function render_embed_modal() {
        global $post_type;
        
        // Only render on post edit screens
        if ($post_type !== 'post') {
            return;
        }
        ?>
        <div id="kg-embed-modal" class="kg-embed-modal" style="display: none;">
            <div class="kg-embed-modal-content">
                <div class="kg-embed-modal-header">
                    <h2><?php echo esc_html__('İçerik Embed Et', 'kg-core'); ?></h2>
                    <button type="button" class="kg-embed-modal-close">&times;</button>
                </div>
                
                <div class="kg-embed-modal-body">
                    <div class="kg-embed-tabs">
                        <button class="kg-embed-tab active" data-type="recipe"><?php echo esc_html__('Tarifler', 'kg-core'); ?></button>
                        <button class="kg-embed-tab" data-type="ingredient"><?php echo esc_html__('Malzemeler', 'kg-core'); ?></button>
                        <button class="kg-embed-tab" data-type="tool"><?php echo esc_html__('Araçlar', 'kg-core'); ?></button>
                        <button class="kg-embed-tab" data-type="post"><?php echo esc_html__('Keşfet', 'kg-core'); ?></button>
                    </div>
                    
                    <div class="kg-embed-search">
                        <input type="text" id="kg-embed-search-input" placeholder="<?php echo esc_attr__('Ara...', 'kg-core'); ?>" />
                    </div>
                    
                    <div class="kg-embed-results" id="kg-embed-results">
                        <!-- Results will be loaded here -->
                    </div>
                </div>
                
                <div class="kg-embed-modal-footer">
                    <div class="kg-embed-selected-count">
                        <span id="kg-embed-selected-count">0</span> <?php echo esc_html__('öğe seçildi', 'kg-core'); ?>
                    </div>
                    <button type="button" class="button button-primary" id="kg-embed-insert"><?php echo esc_html__('Embed Ekle', 'kg-core'); ?></button>
                    <button type="button" class="button" id="kg-embed-cancel"><?php echo esc_html__('İptal', 'kg-core'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for searching embeddable content
     */
    public function ajax_search_content() {
        check_ajax_referer('kg_embed_selector_nonce', 'nonce');
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'recipe';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $allowed_types = ['recipe', 'ingredient', 'tool', 'post'];
        
        if (!in_array($type, $allowed_types)) {
            wp_send_json_error(['message' => 'Invalid content type']);
            return;
        }
        
        // Query args
        $args = [
            'post_type' => $type,
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $query = new \WP_Query($args);
        $results = [];
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $item = [
                    'id' => $post_id,
                    'title' => html_entity_decode(get_the_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'image' => $this->get_thumbnail_url($post_id),
                    'meta' => $this->get_meta_info($post_id, $type),
                    'icon' => $this->get_type_icon($type),
                ];
                
                $results[] = $item;
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(['items' => $results]);
    }
    
    /**
     * Get thumbnail URL
     */
    private function get_thumbnail_url($post_id) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        
        if ($thumbnail_id) {
            return wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
        }
        
        return '';
    }
    
    /**
     * Get meta information based on content type
     */
    private function get_meta_info($post_id, $type) {
        $meta = '';
        
        switch ($type) {
            case 'recipe':
                $prep_time = get_post_meta($post_id, '_kg_prep_time', true);
                $age_groups = wp_get_post_terms($post_id, 'age-group');
                
                $parts = [];
                if ($prep_time) {
                    $parts[] = $prep_time;
                }
                if (!empty($age_groups)) {
                    $parts[] = html_entity_decode($age_groups[0]->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $meta = implode(' • ', $parts);
                break;
                
            case 'ingredient':
                $start_age = get_post_meta($post_id, '_kg_start_age', true);
                if ($start_age) {
                    $meta = $start_age;
                }
                break;
                
            case 'tool':
                $tool_type = get_post_meta($post_id, '_kg_tool_type', true);
                if ($tool_type) {
                    $meta = $tool_type;
                }
                break;
                
            case 'post':
                $categories = get_the_category($post_id);
                if (!empty($categories)) {
                    $meta = html_entity_decode($categories[0]->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                break;
        }
        
        return $meta;
    }
    
    /**
     * Get icon for content type
     */
    private function get_type_icon($type) {
        $icons = [
            'recipe' => 'dashicons-food',
            'ingredient' => 'dashicons-carrot',
            'tool' => 'dashicons-admin-tools',
            'post' => 'dashicons-format-aside',
        ];
        
        return isset($icons[$type]) ? $icons[$type] : 'dashicons-admin-post';
    }
}

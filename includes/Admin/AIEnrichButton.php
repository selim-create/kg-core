<?php
namespace KG_Core\Admin;

use KG_Core\Services\IngredientGenerator;
use KG_Core\Services\AIService;
use KG_Core\Services\ImageService;

class AIEnrichButton {
    
    public function __construct() {
        add_action('admin_bar_menu', [$this, 'add_admin_bar_button'], 100);
        add_action('admin_footer', [$this, 'render_modal']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_kg_enrich_ingredient', [$this, 'ajax_enrich_ingredient']);
    }
    
    /**
     * Add AI enrich button to admin bar
     */
    public function add_admin_bar_button($wp_admin_bar) {
        global $post;
        
        // Only show on ingredient edit pages
        if (!is_admin() || !$post || get_post_type($post) !== 'ingredient') {
            return;
        }
        
        // Only for users with manage_options capability
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node([
            'id' => 'kg_ai_enrich',
            'title' => '🤖 AI ile Zenginleştir',
            'href' => '#',
            'meta' => [
                'class' => 'kg-ai-enrich-button',
                'onclick' => 'event.preventDefault(); kgShowEnrichModal();'
            ]
        ]);
    }
    
    /**
     * Render enrichment modal
     */
    public function render_modal() {
        global $post;
        
        if (!is_admin() || !$post || get_post_type($post) !== 'ingredient') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div id="kg-enrich-modal" style="display: none;">
            <div class="kg-enrich-modal-overlay"></div>
            <div class="kg-enrich-modal-content">
                <h2>🤖 AI ile Malzeme Zenginleştirme</h2>
                <p>Bu malzeme için AI kullanarak otomatik içerik ve görsel oluşturulacak.</p>
                
                <div class="kg-enrich-options">
                    <label>
                        <input type="checkbox" id="kg_enrich_overwrite" checked>
                        <strong>Mevcut verilerin üzerine yaz</strong>
                        <small>Etkin değilse sadece boş alanlar doldurulur</small>
                    </label>
                    
                    <label>
                        <input type="checkbox" id="kg_enrich_generate_image" checked>
                        <strong>Yeni görsel oluştur</strong>
                        <small>DALL-E 3 veya stock foto API kullanarak yeni görsel ekler</small>
                    </label>
                </div>
                
                <div id="kg-enrich-progress" style="display: none;">
                    <div class="kg-progress-bar">
                        <div class="kg-progress-fill"></div>
                    </div>
                    <p class="kg-progress-text">İşleniyor...</p>
                </div>
                
                <div id="kg-enrich-result" style="display: none;"></div>
                
                <div class="kg-enrich-actions">
                    <button type="button" class="button button-primary" id="kg-enrich-start">Zenginleştir</button>
                    <button type="button" class="button" id="kg-enrich-cancel">İptal</button>
                </div>
            </div>
        </div>
        
        <style>
            .kg-enrich-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                z-index: 100000;
            }
            
            .kg-enrich-modal-content {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                max-width: 500px;
                width: 90%;
                z-index: 100001;
            }
            
            .kg-enrich-modal-content h2 {
                margin-top: 0;
                color: #2271b1;
            }
            
            .kg-enrich-options {
                margin: 20px 0;
                padding: 15px;
                background: #f5f5f5;
                border-radius: 4px;
            }
            
            .kg-enrich-options label {
                display: block;
                margin-bottom: 15px;
            }
            
            .kg-enrich-options label:last-child {
                margin-bottom: 0;
            }
            
            .kg-enrich-options small {
                display: block;
                margin-left: 25px;
                color: #666;
            }
            
            .kg-progress-bar {
                background: #e0e0e0;
                height: 30px;
                border-radius: 5px;
                overflow: hidden;
                margin-bottom: 10px;
            }
            
            .kg-progress-fill {
                background: #2271b1;
                height: 100%;
                width: 0%;
                transition: width 0.3s;
            }
            
            .kg-progress-text {
                text-align: center;
                color: #666;
            }
            
            .kg-enrich-actions {
                margin-top: 20px;
                text-align: right;
            }
            
            .kg-enrich-actions button {
                margin-left: 10px;
            }
            
            #kg-enrich-result {
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
            }
            
            #kg-enrich-result.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            #kg-enrich-result.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
        </style>
        <?php
    }
    
    /**
     * Enqueue scripts for AI enrich functionality
     */
    public function enqueue_scripts($hook) {
        global $post;
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post && get_post_type($post) === 'ingredient') {
            wp_enqueue_script(
                'kg-ai-enrich',
                KG_CORE_URL . 'assets/admin/js/ai-enrich.js',
                ['jquery'],
                KG_CORE_VERSION,
                true
            );
            
            wp_localize_script('kg-ai-enrich', 'kgEnrich', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kg_enrich_nonce'),
                'post_id' => $post->ID
            ]);
        }
    }
    
    /**
     * AJAX handler for ingredient enrichment
     */
    public function ajax_enrich_ingredient() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kg_enrich_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
        $generate_image = isset($_POST['generate_image']) && $_POST['generate_image'] === 'true';
        
        if (!$post_id || get_post_type($post_id) !== 'ingredient') {
            wp_send_json_error('Invalid ingredient');
        }
        
        $post = get_post($post_id);
        $ingredient_name = $post->post_title;
        
        // Generate content with AI
        $ai_service = new AIService();
        $ai_data = $ai_service->generateIngredientContent($ingredient_name);
        
        if (is_wp_error($ai_data)) {
            wp_send_json_error($ai_data->get_error_message());
        }
        
        // Update post content if overwrite is enabled or if fields are empty
        if ($overwrite || empty($post->post_excerpt)) {
            wp_update_post([
                'ID' => $post_id,
                'post_excerpt' => $ai_data['excerpt']
            ]);
        }
        
        if ($overwrite || empty($post->post_content)) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $ai_data['content']
            ]);
        }
        
        // Update meta fields
        $this->updateMetaFields($post_id, $ai_data, $overwrite);
        
        // Generate and attach image if requested
        if ($generate_image) {
            $this->attachImage($post_id, $ai_data, $overwrite);
        }
        
        // Update allergens
        if (!empty($ai_data['allergens']) && is_array($ai_data['allergens'])) {
            $this->assignAllergens($post_id, $ai_data['allergens'], $overwrite);
        }
        
        wp_send_json_success([
            'message' => 'Malzeme başarıyla zenginleştirildi!'
        ]);
    }
    
    /**
     * Update meta fields for ingredient
     */
    private function updateMetaFields($post_id, $data, $overwrite) {
        $meta_fields = [
            'start_age' => '_kg_start_age',
            'category' => '_kg_category',
            'benefits' => '_kg_benefits',
            'allergy_risk' => '_kg_allergy_risk',
            'season' => '_kg_season',
            'storage_tips' => '_kg_storage_tips',
            'selection_tips' => '_kg_selection_tips',
            'pro_tips' => '_kg_pro_tips',
            'preparation_tips' => '_kg_preparation_tips'
        ];
        
        foreach ($meta_fields as $key => $meta_key) {
            if (isset($data[$key])) {
                $current_value = get_post_meta($post_id, $meta_key, true);
                
                if ($overwrite || empty($current_value)) {
                    update_post_meta($post_id, $meta_key, $data[$key]);
                }
            }
        }
        
        // Array fields
        $array_fields = [
            'prep_methods' => '_kg_prep_methods',
            'prep_by_age' => '_kg_prep_by_age',
            'pairings' => '_kg_pairings',
            'faq' => '_kg_faq'
        ];
        
        foreach ($array_fields as $key => $meta_key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $current_value = get_post_meta($post_id, $meta_key, true);
                
                if ($overwrite || empty($current_value)) {
                    update_post_meta($post_id, $meta_key, $data[$key]);
                }
            }
        }
        
        // Nutrition fields
        if (isset($data['nutrition']) && is_array($data['nutrition'])) {
            $nutrition_fields = [
                'calories' => '_kg_calories',
                'protein' => '_kg_protein',
                'carbs' => '_kg_carbs',
                'fat' => '_kg_fat',
                'fiber' => '_kg_fiber',
                'vitamins' => '_kg_vitamins'
            ];
            
            foreach ($nutrition_fields as $key => $meta_key) {
                if (isset($data['nutrition'][$key])) {
                    $current_value = get_post_meta($post_id, $meta_key, true);
                    
                    if ($overwrite || empty($current_value)) {
                        update_post_meta($post_id, $meta_key, $data['nutrition'][$key]);
                    }
                }
            }
        }
    }
    
    /**
     * Attach featured image to ingredient post
     */
    private function attachImage($post_id, $data, $overwrite) {
        // Check if post already has featured image
        if (!$overwrite && has_post_thumbnail($post_id)) {
            return;
        }
        
        if (empty($data['image_search_query'])) {
            return;
        }
        
        $image_service = new ImageService();
        $query = $data['image_search_query'];
        
        // Fetch image from API
        $image_data = $image_service->fetchImage($query);
        
        if ($image_data === null) {
            error_log("KG Core: No image found for query: {$query}");
            return;
        }
        
        // Use actual post title for consistency
        $filename = sanitize_title(get_the_title($post_id));
        
        // Download to media library
        $attachment_id = $image_service->downloadToMediaLibrary($image_data['url'], $filename);
        
        if (is_wp_error($attachment_id)) {
            error_log('KG Core Image Download Error: ' . $attachment_id->get_error_message());
            return;
        }
        
        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);
        
        // Save credit information
        update_post_meta($post_id, '_kg_image_credit', $image_data['credit']);
        update_post_meta($post_id, '_kg_image_credit_url', $image_data['credit_url']);
        update_post_meta($post_id, '_kg_image_source', $image_data['source']);
    }
    
    /**
     * Assign allergen taxonomies to ingredient
     */
    private function assignAllergens($post_id, $allergens, $overwrite) {
        // Check if post already has allergens
        $existing_allergens = wp_get_post_terms($post_id, 'allergen');
        
        if (!$overwrite && !empty($existing_allergens) && !is_wp_error($existing_allergens)) {
            return;
        }
        
        $term_ids = [];
        
        foreach ($allergens as $allergen_name) {
            $allergen_name = sanitize_text_field($allergen_name);
            
            // Check if term exists
            $term = get_term_by('name', $allergen_name, 'allergen');
            
            if (!$term) {
                // Create new term if it doesn't exist
                $result = wp_insert_term($allergen_name, 'allergen');
                if (!is_wp_error($result)) {
                    $term_ids[] = $result['term_id'];
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }
        
        if (!empty($term_ids)) {
            wp_set_post_terms($post_id, $term_ids, 'allergen');
        }
    }
}

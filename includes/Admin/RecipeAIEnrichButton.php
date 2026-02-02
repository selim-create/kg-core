<?php
namespace KG_Core\Admin;

use KG_Core\Services\AIService;

class RecipeAIEnrichButton {
    
    public function __construct() {
        add_action('admin_bar_menu', [$this, 'add_admin_bar_button'], 100);
        add_action('admin_footer', [$this, 'render_modal']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_kg_ai_enrich_recipe', [$this, 'ajax_enrich_recipe']);
    }
    
    /**
     * Add AI enrich button to admin bar
     */
    public function add_admin_bar_button($wp_admin_bar) {
        global $post;
        
        // Only show on recipe edit pages
        if (!is_admin() || !$post || get_post_type($post) !== 'recipe') {
            return;
        }
        
        // Only for users with manage_options capability
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node([
            'id' => 'kg_ai_enrich_recipe',
            'title' => '🤖 AI ile Zenginleştir',
            'href' => '#',
            'meta' => [
                'class' => 'kg-ai-enrich-recipe-button',
                'onclick' => 'event.preventDefault(); kgShowRecipeEnrichModal();'
            ]
        ]);
    }
    
    /**
     * Render enrichment modal
     */
    public function render_modal() {
        global $post;
        
        if (!is_admin() || !$post || get_post_type($post) !== 'recipe') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div id="kg-recipe-enrich-modal" style="display: none;">
            <div class="kg-enrich-modal-overlay"></div>
            <div class="kg-enrich-modal-content">
                <h2>🤖 AI ile Tarif Zenginleştirme</h2>
                <p>Bu tarif için AI kullanarak otomatik içerik oluşturulacak.</p>
                
                <div class="kg-enrich-options">
                    <label>
                        <input type="checkbox" id="kg_recipe_enrich_fill_empty" checked>
                        <strong>Sadece boş alanları doldur</strong>
                        <small>Etkin değilse tüm alanlar üzerine yazılır</small>
                    </label>
                </div>
                
                <div id="kg-recipe-enrich-progress" style="display: none;">
                    <div class="kg-progress-bar">
                        <div class="kg-progress-fill"></div>
                    </div>
                    <p class="kg-progress-text">İşleniyor...</p>
                </div>
                
                <div id="kg-recipe-enrich-result" style="display: none;"></div>
                
                <div class="kg-enrich-actions">
                    <button type="button" class="button button-primary" id="kg-recipe-enrich-start">Zenginleştir</button>
                    <button type="button" class="button" id="kg-recipe-enrich-cancel">İptal</button>
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
            
            #kg-recipe-enrich-result {
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
            }
            
            #kg-recipe-enrich-result.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            #kg-recipe-enrich-result.error {
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
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post && get_post_type($post) === 'recipe') {
            wp_enqueue_script(
                'kg-recipe-ai-enrich',
                KG_CORE_URL . 'assets/admin/js/recipe-enrich.js',
                ['jquery'],
                KG_CORE_VERSION,
                true
            );
            
            wp_localize_script('kg-recipe-ai-enrich', 'kgRecipeEnrich', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kg_recipe_enrich_nonce'),
                'post_id' => $post->ID
            ]);
        }
    }
    
    /**
     * AJAX handler for recipe enrichment
     */
    public function ajax_enrich_recipe() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kg_recipe_enrich_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $fill_empty_only = isset($_POST['fill_empty_only']) && $_POST['fill_empty_only'] === 'true';
        
        if (!$post_id || get_post_type($post_id) !== 'recipe') {
            wp_send_json_error('Invalid recipe');
        }
        
        $post = get_post($post_id);
        
        // Collect recipe data
        $recipe_data = [
            'title' => $post->post_title,
            'content' => $post->post_content,
            'ingredients' => get_post_meta($post_id, '_kg_ingredients', true),
            'instructions' => get_post_meta($post_id, '_kg_instructions', true)
        ];
        
        // Generate content with AI
        $ai_service = new AIService();
        $ai_data = $ai_service->generateRecipeContent($recipe_data);
        
        if (is_wp_error($ai_data)) {
            wp_send_json_error($ai_data->get_error_message());
        }
        
        // Update fields
        $this->updateFields($post_id, $ai_data, $fill_empty_only);
        
        wp_send_json_success([
            'message' => 'Tarif başarıyla zenginleştirildi!'
        ]);
    }
    
    /**
     * Update recipe fields
     */
    private function updateFields($post_id, $data, $fill_empty_only) {
        $post = get_post($post_id);
        
        // Update post content if empty or not fill_empty_only mode
        if (isset($data['content']) && (!$fill_empty_only || empty($post->post_content))) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => wp_kses_post($data['content'])
            ]);
        }
        
        // Update meta fields
        $meta_fields = [
            'prep_time' => '_kg_prep_time',
            'cook_time' => '_kg_cook_time',
            'serving_size' => '_kg_serving_size',
            'difficulty' => '_kg_difficulty',
            'freezable' => '_kg_freezable',
            'storage_info' => '_kg_storage_info',
            'substitutes' => '_kg_substitutes',
            'special_notes' => '_kg_special_notes',
        ];
        
        foreach ($meta_fields as $key => $meta_key) {
            if (isset($data[$key])) {
                $current_value = get_post_meta($post_id, $meta_key, true);
                
                if (!$fill_empty_only || empty($current_value)) {
                    $value = $data[$key];
                    
                    // Handle boolean for freezable
                    if ($key === 'freezable') {
                        $value = $value ? '1' : '0';
                    }
                    
                    if (is_array($value)) {
                        update_post_meta($post_id, $meta_key, $value);
                    } else {
                        update_post_meta($post_id, $meta_key, sanitize_textarea_field($value));
                    }
                }
            }
        }
        
        // Update nutrition fields
        if (isset($data['nutrition']) && is_array($data['nutrition'])) {
            $nutrition_fields = [
                'calories' => '_kg_calories',
                'protein' => '_kg_protein',
                'carbs' => '_kg_carbs',
                'fat' => '_kg_fat',
                'fiber' => '_kg_fiber',
                'sugar' => '_kg_sugar',
                'sodium' => '_kg_sodium',
                'vitamins' => '_kg_vitamins',
                'minerals' => '_kg_minerals',
            ];
            
            foreach ($nutrition_fields as $key => $meta_key) {
                if (isset($data['nutrition'][$key])) {
                    $current_value = get_post_meta($post_id, $meta_key, true);
                    
                    if (!$fill_empty_only || empty($current_value)) {
                        update_post_meta($post_id, $meta_key, sanitize_text_field($data['nutrition'][$key]));
                    }
                }
            }
        }
        
        // Update taxonomies
        $taxonomies = [
            'age_groups' => 'age-group',
            'allergens' => 'allergen',
            'diet_types' => 'diet-type',
            'meal_types' => 'meal-type',
            'special_conditions' => 'special-condition'
        ];
        
        foreach ($taxonomies as $data_key => $taxonomy) {
            if (isset($data[$data_key]) && is_array($data[$data_key])) {
                $current_terms = wp_get_post_terms($post_id, $taxonomy);
                
                if (!$fill_empty_only || empty($current_terms) || is_wp_error($current_terms)) {
                    $this->assignTaxonomy($post_id, $taxonomy, $data[$data_key]);
                }
            }
        }
        
        // Update RankMath SEO fields
        if (isset($data['seo']) && is_array($data['seo'])) {
            $seo_fields = [
                'focus_keyword' => 'rank_math_focus_keyword',
                'title' => 'rank_math_title',
                'description' => 'rank_math_description'
            ];
            
            foreach ($seo_fields as $key => $meta_key) {
                if (isset($data['seo'][$key])) {
                    $current_value = get_post_meta($post_id, $meta_key, true);
                    
                    if (!$fill_empty_only || empty($current_value)) {
                        update_post_meta($post_id, $meta_key, sanitize_text_field($data['seo'][$key]));
                    }
                }
            }
        }
    }
    
    /**
     * Assign taxonomy terms to recipe
     */
    private function assignTaxonomy($post_id, $taxonomy, $terms_data) {
        if (empty($terms_data) || !is_array($terms_data)) {
            return;
        }
        
        $term_ids = [];
        
        foreach ($terms_data as $term_name) {
            $term_name = sanitize_text_field($term_name);
            
            // Try to find existing term by slug or name
            $term = get_term_by('slug', sanitize_title($term_name), $taxonomy);
            if (!$term) {
                $term = get_term_by('name', $term_name, $taxonomy);
            }
            
            // For allergens and special-conditions, allow creating new terms
            if (!$term && in_array($taxonomy, ['allergen', 'special-condition'])) {
                $result = wp_insert_term($term_name, $taxonomy);
                if (!is_wp_error($result)) {
                    $term_ids[] = $result['term_id'];
                }
            } elseif ($term) {
                $term_ids[] = $term->term_id;
            }
        }
        
        if (!empty($term_ids)) {
            wp_set_post_terms($post_id, $term_ids, $taxonomy);
        }
    }
}

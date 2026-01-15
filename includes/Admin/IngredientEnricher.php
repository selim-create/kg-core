<?php
namespace KG_Core\Admin;

class IngredientEnricher {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_enrichment_metabox']);
        add_action('wp_ajax_kg_enrich_ingredient', [$this, 'ajax_enrich_ingredient']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    // Ingredient edit sayfasına "AI ile Zenginleştir" butonu ekle
    public function add_enrichment_metabox() {
        add_meta_box(
            'kg_ingredient_enrichment',
            '🤖 AI Zenginleştirme',
            [$this, 'render_enrichment_box'],
            'ingredient',
            'side',
            'high'
        );
    }

    public function render_enrichment_box($post) {
        // Eksik alan sayısını hesapla
        $missing_fields = $this->get_missing_fields($post->ID);
        $missing_count = count($missing_fields);
        
        wp_nonce_field('kg_enrich_ingredient', 'kg_enrich_nonce');
        ?>
        <div class="kg-enrichment-box">
            <?php if ($missing_count > 0): ?>
                <p style="color: #d63638;">
                    <strong><?php echo $missing_count; ?> eksik alan</strong> bulundu
                </p>
                <ul style="font-size: 11px; margin-left: 15px;">
                    <?php foreach (array_slice($missing_fields, 0, 5) as $field): ?>
                        <li><?php echo esc_html($field); ?></li>
                    <?php endforeach; ?>
                    <?php if ($missing_count > 5): ?>
                        <li>... ve <?php echo ($missing_count - 5); ?> alan daha</li>
                    <?php endif; ?>
                </ul>
                <button type="button" id="kg-enrich-btn" class="button button-primary" style="width: 100%; margin-top: 10px;" data-mode="missing">
                    🤖 Eksik Alanları Doldur
                </button>
            <?php else: ?>
                <p style="color: #00a32a;">✅ Tüm alanlar dolu</p>
                <button type="button" id="kg-enrich-btn" class="button" style="width: 100%; margin-top: 10px;" data-mode="all">
                    🔄 Yeniden Oluştur
                </button>
            <?php endif; ?>
            <div id="kg-enrich-status" style="margin-top: 10px;"></div>
        </div>
        <?php
    }

    // Eksik alanları tespit et
    private function get_missing_fields($post_id) {
        $required_fields = [
            '_kg_start_age' => 'Başlangıç Yaşı',
            '_kg_benefits' => 'Faydaları',
            '_kg_allergy_risk' => 'Alerji Riski',
            '_kg_season' => 'Mevsim',
            '_kg_storage_tips' => 'Saklama Koşulları',
            '_kg_preparation_tips' => 'Hazırlama İpuçları',
            '_kg_selection_tips' => 'Seçim İpuçları',
            '_kg_pro_tips' => 'Püf Noktaları',
            '_kg_prep_methods' => 'Hazırlama Yöntemleri',
            '_kg_prep_by_age' => 'Yaşa Göre Hazırlama',
            '_kg_pairings' => 'Uyumlu İkililer',
            '_kg_ing_calories_100g' => 'Kalori (100g)',
            '_kg_ing_protein_100g' => 'Protein (100g)',
            '_kg_faq' => 'SSS',
        ];

        $missing = [];
        foreach ($required_fields as $key => $label) {
            $value = get_post_meta($post_id, $key, true);
            if (empty($value) || (is_array($value) && count($value) === 0)) {
                $missing[$key] = $label;
            }
        }

        // Taxonomy kontrolü
        $category_terms = wp_get_post_terms($post_id, 'ingredient-category');
        if (empty($category_terms) || is_wp_error($category_terms)) {
            $missing['ingredient-category'] = 'Malzeme Kategorisi';
        }

        return $missing;
    }

    // AJAX: Sadece eksik alanları AI ile doldur
    public function ajax_enrich_ingredient() {
        check_ajax_referer('kg_enrich_ingredient', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Yetkiniz yok']);
        }

        $post_id = intval($_POST['post_id']);
        $force_all = isset($_POST['force_all']) && $_POST['force_all'] === 'true';

        if (!$post_id) {
            wp_send_json_error(['message' => 'Geçersiz post ID']);
        }

        $ingredient_name = get_the_title($post_id);
        
        // AI Service'i çağır
        if (!class_exists('\KG_Core\Services\AIService')) {
            wp_send_json_error(['message' => 'AI Service bulunamadı']);
        }

        $ai_service = new \KG_Core\Services\AIService();
        $ai_data = $ai_service->generateIngredientContent($ingredient_name);

        if (is_wp_error($ai_data)) {
            wp_send_json_error(['message' => $ai_data->get_error_message()]);
        }

        $updated_fields = [];
        $missing_fields = $this->get_missing_fields($post_id);

        // Sadece eksik alanları güncelle (force_all değilse)
        if (!$force_all) {
            foreach ($missing_fields as $key => $label) {
                $updated = $this->update_single_field($post_id, $key, $ai_data);
                if ($updated) {
                    $updated_fields[] = $label;
                }
            }
        } else {
            // Tüm alanları güncelle
            $updated_fields = $this->update_all_fields($post_id, $ai_data);
        }

        wp_send_json_success([
            'message' => count($updated_fields) . ' alan güncellendi',
            'updated_fields' => $updated_fields
        ]);
    }

    private function update_single_field($post_id, $key, $ai_data) {
        // Field mapping ve güncelleme logic'i
        $mapping = [
            '_kg_start_age' => 'start_age',
            '_kg_benefits' => 'benefits',
            '_kg_allergy_risk' => 'allergy_risk',
            '_kg_season' => 'season',
            '_kg_storage_tips' => 'storage_tips',
            '_kg_preparation_tips' => 'preparation_tips',
            '_kg_selection_tips' => 'selection_tips',
            '_kg_pro_tips' => 'pro_tips',
            '_kg_prep_methods' => 'prep_methods',
            '_kg_prep_by_age' => 'prep_by_age',
            '_kg_pairings' => 'pairings',
            '_kg_faq' => 'faq',
        ];

        // Handle taxonomy
        if ($key === 'ingredient-category' && !empty($ai_data['category'])) {
            $term = get_term_by('name', $ai_data['category'], 'ingredient-category');
            if (!$term) {
                $term = get_term_by('slug', sanitize_title($ai_data['category']), 'ingredient-category');
            }
            if ($term) {
                wp_set_post_terms($post_id, [$term->term_id], 'ingredient-category');
                return true;
            }
            return false;
        }

        // Handle nutrition fields
        if (strpos($key, '_kg_ing_') === 0 && isset($ai_data['nutrition'])) {
            $nutrition_mapping = [
                '_kg_ing_calories_100g' => 'calories',
                '_kg_ing_protein_100g' => 'protein',
                '_kg_ing_carbs_100g' => 'carbs',
                '_kg_ing_fat_100g' => 'fat',
                '_kg_ing_fiber_100g' => 'fiber',
                '_kg_ing_sugar_100g' => 'sugar',
                '_kg_ing_vitamins' => 'vitamins',
                '_kg_ing_minerals' => 'minerals',
            ];

            if (isset($nutrition_mapping[$key]) && isset($ai_data['nutrition'][$nutrition_mapping[$key]])) {
                update_post_meta($post_id, $key, sanitize_text_field($ai_data['nutrition'][$nutrition_mapping[$key]]));
                return true;
            }
            return false;
        }

        // Handle regular fields
        if (isset($mapping[$key]) && isset($ai_data[$mapping[$key]])) {
            $value = $ai_data[$mapping[$key]];
            
            // Sanitize based on type
            if (is_array($value)) {
                update_post_meta($post_id, $key, $value);
            } else {
                update_post_meta($post_id, $key, sanitize_textarea_field($value));
            }
            return true;
        }

        return false;
    }

    private function update_all_fields($post_id, $ai_data) {
        // Use IngredientGenerator's saveMetaFields logic
        if (!class_exists('\KG_Core\Services\IngredientGenerator')) {
            return ['Error: IngredientGenerator not found'];
        }

        $generator = new \KG_Core\Services\IngredientGenerator();
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('saveMetaFields');
        $method->setAccessible(true);
        $method->invoke($generator, $post_id, $ai_data);

        // Also update category
        if (!empty($ai_data['category'])) {
            $assignCategory = $reflection->getMethod('assignCategory');
            $assignCategory->setAccessible(true);
            $assignCategory->invoke($generator, $post_id, $ai_data['category']);
        }

        return ['Tüm alanlar'];
    }

    public function enqueue_scripts($hook) {
        global $post_type;
        if ($hook !== 'post.php' || $post_type !== 'ingredient') {
            return;
        }

        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                $('#kg-enrich-btn').on('click', function() {
                    var btn = $(this);
                    var statusDiv = $('#kg-enrich-status');
                    var originalText = btn.text();
                    var mode = btn.data('mode');
                    
                    btn.prop('disabled', true).text('İşleniyor...');
                    statusDiv.html('<span style=\"color: #2271b1;\">⏳ AI içerik oluşturuluyor...</span>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kg_enrich_ingredient',
                            nonce: $('#kg_enrich_nonce').val(),
                            post_id: $('#post_ID').val(),
                            force_all: mode === 'all' ? 'true' : 'false'
                        },
                        success: function(response) {
                            if (response.success) {
                                statusDiv.html('<span style=\"color: #00a32a;\">✅ ' + response.data.message + '</span>');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                statusDiv.html('<span style=\"color: #d63638;\">❌ ' + response.data.message + '</span>');
                                btn.prop('disabled', false).text(originalText);
                            }
                        },
                        error: function() {
                            statusDiv.html('<span style=\"color: #d63638;\">❌ Bağlantı hatası</span>');
                            btn.prop('disabled', false).text(originalText);
                        }
                    });
                });
            });
        ");
    }
}

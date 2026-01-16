<?php
namespace KG_Core\Admin;

class IngredientEnricher {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_enrichment_metabox']);
        add_action('wp_ajax_kg_enrich_ingredient', [$this, 'ajax_enrich_ingredient']);
        add_action('wp_ajax_kg_full_enrich_ingredient', [$this, 'ajax_full_enrich_ingredient']);
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
        
        // Nonce değerini doğrudan al
        $nonce = wp_create_nonce('kg_enrich_ingredient');
        ?>
        <div class="kg-enrichment-box">
            <input type="hidden" id="kg_enricher_nonce" value="<?php echo esc_attr($nonce); ?>">
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
            <?php else: ?>
                <p style="color: #00a32a;">✅ Tüm alanlar dolu</p>
            <?php endif; ?>
            
            <button type="button" id="kg-enrich-missing-btn" class="button button-primary" style="width: 100%; margin-top: 10px;">
                🤖 Eksik Alanları Doldur
            </button>
            <button type="button" id="kg-enrich-all-btn" class="button" style="width: 100%; margin-top: 5px;">
                ✨ Zenginleştir
            </button>
            
            <div id="kg-enrich-status" style="margin-top: 10px;"></div>
        </div>
        <?php
    }

    // Eksik alanları tespit et
    private function get_missing_fields($post_id) {
        $required_fields = [
            // Temel
            '_kg_start_age' => 'Başlangıç Yaşı',
            '_kg_benefits' => 'Faydaları',
            '_kg_allergy_risk' => 'Alerji Riski',
            
            // Alerjen Bilgileri (opsiyonel - sadece alerjen malzemeler için)
            '_kg_cross_contamination' => 'Çapraz Bulaşma Riski',
            '_kg_allergy_symptoms' => 'Alerji Semptomları',
            '_kg_alternatives' => 'Alternatif Malzemeler',
            
            // Besin Değerleri (100g)
            '_kg_ing_calories_100g' => 'Kalori (100g)',
            '_kg_ing_protein_100g' => 'Protein (100g)',
            '_kg_ing_carbs_100g' => 'Karbonhidrat (100g)',
            '_kg_ing_fat_100g' => 'Yağ (100g)',
            '_kg_ing_fiber_100g' => 'Lif (100g)',
            '_kg_ing_sugar_100g' => 'Şeker (100g)',
            '_kg_ing_vitamins' => 'Vitaminler',
            '_kg_ing_minerals' => 'Mineraller',
            
            // Hazırlama
            '_kg_prep_methods' => 'Hazırlama Yöntemleri',
            '_kg_preparation_tips' => 'Hazırlama İpuçları',
            '_kg_selection_tips' => 'Seçim İpuçları',
            '_kg_pro_tips' => 'Püf Noktaları',
            '_kg_prep_by_age' => 'Yaşa Göre Hazırlama',
            
            // Diğer
            '_kg_pairings' => 'Uyumlu İkililer',
            '_kg_season' => 'Mevsim',
            '_kg_storage_tips' => 'Saklama Koşulları',
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
        try {
            check_ajax_referer('kg_enrich_ingredient', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Yetkiniz yok']);
                return;
            }

            $post_id = intval($_POST['post_id']);

            if (!$post_id) {
                wp_send_json_error(['message' => 'Geçersiz post ID']);
                return;
            }

            $ingredient_name = get_the_title($post_id);
            
            // AI Service'i çağır
            if (!class_exists('\KG_Core\Services\AIService')) {
                wp_send_json_error(['message' => 'AI Service bulunamadı']);
                return;
            }

            $ai_service = new \KG_Core\Services\AIService();
            $ai_data = $ai_service->generateIngredientContent($ingredient_name);

            if (is_wp_error($ai_data)) {
                wp_send_json_error([
                    'message' => 'AI hatası: ' . $ai_data->get_error_message(),
                    'error_code' => $ai_data->get_error_code()
                ]);
                return;
            }

            $updated_fields = [];
            $missing_fields = $this->get_missing_fields($post_id);

            // Sadece eksik alanları güncelle
            foreach ($missing_fields as $key => $label) {
                $updated = $this->update_single_field($post_id, $key, $ai_data);
                if ($updated) {
                    $updated_fields[] = $label;
                }
            }

            wp_send_json_success([
                'message' => count($updated_fields) . ' alan güncellendi',
                'updated_fields' => $updated_fields
            ]);
        } catch (\Exception $e) {
            error_log('KG Enrichment Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Zenginleştirme hatası: ' . $e->getMessage()
            ]);
        }
    }
    
    // AJAX: Tüm alanları zenginleştir (Title hariç)
    public function ajax_full_enrich_ingredient() {
        try {
            check_ajax_referer('kg_enrich_ingredient', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Yetkiniz yok']);
                return;
            }

            $post_id = intval($_POST['post_id']);

            if (!$post_id) {
                wp_send_json_error(['message' => 'Geçersiz post ID']);
                return;
            }

            $ingredient_name = get_the_title($post_id);
            
            // AI Service'i çağır
            if (!class_exists('\KG_Core\Services\AIService')) {
                wp_send_json_error(['message' => 'AI Service bulunamadı']);
                return;
            }

            $ai_service = new \KG_Core\Services\AIService();
            $ai_data = $ai_service->generateIngredientContent($ingredient_name);

            if (is_wp_error($ai_data)) {
                wp_send_json_error([
                    'message' => 'AI hatası: ' . $ai_data->get_error_message(),
                    'error_code' => $ai_data->get_error_code()
                ]);
                return;
            }

            // Update post content (description) - Title stays the same
            if (isset($ai_data['content'])) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => wp_kses_post($ai_data['content']),
                    'post_excerpt' => isset($ai_data['excerpt']) ? sanitize_text_field($ai_data['excerpt']) : ''
                ]);
            }

            // Update all meta fields using IngredientGenerator's logic
            $updated_fields = $this->update_all_fields($post_id, $ai_data);

            // Update RankMath SEO fields
            if (isset($ai_data['seo'])) {
                if (!empty($ai_data['seo']['title'])) {
                    update_post_meta($post_id, 'rank_math_title', sanitize_text_field($ai_data['seo']['title']));
                }
                if (!empty($ai_data['seo']['description'])) {
                    update_post_meta($post_id, 'rank_math_description', sanitize_text_field($ai_data['seo']['description']));
                }
                if (!empty($ai_data['seo']['focus_keyword'])) {
                    update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($ai_data['seo']['focus_keyword']));
                }
            }

            wp_send_json_success([
                'message' => 'İçerik başarıyla zenginleştirildi',
                'updated_fields' => $updated_fields
            ]);
        } catch (\Exception $e) {
            error_log('KG Full Enrichment Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Zenginleştirme hatası: ' . $e->getMessage()
            ]);
        }
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
            
            // Alerjen bilgileri
            '_kg_cross_contamination' => 'cross_contamination',
            '_kg_allergy_symptoms' => 'allergy_symptoms',
            '_kg_alternatives' => 'alternatives',
            
            // Array/JSON alanları
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

        // Array alanları için özel işleme
        $array_fields = ['_kg_prep_methods', '_kg_prep_by_age', '_kg_pairings', '_kg_faq', '_kg_season'];
        if (in_array($key, $array_fields)) {
            $ai_key = $mapping[$key] ?? null;
            
            // Debug log for pairings
            if ($key === '_kg_pairings') {
                error_log('KG Enricher: Attempting to save pairings. ai_key=' . $ai_key . ', isset=' . (isset($ai_data[$ai_key]) ? 'yes' : 'no'));
                if (isset($ai_data[$ai_key])) {
                    error_log('KG Enricher: pairings data = ' . print_r($ai_data[$ai_key], true));
                }
            }
            
            if ($ai_key && isset($ai_data[$ai_key]) && is_array($ai_data[$ai_key]) && !empty($ai_data[$ai_key])) {
                update_post_meta($post_id, $key, $ai_data[$ai_key]);
                return true;
            }
            return false;
        }

        // Handle regular fields
        if (isset($mapping[$key]) && isset($ai_data[$mapping[$key]])) {
            $value = $ai_data[$mapping[$key]];
            
            // Skip empty allergen fields (normal for non-allergen ingredients)
            if (in_array($key, ['_kg_cross_contamination', '_kg_allergy_symptoms', '_kg_alternatives']) && empty($value)) {
                return false;
            }
            
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
        
        // Call protected methods directly (accessible from same package)
        $generator->saveMetaFields($post_id, $ai_data);

        // Also update category
        if (!empty($ai_data['category'])) {
            $generator->assignCategory($post_id, $ai_data['category']);
        }

        return ['Tüm alanlar'];
    }

    public function enqueue_scripts($hook) {
        global $post_type, $post;
        if ($hook !== 'post.php' || $post_type !== 'ingredient') {
            return;
        }

        // Register and enqueue script with localized data
        wp_register_script('kg-enricher-script', '', ['jquery'], '1.0', true);
        wp_enqueue_script('kg-enricher-script');
        
        // Localize script with nonce and post ID
        wp_localize_script('kg-enricher-script', 'kgEnricher', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kg_enrich_ingredient'),
            'postId' => $post ? $post->ID : 0
        ]);

        wp_add_inline_script('kg-enricher-script', "
            jQuery(document).ready(function($) {
                // Eksik Alanları Doldur butonu
                $('#kg-enrich-missing-btn').on('click', function() {
                    var btn = $(this);
                    var statusDiv = $('#kg-enrich-status');
                    var originalText = btn.text();
                    
                    btn.prop('disabled', true).text('İşleniyor...');
                    $('#kg-enrich-all-btn').prop('disabled', true);
                    statusDiv.html('<span style=\"color: #2271b1;\">⏳ Eksik alanlar AI ile doldruluyor...</span>');
                    
                    $.ajax({
                        url: kgEnricher.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'kg_enrich_ingredient',
                            nonce: kgEnricher.nonce,
                            post_id: kgEnricher.postId
                        },
                        success: function(response) {
                            if (response && response.success && response.data) {
                                var message = response.data.message || 'Güncellendi';
                                statusDiv.html('<span style=\"color: #00a32a;\">✅ ' + message + '</span>');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Bilinmeyen hata';
                                statusDiv.html('<span style=\"color: #d63638;\">❌ ' + errorMsg + '</span>');
                                btn.prop('disabled', false).text(originalText);
                                $('#kg-enrich-all-btn').prop('disabled', false);
                            }
                        },
                        error: function(xhr, status, error) {
                            var errorMsg = 'Bağlantı hatası';
                            if (xhr && xhr.responseText) {
                                try {
                                    var resp = JSON.parse(xhr.responseText);
                                    if (resp.data && resp.data.message) {
                                        errorMsg = resp.data.message;
                                    }
                                } catch (parseError) {
                                    // JSON parse failed, use raw response if available
                                    if (xhr.responseText.length < 100) {
                                        errorMsg = 'Sunucu hatası: ' + xhr.responseText;
                                    }
                                }
                            }
                            statusDiv.html('<span style=\"color: #d63638;\">❌ ' + errorMsg + '</span>');
                            btn.prop('disabled', false).text(originalText);
                            $('#kg-enrich-all-btn').prop('disabled', false);
                        }
                    });
                });
                
                // Zenginleştir butonu
                $('#kg-enrich-all-btn').on('click', function() {
                    var btn = $(this);
                    var statusDiv = $('#kg-enrich-status');
                    var originalText = btn.text();
                    
                    btn.prop('disabled', true).text('İşleniyor...');
                    $('#kg-enrich-missing-btn').prop('disabled', true);
                    statusDiv.html('<span style=\"color: #2271b1;\">⏳ İçerik AI ile zenginleştiriliyor...</span>');
                    
                    $.ajax({
                        url: kgEnricher.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'kg_full_enrich_ingredient',
                            nonce: kgEnricher.nonce,
                            post_id: kgEnricher.postId
                        },
                        success: function(response) {
                            if (response && response.success && response.data) {
                                var message = response.data.message || 'Zenginleştirildi';
                                statusDiv.html('<span style=\"color: #00a32a;\">✅ ' + message + '</span>');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Bilinmeyen hata';
                                statusDiv.html('<span style=\"color: #d63638;\">❌ ' + errorMsg + '</span>');
                                btn.prop('disabled', false).text(originalText);
                                $('#kg-enrich-missing-btn').prop('disabled', false);
                            }
                        },
                        error: function(xhr, status, error) {
                            var errorMsg = 'Bağlantı hatası';
                            if (xhr && xhr.responseText) {
                                try {
                                    var resp = JSON.parse(xhr.responseText);
                                    if (resp.data && resp.data.message) {
                                        errorMsg = resp.data.message;
                                    }
                                } catch (parseError) {
                                    // JSON parse failed, use raw response if available
                                    if (xhr.responseText.length < 100) {
                                        errorMsg = 'Sunucu hatası: ' + xhr.responseText;
                                    }
                                }
                            }
                            statusDiv.html('<span style=\"color: #d63638;\">❌ ' + errorMsg + '</span>');
                            btn.prop('disabled', false).text(originalText);
                            $('#kg-enrich-missing-btn').prop('disabled', false);
                        }
                    });
                });
            });
        ");
    }
}

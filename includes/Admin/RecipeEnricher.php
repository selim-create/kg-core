<?php
namespace KG_Core\Admin;

class RecipeEnricher {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_enrichment_metabox']);
        add_action('wp_ajax_kg_enrich_recipe', [$this, 'ajax_enrich_recipe']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    // Recipe edit sayfasına "AI ile Zenginleştir" butonu ekle
    public function add_enrichment_metabox() {
        add_meta_box(
            'kg_recipe_enrichment',
            '🤖 AI Zenginleştirme',
            [$this, 'render_enrichment_box'],
            'recipe',
            'side',
            'high'
        );
    }

    public function render_enrichment_box($post) {
        // Eksik alan sayısını hesapla
        $missing_fields = $this->get_missing_fields($post->ID);
        $missing_count = count($missing_fields);
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
            <?php else: ?>
                <p style="color: #00a32a;">✅ Tüm alanlar dolu</p>
            <?php endif; ?>
            
            <button type="button" id="kg-enrich-missing-btn" class="button button-primary" style="width: 100%; margin-top: 10px;">
                🤖 Boşsa Zenginleştir
            </button>
            
            <div id="kg-enrich-status" style="margin-top: 10px;"></div>
        </div>
        <?php
    }

    // Eksik alanları tespit et
    private function get_missing_fields($post_id) {
        $required_fields = [
            // Temel Bilgiler
            '_kg_prep_time' => 'Hazırlama Süresi',
            '_kg_cook_time' => 'Pişirme Süresi',
            '_kg_serving_size' => 'Porsiyon Bilgisi',
            '_kg_difficulty' => 'Zorluk Seviyesi',
            '_kg_freezable' => 'Dondurulabilir mi',
            '_kg_storage_info' => 'Saklama Bilgisi',
            
            // İkame Malzemeler
            '_kg_substitutes' => 'İkame Malzemeler',
            
            // Beslenme Değerleri
            '_kg_calories' => 'Kalori',
            '_kg_protein' => 'Protein',
            '_kg_carbs' => 'Karbonhidrat',
            '_kg_fat' => 'Yağ',
            '_kg_fiber' => 'Lif',
            '_kg_sugar' => 'Şeker',
            '_kg_sodium' => 'Sodyum',
            '_kg_vitamins' => 'Vitaminler',
            '_kg_minerals' => 'Mineraller',
            
            // Özel Notlar
            '_kg_special_notes' => 'Özel Notlar',
        ];

        $missing = [];
        foreach ($required_fields as $key => $label) {
            $value = get_post_meta($post_id, $key, true);
            if (empty($value) || (is_array($value) && count($value) === 0)) {
                $missing[$key] = $label;
            }
        }

        // Taxonomy kontrolü
        $taxonomies = ['age-group', 'allergen', 'diet-type', 'meal-type'];
        foreach ($taxonomies as $tax) {
            $terms = wp_get_post_terms($post_id, $tax);
            if (empty($terms) || is_wp_error($terms)) {
                $tax_labels = [
                    'age-group' => 'Yaş Grupları',
                    'allergen' => 'Alerjenler',
                    'diet-type' => 'Diyet Tipi',
                    'meal-type' => 'Öğün Tipi'
                ];
                $missing[$tax] = $tax_labels[$tax] ?? $tax;
            }
        }
        
        // RankMath SEO kontrolü
        $seo_fields = [
            'rank_math_focus_keyword' => 'SEO Focus Keyword',
            'rank_math_title' => 'SEO Title',
            'rank_math_description' => 'SEO Description'
        ];
        foreach ($seo_fields as $key => $label) {
            $value = get_post_meta($post_id, $key, true);
            if (empty($value)) {
                $missing[$key] = $label;
            }
        }

        return $missing;
    }

    // AJAX: Sadece eksik alanları AI ile doldur
    public function ajax_enrich_recipe() {
        try {
            check_ajax_referer('kg_enrich_recipe', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Yetkiniz yok']);
                return;
            }

            $post_id = intval($_POST['post_id']);

            if (!$post_id) {
                wp_send_json_error(['message' => 'Geçersiz post ID']);
                return;
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'recipe') {
                wp_send_json_error(['message' => 'Geçersiz tarif']);
                return;
            }

            // Tarif verilerini topla
            $recipe_data = [
                'title' => $post->post_title,
                'content' => $post->post_content,
                'ingredients' => get_post_meta($post_id, '_kg_ingredients', true),
                'instructions' => get_post_meta($post_id, '_kg_instructions', true)
            ];
            
            // AI Service'i çağır
            if (!class_exists('\KG_Core\Services\AIService')) {
                wp_send_json_error(['message' => 'AI Service bulunamadı']);
                return;
            }

            $ai_service = new \KG_Core\Services\AIService();
            $ai_data = $ai_service->generateRecipeContent($recipe_data);

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
                $updated = $this->update_single_field($post_id, $key, $ai_data, $post);
                if ($updated) {
                    $updated_fields[] = $label;
                }
            }

            wp_send_json_success([
                'message' => count($updated_fields) . ' alan güncellendi',
                'updated_fields' => $updated_fields
            ]);
        } catch (\Exception $e) {
            error_log('KG Recipe Enrichment Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Zenginleştirme hatası: ' . $e->getMessage()
            ]);
        }
    }

    private function update_single_field($post_id, $key, $ai_data, $post) {
        // Meta field mapping
        $meta_mapping = [
            '_kg_prep_time' => 'prep_time',
            '_kg_cook_time' => 'cook_time',
            '_kg_serving_size' => 'serving_size',
            '_kg_difficulty' => 'difficulty',
            '_kg_freezable' => 'freezable',
            '_kg_storage_info' => 'storage_info',
            '_kg_substitutes' => 'substitutes',
            '_kg_special_notes' => 'special_notes',
        ];

        // Handle nutrition fields
        if (strpos($key, '_kg_') === 0 && isset($ai_data['nutrition'])) {
            $nutrition_mapping = [
                '_kg_calories' => 'calories',
                '_kg_protein' => 'protein',
                '_kg_carbs' => 'carbs',
                '_kg_fat' => 'fat',
                '_kg_fiber' => 'fiber',
                '_kg_sugar' => 'sugar',
                '_kg_sodium' => 'sodium',
                '_kg_vitamins' => 'vitamins',
                '_kg_minerals' => 'minerals',
            ];

            if (isset($nutrition_mapping[$key]) && isset($ai_data['nutrition'][$nutrition_mapping[$key]])) {
                update_post_meta($post_id, $key, sanitize_text_field($ai_data['nutrition'][$nutrition_mapping[$key]]));
                return true;
            }
            return false;
        }

        // Handle regular meta fields
        if (isset($meta_mapping[$key]) && isset($ai_data[$meta_mapping[$key]])) {
            $value = $ai_data[$meta_mapping[$key]];
            
            // Handle boolean for freezable
            if ($key === '_kg_freezable') {
                $value = $value ? '1' : '0';
            }
            
            // Sanitize based on type
            if (is_array($value)) {
                update_post_meta($post_id, $key, $value);
            } else {
                update_post_meta($post_id, $key, sanitize_textarea_field($value));
            }
            return true;
        }

        // Handle taxonomies
        if (in_array($key, ['age-group', 'allergen', 'diet-type', 'meal-type', 'special-condition'])) {
            $tax_mapping = [
                'age-group' => 'age_groups',
                'allergen' => 'allergens',
                'diet-type' => 'diet_types',
                'meal-type' => 'meal_types',
                'special-condition' => 'special_conditions'
            ];
            
            if (isset($tax_mapping[$key]) && isset($ai_data[$tax_mapping[$key]]) && is_array($ai_data[$tax_mapping[$key]])) {
                return $this->assign_taxonomy($post_id, $key, $ai_data[$tax_mapping[$key]]);
            }
            return false;
        }

        // Handle RankMath SEO fields
        if (strpos($key, 'rank_math_') === 0 && isset($ai_data['seo'])) {
            $seo_mapping = [
                'rank_math_focus_keyword' => 'focus_keyword',
                'rank_math_title' => 'title',
                'rank_math_description' => 'description'
            ];
            
            if (isset($seo_mapping[$key]) && isset($ai_data['seo'][$seo_mapping[$key]])) {
                update_post_meta($post_id, $key, sanitize_text_field($ai_data['seo'][$seo_mapping[$key]]));
                return true;
            }
            return false;
        }

        // Handle content (only if empty)
        if ($key === 'content' && empty($post->post_content) && isset($ai_data['content'])) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => wp_kses_post($ai_data['content'])
            ]);
            return true;
        }

        return false;
    }

    private function assign_taxonomy($post_id, $taxonomy, $terms_data) {
        if (empty($terms_data) || !is_array($terms_data)) {
            return false;
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
            return true;
        }
        
        return false;
    }

    public function enqueue_scripts($hook) {
        global $post_type, $post;
        if ($hook !== 'post.php' || $post_type !== 'recipe') {
            return;
        }

        // Use inline script to inject kgRecipeEnricher object with nonce and post data
        wp_enqueue_script('jquery');
        
        // Inject kgRecipeEnricher object for JavaScript
        wp_add_inline_script('jquery', 
            'var kgRecipeEnricher = ' . json_encode([
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kg_enrich_recipe'),
                'postId' => $post ? $post->ID : 0
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );

        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                // Eksik Alanları Doldur butonu
                $('#kg-enrich-missing-btn').on('click', function() {
                    var btn = $(this);
                    var statusDiv = $('#kg-enrich-status');
                    var originalText = btn.text();
                    
                    btn.prop('disabled', true).text('İşleniyor...');
                    statusDiv.html('<span style=\"color: #2271b1;\">⏳ Eksik alanlar AI ile doldruluyor...</span>');
                    
                    $.ajax({
                        url: kgRecipeEnricher.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'kg_enrich_recipe',
                            nonce: kgRecipeEnricher.nonce,
                            post_id: kgRecipeEnricher.postId
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
                                    if (xhr.responseText.length < 100) {
                                        errorMsg = 'Sunucu hatası: ' + xhr.responseText;
                                    }
                                }
                            }
                            statusDiv.html('<span style=\"color: #d63638;\">❌ ' + errorMsg + '</span>');
                            btn.prop('disabled', false).text(originalText);
                        }
                    });
                });
            });
        ");
    }
}

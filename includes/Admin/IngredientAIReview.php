<?php
namespace KG_Core\Admin;

use KG_Core\Services\AIService;

/**
 * Editorial, two-step AI suggestions for existing ingredient posts.
 * Generation never writes to the ingredient. Only explicitly selected fields
 * from a server-stored preview can be applied by an authorized editor.
 */
class IngredientAIReview {
    private const PREVIEW_TTL = 600;
    private const LOG_META = '_kg_ingredient_ai_review_log';

    public function __construct() {
        add_action('add_meta_boxes_ingredient', [$this, 'registerBox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_kg_ingredient_ai_review_preview', [$this, 'preview']);
        add_action('wp_ajax_kg_ingredient_ai_review_apply', [$this, 'apply']);
    }

    public function registerBox() {
        add_meta_box(
            'kg_ingredient_ai_review',
            'Malzeme AI Asistanı',
            [$this, 'renderBox'],
            'ingredient',
            'normal',
            'high',
            ['__block_editor_compatible_meta_box' => true]
        );
    }

    public function renderBox($post) {
        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return;
        }
        ?>
        <section id="kg-ingredient-ai-review" class="kg-ai-review" aria-label="Malzeme AI incelemesi">
            <header class="kg-ai-review__header">
                <span class="kg-ai-review__eyebrow">KIDSGOURMET / EDITORYAL ASISTAN</span>
                <div class="kg-ai-review__headline">
                    <div>
                        <h2>Malzeme bilgilerini tamamla ve geliştir</h2>
                        <p>Yapay zek&acirc; &ouml;neriler hazırlar; hangi bilgilerin kaydedileceğine sen karar verirsin.</p>
                    </div>
                    <span class="kg-ai-review__tag">Onaylı akış</span>
                </div>
            </header>
            <div class="kg-ai-review__coverage" data-kg-ai-coverage>
                <div class="kg-ai-review__coverage-head">
                    <div>
                        <span class="kg-ai-review__eyebrow">KAYDEDİLMİŞ ALANLAR</span>
                        <strong data-kg-ai-coverage-headline>Alanlar inceleniyor</strong>
                        <p data-kg-ai-coverage-summary>Kaydedilmiş malzeme bilgilerinin doluluk durumu.</p>
                    </div>
                    <span class="kg-ai-review__coverage-score" data-kg-ai-coverage-score></span>
                </div>
                <div class="kg-ai-review__coverage-track" aria-hidden="true"><span data-kg-ai-coverage-progress></span></div>
                <details class="kg-ai-review__coverage-details" data-kg-ai-coverage-details>
                    <summary><span data-kg-ai-coverage-toggle>Hangi alanlar boş?</span></summary>
                    <div data-kg-ai-coverage-groups></div>
                </details>
                <p class="kg-ai-review__coverage-hint">Sayılar kaydedilmiş bilgilere dayanır. İsteğe bağlı, yalnızca alerjenlere özgü bilgiler uygun olduğunda sayılır; besin değerleri, uzman onayı ve görseller otomatik eksik kabul edilmez.</p>
            </div>
            <div class="kg-ai-review__intro">
                <span class="kg-ai-review__step">01 &middot; İşlem seç</span>
                <span class="kg-ai-review__step">02 &middot; Önerileri incele</span>
                <span class="kg-ai-review__step">03 &middot; Seçip kaydet</span>
            </div>
            <div class="kg-ai-review__actions">
                <div class="kg-ai-review__action-choice">
                    <button type="button" class="button button-primary" data-kg-ai-generate-complete>AI ile Tamamla</button>
                    <small>Yalnızca boş alanlar için öneri oluştur.</small>
                </div>
                <div class="kg-ai-review__action-choice">
                    <button type="button" class="button button-secondary" data-kg-ai-generate-enrich>AI ile Zenginleştir</button>
                    <small>Dolu alanları da iyileştirmek için öneri oluştur.</small>
                </div>
                <p>Öneriler kaydedilmiş verilerden oluşturulur. Kaydedilmemiş değişiklikler öneri üretimini engellemez; ancak onaydan önce bu değişiklikleri kaydetmelisin.</p>
            </div>
            <div class="kg-ai-review__message" data-kg-ai-message role="status" aria-live="polite"></div>
            <div class="kg-ai-review__preview" data-kg-ai-preview hidden>
                <div class="kg-ai-review__preview-heading">
                    <div>
                        <span class="kg-ai-review__eyebrow">AI TASLAĞI</span>
                        <h3>Alan bazında karşılaştır</h3>
                    </div>
                    <span data-kg-ai-count></span>
                </div>
                <p class="kg-ai-review__hint">Mevcut değerler korunur; yalnızca se&ccedil;tiğin alanlar değişir. Sayısal besin değerleri, uzman onayı ve g&ouml;rseller otomatik doldurulmaz.</p>
                <div class="kg-ai-review__rows" data-kg-ai-rows></div>
                <div class="kg-ai-review__footer">
                    <button type="button" class="button button-primary" data-kg-ai-apply disabled>Se&ccedil;ilenleri kaydet</button>
                    <button type="button" class="button" data-kg-ai-cancel>Vazge&ccedil;</button>
                </div>
            </div>
        </section>
        <?php
    }

    public function enqueueAssets($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'ingredient') {
            return;
        }
        global $post;
        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return;
        }
        $js = 'assets/admin/js/ingredient-ai-review.js';
        $css = 'assets/admin/css/ingredient-ai-review.css';
        wp_enqueue_script('kg-ingredient-ai-review', KG_CORE_URL . $js, [], (string) filemtime(KG_CORE_PATH . $js), true);
        wp_localize_script('kg-ingredient-ai-review', 'kgIngredientAIReview', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kg_ingredient_ai_review_' . $post->ID),
            'postId' => (int) $post->ID,
            'isPublished' => $post->post_status === 'publish',
            'coverage' => $this->coverageSnapshot((int) $post->ID),
        ]);
        wp_enqueue_style('kg-ingredient-ai-review', KG_CORE_URL . $css, [], (string) filemtime(KG_CORE_PATH . $css));
    }

    private static function fieldDefinitions() {
        return [
            'excerpt' => ['label' => 'Kısa açıklama', 'source' => 'excerpt', 'target' => 'post_excerpt', 'format' => 'text'],
            'content' => ['label' => 'Malzeme rehberi', 'source' => 'content', 'target' => 'post_content', 'format' => 'html'],
            'start_age' => ['label' => 'Başlangıç yaşı (ay)', 'source' => 'start_age', 'target' => '_kg_start_age', 'format' => 'age'],
            'benefits' => ['label' => 'Faydaları ve kullanım', 'source' => 'benefits', 'target' => '_kg_benefits', 'format' => 'html'],
            'allergy_risk' => ['label' => 'Alerji riski', 'source' => 'allergy_risk', 'target' => '_kg_allergy_risk', 'format' => 'risk'],
            'cross_contamination' => ['label' => 'Çapraz bulaşma', 'source' => 'cross_contamination', 'target' => '_kg_cross_contamination', 'format' => 'text'],
            'allergy_symptoms' => ['label' => 'Alerji belirtileri', 'source' => 'allergy_symptoms', 'target' => '_kg_allergy_symptoms', 'format' => 'text'],
            'alternatives' => ['label' => 'Alternatif malzemeler', 'source' => 'alternatives', 'target' => '_kg_alternatives', 'format' => 'text'],
            'preparation_tips' => ['label' => 'Hazırlama ipuçları', 'source' => 'preparation_tips', 'target' => '_kg_preparation_tips', 'format' => 'text'],
            'storage_tips' => ['label' => 'Saklama koşulları', 'source' => 'storage_tips', 'target' => '_kg_storage_tips', 'format' => 'text'],
            'selection_tips' => ['label' => 'Seçim ipuçları', 'source' => 'selection_tips', 'target' => '_kg_selection_tips', 'format' => 'text'],
            'pro_tips' => ['label' => 'Püf noktaları', 'source' => 'pro_tips', 'target' => '_kg_pro_tips', 'format' => 'text'],
            'season' => ['label' => 'Mevsim', 'source' => 'season', 'target' => '_kg_season', 'format' => 'list'],
            'prep_methods' => ['label' => 'Hazırlama yöntemleri', 'source' => 'prep_methods', 'target' => '_kg_prep_methods', 'format' => 'list'],
            'prep_by_age' => ['label' => 'Yaşa göre hazırlama', 'source' => 'prep_by_age', 'target' => '_kg_prep_by_age', 'format' => 'age_rows'],
            'pairings' => ['label' => 'Uyumlu ikililer', 'source' => 'pairings', 'target' => '_kg_pairings', 'format' => 'pairings'],
        ];
    }

    /**
     * Coverage is editorial completeness, not an AI safety score.
     * All counts use saved WordPress fields; conditional allergen fields
     * are only tracked for ingredients already assigned allergen terms.
     */
    public static function isFilled($value) {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::isFilled($item)) return true;
            }
            return false;
        }
        if ($value === null || $value === false) return false;
        if (is_scalar($value)) return trim(wp_strip_all_tags((string) $value)) !== '';
        return false;
    }

    private function coverageSnapshot($post_id) {
        $post = get_post($post_id);
        $definitions = self::fieldDefinitions();
        $groups = [
            ['key' => 'essential', 'title' => 'Temel editoryal bilgiler', 'keys' => [
                'title', 'excerpt', 'content', 'start_age', 'allergy_risk', 'category',
            ]],
            ['key' => 'enrichment', 'title' => 'Tamamlayıcı bilgiler', 'keys' => [
                'benefits', 'preparation_tips', 'storage_tips', 'selection_tips',
                'pro_tips', 'season', 'prep_methods', 'prep_by_age', 'pairings',
            ]],
        ];

        $allergens = wp_get_post_terms($post_id, 'allergen', ['fields' => 'ids']);
        if (!is_wp_error($allergens) && !empty($allergens)) {
            $groups[] = ['key' => 'allergens', 'title' => 'Alerjen açıklamaları', 'keys' => [
                'cross_contamination', 'allergy_symptoms', 'alternatives',
            ]];
        }

        $category_terms = wp_get_post_terms($post_id, 'ingredient-category', ['fields' => 'ids']);
        $has_category = !is_wp_error($category_terms) && !empty($category_terms);
        $missing_count = 0;
        $filled_count = 0;
        $result = [];

        foreach ($groups as $group) {
            $items = [];
            $group_filled = 0;
            foreach ($group['keys'] as $key) {
                if ($key === 'title') {
                    $label = 'Malzeme adı';
                    $value = $post ? $post->post_title : '';
                    $filled = self::isFilled($value);
                } elseif ($key === 'category') {
                    $label = 'Malzeme kategorisi';
                    $filled = $has_category;
                } else {
                    if (!isset($definitions[$key])) continue;
                    $def = $definitions[$key];
                    $label = $def['label'];
                    $filled = self::isFilled($this->currentValue($post_id, $def['target']));
                }
                $items[] = ['key' => $key, 'label' => $label, 'filled' => $filled];
                if ($filled) $group_filled++;
            }
            $n = count($items);
            $filled_count += $group_filled;
            $missing_count += $n - $group_filled;
            $result[] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'total' => $n,
                'filled' => $group_filled,
                'items' => $items,
            ];
        }

        return [
            'total' => $filled_count + $missing_count,
            'filled' => $filled_count,
            'missing' => $missing_count,
            'groups' => $result,
        ];
    }

    /** A pure check used before persisting a suggested minimum age. */
    public static function isHoneyTitle($title) {
        if (!is_string($title)) return false;
        $without_pumpkin = preg_replace('/(?<![\p{L}])bal\s*kaba(?:\x{011F}|g)(?:\x{0131}|i)(?![\p{L}])/iu', '', $title);
        return (bool) preg_match('/(?<![\p{L}])(?:bal|bal\x{0131}|ball\x{0131}|balli)(?![\p{L}])/iu', $without_pumpkin);
    }

    private static function normalizeSuggestion($format, $raw, $title) {
        if ($format === 'age') {
            if (!is_numeric($raw)) return null;
            $age = (int) $raw;
            if (!in_array($age, [6, 8, 10, 12, 18, 24, 36], true) || (self::isHoneyTitle($title) && $age < 12)) return null;
            return $age;
        }
        if ($format === 'risk') {
            $value = sanitize_text_field((string) $raw);
            return in_array($value, ["D\u{00FC}\u{015F}\u{00FC}k", 'Orta', "Y\u{00FC}ksek"], true) ? $value : null;
        }
        if ($format === 'html' || $format === 'text') {
            if (!is_string($raw) || trim($raw) === '') return null;
            $value = $format === 'html' ? wp_kses_post($raw) : sanitize_textarea_field($raw);
            return strlen($value) > 30000 ? null : trim($value);
        }
        if ($format === 'list') {
            if (is_string($raw)) $raw = explode(',', $raw);
            if (!is_array($raw)) return null;
            $value = array_values(array_filter(array_map(function ($item) {
                return is_string($item) ? sanitize_text_field($item) : '';
            }, array_slice($raw, 0, 12))));
            return $value === [] ? null : $value;
        }
        if ($format === 'age_rows' || $format === 'pairings') {
            if (!is_array($raw)) return null;
            $rows = [];
            foreach (array_slice($raw, 0, 10) as $row) {
                if (!is_array($row)) continue;
                if ($format === 'age_rows' && isset($row['age'], $row['method'], $row['text'])) {
                    // Do not propose early complementary feeding, especially for honey.
                    if (!preg_match('/([0-9]{1,3})/', (string) $row['age'], $match)) return null;
                    $minimum = (int) $match[1];
                    if ($minimum < 6 || (self::isHoneyTitle($title) && $minimum < 12)) return null;
                    $rows[] = [
                        'age' => sanitize_text_field($row['age']),
                        'method' => sanitize_text_field($row['method']),
                        'text' => sanitize_textarea_field($row['text'])
                    ];
                }
                if ($format === 'pairings' && isset($row['name'], $row['emoji'])) {
                    $rows[] = [
                        'name' => sanitize_text_field($row['name']),
                        'emoji' => sanitize_text_field($row['emoji'])
                    ];
                }
            }
            return $rows === [] ? null : $rows;
        }
        return null;
    }

    private static function present($value) {
        if ($value === null || $value === '' || $value === []) return '';
        if (is_array($value)) return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return wp_strip_all_tags((string) $value, true);
    }

    private function currentValue($post_id, $target) {
        $post = get_post($post_id);
        if ($target === 'post_excerpt') return $post->post_excerpt;
        if ($target === 'post_content') return $post->post_content;
        return get_post_meta($post_id, $target, true);
    }

    private function signature($post_id) {
        $post = get_post($post_id);
        $values = [$post->post_title, $post->post_status, $post->post_content, $post->post_excerpt, $post->post_modified_gmt];
        foreach (self::fieldDefinitions() as $field) {
            if ($field['target'][0] === '_') $values[$field['target']] = get_post_meta($post_id, $field['target'], true);
        }
        return hash('sha256', serialize($values));
    }

    private function cacheKey($post_id) {
        return 'kg_ai_review_' . get_current_user_id() . '_' . $post_id;
    }

    private function authorizedPost() {
        $id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!$id || !check_ajax_referer('kg_ingredient_ai_review_' . $id, 'nonce', false)) {
            wp_send_json_error(['message' => 'Guvenlik dogrulamasi basarisiz, sayfayi yenileyin.'], 403);
        }
        $post = get_post($id);
        if (!$post || $post->post_type !== 'ingredient' || !current_user_can('edit_post', $id)) {
            wp_send_json_error(['message' => 'Bu malzemeyi duzenleme yetkiniz yok.'], 403);
        }
        return $post;
    }

    public function preview() {
        $post = $this->authorizedPost();
        if ($post->post_status === 'auto-draft' || trim($post->post_title) === '') {
            wp_send_json_error(['message' => 'Once bir malzeme adi yazip taslagi kaydedin.'], 409);
        }
        $id = (int) $post->ID;
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'complete';
        if (!in_array($mode, ['complete', 'enrich'], true)) {
            wp_send_json_error(['message' => 'Geçersiz AI işlem türü.'], 422);
        }
        if ($mode === 'complete') {
            $has_empty = false;
            foreach (self::fieldDefinitions() as $definition) {
                if (!self::isFilled($this->currentValue($id, $definition['target']))) {
                    $has_empty = true;
                    break;
                }
            }
            if (!$has_empty) {
                wp_send_json_error(['message' => 'AI ile tamamlanabilecek boş alan yok. Mevcut içeriği geliştirmek için AI ile Zenginleştir seçeneğini kullan.'], 422);
            }
        }
        $rate_key = 'kg_ai_review_rate_' . get_current_user_id() . '_' . $id;
        if (get_transient($rate_key)) {
            wp_send_json_error(['message' => 'Yeni bir AI istegi icin kisa bir sure bekleyin.'], 429);
        }
        set_transient($rate_key, 1, 30);
        $snapshot = $this->signature($id);
        $context = [
            'existing_excerpt' => substr(wp_strip_all_tags($post->post_excerpt), 0, 900),
            'existing_content' => substr(wp_strip_all_tags($post->post_content), 0, 1800),
            'existing_start_age' => get_post_meta($id, '_kg_start_age', true),
            'existing_allergy_risk' => get_post_meta($id, '_kg_allergy_risk', true),
        ];
        $ai = (new AIService())->generateIngredientContent($post->post_title, $context);
        if (is_wp_error($ai)) {
            delete_transient($rate_key);
            wp_send_json_error(['message' => $ai->get_error_message()], 422);
        }
        if (!hash_equals($snapshot, $this->signature($id))) {
            wp_send_json_error(['message' => 'Malzeme AI islenirken degisti. Sayfayi yenileyip tekrar deneyin.'], 409);
        }

        $suggestions = [];
        $fields = [];
        foreach (self::fieldDefinitions() as $key => $field) {
            if (!array_key_exists($field['source'], $ai)) continue;
            $proposed = self::normalizeSuggestion($field['format'], $ai[$field['source']], $post->post_title);
            if ($proposed === null) continue;
            $current = $this->currentValue($id, $field['target']);
            if (serialize($current) === serialize($proposed)) continue;
            if ($mode === 'complete' && self::isFilled($current)) continue;
            $suggestions[$key] = $proposed;
            $fields[] = [
                'key' => $key,
                'label' => $field['label'],
                'current' => self::present($current),
                'suggested' => self::present($proposed),
                'empty' => !self::isFilled($current),
            ];
        }
        if ($fields === []) {
            wp_send_json_error(['message' => $mode === 'complete' ? 'Boş alanlar için uygulanabilir AI önerisi bulunamadı. Alanları elle tamamlayabilir veya daha sonra tekrar deneyebilirsin.' : 'Mevcut içerikten farklı bir AI önerisi bulunamadı.'], 422);
        }
        $token = wp_generate_password(40, false, false);
        set_transient($this->cacheKey($id), [
            'token' => $token,
            'signature' => $snapshot,
            'values' => $suggestions,
            'mode' => $mode,
            'time' => current_time('mysql', true),
        ], self::PREVIEW_TTL);
        wp_send_json_success([
            'token' => $token,
            'fields' => $fields,
            'mode' => $mode,
            'expires_in' => self::PREVIEW_TTL,
            'message' => 'AI onerileri hazir. Hicbir alan otomatik kaydedilmedi.',
        ]);
    }

    public function apply() {
        $post = $this->authorizedPost();
        $id = (int) $post->ID;
        $cache = get_transient($this->cacheKey($id));
        $token = isset($_POST['token']) && is_string($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        if (!is_array($cache) || empty($cache['token']) || !$token || !hash_equals($cache['token'], $token)) {
            wp_send_json_error(['message' => 'AI onizleme suresi dolmus veya gecersiz. Yeniden olusturun.'], 409);
        }
        if (!hash_equals($cache['signature'], $this->signature($id))) {
            delete_transient($this->cacheKey($id));
            wp_send_json_error(['message' => 'Malzeme degistirildi. Eski oneri kaydedilemez; yeniden olusturun.'], 409);
        }
        $submitted = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        $selected = array_map('sanitize_key', array_filter($submitted, 'is_string'));
        $selected = array_values(array_unique($selected));
        if ($selected === [] || count($selected) > count(self::fieldDefinitions())) {
            wp_send_json_error(['message' => 'Kaydetmek icin en az bir oneriyi secin.'], 422);
        }
        foreach ($selected as $key) {
            if (!isset($cache['values'][$key]) || !isset(self::fieldDefinitions()[$key])) {
                wp_send_json_error(['message' => 'Secilen alanlardan biri gecersiz.'], 422);
            }
        }
        if ($post->post_status === 'publish' && (!isset($_POST['confirm_published']) || $_POST['confirm_published'] !== 'yes')) {
            wp_send_json_error(['message' => 'Yayindaki malzemenin degisecegini ayrica onaylayin.'], 409);
        }
        // Only values persisted in our server-side transient can be written.
        $post_update = ['ID' => $id];
        foreach ($selected as $key) {
            $def = self::fieldDefinitions()[$key];
            if ($def['target'] === 'post_content' || $def['target'] === 'post_excerpt') {
                $post_update[$def['target']] = $cache['values'][$key];
            }
        }
        if (count($post_update) > 1) {
            $result = wp_update_post($post_update, true);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()], 500);
            }
        }
        foreach ($selected as $key) {
            $def = self::fieldDefinitions()[$key];
            if ($def['target'][0] === '_') {
                update_post_meta($id, $def['target'], $cache['values'][$key]);
            }
        }
        $log = get_post_meta($id, self::LOG_META, true);
        if (!is_array($log)) $log = [];
        $log[] = [
            'user_id' => get_current_user_id(),
            'applied_at_utc' => current_time('mysql', true),
            'fields' => $selected,
        ];
        update_post_meta($id, self::LOG_META, array_slice($log, -30));
        delete_transient($this->cacheKey($id));
        wp_send_json_success(['message' => count($selected) . ' onayli AI oneri alani kaydedildi.', 'fields' => $selected]);
    }
}
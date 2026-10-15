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
            'AI Öneri Stüdyosu',
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
                        <h2>Malzeme &ouml;nerilerini incele</h2>
                        <p>Yapay zek&acirc; &ouml;neriler hazırlar; hangi bilgilerin kaydedileceğine sen karar verirsin.</p>
                    </div>
                    <span class="kg-ai-review__tag">Onaylı akış</span>
                </div>
            </header>
            <div class="kg-ai-review__intro">
                <span class="kg-ai-review__step">01 &middot; &Ouml;neri oluştur</span>
                <span class="kg-ai-review__step">02 &middot; Alanları se&ccedil;</span>
                <span class="kg-ai-review__step">03 &middot; Kaydet</span>
            </div>
            <div class="kg-ai-review__actions">
                <button type="button" class="button button-primary" data-kg-ai-generate>AI ile &ouml;neri oluştur</button>
                <p>Malzeme başlığı ve kaydedilmiş bilgileri temel alır. Kaydedilmemiş değişikliklerin varsa &ouml;nce malzemeyi kaydet.</p>
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
            $suggestions[$key] = $proposed;
            $fields[] = [
                'key' => $key,
                'label' => $field['label'],
                'current' => self::present($current),
                'suggested' => self::present($proposed),
                'empty' => $current === '' || $current === [] || $current === null,
            ];
        }
        if ($fields === []) {
            wp_send_json_error(['message' => 'Farkli bir AI onerisi bulunamadi; mevcut alanlar degismedi.'], 422);
        }
        $token = wp_generate_password(40, false, false);
        set_transient($this->cacheKey($id), [
            'token' => $token,
            'signature' => $snapshot,
            'values' => $suggestions,
            'time' => current_time('mysql', true),
        ], self::PREVIEW_TTL);
        wp_send_json_success([
            'token' => $token,
            'fields' => $fields,
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
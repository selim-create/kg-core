<?php
namespace KG_Core\Admin;

use KG_Core\Services\AIService;

/**
 * Editorial, two-step AI suggestions for existing ingredient posts.
 * Generation never writes to the ingredient. Only explicitly selected fields
 * from a server-stored preview can be applied by an authorized editor.
 */
class IngredientAIReview {
    private const LOG_META = '_kg_ingredient_ai_review_log';

    public function __construct() {
        // WP-Cron requests are not wp-admin requests. This hook must exist
        // whenever WordPress loads the plugin, including cron and WP-CLI.

        if (!is_admin()) {
            return;
        }
        add_action('add_meta_boxes_ingredient', [$this, 'registerBox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_kg_ingredient_ai_review_preview', [$this, 'preview']);
        add_action('wp_ajax_kg_ingredient_ai_review_status', [$this, 'status']);
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

    /** Compare metadata as WordPress returns it (scalars are strings after reload). */
    private static function canonicalStoredValue($value) {
        if (is_array($value)) {
            $output = [];
            foreach ($value as $key => $part) {
                $output[$key] = self::canonicalStoredValue($part);
            }
            return $output;
        }
        if ($value === null || $value === false) return '';
        if ($value === true) return '1';
        if (is_scalar($value)) return (string) $value;
        return $value;
    }

    public static function savedValueMatches($actual, $expected) {
        return serialize(self::canonicalStoredValue($actual))
            === serialize(self::canonicalStoredValue($expected));
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
            if (!is_scalar($raw)) return null;
            $value = trim(sanitize_text_field((string) $raw));
            if (in_array($value, ['Düşük', 'Orta', 'Yüksek'], true)) return $value;
            $aliases = [
                'düşük' => 'Düşük', 'dusuk' => 'Düşük',
                'düşük risk' => 'Düşük', 'dusuk risk' => 'Düşük',
                'low' => 'Düşük', 'low risk' => 'Düşük',
                'orta' => 'Orta', 'orta risk' => 'Orta',
                'medium' => 'Orta', 'medium risk' => 'Orta',
                'yüksek' => 'Yüksek', 'yuksek' => 'Yüksek',
                'yüksek risk' => 'Yüksek', 'yuksek risk' => 'Yüksek',
                'high' => 'Yüksek', 'high risk' => 'Yüksek',
            ];
            $key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            return isset($aliases[$key]) ? $aliases[$key] : null;
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
                if ($format === 'pairings') {
                    $name = isset($row['name']) ? $row['name']
                        : (isset($row['ingredient']) ? $row['ingredient'] : null);
                    if (is_string($name) && trim($name) !== '') {
                        $emoji = isset($row['emoji']) && is_string($row['emoji']) && trim($row['emoji']) !== ''
                            ? $row['emoji'] : '🍽️';
                        $rows[] = [
                            'name' => sanitize_text_field($name),
                            'emoji' => sanitize_text_field($emoji),
                        ];
                    }
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

    private function jobKey($post_id, $user_id = null) {
        return 'kg_ai_review_job_' . ($user_id === null ? get_current_user_id() : (int) $user_id) . '_' . $post_id;
    }

    /**
     * Only request fields that this review UI can actually edit. Skipping
     * irrelevant allergy-specific fields prevents false missing suggestions.
     */
    private function eligibleFields($post_id, $mode) {
        $definitions = self::fieldDefinitions();
        $allergens = wp_get_post_terms($post_id, 'allergen', ['fields' => 'ids']);
        if (is_wp_error($allergens) || empty($allergens)) {
            unset($definitions['cross_contamination'], $definitions['allergy_symptoms'], $definitions['alternatives']);
        }
        if ($mode === 'complete') {
            foreach ($definitions as $key => $definition) {
                if (self::isFilled($this->currentValue($post_id, $definition['target']))) {
                    unset($definitions[$key]);
                }
            }
        }
        return $definitions;
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

    /**
     * Start OpenAI generation quickly; the authenticated status request
     * applies the finished result once, without a second preview token.
     */
    public function preview() {
        $post = $this->authorizedPost();
        if ($post->post_status === 'auto-draft' || trim($post->post_title) === '') {
            wp_send_json_error(['message' => 'Önce malzemeyi taslak olarak kaydet.'], 409);
        }
        $id = (int) $post->ID;
        $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'complete';
        if (!in_array($mode, ['complete', 'enrich'], true)) wp_send_json_error(['message' => 'Geçersiz AI modu.'], 422);
        $definitions = $this->eligibleFields($id, $mode);
        if (!$definitions) wp_send_json_error(['message' => 'Bu modda AI ile önerilecek alan yok.'], 422);
        $publish_confirmed = isset($_POST['confirm_published']) && $_POST['confirm_published'] === 'yes';
        if ($post->post_status === 'publish' && !$publish_confirmed) {
            wp_send_json_error(['message' => 'Yayındaki malzemeye otomatik kayıt için açık onay gerekiyor.'], 409);
        }
        $uid = get_current_user_id();
        $existing = get_transient($this->jobKey($id, $uid));
        if (is_array($existing) && in_array($existing['status'] ?? '', ['queued','running'], true)
            && time() - (int) ($existing['queued_at'] ?? 0) < 600) {
            wp_send_json_success(['job_id' => $existing['id'], 'status' => 'running', 'poll_after_ms' => 3000]);
        }
        $context = [
            'existing_excerpt' => substr(wp_strip_all_tags($post->post_excerpt), 0, 750),
            'existing_content' => substr(wp_strip_all_tags($post->post_content), 0, 1400),
            'existing_start_age' => get_post_meta($id, '_kg_start_age', true),
            'existing_allergy_risk' => get_post_meta($id, '_kg_allergy_risk', true),
        ];
        $started = (new AIService())->startIngredientReviewBackground($post->post_title, $context, $definitions, $mode);
        if (is_wp_error($started)) wp_send_json_error(['message' => $started->get_error_message()], 502);
        $job_id = wp_generate_password(36, false, false);
        $job = [
            'id' => $job_id, 'mode' => $mode, 'status' => 'running',
            'response_id' => $started['response_id'],
            'signature' => $this->signature($id), 'queued_at' => time(),
            'publish_confirmed' => $publish_confirmed,
        ];
        set_transient($this->jobKey($id, $uid), $job, 20 * MINUTE_IN_SECONDS);
        if ($started['status'] === 'completed') {
            $result = $this->applyBackgroundResult($post, $job, $started['text']);
            if (is_wp_error($result)) {
                $job['status'] = 'failed'; $job['error'] = $result->get_error_message();
            } else {
                $job['status'] = 'done'; $job['result'] = $result;
            }
            set_transient($this->jobKey($id, $uid), $job, 20 * MINUTE_IN_SECONDS);
        }
        wp_send_json_success(['job_id' => $job_id, 'status' => $job['status'], 'poll_after_ms' => 3000]);
    }

    public function status() {
        $post = $this->authorizedPost();
        $id = (int) $post->ID;
        $expected = isset($_POST['job_id']) && is_string($_POST['job_id'])
            ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        $key = $this->jobKey($id);
        $job = get_transient($key);
        if (!is_array($job) || empty($job['id']) || !$expected || !hash_equals($job['id'], $expected)) {
            wp_send_json_error(['message' => 'AI işi bulunamadı. Yeniden dene.'], 404);
        }
        if ($job['status'] === 'failed') wp_send_json_success(['status' => 'failed', 'message' => $job['error']]);
        if ($job['status'] === 'done') wp_send_json_success(['status' => 'done', 'result' => $job['result']]);
        if (time() - (int) $job['queued_at'] > 600) {
            $job['status'] = 'failed'; $job['error'] = 'OpenAI işi 10 dakikada tamamlanamadı. OpenAI durumunu kontrol et.';
            set_transient($key, $job, 20 * MINUTE_IN_SECONDS);
            wp_send_json_success(['status' => 'failed', 'message' => $job['error']]);
        }
        $result = (new AIService())->pollIngredientReviewBackground($job['response_id']);
        if (is_wp_error($result)) {
            $job['status'] = 'failed'; $job['error'] = $result->get_error_message();
            set_transient($key, $job, 20 * MINUTE_IN_SECONDS);
            wp_send_json_success(['status' => 'failed', 'message' => $job['error']]);
        }
        if ($result['status'] !== 'completed') {
            wp_send_json_success(['status' => 'running', 'message' => 'OpenAI önerileri hazırlanıyor…']);
        }
        $saved = $this->applyBackgroundResult($post, $job, $result['text']);
        if (is_wp_error($saved)) {
            $job['status'] = 'failed'; $job['error'] = $saved->get_error_message();
        } else {
            $job['status'] = 'done'; $job['result'] = $saved;
        }
        set_transient($key, $job, 20 * MINUTE_IN_SECONDS);
        wp_send_json_success($job['status'] === 'done'
            ? ['status' => 'done', 'result' => $job['result']]
            : ['status' => 'failed', 'message' => $job['error']]);
    }

    /**
     * Commit an authenticated, version-checked AI completion in the same
     * request that receives the finished OpenAI result. No second AJAX call,
     * review token or ephemeral preview cache is required.
     *
     * Complete updates only empty supported fields. Enrich may update
     * populated supported fields. Neither modifies expert approval,
     * verified nutrition numbers, terms, or publication status.
     */
    private function applyBackgroundResult($post, $job, $raw_text) {
        $id = (int) $post->ID;
        $snapshot = $job['signature'] ?? '';
        if (!$snapshot || !hash_equals($snapshot, $this->signature($id))) {
            return new \WP_Error('ingredient_changed', 'AI çalışırken malzeme değişti; kayıt yapılmadı. Sayfayı yenileyip yeniden dene.');
        }
        if ($post->post_status === 'publish' && empty($job['publish_confirmed'])) {
            return new \WP_Error('published_confirm_required', 'Yayındaki malzemeye otomatik kayıt için editör onayı gerekiyor.');
        }
        $mode = $job['mode'] ?? '';
        if (!in_array($mode, ['complete', 'enrich'], true)) {
            return new \WP_Error('invalid_ai_mode', 'AI çalışma modu geçersiz.');
        }
        $ai = (new AIService())->parseIngredientReviewBackgroundText($raw_text);
        if (is_wp_error($ai)) return $ai;

        $definitions = $this->eligibleFields($id, $mode);
        $selected = [];
        foreach ($definitions as $key => $field) {
            if (!array_key_exists($field['source'], $ai)) continue;
            $proposed = self::normalizeSuggestion($field['format'], $ai[$field['source']], $post->post_title);
            if ($proposed === null) continue;
            $current = $this->currentValue($id, $field['target']);
            if (serialize($current) === serialize($proposed)) continue;
            if ($mode === 'complete' && self::isFilled($current)) continue;
            $selected[$key] = $proposed;
        }
        if (!$selected) {
            return new \WP_Error('no_ai_suggestions', 'AI geçerli bir değişiklik önermedi; mevcut alanlara dokunulmadı.');
        }
        // Prevent writing anything when the post changed after the comparisons.
        if (!hash_equals($snapshot, $this->signature($id))) {
            return new \WP_Error('ingredient_changed', 'AI kayıt öncesinde malzeme değişti; işlem durduruldu.');
        }

        $post_update = ['ID' => $id];
        foreach ($selected as $key => $value) {
            $target = $definitions[$key]['target'];
            if ($target === 'post_content' || $target === 'post_excerpt') {
                $post_update[$target] = $value;
            }
        }
        if (count($post_update) > 1) {
            $updated = wp_update_post($post_update, true);
            if (is_wp_error($updated)) return $updated;
        }
        $failures = [];
        foreach ($selected as $key => $value) {
            $target = $definitions[$key]['target'];
            if (strpos($target, '_') === 0) {
                update_post_meta($id, $target, $value);
                // WordPress returns false for an unchanged value as well as
                // failures, so verify persisted values instead of the return.
                if (!self::savedValueMatches(get_post_meta($id, $target, true), $value)) {
                    $failures[] = $key;
                }
            }
        }
        if ($failures) {
            error_log('KG Ingredient AI: fields did not verify on post ' . $id
                . ': ' . implode(',', $failures));
        }
        $saved_keys = array_values(array_diff(array_keys($selected), $failures));
        if (!$saved_keys) {
            return new \WP_Error('ai_save_failed', 'AI alanları kaydedilemedi. Mevcut içerik ve sunucu günlükleri kontrol edilmeli.');
        }
        if (class_exists('\KG_Core\Config\FeatureFlags')
            && \KG_Core\Config\FeatureFlags::useDualWrite()
            && class_exists('\KG_Core\Services\MetaSyncService')) {
            \KG_Core\Services\MetaSyncService::syncIngredient($id);
        }

        $log = get_post_meta($id, self::LOG_META, true);
        if (!is_array($log)) $log = [];
        $log[] = [
            'user_id' => get_current_user_id(),
            'applied_at_utc' => current_time('mysql', true),
            'fields' => $saved_keys,
            'mode' => $mode,
            'automatic' => true,
        ];
        update_post_meta($id, self::LOG_META, array_slice($log, -30));

        return [
            'count' => count($saved_keys),
            'fields' => $saved_keys,
            'partial' => !empty($failures),
            'failed_fields' => $failures,
            'message' => empty($failures)
                ? count($saved_keys) . ' AI alanı otomatik kaydedildi.'
                : count($saved_keys) . ' alan kaydedildi. Kontrol gerektiren alanlar: ' . implode(', ', $failures),
        ];
    }

}
<?php
namespace KG_Core\Services;

/**
 * Advisory checks for recipe publishing.
 *
 * A warning is not a medical judgment. Authorized editors retain final
 * publication control; acknowledgement and publication are audited separately.
 */
class RecipePublicationGuard {
    private const ACK_META = '_kg_recipe_safety_acknowledgment';
    private const WARNING_META = '_kg_recipe_safety_warning';
    private const LAST_SIGNATURE_META = '_kg_recipe_safety_last_publish_signature';
    private const LOG_META = '_kg_recipe_safety_publication_log';

    public function __construct() {
        add_action('add_meta_boxes_recipe', [$this, 'registerNoticeBox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueReviewScript']);
        add_action('wp_ajax_kg_recipe_acknowledge_warning', [$this, 'ajaxAcknowledgeWarning']);
        add_action('wp_ajax_kg_recipe_review_status', [$this, 'ajaxReviewStatus']);
        // Runs after the post, taxonomy terms and post meta have been saved.
        // This also covers Gutenberg, REST and scheduled publication.
        add_action('wp_after_insert_post', [$this, 'recordReview'], 100, 4);
        // WordPress cron calls wp_publish_post(), bypassing wp_after_insert_post.
        add_action('transition_post_status', [$this, 'recordScheduledPublication'], 100, 3);
    }

    public function registerNoticeBox() {
        add_meta_box(
            'kg_recipe_safety_review',
            'Beslenme Güvenliği · Editoryal Kontrol',
            [$this, 'renderNoticeBox'],
            'recipe',
            'side',
            'high',
            [
                '__block_editor_compatible_meta_box' => true,
                '__back_compat_meta_box' => false,
            ]
        );
    }

    public function enqueueReviewScript($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'recipe') {
            return;
        }
        global $post;
        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return;
        }
        wp_enqueue_script(
            'kg-recipe-safety-review',
            KG_CORE_URL . 'assets/admin/js/recipe-safety-review.js',
            [],
            (string) filemtime(KG_CORE_PATH . 'assets/admin/js/recipe-safety-review.js'),
            true
        );
        wp_localize_script('kg-recipe-safety-review', 'kgRecipeSafety', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kg_recipe_safety_ack_' . $post->ID),
            'postId' => (int) $post->ID,
        ]);
    }

    public function renderNoticeBox($post) {
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }

        $warning = $this->assessPost($post->ID);
        if ($warning === null) {
            echo '<p>Kaydedilmiş tarifte bu sınırlı kontroller kapsamında uyarı bulunmadı.</p>';
            echo '<p><small>Bu sonuç uzman incelemesi veya tıbbi onay değildir.</small></p>';
            return;
        }

        echo '<div class="notice notice-warning inline"><p><strong>Güvenlik uyarısı:</strong> '
            . esc_html($warning) . '</p></div>';
        echo '<p><small>Uyarı kaydedilmiş malzeme ve yaş gruplarına dayanır. '
            . 'Değişiklik yaptıysanız önce tarifi kaydedin.</small></p>';

        $fingerprint = $this->signature($post->ID, $warning);
        $ack = get_post_meta($post->ID, self::ACK_META, true);
        if (self::ackMatches($ack, $fingerprint)) {
            $reviewer = get_userdata((int) $ack['user_id']);
            $name = $reviewer ? $reviewer->display_name : 'ID ' . (int) $ack['user_id'];
            echo '<p><strong>Bu sürüm için editör onayı kayıtlı:</strong> '
                . esc_html($name) . ' · ' . esc_html($ack['time_utc']) . ' UTC</p>';
        } else {
            echo '<p><strong>Bu sürüm için açık editör onayı bulunmuyor.</strong></p>';
        }

        if (current_user_can('publish_posts')) {
            echo '<div data-kg-safety-review>';
            echo '<label><input type="checkbox" data-kg-safety-confirm> '
                . 'Uyarıyı okudum; bu tarifin mevcut sürümüne ilişkin editoryal kararı onaylıyorum.</label>';
            echo '<p><button type="button" class="button button-secondary" data-kg-safety-submit disabled>'
                . 'Editör Onayını Kaydet</button></p>';
            echo '<p role="status" aria-live="polite" data-kg-safety-result></p>';
            echo '</div>';
        }

        echo '<p><small>Onay, tarifi otomatik yayımlamaz. Yayınla düğmesi ayrıca kullanılır. '
            . 'Yetkili editör onay vermeden de yayımlayabilir; bu durumda yayın geçmişine '
            . '“açık onay yok” kaydı düşülür.</small></p>';

        $history = get_post_meta($post->ID, self::LOG_META, false);
        if (is_array($history) && $history !== []) {
            $last = end($history);
            if (is_array($last)) {
                echo '<p><small>Son riskli yayın: '
                    . esc_html($last['published_at_utc'] ?? '') . ' UTC · '
                    . (!empty($last['explicitly_acknowledged']) ? 'Açık onay var' : 'Açık onay yok')
                    . '</small></p>';
            }
        }
    }

    private function assessPost($post_id) {
        $ingredients = get_post_meta($post_id, '_kg_ingredients', true);
        $ages = wp_get_post_terms($post_id, 'age-group', ['fields' => 'slugs']);
        return self::assess($ingredients, is_wp_error($ages) ? [] : $ages);
    }

    /**
     * Hash the saved recipe version for which the editor saw the warning.
     * Changing recipe text, ingredients, instructions, age groups or warning invalidates prior approval.
     */
    private function signature($post_id, $warning) {
        $ages = wp_get_post_terms($post_id, 'age-group', ['fields' => 'slugs']);
        if (is_wp_error($ages) || !is_array($ages)) {
            $ages = [];
        }
        sort($ages, SORT_STRING);
        $post = get_post($post_id);
        return hash('sha256', serialize([
            'warning' => $warning,
            'title' => $post ? $post->post_title : '',
            'content' => $post ? $post->post_content : '',
            'excerpt' => $post ? $post->post_excerpt : '',
            'ingredients' => get_post_meta($post_id, '_kg_ingredients', true),
            'instructions' => get_post_meta($post_id, '_kg_instructions', true),
            'ages' => $ages,
        ]));
    }

    public static function ackMatches($ack, $fingerprint) {
        return is_array($ack)
            && !empty($ack['user_id'])
            && !empty($ack['signature'])
            && is_string($ack['signature'])
            && hash_equals($fingerprint, $ack['signature']);
    }

    /**
     * Internal acknowledgment operation, called only after AJAX authorization.
     * Public for isolated regression testing; it does not grant publish access.
     */
    public function acknowledgeSavedVersion($post_id, $user_id) {
        $warning = $this->assessPost($post_id);
        if ($warning === null) {
            return false;
        }
        $ack = [
            'user_id' => (int) $user_id,
            'time_utc' => current_time('mysql', true),
            'warning' => $warning,
            'signature' => $this->signature($post_id, $warning),
        ];
        update_post_meta($post_id, self::ACK_META, $ack);
        return $ack;
    }

    /**
     * Live read-only review state for the Gutenberg document panel.
     * Every request is scoped to one recipe and a nonce for that recipe.
     */
    public function ajaxReviewStatus() {
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || !check_ajax_referer('kg_recipe_safety_ack_' . $post_id, 'nonce', false)) {
            wp_send_json_error(['message' => 'Oturum doğrulaması başarısız. Sayfayı yenileyin.'], 403);
        }
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'recipe' || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Bu tarifi görüntüleme yetkiniz yok.'], 403);
        }

        $warning = $this->assessPost($post_id);
        $ack = get_post_meta($post_id, self::ACK_META, true);
        $approved = $warning !== null && self::ackMatches($ack, $this->signature($post_id, $warning));
        $logs = get_post_meta($post_id, self::LOG_META, false);
        $last = is_array($logs) && $logs !== [] ? end($logs) : null;
        wp_send_json_success([
            'warning' => $warning,
            'approved' => $approved,
            'approved_at' => $approved ? ($ack['time_utc'] ?? null) : null,
            'last_publication' => is_array($last) ? [
                'published_at_utc' => $last['published_at_utc'] ?? null,
                'explicitly_acknowledged' => !empty($last['explicitly_acknowledged']),
            ] : null,
        ]);
    }

    public function ajaxAcknowledgeWarning() {
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id || !check_ajax_referer('kg_recipe_safety_ack_' . $post_id, 'nonce', false)) {
            wp_send_json_error(['message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyin.'], 403);
        }
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'recipe'
            || !current_user_can('edit_post', $post_id) || !current_user_can('publish_posts')) {
            wp_send_json_error(['message' => 'Bu tarife onay verme yetkiniz yok.'], 403);
        }

        $ack = $this->acknowledgeSavedVersion($post_id, get_current_user_id());
        if ($ack === false) {
            wp_send_json_error(['message' => 'Kaydedilmiş sürümde onay bekleyen uyarı bulunamadı.'], 409);
        }
        wp_send_json_success([
            'message' => 'Editoryal onay kaydedildi. Tarifi yayınlamak için ayrıca Yayınla seçeneğini kullanın.',
            'time_utc' => $ack['time_utc'],
        ]);
    }

    public function recordScheduledPublication($new_status, $old_status, $post) {
        if ($post->post_type !== 'recipe' || $old_status !== 'future' || $new_status !== 'publish') {
            return;
        }
        // For wp_publish_post() there is no wp_after_insert_post callback.
        // If this runs as part of wp_update_post(), idempotency in recordReview
        // avoids a second log entry when wp_after_insert_post follows.
        $previous = clone $post;
        $previous->post_status = $old_status;
        $published = clone $post;
        $published->post_status = $new_status;
        $this->recordReview($post->ID, $published, true, $previous);
    }

    /**
     * Keep warning and actual publication decisions separate. A publication
     * without explicit acknowledgment remains possible and is recorded as such.
     *
     * $post_before is null for newly inserted posts.
     */
    public function recordReview($post_id, $post, $update, $post_before = null) {
        if ($post->post_type !== 'recipe' || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $warning = $this->assessPost($post_id);
        update_post_meta($post_id, self::WARNING_META, $warning === null ? '' : $warning);

        $ack = get_post_meta($post_id, self::ACK_META, true);
        $fingerprint = $warning === null ? '' : $this->signature($post_id, $warning);
        if (!self::ackMatches($ack, $fingerprint)) {
            // Invalidate an old sign-off even if the author later restores
            // previous content. No silent approval carryover.
            delete_post_meta($post_id, self::ACK_META);
            $ack = false;
        }

        if ($post->post_status !== 'publish') {
            delete_post_meta($post_id, self::LAST_SIGNATURE_META);
            return;
        }
        if ($warning === null) {
            delete_post_meta($post_id, self::LAST_SIGNATURE_META);
            return;
        }

        $first_publication = !is_object($post_before)
            || $post_before->post_status !== 'publish';
        $previous_signature = get_post_meta($post_id, self::LAST_SIGNATURE_META, true);
        // WP-Cron can emit both a transition hook and an insert callback.
        // The same published version must never create duplicate audit entries.
        if ($previous_signature === $fingerprint) {
            return;
        }

        $explicit = self::ackMatches($ack, $fingerprint);
        add_post_meta($post_id, self::LOG_META, [
            'published_at_utc' => current_time('mysql', true),
            'publisher_user_id' => get_current_user_id(),
            'warning' => $warning,
            'signature' => $fingerprint,
            'explicitly_acknowledged' => $explicit,
            'acknowledging_user_id' => $explicit ? (int) $ack['user_id'] : 0,
            'acknowledged_at_utc' => $explicit ? $ack['time_utc'] : null,
            'publication_type' => $first_publication ? 'publish_transition' : 'published_revision',
        ]);
        update_post_meta($post_id, self::LAST_SIGNATURE_META, $fingerprint);
        // Explicit sign-off applies to this publication, not future revisions.
        delete_post_meta($post_id, self::ACK_META);
    }

    /**
     * Limited deterministic warning for explicit ingredient names.
     * Never infer that ingredients are allergen-free or safe to swallow.
     */
    public static function assess($ingredients, $age_slugs) {
        if (!is_array($ingredients) || $ingredients === []) {
            return 'Tarifin malzeme listesi eksik. Yayın öncesi kontrol önerilir.';
        }
        $names = [];
        foreach ($ingredients as $ingredient) {
            $name = is_array($ingredient)
                ? ($ingredient['name'] ?? $ingredient['item'] ?? '')
                : $ingredient;
            if (!is_string($name) || trim($name) === '') {
                return 'Malzeme listesinde geçersiz satır var.';
            }
            $names[] = $name;
        }

        $text = implode(' ', $names);
        // Distinguish honey (bal, ballı) from pumpkin (bal kabağı)
        // and fish (balık). An exact-token detector is deliberately narrow.
        $without_pumpkin = preg_replace('/(?<![\p{L}])bal\s+kaba(?:ğ|g)ı(?![\p{L}])/iu', '', $text);
        $has_honey = preg_match('/(?<![\p{L}])(?:bal|ballı|balli)(?![\p{L}])/iu', $without_pumpkin) === 1;
        if (!$has_honey) {
            return null;
        }
        if (!is_array($age_slugs) || $age_slugs === []) {
            return 'Bal içeren tarifin yaş grubu doğrulanamadı.';
        }
        // Both the live age taxonomy defaults and legacy AI-generated slugs
        // appear in KidsGourmet content. Unknown slugs must remain visible as
        // warnings rather than being silently classified as 12 months or older.
        $under_12 = [
            '0-6-ay-sadece-sut',
            '6-8-ay-baslangic',
            '9-11-ay-kesif',
            'hazirlk-evresi-0-6-ay',
            'baslangic-tadim-6-8-ay',
            'kesif-puturlye-gecis-9-11-ay',
        ];
        $known_12_plus = [
            '12-24-ay-gecis',
            '2-yas-ve-uzeri',
            'aile-sofrasina-gecis-12-24-ay',
            'cocuk-gurme-2-yas',
        ];
        foreach ($age_slugs as $age) {
            if (!is_string($age) || in_array($age, $under_12, true)) {
                return 'Bal içeren tarifler 12 aydan küçük çocuklara önerilemez.';
            }
            if (!in_array($age, $known_12_plus, true)) {
                return 'Bal içeren tarifte tanımlanmamış bir yaş grubu var; editör kontrolü önerilir.';
            }
        }
        return null;
    }
}

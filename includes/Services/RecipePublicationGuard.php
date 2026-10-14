<?php
namespace KG_Core\Services;

/**
 * Conservative pre-publication checks using values already saved in WordPress,
 * or supplied in the classic editor request. Never claims a complete medical audit.
 */
class RecipePublicationGuard {
    public function __construct() {
        add_action('add_meta_boxes_recipe', [$this, 'registerNoticeBox']);
        add_action('wp_after_insert_post', [$this, 'recordReview'], 100, 3);
    }

    public function registerNoticeBox() {
        add_meta_box('kg_recipe_safety_review', 'Beslenme Güvenliği', [$this, 'renderNoticeBox'], 'recipe', 'side', 'high');
    }

    public function renderNoticeBox($post) {
        $warning = $this->assessPost($post->ID);
        if ($warning === null) {
            echo '<p>Tanımlı kontrollerde uyarı bulunamadı. Bu sonuç tıbbi onay değildir.</p>';
        } else {
            echo '<p><strong>Kontrol önerilir:</strong> ' . esc_html($warning) . '</p>';
            echo '<p>Yayın kararı yetkili editöre aittir. Uyarıya rağmen yayınlanırsa karar kayıt altına alınır.</p>';
        }
    }

    private function assessPost($post_id) {
        $ingredients = get_post_meta($post_id, '_kg_ingredients', true);
        $ages = wp_get_post_terms($post_id, 'age-group', ['fields' => 'slugs']);
        return self::assess($ingredients, is_wp_error($ages) ? [] : $ages);
    }

    public function recordReview($post_id, $post, $update) {
        if ($post->post_type !== 'recipe' || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        $warning = $this->assessPost($post_id);
        update_post_meta($post_id, '_kg_recipe_safety_warning', $warning === null ? '' : $warning);
        if ($post->post_status !== 'publish' || $warning === null) {
            return;
        }
        $signature = hash('sha256', $warning . '|' . serialize(get_post_meta($post_id, '_kg_ingredients', true)) . '|' . serialize(wp_get_post_terms($post_id, 'age-group', ['fields' => 'slugs'])));
        if (get_post_meta($post_id, '_kg_recipe_safety_audit_signature', true) === $signature) {
            return;
        }
        $user_id = get_current_user_id();
        add_post_meta($post_id, '_kg_recipe_safety_approval_log', [
            'user_id' => $user_id,
            'timestamp' => current_time('mysql', true),
            'warning' => $warning,
            'context' => $user_id ? 'editor_or_api' : 'system_or_scheduled',
        ]);
        update_post_meta($post_id, '_kg_recipe_safety_audit_signature', $signature);
    }

    public static function assess($ingredients, $age_slugs) {
        if (!is_array($ingredients) || $ingredients === []) {
            return 'Tarifin malzeme listesi eksik. Yayın öncesi kontrol gerekli.';
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

        $joined = implode(' ', $names);
        $has_honey = preg_match('/(?<![\\p{L}])bal(?![\\p{L}])/iu', $joined) === 1;
        if (!$has_honey) {
            return null;
        }
        if (!is_array($age_slugs) || $age_slugs === []) {
            return 'Bal içeren tarifin yaş grubu doğrulanamadı.';
        }
        foreach (['hazirlk-evresi-0-6-ay', 'baslangic-tadim-6-8-ay', 'kesif-puturlye-gecis-9-11-ay'] as $under_12) {
            if (in_array($under_12, $age_slugs, true)) {
                return 'Bal içeren tarifler 12 aydan küçük çocuklara önerilemez.';
            }
        }
        return null;
    }

}

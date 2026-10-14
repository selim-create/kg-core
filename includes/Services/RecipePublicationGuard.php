<?php
namespace KG_Core\Services;

/**
 * Conservative pre-publication checks using values already saved in WordPress,
 * or supplied in the classic editor request. Never claims a complete medical audit.
 */
class RecipePublicationGuard {
    public function __construct() {
        add_filter('wp_insert_post_data', [$this, 'guardClassicPublish'], 20, 2);
        add_action('admin_notices', [$this, 'showNotice']);
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

    public function guardClassicPublish($data, $postarr) {
        if (($data['post_type'] ?? '') !== 'recipe' || ($data['post_status'] ?? '') !== 'publish') {
            return $data;
        }
        // Keep already-published updates unchanged until the full REST/classic
        // save flows can be validated in staging, to avoid surprise unpublishing.
        $post_id = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if ($post_id > 0 && get_post_status($post_id) === 'publish') {
            return $data;
        }

        $ingredients = isset($_POST['kg_ingredients']) && is_array($_POST['kg_ingredients'])
            ? wp_unslash($_POST['kg_ingredients'])
            : ($post_id ? get_post_meta($post_id, '_kg_ingredients', true) : []);

        $ages = [];
        if ($post_id > 0) {
            $terms = wp_get_post_terms($post_id, 'age-group', ['fields' => 'slugs']);
            if (!is_wp_error($terms) && is_array($terms)) {
                $ages = $terms;
            }
        }
        // The classic editor can include taxonomy term ids not saved yet.
        // Resolve them before deciding; never silently assume an infant age.
        if (isset($_POST['tax_input']['age-group']) && is_array($_POST['tax_input']['age-group'])) {
            foreach (wp_unslash($_POST['tax_input']['age-group']) as $term_id) {
                $term = get_term((int) $term_id, 'age-group');
                if ($term && !is_wp_error($term)) {
                    $ages[] = $term->slug;
                }
            }
        }
        $reason = self::assess($ingredients, array_unique($ages));
        if ($reason !== null) {
            $data['post_status'] = 'draft';
            if (function_exists('set_transient') && is_user_logged_in()) {
                set_transient('kg_recipe_publication_block_' . get_current_user_id(), $reason, 120);
            }
        }
        return $data;
    }

    public function showNotice() {
        if (!is_user_logged_in()) {
            return;
        }
        $key = 'kg_recipe_publication_block_' . get_current_user_id();
        $reason = get_transient($key);
        if (!$reason) {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-error"><p><strong>Tarif taslakta tutuldu:</strong> '
            . esc_html($reason) . '</p></div>';
    }
}

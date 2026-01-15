<?php
namespace KG_Core\Migration;

class FieldConsolidation {
    
    /**
     * Ana migrasyon fonksiyonu - tüm ingredient'ları işle
     */
    public function run() {
        $results = [
            'processed' => 0,
            'category_migrated' => 0,
            'nutrition_migrated' => 0,
            'errors' => []
        ];

        $ingredients = get_posts([
            'post_type' => 'ingredient',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        foreach ($ingredients as $ingredient) {
            $this->migrate_category($ingredient->ID, $results);
            $this->migrate_nutrition($ingredient->ID, $results);
            $this->cleanup_deprecated_fields($ingredient->ID);
            $results['processed']++;
        }

        return $results;
    }

    /**
     * _kg_category meta field'ını ingredient-category taxonomy'ye migrate et
     */
    private function migrate_category($post_id, &$results) {
        // Zaten taxonomy atanmış mı kontrol et
        $existing_terms = wp_get_post_terms($post_id, 'ingredient-category');
        if (!empty($existing_terms) && !is_wp_error($existing_terms)) {
            return; // Zaten taxonomy var, atla
        }

        // Meta field'dan kategori al
        $meta_category = get_post_meta($post_id, '_kg_category', true);
        if (empty($meta_category)) {
            return;
        }

        // Mapping: Meta field değeri -> Taxonomy term
        $category_mapping = [
            'Meyveler' => 'Meyveler',
            'Sebzeler' => 'Sebzeler',
            'Proteinler' => 'Proteinler',
            'Tahıllar' => 'Tahıllar',
            'Süt Ürünleri' => 'Süt Ürünleri',
            'Baklagiller' => 'Baklagiller',
            'Yağlar' => 'Yağlar',
            'Sıvılar' => 'Sıvılar',
            'Baharatlar' => 'Baharatlar',
            'Özel Ürünler' => 'Özel Ürünler',
            // Eski değerler için fallback
            'Protein' => 'Proteinler',
            'Meyve' => 'Meyveler',
            'Sebze' => 'Sebzeler',
            'Tahıl' => 'Tahıllar',
        ];

        $target_term = isset($category_mapping[$meta_category]) 
            ? $category_mapping[$meta_category] 
            : $meta_category;

        // Taxonomy term'i bul veya oluştur
        $term = get_term_by('name', $target_term, 'ingredient-category');
        if (!$term) {
            $term = get_term_by('slug', sanitize_title($target_term), 'ingredient-category');
        }

        if ($term) {
            $result = wp_set_post_terms($post_id, [$term->term_id], 'ingredient-category');
            if (!is_wp_error($result)) {
                $results['category_migrated']++;
            }
        }
    }

    /**
     * Eski besin değerlerini yeni 100g formatına migrate et
     */
    private function migrate_nutrition($post_id, &$results) {
        $old_to_new_mapping = [
            '_kg_calories' => '_kg_ing_calories_100g',
            '_kg_protein' => '_kg_ing_protein_100g',
            '_kg_carbs' => '_kg_ing_carbs_100g',
            '_kg_fat' => '_kg_ing_fat_100g',
            '_kg_fiber' => '_kg_ing_fiber_100g',
            '_kg_vitamins' => '_kg_ing_vitamins',
        ];

        $migrated = false;
        foreach ($old_to_new_mapping as $old_key => $new_key) {
            $old_value = get_post_meta($post_id, $old_key, true);
            $new_value = get_post_meta($post_id, $new_key, true);

            // Yeni alan boşsa ve eski alanda değer varsa, migrate et
            if (!empty($old_value) && empty($new_value)) {
                update_post_meta($post_id, $new_key, $old_value);
                $migrated = true;
            }
        }

        if ($migrated) {
            $results['nutrition_migrated']++;
        }
    }

    /**
     * Kullanılmayan/deprecated alanları temizle
     */
    private function cleanup_deprecated_fields($post_id) {
        $deprecated_fields = [
            // Kategori - artık taxonomy kullanılıyor
            '_kg_category',
            
            // Eski besin değerleri - 100g formatına taşındı
            '_kg_calories',
            '_kg_protein', 
            '_kg_carbs',
            '_kg_fat',
            '_kg_fiber',
            // _kg_vitamins yeni formatta da var, silme
            
            // Mükerrer alerjen alanları
            '_kg_is_allergen',
            '_kg_allergen_type',
            
            // Mükerrer hazırlama alanları
            '_kg_prep_methods_list',
            '_kg_prep_tips',
            '_kg_cooking_suggestions',
        ];

        foreach ($deprecated_fields as $field) {
            delete_post_meta($post_id, $field);
        }
    }

    /**
     * Preview changes without applying them
     */
    public function preview() {
        $results = [
            'total_ingredients' => 0,
            'will_migrate_category' => 0,
            'will_migrate_nutrition' => 0,
            'has_deprecated_fields' => 0,
        ];

        $ingredients = get_posts([
            'post_type' => 'ingredient',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        $results['total_ingredients'] = count($ingredients);

        foreach ($ingredients as $ingredient) {
            // Check category migration
            $existing_terms = wp_get_post_terms($ingredient->ID, 'ingredient-category');
            $meta_category = get_post_meta($ingredient->ID, '_kg_category', true);
            if ((empty($existing_terms) || is_wp_error($existing_terms)) && !empty($meta_category)) {
                $results['will_migrate_category']++;
            }

            // Check nutrition migration
            $old_nutrition_fields = ['_kg_calories', '_kg_protein', '_kg_carbs', '_kg_fat', '_kg_fiber'];
            foreach ($old_nutrition_fields as $old_field) {
                $old_value = get_post_meta($ingredient->ID, $old_field, true);
                if (!empty($old_value)) {
                    $results['will_migrate_nutrition']++;
                    break;
                }
            }

            // Check deprecated fields
            $deprecated_fields = ['_kg_category', '_kg_is_allergen', '_kg_allergen_type', '_kg_prep_methods_list', '_kg_prep_tips', '_kg_cooking_suggestions'];
            foreach ($deprecated_fields as $field) {
                $value = get_post_meta($ingredient->ID, $field, true);
                if (!empty($value)) {
                    $results['has_deprecated_fields']++;
                    break;
                }
            }
        }

        return $results;
    }
}

<?php
namespace KG_Core\Taxonomies;

/**
 * IngredientCategory Taxonomy
 * Categorizes ingredients (Liquids, Oils, Special Products, etc.)
 */
class IngredientCategory {

    public function __construct() {
        add_action('init', [$this, 'register_taxonomy']);
        add_action('init', [$this, 'add_default_categories'], 20);
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x('Malzeme Kategorileri', 'taxonomy general name', 'kg-core'),
            'singular_name'     => _x('Malzeme Kategorisi', 'taxonomy singular name', 'kg-core'),
            'search_items'      => __('Kategori Ara', 'kg-core'),
            'all_items'         => __('Tüm Kategoriler', 'kg-core'),
            'parent_item'       => __('Üst Kategori', 'kg-core'),
            'parent_item_colon' => __('Üst Kategori:', 'kg-core'),
            'edit_item'         => __('Kategoriyi Düzenle', 'kg-core'),
            'update_item'       => __('Kategoriyi Güncelle', 'kg-core'),
            'add_new_item'      => __('Yeni Kategori Ekle', 'kg-core'),
            'new_item_name'     => __('Yeni Kategori Adı', 'kg-core'),
            'menu_name'         => __('Malzeme Kategorileri', 'kg-core'),
        ];

        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'ingredient-category'],
        ];

        register_taxonomy('ingredient-category', ['ingredient'], $args);
    }

    /**
     * Add default ingredient categories
     */
    public function add_default_categories() {
        $default_categories = [
            'Meyveler' => 'Taze ve kuru meyveler',
            'Sebzeler' => 'Taze sebzeler',
            'Proteinler' => 'Et, balık, tavuk, yumurta',
            'Tahıllar' => 'Pirinç, bulgur, makarna, un',
            'Süt Ürünleri' => 'Süt, yoğurt, peynir',
            'Baklagiller' => 'Mercimek, nohut, fasulye',
            'Yağlar' => 'Zeytinyağı, tereyağı, hindistan cevizi yağı',
            'Sıvılar' => 'Su, sebze suyu, nohut suyu',
            'Baharatlar' => 'Kimyon, tarçın, zerdeçal',
            'Özel Ürünler' => 'Formül mama, karbonat, keçiboynuzu unu',
        ];

        foreach ($default_categories as $name => $description) {
            if (!term_exists($name, 'ingredient-category')) {
                wp_insert_term(
                    $name,
                    'ingredient-category',
                    [
                        'description' => $description,
                        'slug'        => sanitize_title($name)
                    ]
                );
            }
        }
    }
}

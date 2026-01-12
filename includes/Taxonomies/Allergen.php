<?php
namespace KG_Core\Taxonomies;

class Allergen {
    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'init', [ $this, 'insert_default_terms' ] );
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x( 'Alerjenler', 'taxonomy general name', 'kg-core' ),
            'singular_name'     => _x( 'Alerjen', 'taxonomy singular name', 'kg-core' ),
            'search_items'      => __( 'Alerjen Ara', 'kg-core' ),
            'all_items'         => __( 'Tüm Alerjenler', 'kg-core' ),
            'edit_item'         => __( 'Düzenle', 'kg-core' ),
            'update_item'       => __( 'Güncelle', 'kg-core' ),
            'add_new_item'      => __( 'Yeni Ekle', 'kg-core' ),
            'new_item_name'     => __( 'Yeni Alerjen Adı', 'kg-core' ),
            'menu_name'         => __( 'Alerjenler', 'kg-core' ),
        ];

        register_taxonomy( 'allergen', ['recipe', 'ingredient'], [
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'query_var' => true,
            'rewrite' => [ 'slug' => 'allergen' ],
        ]);
    }

    public function insert_default_terms() {
        // Only insert if terms don't exist yet
        $existing = get_terms( [
            'taxonomy' => 'allergen',
            'hide_empty' => false,
        ] );

        if ( empty( $existing ) ) {
            $default_allergens = [
                'Süt',
                'Yumurta',
                'Gluten',
                'Fıstık',
                'Balık',
                'Soya',
                'Kabuklu Deniz Ürünleri',
                'Fındık',
                'Susam',
                'Hardal',
            ];

            foreach ( $default_allergens as $allergen ) {
                if ( ! term_exists( $allergen, 'allergen' ) ) {
                    wp_insert_term( $allergen, 'allergen' );
                }
            }
        }
    }
}
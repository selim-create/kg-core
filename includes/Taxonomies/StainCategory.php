<?php
namespace KG_Core\Taxonomies;

class StainCategory {

    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'init', [ $this, 'insert_default_terms' ] );
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x( 'Leke Kategorileri', 'taxonomy general name', 'kg-core' ),
            'singular_name'     => _x( 'Leke Kategorisi', 'taxonomy singular name', 'kg-core' ),
            'search_items'      => __( 'Kategori Ara', 'kg-core' ),
            'all_items'         => __( 'Tüm Kategoriler', 'kg-core' ),
            'edit_item'         => __( 'Düzenle', 'kg-core' ),
            'update_item'       => __( 'Güncelle', 'kg-core' ),
            'add_new_item'      => __( 'Yeni Ekle', 'kg-core' ),
            'new_item_name'     => __( 'Yeni Kategori Adı', 'kg-core' ),
            'menu_name'         => __( 'Kategoriler', 'kg-core' ),
        ];

        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'stain-category' ],
            'show_in_rest'      => true,
        ];

        register_taxonomy( 'stain_category', [ 'stain' ], $args );
    }

    public function insert_default_terms() {
        // Only insert if terms don't exist yet
        $existing = get_terms( [
            'taxonomy' => 'stain_category',
            'hide_empty' => false,
        ] );

        if ( ! empty( $existing ) ) {
            return;
        }

        $default_terms = [
            [
                'name' => 'Yemek Lekeleri',
                'slug' => 'food',
            ],
            [
                'name' => 'Vücut Sıvıları',
                'slug' => 'bodily',
            ],
            [
                'name' => 'Dış Mekan',
                'slug' => 'outdoor',
            ],
            [
                'name' => 'Sanat/Oyun',
                'slug' => 'craft',
            ],
            [
                'name' => 'Ev İçi',
                'slug' => 'household',
            ],
        ];

        foreach ( $default_terms as $term_data ) {
            wp_insert_term( $term_data['name'], 'stain_category', [
                'slug' => $term_data['slug'],
            ] );
        }
    }
}

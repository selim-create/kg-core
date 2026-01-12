<?php
namespace KG_Core\Taxonomies;

class DietType {
    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'init', [ $this, 'insert_default_terms' ] );
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x( 'Diyet Tipleri', 'taxonomy general name', 'kg-core' ),
            'singular_name'     => _x( 'Diyet Tipi', 'taxonomy singular name', 'kg-core' ),
            'search_items'      => __( 'Diyet Tipi Ara', 'kg-core' ),
            'all_items'         => __( 'Tüm Diyet Tipleri', 'kg-core' ),
            'edit_item'         => __( 'Düzenle', 'kg-core' ),
            'update_item'       => __( 'Güncelle', 'kg-core' ),
            'add_new_item'      => __( 'Yeni Ekle', 'kg-core' ),
            'new_item_name'     => __( 'Yeni Diyet Tipi Adı', 'kg-core' ),
            'menu_name'         => __( 'Diyet Tipleri', 'kg-core' ),
        ];

        register_taxonomy( 'diet-type', ['recipe'], [
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'query_var' => true,
            'rewrite' => [ 'slug' => 'diet-type' ],
        ]);
    }

    public function insert_default_terms() {
        // Only insert if terms don't exist yet
        $existing = get_terms( [
            'taxonomy' => 'diet-type',
            'hide_empty' => false,
        ] );

        if ( empty( $existing ) ) {
            $default_diet_types = [
                'BLW (Baby-Led Weaning)',
                'Püre',
                'Vegan',
                'Vejetaryen',
                'Glutensiz',
                'Şekersiz',
                'Tuzsuz',
                'Laktozsuz',
            ];

            foreach ( $default_diet_types as $diet_type ) {
                if ( ! term_exists( $diet_type, 'diet-type' ) ) {
                    wp_insert_term( $diet_type, 'diet-type' );
                }
            }
        }
    }
}
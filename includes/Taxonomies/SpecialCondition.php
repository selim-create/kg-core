<?php
namespace KG_Core\Taxonomies;

class SpecialCondition {

    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'init', [ $this, 'insert_default_terms' ] );
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x( 'Özel Durumlar', 'taxonomy general name', 'kg-core' ),
            'singular_name'     => _x( 'Özel Durum', 'taxonomy singular name', 'kg-core' ),
            'search_items'      => __( 'Özel Durum Ara', 'kg-core' ),
            'all_items'         => __( 'Tüm Özel Durumlar', 'kg-core' ),
            'edit_item'         => __( 'Düzenle', 'kg-core' ),
            'update_item'       => __( 'Güncelle', 'kg-core' ),
            'add_new_item'      => __( 'Yeni Ekle', 'kg-core' ),
            'new_item_name'     => __( 'Yeni Özel Durum Adı', 'kg-core' ),
            'menu_name'         => __( 'Özel Durumlar', 'kg-core' ),
        ];

        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'special-condition' ],
            'show_in_rest'      => true,
        ];

        register_taxonomy( 'special-condition', [ 'recipe' ], $args );
    }

    public function insert_default_terms() {
        $existing = get_terms( [
            'taxonomy' => 'special-condition',
            'hide_empty' => false,
        ] );

        if ( ! empty( $existing ) ) {
            return;
        }

        $default_terms = [
            [ 'name' => 'Kabızlık Giderici', 'slug' => 'kabizlik-giderici' ],
            [ 'name' => 'Bağışıklık Dostu', 'slug' => 'bagisiklik-dostu' ],
            [ 'name' => 'Diş Çıkarma Dönemi', 'slug' => 'dis-cikarma' ],
            [ 'name' => 'Alerjik Bebek', 'slug' => 'alerjik-bebek' ],
        ];

        foreach ( $default_terms as $term_data ) {
            wp_insert_term( $term_data['name'], 'special-condition', [
                'slug' => $term_data['slug'],
            ] );
        }
    }
}

<?php
namespace KG_Core\Taxonomies;

class AgeGroup {

    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
    }

    public function register_taxonomy() {
        $labels = [
            'name'              => _x( 'Yaş Grupları', 'taxonomy general name', 'kg-core' ),
            'singular_name'     => _x( 'Yaş Grubu', 'taxonomy singular name', 'kg-core' ),
            'search_items'      => __( 'Yaş Grubu Ara', 'kg-core' ),
            'all_items'         => __( 'Tüm Yaş Grupları', 'kg-core' ),
            'edit_item'         => __( 'Düzenle', 'kg-core' ),
            'update_item'       => __( 'Güncelle', 'kg-core' ),
            'add_new_item'      => __( 'Yeni Ekle', 'kg-core' ),
            'new_item_name'     => __( 'Yeni Yaş Grubu Adı', 'kg-core' ),
            'menu_name'         => __( 'Yaş Grupları', 'kg-core' ),
        ];

        $args = [
            'hierarchical'      => true, // Kategori gibi hiyerarşik
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'age-group' ],
            'show_in_rest'      => true,
        ];

        // Sadece 'recipe' CPT'sine bağlıyoruz
        register_taxonomy( 'age-group', [ 'recipe' ], $args );
    }
}
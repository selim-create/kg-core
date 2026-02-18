<?php
namespace KG_Core\PostTypes;

class Recipe {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type() {
        $labels = [
            'name'                  => _x( 'Tarifler', 'Post Type General Name', 'kg-core' ),
            'singular_name'         => _x( 'Tarif', 'Post Type Singular Name', 'kg-core' ),
            'menu_name'             => __( 'Tarifler', 'kg-core' ),
            'all_items'             => __( 'Tüm Tarifler', 'kg-core' ),
            'add_new_item'          => __( 'Yeni Tarif Ekle', 'kg-core' ),
            'edit_item'             => __( 'Tarifi Düzenle', 'kg-core' ),
            'view_item'             => __( 'Tarifi Görüntüle', 'kg-core' ),
        ];

        $args = [
            'label'                 => __( 'Tarif', 'kg-core' ),
            'labels'                => $labels,
            'supports'              => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions' ],
            'taxonomies'            => [ 'age-group', 'allergen', 'diet-type' ],
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-carrot', // Havuç ikonu
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true, // Headless için kritik!
            'rest_base'             => 'recipes',
        ];

        register_post_type( 'recipe', $args );
    }
}
<?php
namespace KG_Core\PostTypes;

class Ingredient {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type() {
        $labels = [
            'name'                  => _x( 'Malzemeler', 'Post Type General Name', 'kg-core' ),
            'singular_name'         => _x( 'Malzeme', 'Post Type Singular Name', 'kg-core' ),
            'menu_name'             => __( 'Malzeme Rehberi', 'kg-core' ),
            'add_new_item'          => __( 'Yeni Malzeme Ekle', 'kg-core' ),
            'edit_item'             => __( 'Malzemeyi Düzenle', 'kg-core' ),
        ];

        $args = [
            'label'                 => __( 'Malzeme', 'kg-core' ),
            'labels'                => $labels,
            'supports'              => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'taxonomies'            => [ 'allergen' ], // Alerjenlerle ilişkilendirilebilir
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 6,
            'menu_icon'             => 'dashicons-cart',
            'show_in_rest'          => true,
            'rest_base'             => 'ingredients',
        ];

        register_post_type( 'ingredient', $args );
    }
}
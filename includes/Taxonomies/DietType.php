<?php
namespace KG_Core\Taxonomies;

class DietType {
    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
    }

    public function register_taxonomy() {
        register_taxonomy( 'diet-type', ['recipe'], [
            'labels' => [
                'name' => 'Diyet Tipleri', // BLW, Püre vb.
                'singular_name' => 'Diyet Tipi'
            ],
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => true
        ]);
    }
}
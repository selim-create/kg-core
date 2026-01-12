<?php
namespace KG_Core\Taxonomies;

class Allergen {
    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
    }

    public function register_taxonomy() {
        register_taxonomy( 'allergen', ['recipe', 'ingredient'], [
            'labels' => [
                'name' => 'Alerjenler',
                'singular_name' => 'Alerjen'
            ],
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => true
        ]);
    }
}
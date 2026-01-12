<?php
namespace KG_Core\PostTypes;

class Tool {
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type() {
        register_post_type( 'tool', [
            'labels' => [
                'name' => 'Araçlar',
                'singular_name' => 'Araç'
            ],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-calculator'
        ]);
    }
}
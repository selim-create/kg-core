<?php
namespace KG_Core\PostTypes;

class Stain {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_filter( 'manage_stain_posts_columns', [ $this, 'add_custom_columns' ] );
        add_action( 'manage_stain_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
    }

    public function register_post_type() {
        $labels = [
            'name'                  => _x( 'Lekeler', 'Post Type General Name', 'kg-core' ),
            'singular_name'         => _x( 'Leke', 'Post Type Singular Name', 'kg-core' ),
            'menu_name'             => __( 'Leke Ansiklopedisi', 'kg-core' ),
            'all_items'             => __( 'Tüm Lekeler', 'kg-core' ),
            'add_new_item'          => __( 'Yeni Leke Ekle', 'kg-core' ),
            'edit_item'             => __( 'Lekeyi Düzenle', 'kg-core' ),
            'view_item'             => __( 'Lekeyi Görüntüle', 'kg-core' ),
            'search_items'          => __( 'Leke Ara', 'kg-core' ),
        ];

        $args = [
            'label'                 => __( 'Leke', 'kg-core' ),
            'labels'                => $labels,
            'supports'              => [ 'title', 'editor' ],
            'taxonomies'            => [ 'stain_category' ],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 7,
            'menu_icon'             => 'dashicons-shirt',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rest_base'             => 'stains',
        ];

        register_post_type( 'stain', $args );
    }

    /**
     * Add custom columns to stain list
     */
    public function add_custom_columns( $columns ) {
        // Insert custom columns after title
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( $key === 'title' ) {
                $new_columns['emoji'] = __( 'Emoji', 'kg-core' );
                $new_columns['stain_category'] = __( 'Kategori', 'kg-core' );
                $new_columns['difficulty'] = __( 'Zorluk', 'kg-core' );
                $new_columns['step_count'] = __( 'Adım Sayısı', 'kg-core' );
            }
        }
        return $new_columns;
    }

    /**
     * Render custom column values
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'emoji':
                $emoji = get_post_meta( $post_id, '_kg_stain_emoji', true );
                echo esc_html( $emoji );
                break;

            case 'stain_category':
                $terms = get_the_terms( $post_id, 'stain_category' );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    $term_names = array_map( function( $term ) {
                        return $term->name;
                    }, $terms );
                    echo esc_html( implode( ', ', $term_names ) );
                }
                break;

            case 'difficulty':
                $difficulty = get_post_meta( $post_id, '_kg_stain_difficulty', true );
                $labels = [
                    'easy' => '<span style="background:#4CAF50;color:white;padding:2px 8px;border-radius:3px;">Kolay</span>',
                    'medium' => '<span style="background:#FF9800;color:white;padding:2px 8px;border-radius:3px;">Orta</span>',
                    'hard' => '<span style="background:#F44336;color:white;padding:2px 8px;border-radius:3px;">Zor</span>',
                ];
                echo isset( $labels[ $difficulty ] ) ? $labels[ $difficulty ] : '';
                break;

            case 'step_count':
                $steps = get_post_meta( $post_id, '_kg_stain_steps', true );
                $steps_array = json_decode( $steps, true );
                if ( is_array( $steps_array ) ) {
                    echo count( $steps_array );
                } else {
                    echo '0';
                }
                break;
        }
    }
}

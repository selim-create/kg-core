<?php
namespace KG_Core\PostTypes;

class Ingredient {

    /**
     * Track featured-image changes during the current request.
     *
     * This lets us clear stale attribution again at the end of an admin save in
     * case a meta box re-saves values that belonged to the previous image.
     *
     * @var array<int,bool>
     */
    private $featured_image_changed = [];

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );

        // WordPress stores the featured image as the _thumbnail_id post meta.
        // Listen globally (not only in wp-admin) so REST/API image changes are
        // handled as well.
        add_action( 'added_post_meta', [ $this, 'handle_featured_image_meta_change' ], 10, 4 );
        add_action( 'updated_post_meta', [ $this, 'handle_featured_image_meta_change' ], 10, 4 );
        add_action( 'deleted_post_meta', [ $this, 'handle_featured_image_meta_change' ], 10, 4 );

        // If the featured image changed before save_post fires, clear once more
        // after other ingredient save handlers have run. This prevents an admin
        // form from accidentally restoring the previous image's credit.
        add_action( 'save_post_ingredient', [ $this, 'finalize_featured_image_change' ], 999, 3 );
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

    /**
     * Clear image attribution when the ingredient featured image changes.
     *
     * This catches changes made from the WordPress editor, REST API and any
     * integration that ultimately updates the standard _thumbnail_id field.
     * Automated image-generation flows remain compatible because they set the
     * featured image first and write the new attribution afterwards.
     *
     * @param mixed  $meta_id_or_ids Meta ID, or IDs for deleted_post_meta.
     * @param int    $post_id        Post ID.
     * @param string $meta_key       Meta key.
     * @param mixed  $meta_value     Meta value.
     */
    public function handle_featured_image_meta_change( $meta_id_or_ids, $post_id, $meta_key, $meta_value ) {
        if ( '_thumbnail_id' !== $meta_key || 'ingredient' !== get_post_type( $post_id ) ) {
            return;
        }

        $this->featured_image_changed[ (int) $post_id ] = true;
        $this->clear_image_attribution( $post_id );
    }

    /**
     * Clear attribution after all normal ingredient save handlers when needed.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an existing post update.
     */
    public function finalize_featured_image_change( $post_id, $post, $update ) {
        if ( empty( $this->featured_image_changed[ (int) $post_id ] ) ) {
            return;
        }

        $this->clear_image_attribution( $post_id );
        unset( $this->featured_image_changed[ (int) $post_id ] );
    }

    /**
     * Remove attribution that belongs to a previous featured image.
     *
     * @param int $post_id Post ID.
     */
    private function clear_image_attribution( $post_id ) {
        delete_post_meta( $post_id, '_kg_image_source' );
        delete_post_meta( $post_id, '_kg_image_credit' );
        delete_post_meta( $post_id, '_kg_image_credit_url' );
    }
}

<?php
namespace KG_Core\PostTypes;

class Discussion {

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_expert_role' ] );
        add_filter( 'wp_insert_post_data', [ $this, 'force_pending_status' ], 10, 2 );
    }

    /**
     * Register 'Expert' user role
     */
    public function register_expert_role() {
        if ( ! get_role( 'expert' ) ) {
            add_role( 'expert', __( 'Uzman/Diyetisyen', 'kg-core' ), [
                'read'         => true,
                'edit_posts'   => true,
                'delete_posts' => false,
            ] );
        }
    }

    public function register_post_type() {
        $labels = [
            'name'                  => _x( 'Sorular', 'Post Type General Name', 'kg-core' ),
            'singular_name'         => _x( 'Soru', 'Post Type Singular Name', 'kg-core' ),
            'menu_name'             => __( 'Topluluk Soruları', 'kg-core' ),
            'all_items'             => __( 'Tüm Sorular', 'kg-core' ),
            'add_new_item'          => __( 'Yeni Soru Ekle', 'kg-core' ),
            'edit_item'             => __( 'Soruyu Düzenle', 'kg-core' ),
            'view_item'             => __( 'Soruyu Görüntüle', 'kg-core' ),
            'search_items'          => __( 'Soru Ara', 'kg-core' ),
            'not_found'             => __( 'Soru Bulunamadı', 'kg-core' ),
            'not_found_in_trash'    => __( 'Çöpte Soru Bulunamadı', 'kg-core' ),
        ];

        $args = [
            'label'                 => __( 'Soru', 'kg-core' ),
            'labels'                => $labels,
            'supports'              => [ 'title', 'editor', 'author', 'comments', 'custom-fields' ],
            'taxonomies'            => [ 'community_circle' ],
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 6,
            'menu_icon'             => 'dashicons-format-chat',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'show_in_rest'          => true,
            'rest_base'             => 'discussions',
            'rewrite'               => [ 'slug' => 'soru-cevap' ],
        ];

        register_post_type( 'discussion', $args );
    }

    /**
     * Force 'pending' status for frontend submissions
     * Only affects non-admin users creating discussions
     */
    public function force_pending_status( $data, $postarr ) {
        // Only affect 'discussion' post type
        if ( $data['post_type'] !== 'discussion' ) {
            return $data;
        }

        // Allow admins to set any status
        if ( current_user_can( 'manage_options' ) ) {
            return $data;
        }

        // Force pending for new posts (not updates) from non-admin users
        if ( empty( $postarr['ID'] ) || $postarr['ID'] === 0 ) {
            $data['post_status'] = 'pending';
        }

        return $data;
    }
}
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

        // Image attribution admin UI.
        add_action( 'add_meta_boxes_ingredient', [ $this, 'add_image_credit_meta_box' ] );
        add_action( 'save_post_ingredient', [ $this, 'save_image_credit_visibility' ], 20, 3 );
        add_action( 'admin_footer', [ $this, 'move_image_credit_meta_box_below_featured_image' ] );

        // Respect the visibility switch in both custom KG API and WP REST fallbacks.
        add_filter( 'rest_request_after_callbacks', [ $this, 'filter_custom_api_image_credit' ], 10, 3 );
        add_filter( 'rest_prepare_ingredient', [ $this, 'filter_wp_rest_image_credit' ], 10, 3 );
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
     * Register the image attribution controls as a sidebar meta box.
     */
    public function add_image_credit_meta_box() {
        add_meta_box(
            'kg_ingredient_image_credit',
            'Görsel Kaynağı ve Kredi',
            [ $this, 'render_image_credit_meta_box' ],
            'ingredient',
            'side',
            'low'
        );
    }

    /**
     * Render visibility switch and the target area that receives the existing
     * source / photographer / URL fields from IngredientMetaBox via admin JS.
     *
     * @param \WP_Post $post Current post.
     */
    public function render_image_credit_meta_box( $post ) {
        $stored_visibility = get_post_meta( $post->ID, '_kg_show_image_credit', true );
        $show_image_credit = '' === $stored_visibility || '1' === (string) $stored_visibility;

        wp_nonce_field( 'kg_image_credit_visibility_save', 'kg_image_credit_visibility_nonce' );
        ?>
        <div class="kg-image-credit-sidebar">
            <div id="kg-image-credit-fields-slot"></div>

            <div style="border-top:1px solid #dcdcde; margin-top:14px; padding-top:14px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                    <div>
                        <strong style="display:block;">Krediyi Göster</strong>
                        <span style="display:block; margin-top:3px; color:#646970; font-size:12px; line-height:1.4;">Kapalıysa kredi bilgisi kayıtlı kalır ancak sitede gösterilmez.</span>
                    </div>
                    <label class="kg-credit-switch" style="position:relative; display:inline-block; width:44px; height:24px; flex:0 0 44px;">
                        <input
                            type="checkbox"
                            name="kg_show_image_credit"
                            value="1"
                            <?php checked( $show_image_credit ); ?>
                            style="opacity:0; width:0; height:0; position:absolute;"
                        >
                        <span class="kg-credit-switch-track" style="position:absolute; inset:0; cursor:pointer; background:#8c8f94; border-radius:999px; transition:.2s;"></span>
                        <span class="kg-credit-switch-thumb" style="position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; box-shadow:0 1px 2px rgba(0,0,0,.2); transition:.2s; pointer-events:none;"></span>
                    </label>
                </div>
            </div>
        </div>
        <style>
            .kg-credit-switch input:checked + .kg-credit-switch-track { background:#00a32a !important; }
            .kg-credit-switch input:checked ~ .kg-credit-switch-thumb { transform:translateX(20px); }
            #kg_ingredient_image_credit .inside { margin-top:0; }
            #kg_ingredient_image_credit #kg-image-credit-fields-slot > div { background:transparent !important; border:0 !important; padding:0 !important; }
            #kg_ingredient_image_credit #kg-image-credit-fields-slot p:first-child { margin-top:0; }
            #kg_ingredient_image_credit #kg-image-credit-fields-slot input[type="text"],
            #kg_ingredient_image_credit #kg-image-credit-fields-slot input[type="url"] { width:100% !important; }
        </style>
        <?php
    }

    /**
     * Save the "Krediyi Göster" switch independently from the main ingredient
     * details meta box.
     */
    public function save_image_credit_visibility( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['kg_image_credit_visibility_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kg_image_credit_visibility_nonce'] ) ), 'kg_image_credit_visibility_save' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        update_post_meta( $post_id, '_kg_show_image_credit', isset( $_POST['kg_show_image_credit'] ) ? '1' : '0' );
    }

    /**
     * Move the credit source fields from the large Ingredient Details box into
     * the sidebar box and place that box directly below Featured Image.
     *
     * Keeping the existing inputs means the existing save logic remains the
     * single source of truth for source / credit / URL values.
     */
    public function move_image_credit_meta_box_below_featured_image() {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'ingredient' !== $screen->post_type || 'post' !== $screen->base ) {
            return;
        }
        ?>
        <script>
        (function() {
            function arrangeIngredientCreditBox() {
                var creditBox = document.getElementById('kg_ingredient_image_credit');
                var featuredBox = document.getElementById('postimagediv');
                var slot = document.getElementById('kg-image-credit-fields-slot');
                var detailsBox = document.getElementById('kg_ingredient_details');

                if (creditBox && featuredBox && creditBox.previousElementSibling !== featuredBox) {
                    featuredBox.insertAdjacentElement('afterend', creditBox);
                }

                if (!slot || !detailsBox || slot.dataset.fieldsMoved === '1') {
                    return;
                }

                var headings = detailsBox.querySelectorAll('h3');
                var sourceHeading = null;
                for (var i = 0; i < headings.length; i++) {
                    if ((headings[i].textContent || '').trim() === 'Görsel Kaynağı ve Kredi') {
                        sourceHeading = headings[i];
                        break;
                    }
                }

                if (!sourceHeading) {
                    return;
                }

                var fieldsBlock = sourceHeading.nextElementSibling;
                if (fieldsBlock && fieldsBlock.tagName === 'DIV') {
                    slot.appendChild(fieldsBlock);
                    sourceHeading.remove();
                    slot.dataset.fieldsMoved = '1';
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', arrangeIngredientCreditBox);
            } else {
                arrangeIngredientCreditBox();
            }

            // Gutenberg / late-rendered meta box compatibility.
            window.setTimeout(arrangeIngredientCreditBox, 500);
            window.setTimeout(arrangeIngredientCreditBox, 1500);
        })();
        </script>
        <?php
    }

    /**
     * Hide image credit from the custom KG ingredient API when the switch is off.
     * Existing frontend logic already hides the credit row when image_credit is empty.
     *
     * @param mixed            $response REST response.
     * @param array            $handler  Route handler.
     * @param \WP_REST_Request $request  REST request.
     * @return mixed
     */
    public function filter_custom_api_image_credit( $response, $handler, $request ) {
        if ( ! $request instanceof \WP_REST_Request ) {
            return $response;
        }

        $route = $request->get_route();
        if ( 0 !== strpos( $route, '/kg/v1/ingredients' ) ) {
            return $response;
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $rest_response = rest_ensure_response( $response );
        $data = $rest_response->get_data();
        $rest_response->set_data( $this->apply_image_credit_visibility_to_payload( $data ) );

        return $rest_response;
    }

    /**
     * Also protect the standard WP REST fallback used by the frontend.
     *
     * @param \WP_REST_Response $response REST response.
     * @param \WP_Post          $post     Post object.
     * @param \WP_REST_Request  $request  REST request.
     * @return \WP_REST_Response
     */
    public function filter_wp_rest_image_credit( $response, $post, $request ) {
        if ( ! $post || $this->should_show_image_credit( $post->ID ) ) {
            return $response;
        }

        $data = $response->get_data();
        if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
            if ( array_key_exists( '_kg_image_credit', $data['meta'] ) ) {
                $data['meta']['_kg_image_credit'] = '';
            }
            if ( array_key_exists( 'image_credit', $data['meta'] ) ) {
                $data['meta']['image_credit'] = '';
            }
            $response->set_data( $data );
        }

        return $response;
    }

    /**
     * Recursively apply the visibility setting to KG ingredient response payloads.
     *
     * @param mixed $payload Response payload.
     * @return mixed
     */
    private function apply_image_credit_visibility_to_payload( $payload ) {
        if ( ! is_array( $payload ) ) {
            return $payload;
        }

        if ( isset( $payload['id'] ) && array_key_exists( 'image_credit', $payload ) ) {
            if ( ! $this->should_show_image_credit( (int) $payload['id'] ) ) {
                $payload['image_credit'] = '';
            }
        }

        foreach ( $payload as $key => $value ) {
            if ( is_array( $value ) ) {
                $payload[ $key ] = $this->apply_image_credit_visibility_to_payload( $value );
            }
        }

        return $payload;
    }

    /**
     * Missing visibility meta means ON for backward compatibility.
     *
     * @param int $post_id Ingredient post ID.
     * @return bool
     */
    private function should_show_image_credit( $post_id ) {
        $visibility = get_post_meta( $post_id, '_kg_show_image_credit', true );
        return '' === $visibility || '1' === (string) $visibility;
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

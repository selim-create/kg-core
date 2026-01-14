<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;

class CollectionController {

    // Allowed icon values
    private $allowed_icons = [
        'mug-hot', 'snowflake', 'carrot', 'heart', 'star', 
        'bookmark', 'folder', 'utensils', 'apple-whole', 
        'fish', 'egg', 'bread-slice', 'sun', 'moon', 'cookie'
    ];

    // Allowed color values
    private $allowed_colors = [
        'orange', 'blue', 'green', 'purple', 
        'pink', 'yellow', 'red', 'teal'
    ];

    // Allowed item types
    private $allowed_item_types = [
        'recipe', 'ingredient', 'post', 'discussion'
    ];

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // GET /kg/v1/user/collections - List all collections
        register_rest_route( 'kg/v1', '/user/collections', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_collections' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // POST /kg/v1/user/collections - Create collection
        register_rest_route( 'kg/v1', '/user/collections', [
            'methods'  => 'POST',
            'callback' => [ $this, 'create_collection' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // GET /kg/v1/user/collections/{id} - Get single collection
        register_rest_route( 'kg/v1', '/user/collections/(?P<id>[a-f0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_collection' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // PUT /kg/v1/user/collections/{id} - Update collection
        register_rest_route( 'kg/v1', '/user/collections/(?P<id>[a-f0-9-]+)', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_collection' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // DELETE /kg/v1/user/collections/{id} - Delete collection
        register_rest_route( 'kg/v1', '/user/collections/(?P<id>[a-f0-9-]+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'delete_collection' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // POST /kg/v1/user/collections/{id}/items - Add item to collection
        register_rest_route( 'kg/v1', '/user/collections/(?P<id>[a-f0-9-]+)/items', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_item_to_collection' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // DELETE /kg/v1/user/collections/{id}/items/{item_id} - Remove item from collection
        register_rest_route( 'kg/v1', '/user/collections/(?P<id>[a-f0-9-]+)/items/(?P<item_id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'remove_item_from_collection' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
    }

    /**
     * Check if user is authenticated via JWT
     */
    public function check_authentication( $request ) {
        $token = JWTHandler::get_token_from_request();
        
        if ( ! $token ) {
            return false;
        }

        $payload = JWTHandler::validate_token( $token );
        
        if ( ! $payload ) {
            return false;
        }

        // Store user ID in request for later use
        $request->set_param( 'authenticated_user_id', $payload['user_id'] );
        
        return true;
    }

    /**
     * Get authenticated user ID from request
     */
    private function get_authenticated_user_id( $request ) {
        return $request->get_param( 'authenticated_user_id' );
    }

    /**
     * GET /kg/v1/user/collections
     * Get all collections for the authenticated user
     */
    public function get_collections( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $collections = get_user_meta( $user_id, '_kg_collections', true );

        if ( ! is_array( $collections ) ) {
            $collections = [];
        }

        // Format collections for list view (without items)
        $formatted_collections = [];
        foreach ( $collections as $collection ) {
            $formatted_collections[] = [
                'id' => $collection['id'],
                'name' => $collection['name'],
                'icon' => $collection['icon'],
                'color' => $collection['color'],
                'item_count' => isset( $collection['items'] ) ? count( $collection['items'] ) : 0,
                'created_at' => $collection['created_at'],
            ];
        }

        return new \WP_REST_Response( $formatted_collections, 200 );
    }

    /**
     * POST /kg/v1/user/collections
     * Create a new collection
     */
    public function create_collection( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $icon = sanitize_text_field( $request->get_param( 'icon' ) );
        $color = sanitize_text_field( $request->get_param( 'color' ) );

        // Validation
        if ( empty( $name ) ) {
            return new \WP_Error( 'missing_name', 'Collection name is required', [ 'status' => 400 ] );
        }

        if ( strlen( $name ) > 100 ) {
            return new \WP_Error( 'name_too_long', 'Collection name must be less than 100 characters', [ 'status' => 400 ] );
        }

        if ( empty( $icon ) ) {
            return new \WP_Error( 'missing_icon', 'Icon is required', [ 'status' => 400 ] );
        }

        if ( ! in_array( $icon, $this->allowed_icons ) ) {
            return new \WP_Error( 'invalid_icon', 'Invalid icon. Must be one of: ' . implode( ', ', $this->allowed_icons ), [ 'status' => 400 ] );
        }

        if ( empty( $color ) ) {
            return new \WP_Error( 'missing_color', 'Color is required', [ 'status' => 400 ] );
        }

        if ( ! in_array( $color, $this->allowed_colors ) ) {
            return new \WP_Error( 'invalid_color', 'Invalid color. Must be one of: ' . implode( ', ', $this->allowed_colors ), [ 'status' => 400 ] );
        }

        // Get existing collections
        $collections = get_user_meta( $user_id, '_kg_collections', true );
        if ( ! is_array( $collections ) ) {
            $collections = [];
        }

        // Create new collection
        $now = current_time( 'c' ); // ISO 8601 format
        $collection = [
            'id' => wp_generate_uuid4(),
            'name' => $name,
            'icon' => $icon,
            'color' => $color,
            'items' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $collections[] = $collection;
        update_user_meta( $user_id, '_kg_collections', $collections );

        // Return formatted response
        return new \WP_REST_Response( [
            'id' => $collection['id'],
            'name' => $collection['name'],
            'icon' => $collection['icon'],
            'color' => $collection['color'],
            'item_count' => 0,
            'items' => [],
            'created_at' => $collection['created_at'],
            'updated_at' => $collection['updated_at'],
        ], 201 );
    }

    /**
     * GET /kg/v1/user/collections/{id}
     * Get single collection with full details and items
     */
    public function get_collection( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $collection_id = $request->get_param( 'id' );

        $collections = get_user_meta( $user_id, '_kg_collections', true );
        if ( ! is_array( $collections ) ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        // Find the collection
        $collection = null;
        foreach ( $collections as $col ) {
            if ( $col['id'] === $collection_id ) {
                $collection = $col;
                break;
            }
        }

        if ( ! $collection ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        // Format items with full data
        $formatted_items = [];
        if ( isset( $collection['items'] ) && is_array( $collection['items'] ) ) {
            foreach ( $collection['items'] as $item ) {
                $item_data = $this->get_item_data( $item['item_id'], $item['item_type'] );
                if ( $item_data ) {
                    $formatted_items[] = [
                        'item_id' => $item['item_id'],
                        'item_type' => $item['item_type'],
                        'added_at' => $item['added_at'],
                        'data' => $item_data,
                    ];
                }
            }
        }

        return new \WP_REST_Response( [
            'id' => $collection['id'],
            'name' => $collection['name'],
            'icon' => $collection['icon'],
            'color' => $collection['color'],
            'item_count' => count( $formatted_items ),
            'items' => $formatted_items,
            'created_at' => $collection['created_at'],
            'updated_at' => $collection['updated_at'],
        ], 200 );
    }

    /**
     * PUT /kg/v1/user/collections/{id}
     * Update collection metadata
     */
    public function update_collection( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $collection_id = $request->get_param( 'id' );
        $name = $request->get_param( 'name' );
        $icon = $request->get_param( 'icon' );
        $color = $request->get_param( 'color' );

        $collections = get_user_meta( $user_id, '_kg_collections', true );
        if ( ! is_array( $collections ) ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        // Find and update the collection
        $found = false;
        foreach ( $collections as $index => $col ) {
            if ( $col['id'] === $collection_id ) {
                // Validate and update name
                if ( $name !== null ) {
                    $name = sanitize_text_field( $name );
                    if ( empty( $name ) ) {
                        return new \WP_Error( 'invalid_name', 'Collection name cannot be empty', [ 'status' => 400 ] );
                    }
                    if ( strlen( $name ) > 100 ) {
                        return new \WP_Error( 'name_too_long', 'Collection name must be less than 100 characters', [ 'status' => 400 ] );
                    }
                    $collections[$index]['name'] = $name;
                }

                // Validate and update icon
                if ( $icon !== null ) {
                    $icon = sanitize_text_field( $icon );
                    if ( ! in_array( $icon, $this->allowed_icons ) ) {
                        return new \WP_Error( 'invalid_icon', 'Invalid icon. Must be one of: ' . implode( ', ', $this->allowed_icons ), [ 'status' => 400 ] );
                    }
                    $collections[$index]['icon'] = $icon;
                }

                // Validate and update color
                if ( $color !== null ) {
                    $color = sanitize_text_field( $color );
                    if ( ! in_array( $color, $this->allowed_colors ) ) {
                        return new \WP_Error( 'invalid_color', 'Invalid color. Must be one of: ' . implode( ', ', $this->allowed_colors ), [ 'status' => 400 ] );
                    }
                    $collections[$index]['color'] = $color;
                }

                // Update timestamp
                $collections[$index]['updated_at'] = current_time( 'c' );

                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_collections', $collections );

        // Return updated collection
        foreach ( $collections as $col ) {
            if ( $col['id'] === $collection_id ) {
                return new \WP_REST_Response( [
                    'id' => $col['id'],
                    'name' => $col['name'],
                    'icon' => $col['icon'],
                    'color' => $col['color'],
                    'item_count' => isset( $col['items'] ) ? count( $col['items'] ) : 0,
                    'created_at' => $col['created_at'],
                    'updated_at' => $col['updated_at'],
                ], 200 );
            }
        }
    }

    /**
     * DELETE /kg/v1/user/collections/{id}
     * Delete a collection
     */
    public function delete_collection( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $collection_id = $request->get_param( 'id' );

        $collections = get_user_meta( $user_id, '_kg_collections', true );
        if ( ! is_array( $collections ) ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        // Find and remove the collection
        $new_collections = array_filter( $collections, function( $col ) use ( $collection_id ) {
            return $col['id'] !== $collection_id;
        });

        if ( count( $new_collections ) === count( $collections ) ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_collections', array_values( $new_collections ) );

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Collection deleted successfully',
        ], 200 );
    }

    /**
     * POST /kg/v1/user/collections/{id}/items
     * Add item to collection
     */
    public function add_item_to_collection( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $collection_id = $request->get_param( 'id' );
        $item_id = absint( $request->get_param( 'item_id' ) );
        $item_type = sanitize_text_field( $request->get_param( 'item_type' ) );

        // Validation
        if ( ! $item_id ) {
            return new \WP_Error( 'missing_item_id', 'Item ID is required', [ 'status' => 400 ] );
        }

        if ( empty( $item_type ) ) {
            return new \WP_Error( 'missing_item_type', 'Item type is required', [ 'status' => 400 ] );
        }

        if ( ! in_array( $item_type, $this->allowed_item_types ) ) {
            return new \WP_Error( 'invalid_item_type', 'Invalid item type. Must be one of: ' . implode( ', ', $this->allowed_item_types ), [ 'status' => 400 ] );
        }

        // Verify item exists
        $expected_post_type = $this->get_post_type_for_item_type( $item_type );
        $post = get_post( $item_id );
        if ( ! $post || $post->post_type !== $expected_post_type ) {
            return new \WP_Error( 'invalid_item', 'Invalid item ID or item does not exist', [ 'status' => 404 ] );
        }

        $collections = get_user_meta( $user_id, '_kg_collections', true );
        if ( ! is_array( $collections ) ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        // Find and update the collection
        $found = false;
        foreach ( $collections as $index => $col ) {
            if ( $col['id'] === $collection_id ) {
                if ( ! isset( $collections[$index]['items'] ) ) {
                    $collections[$index]['items'] = [];
                }

                // Check if item already exists
                foreach ( $collections[$index]['items'] as $existing_item ) {
                    if ( $existing_item['item_id'] === $item_id && $existing_item['item_type'] === $item_type ) {
                        return new \WP_Error( 'item_already_exists', 'Item already exists in this collection', [ 'status' => 409 ] );
                    }
                }

                // Add item
                $collections[$index]['items'][] = [
                    'item_id' => $item_id,
                    'item_type' => $item_type,
                    'added_at' => current_time( 'c' ),
                ];

                $collections[$index]['updated_at'] = current_time( 'c' );

                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_collections', $collections );

        // Get item count
        $item_count = 0;
        foreach ( $collections as $col ) {
            if ( $col['id'] === $collection_id ) {
                $item_count = count( $col['items'] );
                break;
            }
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Item added to collection',
            'item_count' => $item_count,
        ], 201 );
    }

    /**
     * DELETE /kg/v1/user/collections/{id}/items/{item_id}
     * Remove item from collection
     */
    public function remove_item_from_collection( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $collection_id = $request->get_param( 'id' );
        $item_id = absint( $request->get_param( 'item_id' ) );
        $item_type = sanitize_text_field( $request->get_param( 'type' ) );

        // Validation
        if ( ! $item_id ) {
            return new \WP_Error( 'missing_item_id', 'Item ID is required', [ 'status' => 400 ] );
        }

        if ( empty( $item_type ) ) {
            return new \WP_Error( 'missing_type', 'Type parameter is required', [ 'status' => 400 ] );
        }

        if ( ! in_array( $item_type, $this->allowed_item_types ) ) {
            return new \WP_Error( 'invalid_type', 'Invalid type. Must be one of: ' . implode( ', ', $this->allowed_item_types ), [ 'status' => 400 ] );
        }

        $collections = get_user_meta( $user_id, '_kg_collections', true );
        if ( ! is_array( $collections ) ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        // Find and update the collection
        $found = false;
        foreach ( $collections as $index => $col ) {
            if ( $col['id'] === $collection_id ) {
                if ( ! isset( $collections[$index]['items'] ) ) {
                    $collections[$index]['items'] = [];
                }

                // Remove item
                $original_count = count( $collections[$index]['items'] );
                $collections[$index]['items'] = array_filter( $collections[$index]['items'], function( $item ) use ( $item_id, $item_type ) {
                    return ! ( $item['item_id'] === $item_id && $item['item_type'] === $item_type );
                });
                $collections[$index]['items'] = array_values( $collections[$index]['items'] );

                if ( count( $collections[$index]['items'] ) === $original_count ) {
                    return new \WP_Error( 'item_not_found', 'Item not found in collection', [ 'status' => 404 ] );
                }

                $collections[$index]['updated_at'] = current_time( 'c' );

                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new \WP_Error( 'collection_not_found', 'Collection not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_collections', $collections );

        // Get item count
        $item_count = 0;
        foreach ( $collections as $col ) {
            if ( $col['id'] === $collection_id ) {
                $item_count = count( $col['items'] );
                break;
            }
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Item removed from collection',
            'item_count' => $item_count,
        ], 200 );
    }

    /**
     * Get item data based on type
     */
    private function get_item_data( $item_id, $item_type ) {
        $post = get_post( $item_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return null;
        }

        switch ( $item_type ) {
            case 'recipe':
                return $this->format_recipe_card( $post );
            case 'ingredient':
                return $this->format_ingredient_card( $post );
            case 'post':
                return $this->format_post_card( $post );
            case 'discussion':
                return $this->format_discussion_card( $post );
            default:
                return null;
        }
    }

    /**
     * Format recipe data for card display
     */
    private function format_recipe_card( $post ) {
        $age_groups = wp_get_post_terms( $post->ID, 'age-group' );
        $age_group = '';
        
        if ( ! empty( $age_groups ) && ! is_wp_error( $age_groups ) ) {
            $age_group = $age_groups[0]->name;
        }

        return [
            'id' => $post->ID,
            'title' => \KG_Core\Utils\Helper::decode_html_entities( $post->post_title ),
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
            'age_group' => $age_group,
            'prep_time' => get_post_meta( $post->ID, '_kg_prep_time', true ),
        ];
    }

    /**
     * Format ingredient data for card display
     */
    private function format_ingredient_card( $post ) {
        return [
            'id' => $post->ID,
            'name' => \KG_Core\Utils\Helper::decode_html_entities( $post->post_title ),
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
            'start_age' => get_post_meta( $post->ID, '_kg_start_age', true ),
        ];
    }

    /**
     * Format post data for card display
     */
    private function format_post_card( $post ) {
        $categories = get_the_category( $post->ID );
        $category = '';
        if ( ! empty( $categories ) ) {
            $category = $categories[0]->name;
        }

        return [
            'id' => $post->ID,
            'title' => \KG_Core\Utils\Helper::decode_html_entities( $post->post_title ),
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
            'category' => $category,
        ];
    }

    /**
     * Format discussion data for card display
     */
    private function format_discussion_card( $post ) {
        $author = get_user_by( 'id', $post->post_author );
        $author_name = $author ? $author->display_name : 'Unknown';

        $circles = wp_get_post_terms( $post->ID, 'community_circle' );
        $circle = '';
        if ( ! empty( $circles ) && ! is_wp_error( $circles ) ) {
            $circle = $circles[0]->name;
        }

        return [
            'id' => $post->ID,
            'title' => \KG_Core\Utils\Helper::decode_html_entities( $post->post_title ),
            'slug' => $post->post_name,
            'author' => $author_name,
            'circle' => $circle,
        ];
    }

    /**
     * Get WordPress post type from item type
     */
    private function get_post_type_for_item_type( $item_type ) {
        $mapping = [
            'recipe' => 'recipe',
            'ingredient' => 'ingredient',
            'post' => 'post',
            'discussion' => 'discussion',
        ];

        return isset( $mapping[ $item_type ] ) ? $mapping[ $item_type ] : '';
    }
}

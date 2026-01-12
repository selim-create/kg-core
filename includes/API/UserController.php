<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;

class UserController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Authentication endpoints
        register_rest_route( 'kg/v1', '/auth/register', [
            'methods'  => 'POST',
            'callback' => [ $this, 'register_user' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'kg/v1', '/auth/login', [
            'methods'  => 'POST',
            'callback' => [ $this, 'login_user' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'kg/v1', '/auth/logout', [
            'methods'  => 'POST',
            'callback' => [ $this, 'logout_user' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/auth/me', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_current_user' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Profile endpoints
        register_rest_route( 'kg/v1', '/user/profile', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_profile' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/profile', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_profile' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Children profile endpoints
        register_rest_route( 'kg/v1', '/user/children', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_children' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/children', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_child' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/children/(?P<id>[a-zA-Z0-9]+)', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_child' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/children/(?P<id>[a-zA-Z0-9]+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'delete_child' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Favorites endpoints
        register_rest_route( 'kg/v1', '/user/favorites', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_favorites' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/favorites', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_favorite' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/favorites/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'remove_favorite' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Shopping list endpoints
        register_rest_route( 'kg/v1', '/user/shopping-list', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_shopping_list' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/shopping-list', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_to_shopping_list' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/shopping-list/(?P<id>[a-zA-Z0-9]+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'remove_from_shopping_list' ],
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
     * Register new user
     */
    public function register_user( $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );
        $name = sanitize_text_field( $request->get_param( 'name' ) );

        if ( empty( $email ) || empty( $password ) ) {
            return new \WP_Error( 'missing_fields', 'Email and password are required', [ 'status' => 400 ] );
        }

        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', 'Invalid email address', [ 'status' => 400 ] );
        }

        // Password strength validation
        if ( strlen( $password ) < 8 ) {
            return new \WP_Error( 'weak_password', 'Password must be at least 8 characters long', [ 'status' => 400 ] );
        }

        if ( email_exists( $email ) ) {
            return new \WP_Error( 'email_exists', 'Email already registered', [ 'status' => 409 ] );
        }

        $user_id = wp_create_user( $email, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        if ( ! empty( $name ) ) {
            wp_update_user( [
                'ID' => $user_id,
                'display_name' => $name,
            ] );
        }

        $token = JWTHandler::generate_token( $user_id );

        return new \WP_REST_Response( [
            'token' => $token,
            'user_id' => $user_id,
            'email' => $email,
            'name' => $name,
        ], 201 );
    }

    /**
     * Login user
     */
    public function login_user( $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $email ) || empty( $password ) ) {
            return new \WP_Error( 'missing_credentials', 'Email and password are required', [ 'status' => 400 ] );
        }

        $user = wp_authenticate( $email, $password );

        if ( is_wp_error( $user ) ) {
            return new \WP_Error( 'invalid_credentials', 'Invalid email or password', [ 'status' => 401 ] );
        }

        $token = JWTHandler::generate_token( $user->ID );

        return new \WP_REST_Response( [
            'token' => $token,
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
        ], 200 );
    }

    /**
     * Logout user
     */
    public function logout_user( $request ) {
        $token = JWTHandler::get_token_from_request();
        JWTHandler::invalidate_token( $token );

        return new \WP_REST_Response( [ 'message' => 'Logged out successfully' ], 200 );
    }

    /**
     * Get current authenticated user
     */
    public function get_current_user( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new \WP_Error( 'user_not_found', 'User not found', [ 'status' => 404 ] );
        }

        return new \WP_REST_Response( [
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
        ], 200 );
    }

    /**
     * Get user profile
     */
    public function get_profile( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new \WP_Error( 'user_not_found', 'User not found', [ 'status' => 404 ] );
        }

        return new \WP_REST_Response( [
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'phone' => get_user_meta( $user_id, 'phone', true ),
        ], 200 );
    }

    /**
     * Update user profile
     */
    public function update_profile( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $phone = sanitize_text_field( $request->get_param( 'phone' ) );

        if ( $name ) {
            wp_update_user( [
                'ID' => $user_id,
                'display_name' => $name,
            ] );
        }

        if ( $phone ) {
            update_user_meta( $user_id, 'phone', $phone );
        }

        return new \WP_REST_Response( [ 'message' => 'Profile updated successfully' ], 200 );
    }

    /**
     * Get children profiles
     */
    public function get_children( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $children = get_user_meta( $user_id, '_kg_children', true );

        if ( ! is_array( $children ) ) {
            $children = [];
        }

        return new \WP_REST_Response( $children, 200 );
    }

    /**
     * Add child profile
     */
    public function add_child( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $birth_date = sanitize_text_field( $request->get_param( 'birth_date' ) );
        $allergens = $request->get_param( 'allergens' );
        $notes = sanitize_textarea_field( $request->get_param( 'notes' ) );

        if ( empty( $name ) || empty( $birth_date ) ) {
            return new \WP_Error( 'missing_fields', 'Name and birth date are required', [ 'status' => 400 ] );
        }

        $children = get_user_meta( $user_id, '_kg_children', true );
        if ( ! is_array( $children ) ) {
            $children = [];
        }

        $child = [
            'id' => uniqid(),
            'name' => $name,
            'birth_date' => $birth_date,
            'allergens' => is_array( $allergens ) ? $allergens : [],
            'notes' => $notes ?: '',
        ];

        $children[] = $child;
        update_user_meta( $user_id, '_kg_children', $children );

        return new \WP_REST_Response( $child, 201 );
    }

    /**
     * Update child profile
     */
    public function update_child( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = $request->get_param( 'id' );
        
        $children = get_user_meta( $user_id, '_kg_children', true );
        if ( ! is_array( $children ) ) {
            return new \WP_Error( 'no_children', 'No children found', [ 'status' => 404 ] );
        }

        $found = false;
        foreach ( $children as $index => $child ) {
            if ( $child['id'] === $child_id ) {
                $name = $request->get_param( 'name' );
                $birth_date = $request->get_param( 'birth_date' );
                $allergens = $request->get_param( 'allergens' );
                $notes = $request->get_param( 'notes' );

                if ( $name ) $children[$index]['name'] = sanitize_text_field( $name );
                if ( $birth_date ) $children[$index]['birth_date'] = sanitize_text_field( $birth_date );
                if ( is_array( $allergens ) ) $children[$index]['allergens'] = $allergens;
                if ( $notes !== null ) $children[$index]['notes'] = sanitize_textarea_field( $notes );

                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new \WP_Error( 'child_not_found', 'Child not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_children', $children );

        return new \WP_REST_Response( [ 'message' => 'Child updated successfully' ], 200 );
    }

    /**
     * Delete child profile
     */
    public function delete_child( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = $request->get_param( 'id' );
        
        $children = get_user_meta( $user_id, '_kg_children', true );
        if ( ! is_array( $children ) ) {
            return new \WP_Error( 'no_children', 'No children found', [ 'status' => 404 ] );
        }

        $new_children = array_filter( $children, function( $child ) use ( $child_id ) {
            return $child['id'] !== $child_id;
        });

        if ( count( $new_children ) === count( $children ) ) {
            return new \WP_Error( 'child_not_found', 'Child not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_children', array_values( $new_children ) );

        return new \WP_REST_Response( [ 'message' => 'Child deleted successfully' ], 200 );
    }

    /**
     * Get favorite recipes
     */
    public function get_favorites( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $favorites = get_user_meta( $user_id, '_kg_favorites', true );

        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        // Get recipe details
        $recipes = [];
        foreach ( $favorites as $recipe_id ) {
            $post = get_post( $recipe_id );
            if ( $post && $post->post_type === 'recipe' ) {
                $recipes[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'slug' => $post->post_name,
                    'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
                ];
            }
        }

        return new \WP_REST_Response( $recipes, 200 );
    }

    /**
     * Add recipe to favorites
     */
    public function add_favorite( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $recipe_id = absint( $request->get_param( 'recipe_id' ) );

        if ( ! $recipe_id ) {
            return new \WP_Error( 'missing_recipe_id', 'Recipe ID is required', [ 'status' => 400 ] );
        }

        $recipe = get_post( $recipe_id );
        if ( ! $recipe || $recipe->post_type !== 'recipe' ) {
            return new \WP_Error( 'invalid_recipe', 'Invalid recipe ID', [ 'status' => 404 ] );
        }

        $favorites = get_user_meta( $user_id, '_kg_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        if ( ! in_array( $recipe_id, $favorites ) ) {
            $favorites[] = $recipe_id;
            update_user_meta( $user_id, '_kg_favorites', $favorites );
        }

        return new \WP_REST_Response( [ 'message' => 'Recipe added to favorites' ], 201 );
    }

    /**
     * Remove recipe from favorites
     */
    public function remove_favorite( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $recipe_id = absint( $request->get_param( 'id' ) );

        $favorites = get_user_meta( $user_id, '_kg_favorites', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        $favorites = array_filter( $favorites, function( $id ) use ( $recipe_id ) {
            return $id !== $recipe_id;
        });

        update_user_meta( $user_id, '_kg_favorites', array_values( $favorites ) );

        return new \WP_REST_Response( [ 'message' => 'Recipe removed from favorites' ], 200 );
    }

    /**
     * Get shopping list
     */
    public function get_shopping_list( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $shopping_list = get_user_meta( $user_id, '_kg_shopping_list', true );

        if ( ! is_array( $shopping_list ) ) {
            $shopping_list = [];
        }

        return new \WP_REST_Response( $shopping_list, 200 );
    }

    /**
     * Add item to shopping list
     */
    public function add_to_shopping_list( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $item_name = sanitize_text_field( $request->get_param( 'item' ) );
        $quantity = sanitize_text_field( $request->get_param( 'quantity' ) );

        if ( empty( $item_name ) ) {
            return new \WP_Error( 'missing_item', 'Item name is required', [ 'status' => 400 ] );
        }

        $shopping_list = get_user_meta( $user_id, '_kg_shopping_list', true );
        if ( ! is_array( $shopping_list ) ) {
            $shopping_list = [];
        }

        $item = [
            'id' => uniqid(),
            'item' => $item_name,
            'quantity' => $quantity ?: '1',
            'checked' => false,
        ];

        $shopping_list[] = $item;
        update_user_meta( $user_id, '_kg_shopping_list', $shopping_list );

        return new \WP_REST_Response( $item, 201 );
    }

    /**
     * Remove item from shopping list
     */
    public function remove_from_shopping_list( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $item_id = $request->get_param( 'id' );

        $shopping_list = get_user_meta( $user_id, '_kg_shopping_list', true );
        if ( ! is_array( $shopping_list ) ) {
            return new \WP_Error( 'empty_list', 'Shopping list is empty', [ 'status' => 404 ] );
        }

        $shopping_list = array_filter( $shopping_list, function( $item ) use ( $item_id ) {
            return $item['id'] !== $item_id;
        });

        update_user_meta( $user_id, '_kg_shopping_list', array_values( $shopping_list ) );

        return new \WP_REST_Response( [ 'message' => 'Item removed from shopping list' ], 200 );
    }
}

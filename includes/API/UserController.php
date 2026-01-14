<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Auth\GoogleAuth;
use KG_Core\Utils\PrivacyHelper;

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

        // Google OAuth endpoint
        register_rest_route( 'kg/v1', '/auth/google', [
            'methods'  => 'POST',
            'callback' => [ $this, 'google_auth' ],
            'permission_callback' => '__return_true',
            'args' => [
                'id_token' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Google ID Token',
                ],
            ],
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

        // Extended user profile endpoint
        register_rest_route( 'kg/v1', '/user/me', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_user_me' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Public user profile endpoint
        register_rest_route( 'kg/v1', '/user/public/(?P<username>[a-zA-Z0-9_-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_public_profile' ],
            'permission_callback' => '__return_true',
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
        $baby_birth_date = sanitize_text_field( $request->get_param( 'baby_birth_date' ) );

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

        // Check password complexity
        $has_uppercase = preg_match( '/[A-Z]/', $password );
        $has_lowercase = preg_match( '/[a-z]/', $password );
        $has_number = preg_match( '/[0-9]/', $password );
        
        if ( ! ( $has_uppercase && $has_lowercase && $has_number ) ) {
            return new \WP_Error( 
                'weak_password', 
                'Password must contain at least one uppercase letter, one lowercase letter, and one number', 
                [ 'status' => 400 ] 
            );
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

        // Register sonrası otomatik çember atama
        if ( ! empty( $baby_birth_date ) ) {
            $this->assign_default_circle( $user_id, $baby_birth_date );
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
     * Google ile giriş
     * Frontend'den gelen Google ID token'ı ile kullanıcı girişi yapar
     */
    public function google_auth( $request ) {
        // Google OAuth aktif mi kontrol et
        if ( ! GoogleAuth::is_enabled() ) {
            return new \WP_Error(
                'google_auth_disabled',
                'Google ile giriş şu anda aktif değil.',
                [ 'status' => 403 ]
            );
        }
        
        $id_token = $request->get_param( 'id_token' );
        
        if ( empty( $id_token ) ) {
            return new \WP_Error(
                'missing_token',
                'Google ID token gerekli.',
                [ 'status' => 400 ]
            );
        }
        
        $google_auth = new GoogleAuth();
        
        // Token'ı doğrula
        $google_data = $google_auth->verify_id_token( $id_token );
        
        if ( is_wp_error( $google_data ) ) {
            return new \WP_Error(
                'invalid_google_token',
                $google_data->get_error_message(),
                [ 'status' => 401 ]
            );
        }
        
        // Email doğrulanmış mı kontrol et
        if ( ! $google_data['email_verified'] ) {
            return new \WP_Error(
                'email_not_verified',
                'Google hesabınızın e-posta adresi doğrulanmamış.',
                [ 'status' => 401 ]
            );
        }
        
        // Kullanıcıyı bul veya oluştur
        $user = $google_auth->get_or_create_user( $google_data );
        
        if ( is_wp_error( $user ) ) {
            return new \WP_Error(
                'user_creation_failed',
                $user->get_error_message(),
                [ 'status' => 500 ]
            );
        }
        
        // JWT token oluştur
        $token = JWTHandler::generate_token( $user->ID );
        
        // Kullanıcı bilgilerini hazırla
        $user_data = $this->prepare_user_data( $user );
        
        return new \WP_REST_Response( [
            'success' => true,
            'token' => $token,
            'user' => $user_data,
            'message' => 'Google ile giriş başarılı.',
        ], 200 );
    }

    /**
     * Kullanıcı verisini hazırla
     */
    private function prepare_user_data( $user ) {
        // Google avatar varsa kullan, yoksa Gravatar
        $google_avatar = get_user_meta( $user->ID, 'google_avatar', true );
        $avatar_url = ! empty( $google_avatar ) ? $google_avatar : get_avatar_url( $user->ID );
        
        // Çocuk bilgilerini al
        $children = get_user_meta( $user->ID, 'kg_children', true );
        $children = is_array( $children ) ? $children : [];
        
        return [
            'id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'display_name' => $user->display_name,
            'avatar_url' => $avatar_url,
            'children' => $children,
            'created_at' => $user->user_registered,
        ];
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
        $display_name = sanitize_text_field( $request->get_param( 'display_name' ) );
        $parent_role = sanitize_text_field( $request->get_param( 'parent_role' ) );
        $avatar_id = absint( $request->get_param( 'avatar_id' ) );

        if ( $name ) {
            wp_update_user( [
                'ID' => $user_id,
                'display_name' => $name,
            ] );
        }

        if ( $phone ) {
            update_user_meta( $user_id, 'phone', $phone );
        }

        // Handle new user meta fields
        if ( $display_name ) {
            update_user_meta( $user_id, '_kg_display_name', $display_name );
        }

        if ( $parent_role ) {
            // Validate parent_role enum
            $allowed_roles = [ 'Anne', 'Baba', 'Bakıcı', 'Diğer' ];
            if ( in_array( $parent_role, $allowed_roles ) ) {
                update_user_meta( $user_id, '_kg_parent_role', $parent_role );
            } else {
                return new \WP_Error( 'invalid_parent_role', 'Invalid parent role. Must be one of: Anne, Baba, Bakıcı, Diğer', [ 'status' => 400 ] );
            }
        }

        if ( $avatar_id ) {
            // Verify attachment exists
            if ( get_post( $avatar_id ) && get_post_type( $avatar_id ) === 'attachment' ) {
                update_user_meta( $user_id, '_kg_avatar_id', $avatar_id );
            } else {
                return new \WP_Error( 'invalid_avatar_id', 'Invalid avatar ID', [ 'status' => 400 ] );
            }
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
        $gender = sanitize_text_field( $request->get_param( 'gender' ) );
        $allergies = $request->get_param( 'allergies' );
        $feeding_style = sanitize_text_field( $request->get_param( 'feeding_style' ) );
        $photo_id = absint( $request->get_param( 'photo_id' ) );
        $kvkk_consent = $request->get_param( 'kvkk_consent' );

        // Required fields validation
        if ( empty( $name ) || empty( $birth_date ) ) {
            return new \WP_Error( 'missing_fields', 'Name and birth date are required', [ 'status' => 400 ] );
        }

        // KVKK consent validation (required and must be true)
        if ( $kvkk_consent !== true && $kvkk_consent !== 'true' && $kvkk_consent !== 1 && $kvkk_consent !== '1' ) {
            return new \WP_Error( 'kvkk_consent_required', 'KVKK consent is required and must be true', [ 'status' => 400 ] );
        }

        // Birth date validation - cannot be in the future
        $birth_date_obj = \DateTime::createFromFormat( 'Y-m-d', $birth_date );
        $now = new \DateTime();
        if ( ! $birth_date_obj || $birth_date_obj > $now ) {
            return new \WP_Error( 'invalid_birth_date', 'Birth date cannot be in the future', [ 'status' => 400 ] );
        }

        // Gender validation
        if ( ! empty( $gender ) ) {
            $allowed_genders = [ 'male', 'female', 'unspecified' ];
            if ( ! in_array( $gender, $allowed_genders ) ) {
                return new \WP_Error( 'invalid_gender', 'Gender must be one of: male, female, unspecified', [ 'status' => 400 ] );
            }
        }

        // Feeding style validation
        if ( ! empty( $feeding_style ) ) {
            $allowed_feeding_styles = [ 'blw', 'puree', 'mixed' ];
            if ( ! in_array( $feeding_style, $allowed_feeding_styles ) ) {
                return new \WP_Error( 'invalid_feeding_style', 'Feeding style must be one of: blw, puree, mixed', [ 'status' => 400 ] );
            }
        }

        // Photo ID validation
        if ( $photo_id > 0 ) {
            if ( ! get_post( $photo_id ) || get_post_type( $photo_id ) !== 'attachment' ) {
                return new \WP_Error( 'invalid_photo_id', 'Invalid photo ID', [ 'status' => 400 ] );
            }
        }

        $children = get_user_meta( $user_id, '_kg_children', true );
        if ( ! is_array( $children ) ) {
            $children = [];
        }

        // Generate UUID v4 using WordPress function
        $uuid = wp_generate_uuid4();

        // Sanitize allergies array
        $sanitized_allergies = [];
        if ( is_array( $allergies ) ) {
            foreach ( $allergies as $allergy ) {
                $sanitized_allergies[] = sanitize_text_field( $allergy );
            }
        }

        $child = [
            'id' => $uuid,
            'name' => $name,
            'birth_date' => $birth_date,
            'gender' => $gender ?: 'unspecified',
            'allergies' => $sanitized_allergies,
            'feeding_style' => $feeding_style ?: 'mixed',
            'photo_id' => $photo_id > 0 ? $photo_id : null,
            'kvkk_consent' => true,
            'created_at' => current_time( 'c' ), // ISO 8601 format
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
                $gender = $request->get_param( 'gender' );
                $allergies = $request->get_param( 'allergies' );
                $feeding_style = $request->get_param( 'feeding_style' );
                $photo_id = $request->get_param( 'photo_id' );

                if ( $name ) {
                    $children[$index]['name'] = sanitize_text_field( $name );
                }

                if ( $birth_date ) {
                    // Validate birth date
                    $birth_date_obj = \DateTime::createFromFormat( 'Y-m-d', $birth_date );
                    $now = new \DateTime();
                    if ( ! $birth_date_obj || $birth_date_obj > $now ) {
                        return new \WP_Error( 'invalid_birth_date', 'Birth date cannot be in the future', [ 'status' => 400 ] );
                    }
                    $children[$index]['birth_date'] = sanitize_text_field( $birth_date );
                }

                if ( $gender !== null ) {
                    $allowed_genders = [ 'male', 'female', 'unspecified' ];
                    if ( ! in_array( $gender, $allowed_genders ) ) {
                        return new \WP_Error( 'invalid_gender', 'Gender must be one of: male, female, unspecified', [ 'status' => 400 ] );
                    }
                    $children[$index]['gender'] = $gender;
                }

                if ( is_array( $allergies ) ) {
                    // Sanitize allergies array
                    $sanitized_allergies = [];
                    foreach ( $allergies as $allergy ) {
                        $sanitized_allergies[] = sanitize_text_field( $allergy );
                    }
                    $children[$index]['allergies'] = $sanitized_allergies;
                }

                if ( $feeding_style !== null ) {
                    $allowed_feeding_styles = [ 'blw', 'puree', 'mixed' ];
                    if ( ! in_array( $feeding_style, $allowed_feeding_styles ) ) {
                        return new \WP_Error( 'invalid_feeding_style', 'Feeding style must be one of: blw, puree, mixed', [ 'status' => 400 ] );
                    }
                    $children[$index]['feeding_style'] = $feeding_style;
                }

                if ( $photo_id !== null ) {
                    $photo_id_int = absint( $photo_id );
                    if ( $photo_id_int > 0 ) {
                        if ( ! get_post( $photo_id_int ) || get_post_type( $photo_id_int ) !== 'attachment' ) {
                            return new \WP_Error( 'invalid_photo_id', 'Invalid photo ID', [ 'status' => 400 ] );
                        }
                        $children[$index]['photo_id'] = $photo_id_int;
                    } else {
                        $children[$index]['photo_id'] = null;
                    }
                }

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

    /**
     * Bebek yaşına göre uygun çember ID'sini bul
     */
    private function get_circle_by_baby_age( $birth_date ) {
        if ( empty( $birth_date ) ) {
            return null;
        }
        
        try {
            $birth = new \DateTime( $birth_date );
            $now = new \DateTime();
            $diff = $now->diff( $birth );
            $months = ( $diff->y * 12 ) + $diff->m;
            
            // Yaş aralıklarına göre çember slug'ları
            $slug = null;
            if ( $months >= 6 && $months < 9 ) {
                $slug = '6-9-ay';
            } elseif ( $months >= 9 && $months < 12 ) {
                $slug = '9-12-ay';
            } elseif ( $months >= 12 && $months < 24 ) {
                $slug = '1-2-yas';
            }
            
            if ( $slug ) {
                $term = get_term_by( 'slug', $slug, 'community_circle' );
                if ( $term && ! is_wp_error( $term ) ) {
                    return $term->term_id;
                }
            }
        } catch ( \Exception $e ) {
            // Invalid date format, return null
            return null;
        }
        
        return null;
    }

    /**
     * Register sonrası kullanıcıya otomatik çember ata
     */
    private function assign_default_circle( $user_id, $birth_date ) {
        $circle_id = $this->get_circle_by_baby_age( $birth_date );
        
        if ( $circle_id ) {
            $circles = get_user_meta( $user_id, '_kg_followed_circles', true ) ?: [];
            if ( ! in_array( $circle_id, $circles ) ) {
                $circles[] = $circle_id;
                update_user_meta( $user_id, '_kg_followed_circles', $circles );
            }
        }
    }

    /**
     * Get extended user profile (/user/me)
     * Returns full profile data for authenticated user
     */
    public function get_user_me( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return new \WP_Error( 'user_not_found', 'User not found', [ 'status' => 404 ] );
        }

        // Get user meta fields
        $display_name = get_user_meta( $user_id, '_kg_display_name', true );
        $parent_role = get_user_meta( $user_id, '_kg_parent_role', true );
        $avatar_id = get_user_meta( $user_id, '_kg_avatar_id', true );
        
        // Get avatar URL
        $avatar_url = '';
        if ( $avatar_id ) {
            $avatar_url = wp_get_attachment_url( $avatar_id );
        }
        if ( ! $avatar_url ) {
            $google_avatar = get_user_meta( $user_id, 'google_avatar', true );
            $avatar_url = ! empty( $google_avatar ) ? $google_avatar : get_avatar_url( $user_id );
        }

        // Get children data
        $children = get_user_meta( $user_id, '_kg_children', true );
        if ( ! is_array( $children ) ) {
            $children = [];
        }

        // Get followed circles
        $followed_circles = get_user_meta( $user_id, '_kg_followed_circles', true );
        if ( ! is_array( $followed_circles ) ) {
            $followed_circles = [];
        }

        // Get user stats
        $question_count = $this->get_user_question_count( $user_id );
        $comment_count = $this->get_user_comment_count( $user_id );

        return new \WP_REST_Response( [
            'id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $display_name ?: $user->display_name,
            'parent_role' => $parent_role,
            'avatar_url' => $avatar_url,
            'children' => $children,
            'followed_circles' => $followed_circles,
            'stats' => [
                'question_count' => $question_count,
                'comment_count' => $comment_count,
            ],
        ], 200 );
    }

    /**
     * Get public user profile by username
     * Returns filtered public data only (no sensitive information)
     */
    public function get_public_profile( $request ) {
        $username = $request->get_param( 'username' );
        
        // Get user by login or slug
        $user = get_user_by( 'login', $username );
        if ( ! $user ) {
            $user = get_user_by( 'slug', $username );
        }

        if ( ! $user ) {
            return new \WP_Error( 'user_not_found', 'User not found', [ 'status' => 404 ] );
        }

        $user_id = $user->ID;

        // Get user meta fields
        $display_name = get_user_meta( $user_id, '_kg_display_name', true );
        $parent_role = get_user_meta( $user_id, '_kg_parent_role', true );
        $avatar_id = get_user_meta( $user_id, '_kg_avatar_id', true );
        
        // Get avatar URL
        $avatar_url = '';
        if ( $avatar_id ) {
            $avatar_url = wp_get_attachment_url( $avatar_id );
        }
        if ( ! $avatar_url ) {
            $google_avatar = get_user_meta( $user_id, 'google_avatar', true );
            $avatar_url = ! empty( $google_avatar ) ? $google_avatar : get_avatar_url( $user_id );
        }

        // Get user stats (public only)
        $question_count = $this->get_user_question_count( $user_id );
        $approved_comments = $this->get_user_approved_comment_count( $user_id );

        // Get badges (placeholder for future implementation)
        $badges = [];

        // Get recent activity (placeholder for future implementation)
        $recent_activity = [];

        // Build public profile data (NO children, birth_date, or email)
        $public_data = [
            'id' => $user->ID,
            'display_name' => $display_name ?: $user->display_name,
            'parent_role' => $parent_role,
            'avatar_url' => $avatar_url,
            'badges' => $badges,
            'stats' => [
                'question_count' => $question_count,
                'approved_comments' => $approved_comments,
            ],
            'recent_activity' => $recent_activity,
        ];

        // Apply privacy filter to ensure no sensitive data leaks
        $public_data = PrivacyHelper::filter_public_profile( $public_data );

        return new \WP_REST_Response( $public_data, 200 );
    }

    /**
     * Get user question count
     */
    private function get_user_question_count( $user_id ) {
        $args = [
            'post_type' => 'discussion',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        
        $query = new \WP_Query( $args );
        return $query->found_posts;
    }

    /**
     * Get user comment count
     */
    private function get_user_comment_count( $user_id ) {
        $args = [
            'user_id' => $user_id,
            'count' => true,
        ];
        
        return get_comments( $args );
    }

    /**
     * Get user approved comment count
     */
    private function get_user_approved_comment_count( $user_id ) {
        $args = [
            'user_id' => $user_id,
            'status' => 'approve',
            'count' => true,
        ];
        
        return get_comments( $args );
    }
}

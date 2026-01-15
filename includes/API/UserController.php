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
        
        // Expert public profile endpoint
        register_rest_route( 'kg/v1', '/expert/public/(?P<username>[a-zA-Z0-9_-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_expert_public_profile' ],
            'permission_callback' => '__return_true',
        ]);

        // BLW test results endpoints
        register_rest_route( 'kg/v1', '/user/blw-results', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_blw_results' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/user/children/(?P<child_id>[a-zA-Z0-9-]+)/blw-results', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_child_blw_results' ],
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

        // Set user role to kg_parent
        $user = get_user_by( 'id', $user_id );
        $user->set_role( 'kg_parent' );

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
        $email_or_username = sanitize_text_field( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $email_or_username ) || empty( $password ) ) {
            return new \WP_Error( 'missing_credentials', 'Email/username and password are required', [ 'status' => 400 ] );
        }

        // Find user by email or username
        if ( is_email( $email_or_username ) ) {
            $user = wp_authenticate( $email_or_username, $password );
        } else {
            // Try with username
            $user = wp_authenticate_username_password( null, $email_or_username, $password );
        }

        if ( is_wp_error( $user ) ) {
            return new \WP_Error( 'invalid_credentials', 'Invalid email/username or password', [ 'status' => 401 ] );
        }

        $token = JWTHandler::generate_token( $user->ID );

        // Also return user role
        $roles = $user->roles;
        
        // Check if user is expert
        $is_expert = \KG_Core\Roles\RoleManager::is_expert( $user->ID );
        
        // Determine redirect URL based on role
        $redirect_url = $is_expert ? '/dashboard/expert' : '/dashboard';

        return new \WP_REST_Response( [
            'token' => $token,
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'username' => $user->user_login,
            'role' => ! empty( $roles ) ? $roles[0] : 'subscriber',
            'is_expert' => $is_expert,
            'redirect_url' => $redirect_url,
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

        // Get children information
        $children = get_user_meta( $user_id, '_kg_children', true );
        if ( ! is_array( $children ) ) {
            $children = [];
        }

        // Avatar URL
        $avatar_id = get_user_meta( $user_id, '_kg_avatar_id', true );
        $avatar_url = '';
        if ( $avatar_id ) {
            $avatar_url = wp_get_attachment_url( $avatar_id );
        }
        if ( ! $avatar_url ) {
            $google_avatar = get_user_meta( $user_id, 'google_avatar', true );
            $avatar_url = ! empty( $google_avatar ) ? $google_avatar : get_avatar_url( $user_id );
        }

        // Role
        $roles = $user->roles;
        $primary_role = ! empty( $roles ) ? $roles[0] : 'subscriber';

        // Display name and parent role
        $display_name = get_user_meta( $user_id, '_kg_display_name', true );
        $parent_role = get_user_meta( $user_id, '_kg_parent_role', true );

        return new \WP_REST_Response( [
            'user_id' => $user->ID,
            'id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'username' => $user->user_login,
            'display_name' => $display_name ?: $user->display_name,
            'parent_role' => $parent_role,
            'avatar_url' => $avatar_url,
            'children' => $children,
            'role' => $primary_role,
            'created_at' => $user->user_registered,
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
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return new \WP_Error( 'user_not_found', 'User not found', [ 'status' => 404 ] );
        }
        
        // Check if user is expert
        $is_expert = \KG_Core\Roles\RoleManager::is_expert( $user_id );
        
        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $phone = sanitize_text_field( $request->get_param( 'phone' ) );
        $display_name = sanitize_text_field( $request->get_param( 'display_name' ) );
        $parent_role = sanitize_text_field( $request->get_param( 'parent_role' ) );
        $avatar_id = absint( $request->get_param( 'avatar_id' ) );
        
        // New fields
        $gender = sanitize_text_field( $request->get_param( 'gender' ) );
        $birth_date = sanitize_text_field( $request->get_param( 'birth_date' ) );
        $biography = sanitize_textarea_field( $request->get_param( 'biography' ) );
        $social_links = $request->get_param( 'social_links' );
        $show_email = $request->get_param( 'show_email' );
        $expertise = $request->get_param( 'expertise' );

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
        
        // Gender validation
        if ( $gender !== null && $gender !== '' ) {
            $allowed_genders = [ 'male', 'female', 'other' ];
            if ( ! in_array( $gender, $allowed_genders ) ) {
                return new \WP_Error( 'invalid_gender', 'Gender must be one of: male, female, other', [ 'status' => 400 ] );
            }
            update_user_meta( $user_id, '_kg_gender', $gender );
        }
        
        // Birth date validation
        if ( $birth_date !== null && $birth_date !== '' ) {
            $birth_date_obj = \DateTime::createFromFormat( 'Y-m-d', $birth_date );
            
            // Check if date format is valid
            if ( ! $birth_date_obj ) {
                return new \WP_Error( 'invalid_birth_date', 'Birth date must be in YYYY-MM-DD format', [ 'status' => 400 ] );
            }
            
            // Check if date is not in the future
            $now = new \DateTime();
            if ( $birth_date_obj > $now ) {
                return new \WP_Error( 'invalid_birth_date', 'Birth date cannot be in the future', [ 'status' => 400 ] );
            }
            
            update_user_meta( $user_id, '_kg_birth_date', $birth_date );
        }
        
        // Biography (experts only)
        if ( $biography !== null && $biography !== '' ) {
            if ( ! $is_expert ) {
                return new \WP_Error( 'not_expert', 'Only experts can set biography', [ 'status' => 403 ] );
            }
            update_user_meta( $user_id, '_kg_biography', $biography );
        }
        
        // Social links validation (experts only)
        if ( $social_links !== null ) {
            if ( ! $is_expert ) {
                return new \WP_Error( 'not_expert', 'Only experts can set social links', [ 'status' => 403 ] );
            }
            
            if ( is_array( $social_links ) ) {
                $allowed_platforms = [ 'instagram', 'twitter', 'linkedin', 'youtube', 'website' ];
                $sanitized_links = [];
                
                foreach ( $social_links as $platform => $url ) {
                    if ( in_array( $platform, $allowed_platforms ) ) {
                        $sanitized_links[ $platform ] = esc_url_raw( $url );
                    }
                }
                
                update_user_meta( $user_id, '_kg_social_links', $sanitized_links );
            }
        }
        
        // Show email (boolean)
        if ( $show_email !== null ) {
            $show_email_bool = filter_var( $show_email, FILTER_VALIDATE_BOOLEAN );
            update_user_meta( $user_id, '_kg_show_email', $show_email_bool );
        }
        
        // Expertise (experts only)
        if ( $expertise !== null ) {
            if ( ! $is_expert ) {
                return new \WP_Error( 'not_expert', 'Only experts can set expertise', [ 'status' => 403 ] );
            }
            
            if ( is_array( $expertise ) ) {
                $sanitized_expertise = [];
                foreach ( $expertise as $item ) {
                    $sanitized_expertise[] = sanitize_text_field( $item );
                }
                update_user_meta( $user_id, '_kg_expertise', $sanitized_expertise );
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

        // KVKK consent validation - more flexible control
        if ( empty( $kvkk_consent ) || 
             ( $kvkk_consent !== true && 
               $kvkk_consent !== 'true' && 
               $kvkk_consent !== 1 && 
               $kvkk_consent !== '1' && 
               $kvkk_consent !== 'on' ) ) {
            return new \WP_Error( 'kvkk_consent_required', 'KVKK consent is required', [ 'status' => 400 ] );
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
     * Get favorite items (recipes, ingredients, posts, discussions)
     */
    public function get_favorites( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $type = $request->get_param( 'type' ) ?: 'all';

        // Migrate legacy favorites if not done yet
        $this->migrate_legacy_favorites( $user_id );

        // Validate type parameter
        $allowed_types = [ 'all', 'recipe', 'ingredient', 'post', 'discussion' ];
        if ( ! in_array( $type, $allowed_types ) ) {
            return new \WP_Error( 'invalid_type', 'Invalid type parameter. Must be one of: all, recipe, ingredient, post, discussion', [ 'status' => 400 ] );
        }

        $response = [];
        $counts = [
            'all' => 0,
            'recipes' => 0,
            'ingredients' => 0,
            'posts' => 0,
            'discussions' => 0,
        ];

        // Get recipes
        if ( $type === 'all' || $type === 'recipe' ) {
            $recipes = $this->get_favorite_recipes( $user_id );
            $response['recipes'] = $recipes;
            $counts['recipes'] = count( $recipes );
            $counts['all'] += count( $recipes );
        }

        // Get ingredients
        if ( $type === 'all' || $type === 'ingredient' ) {
            $ingredients = $this->get_favorite_ingredients( $user_id );
            $response['ingredients'] = $ingredients;
            $counts['ingredients'] = count( $ingredients );
            $counts['all'] += count( $ingredients );
        }

        // Get posts
        if ( $type === 'all' || $type === 'post' ) {
            $posts = $this->get_favorite_posts( $user_id );
            $response['posts'] = $posts;
            $counts['posts'] = count( $posts );
            $counts['all'] += count( $posts );
        }

        // Get discussions
        if ( $type === 'all' || $type === 'discussion' ) {
            $discussions = $this->get_favorite_discussions( $user_id );
            $response['discussions'] = $discussions;
            $counts['discussions'] = count( $discussions );
            $counts['all'] += count( $discussions );
        }

        $response['counts'] = $counts;

        return new \WP_REST_Response( $response, 200 );
    }

    /**
     * Add item to favorites
     */
    public function add_favorite( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $item_id = absint( $request->get_param( 'item_id' ) );
        $item_type = sanitize_text_field( $request->get_param( 'item_type' ) );

        // Validate required parameters
        if ( ! $item_id ) {
            return new \WP_Error( 'missing_item_id', 'Item ID is required', [ 'status' => 400 ] );
        }

        if ( empty( $item_type ) ) {
            return new \WP_Error( 'missing_item_type', 'Item type is required', [ 'status' => 400 ] );
        }

        // Validate item_type
        $allowed_types = [ 'recipe', 'ingredient', 'post', 'discussion' ];
        if ( ! in_array( $item_type, $allowed_types ) ) {
            return new \WP_Error( 'invalid_item_type', 'Invalid item type. Must be one of: recipe, ingredient, post, discussion', [ 'status' => 400 ] );
        }

        // Get expected post type for validation
        $expected_post_type = $this->get_post_type_for_item_type( $item_type );

        // Verify post exists and has correct type
        $post = get_post( $item_id );
        if ( ! $post || $post->post_type !== $expected_post_type ) {
            return new \WP_Error( 'invalid_item', 'Invalid item ID or item does not exist', [ 'status' => 404 ] );
        }

        // Get the appropriate meta key
        $meta_key = '_kg_favorite_' . $item_type . 's';
        if ( $item_type === 'post' ) {
            $meta_key = '_kg_favorite_posts';
        } elseif ( $item_type === 'discussion' ) {
            $meta_key = '_kg_favorite_discussions';
        }

        $favorites = get_user_meta( $user_id, $meta_key, true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        // Check if already in favorites
        if ( in_array( $item_id, $favorites ) ) {
            return new \WP_REST_Response( [ 
                'success' => true,
                'message' => 'Item already in favorites' 
            ], 200 );
        }

        $favorites[] = $item_id;
        update_user_meta( $user_id, $meta_key, $favorites );

        return new \WP_REST_Response( [ 
            'success' => true,
            'message' => 'Item added to favorites' 
        ], 201 );
    }

    /**
     * Remove item from favorites
     */
    public function remove_favorite( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $item_id = absint( $request->get_param( 'id' ) );
        $item_type = sanitize_text_field( $request->get_param( 'type' ) );

        // Validate required parameters
        if ( ! $item_id ) {
            return new \WP_Error( 'missing_item_id', 'Item ID is required', [ 'status' => 400 ] );
        }

        if ( empty( $item_type ) ) {
            return new \WP_Error( 'missing_type', 'Type parameter is required', [ 'status' => 400 ] );
        }

        // Validate item_type
        $allowed_types = [ 'recipe', 'ingredient', 'post', 'discussion' ];
        if ( ! in_array( $item_type, $allowed_types ) ) {
            return new \WP_Error( 'invalid_type', 'Invalid type. Must be one of: recipe, ingredient, post, discussion', [ 'status' => 400 ] );
        }

        // Get the appropriate meta key
        $meta_key = '_kg_favorite_' . $item_type . 's';
        if ( $item_type === 'post' ) {
            $meta_key = '_kg_favorite_posts';
        } elseif ( $item_type === 'discussion' ) {
            $meta_key = '_kg_favorite_discussions';
        }

        $favorites = get_user_meta( $user_id, $meta_key, true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        $favorites = array_filter( $favorites, function( $id ) use ( $item_id ) {
            return $id !== $item_id;
        });

        update_user_meta( $user_id, $meta_key, array_values( $favorites ) );

        return new \WP_REST_Response( [ 
            'success' => true,
            'message' => 'Item removed from favorites' 
        ], 200 );
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
        
        // Check if user is expert
        $is_expert = \KG_Core\Roles\RoleManager::is_expert( $user_id );

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

        // Add user role
        $roles = $user->roles;
        $primary_role = ! empty( $roles ) ? $roles[0] : 'subscriber';
        
        // Get new fields
        $gender = get_user_meta( $user_id, '_kg_gender', true );
        $birth_date = get_user_meta( $user_id, '_kg_birth_date', true );
        $show_email = get_user_meta( $user_id, '_kg_show_email', true );
        
        // Build base response
        $response = [
            'id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'username' => $user->user_login,
            'display_name' => $display_name ?: $user->display_name,
            'parent_role' => $parent_role,
            'avatar_url' => $avatar_url,
            'children' => $children,
            'role' => $primary_role,
            'followed_circles' => $followed_circles,
            'stats' => [
                'question_count' => $question_count,
                'comment_count' => $comment_count,
            ],
            'gender' => $gender ?: '',
            'birth_date' => $birth_date ?: '',
            'show_email' => (bool) $show_email,
            'is_expert' => $is_expert,
        ];
        
        // Add expert-only fields
        if ( $is_expert ) {
            $biography = get_user_meta( $user_id, '_kg_biography', true );
            $social_links = get_user_meta( $user_id, '_kg_social_links', true );
            $expertise = get_user_meta( $user_id, '_kg_expertise', true );
            
            $response['biography'] = $biography ?: '';
            $response['social_links'] = is_array( $social_links ) ? $social_links : [];
            $response['expertise'] = is_array( $expertise ) ? $expertise : [];
        }

        return new \WP_REST_Response( $response, 200 );
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
            'username' => $user->user_login,
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

    /**
     * Get favorite recipes with formatted data
     */
    private function get_favorite_recipes( $user_id ) {
        $favorites = get_user_meta( $user_id, '_kg_favorite_recipes', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        $recipes = [];
        foreach ( $favorites as $recipe_id ) {
            $post = get_post( $recipe_id );
            if ( $post && $post->post_type === 'recipe' && $post->post_status === 'publish' ) {
                $recipes[] = $this->format_recipe_card( $post );
            }
        }

        return $recipes;
    }

    /**
     * Get favorite ingredients with formatted data
     */
    private function get_favorite_ingredients( $user_id ) {
        $favorites = get_user_meta( $user_id, '_kg_favorite_ingredients', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        $ingredients = [];
        foreach ( $favorites as $ingredient_id ) {
            $post = get_post( $ingredient_id );
            if ( $post && $post->post_type === 'ingredient' && $post->post_status === 'publish' ) {
                $ingredients[] = $this->format_ingredient_card( $post );
            }
        }

        return $ingredients;
    }

    /**
     * Get favorite posts with formatted data
     */
    private function get_favorite_posts( $user_id ) {
        $favorites = get_user_meta( $user_id, '_kg_favorite_posts', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        $posts = [];
        foreach ( $favorites as $post_id ) {
            $post = get_post( $post_id );
            if ( $post && $post->post_type === 'post' && $post->post_status === 'publish' ) {
                $posts[] = $this->format_post_card( $post );
            }
        }

        return $posts;
    }

    /**
     * Get favorite discussions with formatted data
     */
    private function get_favorite_discussions( $user_id ) {
        $favorites = get_user_meta( $user_id, '_kg_favorite_discussions', true );
        if ( ! is_array( $favorites ) ) {
            $favorites = [];
        }

        $discussions = [];
        foreach ( $favorites as $discussion_id ) {
            $post = get_post( $discussion_id );
            if ( $post && $post->post_type === 'discussion' && $post->post_status === 'publish' ) {
                $discussions[] = $this->format_discussion_card( $post );
            }
        }

        return $discussions;
    }

    /**
     * Format recipe data for card display
     */
    private function format_recipe_card( $post ) {
        // Get age group taxonomy
        $age_groups = wp_get_post_terms( $post->ID, 'age-group' );
        $age_group = '';
        $age_group_color = '';
        
        if ( ! empty( $age_groups ) && ! is_wp_error( $age_groups ) ) {
            $age_group = $age_groups[0]->name;
            $age_group_color = get_term_meta( $age_groups[0]->term_id, '_kg_color', true ) ?: '#22C55E';
        }

        // Get categories
        $categories = [];
        $meal_types = wp_get_post_terms( $post->ID, 'meal-type' );
        if ( ! empty( $meal_types ) && ! is_wp_error( $meal_types ) ) {
            foreach ( $meal_types as $meal_type ) {
                $categories[] = $meal_type->name;
            }
        }

        return [
            'id' => $post->ID,
            'title' => \KG_Core\Utils\Helper::decode_html_entities( $post->post_title ),
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
            'age_group' => $age_group,
            'age_group_color' => $age_group_color,
            'prep_time' => get_post_meta( $post->ID, '_kg_prep_time', true ),
            'categories' => $categories,
        ];
    }

    /**
     * Format ingredient data for card display
     */
    private function format_ingredient_card( $post ) {
        $start_age = get_post_meta( $post->ID, '_kg_start_age', true );
        $allergy_risk = get_post_meta( $post->ID, '_kg_allergy_risk', true ) ?: 'Düşük';

        return [
            'id' => $post->ID,
            'name' => \KG_Core\Utils\Helper::decode_html_entities( $post->post_title ),
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
            'start_age' => $start_age,
            'allergy_risk' => $allergy_risk,
        ];
    }

    /**
     * Format post data for card display
     */
    private function format_post_card( $post ) {
        // Get category
        $categories = get_the_category( $post->ID );
        $category = '';
        if ( ! empty( $categories ) ) {
            $category = $categories[0]->name;
        }

        // Calculate read time (assuming 200 words per minute)
        $content = $post->post_content;
        $word_count = str_word_count( strip_tags( $content ) );
        $read_time = ceil( $word_count / 200 );

        return [
            'id' => $post->ID,
            'title' => \KG_Core\Utils\Helper::decode_html_entities( $post->post_title ),
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
            'category' => $category,
            'read_time' => $read_time . ' dk',
        ];
    }

    /**
     * Format discussion data for card display
     */
    private function format_discussion_card( $post ) {
        // Get author data
        $author = get_user_by( 'id', $post->post_author );
        $author_name = $author ? $author->display_name : 'Unknown';
        
        // Get author avatar
        $avatar_id = get_user_meta( $post->post_author, '_kg_avatar_id', true );
        $author_avatar = '';
        if ( $avatar_id ) {
            $author_avatar = wp_get_attachment_url( $avatar_id );
        }
        if ( ! $author_avatar ) {
            $google_avatar = get_user_meta( $post->post_author, 'google_avatar', true );
            $author_avatar = ! empty( $google_avatar ) ? $google_avatar : get_avatar_url( $post->post_author );
        }

        // Get circle (community_circle taxonomy)
        $circles = wp_get_post_terms( $post->ID, 'community_circle' );
        $circle = '';
        if ( ! empty( $circles ) && ! is_wp_error( $circles ) ) {
            $circle = $circles[0]->name;
        }

        // Get answer count
        $answer_count = get_comments_number( $post->ID );

        return [
            'id' => $post->ID,
            'title' => \KG_Core\Utils\Helper::decode_html_entities( $post->post_title ),
            'slug' => $post->post_name,
            'author' => $author_name,
            'author_avatar' => $author_avatar,
            'answer_count' => $answer_count,
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

    /**
     * Migrate legacy _kg_favorites to _kg_favorite_recipes
     */
    private function migrate_legacy_favorites( $user_id ) {
        // Check if migration already done
        $migrated = get_user_meta( $user_id, '_kg_favorites_migrated', true );
        if ( $migrated === '1' ) {
            return;
        }

        // Get legacy favorites
        $legacy_favorites = get_user_meta( $user_id, '_kg_favorites', true );
        if ( is_array( $legacy_favorites ) && ! empty( $legacy_favorites ) ) {
            // Migrate to new meta key
            $existing_recipes = get_user_meta( $user_id, '_kg_favorite_recipes', true );
            if ( ! is_array( $existing_recipes ) ) {
                $existing_recipes = [];
            }

            // Merge and deduplicate
            $merged = array_unique( array_merge( $existing_recipes, $legacy_favorites ) );
            update_user_meta( $user_id, '_kg_favorite_recipes', $merged );
        }

        // Mark as migrated
        update_user_meta( $user_id, '_kg_favorites_migrated', '1' );
    }
    
    /**
     * Get expert public profile by username
     * Returns public profile for experts only
     */
    public function get_expert_public_profile( $request ) {
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
        
        // Check if user is expert
        if ( ! \KG_Core\Roles\RoleManager::is_expert( $user_id ) ) {
            return new \WP_Error( 'not_expert', 'This profile is not available for non-expert users', [ 'status' => 403 ] );
        }

        // Get user meta fields
        $avatar_id = get_user_meta( $user_id, '_kg_avatar_id', true );
        $biography = get_user_meta( $user_id, '_kg_biography', true );
        $social_links = get_user_meta( $user_id, '_kg_social_links', true );
        $expertise = get_user_meta( $user_id, '_kg_expertise', true );
        $show_email = get_user_meta( $user_id, '_kg_show_email', true );
        
        // Get avatar URL
        $avatar_url = '';
        if ( $avatar_id ) {
            $avatar_url = wp_get_attachment_url( $avatar_id );
        }
        if ( ! $avatar_url ) {
            $google_avatar = get_user_meta( $user_id, 'google_avatar', true );
            $avatar_url = ! empty( $google_avatar ) ? $google_avatar : get_avatar_url( $user_id );
        }

        // Get statistics
        $total_recipes = $this->count_user_recipes( $user_id );
        $total_blog_posts = $this->count_user_blog_posts( $user_id );
        $total_answers = $this->get_user_answer_count( $user_id );
        $total_questions = $this->get_user_question_count( $user_id );
        
        // Get content
        $recipes = $this->get_user_recipes( $user_id, 6 );
        $blog_posts = $this->get_user_blog_posts( $user_id, 6 );
        $answered_questions = $this->get_user_answered_questions( $user_id, 6 );
        $asked_questions = $this->get_user_asked_questions( $user_id, 6 );
        
        // Get role display name
        $role_display = $this->get_role_display_name( $user );

        // Build public profile data
        $public_data = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'avatar_url' => $avatar_url,
            'biography' => $biography ?: '',
            'expertise' => is_array( $expertise ) ? $expertise : [],
            'social_links' => is_array( $social_links ) ? $social_links : [],
            'role' => $role_display,
            'stats' => [
                'total_recipes' => $total_recipes,
                'total_blog_posts' => $total_blog_posts,
                'total_answers' => $total_answers,
                'total_questions' => $total_questions,
            ],
            'recipes' => $recipes,
            'blog_posts' => $blog_posts,
            'answered_questions' => $answered_questions,
            'asked_questions' => $asked_questions,
        ];
        
        // Add email if show_email is enabled
        if ( $show_email ) {
            $public_data['email'] = $user->user_email;
        }

        return new \WP_REST_Response( $public_data, 200 );
    }
    
    /**
     * Get user's recipes
     * 
     * @param int $user_id User ID
     * @param int $limit Number of recipes to return
     * @return array Array of recipe data
     */
    private function get_user_recipes( $user_id, $limit = 6 ) {
        $args = [
            'post_type' => 'recipe',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $query = new \WP_Query( $args );
        $recipes = [];
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post = get_post();
                $recipes[] = $this->format_recipe_card( $post );
            }
            wp_reset_postdata();
        }
        
        return $recipes;
    }
    
    /**
     * Get user's blog posts
     * 
     * @param int $user_id User ID
     * @param int $limit Number of posts to return
     * @return array Array of post data
     */
    private function get_user_blog_posts( $user_id, $limit = 6 ) {
        $args = [
            'post_type' => 'post',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $query = new \WP_Query( $args );
        $posts = [];
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post = get_post();
                $posts[] = $this->format_post_card( $post );
            }
            wp_reset_postdata();
        }
        
        return $posts;
    }
    
    /**
     * Get questions answered by user
     * 
     * @param int $user_id User ID
     * @param int $limit Number of questions to return
     * @return array Array of discussion data
     */
    private function get_user_answered_questions( $user_id, $limit = 6 ) {
        global $wpdb;
        
        // Get unique discussion IDs where user has commented, ordered by most recent comment
        $query = $wpdb->prepare(
            "SELECT DISTINCT c.comment_post_ID 
            FROM {$wpdb->comments} c
            INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
            WHERE c.user_id = %d 
            AND c.comment_approved = '1'
            AND p.post_type = 'discussion'
            AND p.post_status = 'publish'
            ORDER BY c.comment_date DESC
            LIMIT %d",
            $user_id,
            $limit
        );
        
        $discussion_ids = $wpdb->get_col( $query );
        
        $discussions = [];
        foreach ( $discussion_ids as $discussion_id ) {
            $post = get_post( $discussion_id );
            if ( $post ) {
                $discussions[] = $this->format_discussion_card( $post );
            }
        }
        
        return $discussions;
    }
    
    /**
     * Get questions asked by user
     * 
     * @param int $user_id User ID
     * @param int $limit Number of questions to return
     * @return array Array of discussion data
     */
    private function get_user_asked_questions( $user_id, $limit = 6 ) {
        $args = [
            'post_type' => 'discussion',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $query = new \WP_Query( $args );
        $discussions = [];
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post = get_post();
                $discussions[] = $this->format_discussion_card( $post );
            }
            wp_reset_postdata();
        }
        
        return $discussions;
    }
    
    /**
     * Count user's recipes
     * 
     * @param int $user_id User ID
     * @return int Number of recipes
     */
    private function count_user_recipes( $user_id ) {
        $count = wp_count_posts( 'recipe' );
        
        // Get count for this specific author
        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'recipe' AND post_status = 'publish' AND post_author = %d",
            $user_id
        ) );
        
        return (int) $count;
    }
    
    /**
     * Count user's blog posts
     * 
     * @param int $user_id User ID
     * @return int Number of blog posts
     */
    private function count_user_blog_posts( $user_id ) {
        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_author = %d",
            $user_id
        ) );
        
        return (int) $count;
    }
    
    /**
     * Get user's answer count
     * 
     * @param int $user_id User ID
     * @return int Number of answers
     */
    private function get_user_answer_count( $user_id ) {
        $args = [
            'user_id' => $user_id,
            'post_type' => 'discussion',
            'status' => 'approve',
            'count' => true,
        ];
        
        return get_comments( $args );
    }
    
    /**
     * Get role display name in Turkish
     * 
     * @param WP_User $user User object
     * @return string Turkish role name
     */
    private function get_role_display_name( $user ) {
        $roles = $user->roles;
        
        if ( empty( $roles ) ) {
            return 'Üye';
        }
        
        $role_map = [
            'administrator' => 'Yönetici',
            'editor' => 'Editör',
            'author' => 'Yazar',
            'kg_expert' => 'Beslenme Uzmanı',
            'kg_parent' => 'Ebeveyn',
            'subscriber' => 'Üye',
        ];
        
        $primary_role = $roles[0];
        
        return isset( $role_map[ $primary_role ] ) ? $role_map[ $primary_role ] : ucfirst( $primary_role );
    }

    /**
     * Get user's BLW test results
     */
    public function get_blw_results( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $blw_results = get_user_meta( $user_id, '_kg_blw_results', true );

        if ( ! is_array( $blw_results ) ) {
            $blw_results = [];
        }

        // Sort by timestamp (newest first)
        usort( $blw_results, function( $a, $b ) {
            return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
        });

        return new \WP_REST_Response( $blw_results, 200 );
    }

    /**
     * Get BLW test results for a specific child
     */
    public function get_child_blw_results( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = $request->get_param( 'child_id' );

        if ( empty( $child_id ) ) {
            return new \WP_Error( 'missing_child_id', 'Child ID is required', [ 'status' => 400 ] );
        }

        // Verify child belongs to user
        $children = get_user_meta( $user_id, '_kg_children', true );
        if ( ! is_array( $children ) ) {
            return new \WP_Error( 'no_children', 'No children found', [ 'status' => 404 ] );
        }

        $child_exists = false;
        foreach ( $children as $child ) {
            if ( $child['id'] === $child_id ) {
                $child_exists = true;
                break;
            }
        }

        if ( ! $child_exists ) {
            return new \WP_Error( 'child_not_found', 'Child not found or does not belong to user', [ 'status' => 404 ] );
        }

        // Get BLW results
        $all_results = get_user_meta( $user_id, '_kg_blw_results', true );
        if ( ! is_array( $all_results ) ) {
            $all_results = [];
        }

        // Filter by child_id
        $child_results = array_filter( $all_results, function( $result ) use ( $child_id ) {
            return isset( $result['child_id'] ) && $result['child_id'] === $child_id;
        });

        // Sort by timestamp (newest first)
        usort( $child_results, function( $a, $b ) {
            return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
        });

        return new \WP_REST_Response( array_values( $child_results ), 200 );
    }
}

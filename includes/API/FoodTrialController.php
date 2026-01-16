<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;

/**
 * Food Trial Controller
 * Manages food trial tracking for children
 */
class FoodTrialController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // 1. FIRST: Register static routes (stats)
        // GET /kg/v1/tools/food-trials/stats - Get statistics
        register_rest_route( 'kg/v1', '/tools/food-trials/stats', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_stats' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // 2. SECOND: Register collection routes (GET list, POST create)
        // GET /kg/v1/tools/food-trials - List all food trials for authenticated user
        register_rest_route( 'kg/v1', '/tools/food-trials', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_food_trials' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // POST /kg/v1/tools/food-trials - Add new food trial
        register_rest_route( 'kg/v1', '/tools/food-trials', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_food_trial' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // 3. LAST: Register dynamic {id} routes
        // GET /kg/v1/tools/food-trials/{id} - Get single food trial
        register_rest_route( 'kg/v1', '/tools/food-trials/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_food_trial' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // PUT /kg/v1/tools/food-trials/{id} - Update food trial
        register_rest_route( 'kg/v1', '/tools/food-trials/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_food_trial' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // DELETE /kg/v1/tools/food-trials/{id} - Delete food trial
        register_rest_route( 'kg/v1', '/tools/food-trials/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'delete_food_trial' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
    }

    /**
     * Check if user is authenticated
     */
    public function check_authentication( $request ) {
        $token = JWTHandler::get_token_from_request();
        
        if ( ! $token ) {
            return new \WP_Error( 'unauthorized', 'Kimlik doğrulama gerekli', [ 'status' => 401 ] );
        }

        $payload = JWTHandler::validate_token( $token );
        
        if ( ! $payload ) {
            return new \WP_Error( 'invalid_token', 'Geçersiz token', [ 'status' => 401 ] );
        }

        return true;
    }

    /**
     * Get authenticated user ID
     */
    private function get_user_id() {
        $token = JWTHandler::get_token_from_request();
        $payload = JWTHandler::validate_token( $token );
        return $payload['user_id'] ?? null;
    }

    /**
     * Get all food trials for authenticated user
     */
    public function get_food_trials( $request ) {
        $user_id = $this->get_user_id();
        
        if ( ! $user_id ) {
            return new \WP_Error( 'unauthorized', 'Kullanıcı bulunamadı', [ 'status' => 401 ] );
        }

        $child_id = $request->get_param( 'child_id' );
        $start_date = $request->get_param( 'start_date' );
        $end_date = $request->get_param( 'end_date' );
        
        // Get all food trials from user meta
        $all_trials = get_user_meta( $user_id, '_kg_food_trials', true );
        
        if ( ! is_array( $all_trials ) ) {
            $all_trials = [];
        }

        // Filter by child_id if provided
        if ( $child_id ) {
            $all_trials = $this->filter_trials_by_child( $all_trials, $child_id );
        }

        // Filter by date range if provided
        if ( $start_date && $end_date ) {
            $all_trials = $this->filter_trials_by_date_range( $all_trials, $start_date, $end_date );
        }

        // Sort by trial_date descending
        usort( $all_trials, function( $a, $b ) {
            return strtotime( $b['trial_date'] ) - strtotime( $a['trial_date'] );
        });

        // Mark new foods (first time tried)
        $all_trials = $this->mark_new_foods( $all_trials, $user_id );

        return new \WP_REST_Response( [
            'trials' => array_values( $all_trials ),
            'total' => count( $all_trials ),
        ], 200 );
    }

    /**
     * Add new food trial
     */
    public function add_food_trial( $request ) {
        $user_id = $this->get_user_id();
        
        if ( ! $user_id ) {
            return new \WP_Error( 'unauthorized', 'Kullanıcı bulunamadı', [ 'status' => 401 ] );
        }

        // Validate required fields - ingredient_id is now optional
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $ingredient_id = $request->get_param( 'ingredient_id' ) ? (int) $request->get_param( 'ingredient_id' ) : null;
        $ingredient_name = sanitize_text_field( $request->get_param( 'ingredient_name' ) );
        $trial_date = sanitize_text_field( $request->get_param( 'trial_date' ) );
        $result = sanitize_text_field( $request->get_param( 'result' ) );

        // child_id, trial_date and result are required
        // ingredient_name OR ingredient_id must be provided
        if ( empty( $child_id ) || empty( $trial_date ) || empty( $result ) ) {
            return new \WP_Error( 'missing_fields', 'Gerekli alanlar: child_id, trial_date, result', [ 'status' => 400 ] );
        }

        // Determine the ingredient name
        $final_ingredient_name = '';
        
        if ( $ingredient_id ) {
            // Get ingredient name from WordPress
            $wp_ingredient_name = get_the_title( $ingredient_id );
            if ( ! empty( $wp_ingredient_name ) ) {
                $final_ingredient_name = $wp_ingredient_name;
            }
        }
        
        // If not found from WordPress, use the provided ingredient_name
        if ( empty( $final_ingredient_name ) && ! empty( $ingredient_name ) ) {
            $final_ingredient_name = $ingredient_name;
        }
        
        // If still empty, return error
        if ( empty( $final_ingredient_name ) ) {
            return new \WP_Error( 'missing_ingredient', 'Besin adı gerekli (ingredient_name veya geçerli ingredient_id)', [ 'status' => 400 ] );
        }

        // Validate result value
        $valid_results = [ 'success', 'mild_reaction', 'reaction', 'severe_reaction' ];
        if ( ! in_array( $result, $valid_results ) ) {
            return new \WP_Error( 'invalid_result', 'Geçersiz result değeri. Geçerli değerler: success, mild_reaction, reaction, severe_reaction', [ 'status' => 400 ] );
        }

        // Get all existing trials
        $all_trials = get_user_meta( $user_id, '_kg_food_trials', true );
        if ( ! is_array( $all_trials ) ) {
            $all_trials = [];
        }

        // Generate UUID
        $trial_id = $this->generate_uuid();

        // Create new trial
        $new_trial = [
            'id' => $trial_id,
            'child_id' => $child_id,
            'ingredient_id' => $ingredient_id, // can be null
            'ingredient_name' => $final_ingredient_name,
            'trial_date' => $trial_date,
            'result' => $result,
            'form' => sanitize_text_field( $request->get_param( 'form' ) ?: '' ),
            'reaction' => $this->map_result_to_reaction( $result ), // Frontend format
            'reaction_notes' => sanitize_textarea_field( $request->get_param( 'reaction_notes' ) ?: '' ),
            'amount' => sanitize_text_field( $request->get_param( 'amount' ) ?: '' ),
            'retry_after' => $this->calculate_retry_after( $result, $trial_date ),
            'is_new' => $this->is_new_food( $user_id, $child_id, $final_ingredient_name ),
            'created_at' => current_time( 'c' ),
        ];

        // Add to trials array
        $all_trials[] = $new_trial;

        // Save to user meta
        update_user_meta( $user_id, '_kg_food_trials', $all_trials );

        return new \WP_REST_Response( $new_trial, 201 );
    }

    /**
     * Get single food trial
     */
    public function get_food_trial( $request ) {
        $user_id = $this->get_user_id();
        
        if ( ! $user_id ) {
            return new \WP_Error( 'unauthorized', 'Kullanıcı bulunamadı', [ 'status' => 401 ] );
        }

        $trial_id = $request->get_param( 'id' );

        // Get all trials
        $all_trials = get_user_meta( $user_id, '_kg_food_trials', true );
        if ( ! is_array( $all_trials ) ) {
            return new \WP_Error( 'not_found', 'Deneme bulunamadı', [ 'status' => 404 ] );
        }

        // Find trial by ID
        foreach ( $all_trials as $trial ) {
            if ( $trial['id'] === $trial_id ) {
                return new \WP_REST_Response( $trial, 200 );
            }
        }

        return new \WP_Error( 'not_found', 'Deneme bulunamadı', [ 'status' => 404 ] );
    }

    /**
     * Update food trial
     */
    public function update_food_trial( $request ) {
        $user_id = $this->get_user_id();
        
        if ( ! $user_id ) {
            return new \WP_Error( 'unauthorized', 'Kullanıcı bulunamadı', [ 'status' => 401 ] );
        }

        $trial_id = $request->get_param( 'id' );

        // Get all trials
        $all_trials = get_user_meta( $user_id, '_kg_food_trials', true );
        if ( ! is_array( $all_trials ) ) {
            return new \WP_Error( 'not_found', 'Deneme bulunamadı', [ 'status' => 404 ] );
        }

        // Find and update trial
        $found = false;
        foreach ( $all_trials as $index => $trial ) {
            if ( $trial['id'] === $trial_id ) {
                // Update fields if provided
                if ( $request->get_param( 'result' ) ) {
                    $result = sanitize_text_field( $request->get_param( 'result' ) );
                    $valid_results = [ 'success', 'mild_reaction', 'reaction', 'severe_reaction' ];
                    if ( in_array( $result, $valid_results ) ) {
                        $all_trials[ $index ]['result'] = $result;
                        $all_trials[ $index ]['retry_after'] = $this->calculate_retry_after( $result, $trial['trial_date'] );
                    }
                }
                
                if ( $request->get_param( 'reaction_notes' ) !== null ) {
                    $all_trials[ $index ]['reaction_notes'] = sanitize_textarea_field( $request->get_param( 'reaction_notes' ) );
                }
                
                if ( $request->get_param( 'amount' ) !== null ) {
                    $all_trials[ $index ]['amount'] = sanitize_text_field( $request->get_param( 'amount' ) );
                }
                
                if ( $request->get_param( 'form' ) !== null ) {
                    $all_trials[ $index ]['form'] = sanitize_text_field( $request->get_param( 'form' ) );
                }

                $all_trials[ $index ]['updated_at'] = current_time( 'c' );
                
                $found = true;
                $updated_trial = $all_trials[ $index ];
                break;
            }
        }

        if ( ! $found ) {
            return new \WP_Error( 'not_found', 'Deneme bulunamadı', [ 'status' => 404 ] );
        }

        // Save to user meta
        update_user_meta( $user_id, '_kg_food_trials', $all_trials );

        return new \WP_REST_Response( $updated_trial, 200 );
    }

    /**
     * Delete food trial
     */
    public function delete_food_trial( $request ) {
        $user_id = $this->get_user_id();
        
        if ( ! $user_id ) {
            return new \WP_Error( 'unauthorized', 'Kullanıcı bulunamadı', [ 'status' => 401 ] );
        }

        $trial_id = $request->get_param( 'id' );

        // Get all trials
        $all_trials = get_user_meta( $user_id, '_kg_food_trials', true );
        if ( ! is_array( $all_trials ) ) {
            return new \WP_Error( 'not_found', 'Deneme bulunamadı', [ 'status' => 404 ] );
        }

        // Filter out the trial to delete
        $original_count = count( $all_trials );
        $all_trials = array_filter( $all_trials, function( $trial ) use ( $trial_id ) {
            return $trial['id'] !== $trial_id;
        });

        if ( count( $all_trials ) === $original_count ) {
            return new \WP_Error( 'not_found', 'Deneme bulunamadı', [ 'status' => 404 ] );
        }

        // Save to user meta
        update_user_meta( $user_id, '_kg_food_trials', array_values( $all_trials ) );

        return new \WP_REST_Response( [ 'message' => 'Deneme silindi' ], 200 );
    }

    /**
     * Get statistics for food trials
     */
    public function get_stats( $request ) {
        $user_id = $this->get_user_id();
        
        if ( ! $user_id ) {
            return new \WP_Error( 'unauthorized', 'Kullanıcı bulunamadı', [ 'status' => 401 ] );
        }

        $child_id = $request->get_param( 'child_id' );

        // Get all trials
        $all_trials = get_user_meta( $user_id, '_kg_food_trials', true );
        if ( ! is_array( $all_trials ) ) {
            $all_trials = [];
        }

        // Filter by child_id if provided
        if ( $child_id ) {
            $all_trials = $this->filter_trials_by_child( $all_trials, $child_id );
        }

        // Calculate statistics
        $stats = [
            'total_trials' => count( $all_trials ),
            'success' => 0,
            'mild_reaction' => 0,
            'reaction' => 0,
            'severe_reaction' => 0,
            'recent_trials' => [],
        ];

        foreach ( $all_trials as $trial ) {
            $result = $trial['result'];
            if ( isset( $stats[ $result ] ) ) {
                $stats[ $result ]++;
            }
        }

        // Get 5 most recent trials
        usort( $all_trials, function( $a, $b ) {
            return strtotime( $b['trial_date'] ) - strtotime( $a['trial_date'] );
        });
        $stats['recent_trials'] = array_slice( $all_trials, 0, 5 );

        return new \WP_REST_Response( $stats, 200 );
    }

    /**
     * Calculate retry after date based on reaction
     */
    private function calculate_retry_after( $result, $trial_date ) {
        if ( $result === 'success' ) {
            return null;
        }

        $date = new \DateTime( $trial_date );
        
        switch ( $result ) {
            case 'mild_reaction':
                $date->add( new \DateInterval( 'P14D' ) ); // 2 weeks
                break;
            case 'reaction':
                $date->add( new \DateInterval( 'P1M' ) ); // 1 month
                break;
            case 'severe_reaction':
                $date->add( new \DateInterval( 'P3M' ) ); // 3 months
                break;
        }

        return $date->format( 'Y-m-d' );
    }

    /**
     * Filter trials by child ID
     * 
     * @param array $trials All trials
     * @param string $child_id Child ID to filter by
     * @return array Filtered trials
     */
    private function filter_trials_by_child( $trials, $child_id ) {
        return array_filter( $trials, function( $trial ) use ( $child_id ) {
            return $trial['child_id'] === $child_id;
        });
    }

    /**
     * Filter trials by date range
     * 
     * @param array $trials All trials
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @return array Filtered trials
     */
    private function filter_trials_by_date_range( $trials, $start_date, $end_date ) {
        $start = strtotime( $start_date );
        $end = strtotime( $end_date );
        
        // Validate dates
        if ( $start === false || $end === false ) {
            return $trials; // Return unfiltered if dates are invalid
        }
        
        return array_filter( $trials, function( $trial ) use ( $start, $end ) {
            $trial_date = strtotime( $trial['trial_date'] );
            return $trial_date !== false && $trial_date >= $start && $trial_date <= $end;
        });
    }

    /**
     * Mark foods as new (first time tried by this child)
     * 
     * @param array $trials Trials to mark
     * @param int $user_id User ID
     * @return array Trials with is_new flag
     */
    private function mark_new_foods( $trials, $user_id ) {
        // Get all trials to check history
        $all_user_trials = get_user_meta( $user_id, '_kg_food_trials', true );
        if ( ! is_array( $all_user_trials ) ) {
            $all_user_trials = [];
        }
        
        // Group by child_id and ingredient
        $history = [];
        foreach ( $all_user_trials as $trial ) {
            $key = $trial['child_id'] . '_' . strtolower( $trial['ingredient_name'] );
            if ( ! isset( $history[ $key ] ) ) {
                $history[ $key ] = $trial['trial_date'];
            } elseif ( strtotime( $trial['trial_date'] ) < strtotime( $history[ $key ] ) ) {
                $history[ $key ] = $trial['trial_date'];
            }
        }
        
        // Mark as new if this is the first occurrence
        foreach ( $trials as &$trial ) {
            $key = $trial['child_id'] . '_' . strtolower( $trial['ingredient_name'] );
            $trial['is_new'] = ( $history[ $key ] === $trial['trial_date'] );
        }
        
        return $trials;
    }

    /**
     * Map backend result to frontend reaction format
     * 
     * @param string $result Backend result value
     * @return string Frontend reaction value
     */
    private function map_result_to_reaction( $result ) {
        switch ( $result ) {
            case 'success': return 'none';
            case 'mild_reaction': return 'mild';
            case 'reaction': return 'moderate';
            case 'severe_reaction': return 'severe';
            default: return 'none';
        }
    }

    /**
     * Check if this is the first time this food is tried by this child
     * 
     * @param int $user_id User ID
     * @param string $child_id Child ID
     * @param string $ingredient_name Ingredient name
     * @return bool True if this is a new food for this child
     */
    private function is_new_food( $user_id, $child_id, $ingredient_name ) {
        $all_trials = get_user_meta( $user_id, '_kg_food_trials', true );
        if ( ! is_array( $all_trials ) ) {
            return true;
        }
        
        $normalized_name = strtolower( trim( $ingredient_name ) );
        
        foreach ( $all_trials as $trial ) {
            if ( $trial['child_id'] === $child_id && 
                 strtolower( trim( $trial['ingredient_name'] ) ) === $normalized_name ) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Generate UUID
     */
    private function generate_uuid() {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}

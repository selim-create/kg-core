<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Services\RecommendationService;
use KG_Core\Services\SafetyCheckService;
use KG_Core\Services\NutritionTrackerService;
use KG_Core\Services\FoodIntroductionService;

/**
 * Recommendation Controller
 * Handles all recommendation and safety check endpoints
 */
class RecommendationController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Recommendation endpoints
        register_rest_route( 'kg/v1', '/recommendations/dashboard', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_dashboard_recommendations' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Child ID',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/recommendations/recipes', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_recipe_recommendations' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                ],
                'category' => [
                    'required' => false,
                    'type' => 'string',
                ],
                'include_scores' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/recommendations/similar/(?P<recipe_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_similar_recipes' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'recipe_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        // Safety check endpoints
        register_rest_route( 'kg/v1', '/safety/check-recipe', [
            'methods'  => 'POST',
            'callback' => [ $this, 'check_recipe_safety' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'recipe_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/safety/check-ingredient', [
            'methods'  => 'POST',
            'callback' => [ $this, 'check_ingredient_safety' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'ingredient_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/safety/batch-check', [
            'methods'  => 'POST',
            'callback' => [ $this, 'batch_safety_check' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'recipe_ids' => [
                    'required' => true,
                    'type' => 'array',
                ],
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        // Nutrition tracking endpoints
        register_rest_route( 'kg/v1', '/nutrition/weekly-summary', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_weekly_nutrition' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'week_start' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/nutrition/missing-nutrients', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_missing_nutrients' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'week_start' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/nutrition/variety-analysis', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_variety_analysis' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'days' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 7,
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/nutrition/allergen-log', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_allergen_log' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'days' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 30,
                ],
            ],
        ]);

        // Food introduction endpoints
        register_rest_route( 'kg/v1', '/food-introduction/suggested', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_suggested_foods' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/food-introduction/history', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_introduction_history' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/food-introduction/log', [
            'methods'  => 'POST',
            'callback' => [ $this, 'log_food_introduction' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'food' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'reaction' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'none',
                ],
                'notes' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '',
                ],
                'date' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/food-introduction/next-suggestion', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_next_food_suggestion' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
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
     * Validate child belongs to user
     */
    private function validate_child_access( $child_id, $user_id ) {
        $children = get_user_meta( $user_id, '_kg_children', true );
        
        if ( ! is_array( $children ) ) {
            return null;
        }
        
        foreach ( $children as $child ) {
            if ( $child['id'] === $child_id ) {
                return $child;
            }
        }
        
        return null;
    }

    /**
     * GET /kg/v1/recommendations/dashboard
     * Get dashboard recommendations for a child
     */
    public function get_dashboard_recommendations( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new RecommendationService();
        $dashboard = $service->getDashboardRecommendations( $child_id, $user_id );
        
        if ( is_wp_error( $dashboard ) ) {
            return $dashboard;
        }
        
        return new \WP_REST_Response( $dashboard, 200 );
    }

    /**
     * GET /kg/v1/recommendations/recipes
     * Get personalized recipe recommendations
     */
    public function get_recipe_recommendations( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $limit = absint( $request->get_param( 'limit' ) ) ?: 10;
        $category = sanitize_text_field( $request->get_param( 'category' ) );
        $include_scores = filter_var( $request->get_param( 'include_scores' ), FILTER_VALIDATE_BOOLEAN );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new RecommendationService();
        $recommendations = $service->getPersonalizedRecommendations( $child, [
            'limit' => $limit,
            'category' => $category,
            'include_scores' => $include_scores,
        ]);
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'recommendations' => $recommendations,
            'count' => count( $recommendations ),
        ], 200 );
    }

    /**
     * GET /kg/v1/recommendations/similar/{recipe_id}
     * Get similar safe recipes
     */
    public function get_similar_recipes( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $recipe_id = absint( $request->get_param( 'recipe_id' ) );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new RecommendationService();
        $similar = $service->getSimilarSafeRecipes( $recipe_id, $child );
        
        if ( is_wp_error( $similar ) ) {
            return $similar;
        }
        
        return new \WP_REST_Response( [
            'recipe_id' => $recipe_id,
            'similar_recipes' => $similar,
            'count' => count( $similar ),
        ], 200 );
    }

    /**
     * POST /kg/v1/safety/check-recipe
     * Check recipe safety for a child
     */
    public function check_recipe_safety( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $recipe_id = absint( $request->get_param( 'recipe_id' ) );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new SafetyCheckService();
        $result = $service->checkRecipeSafety( $recipe_id, $child );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * POST /kg/v1/safety/check-ingredient
     * Check ingredient safety for a child
     */
    public function check_ingredient_safety( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $ingredient_id = absint( $request->get_param( 'ingredient_id' ) );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new SafetyCheckService();
        $result = $service->checkIngredientSafety( $ingredient_id, $child );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return new \WP_REST_Response( $result, 200 );
    }

    /**
     * POST /kg/v1/safety/batch-check
     * Batch safety check for multiple recipes
     */
    public function batch_safety_check( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $recipe_ids = $request->get_param( 'recipe_ids' );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate recipe_ids
        if ( ! is_array( $recipe_ids ) || empty( $recipe_ids ) ) {
            return new \WP_Error( 'invalid_recipe_ids', 'recipe_ids must be a non-empty array', [ 'status' => 400 ] );
        }
        
        // Sanitize recipe IDs
        $recipe_ids = array_map( 'absint', $recipe_ids );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new SafetyCheckService();
        $results = $service->batchSafetyCheck( $recipe_ids, $child );
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'checked_count' => count( $results ),
            'results' => $results,
        ], 200 );
    }

    /**
     * GET /kg/v1/nutrition/weekly-summary
     * Get weekly nutrition summary
     */
    public function get_weekly_nutrition( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $week_start = sanitize_text_field( $request->get_param( 'week_start' ) );
        
        // Default to current week if not specified
        if ( empty( $week_start ) ) {
            $week_start = date( 'Y-m-d', strtotime( 'monday this week' ) );
        }
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new NutritionTrackerService();
        $summary = $service->getWeeklyNutritionSummary( $child_id, $user_id, $week_start );
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'week_start' => $week_start,
            'week_end' => date( 'Y-m-d', strtotime( $week_start . ' +6 days' ) ),
            'summary' => $summary,
        ], 200 );
    }

    /**
     * GET /kg/v1/nutrition/missing-nutrients
     * Get missing nutrients
     */
    public function get_missing_nutrients( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $week_start = sanitize_text_field( $request->get_param( 'week_start' ) );
        
        // Default to current week if not specified
        if ( empty( $week_start ) ) {
            $week_start = date( 'Y-m-d', strtotime( 'monday this week' ) );
        }
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new NutritionTrackerService();
        $missing = $service->getMissingNutrients( $child_id, $user_id, $week_start );
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'week_start' => $week_start,
            'missing_nutrients' => $missing,
            'count' => count( $missing ),
        ], 200 );
    }

    /**
     * GET /kg/v1/nutrition/variety-analysis
     * Get variety analysis
     */
    public function get_variety_analysis( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $days = absint( $request->get_param( 'days' ) ) ?: 7;
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new NutritionTrackerService();
        $analysis = $service->getVarietyAnalysis( $child_id, $user_id, $days );
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'analysis' => $analysis,
        ], 200 );
    }

    /**
     * GET /kg/v1/nutrition/allergen-log
     * Get allergen exposure log
     */
    public function get_allergen_log( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $days = absint( $request->get_param( 'days' ) ) ?: 30;
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new NutritionTrackerService();
        $log = $service->getAllergenExposureLog( $child_id, $user_id, $days );
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'days' => $days,
            'exposures' => $log,
            'count' => count( $log ),
        ], 200 );
    }

    /**
     * GET /kg/v1/food-introduction/suggested
     * Get suggested foods for child's age
     */
    public function get_suggested_foods( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        
        $service = new FoodIntroductionService();
        $suggestions = $service->getSuggestedFoodsForAge( $age_in_months );
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'suggestions' => $suggestions,
        ], 200 );
    }

    /**
     * GET /kg/v1/food-introduction/history
     * Get food introduction history
     */
    public function get_introduction_history( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new FoodIntroductionService();
        $history = $service->getIntroductionHistory( $child_id, $user_id );
        
        if ( is_wp_error( $history ) ) {
            return $history;
        }
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'history' => $history,
            'count' => count( $history ),
        ], 200 );
    }

    /**
     * POST /kg/v1/food-introduction/log
     * Log food introduction
     */
    public function log_food_introduction( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $food_data = [
            'food' => sanitize_text_field( $request->get_param( 'food' ) ),
            'reaction' => sanitize_text_field( $request->get_param( 'reaction' ) ),
            'notes' => sanitize_textarea_field( $request->get_param( 'notes' ) ),
            'date' => sanitize_text_field( $request->get_param( 'date' ) ),
        ];
        
        $service = new FoodIntroductionService();
        $result = $service->logFoodIntroduction( $child_id, $user_id, $food_data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return new \WP_REST_Response( $result, 201 );
    }

    /**
     * GET /kg/v1/food-introduction/next-suggestion
     * Get next food suggestion
     */
    public function get_next_food_suggestion( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        
        // Validate child access
        $child = $this->validate_child_access( $child_id, $user_id );
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found or access denied', [ 'status' => 404 ] );
        }
        
        $service = new FoodIntroductionService();
        $suggestion = $service->getNextFoodSuggestion( $child_id, $user_id );
        
        if ( is_wp_error( $suggestion ) ) {
            return $suggestion;
        }
        
        return new \WP_REST_Response( [
            'child_id' => $child_id,
            'next_suggestions' => $suggestion,
        ], 200 );
    }

    /**
     * Calculate age in months
     */
    private function calculate_age_in_months( $birth_date ) {
        $birth = new \DateTime( $birth_date );
        $now = new \DateTime();
        $interval = $birth->diff( $now );
        
        return ( $interval->y * 12 ) + $interval->m;
    }
}

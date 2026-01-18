<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Services\MealPlanGenerator;
use KG_Core\Services\ShoppingListAggregator;

/**
 * Meal Plan Controller
 * Handles weekly meal plan generation and management
 */
class MealPlanController {

    private $generator;
    private $shopping_list_aggregator;

    public function __construct() {
        $this->generator = new MealPlanGenerator();
        $this->shopping_list_aggregator = new ShoppingListAggregator();
        
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Generate meal plan
        register_rest_route( 'kg/v1', '/meal-plans/generate', [
            'methods'  => 'POST',
            'callback' => [ $this, 'generate_plan' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Child UUID',
                ],
                'week_start' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Week start date (Y-m-d)',
                ],
            ],
        ]);

        // Get active plan for child
        register_rest_route( 'kg/v1', '/meal-plans/active', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_active_plan' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Child UUID',
                ],
                'week_start' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Week start date (Y-m-d) - optional filter',
                ],
            ],
        ]);

        // Get plan by ID
        register_rest_route( 'kg/v1', '/meal-plans/(?P<id>[a-zA-Z0-9\-]+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_plan' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Update plan
        register_rest_route( 'kg/v1', '/meal-plans/(?P<id>[a-zA-Z0-9\-]+)', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_plan' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Delete plan
        register_rest_route( 'kg/v1', '/meal-plans/(?P<id>[a-zA-Z0-9\-]+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'delete_plan' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Refresh slot recipe
        register_rest_route( 'kg/v1', '/meal-plans/(?P<id>[a-zA-Z0-9\-]+)/slots/(?P<slotId>[a-zA-Z0-9\-]+)/refresh', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'refresh_slot' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        // Skip slot
        register_rest_route( 'kg/v1', '/meal-plans/(?P<id>[a-zA-Z0-9\-]+)/slots/(?P<slotId>[a-zA-Z0-9\-]+)/skip', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'skip_slot' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'skip_reason' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Reason for skipping (eating_out, ready_meal, family_meal, other)',
                ],
            ],
        ]);

        // Assign recipe to slot (manual assignment)
        register_rest_route( 'kg/v1', '/meal-plans/(?P<id>[a-zA-Z0-9\-]+)/slots/(?P<slotId>[a-zA-Z0-9\-]+)/assign', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'assign_recipe_to_slot' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'recipe_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Recipe ID to assign to the slot',
                ],
            ],
        ]);

        // Generate shopping list
        register_rest_route( 'kg/v1', '/meal-plans/(?P<id>[a-zA-Z0-9\-]+)/shopping-list', [
            'methods'  => 'POST',
            'callback' => [ $this, 'generate_shopping_list' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
    }

    /**
     * Check JWT authentication
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

        return true;
    }

    /**
     * Get authenticated user ID
     */
    private function get_authenticated_user_id( $request ) {
        $token = JWTHandler::get_token_from_request();
        return JWTHandler::get_user_id_from_token( $token );
    }

    /**
     * Generate meal plan
     */
    public function generate_plan( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $week_start = sanitize_text_field( $request->get_param( 'week_start' ) );

        // Validate date format
        $date_obj = \DateTime::createFromFormat( 'Y-m-d', $week_start );
        if ( ! $date_obj ) {
            return new \WP_Error( 'invalid_date', 'Invalid week_start date format. Use Y-m-d', [ 'status' => 400 ] );
        }

        // Get child profile
        $children = get_user_meta( $user_id, '_kg_children', true );
        if ( ! is_array( $children ) ) {
            return new \WP_Error( 'no_children', 'No children found', [ 'status' => 404 ] );
        }

        $child = null;
        foreach ( $children as $c ) {
            if ( $c['id'] === $child_id ) {
                $child = $c;
                break;
            }
        }

        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found', [ 'status' => 404 ] );
        }

        // Deactivate any existing active plans for this child
        $this->deactivate_child_plans( $user_id, $child_id );

        // Generate plan
        $plan = $this->generator->generate( $child, $week_start );

        // Save plan to user meta
        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            $plans = [];
        }
        $plans[] = $plan;
        update_user_meta( $user_id, '_kg_meal_plans', $plans );

        // Enrich response with recipe details
        $enriched_plan = $this->enrich_plan_with_recipes( $plan );

        // Calculate nutrition summary
        $nutrition_summary = $this->generator->calculate_nutrition_summary( $plan );
        $enriched_plan['nutrition_summary'] = $nutrition_summary;

        return new \WP_REST_Response( [
            'success' => true,
            'plan' => $enriched_plan,
        ], 201 );
    }

    /**
     * Get active plan for child
     */
    public function get_active_plan( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = sanitize_text_field( $request->get_param( 'child_id' ) );
        $week_start = sanitize_text_field( $request->get_param( 'week_start' ) );

        if ( empty( $child_id ) ) {
            return new \WP_Error( 'missing_child_id', 'child_id parameter is required', [ 'status' => 400 ] );
        }

        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            $plans = [];
        }

        // If week_start parameter is provided, filter by specific week
        if ( ! empty( $week_start ) ) {
            $plan = $this->get_plan_by_week( $plans, $child_id, $week_start );
            
            if ( $plan ) {
                $enriched_plan = $this->enrich_plan_with_recipes( $plan );
                $nutrition_summary = $this->generator->calculate_nutrition_summary( $plan );
                $enriched_plan['nutrition_summary'] = $nutrition_summary;

                return new \WP_REST_Response( [
                    'success' => true,
                    'plan' => $enriched_plan,
                ], 200 );
            }

            // No plan found for this week - return null instead of 404
            return new \WP_REST_Response( [
                'success' => true,
                'plan' => null,
                'message' => 'No plan found for this week',
            ], 200 );
        }

        // No week_start parameter - find active plan (existing behavior)
        foreach ( $plans as $plan ) {
            if ( $plan['child_id'] === $child_id && $plan['status'] === 'active' ) {
                $enriched_plan = $this->enrich_plan_with_recipes( $plan );
                $nutrition_summary = $this->generator->calculate_nutrition_summary( $plan );
                $enriched_plan['nutrition_summary'] = $nutrition_summary;

                return new \WP_REST_Response( [
                    'success' => true,
                    'plan' => $enriched_plan,
                ], 200 );
            }
        }

        return new \WP_Error( 'no_active_plan', 'No active plan found for this child', [ 'status' => 404 ] );
    }

    /**
     * Get plan by ID
     */
    public function get_plan( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $plan_id = $request->get_param( 'id' );

        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            return new \WP_Error( 'no_plans', 'No meal plans found', [ 'status' => 404 ] );
        }

        foreach ( $plans as $plan ) {
            if ( $plan['id'] === $plan_id ) {
                $enriched_plan = $this->enrich_plan_with_recipes( $plan );
                $nutrition_summary = $this->generator->calculate_nutrition_summary( $plan );
                $enriched_plan['nutrition_summary'] = $nutrition_summary;

                return new \WP_REST_Response( [
                    'success' => true,
                    'plan' => $enriched_plan,
                ], 200 );
            }
        }

        return new \WP_Error( 'plan_not_found', 'Meal plan not found', [ 'status' => 404 ] );
    }

    /**
     * Update plan
     */
    public function update_plan( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $plan_id = $request->get_param( 'id' );
        $status = $request->get_param( 'status' );

        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            return new \WP_Error( 'no_plans', 'No meal plans found', [ 'status' => 404 ] );
        }

        $found = false;
        foreach ( $plans as $index => $plan ) {
            if ( $plan['id'] === $plan_id ) {
                if ( $status ) {
                    $allowed_statuses = [ 'draft', 'active', 'completed' ];
                    if ( ! in_array( $status, $allowed_statuses ) ) {
                        return new \WP_Error( 'invalid_status', 'Invalid status. Must be one of: draft, active, completed', [ 'status' => 400 ] );
                    }
                    $plans[$index]['status'] = $status;
                }
                
                $plans[$index]['updated_at'] = current_time( 'c' );
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new \WP_Error( 'plan_not_found', 'Meal plan not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_meal_plans', $plans );

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Plan updated successfully',
        ], 200 );
    }

    /**
     * Delete plan
     */
    public function delete_plan( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $plan_id = $request->get_param( 'id' );

        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            return new \WP_Error( 'no_plans', 'No meal plans found', [ 'status' => 404 ] );
        }

        $found = false;
        foreach ( $plans as $index => $plan ) {
            if ( $plan['id'] === $plan_id ) {
                unset( $plans[$index] );
                $plans = array_values( $plans ); // Re-index array
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new \WP_Error( 'plan_not_found', 'Meal plan not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_meal_plans', $plans );

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Plan deleted successfully',
        ], 200 );
    }

    /**
     * Refresh slot recipe
     */
    public function refresh_slot( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $plan_id = $request->get_param( 'id' );
        $slot_id = $request->get_param( 'slotId' );

        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            return new \WP_Error( 'no_plans', 'No meal plans found', [ 'status' => 404 ] );
        }

        // Find plan and slot
        $plan_index = null;
        $day_index = null;
        $slot_index = null;
        $current_slot = null;

        foreach ( $plans as $p_idx => $plan ) {
            if ( $plan['id'] === $plan_id ) {
                foreach ( $plan['days'] as $d_idx => $day ) {
                    foreach ( $day['slots'] as $s_idx => $slot ) {
                        if ( $slot['id'] === $slot_id ) {
                            $plan_index = $p_idx;
                            $day_index = $d_idx;
                            $slot_index = $s_idx;
                            $current_slot = $slot;
                            break 3;
                        }
                    }
                }
            }
        }

        if ( $current_slot === null ) {
            return new \WP_Error( 'slot_not_found', 'Slot not found', [ 'status' => 404 ] );
        }

        // Get child to retrieve allergies and age
        $child_id = $plans[$plan_index]['child_id'];
        $children = get_user_meta( $user_id, '_kg_children', true );
        $child = null;
        foreach ( $children as $c ) {
            if ( $c['id'] === $child_id ) {
                $child = $c;
                break;
            }
        }

        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found', [ 'status' => 404 ] );
        }

        // Calculate age and get age group
        $birth_date = new \DateTime( $child['birth_date'] );
        $now = new \DateTime();
        $age_in_months = ( $now->diff( $birth_date )->y * 12 ) + $now->diff( $birth_date )->m;
        
        $age_group_slug = $this->get_age_group_for_months( $age_in_months );
        $allergies = isset( $child['allergies'] ) ? $child['allergies'] : [];

        // Get all used recipe IDs in the plan to exclude
        $used_recipe_ids = [ $current_slot['recipe_id'] ]; // Exclude current recipe

        // Get new recipe
        $new_recipe = $this->generator->refresh_slot_recipe(
            $current_slot['slot_type'],
            $age_group_slug,
            $allergies,
            $used_recipe_ids
        );

        if ( ! $new_recipe ) {
            return new \WP_Error( 'no_alternative_recipe', 'No alternative recipe found', [ 'status' => 404 ] );
        }

        // Update slot
        $plans[$plan_index]['days'][$day_index]['slots'][$slot_index]['recipe_id'] = $new_recipe->ID;
        $plans[$plan_index]['days'][$day_index]['slots'][$slot_index]['status'] = 'filled';
        $plans[$plan_index]['updated_at'] = current_time( 'c' );

        update_user_meta( $user_id, '_kg_meal_plans', $plans );

        // Return full updated plan
        $enriched_plan = $this->enrich_plan_with_recipes( $plans[$plan_index] );
        $nutrition_summary = $this->generator->calculate_nutrition_summary( $plans[$plan_index] );
        $enriched_plan['nutrition_summary'] = $nutrition_summary;

        return new \WP_REST_Response( [
            'success' => true,
            'plan' => $enriched_plan,
        ], 200 );
    }

    /**
     * Skip slot
     */
    public function skip_slot( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $plan_id = $request->get_param( 'id' );
        $slot_id = $request->get_param( 'slotId' );
        $skip_reason = sanitize_text_field( $request->get_param( 'reason' ) ); // 'reason' parameter (frontend sends this)
        
        // Fallback to 'skip_reason' for backwards compatibility
        if ( $skip_reason === null || $skip_reason === '' ) {
            $skip_reason = sanitize_text_field( $request->get_param( 'skip_reason' ) );
        }

        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            return new \WP_Error( 'no_plans', 'No meal plans found', [ 'status' => 404 ] );
        }

        // Validate skip reason
        if ( $skip_reason ) {
            $allowed_reasons = [ 'eating_out', 'ready_meal', 'family_meal', 'other' ];
            if ( ! in_array( $skip_reason, $allowed_reasons ) ) {
                return new \WP_Error( 'invalid_skip_reason', 'Invalid skip_reason. Must be one of: eating_out, ready_meal, family_meal, other', [ 'status' => 400 ] );
            }
        } else {
            $skip_reason = 'other';
        }

        // Find and update slot
        $found = false;
        $plan_index = null;
        foreach ( $plans as $p_idx => $plan ) {
            if ( $plan['id'] === $plan_id ) {
                foreach ( $plan['days'] as $d_idx => $day ) {
                    foreach ( $day['slots'] as $s_idx => $slot ) {
                        if ( $slot['id'] === $slot_id ) {
                            $plans[$p_idx]['days'][$d_idx]['slots'][$s_idx]['status'] = 'skipped';
                            $plans[$p_idx]['days'][$d_idx]['slots'][$s_idx]['skip_reason'] = $skip_reason;
                            $plans[$p_idx]['updated_at'] = current_time( 'c' );
                            $found = true;
                            $plan_index = $p_idx;
                            break 3;
                        }
                    }
                }
            }
        }

        if ( ! $found ) {
            return new \WP_Error( 'slot_not_found', 'Slot not found', [ 'status' => 404 ] );
        }

        update_user_meta( $user_id, '_kg_meal_plans', $plans );

        // Return full updated plan
        $enriched_plan = $this->enrich_plan_with_recipes( $plans[$plan_index] );
        $nutrition_summary = $this->generator->calculate_nutrition_summary( $plans[$plan_index] );
        $enriched_plan['nutrition_summary'] = $nutrition_summary;

        return new \WP_REST_Response( [
            'success' => true,
            'plan' => $enriched_plan,
        ], 200 );
    }

    /**
     * Assign a recipe to a slot (manual assignment)
     */
    public function assign_recipe_to_slot( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $plan_id = $request->get_param( 'id' );
        $slot_id = $request->get_param( 'slotId' );
        $recipe_id = absint( $request->get_param( 'recipe_id' ) );

        // Validate recipe exists
        $recipe = get_post( $recipe_id );
        if ( ! $recipe || $recipe->post_type !== 'recipe' || $recipe->post_status !== 'publish' ) {
            return new \WP_Error( 'invalid_recipe', 'Recipe not found or not published', [ 'status' => 404 ] );
        }

        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            return new \WP_Error( 'no_plans', 'No meal plans found', [ 'status' => 404 ] );
        }

        // Find plan and slot
        $plan_index = null;
        $day_index = null;
        $slot_index = null;

        foreach ( $plans as $p_idx => $plan ) {
            if ( $plan['id'] === $plan_id ) {
                foreach ( $plan['days'] as $d_idx => $day ) {
                    foreach ( $day['slots'] as $s_idx => $slot ) {
                        if ( $slot['id'] === $slot_id ) {
                            $plan_index = $p_idx;
                            $day_index = $d_idx;
                            $slot_index = $s_idx;
                            break 3;
                        }
                    }
                }
            }
        }

        if ( $plan_index === null ) {
            return new \WP_Error( 'slot_not_found', 'Plan or slot not found', [ 'status' => 404 ] );
        }

        // Update slot with the new recipe
        $plans[$plan_index]['days'][$day_index]['slots'][$slot_index]['recipe_id'] = $recipe_id;
        $plans[$plan_index]['days'][$day_index]['slots'][$slot_index]['status'] = 'filled';
        $plans[$plan_index]['days'][$day_index]['slots'][$slot_index]['skip_reason'] = null;
        $plans[$plan_index]['updated_at'] = current_time( 'c' );

        update_user_meta( $user_id, '_kg_meal_plans', $plans );

        // Return full updated plan
        $enriched_plan = $this->enrich_plan_with_recipes( $plans[$plan_index] );
        $nutrition_summary = $this->generator->calculate_nutrition_summary( $plans[$plan_index] );
        $enriched_plan['nutrition_summary'] = $nutrition_summary;

        return new \WP_REST_Response( [
            'success' => true,
            'plan' => $enriched_plan,
        ], 200 );
    }

    /**
     * Generate shopping list
     */
    public function generate_shopping_list( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $plan_id = $request->get_param( 'id' );

        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            return new \WP_Error( 'no_plans', 'No meal plans found', [ 'status' => 404 ] );
        }

        $plan = null;
        foreach ( $plans as $p ) {
            if ( $p['id'] === $plan_id ) {
                $plan = $p;
                break;
            }
        }

        if ( ! $plan ) {
            return new \WP_Error( 'plan_not_found', 'Meal plan not found', [ 'status' => 404 ] );
        }

        // Generate shopping list
        $shopping_list = $this->shopping_list_aggregator->generate( $plan );

        // Ensure response format matches frontend expectation
        return new \WP_REST_Response( [
            'success' => true,
            'items' => $shopping_list['items'] ?? [],
            'total_count' => $shopping_list['total_count'] ?? 0,
        ], 200 );
    }

    /**
     * Deactivate all active plans for a child
     */
    private function deactivate_child_plans( $user_id, $child_id ) {
        $plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        if ( ! is_array( $plans ) ) {
            return;
        }

        foreach ( $plans as $index => $plan ) {
            if ( $plan['child_id'] === $child_id && $plan['status'] === 'active' ) {
                $plans[$index]['status'] = 'completed';
                $plans[$index]['updated_at'] = current_time( 'c' );
            }
        }

        update_user_meta( $user_id, '_kg_meal_plans', $plans );
    }

    /**
     * Get plan by week_start date
     *
     * @param array $plans Array of meal plans
     * @param string $child_id Child UUID
     * @param string $week_start Week start date (Y-m-d)
     * @return array|null Plan data or null if not found
     */
    private function get_plan_by_week( $plans, $child_id, $week_start ) {
        foreach ( $plans as $plan ) {
            if ( $plan['child_id'] === $child_id && $plan['week_start'] === $week_start ) {
                return $plan;
            }
        }
        return null;
    }

    /**
     * Enrich plan with recipe details
     */
    private function enrich_plan_with_recipes( $plan ) {
        foreach ( $plan['days'] as $day_index => $day ) {
            foreach ( $day['slots'] as $slot_index => $slot ) {
                if ( $slot['status'] === 'filled' && $slot['recipe_id'] ) {
                    $plan['days'][$day_index]['slots'][$slot_index]['recipe'] = $this->get_recipe_details( $slot['recipe_id'] );
                }
            }
        }
        return $plan;
    }

    /**
     * Get recipe details
     */
    private function get_recipe_details( $recipe_id ) {
        $recipe = get_post( $recipe_id );
        
        if ( ! $recipe ) {
            return null;
        }

        $image_id = get_post_thumbnail_id( $recipe_id );
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : null;

        // Get prep time
        $prep_time = get_post_meta( $recipe_id, '_kg_prep_time', true );
        
        // Get age groups
        $age_groups = wp_get_post_terms( $recipe_id, 'age-group', [ 'fields' => 'names' ] );
        $age_group = ! empty( $age_groups ) && ! is_wp_error( $age_groups ) ? $age_groups[0] : '';

        // Get allergens
        $allergens = wp_get_post_terms( $recipe_id, 'allergen', [ 'fields' => 'slugs' ] );
        if ( is_wp_error( $allergens ) ) {
            $allergens = [];
        }

        return [
            'id' => $recipe_id,
            'title' => get_the_title( $recipe_id ),
            'slug' => $recipe->post_name,
            'image' => $image_url,
            'prep_time' => $prep_time ?: '',
            'age_group' => $age_group,
            'allergens' => $allergens,
        ];
    }

    /**
     * Get age group slug for months - delegates to generator
     */
    private function get_age_group_for_months( $age_in_months ) {
        // Use reflection to access private method from generator
        // Alternative: make this method public in MealPlanGenerator
        $reflection = new \ReflectionMethod( $this->generator, 'get_age_group_for_months' );
        $reflection->setAccessible( true );
        return $reflection->invoke( $this->generator, $age_in_months );
    }
}

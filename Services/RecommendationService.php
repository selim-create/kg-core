<?php
namespace KG_Core\Services;

/**
 * Recommendation Service
 * Generates personalized recipe recommendations based on child profile
 */
class RecommendationService {

    /**
     * Get personalized recipe recommendations for a child
     * 
     * @param array $child Child profile (id, birth_date, allergies, feeding_style)
     * @param array $options Optional parameters (limit, exclude_ids, category, etc.)
     * @return array Scored and sorted recipe recommendations
     */
    public function getPersonalizedRecommendations( $child, $options = [] ) {
        $defaults = [
            'limit' => 10,
            'exclude_ids' => [],
            'category' => null,
            'include_scores' => false,
        ];
        
        $options = array_merge( $defaults, $options );
        
        // Calculate child's age in months
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        
        // Get age group
        $age_group = $this->get_age_group_for_months( $age_in_months );
        
        // Get candidate recipes
        $candidates = $this->get_candidate_recipes( $age_group, $child, $options );
        
        // Score each recipe
        $scored_recipes = [];
        foreach ( $candidates as $recipe ) {
            $score = $this->calculate_recipe_score( $recipe, $child, $age_in_months );
            $scored_recipes[] = [
                'recipe' => $recipe,
                'total_score' => $score['total'],
                'scores' => $score,
            ];
        }
        
        // Sort by total score (descending)
        usort( $scored_recipes, function( $a, $b ) {
            return $b['total_score'] - $a['total_score'];
        });
        
        // Limit results
        $scored_recipes = array_slice( $scored_recipes, 0, $options['limit'] );
        
        // Format output
        $recommendations = [];
        foreach ( $scored_recipes as $item ) {
            $rec = [
                'recipe_id' => $item['recipe']->ID,
                'title' => $item['recipe']->post_title,
                'slug' => $item['recipe']->post_name,
                'image' => get_the_post_thumbnail_url( $item['recipe']->ID, 'medium' ),
                'score' => round( $item['total_score'], 2 ),
            ];
            
            if ( $options['include_scores'] ) {
                $rec['detailed_scores'] = $item['scores'];
            }
            
            $recommendations[] = $rec;
        }
        
        return $recommendations;
    }
    
    /**
     * Get dashboard recommendations for a child
     * 
     * @param string $child_id Child ID
     * @param int $user_id User ID
     * @return array Dashboard recommendation data
     */
    public function getDashboardRecommendations( $child_id, $user_id ) {
        // Get child profile
        $children = get_user_meta( $user_id, '_kg_children', true );
        $child = null;
        
        if ( is_array( $children ) ) {
            foreach ( $children as $c ) {
                if ( $c['id'] === $child_id ) {
                    $child = $c;
                    break;
                }
            }
        }
        
        if ( ! $child ) {
            return new \WP_Error( 'child_not_found', 'Child not found', [ 'status' => 404 ] );
        }
        
        // Get today's recommendations
        $today_recommendations = $this->getPersonalizedRecommendations( $child, [
            'limit' => 6,
            'include_scores' => false,
        ]);
        
        // Get weekly meal plan status
        $meal_plan_status = $this->get_weekly_meal_plan_status( $child_id, $user_id );
        
        // Get nutrition summary
        $nutrition_tracker = new NutritionTrackerService();
        $week_start = date( 'Y-m-d', strtotime( 'monday this week' ) );
        $nutrition_summary = $nutrition_tracker->getWeeklyNutritionSummary( $child_id, $user_id, $week_start );
        
        // Get safety alerts
        $alerts = $this->get_pending_alerts( $child, $user_id );
        
        return [
            'today' => $today_recommendations,
            'weekly_plan_status' => $meal_plan_status,
            'nutrition_summary' => $nutrition_summary,
            'alerts' => $alerts,
        ];
    }
    
    /**
     * Get similar safe recipes based on a reference recipe
     * 
     * @param int $recipe_id Reference recipe ID
     * @param array $child Child profile
     * @return array Similar safe recipe recommendations
     */
    public function getSimilarSafeRecipes( $recipe_id, $child ) {
        $recipe = get_post( $recipe_id );
        
        if ( ! $recipe || $recipe->post_type !== 'recipe' ) {
            return new \WP_Error( 'invalid_recipe', 'Invalid recipe ID', [ 'status' => 404 ] );
        }
        
        // Get recipe's taxonomies
        $meal_types = wp_get_post_terms( $recipe_id, 'meal-type', [ 'fields' => 'ids' ] );
        $categories = wp_get_post_terms( $recipe_id, 'recipe-category', [ 'fields' => 'ids' ] );
        
        // Calculate child's age in months
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        $age_group = $this->get_age_group_for_months( $age_in_months );
        
        // Query similar recipes
        $args = [
            'post_type' => 'recipe',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'post__not_in' => [ $recipe_id ],
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'age-group',
                    'field' => 'slug',
                    'terms' => $age_group,
                ],
            ],
        ];
        
        // Exclude allergens
        $allergies = isset( $child['allergies'] ) ? $child['allergies'] : [];
        if ( ! empty( $allergies ) ) {
            $args['tax_query'][] = [
                'taxonomy' => 'allergen',
                'field' => 'slug',
                'terms' => $allergies,
                'operator' => 'NOT IN',
            ];
        }
        
        // Add meal type similarity
        if ( ! empty( $meal_types ) ) {
            $args['tax_query'][] = [
                'taxonomy' => 'meal-type',
                'field' => 'term_id',
                'terms' => $meal_types,
            ];
        }
        
        $query = new \WP_Query( $args );
        $candidates = $query->posts;
        
        // Score by similarity
        $scored = [];
        foreach ( $candidates as $candidate ) {
            $similarity_score = $this->calculate_similarity_score( $recipe_id, $candidate->ID );
            $scored[] = [
                'recipe' => $candidate,
                'similarity' => $similarity_score,
            ];
        }
        
        // Sort by similarity
        usort( $scored, function( $a, $b ) {
            return $b['similarity'] - $a['similarity'];
        });
        
        // Return top 10
        $scored = array_slice( $scored, 0, 10 );
        
        $results = [];
        foreach ( $scored as $item ) {
            $results[] = [
                'recipe_id' => $item['recipe']->ID,
                'title' => $item['recipe']->post_title,
                'slug' => $item['recipe']->post_name,
                'image' => get_the_post_thumbnail_url( $item['recipe']->ID, 'medium' ),
                'similarity_score' => round( $item['similarity'], 2 ),
            ];
        }
        
        return $results;
    }
    
    /**
     * Calculate comprehensive score for a recipe
     * 
     * @param \WP_Post $recipe Recipe post
     * @param array $child Child profile
     * @param int $age_in_months Child's age in months
     * @return array Score breakdown
     */
    private function calculate_recipe_score( $recipe, $child, $age_in_months ) {
        $scores = [
            'age_compatibility' => $this->score_age_compatibility( $recipe, $age_in_months ),
            'allergen_safety' => $this->score_allergen_safety( $recipe, $child ),
            'nutritional_balance' => $this->score_nutritional_balance( $recipe, $child ),
            'feeding_style_match' => $this->score_feeding_style( $recipe, $child ),
            'seasonal_relevance' => $this->score_seasonal_relevance( $recipe ),
            'variety_bonus' => $this->score_variety_bonus( $recipe, $child ),
            'user_preferences' => $this->score_user_preferences( $recipe, $child ),
        ];
        
        // Weighted total (allergen safety is critical)
        $weights = [
            'age_compatibility' => 0.20,
            'allergen_safety' => 0.30, // Critical
            'nutritional_balance' => 0.15,
            'feeding_style_match' => 0.15,
            'seasonal_relevance' => 0.05,
            'variety_bonus' => 0.10,
            'user_preferences' => 0.05,
        ];
        
        $total = 0;
        foreach ( $scores as $key => $score ) {
            $total += $score * $weights[ $key ];
        }
        
        $scores['total'] = $total;
        
        return $scores;
    }
    
    /**
     * Score age compatibility (0-100)
     */
    private function score_age_compatibility( $recipe, $age_in_months ) {
        $age_groups = wp_get_post_terms( $recipe->ID, 'age-group', [ 'fields' => 'slugs' ] );
        
        if ( empty( $age_groups ) || is_wp_error( $age_groups ) ) {
            return 50; // Neutral score if no age group
        }
        
        $current_age_group = $this->get_age_group_for_months( $age_in_months );
        
        // Perfect match
        if ( in_array( $current_age_group, $age_groups ) ) {
            return 100;
        }
        
        // Check adjacent age groups
        $age_group_order = [
            '0-6-ay-sadece-sut',
            '6-8-ay-baslangic',
            '9-11-ay-kesif',
            '12-24-ay-gecis',
            '2-yas-ve-uzeri',
        ];
        
        $current_index = array_search( $current_age_group, $age_group_order );
        
        foreach ( $age_groups as $group ) {
            $group_index = array_search( $group, $age_group_order );
            if ( $group_index !== false ) {
                $distance = abs( $current_index - $group_index );
                if ( $distance === 1 ) {
                    return 70; // Adjacent group
                } elseif ( $distance === 2 ) {
                    return 40; // Two groups away
                }
            }
        }
        
        return 20; // Too far from current age
    }
    
    /**
     * Score allergen safety (0-100, 0 if contains allergen)
     */
    private function score_allergen_safety( $recipe, $child ) {
        $allergies = isset( $child['allergies'] ) ? $child['allergies'] : [];
        
        if ( empty( $allergies ) ) {
            return 100; // No allergies, perfect safety
        }
        
        $recipe_allergens = wp_get_post_terms( $recipe->ID, 'allergen', [ 'fields' => 'slugs' ] );
        
        if ( empty( $recipe_allergens ) || is_wp_error( $recipe_allergens ) ) {
            return 100; // No allergens in recipe
        }
        
        // Check for any matching allergen
        $has_allergen = array_intersect( $allergies, $recipe_allergens );
        
        if ( ! empty( $has_allergen ) ) {
            return 0; // CRITICAL: Contains allergen
        }
        
        return 100; // Safe
    }
    
    /**
     * Score nutritional balance (0-100)
     */
    private function score_nutritional_balance( $recipe, $child ) {
        // Get nutrition categories from recipe
        $nutrition_cats = wp_get_post_terms( $recipe->ID, 'nutrition-category', [ 'fields' => 'slugs' ] );
        
        if ( empty( $nutrition_cats ) || is_wp_error( $nutrition_cats ) ) {
            return 50; // Neutral if no data
        }
        
        // Get child's recent nutrition (from user meta or tracking service)
        $recent_nutrition = $this->get_recent_nutrition_categories( $child );
        
        // Calculate diversity score
        $score = 50; // Base score
        
        foreach ( $nutrition_cats as $cat ) {
            // Bonus for underrepresented categories
            if ( ! isset( $recent_nutrition[ $cat ] ) || $recent_nutrition[ $cat ] < 3 ) {
                $score += 10;
            }
        }
        
        return min( 100, $score );
    }
    
    /**
     * Score feeding style match (0-100)
     */
    private function score_feeding_style( $recipe, $child ) {
        $feeding_style = isset( $child['feeding_style'] ) ? $child['feeding_style'] : 'mixed';
        
        $recipe_textures = wp_get_post_terms( $recipe->ID, 'texture', [ 'fields' => 'slugs' ] );
        
        if ( empty( $recipe_textures ) || is_wp_error( $recipe_textures ) ) {
            return 70; // Neutral
        }
        
        $texture_map = [
            'blw' => [ 'finger-food', 'soft-chunks', 'whole' ],
            'puree' => [ 'puree', 'smooth', 'mashed' ],
            'mixed' => [ 'puree', 'mashed', 'soft-chunks', 'finger-food' ],
        ];
        
        $preferred_textures = isset( $texture_map[ $feeding_style ] ) ? $texture_map[ $feeding_style ] : [];
        
        $matches = array_intersect( $preferred_textures, $recipe_textures );
        
        if ( ! empty( $matches ) ) {
            return 100; // Perfect match
        }
        
        return 50; // No match
    }
    
    /**
     * Score seasonal relevance (0-100)
     */
    private function score_seasonal_relevance( $recipe ) {
        $current_month = (int) date( 'n' );
        
        // Determine season
        $season = '';
        if ( in_array( $current_month, [ 12, 1, 2 ] ) ) {
            $season = 'kis'; // Winter
        } elseif ( in_array( $current_month, [ 3, 4, 5 ] ) ) {
            $season = 'ilkbahar'; // Spring
        } elseif ( in_array( $current_month, [ 6, 7, 8 ] ) ) {
            $season = 'yaz'; // Summer
        } else {
            $season = 'sonbahar'; // Fall
        }
        
        $recipe_seasons = wp_get_post_terms( $recipe->ID, 'season', [ 'fields' => 'slugs' ] );
        
        if ( empty( $recipe_seasons ) || is_wp_error( $recipe_seasons ) ) {
            return 70; // Neutral if no seasonal data
        }
        
        if ( in_array( $season, $recipe_seasons ) ) {
            return 100; // Perfect seasonal match
        }
        
        if ( in_array( 'all-season', $recipe_seasons ) || in_array( 'tum-mevsim', $recipe_seasons ) ) {
            return 90; // All-season recipe
        }
        
        return 40; // Out of season
    }
    
    /**
     * Score variety bonus (0-100)
     */
    private function score_variety_bonus( $recipe, $child ) {
        // Get recipes consumed in last 7 days
        $recent_recipes = $this->get_recent_recipes( $child, 7 );
        
        if ( empty( $recent_recipes ) ) {
            return 100; // No history, full bonus
        }
        
        // Check if this recipe was recently consumed
        if ( in_array( $recipe->ID, $recent_recipes ) ) {
            return 0; // No bonus for repeated recipe
        }
        
        // Get ingredients from this recipe
        $recipe_ingredients = $this->get_recipe_ingredients( $recipe->ID );
        
        // Get recently consumed ingredients
        $recent_ingredients = $this->get_recent_ingredients( $child, 7 );
        
        // Calculate new ingredient ratio
        $new_ingredients = array_diff( $recipe_ingredients, $recent_ingredients );
        $new_ratio = count( $recipe_ingredients ) > 0 
            ? count( $new_ingredients ) / count( $recipe_ingredients ) 
            : 0;
        
        return round( $new_ratio * 100 );
    }
    
    /**
     * Score user preferences (0-100)
     */
    private function score_user_preferences( $recipe, $child ) {
        // Get user's favorite categories
        $user_id = $this->get_user_id_from_child( $child );
        $favorite_categories = get_user_meta( $user_id, '_kg_favorite_categories', true );
        
        if ( empty( $favorite_categories ) || ! is_array( $favorite_categories ) ) {
            return 70; // Neutral if no preferences
        }
        
        $recipe_categories = wp_get_post_terms( $recipe->ID, 'recipe-category', [ 'fields' => 'ids' ] );
        
        if ( empty( $recipe_categories ) || is_wp_error( $recipe_categories ) ) {
            return 70; // Neutral
        }
        
        $matches = array_intersect( $favorite_categories, $recipe_categories );
        
        if ( ! empty( $matches ) ) {
            return 100; // Matches user preferences
        }
        
        return 50; // No match
    }
    
    /**
     * Get candidate recipes for scoring
     */
    private function get_candidate_recipes( $age_group, $child, $options ) {
        $args = [
            'post_type' => 'recipe',
            'post_status' => 'publish',
            'posts_per_page' => 50, // Get more candidates for better scoring
            'orderby' => 'rand',
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'age-group',
                    'field' => 'slug',
                    'terms' => $age_group,
                ],
            ],
        ];
        
        // Exclude specific recipes
        if ( ! empty( $options['exclude_ids'] ) ) {
            $args['post__not_in'] = $options['exclude_ids'];
        }
        
        // Filter by category if specified
        if ( ! empty( $options['category'] ) ) {
            $args['tax_query'][] = [
                'taxonomy' => 'recipe-category',
                'field' => 'slug',
                'terms' => $options['category'],
            ];
        }
        
        // CRITICAL: Exclude allergens
        $allergies = isset( $child['allergies'] ) ? $child['allergies'] : [];
        if ( ! empty( $allergies ) ) {
            $args['tax_query'][] = [
                'taxonomy' => 'allergen',
                'field' => 'slug',
                'terms' => $allergies,
                'operator' => 'NOT IN',
            ];
        }
        
        $query = new \WP_Query( $args );
        
        return $query->posts;
    }
    
    /**
     * Calculate age in months from birth date
     */
    private function calculate_age_in_months( $birth_date ) {
        $birth = new \DateTime( $birth_date );
        $now = new \DateTime();
        $interval = $birth->diff( $now );
        
        return ( $interval->y * 12 ) + $interval->m;
    }
    
    /**
     * Get age group slug for age in months
     */
    private function get_age_group_for_months( $age_in_months ) {
        if ( $age_in_months < 6 ) {
            return '0-6-ay-sadece-sut';
        } elseif ( $age_in_months >= 6 && $age_in_months <= 8 ) {
            return '6-8-ay-baslangic';
        } elseif ( $age_in_months >= 9 && $age_in_months <= 11 ) {
            return '9-11-ay-kesif';
        } elseif ( $age_in_months >= 12 && $age_in_months <= 24 ) {
            return '12-24-ay-gecis';
        } else {
            return '2-yas-ve-uzeri';
        }
    }
    
    /**
     * Calculate similarity score between two recipes
     */
    private function calculate_similarity_score( $recipe_id_1, $recipe_id_2 ) {
        $score = 0;
        
        // Compare taxonomies
        $taxonomies = [ 'meal-type', 'recipe-category', 'cooking-method' ];
        
        foreach ( $taxonomies as $taxonomy ) {
            $terms_1 = wp_get_post_terms( $recipe_id_1, $taxonomy, [ 'fields' => 'ids' ] );
            $terms_2 = wp_get_post_terms( $recipe_id_2, $taxonomy, [ 'fields' => 'ids' ] );
            
            if ( ! empty( $terms_1 ) && ! empty( $terms_2 ) ) {
                $matches = array_intersect( $terms_1, $terms_2 );
                $score += count( $matches ) * 20;
            }
        }
        
        // Compare ingredients
        $ingredients_1 = $this->get_recipe_ingredients( $recipe_id_1 );
        $ingredients_2 = $this->get_recipe_ingredients( $recipe_id_2 );
        
        if ( ! empty( $ingredients_1 ) && ! empty( $ingredients_2 ) ) {
            $matches = array_intersect( $ingredients_1, $ingredients_2 );
            $total = count( array_unique( array_merge( $ingredients_1, $ingredients_2 ) ) );
            if ( $total > 0 ) {
                $score += ( count( $matches ) / $total ) * 40;
            }
        }
        
        return min( 100, $score );
    }
    
    /**
     * Get recipe ingredients (ACF field)
     */
    private function get_recipe_ingredients( $recipe_id ) {
        if ( ! function_exists( 'get_field' ) ) {
            return [];
        }
        
        $ingredients = get_field( 'ingredients', $recipe_id );
        $ingredient_ids = [];
        
        if ( is_array( $ingredients ) ) {
            foreach ( $ingredients as $ing ) {
                if ( isset( $ing['ingredient'] ) && is_object( $ing['ingredient'] ) ) {
                    $ingredient_ids[] = $ing['ingredient']->ID;
                } elseif ( isset( $ing['ingredient'] ) && is_numeric( $ing['ingredient'] ) ) {
                    $ingredient_ids[] = (int) $ing['ingredient'];
                }
            }
        }
        
        return $ingredient_ids;
    }
    
    /**
     * Get recent nutrition categories consumed
     */
    private function get_recent_nutrition_categories( $child ) {
        // This would ideally query meal plan history or nutrition tracker
        // For now, return empty array
        return [];
    }
    
    /**
     * Get recipes consumed in last N days
     */
    private function get_recent_recipes( $child, $days = 7 ) {
        $user_id = $this->get_user_id_from_child( $child );
        $meal_plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        
        if ( ! is_array( $meal_plans ) ) {
            return [];
        }
        
        $cutoff_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        $recent_recipes = [];
        
        foreach ( $meal_plans as $plan ) {
            if ( isset( $plan['child_id'] ) && $plan['child_id'] === $child['id'] ) {
                if ( isset( $plan['week_start'] ) && $plan['week_start'] >= $cutoff_date ) {
                    if ( isset( $plan['days'] ) && is_array( $plan['days'] ) ) {
                        foreach ( $plan['days'] as $day ) {
                            if ( isset( $day['slots'] ) && is_array( $day['slots'] ) ) {
                                foreach ( $day['slots'] as $slot ) {
                                    if ( isset( $slot['recipe_id'] ) && $slot['recipe_id'] ) {
                                        $recent_recipes[] = $slot['recipe_id'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return array_unique( $recent_recipes );
    }
    
    /**
     * Get recently consumed ingredients
     */
    private function get_recent_ingredients( $child, $days = 7 ) {
        $recent_recipes = $this->get_recent_recipes( $child, $days );
        $ingredients = [];
        
        foreach ( $recent_recipes as $recipe_id ) {
            $recipe_ingredients = $this->get_recipe_ingredients( $recipe_id );
            $ingredients = array_merge( $ingredients, $recipe_ingredients );
        }
        
        return array_unique( $ingredients );
    }
    
    /**
     * Get user ID from child profile
     */
    private function get_user_id_from_child( $child ) {
        // Query users to find which one has this child
        global $wpdb;
        
        $child_id = $child['id'];
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = '_kg_children' 
            AND meta_value LIKE %s",
            '%' . $wpdb->esc_like( $child_id ) . '%'
        ) );
        
        if ( ! empty( $results ) ) {
            return $results[0]->user_id;
        }
        
        return 0;
    }
    
    /**
     * Get weekly meal plan status
     */
    private function get_weekly_meal_plan_status( $child_id, $user_id ) {
        $meal_plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        
        if ( ! is_array( $meal_plans ) ) {
            return [
                'has_plan' => false,
                'completion' => 0,
            ];
        }
        
        $week_start = date( 'Y-m-d', strtotime( 'monday this week' ) );
        
        foreach ( $meal_plans as $plan ) {
            if ( isset( $plan['child_id'] ) && $plan['child_id'] === $child_id && 
                 isset( $plan['week_start'] ) && $plan['week_start'] === $week_start ) {
                
                $total_slots = 0;
                $filled_slots = 0;
                
                if ( isset( $plan['days'] ) && is_array( $plan['days'] ) ) {
                    foreach ( $plan['days'] as $day ) {
                        if ( isset( $day['slots'] ) && is_array( $day['slots'] ) ) {
                            foreach ( $day['slots'] as $slot ) {
                                $total_slots++;
                                if ( isset( $slot['status'] ) && $slot['status'] === 'filled' ) {
                                    $filled_slots++;
                                }
                            }
                        }
                    }
                }
                
                return [
                    'has_plan' => true,
                    'completion' => $total_slots > 0 ? round( ( $filled_slots / $total_slots ) * 100 ) : 0,
                    'filled_slots' => $filled_slots,
                    'total_slots' => $total_slots,
                ];
            }
        }
        
        return [
            'has_plan' => false,
            'completion' => 0,
        ];
    }
    
    /**
     * Get pending safety alerts for child
     */
    private function get_pending_alerts( $child, $user_id ) {
        $alerts = [];
        
        // Check for new allergens introduced this week
        $introduced_foods = isset( $child['introduced_foods'] ) ? $child['introduced_foods'] : [];
        $recent_recipes = $this->get_recent_recipes( $child, 7 );
        
        // Check for age-inappropriate recipes
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        if ( $age_in_months < 6 ) {
            $alerts[] = [
                'type' => 'age',
                'severity' => 'info',
                'message' => '0-6 ay arası bebekler için sadece anne sütü veya formula önerilir.',
            ];
        }
        
        return $alerts;
    }
}

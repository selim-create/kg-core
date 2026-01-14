<?php
namespace KG_Core\Services;

/**
 * Meal Plan Generator Service
 * Generates weekly meal plans based on child's age, allergies, and preferences
 */
class MealPlanGenerator {

    /**
     * Slot types configuration
     */
    const SLOT_TYPES = [
        'breakfast' => [
            'label' => 'Kahvaltı',
            'meal_type_slug' => 'kahvalti',
            'color' => '#FFF9C4',
            'time_range' => '07:00-09:00'
        ],
        'snack_morning' => [
            'label' => 'Ara Öğün (Kuşluk)',
            'meal_type_slug' => 'ara-ogun-kusluk',
            'color' => '#E8F5E9',
            'time_range' => '10:00-11:00'
        ],
        'lunch' => [
            'label' => 'Öğle Yemeği',
            'meal_type_slug' => 'ogle-yemegi',
            'color' => '#DCEDC8',
            'time_range' => '12:00-13:00'
        ],
        'snack_afternoon' => [
            'label' => 'Ara Öğün (İkindi)',
            'meal_type_slug' => 'ara-ogun-ikindi',
            'color' => '#F3E5F5',
            'time_range' => '15:00-16:00'
        ],
        'dinner' => [
            'label' => 'Akşam Yemeği',
            'meal_type_slug' => 'aksam-yemegi',
            'color' => '#FFCC80',
            'time_range' => '18:00-19:00'
        ],
    ];

    /**
     * Age group mapping
     */
    const AGE_GROUP_MAPPING = [
        '0-6' => '0-6-ay-sadece-sut',
        '6-8' => '6-8-ay-baslangic',
        '9-11' => '9-11-ay-gecis',
        '12-18' => '12-18-ay-pekistirme',
        '19-36' => '19-36-ay-cesitlendirme',
        '36+' => '3-yas-usti',
    ];

    /**
     * Turkish day names
     */
    const DAY_NAMES = [
        'Pazartesi',
        'Salı',
        'Çarşamba',
        'Perşembe',
        'Cuma',
        'Cumartesi',
        'Pazar'
    ];

    /**
     * Generate meal plan
     *
     * @param array $child Child profile data
     * @param string $week_start Week start date (Y-m-d format)
     * @return array Generated meal plan
     */
    public function generate( $child, $week_start ) {
        // 1. Profile Analysis
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        $allergies = isset( $child['allergies'] ) ? $child['allergies'] : [];
        
        // 2. Determine slot strategy based on age
        $slot_types = $this->get_slot_types_for_age( $age_in_months );
        
        // 3. Get suitable age group
        $age_group = $this->get_age_group_for_months( $age_in_months );
        
        // 4. Generate plan structure
        $plan = [
            'id' => wp_generate_uuid4(),
            'child_id' => $child['id'],
            'week_start' => $week_start,
            'week_end' => date( 'Y-m-d', strtotime( $week_start . ' +6 days' ) ),
            'status' => 'active',
            'days' => [],
            'created_at' => current_time( 'c' ),
            'updated_at' => current_time( 'c' ),
        ];
        
        // 5. Generate days
        for ( $i = 0; $i < 7; $i++ ) {
            $day_date = date( 'Y-m-d', strtotime( $week_start . ' +' . $i . ' days' ) );
            $day_name = self::DAY_NAMES[$i];
            
            $day = [
                'date' => $day_date,
                'day_name' => $day_name,
                'slots' => [],
            ];
            
            // 6. Generate slots for this day
            foreach ( $slot_types as $slot_type ) {
                $slot_config = self::SLOT_TYPES[$slot_type];
                
                // Get a recipe for this slot
                $recipe = $this->get_recipe_for_slot(
                    $slot_config['meal_type_slug'],
                    $age_group,
                    $allergies,
                    $plan['days'] // Previously generated days for variety
                );
                
                $slot = [
                    'id' => wp_generate_uuid4(),
                    'slot_type' => $slot_type,
                    'slot_label' => $slot_config['label'],
                    'status' => $recipe ? 'filled' : 'empty',
                    'recipe_id' => $recipe ? $recipe->ID : null,
                    'skip_reason' => null,
                    'time_range' => $slot_config['time_range'],
                    'color_code' => $slot_config['color'],
                ];
                
                $day['slots'][] = $slot;
            }
            
            $plan['days'][] = $day;
        }
        
        return $plan;
    }

    /**
     * Calculate age in months from birth date
     *
     * @param string $birth_date Birth date (Y-m-d format)
     * @return int Age in months
     */
    private function calculate_age_in_months( $birth_date ) {
        $birth = new \DateTime( $birth_date );
        $now = new \DateTime();
        $interval = $birth->diff( $now );
        
        return ( $interval->y * 12 ) + $interval->m;
    }

    /**
     * Get slot types based on age in months
     *
     * @param int $age_in_months Age in months
     * @return array Slot types
     */
    private function get_slot_types_for_age( $age_in_months ) {
        if ( $age_in_months >= 6 && $age_in_months <= 8 ) {
            // 6-8 months: 2 meals (breakfast + dinner)
            return [ 'breakfast', 'dinner' ];
        } elseif ( $age_in_months >= 9 && $age_in_months <= 11 ) {
            // 9-11 months: 3 meals (breakfast + lunch + dinner)
            return [ 'breakfast', 'lunch', 'dinner' ];
        } else {
            // 12+ months: 5 meals (3 main + 2 snacks)
            return [ 'breakfast', 'snack_morning', 'lunch', 'snack_afternoon', 'dinner' ];
        }
    }

    /**
     * Get age group taxonomy slug for months
     *
     * @param int $age_in_months Age in months
     * @return string Age group slug
     */
    private function get_age_group_for_months( $age_in_months ) {
        // Map months to age group slugs
        if ( $age_in_months >= 0 && $age_in_months <= 6 ) {
            return '0-6-ay-sadece-sut';
        } elseif ( $age_in_months >= 6 && $age_in_months <= 8 ) {
            return '6-8-ay-baslangic';
        } elseif ( $age_in_months >= 9 && $age_in_months <= 11 ) {
            return '9-11-ay-gecis';
        } elseif ( $age_in_months >= 12 && $age_in_months <= 18 ) {
            return '12-18-ay-pekistirme';
        } elseif ( $age_in_months >= 19 && $age_in_months <= 36 ) {
            return '19-36-ay-cesitlendirme';
        } else {
            return '3-yas-ustu';
        }
    }

    /**
     * Get a recipe for a specific slot
     *
     * @param string $meal_type_slug Meal type slug
     * @param string $age_group Age group slug
     * @param array $allergies Allergens to exclude
     * @param array $previous_days Previously generated days for variety
     * @return \WP_Post|null Recipe post or null
     */
    private function get_recipe_for_slot( $meal_type_slug, $age_group, $allergies, $previous_days ) {
        // Get recently used recipe IDs for variety
        $used_recipe_ids = $this->get_used_recipe_ids( $previous_days );
        
        // Build query args
        $args = [
            'post_type' => 'recipe',
            'post_status' => 'publish',
            'posts_per_page' => 20, // Get multiple to choose from
            'orderby' => 'rand',
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'age-group',
                    'field' => 'slug',
                    'terms' => $age_group,
                ],
                [
                    'taxonomy' => 'meal-type',
                    'field' => 'slug',
                    'terms' => $meal_type_slug,
                ],
            ],
        ];
        
        // Exclude allergens if specified
        if ( ! empty( $allergies ) ) {
            $args['tax_query'][] = [
                'taxonomy' => 'allergen',
                'field' => 'slug',
                'terms' => $allergies,
                'operator' => 'NOT IN',
            ];
        }
        
        // Exclude recently used recipes
        if ( ! empty( $used_recipe_ids ) ) {
            $args['post__not_in'] = $used_recipe_ids;
        }
        
        $query = new \WP_Query( $args );
        
        if ( $query->have_posts() ) {
            return $query->posts[0];
        }
        
        // If no recipes found, try without variety constraint
        if ( ! empty( $used_recipe_ids ) ) {
            unset( $args['post__not_in'] );
            $query = new \WP_Query( $args );
            if ( $query->have_posts() ) {
                return $query->posts[0];
            }
        }
        
        return null;
    }

    /**
     * Get used recipe IDs from previous days
     *
     * @param array $previous_days Previously generated days
     * @return array Recipe IDs
     */
    private function get_used_recipe_ids( $previous_days ) {
        $recipe_ids = [];
        
        foreach ( $previous_days as $day ) {
            foreach ( $day['slots'] as $slot ) {
                if ( $slot['recipe_id'] ) {
                    $recipe_ids[] = $slot['recipe_id'];
                }
            }
        }
        
        return array_unique( $recipe_ids );
    }

    /**
     * Refresh a slot with a new recipe
     *
     * @param string $slot_type Slot type
     * @param string $age_group Age group slug
     * @param array $allergies Allergens to exclude
     * @param array $excluded_recipe_ids Recipe IDs to exclude
     * @return \WP_Post|null Recipe post or null
     */
    public function refresh_slot_recipe( $slot_type, $age_group, $allergies, $excluded_recipe_ids = [] ) {
        $slot_config = self::SLOT_TYPES[$slot_type];
        
        $args = [
            'post_type' => 'recipe',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'rand',
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'age-group',
                    'field' => 'slug',
                    'terms' => $age_group,
                ],
                [
                    'taxonomy' => 'meal-type',
                    'field' => 'slug',
                    'terms' => $slot_config['meal_type_slug'],
                ],
            ],
        ];
        
        // Exclude allergens
        if ( ! empty( $allergies ) ) {
            $args['tax_query'][] = [
                'taxonomy' => 'allergen',
                'field' => 'slug',
                'terms' => $allergies,
                'operator' => 'NOT IN',
            ];
        }
        
        // Exclude specific recipes
        if ( ! empty( $excluded_recipe_ids ) ) {
            $args['post__not_in'] = $excluded_recipe_ids;
        }
        
        $query = new \WP_Query( $args );
        
        if ( $query->have_posts() ) {
            return $query->posts[0];
        }
        
        return null;
    }

    /**
     * Calculate nutrition summary for a plan
     *
     * @param array $plan Meal plan
     * @return array Nutrition summary
     */
    public function calculate_nutrition_summary( $plan ) {
        $total_meals = 0;
        $allergens_introduced = [];
        
        foreach ( $plan['days'] as $day ) {
            foreach ( $day['slots'] as $slot ) {
                if ( $slot['status'] === 'filled' && $slot['recipe_id'] ) {
                    $total_meals++;
                    
                    // Get recipe allergens
                    $recipe_allergens = wp_get_post_terms( $slot['recipe_id'], 'allergen', [ 'fields' => 'slugs' ] );
                    if ( ! is_wp_error( $recipe_allergens ) ) {
                        $allergens_introduced = array_merge( $allergens_introduced, $recipe_allergens );
                    }
                }
            }
        }
        
        return [
            'total_meals' => $total_meals,
            'vegetables_servings' => 0, // Can be calculated from ingredients
            'protein_servings' => 0,
            'grains_servings' => 0,
            'new_allergens_introduced' => array_unique( $allergens_introduced ),
        ];
    }
}

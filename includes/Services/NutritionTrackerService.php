<?php
namespace KG_Core\Services;

/**
 * Nutrition Tracker Service
 * Tracks and analyzes nutritional intake for children
 */
class NutritionTrackerService {

    /**
     * Get weekly nutrition summary for a child
     * 
     * @param string $child_id Child ID
     * @param int $user_id User ID
     * @param string $week_start Week start date (Y-m-d)
     * @return array Weekly nutrition summary
     */
    public function getWeeklyNutritionSummary( $child_id, $user_id, $week_start ) {
        // Get meal plans for this child and week
        $meal_plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        
        if ( ! is_array( $meal_plans ) ) {
            return $this->get_empty_summary();
        }
        
        $week_end = date( 'Y-m-d', strtotime( $week_start . ' +6 days' ) );
        
        // Find the relevant meal plan
        $current_plan = null;
        foreach ( $meal_plans as $plan ) {
            if ( isset( $plan['child_id'] ) && $plan['child_id'] === $child_id &&
                 isset( $plan['week_start'] ) && $plan['week_start'] === $week_start ) {
                $current_plan = $plan;
                break;
            }
        }
        
        if ( ! $current_plan ) {
            return $this->get_empty_summary();
        }
        
        // Initialize counters
        $nutrition_data = [
            'protein_servings' => 0,
            'vegetable_servings' => 0,
            'fruit_servings' => 0,
            'grains_servings' => 0,
            'dairy_servings' => 0,
            'iron_rich_count' => 0,
            'new_foods_introduced' => [],
            'allergen_exposures' => [],
            'variety_score' => 0,
        ];
        
        $all_ingredients = [];
        $unique_recipes = [];
        
        // Analyze each day in the plan
        if ( isset( $current_plan['days'] ) && is_array( $current_plan['days'] ) ) {
            foreach ( $current_plan['days'] as $day ) {
                if ( isset( $day['slots'] ) && is_array( $day['slots'] ) ) {
                    foreach ( $day['slots'] as $slot ) {
                        if ( isset( $slot['recipe_id'] ) && $slot['recipe_id'] && $slot['status'] === 'filled' ) {
                            $recipe_id = $slot['recipe_id'];
                            $unique_recipes[] = $recipe_id;
                            
                            // Analyze recipe nutrition
                            $recipe_nutrition = $this->analyze_recipe_nutrition( $recipe_id );
                            
                            // Aggregate servings
                            $nutrition_data['protein_servings'] += $recipe_nutrition['protein_servings'];
                            $nutrition_data['vegetable_servings'] += $recipe_nutrition['vegetable_servings'];
                            $nutrition_data['fruit_servings'] += $recipe_nutrition['fruit_servings'];
                            $nutrition_data['grains_servings'] += $recipe_nutrition['grains_servings'];
                            $nutrition_data['dairy_servings'] += $recipe_nutrition['dairy_servings'];
                            $nutrition_data['iron_rich_count'] += $recipe_nutrition['iron_rich'];
                            
                            // Track ingredients
                            $all_ingredients = array_merge( $all_ingredients, $recipe_nutrition['ingredients'] );
                            
                            // Track allergen exposures
                            $allergens = wp_get_post_terms( $recipe_id, 'allergen', [ 'fields' => 'names' ] );
                            if ( ! empty( $allergens ) && ! is_wp_error( $allergens ) ) {
                                foreach ( $allergens as $allergen ) {
                                    if ( ! in_array( $allergen, $nutrition_data['allergen_exposures'] ) ) {
                                        $nutrition_data['allergen_exposures'][] = $allergen;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Calculate variety score
        $unique_ingredients = array_unique( $all_ingredients );
        $unique_recipes = array_unique( $unique_recipes );
        
        // Variety score based on unique ingredients and recipes
        $ingredient_variety = min( 100, count( $unique_ingredients ) * 3 );
        $recipe_variety = min( 100, count( $unique_recipes ) * 5 );
        $nutrition_data['variety_score'] = round( ( $ingredient_variety + $recipe_variety ) / 2 );
        
        // Get new foods introduced
        $nutrition_data['new_foods_introduced'] = $this->get_new_foods_this_week( 
            $child_id, 
            $user_id, 
            $unique_ingredients 
        );
        
        return $nutrition_data;
    }
    
    /**
     * Get missing nutrients for a child
     * 
     * @param string $child_id Child ID
     * @param int $user_id User ID
     * @param string $week_start Week start date
     * @return array Missing nutrients and recommendations
     */
    public function getMissingNutrients( $child_id, $user_id, $week_start ) {
        $summary = $this->getWeeklyNutritionSummary( $child_id, $user_id, $week_start );
        
        // Get child's age for age-appropriate recommendations
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
            return [];
        }
        
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        
        // Define recommended servings based on age
        $recommendations = $this->get_recommended_servings( $age_in_months );
        
        $missing = [];
        
        // Check each nutrient category
        if ( $summary['protein_servings'] < $recommendations['protein'] ) {
            $missing[] = [
                'nutrient' => 'Protein',
                'current' => $summary['protein_servings'],
                'recommended' => $recommendations['protein'],
                'deficit' => $recommendations['protein'] - $summary['protein_servings'],
                'sources' => [ 'Tavuk', 'Balık', 'Yumurta', 'Kırmızı et', 'Baklagiller' ],
                'severity' => $this->calculate_deficit_severity( 
                    $summary['protein_servings'], 
                    $recommendations['protein'] 
                ),
            ];
        }
        
        if ( $summary['vegetable_servings'] < $recommendations['vegetables'] ) {
            $missing[] = [
                'nutrient' => 'Sebze',
                'current' => $summary['vegetable_servings'],
                'recommended' => $recommendations['vegetables'],
                'deficit' => $recommendations['vegetables'] - $summary['vegetable_servings'],
                'sources' => [ 'Brokoli', 'Havuç', 'Kabak', 'Ispanak', 'Bezelye' ],
                'severity' => $this->calculate_deficit_severity( 
                    $summary['vegetable_servings'], 
                    $recommendations['vegetables'] 
                ),
            ];
        }
        
        if ( $summary['fruit_servings'] < $recommendations['fruits'] ) {
            $missing[] = [
                'nutrient' => 'Meyve',
                'current' => $summary['fruit_servings'],
                'recommended' => $recommendations['fruits'],
                'deficit' => $recommendations['fruits'] - $summary['fruit_servings'],
                'sources' => [ 'Elma', 'Muz', 'Armut', 'Çilek', 'Portakal' ],
                'severity' => $this->calculate_deficit_severity( 
                    $summary['fruit_servings'], 
                    $recommendations['fruits'] 
                ),
            ];
        }
        
        if ( $summary['grains_servings'] < $recommendations['grains'] ) {
            $missing[] = [
                'nutrient' => 'Tahıllar',
                'current' => $summary['grains_servings'],
                'recommended' => $recommendations['grains'],
                'deficit' => $recommendations['grains'] - $summary['grains_servings'],
                'sources' => [ 'Pirinç', 'Bulgur', 'Makarna', 'Ekmek', 'Yulaf' ],
                'severity' => $this->calculate_deficit_severity( 
                    $summary['grains_servings'], 
                    $recommendations['grains'] 
                ),
            ];
        }
        
        if ( $summary['dairy_servings'] < $recommendations['dairy'] ) {
            $missing[] = [
                'nutrient' => 'Süt Ürünleri',
                'current' => $summary['dairy_servings'],
                'recommended' => $recommendations['dairy'],
                'deficit' => $recommendations['dairy'] - $summary['dairy_servings'],
                'sources' => [ 'Yoğurt', 'Peynir', 'Süt' ],
                'severity' => $this->calculate_deficit_severity( 
                    $summary['dairy_servings'], 
                    $recommendations['dairy'] 
                ),
            ];
        }
        
        if ( $summary['iron_rich_count'] < $recommendations['iron_rich'] ) {
            $missing[] = [
                'nutrient' => 'Demir',
                'current' => $summary['iron_rich_count'],
                'recommended' => $recommendations['iron_rich'],
                'deficit' => $recommendations['iron_rich'] - $summary['iron_rich_count'],
                'sources' => [ 'Kırmızı et', 'Ispanak', 'Mercimek', 'Nohut', 'Bezelye' ],
                'severity' => $this->calculate_deficit_severity( 
                    $summary['iron_rich_count'], 
                    $recommendations['iron_rich'] 
                ),
            ];
        }
        
        return $missing;
    }
    
    /**
     * Get variety analysis for child's diet
     * 
     * @param string $child_id Child ID
     * @param int $user_id User ID
     * @param int $days Number of days to analyze
     * @return array Variety analysis
     */
    public function getVarietyAnalysis( $child_id, $user_id, $days = 7 ) {
        // Get recent recipes
        $recent_recipes = $this->get_recent_recipes_for_days( $child_id, $user_id, $days );
        
        if ( empty( $recent_recipes ) ) {
            return [
                'variety_score' => 0,
                'unique_recipes' => 0,
                'unique_ingredients' => 0,
                'repeated_recipes' => [],
                'recommendation' => 'Henüz yemek geçmişi yok. Çeşitli tarifler denemeye başlayın.',
            ];
        }
        
        // Count occurrences
        $recipe_counts = array_count_values( $recent_recipes );
        $unique_recipes = count( $recipe_counts );
        
        // Get repeated recipes (>2 times)
        $repeated = [];
        foreach ( $recipe_counts as $recipe_id => $count ) {
            if ( $count > 2 ) {
                $recipe = get_post( $recipe_id );
                if ( $recipe ) {
                    $repeated[] = [
                        'recipe_id' => $recipe_id,
                        'title' => $recipe->post_title,
                        'count' => $count,
                    ];
                }
            }
        }
        
        // Get unique ingredients
        $all_ingredients = [];
        foreach ( array_unique( $recent_recipes ) as $recipe_id ) {
            $ingredients = $this->get_recipe_ingredients( $recipe_id );
            $all_ingredients = array_merge( $all_ingredients, $ingredients );
        }
        $unique_ingredients = count( array_unique( $all_ingredients ) );
        
        // Calculate variety score
        $recipe_variety = min( 100, ( $unique_recipes / $days ) * 100 );
        $ingredient_variety = min( 100, $unique_ingredients * 3 );
        $repetition_penalty = count( $repeated ) * 10;
        
        $variety_score = max( 0, round( ( $recipe_variety + $ingredient_variety ) / 2 - $repetition_penalty ) );
        
        // Generate recommendation
        $recommendation = $this->generate_variety_recommendation( $variety_score, $repeated );
        
        return [
            'variety_score' => $variety_score,
            'unique_recipes' => $unique_recipes,
            'unique_ingredients' => $unique_ingredients,
            'repeated_recipes' => $repeated,
            'days_analyzed' => $days,
            'recommendation' => $recommendation,
        ];
    }
    
    /**
     * Get allergen exposure log
     * 
     * @param string $child_id Child ID
     * @param int $user_id User ID
     * @param int $days Number of days to look back
     * @return array Allergen exposure history
     */
    public function getAllergenExposureLog( $child_id, $user_id, $days = 30 ) {
        $cutoff_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        
        // Get meal plans
        $meal_plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        
        if ( ! is_array( $meal_plans ) ) {
            return [];
        }
        
        $exposures = [];
        
        foreach ( $meal_plans as $plan ) {
            if ( isset( $plan['child_id'] ) && $plan['child_id'] === $child_id &&
                 isset( $plan['week_start'] ) && $plan['week_start'] >= $cutoff_date ) {
                
                if ( isset( $plan['days'] ) && is_array( $plan['days'] ) ) {
                    foreach ( $plan['days'] as $day ) {
                        $day_date = isset( $day['date'] ) ? $day['date'] : '';
                        
                        if ( isset( $day['slots'] ) && is_array( $day['slots'] ) ) {
                            foreach ( $day['slots'] as $slot ) {
                                if ( isset( $slot['recipe_id'] ) && $slot['recipe_id'] ) {
                                    $recipe_id = $slot['recipe_id'];
                                    $allergens = wp_get_post_terms( $recipe_id, 'allergen', [ 'fields' => 'all' ] );
                                    
                                    if ( ! empty( $allergens ) && ! is_wp_error( $allergens ) ) {
                                        foreach ( $allergens as $allergen ) {
                                            $exposures[] = [
                                                'date' => $day_date,
                                                'allergen' => $allergen->name,
                                                'recipe_id' => $recipe_id,
                                                'recipe_title' => get_the_title( $recipe_id ),
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Sort by date (newest first)
        usort( $exposures, function( $a, $b ) {
            return strtotime( $b['date'] ) - strtotime( $a['date'] );
        });
        
        return $exposures;
    }
    
    /**
     * Analyze single recipe nutrition
     */
    private function analyze_recipe_nutrition( $recipe_id ) {
        $nutrition = [
            'protein_servings' => 0,
            'vegetable_servings' => 0,
            'fruit_servings' => 0,
            'grains_servings' => 0,
            'dairy_servings' => 0,
            'iron_rich' => 0,
            'ingredients' => [],
        ];
        
        $ingredients = $this->get_recipe_ingredients( $recipe_id );
        
        foreach ( $ingredients as $ingredient_id ) {
            $nutrition['ingredients'][] = $ingredient_id;
            
            // Check ingredient categories using correct taxonomy
            $categories = wp_get_post_terms( $ingredient_id, 'ingredient-category', [ 'fields' => 'slugs' ] );
            
            if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
                foreach ( $categories as $cat_slug ) {
                    switch ( $cat_slug ) {
                        case 'proteinler':
                        case 'baklagiller': // Legumes are also protein sources and iron-rich
                            $nutrition['protein_servings']++;
                            $nutrition['iron_rich']++;
                            break;
                        case 'sebzeler':
                            $nutrition['vegetable_servings']++;
                            break;
                        case 'meyveler':
                            $nutrition['fruit_servings']++;
                            break;
                        case 'tahillar':
                            $nutrition['grains_servings']++;
                            break;
                        case 'sut-urunleri':
                            $nutrition['dairy_servings']++;
                            break;
                    }
                }
            }
        }
        
        return $nutrition;
    }
    
    /**
     * Get recipe ingredients
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
     * Get new foods introduced this week
     */
    private function get_new_foods_this_week( $child_id, $user_id, $week_ingredients ) {
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
            return [];
        }
        
        $introduced_foods = isset( $child['introduced_foods'] ) ? $child['introduced_foods'] : [];
        
        $new_foods = [];
        foreach ( $week_ingredients as $ingredient_id ) {
            $ingredient = get_post( $ingredient_id );
            if ( $ingredient ) {
                $is_new = ! in_array( $ingredient->post_name, $introduced_foods ) &&
                         ! in_array( $ingredient->post_title, $introduced_foods );
                
                if ( $is_new ) {
                    $new_foods[] = $ingredient->post_title;
                }
            }
        }
        
        return array_unique( $new_foods );
    }
    
    /**
     * Get recent recipes for N days
     */
    private function get_recent_recipes_for_days( $child_id, $user_id, $days ) {
        $cutoff_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        $meal_plans = get_user_meta( $user_id, '_kg_meal_plans', true );
        
        if ( ! is_array( $meal_plans ) ) {
            return [];
        }
        
        $recipes = [];
        
        foreach ( $meal_plans as $plan ) {
            if ( isset( $plan['child_id'] ) && $plan['child_id'] === $child_id &&
                 isset( $plan['week_start'] ) && $plan['week_start'] >= $cutoff_date ) {
                
                if ( isset( $plan['days'] ) && is_array( $plan['days'] ) ) {
                    foreach ( $plan['days'] as $day ) {
                        if ( isset( $day['slots'] ) && is_array( $day['slots'] ) ) {
                            foreach ( $day['slots'] as $slot ) {
                                if ( isset( $slot['recipe_id'] ) && $slot['recipe_id'] ) {
                                    $recipes[] = $slot['recipe_id'];
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $recipes;
    }
    
    /**
     * Get recommended servings by age
     */
    private function get_recommended_servings( $age_in_months ) {
        if ( $age_in_months < 6 ) {
            return [
                'protein' => 0,
                'vegetables' => 0,
                'fruits' => 0,
                'grains' => 0,
                'dairy' => 0,
                'iron_rich' => 0,
            ];
        } elseif ( $age_in_months >= 6 && $age_in_months <= 8 ) {
            return [
                'protein' => 7,
                'vegetables' => 7,
                'fruits' => 7,
                'grains' => 7,
                'dairy' => 7,
                'iron_rich' => 3,
            ];
        } elseif ( $age_in_months >= 9 && $age_in_months <= 11 ) {
            return [
                'protein' => 10,
                'vegetables' => 10,
                'fruits' => 10,
                'grains' => 10,
                'dairy' => 10,
                'iron_rich' => 5,
            ];
        } elseif ( $age_in_months >= 12 && $age_in_months <= 24 ) {
            return [
                'protein' => 14,
                'vegetables' => 14,
                'fruits' => 14,
                'grains' => 14,
                'dairy' => 14,
                'iron_rich' => 7,
            ];
        } else {
            return [
                'protein' => 21,
                'vegetables' => 21,
                'fruits' => 21,
                'grains' => 21,
                'dairy' => 21,
                'iron_rich' => 10,
            ];
        }
    }
    
    /**
     * Calculate deficit severity
     */
    private function calculate_deficit_severity( $current, $recommended ) {
        if ( $recommended === 0 ) {
            return 'none';
        }
        
        $ratio = $current / $recommended;
        
        if ( $ratio >= 0.8 ) {
            return 'low';
        } elseif ( $ratio >= 0.5 ) {
            return 'medium';
        } else {
            return 'high';
        }
    }
    
    /**
     * Generate variety recommendation
     */
    private function generate_variety_recommendation( $score, $repeated ) {
        if ( $score >= 80 ) {
            return 'Harika! Çocuğunuz çok çeşitli besinlerle besleniyor.';
        } elseif ( $score >= 60 ) {
            return 'İyi gidiyorsunuz, ancak biraz daha çeşitlilik eklenebilir.';
        } elseif ( $score >= 40 ) {
            return 'Daha fazla çeşitlilik eklemeye çalışın. Farklı tarifler deneyin.';
        } else {
            $message = 'Çeşitlilik düşük! Yeni tarifler ve malzemeler denemeye başlayın.';
            if ( ! empty( $repeated ) ) {
                $message .= ' Bazı tarifleri çok sık tekrarlıyorsunuz.';
            }
            return $message;
        }
    }
    
    /**
     * Get empty summary structure
     */
    private function get_empty_summary() {
        return [
            'protein_servings' => 0,
            'vegetable_servings' => 0,
            'fruit_servings' => 0,
            'grains_servings' => 0,
            'dairy_servings' => 0,
            'iron_rich_count' => 0,
            'new_foods_introduced' => [],
            'allergen_exposures' => [],
            'variety_score' => 0,
        ];
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

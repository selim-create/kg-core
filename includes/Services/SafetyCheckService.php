<?php
namespace KG_Core\Services;

/**
 * Safety Check Service
 * Performs safety checks for recipes and ingredients based on child profile
 */
class SafetyCheckService {

    /**
     * Check recipe safety for a specific child
     * 
     * @param int $recipe_id Recipe ID
     * @param array $child Child profile
     * @return array Safety check result with alerts and suggestions
     */
    public function checkRecipeSafety( $recipe_id, $child ) {
        $recipe = get_post( $recipe_id );
        
        if ( ! $recipe || $recipe->post_type !== 'recipe' ) {
            return new \WP_Error( 'invalid_recipe', 'Invalid recipe ID', [ 'status' => 404 ] );
        }
        
        $alerts = [];
        $is_safe = true;
        
        // 1. Allergy Check (CRITICAL)
        $allergy_alerts = $this->check_allergens( $recipe_id, $child );
        if ( ! empty( $allergy_alerts ) ) {
            $alerts = array_merge( $alerts, $allergy_alerts );
            $is_safe = false;
        }
        
        // 2. Age Appropriateness Check
        $age_alerts = $this->check_age_appropriateness( $recipe_id, $child );
        if ( ! empty( $age_alerts ) ) {
            $alerts = array_merge( $alerts, $age_alerts );
        }
        
        // 3. Forbidden Ingredients Check (e.g., honey for <12 months)
        $forbidden_alerts = $this->check_forbidden_ingredients( $recipe_id, $child );
        if ( ! empty( $forbidden_alerts ) ) {
            $alerts = array_merge( $alerts, $forbidden_alerts );
            if ( $this->has_critical_alerts( $forbidden_alerts ) ) {
                $is_safe = false;
            }
        }
        
        // 4. Nutritional Concerns
        $nutrition_alerts = $this->check_nutrition_concerns( $recipe_id, $child );
        if ( ! empty( $nutrition_alerts ) ) {
            $alerts = array_merge( $alerts, $nutrition_alerts );
        }
        
        // 5. Get alternative suggestions if unsafe
        $alternatives = [];
        if ( ! $is_safe ) {
            $alternatives = $this->get_safe_alternatives( $recipe_id, $child );
        }
        
        return [
            'recipe_id' => $recipe_id,
            'is_safe' => $is_safe,
            'safety_score' => $this->calculate_safety_score( $alerts ),
            'alerts' => $alerts,
            'alternatives' => $alternatives,
            'checked_at' => current_time( 'c' ),
        ];
    }
    
    /**
     * Check ingredient safety for a specific child
     * 
     * @param int $ingredient_id Ingredient ID
     * @param array $child Child profile
     * @return array Safety check result
     */
    public function checkIngredientSafety( $ingredient_id, $child ) {
        $ingredient = get_post( $ingredient_id );
        
        if ( ! $ingredient || $ingredient->post_type !== 'ingredient' ) {
            return new \WP_Error( 'invalid_ingredient', 'Invalid ingredient ID', [ 'status' => 404 ] );
        }
        
        $alerts = [];
        $is_safe = true;
        
        // 1. Check if ingredient is an allergen for this child
        $allergen_alert = $this->check_ingredient_allergen( $ingredient_id, $child );
        if ( $allergen_alert ) {
            $alerts[] = $allergen_alert;
            $is_safe = false;
        }
        
        // 2. Check age appropriateness
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        $start_age = get_post_meta( $ingredient_id, '_kg_start_age', true );
        
        if ( $start_age ) {
            $start_age_months = (int) $start_age;
            if ( $age_in_months < $start_age_months ) {
                $alerts[] = [
                    'type' => 'age',
                    'severity' => 'warning',
                    'message' => sprintf( 
                        '%s için önerilen minimum yaş %d ay. Çocuğunuz %d aylık.',
                        $ingredient->post_title,
                        $start_age_months,
                        $age_in_months
                    ),
                    'ingredient' => $ingredient->post_title,
                ];
            }
        }
        
        // 3. Check if introduced before
        $introduced_foods = isset( $child['introduced_foods'] ) ? $child['introduced_foods'] : [];
        $is_introduced = in_array( $ingredient->post_name, $introduced_foods ) || 
                        in_array( $ingredient->post_title, $introduced_foods );
        
        if ( ! $is_introduced ) {
            $allergy_risk = get_post_meta( $ingredient_id, '_kg_allergy_risk', true );
            $severity = ( $allergy_risk === 'Yüksek' ) ? 'warning' : 'info';
            
            $alerts[] = [
                'type' => 'nutrition',
                'severity' => $severity,
                'message' => sprintf( 
                    '%s daha önce denenmemiş. İlk kez vereceğiniz için dikkatli olun. Alerji riski: %s',
                    $ingredient->post_title,
                    $allergy_risk ?: 'Düşük'
                ),
                'ingredient' => $ingredient->post_title,
                'alternative' => $allergy_risk === 'Yüksek' ? 'İlk kez verirken az miktarda deneyin ve 3 gün bekleyin.' : null,
            ];
        }
        
        return [
            'ingredient_id' => $ingredient_id,
            'ingredient_name' => $ingredient->post_title,
            'is_safe' => $is_safe,
            'is_introduced' => $is_introduced,
            'alerts' => $alerts,
            'allergy_risk' => get_post_meta( $ingredient_id, '_kg_allergy_risk', true ) ?: 'Düşük',
            'start_age' => $start_age ?: 6,
        ];
    }
    
    /**
     * Batch safety check for multiple recipes
     * 
     * @param array $recipe_ids Array of recipe IDs
     * @param array $child Child profile
     * @return array Batch safety check results
     */
    public function batchSafetyCheck( $recipe_ids, $child ) {
        $results = [];
        
        foreach ( $recipe_ids as $recipe_id ) {
            $check = $this->checkRecipeSafety( $recipe_id, $child );
            
            // Only include summary for batch
            $results[] = [
                'recipe_id' => $recipe_id,
                'is_safe' => $check['is_safe'],
                'safety_score' => $check['safety_score'],
                'critical_alerts' => $this->filter_critical_alerts( $check['alerts'] ),
            ];
        }
        
        return $results;
    }
    
    /**
     * Check for allergens in recipe
     */
    private function check_allergens( $recipe_id, $child ) {
        $alerts = [];
        $allergies = isset( $child['allergies'] ) ? $child['allergies'] : [];
        
        if ( empty( $allergies ) ) {
            return $alerts; // No allergies to check
        }
        
        // Get recipe allergens
        $recipe_allergens = wp_get_post_terms( $recipe_id, 'allergen', [ 'fields' => 'all' ] );
        
        if ( empty( $recipe_allergens ) || is_wp_error( $recipe_allergens ) ) {
            return $alerts; // No allergens in recipe
        }
        
        foreach ( $recipe_allergens as $allergen ) {
            if ( in_array( $allergen->slug, $allergies ) || in_array( $allergen->name, $allergies ) ) {
                $alerts[] = [
                    'type' => 'allergy',
                    'severity' => 'critical',
                    'message' => sprintf(
                        'KESİNLİKLE VERMEYİN! Bu tarif %s içeriyor. Çocuğunuzun bu alerjeni var.',
                        $allergen->name
                    ),
                    'ingredient' => $allergen->name,
                    'alternative' => sprintf( '%s içermeyen benzer tarifler aramayı deneyin.', $allergen->name ),
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Check age appropriateness
     */
    private function check_age_appropriateness( $recipe_id, $child ) {
        $alerts = [];
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        
        // Get recipe age groups
        $age_groups = wp_get_post_terms( $recipe_id, 'age-group', [ 'fields' => 'all' ] );
        
        if ( empty( $age_groups ) || is_wp_error( $age_groups ) ) {
            return $alerts; // No age group specified
        }
        
        $current_age_group = $this->get_age_group_for_months( $age_in_months );
        
        $age_group_slugs = array_map( function( $term ) {
            return $term->slug;
        }, $age_groups );
        
        // Check if current age group is in recipe's age groups
        if ( ! in_array( $current_age_group, $age_group_slugs ) ) {
            $severity = 'warning';
            $message = sprintf(
                'Bu tarif %s yaş grubu için önerilmiş. Çocuğunuz %d aylık.',
                $age_groups[0]->name,
                $age_in_months
            );
            
            // More serious if recipe is for older children
            if ( $this->is_age_group_older( $age_groups[0]->slug, $current_age_group ) ) {
                $message .= ' Bu tarif çocuğunuz için erken olabilir.';
            }
            
            $alerts[] = [
                'type' => 'age',
                'severity' => $severity,
                'message' => $message,
                'alternative' => 'Yaş grubunuza uygun tariflere göz atın.',
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Check for forbidden ingredients
     */
    private function check_forbidden_ingredients( $recipe_id, $child ) {
        $alerts = [];
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        
        // Get recipe ingredients
        $ingredients = $this->get_recipe_ingredients( $recipe_id );
        
        foreach ( $ingredients as $ingredient_id ) {
            $ingredient = get_post( $ingredient_id );
            if ( ! $ingredient ) {
                continue;
            }
            
            // Check for honey (forbidden <12 months)
            if ( stripos( $ingredient->post_title, 'bal' ) !== false || 
                 stripos( $ingredient->post_name, 'honey' ) !== false ) {
                if ( $age_in_months < 12 ) {
                    $alerts[] = [
                        'type' => 'forbidden',
                        'severity' => 'critical',
                        'message' => 'KESİNLİKLE VERMEYİN! Bal 12 aydan önce botulizm riski taşır.',
                        'ingredient' => 'Bal',
                        'alternative' => 'Bal yerine muz veya elma püresi kullanabilirsiniz.',
                    ];
                }
            }
            
            // Check for nuts/choking hazards (whole nuts forbidden <4 years)
            $nut_terms = [ 'fıstık', 'fındık', 'ceviz', 'badem', 'nut' ];
            foreach ( $nut_terms as $nut ) {
                if ( stripos( $ingredient->post_title, $nut ) !== false ) {
                    if ( $age_in_months < 48 ) {
                        // Check if it's powder/butter form
                        $is_powder = stripos( $ingredient->post_title, 'tozu' ) !== false ||
                                    stripos( $ingredient->post_title, 'ezmesi' ) !== false ||
                                    stripos( $ingredient->post_title, 'butter' ) !== false;
                        
                        if ( ! $is_powder ) {
                            $alerts[] = [
                                'type' => 'forbidden',
                                'severity' => 'critical',
                                'message' => 'TAM FİNDIK/CEVİZ VERMEYİN! Boğulma riski. Sadece toz veya ezme formu verin.',
                                'ingredient' => $ingredient->post_title,
                                'alternative' => 'Fındık tozu veya fındık ezmesi kullanın.',
                            ];
                        }
                    }
                }
            }
            
            // Check for cow's milk (not recommended <12 months as main drink)
            if ( ( stripos( $ingredient->post_title, 'inek sütü' ) !== false || 
                   stripos( $ingredient->post_title, 'cow milk' ) !== false ) && 
                 $age_in_months < 12 ) {
                $alerts[] = [
                    'type' => 'forbidden',
                    'severity' => 'warning',
                    'message' => 'İnek sütü 12 aydan önce ana içecek olarak önerilmez. Yemeklerde az miktarda kullanılabilir.',
                    'ingredient' => 'İnek Sütü',
                    'alternative' => 'Anne sütü veya devam formülü tercih edin.',
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Check nutrition concerns
     */
    private function check_nutrition_concerns( $recipe_id, $child ) {
        $alerts = [];
        
        // Check for excessive salt (for babies <1 year)
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        
        if ( $age_in_months < 12 ) {
            $ingredients = $this->get_recipe_ingredients( $recipe_id );
            foreach ( $ingredients as $ingredient_id ) {
                $ingredient = get_post( $ingredient_id );
                if ( ! $ingredient ) {
                    continue;
                }
                
                if ( stripos( $ingredient->post_title, 'tuz' ) !== false || 
                     stripos( $ingredient->post_title, 'salt' ) !== false ) {
                    $alerts[] = [
                        'type' => 'nutrition',
                        'severity' => 'warning',
                        'message' => '12 aydan küçük bebeklere tuz eklenmemeli. Böbrekler henüz yeterince gelişmemiştir.',
                        'ingredient' => 'Tuz',
                        'alternative' => 'Tuz eklemeden pişirin, doğal tatlar yeterlidir.',
                    ];
                }
                
                if ( stripos( $ingredient->post_title, 'şeker' ) !== false || 
                     stripos( $ingredient->post_title, 'sugar' ) !== false ) {
                    $alerts[] = [
                        'type' => 'nutrition',
                        'severity' => 'info',
                        'message' => 'Eklenen şeker küçük çocuklar için önerilmez. Meyvelerin doğal şekeri tercih edilmelidir.',
                        'ingredient' => 'Şeker',
                        'alternative' => 'Muz, hurma veya elma kullanarak tatlandırın.',
                    ];
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get safe alternative recipes
     */
    private function get_safe_alternatives( $recipe_id, $child ) {
        // Use RecommendationService to get safe alternatives
        $recommendation_service = new RecommendationService();
        
        $similar_recipes = $recommendation_service->getSimilarSafeRecipes( $recipe_id, $child );
        
        // Return up to 3 alternatives
        return array_slice( $similar_recipes, 0, 3 );
    }
    
    /**
     * Calculate overall safety score (0-100)
     */
    private function calculate_safety_score( $alerts ) {
        if ( empty( $alerts ) ) {
            return 100; // Perfect safety
        }
        
        $score = 100;
        
        foreach ( $alerts as $alert ) {
            $severity = $alert['severity'];
            
            if ( $severity === 'critical' ) {
                return 0; // Critical alert = unsafe
            } elseif ( $severity === 'warning' ) {
                $score -= 30;
            } elseif ( $severity === 'info' ) {
                $score -= 10;
            }
        }
        
        return max( 0, $score );
    }
    
    /**
     * Check if alerts contain critical ones
     */
    private function has_critical_alerts( $alerts ) {
        foreach ( $alerts as $alert ) {
            if ( isset( $alert['severity'] ) && $alert['severity'] === 'critical' ) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Filter only critical alerts
     */
    private function filter_critical_alerts( $alerts ) {
        return array_filter( $alerts, function( $alert ) {
            return isset( $alert['severity'] ) && $alert['severity'] === 'critical';
        });
    }
    
    /**
     * Check if ingredient is allergen for child
     */
    private function check_ingredient_allergen( $ingredient_id, $child ) {
        $allergies = isset( $child['allergies'] ) ? $child['allergies'] : [];
        
        if ( empty( $allergies ) ) {
            return null;
        }
        
        // Get ingredient allergen taxonomies
        $allergens = wp_get_post_terms( $ingredient_id, 'allergen', [ 'fields' => 'all' ] );
        
        if ( empty( $allergens ) || is_wp_error( $allergens ) ) {
            return null;
        }
        
        foreach ( $allergens as $allergen ) {
            if ( in_array( $allergen->slug, $allergies ) || in_array( $allergen->name, $allergies ) ) {
                return [
                    'type' => 'allergy',
                    'severity' => 'critical',
                    'message' => sprintf(
                        'Bu malzeme %s içeriyor. Çocuğunuzun bu alerjeni var.',
                        $allergen->name
                    ),
                    'ingredient' => get_post( $ingredient_id )->post_title,
                ];
            }
        }
        
        return null;
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
     * Calculate age in months
     */
    private function calculate_age_in_months( $birth_date ) {
        $birth = new \DateTime( $birth_date );
        $now = new \DateTime();
        $interval = $birth->diff( $now );
        
        return ( $interval->y * 12 ) + $interval->m;
    }
    
    /**
     * Get age group for age in months
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
     * Check if first age group is older than second
     */
    private function is_age_group_older( $age_group_1, $age_group_2 ) {
        $age_order = [
            '0-6-ay-sadece-sut' => 1,
            '6-8-ay-baslangic' => 2,
            '9-11-ay-kesif' => 3,
            '12-24-ay-gecis' => 4,
            '2-yas-ve-uzeri' => 5,
        ];
        
        $order_1 = isset( $age_order[ $age_group_1 ] ) ? $age_order[ $age_group_1 ] : 0;
        $order_2 = isset( $age_order[ $age_group_2 ] ) ? $age_order[ $age_group_2 ] : 0;
        
        return $order_1 > $order_2;
    }
}

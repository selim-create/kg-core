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
            // Set is_safe to false if recipe is for older children
            if ( $this->is_recipe_for_older_children( $age_alerts ) ) {
                $is_safe = false;
            }
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
        
        // 6. Decode HTML entities in all alert messages
        $alerts = $this->decode_alert_messages( $alerts );
        
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
     * Decode HTML entities in all alert messages
     * 
     * Ensures that API responses never contain HTML entities like &amp;, &lt;, &gt;
     * All messages are decoded to their plain text equivalents for frontend display.
     * 
     * @param array $alerts Array of alerts
     * @return array Alerts with decoded messages
     */
    private function decode_alert_messages( $alerts ) {
        foreach ( $alerts as &$alert ) {
            if ( isset( $alert['message'] ) ) {
                $alert['message'] = html_entity_decode( $alert['message'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            }
            if ( isset( $alert['alternative'] ) ) {
                $alert['alternative'] = html_entity_decode( $alert['alternative'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            }
            if ( isset( $alert['reason'] ) ) {
                $alert['reason'] = html_entity_decode( $alert['reason'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            }
            if ( isset( $alert['ingredient'] ) ) {
                $alert['ingredient'] = html_entity_decode( $alert['ingredient'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            }
        }
        return $alerts;
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
                    'severity_color' => 'yellow',
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
            $severity_color = ( $allergy_risk === 'Yüksek' ) ? 'yellow' : 'blue';
            
            $alerts[] = [
                'type' => 'nutrition',
                'severity' => $severity,
                'severity_color' => $severity_color,
                'message' => sprintf( 
                    '%s daha önce denenmemiş. İlk kez vereceğiniz için dikkatli olun. Alerji riski: %s',
                    $ingredient->post_title,
                    $allergy_risk ?: 'Düşük'
                ),
                'ingredient' => $ingredient->post_title,
                'alternative' => $allergy_risk === 'Yüksek' ? 'İlk kez verirken az miktarda deneyin ve 3 gün bekleyin.' : null,
            ];
        }
        
        // Decode HTML entities in all alert messages
        $alerts = $this->decode_alert_messages( $alerts );
        
        return [
            'ingredient_id' => $ingredient_id,
            'ingredient_name' => html_entity_decode( $ingredient->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
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
     * 
     * Allergy alerts are always CRITICAL (red) since they pose immediate health risks.
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
                    'severity_color' => 'red',
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
     * Check age appropriateness using centralized mapping
     * 
     * Centralized Age Compatibility Mapping:
     * - Matching age groups → success (green, safe)
     * - Older child with younger recipe → info (blue/yellow, can still use)
     * - Younger child with older recipe → warning/critical (red, dangerous)
     *   - Small gap (1 level) → warning
     *   - Large gap (2+ levels) → critical
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
            // Get severity and message based on centralized mapping
            $severity_data = $this->get_age_compatibility_severity( 
                $current_age_group, 
                $age_groups[0]->slug,
                $age_in_months,
                $age_groups[0]->name
            );
            
            $alerts[] = [
                'type' => 'age',
                'severity' => $severity_data['severity'],
                'severity_color' => $severity_data['color'],
                'message' => $severity_data['message'],
                'alternative' => $severity_data['alternative'],
                'is_for_older' => $severity_data['is_for_older'],
                'child_age_months' => $age_in_months,
                'child_age_group' => $current_age_group,
                'recipe_age_group' => $age_groups[0]->slug,
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Centralized Age Compatibility Severity Mapping
     * 
     * This function implements a deterministic mapping table for all age group combinations.
     * It ensures consistent severity levels across the entire application.
     * 
     * Mapping Logic:
     * ┌────────────────────────────────────────────────────────────────────────────┐
     * │ Child Age vs Recipe Age                    │ Severity  │ Color  │ Safe?  │
     * ├────────────────────────────────────────────────────────────────────────────┤
     * │ Exact match (same age group)               │ success   │ green  │ Yes    │
     * │ Older child, younger recipe (1-2 levels)   │ info      │ blue   │ Yes    │
     * │ Younger child, older recipe (1 level gap)  │ warning   │ yellow │ No     │
     * │ Younger child, older recipe (2+ level gap) │ critical  │ red    │ No     │
     * │ 0-6 months with 9-11 months recipe         │ warning   │ yellow │ No     │
     * │ 0-6 months with 2+ years recipe            │ critical  │ red    │ No     │
     * └────────────────────────────────────────────────────────────────────────────┘
     * 
     * @param string $child_age_group Child's current age group slug
     * @param string $recipe_age_group Recipe's age group slug
     * @param int $child_age_months Child's age in months
     * @param string $recipe_age_name Recipe's age group name (for messages)
     * @return array Severity data with severity, color, message, alternative, is_for_older
     */
    private function get_age_compatibility_severity( $child_age_group, $recipe_age_group, $child_age_months, $recipe_age_name ) {
        $age_order = [
            '0-6-ay-sadece-sut' => 0,
            '6-8-ay-baslangic' => 1,
            '9-11-ay-kesif' => 2,
            '12-24-ay-gecis' => 3,
            '2-yas-ve-uzeri' => 4,
        ];
        
        $child_level = isset( $age_order[ $child_age_group ] ) ? $age_order[ $child_age_group ] : 1;
        $recipe_level = isset( $age_order[ $recipe_age_group ] ) ? $age_order[ $recipe_age_group ] : 1;
        $level_gap = $recipe_level - $child_level;
        
        $base_message = sprintf(
            'Bu tarif %s yaş grubu için önerilmiş. Çocuğunuz %d aylık.',
            $recipe_age_name,
            $child_age_months
        );
        
        // Recipe is for older children (dangerous)
        if ( $level_gap > 0 ) {
            // Determine severity based on gap size
            if ( $level_gap >= 2 ) {
                // Large gap (2+ levels) → CRITICAL
                return [
                    'severity' => 'critical',
                    'color' => 'red',
                    'message' => $base_message . ' KESİNLİKLE VERMEYİN! Bu tarif çocuğunuz için çok erken ve tehlikeli olabilir.',
                    'alternative' => 'Yaş grubunuza uygun tariflere göz atın.',
                    'is_for_older' => true,
                ];
            } else {
                // Small gap (1 level) → WARNING
                return [
                    'severity' => 'warning',
                    'color' => 'yellow',
                    'message' => $base_message . ' Bu tarif çocuğunuz için erken olabilir. Dikkatli olun.',
                    'alternative' => 'Yaş grubunuza daha uygun tariflere öncelik verin.',
                    'is_for_older' => true,
                ];
            }
        }
        
        // Recipe is for younger children (safe, but informational)
        // Older children can eat food designed for younger ones
        return [
            'severity' => 'info',
            'color' => 'blue',
            'message' => $base_message . ' Çocuğunuz bu tarifi yiyebilir.',
            'alternative' => 'Bu tarifi güvenle verebilirsiniz. Yaşına uygun daha gelişmiş tarifler de mevcuttur.',
            'is_for_older' => false,
        ];
    }
    
    /**
     * Check for forbidden ingredients
     * 
     * Forbidden ingredient severity mapping:
     * - Honey for <12 months → CRITICAL (red, botulism risk)
     * - Whole nuts for <48 months → CRITICAL (red, choking hazard)
     * - Cow's milk as main drink <12 months → WARNING (yellow, nutritional concern)
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
                        'severity_color' => 'red',
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
                                'severity_color' => 'red',
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
                    'severity_color' => 'yellow',
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
     * 
     * Nutrition alert severity mapping:
     * - Salt for <12 months → WARNING (yellow, kidney concern)
     * - Sugar for young children → INFO (blue, nutritional advice)
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
                        'severity_color' => 'yellow',
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
                        'severity_color' => 'blue',
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
                    'severity_color' => 'red',
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
     * Check if age alerts indicate recipe is for older children
     * 
     * @param array $alerts Age alerts
     * @return bool True if recipe is for older children
     */
    private function is_recipe_for_older_children( $alerts ) {
        foreach ( $alerts as $alert ) {
            if ( $alert['type'] === 'age' && 
                 isset( $alert['is_for_older'] ) && 
                 $alert['is_for_older'] === true ) {
                return true;
            }
        }
        return false;
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

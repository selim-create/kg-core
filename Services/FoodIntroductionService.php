<?php
namespace KG_Core\Services;

/**
 * Food Introduction Service
 * Manages food introduction schedule and tracking for children
 */
class FoodIntroductionService {

    /**
     * Get suggested foods for child's age
     * 
     * @param int $age_in_months Child's age in months
     * @return array Suggested foods with introduction guidelines
     */
    public function getSuggestedFoodsForAge( $age_in_months ) {
        $suggestions = [];
        
        if ( $age_in_months < 6 ) {
            return [
                'message' => '0-6 ay arası bebekler için sadece anne sütü veya devam formülü önerilir.',
                'foods' => [],
            ];
        }
        
        // Get foods appropriate for this age
        $foods = $this->get_foods_by_age_range( $age_in_months );
        
        foreach ( $foods as $food ) {
            $suggestions[] = [
                'ingredient_id' => $food->ID,
                'name' => $food->post_title,
                'start_age' => get_post_meta( $food->ID, '_kg_start_age', true ),
                'allergy_risk' => get_post_meta( $food->ID, '_kg_allergy_risk', true ) ?: 'Düşük',
                'preparation_tips' => get_post_meta( $food->ID, '_kg_preparation_tips', true ),
                'introduction_guide' => $this->get_introduction_guide( $food->ID ),
            ];
        }
        
        return [
            'age_in_months' => $age_in_months,
            'foods' => $suggestions,
        ];
    }
    
    /**
     * Get food introduction history for a child
     * 
     * @param string $child_id Child ID
     * @param int $user_id User ID
     * @return array Introduction history
     */
    public function getIntroductionHistory( $child_id, $user_id ) {
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
        
        $introduced_foods = isset( $child['introduced_foods'] ) ? $child['introduced_foods'] : [];
        $food_reactions = isset( $child['food_reactions'] ) ? $child['food_reactions'] : [];
        
        $history = [];
        
        // Get details for each introduced food
        foreach ( $introduced_foods as $food_identifier ) {
            // Try to find the ingredient
            $ingredient = $this->find_ingredient_by_identifier( $food_identifier );
            
            if ( $ingredient ) {
                $reaction = $this->find_reaction_for_food( $food_identifier, $food_reactions );
                
                $history[] = [
                    'food' => $ingredient->post_title,
                    'ingredient_id' => $ingredient->ID,
                    'introduced_date' => $reaction ? $reaction['date'] : null,
                    'reaction' => $reaction ? $reaction['reaction'] : 'none',
                    'notes' => $reaction ? $reaction['notes'] : '',
                ];
            } else {
                // Food not found in database, show as text
                $reaction = $this->find_reaction_for_food( $food_identifier, $food_reactions );
                
                $history[] = [
                    'food' => $food_identifier,
                    'ingredient_id' => null,
                    'introduced_date' => $reaction ? $reaction['date'] : null,
                    'reaction' => $reaction ? $reaction['reaction'] : 'none',
                    'notes' => $reaction ? $reaction['notes'] : '',
                ];
            }
        }
        
        // Sort by date (newest first)
        usort( $history, function( $a, $b ) {
            if ( ! $a['introduced_date'] ) return 1;
            if ( ! $b['introduced_date'] ) return -1;
            return strtotime( $b['introduced_date'] ) - strtotime( $a['introduced_date'] );
        });
        
        return $history;
    }
    
    /**
     * Log food introduction for a child
     * 
     * @param string $child_id Child ID
     * @param int $user_id User ID
     * @param array $food_data Food introduction data
     * @return array Updated child profile
     */
    public function logFoodIntroduction( $child_id, $user_id, $food_data ) {
        $children = get_user_meta( $user_id, '_kg_children', true );
        
        if ( ! is_array( $children ) ) {
            return new \WP_Error( 'no_children', 'No children found', [ 'status' => 404 ] );
        }
        
        $child_index = null;
        foreach ( $children as $index => $c ) {
            if ( $c['id'] === $child_id ) {
                $child_index = $index;
                break;
            }
        }
        
        if ( $child_index === null ) {
            return new \WP_Error( 'child_not_found', 'Child not found', [ 'status' => 404 ] );
        }
        
        // Validate food_data
        if ( empty( $food_data['food'] ) ) {
            return new \WP_Error( 'missing_food', 'Food name is required', [ 'status' => 400 ] );
        }
        
        $food_identifier = sanitize_text_field( $food_data['food'] );
        $reaction = isset( $food_data['reaction'] ) ? sanitize_text_field( $food_data['reaction'] ) : 'none';
        $notes = isset( $food_data['notes'] ) ? sanitize_textarea_field( $food_data['notes'] ) : '';
        $date = isset( $food_data['date'] ) ? sanitize_text_field( $food_data['date'] ) : date( 'Y-m-d' );
        
        // Initialize arrays if not exist
        if ( ! isset( $children[ $child_index ]['introduced_foods'] ) ) {
            $children[ $child_index ]['introduced_foods'] = [];
        }
        if ( ! isset( $children[ $child_index ]['food_reactions'] ) ) {
            $children[ $child_index ]['food_reactions'] = [];
        }
        
        // Add to introduced foods if not already there
        if ( ! in_array( $food_identifier, $children[ $child_index ]['introduced_foods'] ) ) {
            $children[ $child_index ]['introduced_foods'][] = $food_identifier;
        }
        
        // Log reaction
        $children[ $child_index ]['food_reactions'][] = [
            'food' => $food_identifier,
            'reaction' => $reaction,
            'notes' => $notes,
            'date' => $date,
        ];
        
        // Update user meta
        update_user_meta( $user_id, '_kg_children', $children );
        
        return [
            'success' => true,
            'food' => $food_identifier,
            'reaction' => $reaction,
            'date' => $date,
        ];
    }
    
    /**
     * Get next food suggestion for introduction
     * 
     * @param string $child_id Child ID
     * @param int $user_id User ID
     * @return array Next food suggestions
     */
    public function getNextFoodSuggestion( $child_id, $user_id ) {
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
        
        $age_in_months = $this->calculate_age_in_months( $child['birth_date'] );
        
        if ( $age_in_months < 6 ) {
            return [
                'suggestions' => [],
                'message' => '6 aydan küçük bebekler için katı gıda önerilmez.',
            ];
        }
        
        $introduced_foods = isset( $child['introduced_foods'] ) ? $child['introduced_foods'] : [];
        
        // Get age-appropriate foods
        $appropriate_foods = $this->get_foods_by_age_range( $age_in_months );
        
        // Filter out already introduced foods
        $suggestions = [];
        foreach ( $appropriate_foods as $food ) {
            $is_introduced = in_array( $food->post_name, $introduced_foods ) ||
                           in_array( $food->post_title, $introduced_foods );
            
            if ( ! $is_introduced ) {
                $allergy_risk = get_post_meta( $food->ID, '_kg_allergy_risk', true ) ?: 'Düşük';
                
                $suggestions[] = [
                    'ingredient_id' => $food->ID,
                    'name' => $food->post_title,
                    'allergy_risk' => $allergy_risk,
                    'start_age' => get_post_meta( $food->ID, '_kg_start_age', true ),
                    'priority' => $this->calculate_introduction_priority( $food, $age_in_months ),
                    'introduction_guide' => $this->get_introduction_guide( $food->ID ),
                ];
            }
        }
        
        // Sort by priority (high to low)
        usort( $suggestions, function( $a, $b ) {
            return $b['priority'] - $a['priority'];
        });
        
        // Return top 5
        $suggestions = array_slice( $suggestions, 0, 5 );
        
        return [
            'age_in_months' => $age_in_months,
            'introduced_count' => count( $introduced_foods ),
            'suggestions' => $suggestions,
            'message' => $this->get_introduction_message( $age_in_months, count( $introduced_foods ) ),
        ];
    }
    
    /**
     * Get foods by age range
     */
    private function get_foods_by_age_range( $age_in_months ) {
        $args = [
            'post_type' => 'ingredient',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_kg_start_age',
                    'value' => $age_in_months,
                    'compare' => '<=',
                    'type' => 'NUMERIC',
                ],
            ],
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        
        $query = new \WP_Query( $args );
        
        return $query->posts;
    }
    
    /**
     * Find ingredient by identifier (slug or title)
     */
    private function find_ingredient_by_identifier( $identifier ) {
        // Try by slug first
        $args = [
            'post_type' => 'ingredient',
            'post_status' => 'publish',
            'name' => $identifier,
            'posts_per_page' => 1,
        ];
        
        $query = new \WP_Query( $args );
        
        if ( $query->have_posts() ) {
            return $query->posts[0];
        }
        
        // Try by title
        $args = [
            'post_type' => 'ingredient',
            'post_status' => 'publish',
            'title' => $identifier,
            'posts_per_page' => 1,
        ];
        
        $query = new \WP_Query( $args );
        
        if ( $query->have_posts() ) {
            return $query->posts[0];
        }
        
        return null;
    }
    
    /**
     * Find reaction data for a food
     */
    private function find_reaction_for_food( $food_identifier, $reactions ) {
        if ( ! is_array( $reactions ) ) {
            return null;
        }
        
        // Find the most recent reaction
        foreach ( array_reverse( $reactions ) as $reaction ) {
            if ( isset( $reaction['food'] ) && $reaction['food'] === $food_identifier ) {
                return $reaction;
            }
        }
        
        return null;
    }
    
    /**
     * Get introduction guide for ingredient
     */
    private function get_introduction_guide( $ingredient_id ) {
        $allergy_risk = get_post_meta( $ingredient_id, '_kg_allergy_risk', true );
        
        $guide = [
            'first_portion' => '1-2 çay kaşığı',
            'waiting_period' => '3 gün',
            'best_time' => 'Sabah veya öğle',
            'tips' => [],
        ];
        
        if ( $allergy_risk === 'Yüksek' ) {
            $guide['first_portion'] = '1/4 çay kaşığı';
            $guide['waiting_period'] = '5 gün';
            $guide['tips'][] = 'İlk kez verirken çok az miktarda başlayın.';
            $guide['tips'][] = 'Reaksiyon takibi için sabah saatlerinde verin.';
            $guide['tips'][] = 'Aynı gün başka yeni besin vermeyin.';
        } elseif ( $allergy_risk === 'Orta' ) {
            $guide['first_portion'] = '1/2 çay kaşığı';
            $guide['waiting_period'] = '3-4 gün';
            $guide['tips'][] = 'İlk gün az miktarda deneyin.';
            $guide['tips'][] = 'Reaksiyonları gözlemleyin.';
        } else {
            $guide['tips'][] = 'Normal porsiyon ile başlayabilirsiniz.';
            $guide['tips'][] = 'Yine de reaksiyonları takip edin.';
        }
        
        return $guide;
    }
    
    /**
     * Calculate introduction priority
     */
    private function calculate_introduction_priority( $food, $age_in_months ) {
        $priority = 50; // Base priority
        
        // Nutritional importance
        $nutrition_cats = wp_get_post_terms( $food->ID, 'nutrition-category', [ 'fields' => 'slugs' ] );
        
        if ( ! empty( $nutrition_cats ) && ! is_wp_error( $nutrition_cats ) ) {
            if ( in_array( 'iron-rich', $nutrition_cats ) || in_array( 'demir', $nutrition_cats ) ) {
                $priority += 30; // Iron is critical
            }
            if ( in_array( 'protein', $nutrition_cats ) ) {
                $priority += 20;
            }
        }
        
        // Age appropriateness
        $start_age = get_post_meta( $food->ID, '_kg_start_age', true );
        if ( $start_age ) {
            $start_age_months = (int) $start_age;
            if ( $age_in_months >= $start_age_months && $age_in_months < $start_age_months + 3 ) {
                $priority += 20; // Perfect timing
            }
        }
        
        // Allergy risk consideration (introduce high-risk early is WHO recommendation)
        $allergy_risk = get_post_meta( $food->ID, '_kg_allergy_risk', true );
        if ( $allergy_risk === 'Yüksek' && $age_in_months >= 6 && $age_in_months <= 12 ) {
            $priority += 15; // Early introduction of allergens is recommended
        }
        
        return $priority;
    }
    
    /**
     * Get introduction message based on progress
     */
    private function get_introduction_message( $age_in_months, $introduced_count ) {
        if ( $age_in_months >= 6 && $age_in_months <= 8 ) {
            if ( $introduced_count < 5 ) {
                return 'Yeni başlıyorsunuz! Haftada 1-2 yeni besin denemeye başlayın.';
            } elseif ( $introduced_count < 10 ) {
                return 'İyi gidiyorsunuz! Besin çeşitliliğini artırmaya devam edin.';
            } else {
                return 'Harika! Çok çeşitli besinler deniyorsunuz.';
            }
        } elseif ( $age_in_months >= 9 && $age_in_months <= 11 ) {
            if ( $introduced_count < 15 ) {
                return 'Daha fazla çeşitlilik eklemeye başlayabilirsiniz.';
            } else {
                return 'Çok iyi! Besin çeşitliliğiniz yeterli.';
            }
        } else {
            if ( $introduced_count < 25 ) {
                return 'Yeni besinler denemeye devam edin.';
            } else {
                return 'Mükemmel! Geniş bir besin çeşitliliğine ulaştınız.';
            }
        }
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

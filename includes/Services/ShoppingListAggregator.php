<?php
namespace KG_Core\Services;

/**
 * Shopping List Aggregator Service
 * Aggregates and categorizes ingredients from meal plans
 */
class ShoppingListAggregator {

    /**
     * Ingredient categories
     */
    const CATEGORIES = [
        'fruits_vegetables' => 'Meyve & Sebze',
        'meat_protein' => 'Et & Protein',
        'dairy' => 'Süt Ürünleri',
        'grains' => 'Kuru Gıda & Tahıllar',
        'other' => 'Diğer',
    ];

    /**
     * Turkish measurement units
     */
    const UNITS = [
        'adet', 'gram', 'kg', 'ml', 'lt',
        'su bardağı', 'çay kaşığı', 'yemek kaşığı',
        'tutam', 'dilim', 'demet'
    ];

    /**
     * Fruits and vegetables keywords for categorization
     */
    const FRUITS_VEGETABLES = [
        'avokado', 'muz', 'elma', 'armut', 'şeftali', 'kiraz', 'çilek', 'üzüm',
        'havuç', 'kabak', 'patates', 'tatlı patates', 'brokoli', 'karnabahar',
        'ıspanak', 'bezelye', 'domates', 'salatalık', 'biber', 'patlıcan',
        'meyve', 'sebze',
    ];

    /**
     * Meat and protein keywords for categorization
     */
    const MEAT_PROTEIN = [
        'tavuk', 'hindi', 'et', 'kıyma', 'balık', 'somon', 'levrek',
        'yumurta', 'fasulye', 'nohut', 'mercimek', 'bakla',
    ];

    /**
     * Dairy keywords for categorization
     */
    const DAIRY = [
        'süt', 'yoğurt', 'peynir', 'labne', 'lor', 'beyaz peynir',
        'kaşar', 'tereyağı', 'krema',
    ];

    /**
     * Grains keywords for categorization
     */
    const GRAINS = [
        'un', 'bulgur', 'pirinç', 'makarna', 'ekmek', 'yulaf',
        'arpa', 'mısır', 'tahıl', 'kepek',
    ];

    /**
     * Generate shopping list from meal plan
     *
     * @param array $plan Meal plan data
     * @return array Shopping list items
     */
    public function generate( $plan ) {
        $ingredients = [];
        
        // 1. Collect all recipe IDs from the plan
        $recipe_ids = [];
        foreach ( $plan['days'] as $day ) {
            foreach ( $day['slots'] as $slot ) {
                if ( $slot['status'] === 'filled' && $slot['recipe_id'] ) {
                    $recipe_ids[] = $slot['recipe_id'];
                }
            }
        }
        
        // 2. Get ingredients from each recipe
        foreach ( $recipe_ids as $recipe_id ) {
            $recipe_ingredients = $this->get_recipe_ingredients( $recipe_id );
            
            foreach ( $recipe_ingredients as $ingredient ) {
                $key = $this->normalize_ingredient_name( $ingredient['name'] );
                
                if ( ! isset( $ingredients[$key] ) ) {
                    $ingredients[$key] = [
                        'ingredient_name' => $ingredient['name'],
                        'total_amount' => 0,
                        'unit' => $ingredient['unit'],
                        'category' => $this->categorize_ingredient( $ingredient['name'] ),
                        'recipes' => [],
                        'checked' => false,
                    ];
                }
                
                // Add to total amount
                $ingredients[$key]['total_amount'] += $this->parse_amount( $ingredient['amount'] );
                
                // Track which recipes use this ingredient
                $ingredients[$key]['recipes'][] = [
                    'id' => $recipe_id,
                    'title' => get_the_title( $recipe_id ),
                    'amount' => $ingredient['amount'] . ' ' . $ingredient['unit'],
                ];
            }
        }
        
        // 3. Sort by category
        $categorized = $this->group_by_category( $ingredients );
        
        return [
            'success' => true,
            'items' => array_values( $ingredients ),
            'total_count' => count( $ingredients ),
        ];
    }

    /**
     * Get ingredients from a recipe
     *
     * @param int $recipe_id Recipe post ID
     * @return array Ingredients list
     */
    private function get_recipe_ingredients( $recipe_id ) {
        $ingredients_meta = get_post_meta( $recipe_id, '_kg_ingredients', true );
        $parsed_ingredients = [];
        
        if ( ! is_array( $ingredients_meta ) ) {
            return [];
        }
        
        foreach ( $ingredients_meta as $ingredient ) {
            // Support both old and new format
            if ( is_array( $ingredient ) ) {
                $name = isset( $ingredient['name'] ) ? $ingredient['name'] : '';
                $amount = isset( $ingredient['amount'] ) ? $ingredient['amount'] : '';
            } else {
                // Parse string format like "2 adet Avokado"
                $parts = $this->parse_ingredient_string( $ingredient );
                $name = $parts['name'];
                $amount = $parts['amount'];
            }
            
            if ( ! empty( $name ) ) {
                $parsed_ingredients[] = [
                    'name' => $name,
                    'amount' => $this->extract_amount( $amount ),
                    'unit' => $this->extract_unit( $amount ),
                ];
            }
        }
        
        return $parsed_ingredients;
    }

    /**
     * Parse ingredient string
     *
     * @param string $ingredient Ingredient string
     * @return array Parsed ingredient
     */
    private function parse_ingredient_string( $ingredient ) {
        // Try to match pattern: "amount unit name" or "amount name"
        if ( preg_match( '/^([\d\.,\/\s]+)?\s*([a-zığüşöçA-ZİĞÜŞÖÇ\.]+)?\s*(.+)$/u', $ingredient, $matches ) ) {
            return [
                'amount' => trim( $matches[1] . ' ' . $matches[2] ),
                'name' => trim( $matches[3] ),
            ];
        }
        
        return [
            'amount' => '',
            'name' => $ingredient,
        ];
    }

    /**
     * Extract amount from amount string
     *
     * @param string $amount Amount string
     * @return string Amount
     */
    private function extract_amount( $amount ) {
        if ( preg_match( '/^([\d\.,\/\s]+)/', $amount, $matches ) ) {
            return trim( $matches[1] );
        }
        return '1';
    }

    /**
     * Extract unit from amount string
     *
     * @param string $amount Amount string
     * @return string Unit
     */
    private function extract_unit( $amount ) {
        $units_pattern = '(' . implode( '|', array_map( 'preg_quote', self::UNITS ) ) . ')';
        if ( preg_match( '/' . $units_pattern . '/i', $amount, $matches ) ) {
            return $matches[1];
        }
        return 'adet';
    }

    /**
     * Parse amount to numeric value
     *
     * @param string $amount Amount string
     * @return float Numeric amount
     */
    private function parse_amount( $amount ) {
        // Handle fractions like "1/2"
        if ( strpos( $amount, '/' ) !== false ) {
            $parts = explode( '/', $amount );
            if ( count( $parts ) === 2 && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) ) {
                return floatval( $parts[0] ) / floatval( $parts[1] );
            }
        }
        
        // Replace comma with dot for decimal parsing
        $amount = str_replace( ',', '.', $amount );
        
        // Extract first numeric value
        if ( preg_match( '/[\d\.]+/', $amount, $matches ) ) {
            return floatval( $matches[0] );
        }
        
        return 1.0;
    }

    /**
     * Normalize ingredient name for grouping
     *
     * @param string $name Ingredient name
     * @return string Normalized name
     */
    private function normalize_ingredient_name( $name ) {
        // Convert to lowercase and remove extra spaces
        $normalized = mb_strtolower( trim( $name ), 'UTF-8' );
        
        // Remove common variations
        $normalized = str_replace( ['taze ', 'organik ', 'yerli '], '', $normalized );
        
        return $normalized;
    }

    /**
     * Categorize ingredient
     *
     * @param string $name Ingredient name
     * @return string Category key
     */
    private function categorize_ingredient( $name ) {
        $name_lower = mb_strtolower( $name, 'UTF-8' );
        
        // Fruits & Vegetables
        foreach ( self::FRUITS_VEGETABLES as $item ) {
            if ( strpos( $name_lower, $item ) !== false ) {
                return 'fruits_vegetables';
            }
        }
        
        // Meat & Protein
        foreach ( self::MEAT_PROTEIN as $item ) {
            if ( strpos( $name_lower, $item ) !== false ) {
                return 'meat_protein';
            }
        }
        
        // Dairy
        foreach ( self::DAIRY as $item ) {
            if ( strpos( $name_lower, $item ) !== false ) {
                return 'dairy';
            }
        }
        
        // Grains
        foreach ( self::GRAINS as $item ) {
            if ( strpos( $name_lower, $item ) !== false ) {
                return 'grains';
            }
        }
        
        return 'other';
    }

    /**
     * Group ingredients by category
     *
     * @param array $ingredients Ingredients array
     * @return array Grouped ingredients
     */
    private function group_by_category( $ingredients ) {
        $grouped = [];
        
        foreach ( self::CATEGORIES as $key => $label ) {
            $grouped[$key] = [
                'category' => $label,
                'items' => [],
            ];
        }
        
        foreach ( $ingredients as $ingredient ) {
            $category = $ingredient['category'];
            $grouped[$category]['items'][] = $ingredient;
        }
        
        return $grouped;
    }
}

<?php
namespace KG_Core\Migration;

/**
 * IngredientParser - Parse and standardize ingredient strings
 */
class IngredientParser {
    
    private $units = [
        'adet', 'çiçek', 'dal', 'yaprak', 'dilim',
        'çay kaşığı', 'tatlı kaşığı', 'yemek kaşığı',
        'çay bardağı', 'su bardağı', 'türk kahvesi fincanı',
        'avuç', 'tutam', 'ölçek',
        'gram', 'g', 'kg', 'ml', 'litre', 'lt'
    ];
    
    /**
     * Parse ingredient string into structured data
     * 
     * @param string $ingredientStr Raw ingredient string (e.g., "1/4 adet ince kıyılmış lahana")
     * @return array Parsed ingredient data
     */
    public function parse($ingredientStr) {
        $result = [
            'quantity' => '',
            'unit' => 'adet',
            'name' => '',
            'ingredient_id' => null,
            'preparation_note' => ''
        ];
        
        // Clean the string
        $ingredientStr = trim($ingredientStr);
        $ingredientStr = preg_replace('/\s+/', ' ', $ingredientStr); // Normalize whitespace
        
        // Extract quantity (numbers, fractions, ranges)
        // Patterns: "1", "1/2", "1,5", "1.5", "1-2", "bir", "yarım"
        $quantityPattern = '/^(bir|yarım|çeyrek|[\d\/\.,\-]+)\s*/iu';
        if (preg_match($quantityPattern, $ingredientStr, $matches)) {
            $result['quantity'] = $this->normalizeQuantity($matches[1]);
            $ingredientStr = trim(substr($ingredientStr, strlen($matches[0])));
        }
        
        // Extract unit
        $unitPattern = $this->buildUnitPattern();
        if (preg_match($unitPattern, $ingredientStr, $matches)) {
            $result['unit'] = $this->normalizeUnit($matches[0]);
            $ingredientStr = trim(substr($ingredientStr, strlen($matches[0])));
        }
        
        // What remains should be ingredient name + optional preparation notes
        // Pattern: preparation notes are usually at the end (doğranmış, kıyılmış, rendelenmiş, etc.)
        $preparationPattern = '/(ince|kalın|küçük|büyük|orta|küp|dilim|rendel\w+|doğran\w+|kıyıl\w+|soyul\w+|çekilmiş|püre|haşlan\w+|ez\w+|yıkan\w+|temizlen\w+|ayıklan\w+|kesilmiş|sıkıl\w+)/iu';
        
        if (preg_match($preparationPattern, $ingredientStr, $matches, PREG_OFFSET_CAPTURE)) {
            $result['name'] = trim(substr($ingredientStr, 0, $matches[0][1]));
            $result['preparation_note'] = trim(substr($ingredientStr, $matches[0][1]));
        } else {
            $result['name'] = $ingredientStr;
        }
        
        // Capitalize first letter of name
        $result['name'] = $this->capitalizeFirstLetter($result['name']);
        
        return $result;
    }
    
    /**
     * Build regex pattern for units
     * 
     * @return string Regex pattern
     */
    private function buildUnitPattern() {
        // Sort units by length descending to match longer units first
        $sortedUnits = $this->units;
        usort($sortedUnits, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        $escapedUnits = array_map('preg_quote', $sortedUnits);
        return '/^(' . implode('|', $escapedUnits) . ')\s*/iu';
    }
    
    /**
     * Normalize quantity value
     * Convert Turkish words to numbers
     * 
     * @param string $quantity Raw quantity
     * @return string Normalized quantity
     */
    private function normalizeQuantity($quantity) {
        $quantity = mb_strtolower($quantity, 'UTF-8');
        
        $replacements = [
            'bir' => '1',
            'yarım' => '1/2',
            'çeyrek' => '1/4',
        ];
        
        if (isset($replacements[$quantity])) {
            return $replacements[$quantity];
        }
        
        // Replace comma with dot for decimals
        $quantity = str_replace(',', '.', $quantity);
        
        return $quantity;
    }
    
    /**
     * Normalize unit to standard form
     * 
     * @param string $unit Raw unit
     * @return string Normalized unit
     */
    private function normalizeUnit($unit) {
        $unit = mb_strtolower($unit, 'UTF-8');
        
        $replacements = [
            'g' => 'gram',
            'lt' => 'litre',
        ];
        
        if (isset($replacements[$unit])) {
            return $replacements[$unit];
        }
        
        return $unit;
    }
    
    /**
     * Capitalize first letter (Turkish-aware)
     * 
     * @param string $str String to capitalize
     * @return string Capitalized string
     */
    private function capitalizeFirstLetter($str) {
        if (empty($str)) {
            return $str;
        }
        
        $first = mb_substr($str, 0, 1, 'UTF-8');
        $rest = mb_substr($str, 1, null, 'UTF-8');
        
        // Turkish specific capitalization
        $first = mb_strtoupper($first, 'UTF-8');
        if ($first === 'I') {
            $first = 'İ';
        } elseif ($first === 'i') {
            $first = 'I';
        }
        
        return $first . $rest;
    }
    
    /**
     * Match ingredient with existing ingredient CPT
     * 
     * @param string $ingredientName Ingredient name
     * @return int|null Ingredient post ID or null
     */
    public function matchIngredient($ingredientName) {
        // Try exact match first
        $existing = get_page_by_title($ingredientName, OBJECT, 'ingredient');
        if ($existing) {
            return $existing->ID;
        }
        
        // Try fuzzy match (remove common variations)
        $normalized = $this->normalizeIngredientName($ingredientName);
        
        $args = [
            'post_type' => 'ingredient',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => $normalized,
        ];
        
        $query = new \WP_Query($args);
        
        if ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            wp_reset_postdata();
            return $id;
        }
        
        return null;
    }
    
    /**
     * Normalize ingredient name for matching
     * 
     * @param string $name Ingredient name
     * @return string Normalized name
     */
    private function normalizeIngredientName($name) {
        $name = mb_strtolower($name, 'UTF-8');
        
        // Remove common variations
        $name = preg_replace('/(taze|kurutulmuş|dondurulmuş|organik|doğal)\s+/iu', '', $name);
        
        return trim($name);
    }
    
    /**
     * Create new ingredient CPT if not exists
     * 
     * @param string $ingredientName Ingredient name
     * @return int|null New ingredient post ID or null on error
     */
    public function createIngredient($ingredientName) {
        $post_data = [
            'post_title' => $ingredientName,
            'post_type' => 'ingredient',
            'post_status' => 'draft', // Draft for review
            'post_author' => $this->getAuthorId(),
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            error_log('KG Migration: Failed to create ingredient: ' . $ingredientName);
            return null;
        }
        
        return $post_id;
    }
    
    /**
     * Get author ID for creating posts
     * 
     * @return int User ID
     */
    private function getAuthorId() {
        $user_id = get_current_user_id();
        
        if ($user_id === 0) {
            $admins = get_users(['role' => 'administrator', 'number' => 1]);
            if (!empty($admins)) {
                $user_id = $admins[0]->ID;
            } else {
                $user_id = 1;
            }
        }
        
        return $user_id;
    }
}

<?php
namespace KG_Core\Migration;

/**
 * IngredientParser - Parse and standardize ingredient strings
 */
class IngredientParser {
    
    private $units = [
        'çiçek', 'dal', 'yaprak', 'dilim', 'diş', 'demet',
        'çay kaşığı', 'tatlı kaşığı', 'yemek kaşığı', 'kaşık',
        'çay bardağı', 'su bardağı', 'bardak', 'fincan', 'türk kahvesi fincanı',
        'avuç', 'tutam', 'ölçek', 'porsiyon',
        'gram', 'gr', 'g', 'kg', 'kilogram',
        'ml', 'mililitre', 'litre', 'lt', 'l',
        'adet', 'tane', 'parça', 'kase', 'kâse',
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
            'unit' => '',  // Will be set based on what we find
            'name' => '',
            'ingredient_id' => null,
            'preparation_note' => ''
        ];
        
        // Clean the string
        $ingredientStr = trim($ingredientStr);
        $ingredientStr = preg_replace('/\s+/', ' ', $ingredientStr); // Normalize whitespace
        
        // 1. Extract parenthesis content as note
        $parenNote = '';
        if (preg_match('/\(([^)]+)\)/u', $ingredientStr, $parenMatch)) {
            $parenNote = trim($parenMatch[1]);
            // Remove parenthesis and content from main string
            $ingredientStr = trim(str_replace($parenMatch[0], '', $ingredientStr));
        }
        
        // 2. Extract quantity (numbers, fractions, ranges)
        // Patterns: "1", "1/2", "1,5", "1.5", "1-2", "bir", "yarım"
        $quantityPattern = '/^(bir|yarım|çeyrek|[\d\/\.,\-]+)\s*/iu';
        if (preg_match($quantityPattern, $ingredientStr, $matches)) {
            $result['quantity'] = $this->normalizeQuantity($matches[1]);
            $ingredientStr = trim(substr($ingredientStr, strlen($matches[0])));
        }
        
        // 3. Extract unit - use sorted units (longest first)
        $sortedUnits = $this->units;
        usort($sortedUnits, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        $unitFound = false;
        foreach ($sortedUnits as $unit) {
            // Match unit followed by whitespace, punctuation, or end of string
            $unitPattern = '/^' . preg_quote($unit, '/') . '(?:\s+|$)/iu';
            if (preg_match($unitPattern, $ingredientStr, $unitMatch)) {
                $result['unit'] = $unit;
                $ingredientStr = trim(substr($ingredientStr, strlen($unitMatch[0])));
                $unitFound = true;
                break;
            }
        }
        
        // If no unit found but we have a quantity, default to 'adet'
        if (!$unitFound && !empty($result['quantity'])) {
            $result['unit'] = 'adet';
        }
        
        // 4. What remains should be ingredient name + optional preparation notes
        // Pattern: preparation notes can be at start or end
        $preparationPattern = '/\b(ince|kalın|küçük|büyük|orta boy|orta|küp|dilim halinde|dilim|rendel\w+|doğran\w+|kıyıl\w+|soyul\w+|çekilmiş|püre|haşlan\w+|ez\w+|yıkan\w+|temizlen\w+|ayıklan\w+|kesilmiş|sıkıl\w+|ufalanmış)\b/iu';
        
        // Find all preparation terms
        $prepTerms = [];
        if (preg_match_all($preparationPattern, $ingredientStr, $allMatches)) {
            foreach ($allMatches[0] as $match) {
                $prepTerms[] = $match;
            }
            // Remove preparation terms to get ingredient name
            $nameStr = preg_replace($preparationPattern, '', $ingredientStr);
            $ingredientStr = trim(preg_replace('/\s+/', ' ', $nameStr));
        }
        
        // 5. Clean up the name - remove trailing commas, periods
        // Also check if there's a comma followed by alternative text (like ", 1 çay bardağı...")
        // This should go into the note instead
        $nameParts = explode(',', $ingredientStr, 2);
        $result['name'] = trim($nameParts[0], ' ,.');
        
        // 6. Capitalize first letter of name
        $result['name'] = $this->capitalizeFirstLetter($result['name']);
        
        // 7. Combine preparation notes
        $allNotes = [];
        if (!empty($prepTerms)) {
            $allNotes[] = implode(' ', $prepTerms);
        }
        if (!empty($parenNote)) {
            $allNotes[] = $parenNote;
        }
        // If there's text after comma, add it to the note
        if (isset($nameParts[1]) && !empty(trim($nameParts[1]))) {
            $alternativeText = trim($nameParts[1], ' ,.');
            $allNotes[] = $alternativeText;
        }
        
        $result['preparation_note'] = implode('. ', array_filter($allNotes));
        
        return $result;
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
        // First check if it exists
        $existing = $this->matchIngredient($ingredientName);
        if ($existing) {
            return $existing;
        }
        
        // Try to use IngredientGenerator with AI
        if (class_exists('KG_Core\Services\IngredientGenerator')) {
            $generator = new \KG_Core\Services\IngredientGenerator();
            $result = $generator->create($ingredientName);
            
            if (!is_wp_error($result)) {
                error_log("KG Migration: Created ingredient with AI: {$ingredientName} (ID: {$result})");
                return $result;
            }
            
            // If AI failed, log the error and fall back to simple creation
            error_log("KG Migration: AI ingredient creation failed for {$ingredientName}: " . $result->get_error_message());
        }
        
        // Fallback: Create simple ingredient post without AI
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
        
        error_log("KG Migration: Created simple ingredient: {$ingredientName} (ID: {$post_id})");
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

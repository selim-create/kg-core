<?php
namespace KG_Core\Migration;

/**
 * ContentParser - Parse blog post content to extract recipe components
 */
class ContentParser {
    
    /**
     * Parse blog content and extract recipe data
     * 
     * @param string $content HTML content from blog post
     * @param string $title Post title
     * @return array Parsed recipe data
     */
    public function parse($content, $title = '') {
        $result = [
            'ingredients' => [],
            'instructions' => [],
            'expert_note' => '',
            'expert_name' => '',
            'expert_title' => '',
            'video_url' => '',
            'special_notes' => '',
        ];
        
        // Strip shortcodes first
        $content = strip_shortcodes($content);
        
        // Parse ingredients
        $result['ingredients'] = $this->extractIngredients($content);
        
        // Parse instructions
        $result['instructions'] = $this->extractInstructions($content);
        
        // Parse expert note
        $expertData = $this->extractExpertNote($content);
        $result['expert_note'] = $expertData['note'];
        $result['expert_name'] = $expertData['name'];
        $result['expert_title'] = $expertData['title'];
        
        // Parse video URL
        $result['video_url'] = $this->extractVideoUrl($content);
        
        // Parse special notes
        $result['special_notes'] = $this->extractSpecialNotes($content);
        
        return $result;
    }
    
    /**
     * Extract ingredients from content
     * Looks for bullet lists with • or * markers
     * 
     * @param string $content HTML content
     * @return array Raw ingredient strings
     */
    private function extractIngredients($content) {
        $ingredients = [];
        
        error_log("KG Migration: Starting ingredient extraction");
        error_log("KG Migration: Content length: " . strlen($content));
        
        // Find "Malzemeler" section start
        $malzemelerPos = $this->findSectionStart($content, 'Malzemeler');
        
        if ($malzemelerPos === false) {
            error_log("KG Migration: Malzemeler section not found");
            return [];
        }
        
        // Find "Hazırlanış" section start (this is where ingredients end)
        $hazirlanisPos = $this->findSectionStart($content, 'Hazırlan');
        
        // If Hazırlanış not found, use end of content
        $endPos = ($hazirlanisPos !== false) ? $hazirlanisPos : strlen($content);
        
        // Extract only the section BETWEEN Malzemeler and Hazırlanış
        $ingredientSection = substr($content, $malzemelerPos, $endPos - $malzemelerPos);
        
        error_log("KG Migration: Ingredient section length: " . strlen($ingredientSection));
        
        // Extract list items with bullet points (• or *)
        preg_match_all('/[•\*]\s*([^\n<]+)/u', $ingredientSection, $matches);
        if (!empty($matches[1])) {
            $ingredients = array_map('trim', $matches[1]);
        }
        
        // If no bullets found, try HTML list items
        if (empty($ingredients)) {
            preg_match_all('/<li[^>]*>([^<]+)<\/li>/i', $ingredientSection, $liMatches);
            if (!empty($liMatches[1])) {
                $ingredients = array_map('trim', $liMatches[1]);
            }
        }
        
        // Filter out empty items and items that are too short
        $ingredients = array_filter($ingredients, function($item) {
            return strlen(trim($item)) > 2;
        });
        
        error_log("KG Migration: Found " . count($ingredients) . " ingredients");
        error_log("KG Migration: Ingredients: " . print_r($ingredients, true));
        
        return $ingredients;
    }
    
    /**
     * Find the start position of a section in content
     * 
     * @param string $content HTML content
     * @param string $sectionName Section name to find
     * @return int|false Start position or false if not found
     */
    private function findSectionStart($content, $sectionName) {
        // Try various patterns for section headers
        // We want to find the BEGINNING of the section marker, not the end
        $patterns = [
            '/<h[1-6][^>]*>' . $sectionName . '[ıi]?ş?[ıi]?/iu',  // <h3>Hazırlanışı or <h3>Malzemeler
            '/<strong>' . $sectionName . '[ıi]?ş?[ıi]?/iu',       // <strong>Hazırlanışı
            '/\n' . $sectionName . '[ıi]?ş?[ıi]?\s*\n/iu',         // Plain text heading
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                // For Malzemeler, return position AFTER the closing tag
                // For Hazırlanış (when looking for end of ingredients), return position AT the tag start
                if ($sectionName === 'Malzemeler') {
                    // Find the end of the opening tag
                    $tagEnd = strpos($content, '>', $matches[0][1]);
                    if ($tagEnd !== false) {
                        return $tagEnd + 1;
                    }
                } else {
                    // For Hazırlanış, return the START position of the tag
                    return $matches[0][1];
                }
            }
        }
        
        return false;
    }
    
    /**
     * Extract instruction steps from content
     * 
     * @param string $content HTML content
     * @return array Instruction text strings
     */
    private function extractInstructions($content) {
        $instructions = [];
        
        error_log("KG Migration: Starting instruction extraction");
        
        // Look for "Hazırlanışı" or "Hazırlanış" section
        if (preg_match('/Hazırlan[ıi]ş[ıi]?\s*[:\s]*(<\/h[1-6]>|<\/p>|<\/strong>|<br\s*\/?>|\n)/iu', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $startPos = $matches[0][1];
            
            // Find end - usually before expert note or end of content
            $endPos = strlen($content);
            
            // Check for expert note section
            if (preg_match('/[A-ZÇĞİÖŞÜ][a-zçğıöşü\.\s]+[\'\`]+(nın|nin|nun|nün|ın|in|un|ün)\s+(notu|Notu)/u', $content, $endMatches, PREG_OFFSET_CAPTURE, $startPos)) {
                $endPos = $endMatches[0][1];
            }
            
            $instructionSection = substr($content, $startPos, $endPos - $startPos);
            
            // Extract bullet points or list items
            preg_match_all('/[•\*]\s*([^\n<]+)/u', $instructionSection, $matches);
            if (!empty($matches[1])) {
                $instructions = array_map('trim', $matches[1]);
            }
            
            // Try HTML list items if no bullets found
            if (empty($instructions)) {
                preg_match_all('/<li[^>]*>([^<]+)<\/li>/i', $instructionSection, $liMatches);
                if (!empty($liMatches[1])) {
                    $instructions = array_map('trim', $liMatches[1]);
                }
            }
            
            // If still empty, try paragraph-based extraction with bullets
            if (empty($instructions)) {
                preg_match_all('/<p[^>]*>[•\*]?\s*([^<]+)<\/p>/i', $instructionSection, $pMatches);
                if (!empty($pMatches[1])) {
                    $instructions = array_map('trim', $pMatches[1]);
                }
            }
        }
        
        error_log("KG Migration: Found " . count($instructions) . " instructions");
        return array_filter($instructions);
    }
    
    /**
     * Extract expert note and information
     * Pattern: "XXX'nın/ın notu:" or "XXX'nın/ın notu"
     * 
     * @param string $content HTML content
     * @return array ['note' => '', 'name' => '', 'title' => '']
     */
    private function extractExpertNote($content) {
        $result = [
            'note' => '',
            'name' => '',
            'title' => '',
        ];
        
        error_log("KG Migration: Starting expert note extraction");
        
        // Pattern to match expert note header with Turkish characters
        // Examples: "Doç.Dr. Enver Mahir Gülcan'ın notu", "Dyt. Figen Fişekçi Üvez'in notu:"
        // Pattern 1: Title + Name + possessive + notu
        $patterns = [
            // Pattern with title (Doç.Dr., Prof.Dr., Dr., Dyt., Uzm.)
            '/((Doç\.?\s*Dr\.?|Prof\.?\s*Dr\.?|Dr\.?|Dyt\.?|Uzm\.?)\s+([A-ZÇĞİÖŞÜa-zçğıöşü\s\.]+?))[\'\'`´]+\s*(nın|nin|nun|nün|ın|in|un|ün)\s+[Nn]otu:?\s*/u',
            
            // Pattern without title (just name)
            '/([A-ZÇĞİÖŞÜ][a-zçğıöşü]+\s+[A-ZÇĞİÖŞÜ][a-zçğıöşü]+(?:\s+[A-ZÇĞİÖŞÜ][a-zçğıöşü]+)?)[\'\'`´]+\s*(nın|nin|nun|nün|ın|in|un|ün)\s+[Nn]otu:?\s*/u',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $fullMatch = trim($matches[1][0]);
                $title = '';
                $name = $fullMatch;
                
                // Check if we have a title in group 2
                if (isset($matches[2]) && !empty($matches[2][0])) {
                    $title = trim($matches[2][0]);
                    // Name is in group 3
                    if (isset($matches[3]) && !empty($matches[3][0])) {
                        $name = trim($matches[3][0]);
                    }
                } else {
                    // Try to extract title from fullMatch
                    if (preg_match('/^(Doç\.?\s*Dr\.?|Prof\.?\s*Dr\.?|Dr\.?|Dyt\.?|Uzm\.?)\s+(.+)$/iu', $fullMatch, $titleMatch)) {
                        $title = trim($titleMatch[1]);
                        $name = trim($titleMatch[2]);
                    }
                }
                
                // Get note position start
                $startPos = $matches[0][1] + strlen($matches[0][0]);
                
                // Extract the note text (next paragraph or until next heading)
                $endPos = strlen($content);
                
                // Look for next heading, special note, or end of content
                $endPatterns = [
                    '/<h[1-6]/i',
                    '/İlginizi çekebilecek/i',
                    '/<div class=/i',
                ];
                
                foreach ($endPatterns as $endPattern) {
                    if (preg_match($endPattern, $content, $endMatch, PREG_OFFSET_CAPTURE, $startPos)) {
                        $endPos = $endMatch[0][1];
                        break;
                    }
                }
                
                $noteSection = substr($content, $startPos, $endPos - $startPos);
                
                // Clean HTML and get text
                $noteText = wp_strip_all_tags($noteSection);
                $noteText = trim($noteText);
                
                $result['note'] = $noteText;
                $result['name'] = $name;
                $result['title'] = $title;
                
                error_log("KG Migration: Found expert - name: {$name}, title: {$title}, note length: " . strlen($noteText));
                
                break;
            }
        }
        
        if (empty($result['note'])) {
            error_log("KG Migration: No expert note found");
        }
        
        return $result;
    }
    
    /**
     * Extract YouTube video URL from content
     * 
     * @param string $content HTML content
     * @return string Video URL or empty string
     */
    private function extractVideoUrl($content) {
        // Look for YouTube iframe embed
        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/i', $content, $matches)) {
            return 'https://www.youtube.com/watch?v=' . $matches[1];
        }
        
        // Look for YouTube watch URL
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i', $content, $matches)) {
            return 'https://www.youtube.com/watch?v=' . $matches[1];
        }
        
        // Look for youtu.be short URL
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/i', $content, $matches)) {
            return 'https://www.youtube.com/watch?v=' . $matches[1];
        }
        
        return '';
    }
    
    /**
     * Extract special notes (Not:, Süt:, etc.)
     * 
     * @param string $content HTML content
     * @return string Special notes text
     */
    private function extractSpecialNotes($content) {
        $notes = [];
        
        // Look for "Not:" or "Süt:" or similar patterns
        preg_match_all('/(Not|Süt|İpucu|Uyarı|Dikkat):\s*([^\n<]+)/iu', $content, $matches);
        
        if (!empty($matches[0])) {
            $notes = $matches[0];
        }
        
        return implode("\n", $notes);
    }
}

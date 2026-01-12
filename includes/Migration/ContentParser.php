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
        
        // Pattern 1: Look for "Malzemeler" section
        if (preg_match('/Malzemeler\s*(<\/h[1-6]>|<\/p>|<br\s*\/?>)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $startPos = $matches[0][1];
            
            // Find end of ingredients section (usually starts with "Hazırlanış" or another heading)
            $endPos = strlen($content);
            if (preg_match('/Hazırlan[ıi]ş/i', $content, $endMatches, PREG_OFFSET_CAPTURE, $startPos)) {
                $endPos = $endMatches[0][1];
            }
            
            $ingredientSection = substr($content, $startPos, $endPos - $startPos);
            
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
        }
        
        return array_filter($ingredients); // Remove empty items
    }
    
    /**
     * Extract instruction steps from content
     * 
     * @param string $content HTML content
     * @return array Instruction text strings
     */
    private function extractInstructions($content) {
        $instructions = [];
        
        // Look for "Hazırlanışı" or "Hazırlanış" section
        if (preg_match('/Hazırlan[ıi]ş[ıi]?\s*(<\/h[1-6]>|<\/p>|<br\s*\/?>)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
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
        
        // Pattern to match expert note header with Turkish characters
        // Examples: "Doç.Dr. Enver Mahir Gülcan'ın notu", "Dyt. Figen Fişekçi Üvez'in notu:"
        $pattern = '/([A-ZÇĞİÖŞÜ][a-zçğıöşü\.\s]+[A-ZÇĞİÖŞÜ][a-zçğıöşü\.\s]+)[\'\`]+(nın|nin|nun|nün|ın|in|un|ün)\s+(notu|Notu):?\s*(<\/h[1-6]>|<\/p>|<br\s*\/?>)?/u';
        
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $fullName = trim($matches[1][0]);
            $startPos = $matches[0][1] + strlen($matches[0][0]);
            
            // Extract the note text (next paragraph or until next heading)
            $noteText = '';
            $endPos = strlen($content);
            
            // Look for next heading or end of content
            if (preg_match('/<h[1-6]/i', $content, $endMatches, PREG_OFFSET_CAPTURE, $startPos)) {
                $endPos = $endMatches[0][1];
            }
            
            $noteSection = substr($content, $startPos, $endPos - $startPos);
            
            // Clean HTML and get text
            $noteText = wp_strip_all_tags($noteSection);
            $noteText = trim($noteText);
            
            $result['note'] = $noteText;
            $result['name'] = $fullName;
            
            // Try to extract title (Doç.Dr., Dyt., Prof.Dr., etc.)
            if (preg_match('/^([A-ZÇĞİÖŞÜ][a-zçğıöşü]*\.?\s?[A-ZÇĞİÖŞÜ][a-zçğıöşü]*\.?)\s+(.+)$/u', $fullName, $titleMatches)) {
                $result['title'] = trim($titleMatches[1]);
                $result['name'] = trim($titleMatches[2]);
            }
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

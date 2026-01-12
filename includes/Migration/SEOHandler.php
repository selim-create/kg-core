<?php
namespace KG_Core\Migration;

/**
 * SEOHandler - Handle RankMath SEO meta fields
 */
class SEOHandler {
    
    /**
     * Update SEO metadata for a recipe post
     * 
     * @param int $postId Post ID
     * @param array $data Recipe data
     * @return bool Success
     */
    public function updateSEO($postId, $data) {
        $title = isset($data['title']) ? $data['title'] : get_the_title($postId);
        $excerpt = isset($data['excerpt']) ? $data['excerpt'] : get_the_excerpt($postId);
        
        // Generate SEO title if not provided
        $seoTitle = $this->generateSEOTitle($title);
        
        // Use AI-generated description or generate from excerpt
        $seoDescription = '';
        if (!empty($data['seo_description'])) {
            $seoDescription = $data['seo_description'];
        } elseif (!empty($excerpt)) {
            $seoDescription = $this->cleanExcerpt($excerpt);
        } else {
            // Generate description from content
            $seoDescription = $this->generateDescription($postId, $data);
        }
        
        // Generate focus keyword from title
        $focusKeyword = $this->generateFocusKeyword($title);
        
        // Update RankMath meta fields
        update_post_meta($postId, 'rank_math_title', $seoTitle);
        update_post_meta($postId, 'rank_math_description', $seoDescription);
        update_post_meta($postId, 'rank_math_focus_keyword', $focusKeyword);
        update_post_meta($postId, 'rank_math_robots', ['index', 'follow']);
        
        // Also update Yoast SEO as fallback
        update_post_meta($postId, '_yoast_wpseo_title', $seoTitle);
        update_post_meta($postId, '_yoast_wpseo_metadesc', $seoDescription);
        update_post_meta($postId, '_yoast_wpseo_focuskw', $focusKeyword);
        
        // If post doesn't have excerpt, set it
        if (empty($excerpt) && !empty($seoDescription)) {
            wp_update_post([
                'ID' => $postId,
                'post_excerpt' => $seoDescription
            ]);
        }
        
        return true;
    }
    
    /**
     * Generate SEO title from recipe title
     * 
     * @param string $title Recipe title
     * @return string SEO title
     */
    private function generateSEOTitle($title) {
        // Remove extra whitespace
        $title = trim(preg_replace('/\s+/', ' ', $title));
        
        // Add site branding if not too long
        $seoTitle = $title;
        if (strlen($title) < 50) {
            $seoTitle .= ' | KidsGourmet';
        }
        
        // Limit to 60 characters (SEO best practice)
        if (strlen($seoTitle) > 60) {
            $seoTitle = substr($title, 0, 57) . '...';
        }
        
        return $seoTitle;
    }
    
    /**
     * Generate meta description
     * 
     * @param int $postId Post ID
     * @param array $data Recipe data
     * @return string Description (max 160 chars)
     */
    private function generateDescription($postId, $data) {
        $description = '';
        
        // Try to build from recipe data
        $title = isset($data['title']) ? $data['title'] : get_the_title($postId);
        $ageGroup = $this->getAgeGroupName($postId);
        
        if ($ageGroup) {
            $description = "{$title} tarifi {$ageGroup} için. ";
        } else {
            $description = "{$title} tarifi bebek ve çocuklar için. ";
        }
        
        // Add expert info if available
        if (!empty($data['expert_name'])) {
            $description .= "Uzman onaylı. ";
        }
        
        // Add prep time if available
        if (!empty($data['prep_time'])) {
            $description .= "Hazırlama süresi: {$data['prep_time']} dk. ";
        }
        
        // Limit to 160 characters
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157) . '...';
        }
        
        return $description;
    }
    
    /**
     * Generate focus keyword from title
     * 
     * @param string $title Recipe title
     * @return string Focus keyword
     */
    private function generateFocusKeyword($title) {
        // Remove common words and get main keywords
        $stopWords = ['tarifi', 'için', 'ile', 've', 'olan', 'nasıl', 'yapılır'];
        
        $words = explode(' ', mb_strtolower($title, 'UTF-8'));
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return !in_array($word, $stopWords) && strlen($word) > 2;
        });
        
        // Take first 2-3 meaningful words
        $keywords = array_slice($keywords, 0, 3);
        
        return implode(' ', $keywords);
    }
    
    /**
     * Clean excerpt text
     * 
     * @param string $excerpt Excerpt text
     * @return string Cleaned excerpt (max 160 chars)
     */
    private function cleanExcerpt($excerpt) {
        $excerpt = wp_strip_all_tags($excerpt);
        $excerpt = trim($excerpt);
        
        if (strlen($excerpt) > 160) {
            $excerpt = substr($excerpt, 0, 157) . '...';
        }
        
        return $excerpt;
    }
    
    /**
     * Get age group name for post
     * 
     * @param int $postId Post ID
     * @return string|null Age group name or null
     */
    private function getAgeGroupName($postId) {
        $terms = get_the_terms($postId, 'age-group');
        
        if ($terms && !is_wp_error($terms)) {
            $term = array_shift($terms);
            return $term->name;
        }
        
        return null;
    }
    
    /**
     * Generate SEO description using AI
     * 
     * @param string $title Recipe title
     * @param string $content Recipe content
     * @return string|null AI-generated description or null
     */
    public function generateAIDescription($title, $content) {
        $api_key = get_option('kg_openai_api_key', '') ?: get_option('kg_ai_api_key', '');
        
        if (empty($api_key)) {
            return null;
        }
        
        $prompt = "Bu tarif için maksimum 160 karakter SEO açıklaması (meta description) yaz:\n\n";
        $prompt .= "Tarif: {$title}\n\n";
        $prompt .= "Sadece açıklamayı yaz, başka bir şey ekleme.";
        
        $model = get_option('kg_ai_model', 'gpt-4o-mini');
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 100
        ];
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['choices'][0]['message']['content'])) {
            return null;
        }
        
        $description = trim($body['choices'][0]['message']['content']);
        
        // Ensure it's not too long
        if (strlen($description) > 160) {
            $description = substr($description, 0, 157) . '...';
        }
        
        return $description;
    }
}

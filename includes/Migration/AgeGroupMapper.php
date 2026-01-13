<?php
namespace KG_Core\Migration;

/**
 * AgeGroupMapper - Map age expressions to age-group taxonomy terms
 */
class AgeGroupMapper {
    
    private $mappings = [
        // 6-8 ay grubu
        '/5\s*ay/iu' => '6-8-ay-baslangic',
        '/6\s*ay/iu' => '6-8-ay-baslangic',
        '/7\s*ay/iu' => '6-8-ay-baslangic',
        '/8\s*ay/iu' => '6-8-ay-baslangic',
        '/altı\s*ay/iu' => '6-8-ay-baslangic',
        '/yedi\s*ay/iu' => '6-8-ay-baslangic',
        '/sekiz\s*ay/iu' => '6-8-ay-baslangic',
        
        // 9-11 ay grubu
        '/9\s*ay/iu' => '9-11-ay-kesif',
        '/10\s*ay/iu' => '9-11-ay-kesif',
        '/11\s*ay/iu' => '9-11-ay-kesif',
        '/dokuz\s*ay/iu' => '9-11-ay-kesif',
        '/on\s*ay/iu' => '9-11-ay-kesif',
        '/on\s*bir\s*ay/iu' => '9-11-ay-kesif',
        
        // 12-24 ay grubu
        '/12\s*ay/iu' => '12-24-ay-gecis',
        '/1\s*yaş/iu' => '12-24-ay-gecis',
        '/bir\s*yaş/iu' => '12-24-ay-gecis',
        '/on\s*iki\s*ay/iu' => '12-24-ay-gecis',
        '/18\s*ay/iu' => '12-24-ay-gecis',
        
        // 2+ yaş grubu
        '/2\s*yaş/iu' => '2-yas-ve-uzeri',
        '/3\s*yaş/iu' => '2-yas-ve-uzeri',
        '/4\s*yaş/iu' => '2-yas-ve-uzeri',
        '/5\s*yaş/iu' => '2-yas-ve-uzeri',
        '/iki\s*yaş/iu' => '2-yas-ve-uzeri',
        '/üç\s*yaş/iu' => '2-yas-ve-uzeri',
        '/dört\s*yaş/iu' => '2-yas-ve-uzeri',
        '/beş\s*yaş/iu' => '2-yas-ve-uzeri',
        
        // Generic patterns
        '/bebekler\s+için/iu' => '6-8-ay-baslangic',
        '/ek\s+gıda/iu' => '6-8-ay-baslangic',
        '/çocuklar\s+için/iu' => '2-yas-ve-uzeri',
    ];
    
    /**
     * Map age from title and content to taxonomy slug
     * 
     * @param string $title Post title
     * @param string $content Post content
     * @return string|null Taxonomy slug or null if not found
     */
    public function map($title, $content) {
        $text = $title . ' ' . $content;
        
        // Try each pattern
        foreach ($this->mappings as $pattern => $slug) {
            if (preg_match($pattern, $text)) {
                return $slug;
            }
        }
        
        // Default fallback - if no match found
        return null;
    }
    
    /**
     * Get age group term ID from slug
     * 
     * @param string $slug Taxonomy slug
     * @return int|null Term ID or null
     */
    public function getTermId($slug) {
        if (empty($slug)) {
            return null;
        }
        
        $term = get_term_by('slug', $slug, 'age-group');
        
        if ($term && !is_wp_error($term)) {
            return $term->term_id;
        }
        
        return null;
    }
    
    /**
     * Assign age group to recipe post
     * 
     * @param int $postId Recipe post ID
     * @param string $slug Age group slug
     * @return bool Success
     */
    public function assignToPost($postId, $slug) {
        $termId = $this->getTermId($slug);
        
        if (!$termId) {
            return false;
        }
        
        $result = wp_set_post_terms($postId, [$termId], 'age-group');
        
        return !is_wp_error($result);
    }
    
    /**
     * Extract all age mentions from text for logging
     * 
     * @param string $text Text to search
     * @return array Array of age mentions found
     */
    public function extractAgeMentions($text) {
        $mentions = [];
        
        // Look for month patterns
        if (preg_match_all('/(\d+)\s*ay/iu', $text, $matches)) {
            $mentions = array_merge($mentions, $matches[0]);
        }
        
        // Look for year patterns
        if (preg_match_all('/(\d+)\s*yaş/iu', $text, $matches)) {
            $mentions = array_merge($mentions, $matches[0]);
        }
        
        return array_unique($mentions);
    }
}

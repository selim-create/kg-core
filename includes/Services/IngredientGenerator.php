<?php
namespace KG_Core\Services;

class IngredientGenerator {
    private $ai_service;
    private $image_service;
    
    public function __construct() {
        $this->ai_service = new AIService();
        $this->image_service = new ImageService();
    }
    
    /**
     * Create an ingredient post with AI-generated content
     * 
     * @param string $ingredient_name Name of the ingredient
     * @return int|WP_Error Post ID or error
     */
    public function create($ingredient_name) {
        // Check if ingredient already exists
        $existing = get_page_by_title($ingredient_name, OBJECT, 'ingredient');
        if ($existing) {
            return new \WP_Error('already_exists', 'Malzeme zaten mevcut: ' . $ingredient_name);
        }
        
        // Generate content with AI
        $ai_data = $this->ai_service->generateIngredientContent($ingredient_name);
        
        if (is_wp_error($ai_data)) {
            return $ai_data;
        }
        
        // Create post
        $post_data = [
            'post_title' => $ai_data['title'],
            'post_content' => $ai_data['content'],
            'post_excerpt' => $ai_data['excerpt'],
            'post_type' => 'ingredient',
            'post_status' => 'draft', // Draft for manual review
            'post_author' => $this->getAuthorId()
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Save meta fields
        $this->saveMetaFields($post_id, $ai_data);
        
        // Attach image (always try to attach if configured)
        $this->attachImage($post_id, $ai_data);
        
        // Assign allergens
        if (!empty($ai_data['allergens']) && is_array($ai_data['allergens'])) {
            $this->assignAllergens($post_id, $ai_data['allergens']);
        }
        
        return $post_id;
    }
    
    /**
     * Save meta fields for ingredient
     * 
     * @param int $post_id Post ID
     * @param array $data AI-generated data
     */
    private function saveMetaFields($post_id, $data) {
        // Basic info
        if (isset($data['start_age'])) {
            update_post_meta($post_id, '_kg_start_age', intval($data['start_age']));
        }
        
        if (isset($data['category'])) {
            update_post_meta($post_id, '_kg_category', sanitize_text_field($data['category']));
        }
        
        if (isset($data['benefits'])) {
            update_post_meta($post_id, '_kg_benefits', wp_kses_post($data['benefits']));
        }
        
        if (isset($data['allergy_risk'])) {
            update_post_meta($post_id, '_kg_allergy_risk', sanitize_text_field($data['allergy_risk']));
        }
        
        if (isset($data['season'])) {
            update_post_meta($post_id, '_kg_season', sanitize_text_field($data['season']));
        }
        
        if (isset($data['storage_tips'])) {
            update_post_meta($post_id, '_kg_storage_tips', sanitize_textarea_field($data['storage_tips']));
        }
        
        if (isset($data['selection_tips'])) {
            update_post_meta($post_id, '_kg_selection_tips', sanitize_textarea_field($data['selection_tips']));
        }
        
        if (isset($data['pro_tips'])) {
            update_post_meta($post_id, '_kg_pro_tips', sanitize_textarea_field($data['pro_tips']));
        }
        
        if (isset($data['preparation_tips'])) {
            update_post_meta($post_id, '_kg_preparation_tips', sanitize_textarea_field($data['preparation_tips']));
        }
        
        // Preparation methods
        if (isset($data['prep_methods']) && is_array($data['prep_methods'])) {
            update_post_meta($post_id, '_kg_prep_methods', array_map('sanitize_text_field', $data['prep_methods']));
        }
        
        // Preparation by age (JSON array)
        if (isset($data['prep_by_age']) && is_array($data['prep_by_age'])) {
            $sanitized_prep_by_age = [];
            foreach ($data['prep_by_age'] as $item) {
                if (isset($item['age']) && isset($item['method']) && isset($item['text'])) {
                    $sanitized_prep_by_age[] = [
                        'age' => sanitize_text_field($item['age']),
                        'method' => sanitize_text_field($item['method']),
                        'text' => sanitize_textarea_field($item['text'])
                    ];
                }
            }
            update_post_meta($post_id, '_kg_prep_by_age', $sanitized_prep_by_age);
        }
        
        // Pairings (JSON array)
        if (isset($data['pairings']) && is_array($data['pairings'])) {
            $sanitized_pairings = [];
            foreach ($data['pairings'] as $pairing) {
                if (isset($pairing['emoji']) && isset($pairing['name'])) {
                    $sanitized_pairings[] = [
                        'emoji' => sanitize_text_field($pairing['emoji']),
                        'name' => sanitize_text_field($pairing['name'])
                    ];
                }
            }
            update_post_meta($post_id, '_kg_pairings', $sanitized_pairings);
        }
        
        // Nutrition
        if (isset($data['nutrition']) && is_array($data['nutrition'])) {
            $nutrition = $data['nutrition'];
            
            if (isset($nutrition['calories'])) {
                update_post_meta($post_id, '_kg_calories', sanitize_text_field($nutrition['calories']));
            }
            if (isset($nutrition['protein'])) {
                update_post_meta($post_id, '_kg_protein', sanitize_text_field($nutrition['protein']));
            }
            if (isset($nutrition['carbs'])) {
                update_post_meta($post_id, '_kg_carbs', sanitize_text_field($nutrition['carbs']));
            }
            if (isset($nutrition['fat'])) {
                update_post_meta($post_id, '_kg_fat', sanitize_text_field($nutrition['fat']));
            }
            if (isset($nutrition['fiber'])) {
                update_post_meta($post_id, '_kg_fiber', sanitize_text_field($nutrition['fiber']));
            }
            if (isset($nutrition['vitamins'])) {
                update_post_meta($post_id, '_kg_vitamins', sanitize_text_field($nutrition['vitamins']));
            }
        }
        
        // FAQ
        if (isset($data['faq']) && is_array($data['faq'])) {
            $sanitized_faq = [];
            foreach ($data['faq'] as $item) {
                if (isset($item['question']) && isset($item['answer'])) {
                    $sanitized_faq[] = [
                        'question' => sanitize_text_field($item['question']),
                        'answer' => sanitize_textarea_field($item['answer'])
                    ];
                }
            }
            update_post_meta($post_id, '_kg_faq', $sanitized_faq);
        }
        
        // SEO Meta (RankMath and Yoast)
        if (!empty($data['seo'])) {
            $this->saveSeoMeta($post_id, $data['seo']);
        }
    }
    
    /**
     * Save SEO meta fields for RankMath and Yoast
     * 
     * @param int $post_id Post ID
     * @param array $seo_data SEO data from AI
     */
    private function saveSeoMeta($post_id, $seo_data) {
        if (empty($seo_data)) {
            return;
        }
        
        // RankMath SEO meta
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_data['title']));
        }
        
        if (!empty($seo_data['description'])) {
            update_post_meta($post_id, 'rank_math_description', sanitize_text_field($seo_data['description']));
        }
        
        if (!empty($seo_data['focus_keyword'])) {
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($seo_data['focus_keyword']));
        }
        
        // Yoast SEO meta (as fallback/alternative)
        if (!empty($seo_data['title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($seo_data['title']));
        }
        
        if (!empty($seo_data['description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($seo_data['description']));
        }
        
        if (!empty($seo_data['focus_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($seo_data['focus_keyword']));
        }
    }
    
    /**
     * Attach featured image to ingredient post
     * 
     * @param int $post_id Post ID
     * @param array $data AI-generated data
     */
    private function attachImage($post_id, $data) {
        // Use ingredient name instead of image_search_query
        $ingredient_name = $data['title'] ?? get_the_title($post_id);
        
        // Generate image using new system
        $image_data = $this->image_service->generateImage($ingredient_name);
        
        if ($image_data === null) {
            error_log("KG Core: No image generated for: {$ingredient_name}");
            return;
        }
        
        // Generate filename
        $filename = sanitize_title($ingredient_name) . '.png';
        
        // Download to media library
        $attachment_id = $this->image_service->downloadToMediaLibrary($image_data['url'], $filename);
        
        if (is_wp_error($attachment_id)) {
            error_log('KG Core Image Download Error: ' . $attachment_id->get_error_message());
            return;
        }
        
        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);
        
        // Save source information as post meta
        if (isset($image_data['source'])) {
            update_post_meta($post_id, '_kg_image_source', $image_data['source']);
        }
        if (isset($image_data['credit'])) {
            update_post_meta($post_id, '_kg_image_credit', $image_data['credit']);
        }
        if (isset($image_data['credit_url'])) {
            update_post_meta($post_id, '_kg_image_credit_url', $image_data['credit_url']);
        }
    }
    
    /**
     * Assign allergen taxonomies to ingredient
     * 
     * @param int $post_id Post ID
     * @param array $allergens Array of allergen names
     */
    private function assignAllergens($post_id, $allergens) {
        $term_ids = [];
        
        foreach ($allergens as $allergen_name) {
            $allergen_name = sanitize_text_field($allergen_name);
            
            // Check if term exists
            $term = get_term_by('name', $allergen_name, 'allergen');
            
            if (!$term) {
                // Create new term if it doesn't exist
                $result = wp_insert_term($allergen_name, 'allergen');
                if (!is_wp_error($result)) {
                    $term_ids[] = $result['term_id'];
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }
        
        if (!empty($term_ids)) {
            wp_set_post_terms($post_id, $term_ids, 'allergen');
        }
    }
    
    /**
     * Get author ID for creating posts
     * Handles cron context where get_current_user_id() returns 0
     * 
     * @return int User ID
     */
    private function getAuthorId() {
        $user_id = get_current_user_id();
        
        // If called from cron (no user context), use first admin user
        if ($user_id === 0) {
            $admins = get_users(['role' => 'administrator', 'number' => 1]);
            if (!empty($admins)) {
                $user_id = $admins[0]->ID;
            } else {
                $user_id = 1; // Fallback to user ID 1
            }
        }
        
        return $user_id;
    }
}

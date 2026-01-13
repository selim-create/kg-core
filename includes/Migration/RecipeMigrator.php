<?php
namespace KG_Core\Migration;

/**
 * RecipeMigrator - Main orchestrator for recipe migration
 */
class RecipeMigrator {
    
    private $content_parser;
    private $ingredient_parser;
    private $age_group_mapper;
    private $ai_enhancer;
    private $seo_handler;
    private $logger;
    
    public function __construct() {
        $this->content_parser = new ContentParser();
        $this->ingredient_parser = new IngredientParser();
        $this->age_group_mapper = new AgeGroupMapper();
        $this->ai_enhancer = new AIEnhancer();
        $this->seo_handler = new SEOHandler();
        $this->logger = new MigrationLogger();
    }
    
    /**
     * Migrate a single blog post to recipe
     * 
     * @param int $postId Blog post ID
     * @return int|WP_Error New recipe post ID or error
     */
    public function migrate($postId) {
        // Start logging
        $this->logger->startMigration($postId);
        
        try {
            // 1. Get blog post
            $post = get_post($postId);
            if (!$post || $post->post_type !== 'post') {
                throw new \Exception("Post {$postId} not found or not a blog post");
            }
            
            $this->logger->log("Processing post {$postId}: {$post->post_title}");
            
            // 2. Parse content
            $parsedData = $this->content_parser->parse($post->post_content, $post->post_title);
            $parsedData['title'] = $post->post_title;
            $parsedData['excerpt'] = $post->post_excerpt;
            
            $this->logger->log("Parsed " . count($parsedData['ingredients']) . " ingredients and " . count($parsedData['instructions']) . " instructions");
            
            // 3. Parse and standardize ingredients
            $standardizedIngredients = [];
            foreach ($parsedData['ingredients'] as $rawIngredient) {
                $parsed = $this->ingredient_parser->parse($rawIngredient);
                
                // Try to match with existing ingredient
                $ingredientId = $this->ingredient_parser->matchIngredient($parsed['name']);
                
                // If not found, create new ingredient (as draft)
                if (!$ingredientId) {
                    $ingredientId = $this->ingredient_parser->createIngredient($parsed['name']);
                    $this->logger->log("Created new ingredient: {$parsed['name']} (ID: {$ingredientId})");
                }
                
                $parsed['ingredient_id'] = $ingredientId;
                $standardizedIngredients[] = $parsed;
            }
            
            $parsedData['ingredients'] = $standardizedIngredients;
            
            // 4. Determine age group
            $ageGroupSlug = $this->age_group_mapper->map($post->post_title, $post->post_content);
            if ($ageGroupSlug) {
                $this->logger->log("Mapped to age group: {$ageGroupSlug}");
            } else {
                $this->logger->log("Could not determine age group", 'warning');
            }
            
            // 5. Enhance with AI
            $this->logger->log("Calling AI for enhancement...");
            sleep(1); // Rate limiting
            $aiData = $this->ai_enhancer->enhance($parsedData);
            $this->logger->log("AI enhancement completed");
            
            // 6. Create recipe post
            $recipeId = $this->createRecipePost($post, $parsedData, $aiData, $ageGroupSlug);
            
            if (is_wp_error($recipeId)) {
                throw new \Exception($recipeId->get_error_message());
            }
            
            $this->logger->log("Created recipe post ID: {$recipeId}");
            
            // 7. Copy featured image
            $this->copyFeaturedImage($postId, $recipeId);
            
            // 8. Update SEO
            $this->seo_handler->updateSEO($recipeId, array_merge($parsedData, $aiData));
            $this->logger->log("SEO metadata updated");
            
            // 9. Set original blog post to draft
            wp_update_post([
                'ID' => $postId,
                'post_status' => 'draft'
            ]);
            $this->logger->log("Original blog post set to draft");
            
            // 10. Log success
            $metadata = [
                'ingredients_count' => count($standardizedIngredients),
                'instructions_count' => count($parsedData['instructions']),
                'age_group' => $ageGroupSlug,
                'has_expert_note' => !empty($parsedData['expert_note']),
                'ai_enhanced' => true
            ];
            
            $this->logger->success($postId, $recipeId, $metadata);
            
            return $recipeId;
            
        } catch (\Exception $e) {
            $this->logger->error($postId, $e->getMessage());
            return new \WP_Error('migration_error', $e->getMessage());
        }
    }
    
    /**
     * Create recipe post from parsed data
     * 
     * @param WP_Post $originalPost Original blog post
     * @param array $parsedData Parsed content data
     * @param array $aiData AI-enhanced data
     * @param string|null $ageGroupSlug Age group slug
     * @return int|WP_Error Recipe post ID or error
     */
    private function createRecipePost($originalPost, $parsedData, $aiData, $ageGroupSlug) {
        // Prepare ingredients for meta
        $ingredients = [];
        foreach ($parsedData['ingredients'] as $ing) {
            $ingredients[] = [
                'amount' => $ing['quantity'],
                'unit' => $ing['unit'],
                'name' => $ing['name'],
                'ingredient_id' => $ing['ingredient_id']
            ];
        }
        
        // Prepare instructions for meta
        $instructions = [];
        foreach ($parsedData['instructions'] as $idx => $text) {
            $instructions[] = [
                'id' => $idx + 1,
                'title' => '',
                'text' => $text,
                'tip' => ''
            ];
        }
        
        // Prepare substitutes from AI
        $substitutes = [];
        if (!empty($aiData['substitutes'])) {
            foreach ($aiData['substitutes'] as $sub) {
                if (!empty($sub['original']) && !empty($sub['substitute'])) {
                    $substitutes[] = [
                        'original' => $sub['original'],
                        'substitute' => $sub['substitute'],
                        'note' => isset($sub['note']) ? $sub['note'] : ''
                    ];
                }
            }
        }
        
        // Create post
        $postData = [
            'post_title' => $originalPost->post_title,
            'post_content' => isset($aiData['description']) ? $aiData['description'] : $this->generateDescription($parsedData),
            'post_excerpt' => $originalPost->post_excerpt,
            'post_type' => 'recipe',
            'post_status' => 'draft', // Create as draft for review
            'post_author' => $originalPost->post_author,
            'post_date' => $originalPost->post_date,
        ];
        
        $recipeId = wp_insert_post($postData);
        
        if (is_wp_error($recipeId)) {
            return $recipeId;
        }
        
        // Save meta fields
        update_post_meta($recipeId, '_kg_prep_time', isset($aiData['prep_time']) ? $aiData['prep_time'] : '');
        update_post_meta($recipeId, '_kg_ingredients', $ingredients);
        update_post_meta($recipeId, '_kg_instructions', $instructions);
        update_post_meta($recipeId, '_kg_substitutes', $substitutes);
        update_post_meta($recipeId, '_kg_calories', isset($aiData['calories']) ? $aiData['calories'] : '');
        update_post_meta($recipeId, '_kg_protein', isset($aiData['protein']) ? $aiData['protein'] : '');
        update_post_meta($recipeId, '_kg_fiber', isset($aiData['fiber']) ? $aiData['fiber'] : '');
        update_post_meta($recipeId, '_kg_vitamins', isset($aiData['vitamins']) ? $aiData['vitamins'] : '');
        update_post_meta($recipeId, '_kg_video_url', isset($parsedData['video_url']) ? $parsedData['video_url'] : '');
        update_post_meta($recipeId, '_kg_expert_name', isset($parsedData['expert_name']) ? $parsedData['expert_name'] : '');
        update_post_meta($recipeId, '_kg_expert_title', isset($parsedData['expert_title']) ? $parsedData['expert_title'] : '');
        update_post_meta($recipeId, '_kg_expert_note', isset($parsedData['expert_note']) ? $parsedData['expert_note'] : '');
        
        // Uzman notu varsa "Uzman Onaylı" checkbox'ını seç
        $hasExpertNote = !empty($parsedData['expert_note']) && !empty($parsedData['expert_name']);
        update_post_meta($recipeId, '_kg_expert_approved', $hasExpertNote ? '1' : '0');
        
        // Özel notları da kaydet (Süt:, Not: vb.)
        if (!empty($parsedData['special_notes'])) {
            update_post_meta($recipeId, '_kg_special_notes', $parsedData['special_notes']);
        }
        
        // Cross-sell data
        if (!empty($aiData['main_ingredient'])) {
            $crossSellData = [
                'mode' => 'manual',
                'url' => isset($aiData['cross_sell_url']) ? $aiData['cross_sell_url'] : '',
                'title' => isset($aiData['cross_sell_title']) ? $aiData['cross_sell_title'] : '',
                'image' => '',
                'ingredient' => $aiData['main_ingredient'],
                'tariften_id' => ''
            ];
            update_post_meta($recipeId, '_kg_cross_sell', $crossSellData);
        }
        
        // Assign taxonomies
        if ($ageGroupSlug) {
            $this->age_group_mapper->assignToPost($recipeId, $ageGroupSlug);
        }
        
        if (!empty($aiData['allergens'])) {
            $allergenIds = $this->ai_enhancer->mapAllergens($aiData['allergens']);
            if (!empty($allergenIds)) {
                wp_set_post_terms($recipeId, $allergenIds, 'allergen');
            }
        }
        
        if (!empty($aiData['diet_types'])) {
            $dietIds = $this->ai_enhancer->mapDietTypes($aiData['diet_types']);
            if (!empty($dietIds)) {
                wp_set_post_terms($recipeId, $dietIds, 'diet-type');
            }
        }
        
        if (!empty($aiData['meal_types'])) {
            $mealIds = $this->ai_enhancer->mapMealTypes($aiData['meal_types']);
            if (!empty($mealIds)) {
                wp_set_post_terms($recipeId, $mealIds, 'meal-type');
            }
        }
        
        // Store reference to original post
        update_post_meta($recipeId, '_kg_migrated_from', $originalPost->ID);
        update_post_meta($originalPost->ID, '_kg_migrated_to', $recipeId);
        
        return $recipeId;
    }
    
    /**
     * Generate a simple description when AI doesn't provide one
     * 
     * @param array $parsedData Parsed recipe data
     * @return string Generated description
     */
    private function generateDescription($parsedData) {
        $ingredientCount = count($parsedData['ingredients']);
        $title = isset($parsedData['title']) ? $parsedData['title'] : 'Bu tarif';
        
        return "Bu {$title} tarifi {$ingredientCount} malzeme ile hazırlanır. Aşağıda detaylı malzeme listesi ve hazırlanış adımlarını bulabilirsiniz.";
    }
    
    /**
     * Copy featured image from blog post to recipe
     * 
     * @param int $sourceId Source post ID
     * @param int $targetId Target post ID
     */
    private function copyFeaturedImage($sourceId, $targetId) {
        $thumbnailId = get_post_thumbnail_id($sourceId);
        
        if ($thumbnailId) {
            set_post_thumbnail($targetId, $thumbnailId);
            $this->logger->log("Featured image copied");
        }
    }
    
    /**
     * Migrate a batch of posts
     * 
     * @param int $limit Number of posts to migrate
     * @return array Results with success and error counts
     */
    public function migrateBatch($limit = 10) {
        $recipeIds = $this->getRecipeIds();
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        $processed = 0;
        
        foreach ($recipeIds as $postId) {
            if ($processed >= $limit) {
                break;
            }
            
            // Skip if already migrated
            if ($this->logger->isMigrated($postId)) {
                $results['skipped']++;
                continue;
            }
            
            $result = $this->migrate($postId);
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $postId,
                    'error' => $result->get_error_message()
                ];
            } else {
                $results['success']++;
            }
            
            $processed++;
        }
        
        return $results;
    }
    
    /**
     * Migrate all posts
     * 
     * @return array Results
     */
    public function migrateAll() {
        $recipeIds = $this->getRecipeIds();
        $total = count($recipeIds);
        
        $this->logger->log("Starting migration of all {$total} posts");
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        foreach ($recipeIds as $postId) {
            // Skip if already migrated
            if ($this->logger->isMigrated($postId)) {
                $results['skipped']++;
                continue;
            }
            
            $result = $this->migrate($postId);
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $postId,
                    'error' => $result->get_error_message()
                ];
            } else {
                $results['success']++;
            }
            
            // Rate limiting - wait 1 second between migrations
            sleep(1);
        }
        
        $this->logger->log("Migration complete: {$results['success']} success, {$results['failed']} failed, {$results['skipped']} skipped");
        
        return $results;
    }
    
    /**
     * Get recipe IDs from JSON file
     * 
     * @return array Recipe post IDs
     */
    public function getRecipeIds() {
        $jsonFile = KG_CORE_PATH . 'data/recipe-ids.json';
        
        if (!file_exists($jsonFile)) {
            return [];
        }
        
        $jsonData = file_get_contents($jsonFile);
        $data = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['recipe_post_ids'])) {
            return [];
        }
        
        return $data['recipe_post_ids'];
    }
}

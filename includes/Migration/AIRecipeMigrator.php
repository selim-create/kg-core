<?php
namespace KG_Core\Migration;

/**
 * AIRecipeMigrator - AI-First approach to migrate blog posts to recipes
 * Uses OpenAI GPT-4 to parse and structure entire blog content in one call
 */
class AIRecipeMigrator {
    
    /**
     * Recipe IDs JSON file path (relative to plugin directory)
     */
    const RECIPE_IDS_FILE = 'data/recipe-ids.json';
    
    /**
     * Minutes per hour constant for time conversion
     */
    const MINUTES_PER_HOUR = 60;
    
    private $api_key;
    private $model;
    private $logger;
    
    public function __construct() {
        $this->api_key = get_option('kg_openai_api_key', '') ?: get_option('kg_ai_api_key', '');
        $this->model = get_option('kg_ai_model', 'gpt-4o');
        $this->logger = new MigrationLogger();
    }
    
    /**
     * Migrate a single blog post using AI
     * 
     * @param int $postId Blog post ID
     * @return int|WP_Error New recipe post ID or error
     */
    public function migrate($postId) {
        // 1. Duplicate kontrolü - AYNI POST ID İÇİN SADECE BİR KEZ OLUŞTUR
        $existingRecipe = $this->getExistingRecipe($postId);
        if ($existingRecipe) {
            $this->logger->log("Post {$postId} already migrated to recipe {$existingRecipe}. Skipping.");
            return $existingRecipe; // Mevcut recipe ID'yi döndür
        }
        
        // 2. Blog post'u al
        $post = get_post($postId);
        if (!$post || $post->post_type !== 'post') {
            return new \WP_Error('invalid_post', "Post {$postId} not found or not a blog post");
        }
        
        // 3. AI ile parse et
        $this->logger->log("Calling OpenAI for post {$postId}: {$post->post_title}");
        
        $aiData = $this->parseWithAI($post);
        
        if (is_wp_error($aiData)) {
            $this->logger->error($postId, $aiData->get_error_message());
            return $aiData;
        }
        
        // 4. Recipe oluştur
        $recipeId = $this->createRecipe($post, $aiData);
        
        if (is_wp_error($recipeId)) {
            $this->logger->error($postId, $recipeId->get_error_message());
            return $recipeId;
        }
        
        // 5. Orijinal post'u draft yap
        wp_update_post(['ID' => $postId, 'post_status' => 'draft']);
        
        // 6. Log success
        $this->logger->success($postId, $recipeId, [
            'ingredients_count' => count($aiData['ingredients'] ?? []),
            'instructions_count' => count($aiData['instructions'] ?? []),
            'has_expert' => !empty($aiData['expert']['name'] ?? '')
        ]);
        
        return $recipeId;
    }
    
    /**
     * Check if post already migrated
     * 
     * @param int $postId Blog post ID
     * @return int|null Existing recipe ID or null
     */
    private function getExistingRecipe($postId) {
        $recipes = get_posts([
            'post_type' => 'recipe',
            'meta_key' => '_kg_migrated_from',
            'meta_value' => $postId,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        return !empty($recipes) ? $recipes[0]->ID : null;
    }
    
    /**
     * Parse blog content with OpenAI
     * 
     * @param WP_Post $post WordPress post object
     * @return array|WP_Error Parsed data or error
     */
    private function parseWithAI($post) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }
        
        $blogContent = $post->post_content;
        $blogTitle = $post->post_title;
        
        // HTML'i temizle ama yapıyı koru
        $cleanContent = wp_strip_all_tags($blogContent);
        $cleanContent = html_entity_decode($cleanContent, ENT_QUOTES, 'UTF-8');
        
        $prompt = $this->buildPrompt($blogTitle, $cleanContent);
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Sen bir bebek beslenmesi uzmanı ve tarif editörüsün. Blog yazılarını yapılandırılmış tarif formatına dönüştürüyorsun. Her zaman geçerli JSON döndür. Türkçe yanıt ver.'
                    ],
                    [
                        'role' => 'user', 
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 4000
            ]),
            'timeout' => 120
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['choices'][0]['message']['content'])) {
            return new \WP_Error('ai_error', 'OpenAI empty response');
        }
        
        $jsonString = $body['choices'][0]['message']['content'];
        
        // JSON bloklarını temizle
        $jsonString = preg_replace('/```json\s*/', '', $jsonString);
        $jsonString = preg_replace('/```\s*/', '', $jsonString);
        $jsonString = trim($jsonString);
        
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_error', 'Invalid JSON: ' . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Build comprehensive prompt for OpenAI
     * 
     * @param string $title Blog post title
     * @param string $content Blog post content
     * @return string AI prompt
     */
    private function buildPrompt($title, $content) {
        return "
Aşağıdaki blog yazısını analiz et ve TÜM bilgileri JSON formatında çıkar.

⚠️ ÖNEMLİ KURALLAR:
1. HİÇBİR VERİYİ KAYBETME - Blog yazısındaki tüm bilgiler bir yere yerleştirilmeli
2. Malzeme adları SADECE malzeme ismi olsun (Brokoli, Su, Zeytinyağı, File Badem)
3. Parantez içi açıklamalar malzemenin \"note\" alanına gitsin
4. Hazırlanış adımlarındaki parantez içi ipuçları \"tip\" alanına gitsin
5. Uzman notunu TAMAMEN al, hiç kesme
6. \"Süt:\", \"Not:\", \"İpucu:\", \"Uyarı:\" ile başlayan TÜM bölümler \"special_notes\"a eklensin
7. \"İlginizi çekebilecek\" veya reklam içeriklerini ATLA
8. Beslenme değerlerini MUTLAKA doldur - tahmin ederek de olsa, boş bırakma
9. Hazırlama süresini mutlaka bul veya tahmin et - boş bırakma
10. İkame malzemeler için alerjenleri göz önünde bulundur

📝 BLOG BAŞLIĞI:
{$title}

📝 BLOG İÇERİĞİ:
{$content}

📋 JSON FORMATI (Bu yapıyı AYNEN kullan):
{
  \"description\": \"Bu tarif için 1-2 paragraf SEO uyumlu açıklama. Tarifin faydaları, hangi yaş grubuna uygun olduğu. Malzeme listesi veya adımları buraya YAZMA.\",
  
  \"ingredients\": [
    {
      \"amount\": \"3\",
      \"unit\": \"çiçek\",
      \"name\": \"Brokoli\",
      \"note\": \"\"
    },
    {
      \"amount\": \"1/4\",
      \"unit\": \"adet\",
      \"name\": \"Kuru Soğan\",
      \"note\": \"küçük\"
    },
    {
      \"amount\": \"\",
      \"unit\": \"\",
      \"name\": \"File Badem\",
      \"note\": \"yetişkinler ve büyük yaş grubu çocuklar için\"
    }
  ],
  
  \"instructions\": [
    {
      \"step\": 1,
      \"title\": \"\",
      \"text\": \"Soğan tencerede zeytinyağında sote edilir.\",
      \"tip\": \"\"
    },
    {
      \"step\": 2,
      \"title\": \"\",
      \"text\": \"Süt ilave edilerek 5 dk daha kaynatılır.\",
      \"tip\": \"Formül mama ya da anne sütü ilave edecekseniz çorba piştikten sonra ekleyin. Bu iki süt türü pişirilmemelidir.\"
    }
  ],
  
  \"substitutes\": [
    {
      \"original\": \"İnek sütü\",
      \"substitute\": \"Formül mama\",
      \"note\": \"1 yaş altı bebekler için\"
    },
    {
      \"original\": \"İnek sütü\",
      \"substitute\": \"Badem sütü\",
      \"note\": \"Laktozsuz diyet için\"
    }
  ],
  
  \"expert\": {
    \"name\": \"Enver Mahir Gülcan\",
    \"title\": \"Doç.Dr.\",
    \"note\": \"UZMAN NOTUNUN TAMAMI BURAYA - KESMEYİN\"
  },
  
  \"special_notes\": \"Süt: Çocuğunuzun inek sütü alerjisi yoksa... (TAM METİN)\\n\\nNot: İçine ev yapımı... (TAM METİN)\",
  
  \"nutrition\": {
    \"calories\": \"150 kcal\",
    \"protein\": \"5 g\",
    \"fiber\": \"3 g\",
    \"vitamins\": \"A, C, K\"
  },
  
  \"prep_time\": \"25 dakika\",
  
  \"age_group\": \"9-11-ay-kesif\",
  
  \"allergens\": [\"süt\"],
  
  \"diet_types\": [\"vejetaryen\"],
  
  \"meal_types\": [\"öğle yemeği\", \"akşam yemeği\"],
  
  \"main_ingredient\": \"Brokoli\",
  
  \"video_url\": \"\"
}

BİRİM SEÇENEKLERİ (sadece bunları kullan):
- adet, su bardağı, çay bardağı, yemek kaşığı, tatlı kaşığı, çay kaşığı
- gram, ml, kg, litre
- tutam, avuç, ölçek
- çiçek, dal, dilim, diş, demet, yaprak

YAŞ GRUBU SEÇENEKLERİ:
- 6-8-ay-baslangic (5-8 ay arası)
- 9-11-ay-kesif (9-11 ay arası)
- 12-24-ay-gecis (1-2 yaş arası)
- 2-yas-ve-uzeri (2 yaş ve üzeri)

BESİN DEĞERLERİ KURALLARI:
- Calories: \"XXX kcal\" formatında
- Protein: \"XX g\" formatında
- Fiber: \"XX g\" formatında
- Vitamins: \"A, C, K\" gibi virgülle ayrılmış liste
- Boş bırakma! Tahmin et, yaklaşık değer ver.

HAZIRLIK SÜRESİ KURALLARI:
- \"XX dakika\" veya \"XX saat\" formatında
- Blog yazısında geçiyorsa onu kullan
- Geçmiyorsa tarifin karmaşıklığına göre tahmin et
- Boş bırakma!

İKAME MALZEMELER KURALLARI:
- Alerjenli malzemeler için mutlaka ikame öner
- Süt, yumurta, glüten içeren malzemeler için alternatifleri ekle
- Her ikame için hangi durumda kullanılacağını \"note\" alanında belirt

Sadece JSON döndür, başka açıklama ekleme.
";
    }
    
    /**
     * Create recipe post from AI data
     * 
     * @param WP_Post $originalPost Original blog post
     * @param array $aiData Parsed AI data
     * @return int|WP_Error Recipe post ID or error
     */
    private function createRecipe($originalPost, $aiData) {
        // Post oluştur
        $recipeId = wp_insert_post([
            'post_title' => $originalPost->post_title,
            'post_content' => isset($aiData['description']) ? $aiData['description'] : '',
            'post_excerpt' => $originalPost->post_excerpt,
            'post_type' => 'recipe',
            'post_status' => 'draft',
            'post_author' => $originalPost->post_author,
            'post_date' => $originalPost->post_date,
        ]);
        
        if (is_wp_error($recipeId)) {
            return $recipeId;
        }
        
        // Meta fields
        $prepTime = $this->extractPrepTime($aiData, $originalPost->post_content);
        update_post_meta($recipeId, '_kg_prep_time', $prepTime);
        update_post_meta($recipeId, '_kg_is_featured', '0');
        
        // Ingredients
        $ingredients = [];
        if (!empty($aiData['ingredients'])) {
            foreach ($aiData['ingredients'] as $ing) {
                $ingredientId = $this->findOrCreateIngredient($ing['name'] ?? '');
                $ingredients[] = [
                    'amount' => $ing['amount'] ?? '',
                    'unit' => $ing['unit'] ?? 'adet',
                    'name' => $ing['name'] ?? '',
                    'note' => $ing['note'] ?? '',
                    'ingredient_id' => $ingredientId
                ];
            }
        }
        update_post_meta($recipeId, '_kg_ingredients', $ingredients);
        
        // Instructions
        $instructions = [];
        if (!empty($aiData['instructions'])) {
            foreach ($aiData['instructions'] as $idx => $inst) {
                $instructions[] = [
                    'id' => $inst['step'] ?? ($idx + 1),
                    'title' => $inst['title'] ?? '',
                    'text' => $inst['text'] ?? '',
                    'tip' => $inst['tip'] ?? ''
                ];
            }
        }
        update_post_meta($recipeId, '_kg_instructions', $instructions);
        
        // Substitutes
        $substitutes = [];
        if (!empty($aiData['substitutes'])) {
            foreach ($aiData['substitutes'] as $sub) {
                $substitutes[] = [
                    'original' => $sub['original'] ?? '',
                    'substitute' => $sub['substitute'] ?? '',
                    'note' => $sub['note'] ?? ''
                ];
            }
        }
        update_post_meta($recipeId, '_kg_substitutes', $substitutes);
        
        // Nutrition
        $nutrition = $this->getNutritionWithFallback($aiData, $originalPost);
        update_post_meta($recipeId, '_kg_calories', $nutrition['calories']);
        update_post_meta($recipeId, '_kg_protein', $nutrition['protein']);
        update_post_meta($recipeId, '_kg_fiber', $nutrition['fiber']);
        update_post_meta($recipeId, '_kg_vitamins', $nutrition['vitamins']);
        
        // Expert
        $expertName = $aiData['expert']['name'] ?? '';
        $expertTitle = $aiData['expert']['title'] ?? '';
        $expertNote = $aiData['expert']['note'] ?? '';
        update_post_meta($recipeId, '_kg_expert_name', $expertName);
        update_post_meta($recipeId, '_kg_expert_title', $expertTitle);
        update_post_meta($recipeId, '_kg_expert_note', $expertNote);
        update_post_meta($recipeId, '_kg_expert_approved', (!empty($expertName) && !empty($expertNote)) ? '1' : '0');
        
        // Special notes
        update_post_meta($recipeId, '_kg_special_notes', $aiData['special_notes'] ?? '');
        
        // Video
        update_post_meta($recipeId, '_kg_video_url', $aiData['video_url'] ?? '');
        
        // Cross-sell
        $mainIngredient = $aiData['main_ingredient'] ?? '';
        if ($mainIngredient) {
            $crossSellData = [
                'mode' => 'manual',
                'url' => 'https://www.tariften.com/recipes?q=' . urlencode($mainIngredient),
                'title' => "Artan malzemelerle kendinize harika bir {$mainIngredient} yemeği yapabilirsiniz.",
                'image' => '',
                'ingredient' => $mainIngredient,
                'tariften_id' => ''
            ];
            update_post_meta($recipeId, '_kg_cross_sell', $crossSellData);
        }
        
        // Taxonomies
        if (!empty($aiData['age_group'])) {
            wp_set_object_terms($recipeId, $aiData['age_group'], 'age-group');
        }
        
        if (!empty($aiData['allergens'])) {
            $this->setTaxonomyTerms($recipeId, $aiData['allergens'], 'allergen');
        }
        
        if (!empty($aiData['diet_types'])) {
            $this->setTaxonomyTerms($recipeId, $aiData['diet_types'], 'diet-type');
        }
        
        if (!empty($aiData['meal_types'])) {
            $this->setTaxonomyTerms($recipeId, $aiData['meal_types'], 'meal-type');
        }
        
        // Migration reference
        update_post_meta($recipeId, '_kg_migrated_from', $originalPost->ID);
        update_post_meta($originalPost->ID, '_kg_migrated_to', $recipeId);
        
        // Mark as test migration if in test mode (can be set via option)
        $isTestMode = get_option('kg_migration_test_mode', false);
        if ($isTestMode) {
            update_post_meta($recipeId, '_kg_migrated_test', '1');
        }
        
        // Copy featured image
        $thumbnailId = get_post_thumbnail_id($originalPost->ID);
        if ($thumbnailId) {
            set_post_thumbnail($recipeId, $thumbnailId);
        }
        
        // Generate SEO metadata via CRON
        if (!wp_next_scheduled('kg_generate_recipe_seo', [$recipeId])) {
            wp_schedule_single_event(time() + 15, 'kg_generate_recipe_seo', [$recipeId]);
        }
        
        return $recipeId;
    }
    
    /**
     * Find existing ingredient or create new one
     * 
     * @param string $name Ingredient name
     * @return int|null Ingredient post ID or null
     */
    private function findOrCreateIngredient($name) {
        if (empty($name)) return null;
        
        // Search by exact title using WP_Query
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'ingredient' 
             AND post_title = %s 
             AND post_status IN ('publish', 'draft')
             LIMIT 1",
            $name
        ));
        
        if ($post_id) {
            return (int) $post_id;
        }
        
        // Try similar search
        $similar = get_posts([
            'post_type' => 'ingredient',
            's' => $name,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ]);
        
        if (!empty($similar)) {
            return $similar[0]->ID;
        }
        
        // Don't create a draft ingredient immediately - use queue system instead
        // Schedule ingredient creation via CRON for AI processing
        if (!wp_next_scheduled('kg_generate_ingredient', [$name])) {
            wp_schedule_single_event(time() + 5, 'kg_generate_ingredient', [$name]);
        }
        
        // Return null to indicate ingredient will be created later
        // This prevents empty ingredient posts from being created
        return null;
    }
    
    /**
     * Set taxonomy terms (create if not exist)
     * 
     * @param int $postId Post ID
     * @param array $terms Term names
     * @param string $taxonomy Taxonomy name
     */
    private function setTaxonomyTerms($postId, $terms, $taxonomy) {
        $termIds = [];
        
        foreach ($terms as $termName) {
            $term = term_exists($termName, $taxonomy);
            
            if (!$term) {
                $term = wp_insert_term($termName, $taxonomy);
            }
            
            if (!is_wp_error($term)) {
                $termIds[] = is_array($term) ? $term['term_id'] : $term;
            }
        }
        
        if (!empty($termIds)) {
            wp_set_object_terms($postId, array_map('intval', $termIds), $taxonomy);
        }
    }
    
    /**
     * Get recipe IDs from JSON file
     * 
     * @return array Recipe post IDs
     */
    public function getRecipeIds() {
        $jsonFile = KG_CORE_PATH . self::RECIPE_IDS_FILE;
        
        if (!file_exists($jsonFile)) {
            $this->logger->log("Recipe IDs file not found: {$jsonFile}", 'warning');
            return [];
        }
        
        $data = json_decode(file_get_contents($jsonFile), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log("Invalid JSON in recipe IDs file: " . json_last_error_msg(), 'error');
            return [];
        }
        
        return $data['recipe_post_ids'] ?? [];
    }
    
    /**
     * Migrate batch
     * 
     * @param int $limit Number of posts to migrate
     * @return array Results
     */
    public function migrateBatch($limit = 10) {
        $recipeIds = $this->getRecipeIds();
        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];
        $processed = 0;
        
        foreach ($recipeIds as $postId) {
            if ($processed >= $limit) break;
            
            // Already migrated check
            if ($this->getExistingRecipe($postId)) {
                $results['skipped']++;
                $this->logger->log("Post {$postId} already migrated, skipping.");
                continue;
            }
            
            sleep(2); // Rate limiting for OpenAI
            
            $result = $this->migrate($postId);
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = ['post_id' => $postId, 'error' => $result->get_error_message()];
            } else {
                $results['success']++;
            }
            
            $processed++;
        }
        
        return $results;
    }
    
    /**
     * Migrate all recipes
     * 
     * @return array Results
     */
    public function migrateAll() {
        $recipeIds = $this->getRecipeIds();
        $totalCount = count($recipeIds);
        
        $this->logger->log("Starting migration of all {$totalCount} recipes");
        
        return $this->migrateBatch($totalCount);
    }
    
    /**
     * Extract preparation time with fallback
     * 
     * @param array $aiData AI-parsed data
     * @param string $content Blog post content
     * @return string Preparation time
     */
    private function extractPrepTime($aiData, $content) {
        // First, try AI data
        if (!empty($aiData['prep_time'])) {
            return $aiData['prep_time'];
        }
        
        // Fallback: Try regex extraction
        $cleanContent = wp_strip_all_tags($content);
        
        // Pattern 1: "XX dakika" or "XX dk" - with word boundaries
        if (preg_match('/\b(\d+)\s*(dakika|dk)\b/i', $cleanContent, $matches)) {
            return $matches[1] . ' dakika';
        }
        
        // Pattern 2: "XX saat" - with word boundaries
        if (preg_match('/\b(\d+)\s*saat\b/i', $cleanContent, $matches)) {
            $hours = (int) $matches[1];
            return ($hours * self::MINUTES_PER_HOUR) . ' dakika'; // Convert to minutes
        }
        
        // Pattern 3: "Hazırlama süresi: XX"
        if (preg_match('/hazırlama\s+süresi[:\s]+(\d+)/i', $cleanContent, $matches)) {
            return $matches[1] . ' dakika';
        }
        
        // Default fallback based on recipe complexity (estimate)
        return '20 dakika'; // Default assumption for baby food
    }
    
    /**
     * Get nutrition values with fallback
     * 
     * @param array $aiData AI-parsed data
     * @param WP_Post $post Original post
     * @return array Nutrition values
     */
    private function getNutritionWithFallback($aiData, $post) {
        $nutrition = [
            'calories' => '',
            'protein' => '',
            'fiber' => '',
            'vitamins' => ''
        ];
        
        // Try to get from AI data first
        if (!empty($aiData['nutrition'])) {
            $nutrition['calories'] = $aiData['nutrition']['calories'] ?? '';
            $nutrition['protein'] = $aiData['nutrition']['protein'] ?? '';
            $nutrition['fiber'] = $aiData['nutrition']['fiber'] ?? '';
            $nutrition['vitamins'] = $aiData['nutrition']['vitamins'] ?? '';
        }
        
        // Apply fallback for empty values based on recipe type
        $recipeType = $this->guessRecipeType($post->post_title, $post->post_content);
        $defaults = $this->getDefaultNutritionByType($recipeType);
        
        if (empty($nutrition['calories'])) {
            $nutrition['calories'] = $defaults['calories'];
        }
        if (empty($nutrition['protein'])) {
            $nutrition['protein'] = $defaults['protein'];
        }
        if (empty($nutrition['fiber'])) {
            $nutrition['fiber'] = $defaults['fiber'];
        }
        if (empty($nutrition['vitamins'])) {
            $nutrition['vitamins'] = $defaults['vitamins'];
        }
        
        return $nutrition;
    }
    
    /**
     * Guess recipe type from title and content
     * 
     * @param string $title Recipe title
     * @param string $content Recipe content
     * @return string Recipe type (soup, dessert, main, snack)
     */
    private function guessRecipeType($title, $content) {
        $titleLower = mb_strtolower($title, 'UTF-8');
        
        // Check title first for efficiency
        // Patterns are case-insensitive since we already converted to lowercase
        if (preg_match('/(çorba|soup)/', $titleLower)) {
            return 'soup';
        }
        
        if (preg_match('/(tatlı|muhallebi|puding|kek|kurabiye|brownie|bisküvi)/', $titleLower)) {
            return 'dessert';
        }
        
        if (preg_match('/(atıştırmalık|aperatif|kraker|çubuk)/', $titleLower)) {
            return 'snack';
        }
        
        if (preg_match('/(pilav|makarna|köfte|börek|yemek)/', $titleLower)) {
            return 'main';
        }
        
        if (preg_match('/(püre|püresi|ezme)/', $titleLower)) {
            return 'puree';
        }
        
        // Only check content if title didn't match
        $contentLower = mb_strtolower($content, 'UTF-8');
        
        if (preg_match('/(çorba|soup)/', $contentLower)) {
            return 'soup';
        }
        
        if (preg_match('/(tatlı|muhallebi|puding|kek)/', $contentLower)) {
            return 'dessert';
        }
        
        return 'main'; // Default
    }
    
    /**
     * Get default nutrition values by recipe type
     * 
     * @param string $type Recipe type
     * @return array Default nutrition values
     */
    private function getDefaultNutritionByType($type) {
        $defaults = [
            'soup' => [
                'calories' => '100 kcal',
                'protein' => '4 g',
                'fiber' => '2 g',
                'vitamins' => 'A, C'
            ],
            'dessert' => [
                'calories' => '200 kcal',
                'protein' => '3 g',
                'fiber' => '1 g',
                'vitamins' => 'B, D'
            ],
            'snack' => [
                'calories' => '150 kcal',
                'protein' => '5 g',
                'fiber' => '3 g',
                'vitamins' => 'E, B'
            ],
            'main' => [
                'calories' => '180 kcal',
                'protein' => '8 g',
                'fiber' => '3 g',
                'vitamins' => 'A, B, C'
            ],
            'puree' => [
                'calories' => '80 kcal',
                'protein' => '2 g',
                'fiber' => '2 g',
                'vitamins' => 'A, C'
            ]
        ];
        
        return isset($defaults[$type]) ? $defaults[$type] : $defaults['main'];
    }
    
    /**
     * Clean test migrations
     * Removes recipes marked as test migrations
     * 
     * @return array Cleanup results
     */
    public function cleanTestMigrations() {
        $testRecipes = get_posts([
            'post_type' => 'recipe',
            'meta_key' => '_kg_migrated_test',
            'meta_value' => '1',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);
        
        $deleted = 0;
        $errors = 0;
        
        foreach ($testRecipes as $recipe) {
            // Delete the recipe post first
            $result = wp_delete_post($recipe->ID, true);
            
            if ($result) {
                // Only clean up migration log after successful deletion
                global $wpdb;
                $wpdb->delete(
                    $wpdb->prefix . 'kg_migration_log',
                    ['recipe_post_id' => (int) $recipe->ID], // Cast to int for safety
                    ['%d']
                );
                $deleted++;
            } else {
                $errors++;
                error_log('KG Core: Failed to delete test recipe ' . (int) $recipe->ID);
            }
        }
        
        $message = "{$deleted} test recipes cleaned";
        if ($errors > 0) {
            $message .= " ({$errors} errors)";
        }
        
        $this->logger->log($message);
        
        return [
            'deleted' => $deleted,
            'errors' => $errors,
            'message' => $message
        ];
    }
}

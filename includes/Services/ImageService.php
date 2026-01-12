<?php
namespace KG_Core\Services;

class ImageService {
    private $unsplash_key;
    private $pexels_key;
    private $preferred_api;
    private $openai_api_key;
    private $image_provider;
    private $stability_api_key;
    
    public function __construct() {
        $this->unsplash_key = get_option('kg_unsplash_api_key', '');
        $this->pexels_key = get_option('kg_pexels_api_key', '');
        $this->preferred_api = get_option('kg_preferred_image_api', 'unsplash');
        $this->openai_api_key = get_option('kg_ai_api_key', '');
        $this->image_provider = get_option('kg_image_provider', 'dalle');
        $this->stability_api_key = get_option('kg_stability_api_key', '');
    }
    
    /**
     * Generate image using configured provider (DALL-E or Stable Diffusion)
     * 
     * @param string $ingredient_name Name of ingredient in Turkish
     * @return array|null Image data with URL and source or null on failure
     */
    public function generateImage($ingredient_name) {
        if ($this->image_provider === 'stability' && !empty($this->stability_api_key)) {
            return $this->generateWithStableDiffusion($ingredient_name);
        } else if (!empty($this->openai_api_key)) {
            return $this->generateWithDallE($ingredient_name);
        }
        
        error_log('KG Core: No image generation API key configured');
        return null;
    }
    
    /**
     * Generate image using DALL-E 3
     * 
     * @param string $ingredient_name Name of ingredient in Turkish
     * @return array|null Image data with URL and source or null on failure
     */
    private function generateWithDallE($ingredient_name) {
        if (empty($this->openai_api_key)) {
            error_log('KG Core: OpenAI API key not configured for DALL-E');
            return null;
        }
        
        $prompt = $this->buildDallEPrompt($ingredient_name);
        
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openai_api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'standard',
                'style' => 'natural'
            ]),
            'timeout' => 90
        ]);
        
        if (is_wp_error($response)) {
            error_log('KG Core DALL-E Error: ' . $response->get_error_message());
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
            error_log('KG Core DALL-E HTTP Error: ' . $error_msg);
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['data'][0]['url'])) {
            error_log('KG Core DALL-E Error: No image URL in response');
            return null;
        }
        
        return [
            'url' => $body['data'][0]['url'],
            'source' => 'dall-e-3',
            'credit' => 'AI Generated (DALL-E 3)',
            'credit_url' => '',
            'prompt' => $prompt
        ];
    }
    
    /**
     * Generate image using Stable Diffusion (Stability AI)
     * 
     * @param string $ingredient_name Name of ingredient in Turkish
     * @return array|null Image data with URL and source or null on failure
     */
    private function generateWithStableDiffusion($ingredient_name) {
        if (empty($this->stability_api_key)) {
            error_log('KG Core: Stability AI API key not configured');
            return null;
        }
        
        $prompt = $this->buildStabilityPrompt($ingredient_name);
        $negative_prompt = $this->getStabilityNegativePrompt();
        
        $response = wp_remote_post('https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->stability_api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => json_encode([
                'text_prompts' => [
                    [
                        'text' => $prompt,
                        'weight' => 1
                    ],
                    [
                        'text' => $negative_prompt,
                        'weight' => -1
                    ]
                ],
                'cfg_scale' => 7,
                'height' => 1024,
                'width' => 1024,
                'steps' => 30,
                'samples' => 1
            ]),
            'timeout' => 90
        ]);
        
        if (is_wp_error($response)) {
            error_log('KG Core Stability AI Error: ' . $response->get_error_message());
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = isset($body['message']) ? $body['message'] : 'Unknown error';
            error_log('KG Core Stability AI HTTP Error: ' . $error_msg);
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['artifacts'][0]['base64'])) {
            error_log('KG Core Stability AI Error: No image data in response');
            return null;
        }
        
        // Convert base64 to data URL
        $base64_image = $body['artifacts'][0]['base64'];
        $data_url = 'data:image/png;base64,' . $base64_image;
        
        return [
            'url' => $data_url,
            'source' => 'stability-ai',
            'credit' => 'AI Generated (Stable Diffusion)',
            'credit_url' => '',
            'prompt' => $prompt,
            'negative_prompt' => $negative_prompt
        ];
    }
    
    /**
     * Build DALL-E 3 prompt for ingredient image (optimized to avoid prepared food)
     * 
     * @param string $ingredient_name Ingredient name in Turkish
     * @return string Formatted prompt in English
     */
    private function buildDallEPrompt($ingredient_name) {
        $english_name = $this->getEnglishName($ingredient_name);
        $category = $this->getIngredientCategory($ingredient_name);
        $template = $this->getIngredientPromptTemplate($category);
        
        // Replace {INGREDIENT} placeholder with actual ingredient name
        $prompt = str_replace('{INGREDIENT}', $english_name, $template);
        
        return $prompt;
    }
    
    /**
     * Build Stable Diffusion prompt for ingredient image
     * 
     * @param string $ingredient_name Ingredient name in Turkish
     * @return string Formatted prompt in English
     */
    private function buildStabilityPrompt($ingredient_name) {
        // Use same prompt template as DALL-E
        return $this->buildDallEPrompt($ingredient_name);
    }
    
    /**
     * Get negative prompt for Stable Diffusion
     * Excludes unwanted elements like cooked food, kitchen items, equipment
     * 
     * @return string Negative prompt
     */
    private function getStabilityNegativePrompt() {
        return 'cooked, cooking, meal, dish, recipe, plate, bowl, puree, mashed, soup, stew, sauce, prepared food, baby food, processed, ' .
               'pan, pot, spoon, fork, knife, cutting board, kitchen, stove, oven, microwave, blender, mixer, ' .
               'camera, spotlight, tripod, studio equipment, lighting equipment, hands, fingers, person, human, face, body parts, ' .
               'text, watermark, logo, signature, label, tag, price tag, table, tablecloth, napkin, decoration, flowers, vase, ' .
               'blurry, low quality, pixelated, grainy, noisy, artifacts, distorted, deformed, ugly, bad anatomy, wrong proportions, ' .
               'oversaturated, undersaturated, overexposed, underexposed, busy background, cluttered, messy, complex background, ' .
               'colored background, patterned background, textured background, gradient background, dark background, black background';
    }
    
    /**
     * Get prompt template based on ingredient category
     * 
     * @param string $category Category (fruits, vegetables, proteins, grains, dairy)
     * @return string Prompt template with {INGREDIENT} placeholder
     */
    private function getIngredientPromptTemplate($category) {
        $templates = [
            'fruits' => 'A single fresh {INGREDIENT}, raw and uncut, whole fruit, ' .
                       'isolated on pure white seamless background, ' .
                       'professional product photography, commercial food photography style, ' .
                       'soft diffused natural lighting from left side, ' .
                       'subtle natural shadow beneath the fruit, ' .
                       'vibrant natural colors, highly detailed texture of skin, ' .
                       'sharp focus, centered composition, negative space around subject, ' .
                       'no other objects, no decorations, no props, ' .
                       'clean minimalist style, stock photo quality',
            
            'vegetables' => 'Fresh raw {INGREDIENT}, whole and unprocessed vegetable, ' .
                           'isolated on pure white seamless studio background, ' .
                           'professional product photography for grocery store, ' .
                           'soft box lighting from upper left at 45 degrees, ' .
                           'gentle natural shadow on white surface, ' .
                           'vivid fresh colors showing ripeness and quality, ' .
                           'detailed texture visible, water droplets for freshness look, ' .
                           'sharp focus throughout, centered in frame, ' .
                           'no cutting board, no knife, no kitchen items, ' .
                           'clean commercial photography style',
            
            'proteins' => 'Raw fresh {INGREDIENT}, uncooked protein ingredient, ' .
                         'placed on pure white background, ' .
                         'professional butcher shop or fishmonger display style photography, ' .
                         'clean soft lighting, no harsh shadows, ' .
                         'showing fresh quality and natural color, ' .
                         'high detail, sharp focus, ' .
                         'no seasoning, no marinade, no cooking preparation, ' .
                         'isolated single item, no other ingredients, ' .
                         'commercial food photography',
            
            'grains' => 'Dry uncooked {INGREDIENT}, raw grain or cereal ingredient, ' .
                       'small pile or scattered arrangement on pure white background, ' .
                       'macro food photography showing individual grain texture, ' .
                       'soft even lighting, no harsh shadows, ' .
                       'natural earthy colors, sharp detail, ' .
                       'no bowl, no container, no scoop, ' .
                       'clean product photography style',
            
            'dairy' => 'Fresh {INGREDIENT}, unprocessed dairy product, ' .
                      'presented on pure white background, ' .
                      'professional product photography, ' .
                      'clean soft lighting, minimal shadows, ' .
                      'showing freshness and quality, ' .
                      'sharp focus, centered composition, ' .
                      'no decorations, no garnish, ' .
                      'commercial photography style'
        ];
        
        return $templates[$category] ?? $templates['vegetables'];
    }
    
    /**
     * Determine ingredient category from Turkish name
     * 
     * @param string $name Turkish ingredient name
     * @return string Category (fruits, vegetables, proteins, grains, dairy)
     */
    private function getIngredientCategory($name) {
        $lower = mb_strtolower($name, 'UTF-8');
        
        $fruits = ['elma', 'muz', 'armut', 'şeftali', 'kayısı', 'erik', 'kiraz', 'çilek', 'üzüm', 'karpuz', 'kavun', 
                   'portakal', 'mandalina', 'kivi', 'hurma', 'incir', 'nar', 'avokado', 'mango', 'ananas', 'papaya',
                   'böğürtlen', 'ahududu', 'yaban mersini', 'vişne', 'limon', 'greyfurt', 'ayva',
                   'kuru hurma', 'kuru üzüm', 'kuru kayısı', 'kuru incir'];
        
        $vegetables = ['havuç', 'patates', 'brokoli', 'tatlı patates', 'kabak', 'balkabağı', 'karnabahar', 'ıspanak',
                      'pırasa', 'bezelye', 'fasulye', 'domates', 'salatalık', 'biber', 'patlıcan', 'lahana', 'soğan',
                      'sarımsak', 'kereviz', 'pancar', 'marul', 'roka', 'turp', 'bamya', 'enginar',
                      'kırmızı biber', 'yeşil biber', 'kornişon', 'kuşkonmaz'];
        
        $proteins = ['tavuk göğsü', 'tavuk', 'hindi', 'somon', 'levrek', 'çipura', 'ton balığı', 'yumurta', 'dana eti',
                    'kuzu eti', 'kırmızı et', 'balık', 'hamsi', 'palamut', 'uskumru'];
        
        $grains = ['pirinç', 'yulaf', 'mercimek', 'nohut', 'bulgur', 'kinoa', 'arpa', 'buğday', 'mısır', 'kuskus',
                  'kırmızı mercimek', 'yeşil mercimek', 'barbunya', 'kuru fasulye'];
        
        $dairy = ['yoğurt', 'süt', 'peynir', 'lor peyniri', 'beyaz peynir', 'kaşar peyniri', 'labne', 'tereyağı'];
        
        if (in_array($lower, $fruits)) {
            return 'fruits';
        } else if (in_array($lower, $vegetables)) {
            return 'vegetables';
        } else if (in_array($lower, $proteins)) {
            return 'proteins';
        } else if (in_array($lower, $grains)) {
            return 'grains';
        } else if (in_array($lower, $dairy)) {
            return 'dairy';
        }
        
        // Log when using fallback to help identify missing ingredients
        error_log("KG Core: Using default category for ingredient: {$name}");
        return 'vegetables'; // Default to vegetables as most common
    }
    
    /**
     * Translate Turkish ingredient name to English with descriptive terms
     * Expanded to 100+ ingredients
     * 
     * @param string $name Turkish ingredient name
     * @return string English ingredient name with descriptive terms
     */
    private function getEnglishName($name) {
        $translations = [
            // Fruits
            'elma' => 'whole red apple',
            'muz' => 'whole ripe yellow banana',
            'armut' => 'whole fresh pear',
            'şeftali' => 'whole fresh peach',
            'kayısı' => 'whole fresh apricots',
            'erik' => 'whole fresh plums',
            'kiraz' => 'fresh cherries with stems',
            'çilek' => 'fresh whole strawberries',
            'üzüm' => 'fresh grape cluster',
            'karpuz' => 'whole watermelon',
            'kavun' => 'whole fresh cantaloupe melon',
            'portakal' => 'whole fresh oranges',
            'mandalina' => 'whole fresh mandarins',
            'kivi' => 'whole kiwi fruit',
            'hurma' => 'whole fresh persimmon',
            'incir' => 'whole fresh figs',
            'nar' => 'whole fresh pomegranate',
            'avokado' => 'whole ripe avocado',
            'mango' => 'whole ripe mango',
            'ananas' => 'whole fresh pineapple',
            'papaya' => 'whole fresh papaya',
            'böğürtlen' => 'fresh blackberries',
            'ahududu' => 'fresh raspberries',
            'yaban mersini' => 'fresh blueberries',
            'vişne' => 'fresh sour cherries',
            'limon' => 'whole fresh lemons',
            'greyfurt' => 'whole fresh grapefruit',
            'ayva' => 'whole fresh quince',
            
            // Vegetables
            'havuç' => 'fresh orange carrots with green tops',
            'patates' => 'whole raw potatoes',
            'brokoli' => 'fresh broccoli head',
            'tatlı patates' => 'whole sweet potatoes',
            'kabak' => 'fresh zucchini squash',
            'balkabağı' => 'whole orange pumpkin',
            'karnabahar' => 'fresh cauliflower head',
            'ıspanak' => 'fresh spinach leaves bunch',
            'pırasa' => 'fresh leeks',
            'bezelye' => 'fresh green peas in pods',
            'fasulye' => 'fresh green beans',
            'domates' => 'fresh red tomatoes',
            'salatalık' => 'fresh cucumber',
            'biber' => 'fresh bell peppers',
            'patlıcan' => 'whole purple eggplant',
            'lahana' => 'whole fresh cabbage',
            'soğan' => 'whole yellow onions',
            'sarımsak' => 'whole garlic bulb',
            'kereviz' => 'fresh celery stalks',
            'pancar' => 'whole fresh beetroot',
            'marul' => 'fresh lettuce head',
            'roka' => 'fresh arugula leaves',
            'turp' => 'fresh radishes',
            'bamya' => 'fresh okra pods',
            'enginar' => 'whole fresh artichoke',
            'kırmızı biber' => 'fresh red bell peppers',
            'yeşil biber' => 'fresh green bell peppers',
            'kornişon' => 'fresh gherkin cucumbers',
            'kuşkonmaz' => 'fresh asparagus spears',
            
            // Proteins
            'tavuk göğsü' => 'raw chicken breast fillet',
            'tavuk' => 'raw whole chicken',
            'hindi' => 'raw turkey breast',
            'somon' => 'raw salmon fillet',
            'levrek' => 'raw sea bass fillet',
            'çipura' => 'raw sea bream fish',
            'ton balığı' => 'raw tuna steak',
            'yumurta' => 'fresh brown eggs',
            'dana eti' => 'raw veal meat',
            'kuzu eti' => 'raw lamb meat',
            'kırmızı et' => 'raw beef steak',
            'balık' => 'raw whole fish',
            'hamsi' => 'fresh anchovies',
            'palamut' => 'raw bonito fish',
            'uskumru' => 'whole fresh mackerel',
            
            // Grains & Legumes (raw/dry forms)
            'pirinç' => 'raw white rice grains',
            'yulaf' => 'dry rolled oats',
            'mercimek' => 'raw red lentils',
            'nohut' => 'dried chickpeas',
            'bulgur' => 'dry bulgur wheat',
            'kinoa' => 'raw quinoa grains',
            'arpa' => 'raw barley grains',
            'buğday' => 'raw wheat grains',
            'mısır' => 'fresh corn kernels',
            'kuskus' => 'dry couscous grains',
            'kırmızı mercimek' => 'raw red lentils',
            'yeşil mercimek' => 'raw green lentils',
            'barbunya' => 'dried pinto beans',
            'kuru fasulye' => 'dried white beans',
            
            // Dairy
            'yoğurt' => 'plain white yogurt in glass bowl',
            'süt' => 'glass of whole milk',
            'peynir' => 'block of cheese',
            'lor peyniri' => 'fresh cottage cheese',
            'beyaz peynir' => 'white feta cheese block',
            'kaşar peyniri' => 'yellow kashkaval cheese',
            'labne' => 'fresh labneh yogurt cheese',
            'tereyağı' => 'block of butter',
            
            // Nuts & Seeds
            'badem' => 'whole raw almonds',
            'ceviz' => 'whole walnuts',
            'fındık' => 'whole hazelnuts',
            'fıstık' => 'raw peanuts',
            'antep fıstığı' => 'raw pistachios',
            'susam' => 'sesame seeds',
            'ayçiçeği çekirdeği' => 'sunflower seeds',
            'kabak çekirdeği' => 'pumpkin seeds',
            'çam fıstığı' => 'pine nuts',
            'kaju' => 'raw cashew nuts',
            
            // Herbs & Spices (fresh)
            'maydanoz' => 'fresh parsley bunch',
            'dereotu' => 'fresh dill bunch',
            'nane' => 'fresh mint leaves',
            'fesleğen' => 'fresh basil leaves',
            'kekik' => 'fresh thyme sprigs',
            'biberiye' => 'fresh rosemary sprigs',
            'tarhun' => 'fresh tarragon',
            
            // Dried fruits (with distinct names)
            'kuru hurma' => 'dried dates',
            'kuru üzüm' => 'dried raisins',
            'kuru kayısı' => 'dried apricots',
            'kuru incir' => 'dried figs',
            
            // Other
            'zeytinyağı' => 'bottle of olive oil',
            'bal' => 'jar of honey',
            'zeytin' => 'whole black olives'
        ];
        
        $lower = mb_strtolower($name, 'UTF-8');
        return $translations[$lower] ?? $name;
    }
    
    /**
     * Legacy method name for backward compatibility
     * 
     * @param string $name Turkish ingredient name
     * @return string English ingredient name
     */
    private function translateToEnglish($name) {
        return $this->getEnglishName($name);
    }
    
    /**
     * Fetch image for given search query
     * 
     * @param string $query Search query (in English)
     * @return array|null Image data or null if not found
     */
    public function fetchImage($query) {
        if (empty($query)) {
            return null;
        }
        
        // Try preferred API first
        if ($this->preferred_api === 'dall-e' && !empty($this->openai_api_key)) {
            $result = $this->generateImage($query);
            if ($result !== null) {
                return $result;
            }
        } elseif ($this->preferred_api === 'unsplash' && !empty($this->unsplash_key)) {
            $result = $this->fetchFromUnsplash($query);
            if ($result !== null) {
                return $result;
            }
        } elseif ($this->preferred_api === 'pexels' && !empty($this->pexels_key)) {
            $result = $this->fetchFromPexels($query);
            if ($result !== null) {
                return $result;
            }
        }
        
        // Fallback to other APIs
        if ($this->preferred_api === 'dall-e') {
            if (!empty($this->unsplash_key)) {
                $result = $this->fetchFromUnsplash($query);
                if ($result !== null) return $result;
            }
            if (!empty($this->pexels_key)) {
                return $this->fetchFromPexels($query);
            }
        } elseif ($this->preferred_api === 'unsplash' && !empty($this->pexels_key)) {
            return $this->fetchFromPexels($query);
        } elseif ($this->preferred_api === 'pexels' && !empty($this->unsplash_key)) {
            return $this->fetchFromUnsplash($query);
        }
        
        return null;
    }
    
    /**
     * Fetch image from Unsplash
     * 
     * @param string $query Search query
     * @return array|null Image data or null
     */
    private function fetchFromUnsplash($query) {
        if (empty($this->unsplash_key)) {
            return null;
        }
        
        $url = 'https://api.unsplash.com/search/photos?' . http_build_query([
            'query' => $query,
            'per_page' => 1,
            'orientation' => 'landscape',
            'content_filter' => 'high'
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Client-ID ' . $this->unsplash_key
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('KG Core Unsplash Error: ' . $response->get_error_message());
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('KG Core Unsplash HTTP Error: ' . $status_code);
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['results'])) {
            return null;
        }
        
        $photo = $body['results'][0];
        
        return [
            'url' => $photo['urls']['regular'],
            'credit' => $photo['user']['name'],
            'credit_url' => $photo['user']['links']['html'],
            'source' => 'unsplash'
        ];
    }
    
    /**
     * Fetch image from Pexels
     * 
     * @param string $query Search query
     * @return array|null Image data or null
     */
    private function fetchFromPexels($query) {
        if (empty($this->pexels_key)) {
            return null;
        }
        
        $url = 'https://api.pexels.com/v1/search?' . http_build_query([
            'query' => $query,
            'per_page' => 1,
            'orientation' => 'landscape'
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => $this->pexels_key
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('KG Core Pexels Error: ' . $response->get_error_message());
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('KG Core Pexels HTTP Error: ' . $status_code);
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['photos'])) {
            return null;
        }
        
        $photo = $body['photos'][0];
        
        return [
            'url' => $photo['src']['large'],
            'credit' => $photo['photographer'],
            'credit_url' => $photo['photographer_url'],
            'source' => 'pexels'
        ];
    }
    
    /**
     * Download image to WordPress media library
     * 
     * @param string $url Image URL
     * @param string $filename Desired filename
     * @return int|WP_Error Attachment ID or error
     */
    public function downloadToMediaLibrary($url, $filename) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download file to temp location
        $temp_file = download_url($url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        // Sanitize filename and ensure proper extension
        $filename = sanitize_file_name($filename);
        
        // Add appropriate extension if not present
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            // Default to PNG (common for AI-generated and high-quality images)
            $filename .= '.png';
        }
        
        // Prepare file array
        $file_array = [
            'name' => $filename,
            'tmp_name' => $temp_file
        ];
        
        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, 0);
        
        // Remove temp file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Mark AI-generated images
        update_post_meta($attachment_id, '_kg_ai_generated', true);
        
        return $attachment_id;
    }
}

<?php

namespace KG_Core\Services;

class ImageService {

    private $unsplash_key;
    private $pexels_key;
    private $preferred_api;
    private $openai_api_key;
    private $image_provider;
    private $stability_api_key;

    // ---- Prompt system constants (internal only) ----
    private const KG_BG_COLOR = '#F7F7F5';

    public function __construct() {
        $this->unsplash_key     = get_option('kg_unsplash_api_key', '');
        $this->pexels_key       = get_option('kg_pexels_api_key', '');
        $this->preferred_api    = get_option('kg_preferred_image_api', 'unsplash');
        $this->openai_api_key   = get_option('kg_ai_api_key', '');
        $this->image_provider   = get_option('kg_image_provider', 'dalle');
        $this->stability_api_key = get_option('kg_stability_api_key', '');
    }

    /**
     * Generate image using configured provider (DALL-E or Stable Diffusion)
     *
     * @param string $ingredient_name Name of ingredient in Turkish
     * @return array|null Image data with URL and source or null on failure
     */
    public function generateImage($ingredient_name) {
        $ingredient_name = trim((string) $ingredient_name);
        if ($ingredient_name === '') {
            error_log('KG Core: Empty ingredient name passed to generateImage');
            return null;
        }

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
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model'   => 'dall-e-3',
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => '1024x1024',
                'quality' => 'standard',
                'style'   => 'natural'
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
            'url'        => $body['data'][0]['url'],
            'source'     => 'dall-e-3',
            'credit'     => 'AI Generated (DALL-E 3)',
            'credit_url' => '',
            'prompt'     => $prompt
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
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => json_encode([
                'text_prompts' => [
                    ['text' => $prompt, 'weight' => 1],
                    ['text' => $negative_prompt, 'weight' => -1]
                ],
                'cfg_scale' => 7,
                'height'    => 1024,
                'width'     => 1024,
                'steps'     => 30,
                'samples'   => 1
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
            'url'             => $data_url,
            'source'          => 'stability-ai',
            'credit'          => 'AI Generated (Stable Diffusion)',
            'credit_url'      => '',
            'prompt'          => $prompt,
            'negative_prompt' => $negative_prompt
        ];
    }

    /**
     * Build DALL-E 3 prompt for ingredient image (optimized for realistic, UI-consistent ingredient set)
     *
     * @param string $ingredient_name Ingredient name in Turkish
     * @return string Formatted prompt in English
     */
    private function buildDallEPrompt($ingredient_name) {
        $english_name = $this->getEnglishName($ingredient_name);
        $category     = $this->getIngredientCategory($ingredient_name);
        $template     = $this->getIngredientPromptTemplate($category);

        // Replace placeholder
        $prompt = str_replace('{INGREDIENT}', $english_name, $template);

        // DALL·E is good with "series consistency" hints:
        $prompt .= ' This image is part of a cohesive ingredient image set for a modern health-tech baby nutrition website. Keep the same lighting, background, camera angle, and scale as the rest of the set.';

        return $prompt;
    }

    /**
     * Build Stable Diffusion prompt for ingredient image
     *
     * @param string $ingredient_name Ingredient name in Turkish
     * @return string Formatted prompt in English
     */
    private function buildStabilityPrompt($ingredient_name) {
        // Use same prompt template as DALL-E for consistency
        return $this->buildDallEPrompt($ingredient_name);
    }

    /**
     * Get negative prompt for Stable Diffusion (SDXL)
     * Focused list to avoid harming realism (no contradictory constraints)
     *
     * @return string Negative prompt
     */
    private function getStabilityNegativePrompt() {
        return implode(', ', [
            // No prepared meals / cooking context
            'cooked meal', 'prepared dish', 'recipe plating', 'puree', 'baby food', 'soup', 'stew', 'sauce', 'seasoning', 'marinade',

            // No props / people
            'plate', 'bowl with pattern', 'utensils', 'spoon', 'fork', 'knife', 'cutting board', 'kitchen scene',
            'hands', 'fingers', 'person', 'human', 'face',

            // No packaging / branding
            'label', 'logo', 'watermark', 'text', 'brand', 'barcode', 'price tag',

            // Avoid gore / unpleasant protein outputs
            'blood', 'gore', 'raw meat hanging', 'butcher hooks', 'organs',

            // Quality negatives
            'blurry', 'low quality', 'low-res', 'pixelated', 'grainy', 'noisy', 'jpeg artifacts',
            'distorted', 'deformed', 'bad anatomy', 'wrong proportions',

            // Avoid messy composition
            'cluttered', 'busy background', 'messy', 'multiple objects', 'extra ingredients'
        ]);
    }

    /**
     * Get prompt template based on ingredient category
     *
     * @param string $category Category (fruits, vegetables, proteins, grains, dairy, nuts_seeds, herbs_spices, oils_condiments)
     * @return string Prompt template with {INGREDIENT} placeholder
     */
    private function getIngredientPromptTemplate($category) {
        // Global “KidsGourmet set style” anchor
        $base = 'Single {INGREDIENT} ingredient photo for a modern health-tech baby nutrition app. ' .
                'Consistent series style: clean off-white seamless background (' . self::KG_BG_COLOR . '), ' .
                'soft diffused daylight, subtle contact shadow (not floating), ' .
                '3/4 top angle (about 20 degrees), centered composition, subject fills ~70% of the frame, crop-safe margins, lots of negative space. ' .
                'Color accurate, natural texture, subtle imperfections, ultra realistic, sharp focus. ' .
                'No text, no logo, no watermark, no hands, no people, no utensils, no cutting board, no kitchen scene. ';

        $templates = [
            'fruits' => $base .
                'Whole fruit only, uncut, fresh and ripe, realistic skin texture and sheen, natural color variation, no water droplets, no props.',

            'vegetables' => $base .
                'Whole vegetable only, uncut, fresh crisp texture, natural color variation, not plastic-looking, no water droplets, no props.',

            'proteins' => $base .
                'Fresh raw protein ingredient in a clean, food-safe presentation. No blood, no gore, no ice, no packaging, no garnish. Single item only.',

            'grains' => $base .
                'Dry uncooked grains/legumes in a neat small mound (not scattered). Macro detail showing individual grains. Clean surface, no container, no props.',

            'dairy' => $base .
                'If the ingredient is inherently liquid/soft (milk, yogurt, labneh), present it in a plain matte white ceramic bowl or a plain clear glass with NO branding. ' .
                'No spoon, no labels. Minimal, consistent container style across the set. ' .
                'Otherwise (cheese/butter), show a simple clean-cut portion with natural texture. No garnish.',

            'nuts_seeds' => $base .
                'Raw nuts/seeds only, natural texture, a neat small pile, macro detail, no container, no props, no salt, no roasting.',

            'herbs_spices' => $base .
                'Fresh herbs only, compact tidy small bunch (not sprawling to edges), crisp leaves, natural texture, no rubber band visible, no props.',

            'oils_condiments' => $base .
                'If the ingredient is a liquid (olive oil, honey), present it in a plain clear glass bottle/jar with NO label, no branding, minimal shape, consistent across the set. ' .
                'No spoon, no drips, no garnish. Single item only.'
        ];

        return $templates[$category] ?? $templates['vegetables'];
    }

    /**
     * Determine ingredient category from Turkish name
     *
     * @param string $name Turkish ingredient name
     * @return string Category
     */
    private function getIngredientCategory($name) {
        $lower = $this->mbLower($name);

        // Fruits
        $fruits = [
            'elma','muz','armut','şeftali','kayısı','erik','kiraz','çilek','üzüm','karpuz','kavun',
            'portakal','mandalina','kivi','hurma','incir','nar','avokado','mango','ananas','papaya',
            'böğürtlen','ahududu','yaban mersini','vişne','limon','greyfurt','ayva',
            'kuru hurma','kuru üzüm','kuru kayısı','kuru incir'
        ];

        // Vegetables
        $vegetables = [
            'havuç','patates','brokoli','tatlı patates','kabak','balkabağı','karnabahar','ıspanak',
            'pırasa','bezelye','fasulye','domates','salatalık','biber','patlıcan','lahana','soğan',
            'sarımsak','kereviz','pancar','marul','roka','turp','bamya','enginar',
            'kırmızı biber','yeşil biber','kornişon','kuşkonmaz'
        ];

        // Proteins
        $proteins = [
            'tavuk göğsü','tavuk','hindi','somon','levrek','çipura','ton balığı','yumurta','dana eti',
            'kuzu eti','kırmızı et','balık','hamsi','palamut','uskumru'
        ];

        // Grains & Legumes
        $grains = [
            'pirinç','yulaf','mercimek','nohut','bulgur','kinoa','arpa','buğday','mısır','kuskus',
            'kırmızı mercimek','yeşil mercimek','barbunya','kuru fasulye'
        ];

        // Dairy
        $dairy = [
            'yoğurt','süt','peynir','lor peyniri','beyaz peynir','kaşar peyniri','labne','tereyağı'
        ];

        // Nuts & Seeds
        $nuts_seeds = [
            'badem','ceviz','fındık','fıstık','antep fıstığı','susam','ayçiçeği çekirdeği','kabak çekirdeği','çam fıstığı','kaju'
        ];

        // Herbs & Spices (fresh herbs)
        $herbs_spices = [
            'maydanoz','dereotu','nane','fesleğen','kekik','biberiye','tarhun'
        ];

        // Oils & condiments (container-allowed)
        $oils_condiments = [
            'zeytinyağı','bal','zeytin'
        ];

        if (in_array($lower, $fruits, true)) return 'fruits';
        if (in_array($lower, $vegetables, true)) return 'vegetables';
        if (in_array($lower, $proteins, true)) return 'proteins';
        if (in_array($lower, $grains, true)) return 'grains';
        if (in_array($lower, $dairy, true)) return 'dairy';
        if (in_array($lower, $nuts_seeds, true)) return 'nuts_seeds';
        if (in_array($lower, $herbs_spices, true)) return 'herbs_spices';
        if (in_array($lower, $oils_condiments, true)) return 'oils_condiments';

        // Log fallback to help identify missing ingredients
        error_log("KG Core: Using default category for ingredient: {$name}");
        return 'vegetables';
    }

    /**
     * Translate Turkish ingredient name to English with descriptive terms
     * (UI-consistent & prompt-compatible)
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
            'kayısı' => 'whole fresh apricot',
            'erik' => 'whole fresh plum',
            'kiraz' => 'fresh cherries with stems',
            'çilek' => 'fresh whole strawberries',
            'üzüm' => 'fresh grape cluster',
            'karpuz' => 'whole watermelon',
            'kavun' => 'whole cantaloupe melon',
            'portakal' => 'whole fresh orange',
            'mandalina' => 'whole mandarin',
            'kivi' => 'whole kiwi fruit',
            'hurma' => 'whole persimmon',
            'incir' => 'whole fresh fig',
            'nar' => 'whole pomegranate',
            'avokado' => 'whole ripe avocado',
            'mango' => 'whole ripe mango',
            'ananas' => 'whole pineapple',
            'papaya' => 'whole papaya',
            'böğürtlen' => 'fresh blackberries',
            'ahududu' => 'fresh raspberries',
            'yaban mersini' => 'fresh blueberries',
            'vişne' => 'fresh sour cherries',
            'limon' => 'whole lemon',
            'greyfurt' => 'whole grapefruit',
            'ayva' => 'whole quince',

            // Vegetables
            'havuç' => 'fresh orange carrot',
            'patates' => 'whole raw potato',
            'brokoli' => 'fresh broccoli head',
            'tatlı patates' => 'whole sweet potato',
            'kabak' => 'fresh zucchini',
            'balkabağı' => 'whole orange pumpkin',
            'karnabahar' => 'fresh cauliflower head',
            'ıspanak' => 'fresh spinach (compact bunch of leaves)',
            'pırasa' => 'fresh leek',
            'bezelye' => 'fresh green peas in pod',
            'fasulye' => 'fresh green beans',
            'domates' => 'fresh red tomato',
            'salatalık' => 'fresh cucumber',
            'biber' => 'fresh bell pepper',
            'patlıcan' => 'whole purple eggplant',
            'lahana' => 'whole cabbage',
            'soğan' => 'whole yellow onion',
            'sarımsak' => 'whole garlic bulb',
            'kereviz' => 'fresh celery stalks (compact)',
            'pancar' => 'whole beetroot',
            'marul' => 'fresh lettuce head',
            'roka' => 'fresh arugula (compact bunch)',
            'turp' => 'fresh radish',
            'bamya' => 'fresh okra pods',
            'enginar' => 'whole artichoke',
            'kırmızı biber' => 'fresh red bell pepper',
            'yeşil biber' => 'fresh green pepper',
            'kornişon' => 'fresh gherkin cucumber',
            'kuşkonmaz' => 'fresh asparagus spears (compact bundle)',

            // Proteins (clean & food-safe wording)
            'tavuk göğsü' => 'raw chicken breast fillet (clean cut)',
            'tavuk' => 'raw chicken breast pieces (clean cut)',
            'hindi' => 'raw turkey breast fillet (clean cut)',
            'somon' => 'raw salmon fillet (clean cut)',
            'levrek' => 'raw sea bass fillet (clean cut)',
            'çipura' => 'raw sea bream (clean, no blood)',
            'ton balığı' => 'raw tuna steak (clean cut)',
            'yumurta' => 'fresh brown eggs',
            'dana eti' => 'raw beef steak (clean cut)',
            'kuzu eti' => 'raw lamb meat (clean cut)',
            'kırmızı et' => 'raw beef steak (clean cut)',
            'balık' => 'raw fish fillet (clean cut)',
            'hamsi' => 'fresh anchovies (clean, no blood)',
            'palamut' => 'raw bonito fish (clean, no blood)',
            'uskumru' => 'whole fresh mackerel (clean, no blood)',

            // Grains & Legumes
            'pirinç' => 'raw white rice grains',
            'yulaf' => 'dry rolled oats',
            'mercimek' => 'dry red lentils',
            'nohut' => 'dried chickpeas',
            'bulgur' => 'dry bulgur wheat',
            'kinoa' => 'raw quinoa grains',
            'arpa' => 'raw barley grains',
            'buğday' => 'raw wheat grains',
            'mısır' => 'dry corn kernels',
            'kuskus' => 'dry couscous grains',
            'kırmızı mercimek' => 'dry red lentils',
            'yeşil mercimek' => 'dry green lentils',
            'barbunya' => 'dried pinto beans',
            'kuru fasulye' => 'dried white beans',

            // Dairy (container standardized in template)
            'yoğurt' => 'plain yogurt',
            'süt' => 'fresh milk',
            'peynir' => 'cheese block (plain, unbranded)',
            'lor peyniri' => 'cottage cheese',
            'beyaz peynir' => 'feta cheese block (plain, unbranded)',
            'kaşar peyniri' => 'kashkaval cheese block (plain, unbranded)',
            'labne' => 'labneh (yogurt cheese)',
            'tereyağı' => 'butter block (plain, unbranded)',

            // Nuts & Seeds
            'badem' => 'raw whole almonds',
            'ceviz' => 'raw whole walnuts',
            'fındık' => 'raw whole hazelnuts',
            'fıstık' => 'raw peanuts',
            'antep fıstığı' => 'raw pistachios',
            'susam' => 'sesame seeds',
            'ayçiçeği çekirdeği' => 'sunflower seeds',
            'kabak çekirdeği' => 'pumpkin seeds',
            'çam fıstığı' => 'pine nuts',
            'kaju' => 'raw cashew nuts',

            // Herbs
            'maydanoz' => 'fresh parsley (compact bunch)',
            'dereotu' => 'fresh dill (compact bunch)',
            'nane' => 'fresh mint leaves (compact bunch)',
            'fesleğen' => 'fresh basil leaves (compact bunch)',
            'kekik' => 'fresh thyme sprigs (compact bunch)',
            'biberiye' => 'fresh rosemary sprigs (compact bunch)',
            'tarhun' => 'fresh tarragon (compact bunch)',

            // Dried fruits
            'kuru hurma' => 'dried dates',
            'kuru üzüm' => 'dried raisins',
            'kuru kayısı' => 'dried apricots',
            'kuru incir' => 'dried figs',

            // Oils & condiments (container standardized in template)
            'zeytinyağı' => 'olive oil',
            'bal' => 'honey',
            'zeytin' => 'black olives'
        ];

        $lower = $this->mbLower($name);
        return $translations[$lower] ?? trim((string)$name);
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
        $query = trim((string) $query);
        if ($query === '') {
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
            'query'          => $query,
            'per_page'       => 1,
            'orientation'    => 'landscape',
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
            'url'        => $photo['urls']['regular'],
            'credit'     => $photo['user']['name'],
            'credit_url' => $photo['user']['links']['html'],
            'source'     => 'unsplash'
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
            'query'       => $query,
            'per_page'    => 1,
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
            'url'        => $photo['src']['large'],
            'credit'     => $photo['photographer'],
            'credit_url' => $photo['photographer_url'],
            'source'     => 'pexels'
        ];
    }

    /**
     * Download image to WordPress media library
     *
     * @param string $url Image URL
     * @param string $filename Desired filename
     * @return int|\WP_Error Attachment ID or error
     */
    public function downloadToMediaLibrary($url, $filename) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $url = trim((string)$url);
        if ($url === '') {
            return new \WP_Error('kg_invalid_url', 'Empty URL provided');
        }

        // Download file to temp location
        $temp_file = download_url($url);

        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Sanitize filename and ensure proper extension
        $filename = sanitize_file_name((string)$filename);

        // Add appropriate extension if not present
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            $filename .= '.png';
        }

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $temp_file
        ];

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

    // ---- Small internal helper(s) (safe additions; no external API change) ----
    private function mbLower($text) {
        return mb_strtolower(trim((string)$text), 'UTF-8');
    }
}

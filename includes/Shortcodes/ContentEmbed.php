<?php
namespace KG_Core\Shortcodes;

class ContentEmbed {
    private $allowed_types = ['recipe', 'ingredient', 'tool', 'post'];
    
    /**
     * Age group color mapping
     */
    private $age_group_colors = [
        '6-8-ay' => '#FFAB91',
        '8-10-ay' => '#FFB74D',
        '10-12-ay' => '#FFA726',
        '12-18-ay' => '#FF9800',
        '18-24-ay' => '#FF6F00',
        '2-yas' => '#81C784',
        '3-yas' => '#66BB6A',
        '4-yas' => '#4CAF50',
    ];
    
    /**
     * URL mapping for post types
     */
    private $url_prefixes = [
        'recipe' => 'tarifler',
        'ingredient' => 'malzemeler',
        'tool' => 'araclar',
        'post' => 'posts',
    ];
    
    public function __construct() {
        add_shortcode('kg-embed', [$this, 'render_shortcode']);
        add_filter('the_content', [$this, 'process_embeds_for_rest'], 5);
        add_action('rest_api_init', [$this, 'register_rest_fields']);
    }
    
    /**
     * Render shortcode (frontend display)
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'type' => '',
            'id' => '',
            'ids' => '',
        ], $atts);
        
        // Validate type
        if (!in_array($atts['type'], $this->allowed_types)) {
            return '';
        }
        
        // Get IDs
        $ids = !empty($atts['ids']) ? explode(',', $atts['ids']) : [$atts['id']];
        $ids = array_filter(array_map('absint', $ids));
        
        if (empty($ids)) {
            return '';
        }
        
        // Return placeholder for frontend rendering
        static $embed_counter = 0;
        $placeholder_id = 'kg-embed-' . $embed_counter++;
        
        return sprintf(
            '<div class="kg-embed-placeholder" data-embed-id="%s" data-type="%s" data-ids="%s"></div>',
            esc_attr($placeholder_id),
            esc_attr($atts['type']),
            esc_attr(implode(',', $ids))
        );
    }
    
    /**
     * Process embeds for REST API
     */
    public function process_embeds_for_rest($content) {
        // Only process during REST API requests
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return $content;
        }
        
        return $content;
    }
    
    /**
     * Register REST API fields
     */
    public function register_rest_fields() {
        register_rest_field('post', 'embedded_content', [
            'get_callback' => [$this, 'get_embedded_content'],
            'schema' => [
                'description' => __('Embedded content within the post', 'kg-core'),
                'type' => 'array',
            ],
        ]);
    }
    
    /**
     * Get embedded content for REST API
     */
    public function get_embedded_content($post) {
        $content = get_post_field('post_content', $post['id']);
        return $this->extract_embeds_from_content($content);
    }
    
    /**
     * Extract embeds from content with paragraph positions
     */
    public function extract_embeds_from_content($content) {
        $embeds = [];
        static $embed_counter = 0;
        
        // Find all shortcodes in content
        if (preg_match_all('/\[kg-embed\s+([^\]]+)\]/i', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $shortcode = $match[0][0];
                $offset = $match[0][1];
                
                // Parse shortcode attributes
                preg_match('/type=["\']?([^"\'\s]+)["\']?/i', $shortcode, $type_match);
                preg_match('/ids=["\']?([^"\']+)["\']?/i', $shortcode, $ids_match);
                preg_match('/id=["\']?(\d+)["\']?/i', $shortcode, $id_match);
                
                $type = isset($type_match[1]) ? $type_match[1] : '';
                
                // Get IDs
                if (isset($ids_match[1])) {
                    $ids = array_map('absint', array_filter(explode(',', $ids_match[1])));
                } elseif (isset($id_match[1])) {
                    $ids = [absint($id_match[1])];
                } else {
                    continue;
                }
                
                if (!in_array($type, $this->allowed_types) || empty($ids)) {
                    continue;
                }
                
                // Calculate paragraph position
                $position = 0;
                $content_before = substr($content, 0, $offset);
                $position = substr_count($content_before, '<p>') + substr_count($content_before, "\n\n");
                
                // Get embed data
                $items = $this->get_embed_data($type, $ids);
                
                if (!empty($items)) {
                    $embeds[] = [
                        'type' => $type,
                        'position' => $position,
                        'placeholder_id' => 'kg-embed-' . $embed_counter++,
                        'items' => $items,
                    ];
                }
            }
        }
        
        // Also find block placeholders (from Gutenberg blocks)
        if (preg_match_all('/<div[^>]*class="[^"]*kg-embed-placeholder[^"]*"[^>]*data-type="([^"]*)"[^>]*data-ids="([^"]*)"[^>]*>/i', 
            $content, $block_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($block_matches as $match) {
                $full_match = $match[0][0];
                $offset = $match[0][1];
                $type = $match[1][0];
                $ids_str = $match[2][0];
                
                // Get IDs
                $ids = array_map('absint', array_filter(explode(',', $ids_str)));
                
                if (!in_array($type, $this->allowed_types) || empty($ids)) {
                    continue;
                }
                
                // Calculate paragraph position
                $position = 0;
                $content_before = substr($content, 0, $offset);
                $position = substr_count($content_before, '<p>') + substr_count($content_before, "\n\n");
                
                // Get embed data
                $items = $this->get_embed_data($type, $ids);
                
                if (!empty($items)) {
                    $embeds[] = [
                        'type' => $type,
                        'position' => $position,
                        'placeholder_id' => 'kg-embed-' . $embed_counter++,
                        'items' => $items,
                    ];
                }
            }
        }
        
        return $embeds;
    }
    
    /**
     * Get embed data based on type
     */
    private function get_embed_data($type, $ids) {
        $items = [];
        
        foreach ($ids as $id) {
            $post = get_post($id);
            
            if (!$post || $post->post_status !== 'publish') {
                continue;
            }
            
            // Base data for all types
            $base_data = [
                'id' => $post->ID,
                'title' => html_entity_decode($post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'slug' => $post->post_name,
                'excerpt' => $post->post_excerpt ? $post->post_excerpt : wp_trim_words($post->post_content, 20),
                'image' => $this->get_featured_image($post->ID),
                'url' => $this->get_post_url($post),
                'embed_type' => $type,
            ];
            
            // Get type-specific data
            switch ($type) {
                case 'recipe':
                    $items[] = $this->get_recipe_embed_data($post, $base_data);
                    break;
                case 'ingredient':
                    $items[] = $this->get_ingredient_embed_data($post, $base_data);
                    break;
                case 'tool':
                    $items[] = $this->get_tool_embed_data($post, $base_data);
                    break;
                case 'post':
                    $items[] = $this->get_post_embed_data($post, $base_data);
                    break;
            }
        }
        
        return $items;
    }
    
    /**
     * Get recipe-specific embed data
     */
    private function get_recipe_embed_data($post, $base_data) {
        $prep_time = get_post_meta($post->ID, '_kg_prep_time', true);
        $is_featured = get_post_meta($post->ID, '_kg_is_featured', true);
        
        // Get age group
        $age_groups = wp_get_post_terms($post->ID, 'age-group');
        $age_group = !empty($age_groups) ? $age_groups[0]->name : '';
        
        // Get diet types
        $diet_types = wp_get_post_terms($post->ID, 'diet-type');
        $diet_type_names = array_map(function($term) {
            return html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $diet_types);
        
        // Get allergens
        $allergens = wp_get_post_terms($post->ID, 'allergen');
        $allergen_names = array_map(function($term) {
            return html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $allergens);
        
        return array_merge($base_data, [
            'prep_time' => $prep_time ? $prep_time : '',
            'age_group' => html_entity_decode($age_group, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'age_group_color' => $this->get_age_group_color($post->ID),
            'diet_types' => $diet_type_names,
            'allergens' => $allergen_names,
            'is_featured' => $is_featured === '1',
        ]);
    }
    
    /**
     * Get ingredient-specific embed data
     */
    private function get_ingredient_embed_data($post, $base_data) {
        $start_age = get_post_meta($post->ID, '_kg_start_age', true);
        $benefits = get_post_meta($post->ID, '_kg_benefits', true);
        $allergy_risk = get_post_meta($post->ID, '_kg_allergy_risk', true);
        $season = get_post_meta($post->ID, '_kg_season', true);
        
        // Get allergens
        $allergens = wp_get_post_terms($post->ID, 'allergen');
        $allergen_names = array_map(function($term) {
            return html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $allergens);
        
        return array_merge($base_data, [
            'start_age' => $start_age ? $start_age : '',
            'benefits' => $benefits ? $benefits : '',
            'allergy_risk' => $allergy_risk ? $allergy_risk : '',
            'allergens' => $allergen_names,
            'season' => $season ? $season : '',
        ]);
    }
    
    /**
     * Get tool-specific embed data
     */
    private function get_tool_embed_data($post, $base_data) {
        $tool_type = get_post_meta($post->ID, '_kg_tool_type', true);
        $tool_icon = get_post_meta($post->ID, '_kg_tool_icon', true);
        $tool_types = get_post_meta($post->ID, '_kg_tool_types', true);
        $is_active = get_post_meta($post->ID, '_kg_is_active', true);
        
        return array_merge($base_data, [
            'tool_type' => $tool_type ? $tool_type : '',
            'tool_icon' => $tool_icon ? $tool_icon : '',
            'tool_types' => is_array($tool_types) ? $tool_types : [],
            'is_active' => $is_active === '1',
        ]);
    }
    
    /**
     * Get post-specific embed data (Keşfet)
     */
    private function get_post_embed_data($post, $base_data) {
        // Get category
        $categories = get_the_category($post->ID);
        $category_data = !empty($categories) ? [
            'name' => html_entity_decode($categories[0]->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'slug' => $categories[0]->slug,
        ] : null;
        
        // Get author data
        $author_data = $this->get_author_data($post);
        
        // Calculate read time
        $read_time = $this->calculate_read_time($post->post_content);
        
        return array_merge($base_data, [
            'category' => $category_data,
            'author' => $author_data,
            'date' => get_the_date('Y-m-d', $post->ID),
            'read_time' => $read_time,
        ]);
    }
    
    /**
     * Get age group color
     */
    private function get_age_group_color($post_id) {
        $age_groups = wp_get_post_terms($post_id, 'age-group');
        
        if (empty($age_groups)) {
            return '#CCCCCC';
        }
        
        $age_group_slug = $age_groups[0]->slug;
        
        return isset($this->age_group_colors[$age_group_slug]) ? $this->age_group_colors[$age_group_slug] : '#CCCCCC';
    }
    
    /**
     * Get post URL based on post type
     */
    private function get_post_url($post) {
        $prefix = isset($this->url_prefixes[$post->post_type]) ? $this->url_prefixes[$post->post_type] : $post->post_type;
        return '/' . $prefix . '/' . $post->post_name;
    }
    
    /**
     * Get featured image URL
     */
    private function get_featured_image($post_id) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'large');
            return $image_url ? $image_url : null;
        }
        
        return null;
    }
    
    /**
     * Get author data
     */
    private function get_author_data($post) {
        $author = get_userdata($post->post_author);
        
        return [
            'name' => $author ? $author->display_name : 'KidsGourmet Editörü',
            'avatar' => get_avatar_url($post->post_author, ['size' => 96]),
        ];
    }
    
    /**
     * Calculate read time
     */
    private function calculate_read_time($content) {
        $content = strip_tags($content);
        $word_count = str_word_count($content);
        $minutes = ceil($word_count / 200);
        
        return $minutes . ' dk';
    }
}

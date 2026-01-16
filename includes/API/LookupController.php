<?php
namespace KG_Core\API;

class LookupController {
    
    /**
     * Frontend domain
     */
    private $frontend_url;
    
    /**
     * Content type to URL prefix mapping
     */
    private $type_prefixes = [
        'recipe'     => '/tarifler',
        'post'       => '/kesfet',
        'ingredient' => '/beslenme-rehberi',
        'discussion' => '/topluluk/soru',
        'expert'     => '/uzman',
        'category'   => '/kesfet/kategori',
        'post_tag'   => '/etiket',
    ];
    
    public function __construct() {
        $this->frontend_url = defined('KG_FRONTEND_URL') 
            ? KG_FRONTEND_URL 
            : 'https://kidsgourmet.com.tr';
            
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('kg/v1', '/lookup', [
            'methods'             => 'GET',
            'callback'            => [$this, 'lookup_slug'],
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => [
                'slug' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_title',
                    'validate_callback' => function($param) {
                        return !empty($param);
                    }
                ]
            ]
        ]);
    }
    
    /**
     * Lookup slug and return content type and redirect URL
     */
    public function lookup_slug(\WP_REST_Request $request) {
        $slug = $request->get_param('slug');
        
        // Trailing slash ve gereksiz karakterleri temizle
        $slug = trim($slug, '/');
        $slug = sanitize_title($slug);
        
        if (empty($slug)) {
            return new \WP_REST_Response([
                'found'    => false,
                'type'     => null,
                'slug'     => $slug,
                'id'       => null,
                'redirect' => null,
            ], 200);
        }
        
        // 1. Önce Recipe'lerde ara
        $recipe = $this->find_by_slug($slug, 'recipe');
        if ($recipe) {
            return $this->build_response('recipe', $recipe);
        }
        
        // 2. Post'larda ara
        $post = $this->find_by_slug($slug, 'post');
        if ($post) {
            return $this->build_response('post', $post);
        }
        
        // 3. Ingredient'larda ara
        $ingredient = $this->find_by_slug($slug, 'ingredient');
        if ($ingredient) {
            return $this->build_response('ingredient', $ingredient);
        }
        
        // 4. Discussion'larda ara
        $discussion = $this->find_by_slug($slug, 'discussion');
        if ($discussion) {
            return $this->build_response('discussion', $discussion);
        }
        
        // 5. Kategorilerde ara
        $category = get_category_by_slug($slug);
        if ($category) {
            return new \WP_REST_Response([
                'found'    => true,
                'type'     => 'category',
                'slug'     => $category->slug,
                'id'       => $category->term_id,
                'redirect' => $this->type_prefixes['category'] . '/' . $category->slug,
            ], 200);
        }
        
        // 6. Etiketlerde ara
        $tag = get_term_by('slug', $slug, 'post_tag');
        if ($tag) {
            return new \WP_REST_Response([
                'found'    => true,
                'type'     => 'post_tag',
                'slug'     => $tag->slug,
                'id'       => $tag->term_id,
                'redirect' => $this->type_prefixes['post_tag'] . '/' . $tag->slug,
            ], 200);
        }
        
        // Bulunamadı
        return new \WP_REST_Response([
            'found'    => false,
            'type'     => null,
            'slug'     => $slug,
            'id'       => null,
            'redirect' => null,
        ], 200);
    }
    
    /**
     * Find post by slug and type
     */
    private function find_by_slug($slug, $post_type) {
        $posts = get_posts([
            'name'        => $slug,
            'post_type'   => $post_type,
            'post_status' => 'publish',
            'numberposts' => 1,
        ]);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    /**
     * Build success response
     */
    private function build_response($type, $post) {
        $prefix = $this->type_prefixes[$type] ?? '';
        
        return new \WP_REST_Response([
            'found'    => true,
            'type'     => $type,
            'slug'     => $post->post_name,
            'id'       => $post->ID,
            'redirect' => $prefix . '/' . $post->post_name,
        ], 200);
    }
}

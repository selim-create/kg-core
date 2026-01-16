<?php
namespace KG_Core\Redirect;

class FrontendRedirect {
    
    /**
     * Frontend domain
     */
    private $frontend_url;
    
    /**
     * Excluded paths (redirect yapılmayacak)
     */
    private $excluded_paths = [
        '/wp-admin',
        '/wp-login.php',
        '/wp-json',
        '/wp-content',
        '/wp-includes',
        '/xmlrpc.php',
        '/wp-cron.php',
        '/favicon.ico',
        '/robots.txt',
        '/sitemap',
    ];
    
    public function __construct() {
        $this->frontend_url = defined('KG_FRONTEND_URL') 
            ? KG_FRONTEND_URL 
            : 'https://kidsgourmet.com.tr';
        
        // Erken aşamada redirect kontrolü
        add_action('template_redirect', [$this, 'maybe_redirect_to_frontend'], 1);
        
        // Parse request aşamasında da kontrol (daha erken)
        add_action('parse_request', [$this, 'early_redirect_check'], 1);
    }
    
    /**
     * Check if current path should be excluded from redirect
     */
    private function is_excluded_path($request_uri) {
        foreach ($this->excluded_paths as $excluded) {
            if (strpos($request_uri, $excluded) === 0) {
                return true;
            }
        }
        
        // Admin AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }
        
        // REST API
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        
        // Admin
        if (is_admin()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Early redirect check (parse_request hook)
     */
    public function early_redirect_check($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Excluded path'leri atla
        if ($this->is_excluded_path($request_uri)) {
            return;
        }
        
        // Login sayfasını atla
        if (strpos($request_uri, 'wp-login') !== false) {
            return;
        }
        
        $this->do_redirect($request_uri);
    }
    
    /**
     * Template redirect hook
     */
    public function maybe_redirect_to_frontend() {
        // Admin, REST API, AJAX isteklerini atla
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Excluded path'leri atla
        if ($this->is_excluded_path($request_uri)) {
            return;
        }
        
        $this->do_redirect($request_uri);
    }
    
    /**
     * Perform the redirect
     */
    private function do_redirect($request_uri) {
        // Slug'ı temizle
        $path = parse_url($request_uri, PHP_URL_PATH);
        $path = trim($path, '/');
        
        if (empty($path)) {
            // Ana sayfa
            $redirect_url = $this->frontend_url;
        } else {
            // Lookup ile doğru URL'yi bul
            $redirect_url = $this->get_redirect_url($path);
        }
        
        // 301 Permanent Redirect
        wp_redirect($redirect_url, 301);
        exit;
    }
    
    /**
     * Get redirect URL for a path
     */
    private function get_redirect_url($path) {
        // Path'i segmentlere ayır
        $segments = explode('/', $path);
        $slug = end($segments); // Son segment'i al
        
        // Slug ile içerik ara
        
        // 1. Recipe
        $recipe = get_page_by_path($slug, OBJECT, 'recipe');
        if ($recipe && $recipe->post_status === 'publish') {
            return $this->frontend_url . '/tarifler/' . $recipe->post_name;
        }
        
        // 2. Post
        $post = get_page_by_path($slug, OBJECT, 'post');
        if ($post && $post->post_status === 'publish') {
            return $this->frontend_url . '/kesfet/' . $post->post_name;
        }
        
        // 3. Ingredient
        $ingredient = get_page_by_path($slug, OBJECT, 'ingredient');
        if ($ingredient && $ingredient->post_status === 'publish') {
            return $this->frontend_url . '/beslenme-rehberi/' . $ingredient->post_name;
        }
        
        // 4. Discussion
        $discussion = get_page_by_path($slug, OBJECT, 'discussion');
        if ($discussion && $discussion->post_status === 'publish') {
            return $this->frontend_url . '/topluluk/soru/' . $discussion->post_name;
        }
        
        // 5. Kategori
        $category = get_category_by_slug($slug);
        if ($category) {
            return $this->frontend_url . '/kesfet/kategori/' . $category->slug;
        }
        
        // 6. Etiket
        $tag = get_term_by('slug', $slug, 'post_tag');
        if ($tag) {
            return $this->frontend_url . '/etiket/' . $tag->slug;
        }
        
        // Bulunamadı - yine de frontend'e yönlendir (404 orada handle edilir)
        return $this->frontend_url . '/kesfet/' . $slug;
    }
}

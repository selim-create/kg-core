<?php
namespace KG_Core\Admin;

class FrontendViewLinks {
    
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
    ];
    
    public function __construct() {
        $this->frontend_url = defined('KG_FRONTEND_URL') 
            ? KG_FRONTEND_URL 
            : 'https://kidsgourmet.com.tr';
        
        // Admin bar'a buton ekle
        add_action('admin_bar_menu', [$this, 'add_frontend_view_button'], 100);
        
        // Post list'te link değiştir
        add_filter('post_row_actions', [$this, 'modify_row_actions'], 10, 2);
        add_filter('page_row_actions', [$this, 'modify_row_actions'], 10, 2);
        
        // Preview link'i değiştir
        add_filter('preview_post_link', [$this, 'modify_preview_link'], 10, 2);
        
        // View link'i değiştir
        add_filter('post_link', [$this, 'modify_post_link'], 10, 2);
        add_filter('post_type_link', [$this, 'modify_post_type_link'], 10, 2);
        
        // Admin edit sayfasında bilgi kutusu
        add_action('edit_form_after_title', [$this, 'add_frontend_link_notice']);
        
        // Admin CSS
        add_action('admin_head', [$this, 'add_admin_styles']);
    }
    
    /**
     * Get frontend URL for a post
     */
    public function get_frontend_url($post) {
        if (!$post) return null;
        
        $post_type = $post->post_type;
        $slug = $post->post_name;
        
        if (!$slug) return null;
        
        $prefix = $this->type_prefixes[$post_type] ?? '/kesfet';
        
        return $this->frontend_url . $prefix . '/' . $slug;
    }
    
    /**
     * Add frontend view button to admin bar
     */
    public function add_frontend_view_button($wp_admin_bar) {
        if (!is_admin()) return;
        
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') return;
        
        global $post;
        if (!$post || $post->post_status !== 'publish') return;
        
        $frontend_url = $this->get_frontend_url($post);
        if (!$frontend_url) return;
        
        $wp_admin_bar->add_node([
            'id'    => 'kg-view-frontend',
            'title' => '<span class="ab-icon dashicons dashicons-external"></span> Frontend\'de Görüntüle',
            'href'  => $frontend_url,
            'meta'  => [
                'target' => '_blank',
                'class'  => 'kg-frontend-view-btn',
                'title'  => 'Bu içeriği frontend sitesinde görüntüle',
            ],
        ]);
    }
    
    /**
     * Modify row actions in post list
     */
    public function modify_row_actions($actions, $post) {
        if ($post->post_status !== 'publish') {
            return $actions;
        }
        
        $frontend_url = $this->get_frontend_url($post);
        if (!$frontend_url) {
            return $actions;
        }
        
        // "Görüntüle" linkini frontend URL'si ile değiştir
        if (isset($actions['view'])) {
            $actions['view'] = sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url($frontend_url),
                esc_html__('Frontend\'de Gör', 'kg-core')
            );
        }
        
        return $actions;
    }
    
    /**
     * Modify preview link
     */
    public function modify_preview_link($preview_link, $post) {
        // Preview için frontend URL kullan (draft'lar için çalışmaz)
        if ($post->post_status === 'publish') {
            $frontend_url = $this->get_frontend_url($post);
            if ($frontend_url) {
                return $frontend_url;
            }
        }
        
        return $preview_link;
    }
    
    /**
     * Modify post permalink
     */
    public function modify_post_link($permalink, $post) {
        // Admin'de post link'lerini frontend'e yönlendir
        if (is_admin() && $post->post_status === 'publish') {
            $frontend_url = $this->get_frontend_url($post);
            if ($frontend_url) {
                return $frontend_url;
            }
        }
        
        return $permalink;
    }
    
    /**
     * Modify custom post type permalink
     */
    public function modify_post_type_link($permalink, $post) {
        return $this->modify_post_link($permalink, $post);
    }
    
    /**
     * Add frontend link notice on edit page
     */
    public function add_frontend_link_notice($post) {
        if ($post->post_status !== 'publish') return;
        
        $frontend_url = $this->get_frontend_url($post);
        if (!$frontend_url) return;
        
        $post_type_labels = [
            'recipe'     => 'Tarif',
            'post'       => 'Yazı',
            'ingredient' => 'Malzeme',
            'discussion' => 'Tartışma',
        ];
        
        $type_label = $post_type_labels[$post->post_type] ?? 'İçerik';
        
        echo '<div class="kg-frontend-notice">';
        echo '<span class="dashicons dashicons-external"></span> ';
        echo '<strong>' . esc_html($type_label) . ' Frontend URL:</strong> ';
        echo '<a href="' . esc_url($frontend_url) . '" target="_blank" rel="noopener">';
        echo esc_html($frontend_url);
        echo '</a>';
        echo '</div>';
    }
    
    /**
     * Add admin styles
     */
    public function add_admin_styles() {
        echo '<style>
            .kg-frontend-notice {
                background: #e7f5ff;
                border: 1px solid #74c0fc;
                border-radius: 4px;
                padding: 10px 15px;
                margin: 10px 0 20px 0;
                font-size: 13px;
            }
            .kg-frontend-notice .dashicons {
                color: #1c7ed6;
                margin-right: 5px;
            }
            .kg-frontend-notice a {
                color: #1c7ed6;
                text-decoration: none;
            }
            .kg-frontend-notice a:hover {
                text-decoration: underline;
            }
            #wp-admin-bar-kg-view-frontend a {
                background: #f59f00 !important;
                color: #fff !important;
            }
            #wp-admin-bar-kg-view-frontend a:hover {
                background: #f08c00 !important;
            }
            #wp-admin-bar-kg-view-frontend .ab-icon {
                margin-right: 5px;
            }
        </style>';
    }
}

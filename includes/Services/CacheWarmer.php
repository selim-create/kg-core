<?php
namespace KG_Core\Services;

/**
 * Cache Warmer Service
 * 
 * Pre-warms caches for frequently accessed data to improve performance
 * Runs on a scheduled cron job (hourly)
 */
class CacheWarmer {
    
    public function __construct() {
        add_action('kg_cache_warm', [$this, 'warm_caches']);
        
        // Schedule cache warming if not already scheduled
        if (!wp_next_scheduled('kg_cache_warm')) {
            wp_schedule_event(time(), 'hourly', 'kg_cache_warm');
        }
    }
    
    /**
     * Main cache warming method
     */
    public function warm_caches() {
        $this->warm_featured_cache();
        $this->warm_popular_recipes();
        
        do_action('kg_cache_warmed');
    }
    
    /**
     * Warm featured content cache
     */
    private function warm_featured_cache() {
        foreach (['all', 'recipe', 'post'] as $type) {
            foreach ([5, 10] as $limit) {
                // Check if cache already exists
                if (CacheService::get_featured($type, $limit) !== null) {
                    continue;
                }
                
                // Fetch and cache featured content
                $data = $this->fetch_featured($type, $limit);
                if (!empty($data)) {
                    CacheService::set_featured($data, $type, $limit);
                }
            }
        }
    }
    
    /**
     * Warm popular recipes cache
     */
    private function warm_popular_recipes() {
        global $wpdb;
        
        // Get top 20 popular recipes by rating * rating_count
        $ids = $wpdb->get_col("
            SELECT p.ID 
            FROM {$wpdb->posts} p 
            LEFT JOIN {$wpdb->prefix}kg_recipe_meta m ON p.ID = m.post_id 
            WHERE p.post_type = 'recipe' 
            AND p.post_status = 'publish' 
            ORDER BY (COALESCE(m.rating, 0) * COALESCE(m.rating_count, 0)) DESC 
            LIMIT 20
        ");
        
        foreach ($ids as $id) {
            // Check if recipe is already cached
            if (CacheService::get_recipe($id) === null) {
                // Recipe will be cached on next request
                // We don't fetch here to avoid expensive operations
            }
        }
    }
    
    /**
     * Fetch featured content from database
     * 
     * @param string $type Content type (all, recipe, post)
     * @param int $limit Number of items to fetch
     * @return array Featured items
     */
    private function fetch_featured($type, $limit) {
        $args = [
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => [
                [
                    'key' => '_kg_is_featured',
                    'value' => '1',
                ],
            ],
        ];
        
        // Set post type
        $args['post_type'] = $type === 'all' ? ['recipe', 'post', 'ingredient'] : $type;
        
        $query = new \WP_Query($args);
        $items = [];
        
        while ($query->have_posts()) {
            $query->the_post();
            $items[] = [
                'id' => get_the_ID(),
                'type' => get_post_type(),
                'title' => get_the_title(),
                'slug' => get_post_field('post_name'),
                'image' => get_the_post_thumbnail_url(get_the_ID(), 'medium'),
            ];
        }
        
        wp_reset_postdata();
        
        return $items;
    }
    
    /**
     * Manually trigger cache warming
     * 
     * @return void
     */
    public static function trigger() {
        (new self())->warm_caches();
    }
    
    /**
     * Deactivation cleanup
     * 
     * @return void
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('kg_cache_warm');
    }
}

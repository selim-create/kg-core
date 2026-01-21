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
        // Get top 20 popular recipes by rating * rating_count using WP_Query
        // First, get all published recipes
        $args = [
            'post_type' => 'recipe',
            'post_status' => 'publish',
            'posts_per_page' => 100, // Get a larger set to sort
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        
        $query = new \WP_Query($args);
        $recipe_ids = $query->posts;
        
        if (empty($recipe_ids)) {
            return;
        }
        
        // Get popularity scores from custom table
        global $wpdb;
        $table = $wpdb->prefix . 'kg_recipe_meta';
        
        // Check if custom table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return;
        }
        
        // Get popularity scores for these recipes
        $ids_placeholder = implode(',', array_fill(0, count($recipe_ids), '%d'));
        $scores = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, (COALESCE(rating, 0) * COALESCE(rating_count, 0)) as score 
                FROM {$table} 
                WHERE post_id IN ({$ids_placeholder}) 
                ORDER BY score DESC 
                LIMIT 20",
                ...$recipe_ids
            )
        );
        
        foreach ($scores as $score) {
            // Check if recipe is already cached
            if (CacheService::get_recipe($score->post_id) === null) {
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

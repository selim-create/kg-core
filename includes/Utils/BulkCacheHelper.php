<?php
namespace KG_Core\Utils;

/**
 * Bulk Cache Helper
 * 
 * Provides methods to prime WordPress caches for multiple posts at once,
 * preventing N+1 query problems in API endpoints.
 */
class BulkCacheHelper {
    
    /**
     * Prime caches for recipe list
     * 
     * @param array $posts Array of WP_Post objects
     */
    public static function prime_recipe_caches( $posts ) {
        if ( empty( $posts ) ) {
            return;
        }
        
        $post_ids = wp_list_pluck( $posts, 'ID' );
        
        // Prime meta cache
        update_meta_cache( 'post', $post_ids );
        
        // Prime term cache for all recipe-related taxonomies
        update_object_term_cache( $post_ids, ['age-group', 'meal-type', 'diet-type', 'allergen', 'special-condition'] );
        
        // Prime author cache
        $author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
        if ( ! empty( $author_ids ) ) {
            cache_users( $author_ids );
        }
        
        // Prime term meta cache (age group colors)
        self::prime_term_meta_cache( $posts, 'age-group' );
    }
    
    /**
     * Prime caches for post/blog list
     * 
     * @param array $posts Array of WP_Post objects
     */
    public static function prime_post_caches( $posts ) {
        if ( empty( $posts ) ) {
            return;
        }
        
        $post_ids = wp_list_pluck( $posts, 'ID' );
        
        // Prime meta cache
        update_meta_cache( 'post', $post_ids );
        
        // Prime term cache for categories
        update_object_term_cache( $post_ids, 'category' );
        
        // Prime author cache
        $author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
        if ( ! empty( $author_ids ) ) {
            cache_users( $author_ids );
        }
    }
    
    /**
     * Prime caches for ingredient list
     * 
     * @param array $posts Array of WP_Post objects
     */
    public static function prime_ingredient_caches( $posts ) {
        if ( empty( $posts ) ) {
            return;
        }
        
        $post_ids = wp_list_pluck( $posts, 'ID' );
        
        // Prime meta cache
        update_meta_cache( 'post', $post_ids );
        
        // Prime term cache for ingredient categories and allergens
        update_object_term_cache( $post_ids, ['ingredient-category', 'allergen'] );
    }
    
    /**
     * Prime caches for discussion list
     * 
     * @param array $posts Array of WP_Post objects
     */
    public static function prime_discussion_caches( $posts ) {
        if ( empty( $posts ) ) {
            return;
        }
        
        $post_ids = wp_list_pluck( $posts, 'ID' );
        
        // Prime meta cache
        update_meta_cache( 'post', $post_ids );
        
        // Prime term cache for community circles
        update_object_term_cache( $post_ids, 'community_circle' );
        
        // Prime author cache
        $author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
        if ( ! empty( $author_ids ) ) {
            cache_users( $author_ids );
        }
        
        // Prime circle term meta cache (icons, colors)
        self::prime_term_meta_cache( $posts, 'community_circle' );
    }
    
    /**
     * Prime caches for search results (mixed content)
     * 
     * @param array $posts Array of WP_Post objects
     */
    public static function prime_search_caches( $posts ) {
        if ( empty( $posts ) ) {
            return;
        }
        
        $post_ids = wp_list_pluck( $posts, 'ID' );
        
        // Prime meta cache
        update_meta_cache( 'post', $post_ids );
        
        // Prime term cache for all possible taxonomies
        $taxonomies = ['age-group', 'meal-type', 'diet-type', 'allergen', 'ingredient-category', 'community_circle', 'category'];
        foreach ( $taxonomies as $tax ) {
            update_object_term_cache( $post_ids, $tax );
        }
        
        // Prime author cache
        $author_ids = array_unique( wp_list_pluck( $posts, 'post_author' ) );
        if ( ! empty( $author_ids ) ) {
            cache_users( $author_ids );
        }
    }
    
    /**
     * Prime user cache for comments
     * 
     * @param array $comments Array of WP_Comment objects
     */
    public static function prime_comment_user_caches( $comments ) {
        if ( empty( $comments ) ) {
            return;
        }
        
        // Extract user IDs from comments
        $user_ids = array_filter( array_unique( wp_list_pluck( $comments, 'user_id' ) ) );
        
        if ( ! empty( $user_ids ) ) {
            cache_users( $user_ids );
        }
    }
    
    /**
     * Prime term meta cache (e.g., color codes, icons)
     * 
     * @param array $posts Array of WP_Post objects
     * @param string $taxonomy Taxonomy name
     */
    private static function prime_term_meta_cache( $posts, $taxonomy ) {
        $term_ids = [];
        
        foreach ( $posts as $post ) {
            $terms = get_object_term_cache( $post->ID, $taxonomy );
            if ( $terms ) {
                foreach ( $terms as $term ) {
                    $term_ids[] = $term->term_id;
                }
            }
        }
        
        if ( ! empty( $term_ids ) ) {
            update_termmeta_cache( array_unique( $term_ids ) );
        }
    }
}

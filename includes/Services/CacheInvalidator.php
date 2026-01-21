<?php
namespace KG_Core\Services;

/**
 * CacheInvalidator - Cache temizleme hook'ları
 * 
 * Bu sınıf WordPress hook'larını dinleyerek ilgili cache'leri
 * otomatik olarak temizler. Böylece kullanıcılar her zaman
 * güncel veriyi görür.
 */
class CacheInvalidator {
    
    public function __construct() {
        // Recipe güncellendiğinde
        add_action('save_post_recipe', [$this, 'on_recipe_save'], 20, 3);
        
        // Ingredient güncellendiğinde
        add_action('save_post_ingredient', [$this, 'on_ingredient_save'], 20, 3);
        
        // Post meta güncellendiğinde
        add_action('updated_post_meta', [$this, 'on_meta_update'], 10, 4);
        add_action('added_post_meta', [$this, 'on_meta_update'], 10, 4);
        add_action('deleted_post_meta', [$this, 'on_meta_delete'], 10, 4);
        
        // Yorum eklendiğinde
        add_action('wp_insert_comment', [$this, 'on_comment_insert'], 10, 2);
        add_action('edit_comment', [$this, 'on_comment_edit'], 10, 2);
        add_action('delete_comment', [$this, 'on_comment_delete'], 10, 2);
        add_action('wp_set_comment_status', [$this, 'on_comment_status_change'], 10, 2);
        
        // Term güncellendiğinde (taxonomy)
        add_action('edited_term', [$this, 'on_term_edit'], 10, 3);
        add_action('created_term', [$this, 'on_term_create'], 10, 3);
        add_action('delete_term', [$this, 'on_term_delete'], 10, 3);
        
        // Post silindiğinde
        add_action('before_delete_post', [$this, 'on_post_delete'], 10, 1);
        
        // Featured status değiştiğinde
        add_action('kg_featured_status_changed', [$this, 'on_featured_change'], 10, 2);
        
        // Rating yapıldığında
        add_action('kg_recipe_rated', [$this, 'on_recipe_rated'], 10, 2);
    }
    
    /**
     * Recipe kaydedildiğinde
     */
    public function on_recipe_save($post_id, $post, $update) {
        // Autosave'i atla
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Revision'ları atla
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        CacheService::invalidate_recipe($post_id);
        CacheService::invalidate_featured();
        CacheService::invalidate_list('recipes');
    }
    
    /**
     * Ingredient kaydedildiğinde
     */
    public function on_ingredient_save($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        CacheService::invalidate_list('ingredients');
        CacheService::invalidate_featured();
    }
    
    /**
     * Post meta güncellendiğinde
     */
    public function on_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Sadece bizim meta'larımızı dinle
        if (strpos($meta_key, '_kg_') !== 0) {
            return;
        }
        
        $post_type = get_post_type($post_id);
        
        switch ($post_type) {
            case 'recipe':
                CacheService::invalidate_recipe($post_id);
                
                // Featured veya rating değiştiyse listeleri de güncelle
                if (in_array($meta_key, ['_kg_is_featured', '_kg_rating', '_kg_rating_count'])) {
                    CacheService::invalidate_featured();
                    CacheService::invalidate_list('recipes');
                }
                break;
                
            case 'ingredient':
                CacheService::invalidate_list('ingredients');
                if ($meta_key === '_kg_is_featured') {
                    CacheService::invalidate_featured();
                }
                break;
                
            case 'post':
                if ($meta_key === '_kg_is_featured') {
                    CacheService::invalidate_featured();
                    CacheService::invalidate_list('posts');
                }
                break;
                
            case 'discussion':
                if ($meta_key === '_kg_is_featured') {
                    CacheService::invalidate_featured();
                }
                break;
        }
    }
    
    /**
     * Meta silindiğinde
     */
    public function on_meta_delete($meta_ids, $post_id, $meta_key, $meta_value) {
        $this->on_meta_update(0, $post_id, $meta_key, $meta_value);
    }
    
    /**
     * Yorum eklendiğinde
     */
    public function on_comment_insert($comment_id, $comment) {
        $post_id = $comment->comment_post_ID;
        $post_type = get_post_type($post_id);
        
        if ($post_type === 'recipe') {
            CacheService::invalidate_recipe($post_id);
        }
    }
    
    /**
     * Yorum düzenlendiğinde
     */
    public function on_comment_edit($comment_id, $data) {
        $comment = get_comment($comment_id);
        if ($comment) {
            $this->on_comment_insert($comment_id, $comment);
        }
    }
    
    /**
     * Yorum silindiğinde
     */
    public function on_comment_delete($comment_id, $comment) {
        if (is_object($comment)) {
            $this->on_comment_insert($comment_id, $comment);
        }
    }
    
    /**
     * Yorum durumu değiştiğinde
     */
    public function on_comment_status_change($comment_id, $status) {
        $comment = get_comment($comment_id);
        if ($comment) {
            $this->on_comment_insert($comment_id, $comment);
        }
    }
    
    /**
     * Term düzenlendiğinde
     */
    public function on_term_edit($term_id, $tt_id, $taxonomy) {
        $this->invalidate_by_taxonomy($taxonomy);
    }
    
    /**
     * Term oluşturulduğunda
     */
    public function on_term_create($term_id, $tt_id, $taxonomy) {
        $this->invalidate_by_taxonomy($taxonomy);
    }
    
    /**
     * Term silindiğinde
     */
    public function on_term_delete($term_id, $tt_id, $taxonomy) {
        $this->invalidate_by_taxonomy($taxonomy);
    }
    
    /**
     * Taxonomy'ye göre cache temizle
     */
    private function invalidate_by_taxonomy($taxonomy) {
        $recipe_taxonomies = ['age-group', 'meal-type', 'diet-type', 'allergen', 'special-condition'];
        $ingredient_taxonomies = ['ingredient-category'];
        
        if (in_array($taxonomy, $recipe_taxonomies)) {
            CacheService::invalidate_list('recipes');
        }
        
        if (in_array($taxonomy, $ingredient_taxonomies)) {
            CacheService::invalidate_list('ingredients');
        }
    }
    
    /**
     * Post silindiğinde
     */
    public function on_post_delete($post_id) {
        $post_type = get_post_type($post_id);
        
        switch ($post_type) {
            case 'recipe':
                CacheService::invalidate_recipe($post_id);
                CacheService::invalidate_list('recipes');
                CacheService::invalidate_featured();
                break;
                
            case 'ingredient':
                CacheService::invalidate_list('ingredients');
                CacheService::invalidate_featured();
                break;
        }
    }
    
    /**
     * Featured durumu değiştiğinde
     */
    public function on_featured_change($post_id, $is_featured) {
        CacheService::invalidate_featured();
        
        $post_type = get_post_type($post_id);
        CacheService::invalidate_list($post_type . 's');
    }
    
    /**
     * Recipe puanlandığında
     */
    public function on_recipe_rated($recipe_id, $user_id) {
        CacheService::invalidate_recipe($recipe_id);
        CacheService::invalidate_featured();
    }
}

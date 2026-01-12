<?php
namespace KG_Core\API;

use KG_Core\Services\TariftenService;

class CrossSellController {
    
    public function __construct() {
        add_action('wp_ajax_kg_fetch_tariften_suggestions', [$this, 'fetch_suggestions']);
    }
    
    public function fetch_suggestions() {
        check_ajax_referer('kg_cross_sell_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Yetkisiz']);
        }
        
        $ingredient = sanitize_text_field($_POST['ingredient'] ?? '');
        
        if (empty($ingredient)) {
            wp_send_json_error(['message' => 'Malzeme gerekli']);
        }
        
        $service = new TariftenService();
        $result = $service->getRecipesByIngredient($ingredient);
        
        if ($result['success']) {
            wp_send_json_success($result['recipes']);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
}

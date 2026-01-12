<?php
namespace KG_Core\API;

use KG_Core\Services\IngredientGenerator;

class AIController {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Generate ingredient endpoint
        register_rest_route('kg/v1', '/ai/generate-ingredient', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_ingredient'],
            'permission_callback' => [$this, 'check_manage_options'],
            'args' => [
                'name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return !empty($param);
                    }
                ]
            ]
        ]);
        
        // Get AI status endpoint
        register_rest_route('kg/v1', '/ai/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_status'],
            'permission_callback' => [$this, 'check_manage_options']
        ]);
    }
    
    /**
     * Permission callback - requires manage_options capability
     */
    public function check_manage_options() {
        return current_user_can('manage_options');
    }
    
    /**
     * Generate ingredient endpoint handler
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function generate_ingredient($request) {
        $ingredient_name = $request->get_param('name');
        
        // Check if already exists
        $existing = get_page_by_title($ingredient_name, OBJECT, 'ingredient');
        if ($existing) {
            return new \WP_Error(
                'already_exists',
                'Bu malzeme zaten mevcut.',
                ['status' => 409, 'post_id' => $existing->ID]
            );
        }
        
        // Generate ingredient
        $generator = new IngredientGenerator();
        $result = $generator->create($ingredient_name);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'post_id' => $result,
            'message' => 'Malzeme başarıyla oluşturuldu (taslak olarak)'
        ], 200);
    }
    
    /**
     * Get AI configuration status
     * 
     * @return WP_REST_Response
     */
    public function get_status() {
        $ai_provider = get_option('kg_ai_provider', 'openai');
        $ai_api_key = get_option('kg_ai_api_key', '');
        $unsplash_key = get_option('kg_unsplash_api_key', '');
        $pexels_key = get_option('kg_pexels_api_key', '');
        $auto_generate = get_option('kg_auto_generate_on_missing', false);
        
        return new \WP_REST_Response([
            'provider' => $ai_provider,
            'configured' => !empty($ai_api_key),
            'image_apis' => [
                'unsplash' => !empty($unsplash_key),
                'pexels' => !empty($pexels_key)
            ],
            'auto_generate_enabled' => $auto_generate
        ], 200);
    }
}

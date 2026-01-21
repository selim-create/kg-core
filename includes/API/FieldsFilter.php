<?php
namespace KG_Core\API;

/**
 * Fields Filter for REST API endpoints
 * 
 * Provides sparse fieldsets support to reduce response payload size
 */
class FieldsFilter {
    
    /**
     * Allowed fields per content type
     */
    private static $allowed_fields = [
        'recipe' => ['id', 'title', 'slug', 'excerpt', 'content', 'image', 'prep_time', 'cook_time', 'difficulty', 'serving_size', 'rating', 'rating_count', 'is_featured', 'age_group', 'age_group_color', 'meal_type', 'diet_types', 'allergens', 'ingredients', 'instructions', 'nutrition', 'expert', 'seo', 'created_at'],
        'ingredient' => ['id', 'title', 'slug', 'excerpt', 'content', 'image', 'start_age', 'allergy_risk', 'is_featured', 'season', 'nutrition', 'benefits', 'storage_tips', 'expert', 'seo'],
        'post' => ['id', 'title', 'slug', 'excerpt', 'content', 'image', 'author', 'categories', 'is_featured', 'is_sponsored', 'sponsor', 'read_time', 'seo', 'created_at'],
    ];
    
    /**
     * Default fields for list views (when no fields parameter provided)
     */
    private static $list_defaults = [
        'recipe' => ['id', 'title', 'slug', 'image', 'prep_time', 'difficulty', 'rating', 'age_group', 'age_group_color'],
        'ingredient' => ['id', 'title', 'slug', 'image', 'start_age', 'allergy_risk'],
        'post' => ['id', 'title', 'slug', 'image', 'excerpt', 'author', 'read_time', 'created_at'],
    ];
    
    /**
     * Get fields argument definition for register_rest_route
     * 
     * @return array Argument definition
     */
    public static function get_args() {
        return [
            'fields' => [
                'required' => false,
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
    
    /**
     * Parse fields parameter from request
     * 
     * @param WP_REST_Request $request Request object
     * @param string $content_type Content type (recipe, ingredient, post)
     * @param bool $is_list Whether this is a list view (enables default fields)
     * @return array|null Array of field names, or null for all fields
     */
    public static function parse($request, $content_type, $is_list = false) {
        $fields_param = $request->get_param('fields');
        
        // If no fields specified and this is a list view, use list defaults
        if (empty($fields_param)) {
            return $is_list && isset(self::$list_defaults[$content_type]) 
                ? self::$list_defaults[$content_type] 
                : null;
        }
        
        // Parse comma-separated fields
        $requested = array_map('trim', explode(',', $fields_param));
        $allowed = self::$allowed_fields[$content_type] ?? [];
        
        // Filter to only allowed fields
        $filtered = array_intersect($requested, $allowed);
        
        // Always include 'id' field
        if (!in_array('id', $filtered)) {
            array_unshift($filtered, 'id');
        }
        
        return $filtered;
    }
    
    /**
     * Filter data array to only include specified fields
     * 
     * @param array $data Data array
     * @param array|null $fields Fields to include (null = all fields)
     * @return array Filtered data array
     */
    public static function filter($data, $fields) {
        if ($fields === null) {
            return $data;
        }
        
        $filtered = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }
        
        return $filtered;
    }
    
    /**
     * Check if a field should be included in response
     * 
     * @param array|null $fields Fields array or null for all
     * @param string $field Field name to check
     * @return bool True if field should be included
     */
    public static function includes($fields, $field) {
        return $fields === null || in_array($field, $fields);
    }
}

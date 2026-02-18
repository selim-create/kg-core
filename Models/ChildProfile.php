<?php
namespace KG_Core\Models;

/**
 * ChildProfile Model
 * 
 * Handles database operations for child profiles
 */
class ChildProfile {
    
    /**
     * Get child profile by UUID
     * 
     * @param string $uuid Child UUID
     * @return object|null Child profile or null
     */
    public static function get_by_uuid( $uuid ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_child_profiles';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE uuid = %s",
            $uuid
        ) );
    }
    
    /**
     * Get child profiles by user ID
     * 
     * @param int $user_id User ID
     * @return array Child profiles
     */
    public static function get_by_user_id( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_child_profiles';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );
    }
    
    /**
     * Create child profile
     * 
     * @param array $data Child profile data
     * @return int|false Insert ID or false on failure
     */
    public static function create( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_child_profiles';
        
        $defaults = [
            'uuid' => wp_generate_uuid4(),
            'gender' => 'unspecified',
            'feeding_style' => 'mixed',
            'kvkk_consent' => true,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        // Encode JSON fields
        if ( isset( $data['allergies'] ) && is_array( $data['allergies'] ) ) {
            $data['allergies'] = json_encode( $data['allergies'] );
        }
        
        $result = $wpdb->insert( $table, $data );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update child profile
     * 
     * @param string $uuid Child UUID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public static function update( $uuid, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_child_profiles';
        
        // Add updated_at timestamp
        $data['updated_at'] = current_time( 'mysql' );
        
        // Encode JSON fields
        if ( isset( $data['allergies'] ) && is_array( $data['allergies'] ) ) {
            $data['allergies'] = json_encode( $data['allergies'] );
        }
        
        return $wpdb->update(
            $table,
            $data,
            [ 'uuid' => $uuid ]
        ) !== false;
    }
    
    /**
     * Delete child profile
     * 
     * @param string $uuid Child UUID
     * @return bool True on success, false on failure
     */
    public static function delete( $uuid ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_child_profiles';
        
        return $wpdb->delete( $table, [ 'uuid' => $uuid ] ) !== false;
    }
    
    /**
     * Update avatar path
     * 
     * @param string $uuid Child UUID
     * @param string|null $avatar_path Avatar path or null to remove
     * @return bool True on success, false on failure
     */
    public static function update_avatar( $uuid, $avatar_path ) {
        return self::update( $uuid, [ 'avatar_path' => $avatar_path ] );
    }
    
    /**
     * Check if child belongs to user
     * 
     * @param string $uuid Child UUID
     * @param int $user_id User ID
     * @return bool True if child belongs to user
     */
    public static function belongs_to_user( $uuid, $user_id ) {
        $child = self::get_by_uuid( $uuid );
        return $child && (int) $child->user_id === (int) $user_id;
    }
    
    /**
     * Format child profile for API response
     * 
     * @param object $child Child profile object
     * @return array Formatted child profile
     */
    public static function format_for_api( $child ) {
        if ( ! $child ) {
            return null;
        }
        
        return [
            'id' => $child->uuid,
            'name' => $child->name,
            'birth_date' => $child->birth_date,
            'gender' => $child->gender,
            'allergies' => json_decode( $child->allergies ?? '[]', true ),
            'feeding_style' => $child->feeding_style,
            'photo_id' => $child->photo_id ? (int) $child->photo_id : null,
            'avatar_path' => $child->avatar_path,
            'kvkk_consent' => (bool) $child->kvkk_consent,
            'created_at' => $child->created_at,
            'updated_at' => $child->updated_at,
        ];
    }
}

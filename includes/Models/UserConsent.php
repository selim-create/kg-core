<?php
namespace KG_Core\Models;

/**
 * UserConsent Model
 * 
 * Handles database operations for user consent records
 */
class UserConsent {
    
    /**
     * Get consent by user ID and type
     * 
     * @param int $user_id User ID
     * @param string $type Consent type (terms, marketing, sensitive_data)
     * @return object|null Consent record or null
     */
    public static function get_by_user_and_type( $user_id, $type ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_user_consents';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND consent_type = %s ORDER BY created_at DESC LIMIT 1",
            $user_id,
            $type
        ) );
    }
    
    /**
     * Get all consents by user ID
     * 
     * @param int $user_id User ID
     * @return array Consent records
     */
    public static function get_by_user_id( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_user_consents';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );
    }
    
    /**
     * Get active consents by user ID
     * 
     * @param int $user_id User ID
     * @return array Active consent records
     */
    public static function get_active_by_user_id( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_user_consents';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND consented = 1 AND revoked_at IS NULL ORDER BY created_at DESC",
            $user_id
        ) );
    }
    
    /**
     * Check if user has active consent of specific type
     * 
     * @param int $user_id User ID
     * @param string $type Consent type
     * @return bool True if user has active consent
     */
    public static function has_active_consent( $user_id, $type ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_user_consents';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND consent_type = %s AND consented = 1 AND revoked_at IS NULL",
            $user_id,
            $type
        ) );
        
        return (int) $count > 0;
    }
    
    /**
     * Create consent record
     * 
     * @param array $data Consent data
     * @return int|false Insert ID or false on failure
     */
    public static function create( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_user_consents';
        
        $defaults = [
            'consented' => false,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        // Convert boolean to int for MySQL
        if ( isset( $data['consented'] ) ) {
            $data['consented'] = $data['consented'] ? 1 : 0;
        }
        
        // Format timestamps
        if ( isset( $data['consented_at'] ) && ! empty( $data['consented_at'] ) ) {
            $data['consented_at'] = self::format_timestamp( $data['consented_at'] );
        }
        
        if ( isset( $data['revoked_at'] ) && ! empty( $data['revoked_at'] ) ) {
            $data['revoked_at'] = self::format_timestamp( $data['revoked_at'] );
        }
        
        $result = $wpdb->insert( $table, $data );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update consent record
     * 
     * @param int $id Consent ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public static function update( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_user_consents';
        
        // Add updated_at timestamp
        $data['updated_at'] = current_time( 'mysql' );
        
        // Convert boolean to int for MySQL
        if ( isset( $data['consented'] ) ) {
            $data['consented'] = $data['consented'] ? 1 : 0;
        }
        
        // Format timestamps
        if ( isset( $data['consented_at'] ) && ! empty( $data['consented_at'] ) ) {
            $data['consented_at'] = self::format_timestamp( $data['consented_at'] );
        }
        
        if ( isset( $data['revoked_at'] ) && ! empty( $data['revoked_at'] ) ) {
            $data['revoked_at'] = self::format_timestamp( $data['revoked_at'] );
        }
        
        return $wpdb->update(
            $table,
            $data,
            [ 'id' => $id ]
        ) !== false;
    }
    
    /**
     * Update consent by user ID and type
     * 
     * @param int $user_id User ID
     * @param string $type Consent type
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public static function update_by_user_and_type( $user_id, $type, $data ) {
        $existing = self::get_by_user_and_type( $user_id, $type );
        
        if ( $existing ) {
            return self::update( $existing->id, $data );
        }
        
        return false;
    }
    
    /**
     * Delete consent record
     * 
     * @param int $id Consent ID
     * @return bool True on success, false on failure
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_user_consents';
        
        return $wpdb->delete( $table, [ 'id' => $id ] ) !== false;
    }
    
    /**
     * Format consent for API response
     * 
     * @param object $consent Consent object
     * @return array Formatted consent
     */
    public static function format_for_api( $consent ) {
        if ( ! $consent ) {
            return null;
        }
        
        return [
            'id' => (int) $consent->id,
            'consent_type' => $consent->consent_type,
            'consented' => (bool) $consent->consented,
            'consented_at' => $consent->consented_at,
            'revoked_at' => $consent->revoked_at,
            'version' => $consent->version,
            'created_at' => $consent->created_at,
            'updated_at' => $consent->updated_at,
        ];
    }
    
    /**
     * Format timestamp to MySQL format
     * 
     * @param string $timestamp Timestamp in various formats
     * @return string MySQL formatted timestamp
     */
    private static function format_timestamp( $timestamp ) {
        // If already in MySQL format, return as is
        if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp ) ) {
            return $timestamp;
        }
        
        // Try to parse ISO 8601 format
        try {
            $dt = new \DateTime( $timestamp );
            return $dt->format( 'Y-m-d H:i:s' );
        } catch ( \Exception $e ) {
            return current_time( 'mysql' );
        }
    }
}

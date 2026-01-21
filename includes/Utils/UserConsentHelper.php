<?php
namespace KG_Core\Utils;

use KG_Core\Models\UserConsent;

/**
 * UserConsentHelper - Helper functions for user consent management
 */
class UserConsentHelper {
    
    /**
     * Check if user has active consent of specific type
     * 
     * @param int $user_id User ID
     * @param string $type Consent type (terms, marketing, sensitive_data)
     * @return bool True if user has active consent
     */
    public static function has_active_consent( $user_id, $type ) {
        return UserConsent::has_active_consent( $user_id, $type );
    }
    
    /**
     * Check if user has marketing consent
     * 
     * @param int $user_id User ID
     * @return bool True if user has marketing consent
     */
    public static function has_marketing_consent( $user_id ) {
        return self::has_active_consent( $user_id, 'marketing' );
    }
    
    /**
     * Check if user has sensitive data consent
     * 
     * @param int $user_id User ID
     * @return bool True if user has sensitive data consent
     */
    public static function has_sensitive_data_consent( $user_id ) {
        return self::has_active_consent( $user_id, 'sensitive_data' );
    }
    
    /**
     * Get all consents for a user formatted for API
     * 
     * @param int $user_id User ID
     * @return array Formatted consents
     */
    public static function get_user_consents( $user_id ) {
        $consents = UserConsent::get_by_user_id( $user_id );
        
        $formatted = [];
        foreach ( $consents as $consent ) {
            $formatted[] = UserConsent::format_for_api( $consent );
        }
        
        return $formatted;
    }
    
    /**
     * Get latest consent status for each type
     * 
     * @param int $user_id User ID
     * @return array Consent status by type
     */
    public static function get_consent_status( $user_id ) {
        $types = [ 'terms', 'marketing', 'sensitive_data' ];
        $status = [];
        
        foreach ( $types as $type ) {
            $consent = UserConsent::get_by_user_and_type( $user_id, $type );
            
            if ( $consent ) {
                $status[ $type ] = [
                    'consented' => (bool) $consent->consented,
                    'consented_at' => $consent->consented_at,
                    'revoked_at' => $consent->revoked_at,
                    'version' => $consent->version,
                ];
            } else {
                $status[ $type ] = [
                    'consented' => false,
                    'consented_at' => null,
                    'revoked_at' => null,
                    'version' => null,
                ];
            }
        }
        
        return $status;
    }
}

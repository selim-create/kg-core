<?php
namespace KG_Core\Utils;

/**
 * Privacy Helper Class
 * Filters sensitive user data for public endpoints
 */
class PrivacyHelper {
    
    /**
     * Filter user data for public profile view
     * Removes sensitive information like children, birth_date, email
     * 
     * @param array $user_data Full user data
     * @return array Filtered public data
     */
    public static function filter_public_profile( $user_data ) {
        // Define allowed fields for public view
        $allowed_fields = [
            'id',
            'display_name',
            'parent_role',
            'avatar_url',
            'badges',
            'stats',
            'recent_activity'
        ];
        
        $public_data = [];
        
        foreach ( $allowed_fields as $field ) {
            if ( isset( $user_data[ $field ] ) ) {
                $public_data[ $field ] = $user_data[ $field ];
            }
        }
        
        return $public_data;
    }
    
    /**
     * Remove children data from array
     * 
     * @param array $data User data
     * @return array Data without children
     */
    public static function remove_children_data( $data ) {
        if ( isset( $data['children'] ) ) {
            unset( $data['children'] );
        }
        return $data;
    }
    
    /**
     * Remove email from array
     * 
     * @param array $data User data
     * @return array Data without email
     */
    public static function remove_email( $data ) {
        if ( isset( $data['email'] ) ) {
            unset( $data['email'] );
        }
        return $data;
    }
    
    /**
     * Remove birth dates from children array
     * 
     * @param array $children Children array
     * @return array Children array without birth dates
     */
    public static function remove_birth_dates( $children ) {
        if ( ! is_array( $children ) ) {
            return $children;
        }
        
        return array_map( function( $child ) {
            if ( isset( $child['birth_date'] ) ) {
                unset( $child['birth_date'] );
            }
            return $child;
        }, $children );
    }
}

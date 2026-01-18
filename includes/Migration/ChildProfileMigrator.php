<?php
namespace KG_Core\Migration;

use KG_Core\Models\ChildProfile;

/**
 * ChildProfileMigrator
 * 
 * Migrates child profiles from user meta to child_profiles table
 */
class ChildProfileMigrator {
    
    /**
     * Migrate all child profiles from user meta to database
     * 
     * @return array Migration results
     */
    public static function migrate_all() {
        global $wpdb;
        
        $results = [
            'total_users' => 0,
            'total_children' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];
        
        // Get all users who have children in meta
        $users_with_children = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_kg_children'"
        );
        
        $results['total_users'] = count( $users_with_children );
        
        foreach ( $users_with_children as $user_meta ) {
            $user_id = $user_meta->user_id;
            $children = maybe_unserialize( $user_meta->meta_value );
            
            if ( ! is_array( $children ) ) {
                continue;
            }
            
            foreach ( $children as $child ) {
                $results['total_children']++;
                
                // Check if child already exists in database
                $existing = ChildProfile::get_by_uuid( $child['id'] ?? '' );
                if ( $existing ) {
                    $results['skipped']++;
                    continue;
                }
                
                // Prepare child data for insertion
                $child_data = [
                    'uuid' => $child['id'] ?? wp_generate_uuid4(),
                    'user_id' => $user_id,
                    'name' => $child['name'] ?? '',
                    'birth_date' => $child['birth_date'] ?? '',
                    'gender' => $child['gender'] ?? 'unspecified',
                    'allergies' => $child['allergies'] ?? [],
                    'feeding_style' => $child['feeding_style'] ?? 'mixed',
                    'photo_id' => isset( $child['photo_id'] ) && $child['photo_id'] > 0 ? $child['photo_id'] : null,
                    'kvkk_consent' => isset( $child['kvkk_consent'] ) ? (bool) $child['kvkk_consent'] : true,
                ];
                
                // Set created_at if available
                if ( ! empty( $child['created_at'] ) ) {
                    $created_at = $child['created_at'];
                    // Convert ISO 8601 to MySQL datetime if needed
                    if ( strpos( $created_at, 'T' ) !== false ) {
                        $dt = new \DateTime( $created_at );
                        $child_data['created_at'] = $dt->format( 'Y-m-d H:i:s' );
                    } else {
                        $child_data['created_at'] = $created_at;
                    }
                }
                
                // Insert into database
                $insert_id = ChildProfile::create( $child_data );
                
                if ( $insert_id ) {
                    $results['migrated']++;
                } else {
                    $results['errors'][] = sprintf(
                        'Failed to migrate child %s for user %d',
                        $child_data['uuid'],
                        $user_id
                    );
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Rollback migration - move children back to user meta and delete from table
     * (For testing/development purposes)
     * 
     * @return array Rollback results
     */
    public static function rollback_all() {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_child_profiles';
        
        $results = [
            'total_children' => 0,
            'restored' => 0,
            'errors' => [],
        ];
        
        // Get all child profiles from database
        $children = $wpdb->get_results( "SELECT * FROM $table" );
        $results['total_children'] = count( $children );
        
        // Group children by user_id
        $users = [];
        foreach ( $children as $child ) {
            if ( ! isset( $users[ $child->user_id ] ) ) {
                $users[ $child->user_id ] = [];
            }
            
            $users[ $child->user_id ][] = [
                'id' => $child->uuid,
                'name' => $child->name,
                'birth_date' => $child->birth_date,
                'gender' => $child->gender,
                'allergies' => json_decode( $child->allergies ?? '[]', true ),
                'feeding_style' => $child->feeding_style,
                'photo_id' => $child->photo_id ? (int) $child->photo_id : null,
                'kvkk_consent' => (bool) $child->kvkk_consent,
                'created_at' => $child->created_at,
            ];
        }
        
        // Restore to user meta
        foreach ( $users as $user_id => $user_children ) {
            $updated = update_user_meta( $user_id, '_kg_children', $user_children );
            if ( $updated ) {
                $results['restored'] += count( $user_children );
            } else {
                $results['errors'][] = sprintf(
                    'Failed to restore children for user %d',
                    $user_id
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Verify migration - compare user meta with database
     * 
     * @return array Verification results
     */
    public static function verify_migration() {
        global $wpdb;
        
        $results = [
            'matching' => 0,
            'missing_in_db' => [],
            'missing_in_meta' => [],
            'discrepancies' => [],
        ];
        
        // Get all users with children in meta
        $users_with_children = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_kg_children'"
        );
        
        foreach ( $users_with_children as $user_meta ) {
            $user_id = $user_meta->user_id;
            $children = maybe_unserialize( $user_meta->meta_value );
            
            if ( ! is_array( $children ) ) {
                continue;
            }
            
            foreach ( $children as $child ) {
                $uuid = $child['id'] ?? '';
                $db_child = ChildProfile::get_by_uuid( $uuid );
                
                if ( ! $db_child ) {
                    $results['missing_in_db'][] = $uuid;
                } elseif ( (int) $db_child->user_id !== (int) $user_id ) {
                    $results['discrepancies'][] = sprintf(
                        'Child %s has different user_id: meta=%d, db=%d',
                        $uuid,
                        $user_id,
                        $db_child->user_id
                    );
                } else {
                    $results['matching']++;
                }
            }
        }
        
        return $results;
    }
}

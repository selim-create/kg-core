<?php
namespace KG_Core\Database\Migrations;

/**
 * Migration: Add guardian_declaration to user_consents consent_type ENUM
 * 
 * Purpose: Update the consent_type ENUM to include 'guardian_declaration' 
 * for KVKK compliance when processing child data
 */
class AddGuardianDeclarationConsent {
    
    /**
     * Run the migration (add guardian_declaration to ENUM)
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kg_user_consents';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        if ( ! $table_exists ) {
            error_log( "Migration: Table $table_name does not exist. Skipping migration." );
            return false;
        }
        
        // Check current ENUM values
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name WHERE Field = 'consent_type'" );
        
        if ( empty( $columns ) ) {
            error_log( 'Migration: consent_type column not found' );
            return false;
        }
        
        $current_enum = $columns[0]->Type;
        
        // Check if guardian_declaration already exists
        if ( strpos( $current_enum, 'guardian_declaration' ) !== false ) {
            error_log( 'Migration: guardian_declaration already exists in ENUM. Skipping.' );
            return true;
        }
        
        // Note: Table and column names are hardcoded constants, not user input
        // DDL statements (ALTER TABLE) don't support parameterized queries in MySQL
        
        // Alter the ENUM to include guardian_declaration
        $sql = "ALTER TABLE $table_name 
                MODIFY COLUMN consent_type ENUM('terms', 'marketing', 'sensitive_data', 'guardian_declaration') NOT NULL";
        
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            error_log( 'Migration: Failed to add guardian_declaration to consent_type ENUM: ' . $wpdb->last_error );
            return false;
        }
        
        error_log( 'Migration: Successfully added guardian_declaration to consent_type ENUM' );
        
        // Auto-grant guardian_declaration for users who already have children
        $this->auto_grant_guardian_declaration();
        
        return true;
    }
    
    /**
     * Reverse the migration (remove guardian_declaration from ENUM)
     * 
     * Note: This will fail if there are existing records with guardian_declaration
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kg_user_consents';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        if ( ! $table_exists ) {
            return false;
        }
        
        // Note: Table and column names are hardcoded constants, not user input
        // DDL statements (ALTER TABLE) don't support parameterized queries in MySQL
        
        // Delete any guardian_declaration consents first
        $wpdb->delete( $table_name, [ 'consent_type' => 'guardian_declaration' ] );
        
        // Revert ENUM to original values
        $sql = "ALTER TABLE $table_name 
                MODIFY COLUMN consent_type ENUM('terms', 'marketing', 'sensitive_data') NOT NULL";
        
        $result = $wpdb->query( $sql );
        
        if ( $result === false ) {
            error_log( 'Migration rollback: Failed to remove guardian_declaration from ENUM: ' . $wpdb->last_error );
            return false;
        }
        
        return true;
    }
    
    /**
     * Auto-grant guardian_declaration consent for users who already have children
     * 
     * This is for backward compatibility - users who added children before
     * the guardian_declaration consent was required should be automatically granted it
     */
    private function auto_grant_guardian_declaration() {
        global $wpdb;
        
        // Find all users who have children
        $users_with_children = $wpdb->get_results( 
            "SELECT user_id, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = '_kg_children' 
             AND meta_value IS NOT NULL 
             AND meta_value != '' 
             AND meta_value != 'a:0:{}'",
            ARRAY_A
        );
        
        if ( empty( $users_with_children ) ) {
            error_log( 'Migration: No users with children found. Skipping auto-grant.' );
            return;
        }
        
        $consent_table = $wpdb->prefix . 'kg_user_consents';
        $granted_count = 0;
        
        foreach ( $users_with_children as $user ) {
            $user_id = $user['user_id'];
            
            // Check if user already has guardian_declaration consent
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $consent_table 
                 WHERE user_id = %d AND consent_type = 'guardian_declaration'",
                $user_id
            ) );
            
            if ( $existing > 0 ) {
                continue; // Already has consent
            }
            
            // Grant guardian_declaration consent
            $result = $wpdb->insert(
                $consent_table,
                [
                    'user_id' => $user_id,
                    'consent_type' => 'guardian_declaration',
                    'consented' => 1,
                    'consented_at' => current_time( 'mysql' ),
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ '%d', '%s', '%d', '%s', '%s', '%s' ]
            );
            
            if ( $result ) {
                $granted_count++;
            } else {
                error_log( "Migration: Failed to grant guardian_declaration for user $user_id: " . $wpdb->last_error );
            }
        }
        
        error_log( "Migration: Auto-granted guardian_declaration consent to $granted_count users with existing children" );
    }
}

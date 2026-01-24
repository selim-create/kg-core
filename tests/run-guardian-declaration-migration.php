<?php
/**
 * Run Guardian Declaration Consent Migration
 * 
 * This script executes the migration to add guardian_declaration to the consent_type ENUM
 */

// WordPress environment required
if ( ! defined( 'ABSPATH' ) ) {
    die( 'Direct access not permitted.' );
}

echo "<h1>Guardian Declaration Consent - Migration</h1>\n";

// Include migration class
require_once dirname( __DIR__ ) . '/includes/Database/migrations/2026_01_24_add_guardian_declaration_consent.php';

$migration = new \KG_Core\Database\Migrations\AddGuardianDeclarationConsent();

echo "<h2>Running Migration...</h2>\n";

$result = $migration->up();

if ( $result ) {
    echo "<p style='color: green; font-weight: bold;'>✓ Migration completed successfully!</p>\n";
    
    // Verify the result
    global $wpdb;
    $table_name = $wpdb->prefix . 'kg_user_consents';
    $columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name WHERE Field = 'consent_type'" );
    
    if ( ! empty( $columns ) ) {
        $enum_values = $columns[0]->Type;
        echo "<p><strong>Current ENUM values:</strong> <code>$enum_values</code></p>\n";
        
        if ( strpos( $enum_values, 'guardian_declaration' ) !== false ) {
            echo "<p style='color: green;'>✓ guardian_declaration successfully added to ENUM</p>\n";
        } else {
            echo "<p style='color: red;'>✗ guardian_declaration NOT found in ENUM</p>\n";
        }
    }
    
    // Check if any consents were auto-granted
    $consent_count = $wpdb->get_var( 
        "SELECT COUNT(*) FROM $table_name WHERE consent_type = 'guardian_declaration'"
    );
    
    echo "<p><strong>Guardian declaration consents in database:</strong> $consent_count</p>\n";
    
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Migration failed. Check error logs for details.</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Next steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>Verify the migration was successful</li>\n";
echo "<li>Run integration tests to ensure functionality works</li>\n";
echo "<li>Test registration with child profile</li>\n";
echo "</ul>\n";

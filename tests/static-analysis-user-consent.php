#!/usr/bin/env php
<?php
/**
 * Static Analysis: User Consent Management Implementation
 * 
 * This script performs static code analysis without requiring WordPress
 * to verify the implementation is correct.
 */

echo "=== User Consent Management Static Analysis ===\n\n";

$base_path = dirname( __DIR__ );
$errors = [];
$warnings = [];
$successes = [];

// Test 1: Check if all files exist
echo "1. Checking if all required files exist...\n";

$required_files = [
    'includes/Database/UserConsentSchema.php',
    'includes/Models/UserConsent.php',
    'includes/Utils/UserConsentHelper.php',
];

foreach ( $required_files as $file ) {
    $full_path = $base_path . '/' . $file;
    if ( file_exists( $full_path ) ) {
        $successes[] = "✓ File exists: $file";
    } else {
        $errors[] = "✗ File missing: $file";
    }
}

// Test 2: Check if files are properly included in kg-core.php
echo "\n2. Checking if files are included in kg-core.php...\n";

$main_file = $base_path . '/kg-core.php';
$main_content = file_get_contents( $main_file );

$includes_to_check = [
    'UserConsentSchema.php',
    'UserConsent.php',
    'UserConsentHelper.php',
];

foreach ( $includes_to_check as $include ) {
    if ( strpos( $main_content, $include ) !== false ) {
        $successes[] = "✓ $include is included in kg-core.php";
    } else {
        $errors[] = "✗ $include is NOT included in kg-core.php";
    }
}

// Test 3: Check if schema is registered in activation hook
echo "\n3. Checking activation hook registration...\n";

if ( strpos( $main_content, 'UserConsentSchema::create_table' ) !== false ) {
    $successes[] = "✓ UserConsentSchema::create_table() called in activation hook";
} else {
    $errors[] = "✗ UserConsentSchema::create_table() NOT called in activation hook";
}

// Test 4: Check UserController modifications
echo "\n4. Checking UserController modifications...\n";

$controller_file = $base_path . '/includes/API/UserController.php';
$controller_content = file_get_contents( $controller_file );

// Check for UserConsent import
if ( strpos( $controller_content, 'use KG_Core\Models\UserConsent;' ) !== false ) {
    $successes[] = "✓ UserConsent model imported in UserController";
} else {
    $errors[] = "✗ UserConsent model NOT imported in UserController";
}

// Check for consent handling in register_user
if ( strpos( $controller_content, 'consents_data' ) !== false ) {
    $successes[] = "✓ Consent handling code added to register_user";
} else {
    $errors[] = "✗ Consent handling code NOT added to register_user";
}

// Check for validation
if ( strpos( $controller_content, 'terms_accepted' ) !== false ) {
    $successes[] = "✓ Terms validation added to register_user";
} else {
    $warnings[] = "⚠ Terms validation might be missing in register_user";
}

// Check for consent endpoints
if ( strpos( $controller_content, '/user/consents' ) !== false ) {
    $successes[] = "✓ Consent management endpoints registered";
} else {
    $errors[] = "✗ Consent management endpoints NOT registered";
}

// Check for endpoint methods
if ( strpos( $controller_content, 'function get_user_consents' ) !== false ) {
    $successes[] = "✓ get_user_consents() method exists";
} else {
    $errors[] = "✗ get_user_consents() method missing";
}

if ( strpos( $controller_content, 'function update_user_consent' ) !== false ) {
    $successes[] = "✓ update_user_consent() method exists";
} else {
    $errors[] = "✗ update_user_consent() method missing";
}

// Test 5: Validate UserConsentSchema structure
echo "\n5. Validating UserConsentSchema.php structure...\n";

$schema_file = $base_path . '/includes/Database/UserConsentSchema.php';
$schema_content = file_get_contents( $schema_file );

$required_columns = [
    'user_id',
    'consent_type',
    'consented',
    'consented_at',
    'revoked_at',
    'ip_address',
    'user_agent',
    'version',
];

foreach ( $required_columns as $column ) {
    if ( strpos( $schema_content, $column ) !== false ) {
        $successes[] = "✓ Column '$column' defined in schema";
    } else {
        $errors[] = "✗ Column '$column' missing in schema";
    }
}

// Check for ENUM values
if ( preg_match( "/ENUM\('terms',\s*'marketing',\s*'sensitive_data'\)/", $schema_content ) ) {
    $successes[] = "✓ Correct ENUM values for consent_type";
} else {
    $warnings[] = "⚠ ENUM values for consent_type might not be correct";
}

// Check for indexes
if ( strpos( $schema_content, 'INDEX idx_user_consents' ) !== false ) {
    $successes[] = "✓ Index idx_user_consents defined";
} else {
    $warnings[] = "⚠ Index idx_user_consents might be missing";
}

// Test 6: Validate UserConsent model methods
echo "\n6. Validating UserConsent.php model methods...\n";

$model_file = $base_path . '/includes/Models/UserConsent.php';
$model_content = file_get_contents( $model_file );

$required_methods = [
    'get_by_user_and_type',
    'get_by_user_id',
    'has_active_consent',
    'create',
    'update',
    'format_for_api',
];

foreach ( $required_methods as $method ) {
    if ( preg_match( "/function\s+$method\s*\(/", $model_content ) ) {
        $successes[] = "✓ Method $method() exists in UserConsent model";
    } else {
        $errors[] = "✗ Method $method() missing in UserConsent model";
    }
}

// Test 7: Validate UserConsentHelper methods
echo "\n7. Validating UserConsentHelper.php methods...\n";

$helper_file = $base_path . '/includes/Utils/UserConsentHelper.php';
$helper_content = file_get_contents( $helper_file );

$helper_methods = [
    'has_active_consent',
    'has_marketing_consent',
    'has_sensitive_data_consent',
    'get_consent_status',
];

foreach ( $helper_methods as $method ) {
    if ( preg_match( "/function\s+$method\s*\(/", $helper_content ) ) {
        $successes[] = "✓ Method $method() exists in UserConsentHelper";
    } else {
        $errors[] = "✗ Method $method() missing in UserConsentHelper";
    }
}

// Display Results
echo "\n" . str_repeat( "=", 60 ) . "\n";
echo "ANALYSIS RESULTS\n";
echo str_repeat( "=", 60 ) . "\n\n";

if ( ! empty( $successes ) ) {
    echo "✅ SUCCESSES (" . count( $successes ) . "):\n";
    foreach ( $successes as $success ) {
        echo "  $success\n";
    }
    echo "\n";
}

if ( ! empty( $warnings ) ) {
    echo "⚠️  WARNINGS (" . count( $warnings ) . "):\n";
    foreach ( $warnings as $warning ) {
        echo "  $warning\n";
    }
    echo "\n";
}

if ( ! empty( $errors ) ) {
    echo "❌ ERRORS (" . count( $errors ) . "):\n";
    foreach ( $errors as $error ) {
        echo "  $error\n";
    }
    echo "\n";
}

echo str_repeat( "=", 60 ) . "\n";
echo "SUMMARY\n";
echo str_repeat( "=", 60 ) . "\n";
echo "Successes: " . count( $successes ) . "\n";
echo "Warnings:  " . count( $warnings ) . "\n";
echo "Errors:    " . count( $errors ) . "\n\n";

if ( empty( $errors ) ) {
    echo "✅ Analysis completed successfully!\n";
    echo "   All required components are present and properly integrated.\n\n";
    exit( 0 );
} else {
    echo "❌ Analysis found errors that need to be fixed.\n\n";
    exit( 1 );
}

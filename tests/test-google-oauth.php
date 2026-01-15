<?php
/**
 * Static Code Analysis Test for Google OAuth Implementation
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core Google OAuth Implementation Verification ===\n\n";

$baseDir = __DIR__;
$passed = 0;
$failed = 0;

// Test 1: Check if GoogleAuth file exists
echo "1. GoogleAuth Service\n";
$googleAuthFile = $baseDir . '/includes/Auth/GoogleAuth.php';
if (file_exists($googleAuthFile)) {
    echo "   ✓ File exists: GoogleAuth.php\n";
    $content = file_get_contents($googleAuthFile);
    
    // Check for required methods
    $requiredMethods = [
        'is_enabled',
        'verify_id_token',
        'get_or_create_user',
        'generate_unique_username'
    ];
    
    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
    
    // Check for security measures
    if (strpos($content, 'oauth2.googleapis.com/tokeninfo') !== false) {
        echo "   ✓ Google token verification endpoint used\n";
        $passed++;
    } else {
        echo "   ✗ Google token verification endpoint missing\n";
        $failed++;
    }
    
    if (strpos($content, '$body[\'aud\']') !== false && strpos($content, 'client_id') !== false) {
        echo "   ✓ Client ID verification implemented\n";
        $passed++;
    } else {
        echo "   ✗ Client ID verification missing\n";
        $failed++;
    }
    
    if (strpos($content, '$body[\'exp\']') !== false) {
        echo "   ✓ Token expiration check implemented\n";
        $passed++;
    } else {
        echo "   ✗ Token expiration check missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: GoogleAuth.php\n";
    $failed++;
}

echo "\n";

// Test 2: Check UserController for Google endpoint
echo "2. UserController Google Endpoint\n";
$userControllerFile = $baseDir . '/includes/API/UserController.php';
if (file_exists($userControllerFile)) {
    echo "   ✓ File exists: UserController.php\n";
    $content = file_get_contents($userControllerFile);
    
    // Check for GoogleAuth use statement
    if (strpos($content, 'use KG_Core\Auth\GoogleAuth') !== false) {
        echo "   ✓ GoogleAuth imported\n";
        $passed++;
    } else {
        echo "   ✗ GoogleAuth import missing\n";
        $failed++;
    }
    
    // Check for Google endpoint registration
    if (strpos($content, '/auth/google') !== false) {
        echo "   ✓ Google endpoint registered\n";
        $passed++;
    } else {
        echo "   ✗ Google endpoint not registered\n";
        $failed++;
    }
    
    // Check for google_auth method
    if (strpos($content, 'function google_auth') !== false) {
        echo "   ✓ google_auth method exists\n";
        $passed++;
    } else {
        echo "   ✗ google_auth method missing\n";
        $failed++;
    }
    
    // Check for prepare_user_data method
    if (strpos($content, 'function prepare_user_data') !== false) {
        echo "   ✓ prepare_user_data method exists\n";
        $passed++;
    } else {
        echo "   ✗ prepare_user_data method missing\n";
        $failed++;
    }
    
    // Check for security checks
    if (strpos($content, 'GoogleAuth::is_enabled()') !== false) {
        echo "   ✓ Google OAuth enabled check implemented\n";
        $passed++;
    } else {
        echo "   ✗ Google OAuth enabled check missing\n";
        $failed++;
    }
    
    if (strpos($content, 'email_verified') !== false) {
        echo "   ✓ Email verification check implemented\n";
        $passed++;
    } else {
        echo "   ✗ Email verification check missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: UserController.php\n";
    $failed++;
}

echo "\n";

// Test 3: Check SettingsPage for Google OAuth settings
echo "3. Admin SettingsPage\n";
$settingsPageFile = $baseDir . '/includes/Admin/SettingsPage.php';
if (file_exists($settingsPageFile)) {
    echo "   ✓ File exists: SettingsPage.php\n";
    $content = file_get_contents($settingsPageFile);
    
    // Check for setting registrations
    $settings = [
        'kg_google_client_id',
        'kg_google_client_secret',
        'kg_google_auth_enabled'
    ];
    
    foreach ($settings as $setting) {
        if (strpos($content, "register_setting('kg_ai_settings', '$setting'") !== false) {
            echo "   ✓ Setting registered: $setting\n";
            $passed++;
        } else {
            echo "   ✗ Setting not registered: $setting\n";
            $failed++;
        }
    }
    
    // Check for UI elements
    if (strpos($content, 'Google OAuth Ayarları') !== false) {
        echo "   ✓ Google OAuth UI section added\n";
        $passed++;
    } else {
        echo "   ✗ Google OAuth UI section missing\n";
        $failed++;
    }
    
    // Check for security - password input for client secret
    if (strpos($content, 'type="password" name="kg_google_client_secret"') !== false) {
        echo "   ✓ Client Secret field is password type\n";
        $passed++;
    } else {
        echo "   ✗ Client Secret field is not password type\n";
        $failed++;
    }
    
    // Check for setup instructions
    if (strpos($content, 'Google OAuth Kurulum Adımları') !== false) {
        echo "   ✓ Setup instructions included\n";
        $passed++;
    } else {
        echo "   ✗ Setup instructions missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: SettingsPage.php\n";
    $failed++;
}

echo "\n";

// Test 4: Check main plugin file
echo "4. Main Plugin File (kg-core.php)\n";
$pluginFile = $baseDir . '/kg-core.php';
if (file_exists($pluginFile)) {
    echo "   ✓ File exists: kg-core.php\n";
    $content = file_get_contents($pluginFile);
    
    // Check for GoogleAuth require
    if (strpos($content, "includes/Auth/GoogleAuth.php") !== false) {
        echo "   ✓ GoogleAuth.php required in main plugin file\n";
        $passed++;
    } else {
        echo "   ✗ GoogleAuth.php not required in main plugin file\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: kg-core.php\n";
    $failed++;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed > 0) {
    echo "\n❌ Some tests failed. Please review the implementation.\n";
    exit(1);
} else {
    echo "\n✅ All tests passed!\n";
    exit(0);
}

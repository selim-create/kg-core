<?php
/**
 * Static Code Analysis Test for Auth System Enhancements
 * 
 * Tests for:
 * - Forgot password endpoint
 * - Reset password endpoint
 * - Registration with username
 * - Registration with child profile
 * 
 * @package KG_Core
 */

echo "=== Auth System Enhancements Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if UserController file exists and has required methods
echo "1. UserController Implementation\n";
$userControllerFile = $baseDir . '/includes/API/UserController.php';

if (file_exists($userControllerFile)) {
    echo "   ✓ File exists: UserController.php\n";
    $passed++;
    $content = file_get_contents($userControllerFile);
    
    // Check for forgot_password method
    if (strpos($content, 'function forgot_password') !== false) {
        echo "   ✓ Method exists: forgot_password()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: forgot_password()\n";
        $failed++;
    }
    
    // Check for reset_password method
    if (strpos($content, 'function reset_password') !== false) {
        echo "   ✓ Method exists: reset_password()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: reset_password()\n";
        $failed++;
    }
    
    // Check for email template method
    if (strpos($content, 'function get_password_reset_email_template') !== false) {
        echo "   ✓ Method exists: get_password_reset_email_template()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: get_password_reset_email_template()\n";
        $failed++;
    }
    
    // Check for route registrations
    echo "\n2. Endpoint Registration\n";
    
    if (strpos($content, "'/auth/forgot-password'") !== false) {
        echo "   ✓ Route registered: /auth/forgot-password\n";
        $passed++;
    } else {
        echo "   ✗ Route missing: /auth/forgot-password\n";
        $failed++;
    }
    
    if (strpos($content, "'/auth/reset-password'") !== false) {
        echo "   ✓ Route registered: /auth/reset-password\n";
        $passed++;
    } else {
        echo "   ✗ Route missing: /auth/reset-password\n";
        $failed++;
    }
    
    // Check for security features
    echo "\n3. Security Features\n";
    
    if (strpos($content, 'prevent email enumeration') !== false) {
        echo "   ✓ Email enumeration protection documented\n";
        $passed++;
    } else {
        echo "   ✗ Email enumeration protection not documented\n";
        $failed++;
    }
    
    if (preg_match('/if\s*\(\s*!\s*\$user\s*\)\s*{.*return.*200/s', $content)) {
        echo "   ✓ Returns 200 even when user not found (anti-enumeration)\n";
        $passed++;
    } else {
        echo "   ⚠ Warning: Email enumeration protection implementation unclear\n";
    }
    
    if (strpos($content, 'get_password_reset_key') !== false) {
        echo "   ✓ Uses WordPress native password reset key\n";
        $passed++;
    } else {
        echo "   ✗ Missing WordPress password reset key generation\n";
        $failed++;
    }
    
    if (strpos($content, 'check_password_reset_key') !== false) {
        echo "   ✓ Uses WordPress native key validation\n";
        $passed++;
    } else {
        echo "   ✗ Missing WordPress password reset key validation\n";
        $failed++;
    }
    
    // Check for registration enhancements
    echo "\n4. Registration Enhancements\n";
    
    if (strpos($content, '$username = sanitize_user') !== false) {
        echo "   ✓ Username parameter added to register_user\n";
        $passed++;
    } else {
        echo "   ✗ Username parameter missing in register_user\n";
        $failed++;
    }
    
    if (strpos($content, '$child_data = $request->get_param') !== false) {
        echo "   ✓ Child data parameter added to register_user\n";
        $passed++;
    } else {
        echo "   ✗ Child data parameter missing in register_user\n";
        $failed++;
    }
    
    if (strpos($content, 'username_exists') !== false) {
        echo "   ✓ Username uniqueness validation\n";
        $passed++;
    } else {
        echo "   ✗ Username uniqueness validation missing\n";
        $failed++;
    }
    
    if (strpos($content, 'validate_username') !== false) {
        echo "   ✓ Username format validation\n";
        $passed++;
    } else {
        echo "   ✗ Username format validation missing\n";
        $failed++;
    }
    
    // Check for password validation changes
    echo "\n5. Password Validation\n";
    
    if (preg_match('/strlen\s*\(\s*\$password\s*\)\s*<\s*6/', $content)) {
        echo "   ✓ Password minimum length set to 6 characters\n";
        $passed++;
    } else {
        echo "   ✗ Password minimum length not set to 6 characters\n";
        $failed++;
    }
    
    // Check for child profile creation logic
    echo "\n6. Child Profile Creation\n";
    
    if (strpos($content, 'wp_generate_uuid4') !== false) {
        echo "   ✓ Uses UUID v4 for child ID generation\n";
        $passed++;
    } else {
        echo "   ✗ UUID v4 generation missing for child ID\n";
        $failed++;
    }
    
    if (strpos($content, "'kvkk_consent' => true") !== false) {
        echo "   ✓ KVKK consent auto-set during registration\n";
        $passed++;
    } else {
        echo "   ✗ KVKK consent auto-set missing\n";
        $failed++;
    }
    
    if (strpos($content, "'_kg_children'") !== false) {
        echo "   ✓ Children stored in user meta\n";
        $passed++;
    } else {
        echo "   ✗ Children storage in user meta missing\n";
        $failed++;
    }
    
    // Check email template
    echo "\n7. Email Template\n";
    
    if (strpos($content, '<!DOCTYPE html>') !== false) {
        echo "   ✓ HTML email template present\n";
        $passed++;
    } else {
        echo "   ✗ HTML email template missing\n";
        $failed++;
    }
    
    if (strpos($content, 'esc_html') !== false && strpos($content, 'esc_url') !== false) {
        echo "   ✓ Email template uses proper escaping\n";
        $passed++;
    } else {
        echo "   ✗ Email template missing proper escaping\n";
        $failed++;
    }
    
    if (strpos($content, 'KidsGourmet') !== false) {
        echo "   ✓ Email template branded for KidsGourmet\n";
        $passed++;
    } else {
        echo "   ✗ Email template branding missing\n";
        $failed++;
    }
    
    // Check response updates
    echo "\n8. API Response Updates\n";
    
    if (preg_match("/'username'\s*=>\s*\\\$user_login/", $content)) {
        echo "   ✓ Registration response includes username\n";
        $passed++;
    } else {
        echo "   ✗ Registration response missing username\n";
        $failed++;
    }
    
    if (preg_match("/'children'\s*=>\s*\\\$children/", $content)) {
        echo "   ✓ Registration response includes children\n";
        $passed++;
    } else {
        echo "   ✗ Registration response missing children\n";
        $failed++;
    }
    
} else {
    echo "   ✗ File not found: UserController.php\n";
    $failed++;
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "Test Summary:\n";
echo "  Passed: $passed\n";
echo "  Failed: $failed\n";
echo "  Total:  " . ($passed + $failed) . "\n";

$successRate = ($passed + $failed) > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : 0;
echo "  Success Rate: $successRate%\n";

if ($failed === 0) {
    echo "\n✓ ALL CHECKS PASSED!\n";
    exit(0);
} else {
    echo "\n✗ SOME CHECKS FAILED\n";
    exit(1);
}

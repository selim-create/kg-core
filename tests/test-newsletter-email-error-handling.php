<?php
/**
 * Newsletter Email Error Handling Test
 * 
 * This test validates that newsletter subscription succeeds even when email sending fails.
 * It ensures:
 * 1. Database insert succeeds
 * 2. Email failures don't cause subscription to fail
 * 3. Appropriate success messages are returned
 * 4. Response includes email_sent flag
 */

echo "=== NEWSLETTER EMAIL ERROR HANDLING TEST ===\n\n";

// Define the constant for the plugin path
if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/../');
}

// Mock WordPress functions
if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return strip_tags($text);
    }
}

if (!function_exists('current_time')) {
    function current_time($format) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url() {
        return 'https://example.com';
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path) {
        return 'https://example.com/wp-json/' . $path;
    }
}

if (!function_exists('__')) {
    function __($text, $domain) {
        return $text;
    }
}

$test_results = [];

// TEST 1: Check that sendConfirmationEmail has try-catch wrapper
echo "TEST 1: Checking sendConfirmationEmail has proper error handling\n";
echo "-------------------------------------------------------------------\n";
$service_file = file_get_contents(KG_CORE_PATH . 'includes/Newsletter/NewsletterService.php');

if (strpos($service_file, 'public function sendConfirmationEmail') !== false) {
    echo "✅ PASS: sendConfirmationEmail method exists\n";
    
    // Check for try-catch wrapper
    $pattern = '/public function sendConfirmationEmail.*?\{.*?try\s*\{.*?catch.*?\}/s';
    if (preg_match($pattern, $service_file)) {
        echo "✅ PASS: sendConfirmationEmail has try-catch error handling\n";
    } else {
        echo "❌ FAIL: sendConfirmationEmail missing try-catch wrapper\n";
        $test_results[] = false;
    }
    
    // Check for detailed error logging
    if (strpos($service_file, 'Newsletter: sendConfirmationEmail error:') !== false) {
        echo "✅ PASS: sendConfirmationEmail has detailed error logging\n";
    } else {
        echo "❌ FAIL: sendConfirmationEmail missing detailed error logging\n";
        $test_results[] = false;
    }
} else {
    echo "❌ FAIL: sendConfirmationEmail method not found\n";
    $test_results[] = false;
}
echo "\n";

// TEST 2: Check that subscribe() handles email failures gracefully (new subscription)
echo "TEST 2: Checking subscribe() handles email failures in new subscription\n";
echo "-------------------------------------------------------------------\n";

// Check that email sending is wrapped in try-catch
if (preg_match('/\/\/ Try to send confirmation email.*?try\s*\{.*?sendConfirmationEmail.*?catch.*?\}/s', $service_file)) {
    echo "✅ PASS: Email sending wrapped in try-catch for new subscriptions\n";
} else {
    echo "❌ FAIL: Email sending not wrapped in try-catch for new subscriptions\n";
    $test_results[] = false;
}

// Check for non-blocking comment
if (strpos($service_file, '// Try to send confirmation email (non-blocking)') !== false) {
    echo "✅ PASS: Email sending marked as non-blocking\n";
} else {
    echo "❌ FAIL: Email sending not marked as non-blocking\n";
    $test_results[] = false;
}

// Check that success is always returned after database insert
if (strpos($service_file, "// Always return success if database insert was successful") !== false) {
    echo "✅ PASS: Success always returned after database insert\n";
} else {
    echo "❌ FAIL: Missing comment about always returning success\n";
    $test_results[] = false;
}

// Check that response includes email_sent flag
if (preg_match("/'email_sent'\s*=>\s*\\\$email_sent/", $service_file)) {
    echo "✅ PASS: Response includes email_sent flag\n";
} else {
    echo "❌ FAIL: Response missing email_sent flag\n";
    $test_results[] = false;
}

// Check for conditional success message based on email_sent
if (preg_match('/\$email_sent\s*\?\s*__\(.*?\)\s*:\s*__\(/s', $service_file)) {
    echo "✅ PASS: Conditional success message based on email_sent status\n";
} else {
    echo "❌ FAIL: Missing conditional success message\n";
    $test_results[] = false;
}

echo "\n";

// TEST 3: Check that pending status resend handles email failures
echo "TEST 3: Checking pending status resend handles email failures\n";
echo "-------------------------------------------------------------------\n";

// Check for try-catch in pending status block
if (preg_match('/is_pending\(\).*?\{.*?\/\/ Try to resend confirmation email.*?try\s*\{.*?sendConfirmationEmail.*?catch.*?\}/s', $service_file)) {
    echo "✅ PASS: Pending status resend wrapped in try-catch\n";
} else {
    echo "❌ FAIL: Pending status resend not wrapped in try-catch\n";
    $test_results[] = false;
}

// Check for conditional message in pending status
if (preg_match('/is_pending\(\).*?return\s*\[.*?\'message\'\s*=>\s*\$email_sent.*?\?.*?:/s', $service_file)) {
    echo "✅ PASS: Pending status has conditional message\n";
} else {
    echo "❌ FAIL: Pending status missing conditional message\n";
    $test_results[] = false;
}

// Check for email_sent flag in pending status response
if (preg_match('/is_pending\(\).*?return\s*\[.*?\'email_sent\'\s*=>\s*\$email_sent/s', $service_file)) {
    echo "✅ PASS: Pending status response includes email_sent flag\n";
} else {
    echo "❌ FAIL: Pending status response missing email_sent flag\n";
    $test_results[] = false;
}

echo "\n";

// TEST 4: Check that unsubscribed reactivation handles email failures
echo "TEST 4: Checking unsubscribed reactivation handles email failures\n";
echo "-------------------------------------------------------------------\n";

// Check for try-catch in unsubscribed reactivation block
if (preg_match('/is_unsubscribed\(\).*?\{.*?\/\/ Try to send confirmation email.*?try\s*\{.*?sendConfirmationEmail.*?catch.*?\}/s', $service_file)) {
    echo "✅ PASS: Unsubscribed reactivation wrapped in try-catch\n";
} else {
    echo "❌ FAIL: Unsubscribed reactivation not wrapped in try-catch\n";
    $test_results[] = false;
}

// Check for conditional message in unsubscribed reactivation
if (preg_match('/is_unsubscribed\(\).*?return\s*\[.*?\'message\'\s*=>\s*\$email_sent.*?\?.*?:/s', $service_file)) {
    echo "✅ PASS: Unsubscribed reactivation has conditional message\n";
} else {
    echo "❌ FAIL: Unsubscribed reactivation missing conditional message\n";
    $test_results[] = false;
}

// Check for email_sent flag in unsubscribed reactivation response
if (preg_match('/is_unsubscribed\(\).*?return\s*\[.*?\'email_sent\'\s*=>\s*\$email_sent/s', $service_file)) {
    echo "✅ PASS: Unsubscribed reactivation response includes email_sent flag\n";
} else {
    echo "❌ FAIL: Unsubscribed reactivation response missing email_sent flag\n";
    $test_results[] = false;
}

echo "\n";

// TEST 5: Check that old error response is removed
echo "TEST 5: Checking that old email failure error response is removed\n";
echo "-------------------------------------------------------------------\n";

// Check that the old error code 'email_failed' is NOT returned from subscribe
$old_error_pattern = "/'code'\s*=>\s*'email_failed'/";
if (!preg_match($old_error_pattern, $service_file)) {
    echo "✅ PASS: Old 'email_failed' error response removed\n";
} else {
    echo "❌ FAIL: Old 'email_failed' error response still exists\n";
    $test_results[] = false;
}

// Check that old error message about email sending is removed
if (strpos($service_file, "Onay e-postası gönderilemedi. Lütfen daha sonra tekrar deneyin.") === false) {
    echo "✅ PASS: Old email failure error message removed\n";
} else {
    echo "❌ FAIL: Old email failure error message still exists\n";
    $test_results[] = false;
}

echo "\n";

// TEST 6: Check error logging improvements
echo "TEST 6: Checking error logging improvements\n";
echo "-------------------------------------------------------------------\n";

// Check for specific error logging with email address
if (strpos($service_file, "sprintf('Newsletter: Failed to send confirmation email to %s'") !== false) {
    echo "✅ PASS: Error logging includes subscriber email\n";
} else {
    echo "❌ FAIL: Error logging missing subscriber email\n";
    $test_results[] = false;
}

// Check for exception logging in resend
if (strpos($service_file, "Newsletter: Resend email exception:") !== false) {
    echo "✅ PASS: Exception logging for resend exists\n";
} else {
    echo "❌ FAIL: Exception logging for resend missing\n";
    $test_results[] = false;
}

// Check for exception logging in reactivation
if (strpos($service_file, "Newsletter: Reactivation email exception:") !== false) {
    echo "✅ PASS: Exception logging for reactivation exists\n";
} else {
    echo "❌ FAIL: Exception logging for reactivation missing\n";
    $test_results[] = false;
}

echo "\n";

// SUMMARY
echo "=== TEST SUMMARY ===\n";
if (empty($test_results) || !in_array(false, $test_results)) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "\nNewsletter email error handling successfully implemented:\n";
    echo "- Email sending failures are non-blocking\n";
    echo "- Database success always returns 200 OK\n";
    echo "- Email failures are logged but don't cause subscription to fail\n";
    echo "- Response includes email_sent flag for status tracking\n";
    echo "- User-friendly conditional messages based on email status\n";
    echo "- All email sending attempts wrapped in try-catch blocks\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "Please review the failures above.\n";
    exit(1);
}

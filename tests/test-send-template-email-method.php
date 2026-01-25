<?php
/**
 * Test for send_template_email() method
 * 
 * This test validates that the new send_template_email() method exists
 * in EmailService and has the correct signature.
 */

echo "=== SEND_TEMPLATE_EMAIL METHOD TEST ===\n\n";

// Define the constant for the plugin path
if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/../');
}

$test_results = [];

// TEST 1: Check that send_template_email method exists in EmailService
echo "TEST 1: Checking send_template_email method exists\n";
echo "-------------------------------------------------------------------\n";
$service_file_path = KG_CORE_PATH . 'includes/Notifications/EmailService.php';
if (!file_exists($service_file_path)) {
    echo "❌ FAIL: EmailService.php file not found at $service_file_path\n";
    exit(1);
}
$service_file = file_get_contents($service_file_path);

if (strpos($service_file, 'public function send_template_email') !== false) {
    echo "✅ PASS: send_template_email method exists in EmailService\n";
} else {
    echo "❌ FAIL: send_template_email method not found in EmailService\n";
    $test_results[] = false;
}
echo "\n";

// TEST 2: Check method signature and parameters
echo "TEST 2: Checking send_template_email method signature\n";
echo "-------------------------------------------------------------------\n";

// Check for correct parameter names
if (preg_match('/public function send_template_email\s*\(\s*\$to\s*,\s*\$template_key\s*,\s*\$placeholders\s*=\s*array\(\s*\)\s*\)/', $service_file)) {
    echo "✅ PASS: Method has correct signature with \$to, \$template_key, \$placeholders parameters\n";
} else {
    echo "❌ FAIL: Method signature incorrect\n";
    $test_results[] = false;
}
echo "\n";

// TEST 3: Check that method validates email address
echo "TEST 3: Checking email address validation\n";
echo "-------------------------------------------------------------------\n";

$pattern = '/public function send_template_email.*?\{.*?if\s*\(\s*empty\s*\(\s*\$to\s*\)\s*\|\|\s*!\s*is_email\s*\(\s*\$to\s*\)\s*\)/s';
if (preg_match($pattern, $service_file)) {
    echo "✅ PASS: Method validates email address\n";
} else {
    echo "❌ FAIL: Email validation not found\n";
    $test_results[] = false;
}

// Check for invalid_email error
if (strpos($service_file, "'invalid_email'") !== false && strpos($service_file, "'Invalid recipient email address'") !== false) {
    echo "✅ PASS: Returns WP_Error for invalid email\n";
} else {
    echo "❌ FAIL: Invalid email error not found\n";
    $test_results[] = false;
}
echo "\n";

// TEST 4: Check that method uses TemplateEngine
echo "TEST 4: Checking TemplateEngine usage\n";
echo "-------------------------------------------------------------------\n";

$pattern = '/public function send_template_email.*?\{.*?\$this->template_engine->render\s*\(\s*\$template_key\s*,\s*\$placeholders\s*\)/s';
if (preg_match($pattern, $service_file)) {
    echo "✅ PASS: Method calls template_engine->render()\n";
} else {
    echo "❌ FAIL: TemplateEngine render() not called\n";
    $test_results[] = false;
}
echo "\n";

// TEST 5: Check that method uses send() to send email
echo "TEST 5: Checking that method calls send()\n";
echo "-------------------------------------------------------------------\n";

$pattern = '/public function send_template_email.*?\{.*?\$this->send\s*\(/s';
if (preg_match($pattern, $service_file)) {
    echo "✅ PASS: Method calls send() to send email\n";
} else {
    echo "❌ FAIL: send() method not called\n";
    $test_results[] = false;
}
echo "\n";

// TEST 6: Check that method logs with user_id = 0
echo "TEST 6: Checking email logging with user_id = 0\n";
echo "-------------------------------------------------------------------\n";

$pattern = '/public function send_template_email.*?\{.*?\$this->log_email\s*\(\s*0\s*,/s';
if (preg_match($pattern, $service_file)) {
    echo "✅ PASS: Method logs email with user_id = 0\n";
} else {
    echo "❌ FAIL: Email not logged with user_id = 0\n";
    $test_results[] = false;
}

// Check for comment explaining user_id = 0
if (strpos($service_file, '// No user_id for newsletter subscribers') !== false) {
    echo "✅ PASS: Comment explains user_id = 0 for newsletter subscribers\n";
} else {
    echo "❌ FAIL: Missing comment about user_id = 0\n";
    $test_results[] = false;
}
echo "\n";

// TEST 7: Check that method handles template rendering errors
echo "TEST 7: Checking error handling for template rendering\n";
echo "-------------------------------------------------------------------\n";

$pattern = '/public function send_template_email.*?\{.*?if\s*\(\s*is_wp_error\s*\(\s*\$rendered\s*\)\s*\)/s';
if (preg_match($pattern, $service_file)) {
    echo "✅ PASS: Method checks for template rendering errors\n";
} else {
    echo "❌ FAIL: Template error check not found\n";
    $test_results[] = false;
}
echo "\n";

// TEST 8: Check that method logs both success and failure
echo "TEST 8: Checking email logging for both success and failure\n";
echo "-------------------------------------------------------------------\n";

$pattern = '/public function send_template_email.*?\{.*?\$status\s*=\s*is_wp_error\s*\(\s*\$result\s*\)\s*\?\s*[\'"]failed[\'"]\s*:\s*[\'"]sent[\'"]/s';
if (preg_match($pattern, $service_file)) {
    echo "✅ PASS: Method determines status based on send result\n";
} else {
    echo "❌ FAIL: Status determination not found\n";
    $test_results[] = false;
}

// Check that log_email is called after send
$pattern = '/public function send_template_email.*?\{.*?\$result\s*=\s*\$this->send\(.*?\);.*?\$this->log_email\(/s';
if (preg_match($pattern, $service_file)) {
    echo "✅ PASS: Method logs email after sending\n";
} else {
    echo "❌ FAIL: Email not logged after sending\n";
    $test_results[] = false;
}
echo "\n";

// TEST 9: Verify NewsletterService calls the new method
echo "TEST 9: Checking NewsletterService uses send_template_email\n";
echo "-------------------------------------------------------------------\n";

$newsletter_file_path = KG_CORE_PATH . 'includes/Newsletter/NewsletterService.php';
if (!file_exists($newsletter_file_path)) {
    echo "❌ FAIL: NewsletterService.php file not found at $newsletter_file_path\n";
    exit(1);
}
$newsletter_file = file_get_contents($newsletter_file_path);

// Check sendConfirmationEmail calls send_template_email
if (strpos($newsletter_file, 'send_template_email') !== false) {
    echo "✅ PASS: NewsletterService calls send_template_email\n";
} else {
    echo "❌ FAIL: NewsletterService doesn't call send_template_email\n";
    $test_results[] = false;
}

// Count occurrences to ensure both sendConfirmationEmail and sendWelcomeEmail use it
$count = substr_count($newsletter_file, '$email_service->send_template_email');
if ($count >= 2) {
    echo "✅ PASS: send_template_email is called at least twice (confirmation and welcome emails)\n";
} else {
    echo "❌ FAIL: send_template_email should be called at least twice\n";
    $test_results[] = false;
}
echo "\n";

// TEST 10: Check PHPDoc comments
echo "TEST 10: Checking PHPDoc comments\n";
echo "-------------------------------------------------------------------\n";

if (strpos($service_file, '* Send email using template (for non-user recipients like newsletter)') !== false) {
    echo "✅ PASS: Method has descriptive PHPDoc comment\n";
} else {
    echo "❌ FAIL: Missing descriptive PHPDoc comment\n";
    $test_results[] = false;
}

if (strpos($service_file, '* @param string $to            Recipient email address.') !== false) {
    echo "✅ PASS: PHPDoc includes @param for \$to\n";
} else {
    echo "❌ FAIL: PHPDoc missing @param for \$to\n";
    $test_results[] = false;
}

if (strpos($service_file, '* @return bool|WP_Error True on success, WP_Error on failure.') !== false) {
    echo "✅ PASS: PHPDoc includes @return\n";
} else {
    echo "❌ FAIL: PHPDoc missing @return\n";
    $test_results[] = false;
}
echo "\n";

// SUMMARY
echo "=== TEST SUMMARY ===\n";
if (empty($test_results) || !in_array(false, $test_results)) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "\nsend_template_email() method successfully implemented:\n";
    echo "- Method exists in EmailService class\n";
    echo "- Accepts email address as first parameter (not user_id)\n";
    echo "- Uses TemplateEngine to render templates\n";
    echo "- Sends email using send() method\n";
    echo "- Logs emails with user_id = 0 for newsletter subscribers\n";
    echo "- Handles errors properly\n";
    echo "- Used by NewsletterService for both confirmation and welcome emails\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "Please review the failures above.\n";
    exit(1);
}

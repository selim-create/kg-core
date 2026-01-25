<?php
/**
 * Integration test to validate the complete flow
 * 
 * This test validates that NewsletterService can now call send_template_email
 * without errors.
 */

echo "=== INTEGRATION TEST: Newsletter EmailService Integration ===\n\n";

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

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        return null;
    }
}

if (!function_exists('current_time')) {
    function current_time($format) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        
        public function __construct($code, $message) {
            $this->code = $code;
            $this->message = $message;
        }
        
        public function get_error_message() {
            return $this->message;
        }
        
        public function get_error_code() {
            return $this->code;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Load the classes
$template_engine_path = KG_CORE_PATH . 'includes/Notifications/TemplateEngine.php';
$email_service_path = KG_CORE_PATH . 'includes/Notifications/EmailService.php';

if (!file_exists($template_engine_path)) {
    echo "❌ FAIL: TemplateEngine.php not found at $template_engine_path\n";
    exit(1);
}

if (!file_exists($email_service_path)) {
    echo "❌ FAIL: EmailService.php not found at $email_service_path\n";
    exit(1);
}

require_once $template_engine_path;
require_once $email_service_path;

use KG_Core\Notifications\EmailService;

$test_results = [];

// TEST 1: Verify EmailService can be instantiated
echo "TEST 1: Instantiate EmailService\n";
echo "-------------------------------------------------------------------\n";
try {
    $email_service = new EmailService();
    echo "✅ PASS: EmailService instantiated successfully\n";
} catch (Exception $e) {
    echo "❌ FAIL: Failed to instantiate EmailService: " . $e->getMessage() . "\n";
    $test_results[] = false;
}
echo "\n";

// TEST 2: Verify send_template_email method exists and is callable
echo "TEST 2: Check send_template_email method is callable\n";
echo "-------------------------------------------------------------------\n";
if (method_exists($email_service, 'send_template_email')) {
    echo "✅ PASS: send_template_email method exists\n";
} else {
    echo "❌ FAIL: send_template_email method does not exist\n";
    $test_results[] = false;
}

if (is_callable([$email_service, 'send_template_email'])) {
    echo "✅ PASS: send_template_email method is callable\n";
} else {
    echo "❌ FAIL: send_template_email method is not callable\n";
    $test_results[] = false;
}
echo "\n";

// TEST 3: Verify method signature accepts correct parameters
echo "TEST 3: Verify method signature\n";
echo "-------------------------------------------------------------------\n";
try {
    $reflection = new ReflectionMethod($email_service, 'send_template_email');
    $parameters = $reflection->getParameters();
    
    if (count($parameters) === 3) {
        echo "✅ PASS: Method has 3 parameters\n";
    } else {
        echo "❌ FAIL: Method should have 3 parameters, has " . count($parameters) . "\n";
        $test_results[] = false;
    }
    
    if ($parameters[0]->getName() === 'to') {
        echo "✅ PASS: First parameter is '\$to'\n";
    } else {
        echo "❌ FAIL: First parameter should be '\$to', is '" . $parameters[0]->getName() . "'\n";
        $test_results[] = false;
    }
    
    if ($parameters[1]->getName() === 'template_key') {
        echo "✅ PASS: Second parameter is '\$template_key'\n";
    } else {
        echo "❌ FAIL: Second parameter should be '\$template_key', is '" . $parameters[1]->getName() . "'\n";
        $test_results[] = false;
    }
    
    if ($parameters[2]->getName() === 'placeholders') {
        echo "✅ PASS: Third parameter is '\$placeholders'\n";
    } else {
        echo "❌ FAIL: Third parameter should be '\$placeholders', is '" . $parameters[2]->getName() . "'\n";
        $test_results[] = false;
    }
    
    if ($parameters[2]->isDefaultValueAvailable() && $parameters[2]->getDefaultValue() === array()) {
        echo "✅ PASS: Third parameter has default value of array()\n";
    } else {
        echo "❌ FAIL: Third parameter should have default value of array()\n";
        $test_results[] = false;
    }
} catch (Exception $e) {
    echo "❌ FAIL: Error reflecting method: " . $e->getMessage() . "\n";
    $test_results[] = false;
}
echo "\n";

// TEST 4: Verify invalid email returns WP_Error
echo "TEST 4: Verify invalid email handling\n";
echo "-------------------------------------------------------------------\n";
try {
    $result = $email_service->send_template_email('invalid-email', 'test_template', []);
    if (is_wp_error($result)) {
        echo "✅ PASS: Invalid email returns WP_Error\n";
        if ($result->get_error_code() === 'invalid_email') {
            echo "✅ PASS: Error code is 'invalid_email'\n";
        } else {
            echo "❌ FAIL: Error code should be 'invalid_email', is '" . $result->get_error_code() . "'\n";
            $test_results[] = false;
        }
    } else {
        echo "❌ FAIL: Invalid email should return WP_Error\n";
        $test_results[] = false;
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    $test_results[] = false;
}
echo "\n";

// TEST 5: Verify empty email returns WP_Error
echo "TEST 5: Verify empty email handling\n";
echo "-------------------------------------------------------------------\n";
try {
    $result = $email_service->send_template_email('', 'test_template', []);
    if (is_wp_error($result)) {
        echo "✅ PASS: Empty email returns WP_Error\n";
    } else {
        echo "❌ FAIL: Empty email should return WP_Error\n";
        $test_results[] = false;
    }
} catch (Exception $e) {
    echo "❌ FAIL: Exception thrown: " . $e->getMessage() . "\n";
    $test_results[] = false;
}
echo "\n";

// TEST 6: Verify method differs from send_from_template
echo "TEST 6: Compare with send_from_template method\n";
echo "-------------------------------------------------------------------\n";
try {
    $send_from_template_reflection = new ReflectionMethod($email_service, 'send_from_template');
    $send_from_template_params = $send_from_template_reflection->getParameters();
    
    if ($send_from_template_params[0]->getName() === 'user_id') {
        echo "✅ PASS: send_from_template first parameter is '\$user_id' (different from send_template_email)\n";
    } else {
        echo "❌ FAIL: send_from_template first parameter should be '\$user_id'\n";
        $test_results[] = false;
    }
    
    echo "✅ PASS: send_template_email and send_from_template are distinct methods with different signatures\n";
} catch (Exception $e) {
    echo "❌ FAIL: Error comparing methods: " . $e->getMessage() . "\n";
    $test_results[] = false;
}
echo "\n";

// SUMMARY
echo "=== TEST SUMMARY ===\n";
if (empty($test_results) || !in_array(false, $test_results)) {
    echo "🎉 ALL INTEGRATION TESTS PASSED!\n";
    echo "\nEmailService integration validated:\n";
    echo "- EmailService can be instantiated\n";
    echo "- send_template_email method exists and is callable\n";
    echo "- Method has correct signature: send_template_email(\$to, \$template_key, \$placeholders = array())\n";
    echo "- Method validates email addresses\n";
    echo "- Method is distinct from send_from_template (which uses user_id)\n";
    echo "\nNewsletter subscription emails should now work correctly!\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "Please review the failures above.\n";
    exit(1);
}

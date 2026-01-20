<?php
/**
 * Contact Form Endpoint Implementation Test
 * 
 * This test validates:
 * 1. ContactRESTController exists and has correct class structure
 * 2. ContactRESTController is properly included in kg-core.php
 * 3. ContactRESTController is properly initialized
 */

echo "=== CONTACT FORM ENDPOINT IMPLEMENTATION TEST ===\n\n";

// Define the constant for the plugin path
if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/../');
}

$test_results = [];

// TEST 1: Check ContactRESTController file exists
echo "TEST 1: Checking ContactRESTController file\n";
echo "-------------------------------------------------------------------\n";
$controller_file = KG_CORE_PATH . 'includes/Contact/ContactRESTController.php';
if (file_exists($controller_file)) {
    echo "✅ PASS: ContactRESTController.php exists\n";
} else {
    echo "❌ FAIL: ContactRESTController.php missing\n";
    $test_results[] = false;
}
echo "\n";

// TEST 2: Check ContactRESTController class structure
echo "TEST 2: Checking ContactRESTController class structure\n";
echo "-------------------------------------------------------------------\n";
if (file_exists($controller_file)) {
    $content = file_get_contents($controller_file);
    
    // Check namespace
    if (strpos($content, "namespace KG_Core\Contact;") !== false) {
        echo "✅ PASS: Correct namespace\n";
    } else {
        echo "❌ FAIL: Incorrect namespace\n";
        $test_results[] = false;
    }
    
    // Check class extends WP_REST_Controller
    if (strpos($content, "class ContactRESTController extends WP_REST_Controller") !== false) {
        echo "✅ PASS: Class extends WP_REST_Controller\n";
    } else {
        echo "❌ FAIL: Class does not extend WP_REST_Controller\n";
        $test_results[] = false;
    }
    
    // Check if required methods exist in source code
    $required_methods = ['register_routes', 'handle_submit', 'log_contact_submission', 'get_client_ip'];
    
    foreach ($required_methods as $method) {
        if (preg_match("/function\s+{$method}\s*\(/", $content)) {
            echo "✅ PASS: Method {$method}() exists\n";
        } else {
            echo "❌ FAIL: Method {$method}() missing\n";
            $test_results[] = false;
        }
    }
} else {
    echo "⚠️  SKIP: Cannot check class structure (file missing)\n";
    $test_results[] = false;
}
echo "\n";

// TEST 3: Check kg-core.php includes ContactRESTController
echo "TEST 3: Checking kg-core.php includes ContactRESTController\n";
echo "-------------------------------------------------------------------\n";
$main_file = KG_CORE_PATH . 'kg-core.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    
    // Check if ContactRESTController is required
    if (strpos($content, "includes/Contact/ContactRESTController.php") !== false) {
        echo "✅ PASS: ContactRESTController.php is included in kg-core.php\n";
    } else {
        echo "❌ FAIL: ContactRESTController.php not included in kg-core.php\n";
        $test_results[] = false;
    }
    
    // Check if ContactRESTController is initialized
    if (strpos($content, "KG_Core\Contact\ContactRESTController") !== false) {
        echo "✅ PASS: ContactRESTController is initialized in kg-core.php\n";
    } else {
        echo "❌ FAIL: ContactRESTController not initialized in kg-core.php\n";
        $test_results[] = false;
    }
} else {
    echo "❌ FAIL: kg-core.php not found\n";
    $test_results[] = false;
}
echo "\n";

// TEST 4: Check ContactRESTController endpoint configuration
echo "TEST 4: Checking endpoint configuration\n";
echo "-------------------------------------------------------------------\n";
if (file_exists($controller_file)) {
    $content = file_get_contents($controller_file);
    
    // Check namespace
    if (strpos($content, "namespace KG_Core\Contact;") !== false) {
        echo "✅ PASS: Correct namespace\n";
    } else {
        echo "❌ FAIL: Incorrect namespace\n";
        $test_results[] = false;
    }
    
    // Check endpoint path
    if (strpos($content, "'kg/v1'") !== false) {
        echo "✅ PASS: Correct API namespace (kg/v1)\n";
    } else {
        echo "❌ FAIL: Incorrect API namespace\n";
        $test_results[] = false;
    }
    
    if (strpos($content, "'contact'") !== false) {
        echo "✅ PASS: Correct REST base (contact)\n";
    } else {
        echo "❌ FAIL: Incorrect REST base\n";
        $test_results[] = false;
    }
    
    // Check required parameters
    $required_params = ['name', 'email', 'subject', 'message'];
    foreach ($required_params as $param) {
        if (preg_match("/'{$param}'\s*=>\s*\[/", $content)) {
            echo "✅ PASS: Parameter '{$param}' defined\n";
        } else {
            echo "❌ FAIL: Parameter '{$param}' missing\n";
            $test_results[] = false;
        }
    }
    
    // Check email recipient
    if (strpos($content, "iletisim@kidsgourmet.com.tr") !== false) {
        echo "✅ PASS: Correct email recipient\n";
    } else {
        echo "❌ FAIL: Incorrect email recipient\n";
        $test_results[] = false;
    }
} else {
    echo "⚠️  SKIP: Cannot check endpoint configuration (file missing)\n";
    $test_results[] = false;
}
echo "\n";

// TEST 5: Check CORS configuration
echo "TEST 5: Checking CORS configuration\n";
echo "-------------------------------------------------------------------\n";
$cors_file = KG_CORE_PATH . 'includes/CORS/CORSHandler.php';
if (file_exists($cors_file)) {
    $cors_content = file_get_contents($cors_file);
    
    // Check required origins
    $required_origins = [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:3002',
        'https://kidsgourmet.com.tr',
        'https://www.kidsgourmet.com.tr',
        'https://api.kidsgourmet.com.tr',
        'https://kidsgourmet-web.vercel.app',
    ];
    
    foreach ($required_origins as $origin) {
        if (strpos($cors_content, $origin) !== false) {
            echo "✅ PASS: Origin '{$origin}' allowed\n";
        } else {
            echo "❌ FAIL: Origin '{$origin}' missing\n";
            $test_results[] = false;
        }
    }
} else {
    echo "❌ FAIL: CORSHandler.php not found\n";
    $test_results[] = false;
}
echo "\n";

// FINAL RESULTS
echo "=== TEST SUMMARY ===\n";
if (empty($test_results) || !in_array(false, $test_results)) {
    echo "✅ ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    exit(1);
}

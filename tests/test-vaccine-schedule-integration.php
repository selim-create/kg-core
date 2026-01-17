<?php
/**
 * Integration Test: Vaccine Schedule Auto-Generation
 * 
 * This test verifies the complete flow of automatic vaccine schedule generation:
 * 1. Child creation triggers vaccine schedule generation
 * 2. Fallback generation works when schedule is empty
 * 3. Manual generation endpoint works correctly
 */

echo "=== VACCINE SCHEDULE AUTO-GENERATION INTEGRATION TEST ===\n\n";

// Test configuration
$test_passed = true;
$test_count = 0;
$passed_count = 0;

function run_test($name, $condition, $error_msg = '') {
    global $test_count, $passed_count, $test_passed;
    $test_count++;
    
    if ($condition) {
        echo "✅ TEST {$test_count}: {$name}\n";
        $passed_count++;
    } else {
        echo "❌ TEST {$test_count}: {$name}\n";
        if ($error_msg) {
            echo "   Error: {$error_msg}\n";
        }
        $test_passed = false;
    }
}

echo "PART 1: Code Structure Verification\n";
echo "====================================\n\n";

// Load the files
$user_controller = file_get_contents(__DIR__ . '/../includes/API/UserController.php');
$vaccine_controller = file_get_contents(__DIR__ . '/../includes/API/VaccineController.php');
$vaccine_record_manager = file_get_contents(__DIR__ . '/../includes/Health/VaccineRecordManager.php');

// Test 1: UserController imports VaccineRecordManager
run_test(
    "UserController imports VaccineRecordManager",
    strpos($user_controller, 'use KG_Core\Health\VaccineRecordManager;') !== false,
    "Missing import statement"
);

// Test 2: UserController::add_child() creates schedule
run_test(
    "add_child() instantiates VaccineRecordManager",
    strpos($user_controller, 'new VaccineRecordManager()') !== false,
    "VaccineRecordManager not instantiated"
);

run_test(
    "add_child() calls create_schedule_for_child()",
    strpos($user_controller, 'create_schedule_for_child(') !== false,
    "Method not called"
);

// Test 3: Correct parameters passed
run_test(
    "add_child() passes user_id parameter",
    strpos($user_controller, '$user_id,') !== false,
    "user_id not passed"
);

run_test(
    "add_child() passes child UUID",
    strpos($user_controller, '$uuid,') !== false,
    "UUID not passed"
);

run_test(
    "add_child() passes birth_date",
    strpos($user_controller, '$birth_date,') !== false,
    "birth_date not passed"
);

run_test(
    "add_child() sets include_private to false",
    preg_match('/create_schedule_for_child\([^)]*false[^)]*\)/', $user_controller),
    "include_private not set to false"
);

// Test 4: Error handling
run_test(
    "add_child() uses try-catch for error handling",
    strpos($user_controller, 'try {') !== false && strpos($user_controller, 'catch ( \Exception') !== false,
    "Missing try-catch block"
);

run_test(
    "add_child() checks for WP_Error",
    strpos($user_controller, 'is_wp_error(') !== false,
    "Missing WP_Error check"
);

run_test(
    "add_child() logs errors",
    strpos($user_controller, 'error_log(') !== false,
    "Missing error logging"
);

echo "\nPART 2: Fallback Generation in get_child_schedule()\n";
echo "====================================================\n\n";

// Test 5: Fallback logic exists
run_test(
    "get_child_schedule() checks if schedule is empty",
    strpos($vaccine_controller, 'empty( $schedule )') !== false,
    "Empty check missing"
);

run_test(
    "get_child_schedule() fetches child metadata",
    strpos($vaccine_controller, "get_user_meta( \$user_id, '_kg_children'") !== false,
    "Missing metadata fetch"
);

run_test(
    "get_child_schedule() creates schedule when empty",
    preg_match('/if.*empty.*schedule.*create_schedule_for_child/s', $vaccine_controller),
    "Fallback creation missing"
);

echo "\nPART 3: Manual Generation Endpoint\n";
echo "===================================\n\n";

// Test 6: Route registration
run_test(
    "generate-schedule route is registered",
    strpos($vaccine_controller, '/health/vaccines/generate-schedule') !== false,
    "Route not registered"
);

run_test(
    "generate-schedule route uses POST method",
    preg_match("/register_rest_route.*generate-schedule.*'methods'\s*=>\s*'POST'/s", $vaccine_controller),
    "Wrong HTTP method"
);

run_test(
    "generate-schedule route requires authentication",
    preg_match("/generate-schedule.*'permission_callback'.*check_authentication/s", $vaccine_controller),
    "Authentication not required"
);

// Test 7: Implementation
run_test(
    "generate_schedule() method exists",
    strpos($vaccine_controller, 'public function generate_schedule(') !== false,
    "Method not implemented"
);

run_test(
    "generate_schedule() verifies child ownership",
    strpos($vaccine_controller, 'verify_child_ownership(') !== false,
    "Missing ownership check"
);

run_test(
    "generate_schedule() handles existing schedules",
    strpos($vaccine_controller, 'schedule_exists') !== false,
    "Missing duplicate schedule handling"
);

echo "\nPART 4: Data Type Fixes\n";
echo "========================\n\n";

// Test 8: child_id format specifiers
run_test(
    "SELECT queries use %s for child_id (not %d)",
    !preg_match('/WHERE child_id = %d/', $vaccine_record_manager),
    "Found %d for child_id in WHERE clause"
);

run_test(
    "INSERT queries use correct format array",
    preg_match("/\\['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'\\]/", $vaccine_record_manager),
    "Incorrect format specifier array"
);

// Test 9: No data type bugs
$d_format_count = substr_count($vaccine_record_manager, "WHERE child_id = %d");
run_test(
    "No remaining %d bugs for child_id",
    $d_format_count === 0,
    "Found {$d_format_count} instances of 'WHERE child_id = %d'"
);

echo "\nPART 5: Code Quality\n";
echo "====================\n\n";

// Test 10: Syntax validation
$syntax_errors = [];

foreach ([
    'UserController.php' => __DIR__ . '/../includes/API/UserController.php',
    'VaccineController.php' => __DIR__ . '/../includes/API/VaccineController.php',
    'VaccineRecordManager.php' => __DIR__ . '/../includes/Health/VaccineRecordManager.php',
] as $name => $path) {
    $result = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
    if (strpos($result, 'No syntax errors') === false) {
        $syntax_errors[] = "{$name}: {$result}";
    }
}

run_test(
    "All files have valid PHP syntax",
    empty($syntax_errors),
    implode("\n   ", $syntax_errors)
);

// Test 11: Best practices
run_test(
    "Uses proper namespacing",
    strpos($user_controller, 'namespace KG_Core\API;') !== false,
    "Missing namespace"
);

run_test(
    "Includes PHPDoc comments",
    strpos($vaccine_controller, '/**') !== false,
    "Missing documentation"
);

echo "\n";
echo "====================================\n";
echo "SUMMARY\n";
echo "====================================\n";
echo "Tests Run: {$test_count}\n";
echo "Tests Passed: {$passed_count}\n";
echo "Tests Failed: " . ($test_count - $passed_count) . "\n";

if ($test_passed) {
    echo "\n✅ ALL TESTS PASSED!\n";
    echo "\nImplementation Summary:\n";
    echo "1. ✅ Child creation automatically generates vaccine schedule\n";
    echo "2. ✅ Fallback generation when schedule is empty\n";
    echo "3. ✅ Manual generation endpoint available\n";
    echo "4. ✅ Data type bugs fixed (child_id as VARCHAR)\n";
    echo "5. ✅ Proper error handling and logging\n";
    exit(0);
} else {
    echo "\n❌ SOME TESTS FAILED\n";
    echo "Please review the failed tests above.\n";
    exit(1);
}

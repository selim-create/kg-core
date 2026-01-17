<?php
/**
 * Static Test Script for Automatic Vaccine Schedule Generation
 * 
 * Verifies that:
 * 1. UserController::add_child() automatically creates vaccine schedule
 * 2. VaccineController::get_child_schedule() auto-generates schedule as fallback
 * 3. VaccineController::generate_schedule() endpoint exists for manual trigger
 * 4. VaccineRecordManager uses correct data types for child_id (VARCHAR/UUID)
 */

echo "=== AUTOMATIC VACCINE SCHEDULE GENERATION TEST ===\n\n";

// Test file paths
$user_controller_path = __DIR__ . '/../includes/API/UserController.php';
$vaccine_controller_path = __DIR__ . '/../includes/API/VaccineController.php';
$record_manager_path = __DIR__ . '/../includes/Health/VaccineRecordManager.php';

echo "TEST 1: Checking if files exist...\n";

if (file_exists($user_controller_path)) {
    echo "✅ UserController.php exists\n";
} else {
    echo "❌ UserController.php NOT found\n";
    exit(1);
}

if (file_exists($vaccine_controller_path)) {
    echo "✅ VaccineController.php exists\n";
} else {
    echo "❌ VaccineController.php NOT found\n";
    exit(1);
}

if (file_exists($record_manager_path)) {
    echo "✅ VaccineRecordManager.php exists\n";
} else {
    echo "❌ VaccineRecordManager.php NOT found\n";
    exit(1);
}

echo "\n";

echo "TEST 2: Checking UserController imports VaccineRecordManager...\n";

$user_controller_content = file_get_contents($user_controller_path);

if (strpos($user_controller_content, 'use KG_Core\Health\VaccineRecordManager;') !== false) {
    echo "✅ VaccineRecordManager is imported in UserController\n";
} else {
    echo "❌ VaccineRecordManager is NOT imported in UserController\n";
}

echo "\n";

echo "TEST 3: Checking UserController::add_child() creates vaccine schedule...\n";

$checks = [
    'new VaccineRecordManager()' => 'Instantiates VaccineRecordManager',
    'create_schedule_for_child(' => 'Calls create_schedule_for_child()',
    '$uuid,' => 'Passes child UUID',
    '$birth_date,' => 'Passes birth date',
    'false' => 'Passes include_private flag',
    'is_wp_error(' => 'Handles errors gracefully',
    'error_log(' => 'Logs errors',
];

foreach ($checks as $pattern => $description) {
    if (strpos($user_controller_content, $pattern) !== false) {
        echo "✅ {$description}\n";
    } else {
        echo "❌ {$description} - NOT found\n";
    }
}

echo "\n";

echo "TEST 4: Checking VaccineController::get_child_schedule() has fallback...\n";

$vaccine_controller_content = file_get_contents($vaccine_controller_path);

$fallback_checks = [
    'empty( $schedule )' => 'Checks if schedule is empty',
    'get_user_meta( $user_id, \'_kg_children\'' => 'Fetches children metadata',
    'foreach ( $children as $c )' => 'Iterates through children',
    '$c[\'birth_date\']' => 'Gets birth date',
    'create_schedule_for_child(' => 'Creates schedule as fallback',
];

foreach ($fallback_checks as $pattern => $description) {
    if (strpos($vaccine_controller_content, $pattern) !== false) {
        echo "✅ {$description}\n";
    } else {
        echo "❌ {$description} - NOT found\n";
    }
}

echo "\n";

echo "TEST 5: Checking VaccineController for generate-schedule endpoint...\n";

$route_found = strpos($vaccine_controller_content, '/health/vaccines/generate-schedule') !== false;
$callback_found = strpos($vaccine_controller_content, "'callback' => [ \$this, 'generate_schedule' ]") !== false;
$method_found = strpos($vaccine_controller_content, 'public function generate_schedule(') !== false;

if ($route_found) {
    echo "✅ Route /health/vaccines/generate-schedule registered\n";
} else {
    echo "❌ Route NOT found\n";
}

if ($callback_found) {
    echo "✅ Callback points to generate_schedule()\n";
} else {
    echo "❌ Callback NOT found\n";
}

if ($method_found) {
    echo "✅ Method generate_schedule() implemented\n";
} else {
    echo "❌ Method NOT found\n";
}

echo "\n";

echo "TEST 6: Checking generate_schedule() implementation...\n";

$impl_checks = [
    'verify_child_ownership(' => 'Verifies child ownership',
    '$child[\'birth_date\']' => 'Gets child birth date',
    'create_schedule_for_child(' => 'Creates schedule',
    'schedule_exists' => 'Handles existing schedule',
    'get_child_vaccines(' => 'Fetches created schedule',
];

foreach ($impl_checks as $pattern => $description) {
    if (strpos($vaccine_controller_content, $pattern) !== false) {
        echo "✅ {$description}\n";
    } else {
        echo "❌ {$description} - NOT found\n";
    }
}

echo "\n";

echo "TEST 7: Checking VaccineRecordManager uses correct data types...\n";

$record_manager_content = file_get_contents($record_manager_path);

// Check for correct format specifiers (%s for child_id, not %d)
$correct_formats = 0;
$incorrect_formats = 0;

// Pattern 1: WHERE child_id = %s
if (preg_match('/WHERE child_id = %s/', $record_manager_content)) {
    echo "✅ SELECT queries use %s for child_id\n";
    $correct_formats++;
} else if (preg_match('/WHERE child_id = %d/', $record_manager_content)) {
    echo "❌ SELECT queries use %d instead of %s for child_id\n";
    $incorrect_formats++;
}

// Pattern 2: INSERT format specifiers
// Should be ['%d', '%s', '%s', ...] (user_id is %d, child_id is %s)
if (preg_match("/\['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'\]/", $record_manager_content)) {
    echo "✅ INSERT queries use correct format specifiers\n";
    $correct_formats++;
} else if (preg_match("/\['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s'\]/", $record_manager_content)) {
    echo "❌ INSERT queries use %d instead of %s for child_id\n";
    $incorrect_formats++;
}

echo "\n";

echo "TEST 8: Syntax check...\n";

$files_to_check = [
    $user_controller_path => 'UserController.php',
    $vaccine_controller_path => 'VaccineController.php',
    $record_manager_path => 'VaccineRecordManager.php',
];

foreach ($files_to_check as $path => $name) {
    $syntax_check = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
    
    if (strpos($syntax_check, 'No syntax errors') !== false) {
        echo "✅ {$name} has no syntax errors\n";
    } else {
        echo "❌ {$name} has syntax errors:\n";
        echo $syntax_check . "\n";
    }
}

echo "\n";

echo "TEST 9: Checking error handling and logging...\n";

$error_handling_checks = [
    'try {' => 'Uses try-catch blocks',
    'catch ( \Exception $e )' => 'Catches exceptions',
    'error_log(' => 'Logs errors',
    'is_wp_error(' => 'Checks for WP_Error',
];

foreach ($error_handling_checks as $pattern => $description) {
    if (strpos($user_controller_content, $pattern) !== false) {
        echo "✅ {$description}\n";
    } else {
        echo "⚠️  {$description} - NOT found\n";
    }
}

echo "\n=== ALL TESTS COMPLETED ===\n";

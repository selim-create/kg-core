<?php
/**
 * Static Test Script for Vaccine API Endpoints
 * 
 * Verifies that the new vaccine endpoint code is properly implemented:
 * 1. GET /kg/v1/health/vaccines/upcoming
 * 2. GET /kg/v1/health/vaccines/history
 * 3. GET /kg/v1/health/vaccines/overdue
 */

echo "=== VACCINE ENDPOINTS STATIC TEST ===\n\n";

// Test file paths
$controller_path = __DIR__ . '/../includes/API/VaccineController.php';
$record_manager_path = __DIR__ . '/../includes/Health/VaccineRecordManager.php';

echo "TEST 1: Checking if files exist...\n";

if (file_exists($controller_path)) {
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

echo "TEST 2: Checking VaccineController for route registrations...\n";

$controller_content = file_get_contents($controller_path);

$routes = [
    '/health/vaccines/upcoming' => 'get_upcoming_vaccines',
    '/health/vaccines/history' => 'get_vaccine_history',
    '/health/vaccines/overdue' => 'get_overdue_vaccines'
];

foreach ($routes as $route => $callback) {
    $route_found = strpos($controller_content, "'{$route}'") !== false;
    $callback_found = strpos($controller_content, "'{$callback}'") !== false || 
                      strpos($controller_content, "'callback' => [ \$this, '{$callback}' ]") !== false;
    
    if ($route_found && $callback_found) {
        echo "✅ Route {$route} -> {$callback}()\n";
    } else {
        if (!$route_found) {
            echo "❌ Route {$route} NOT found\n";
        }
        if (!$callback_found) {
            echo "❌ Callback {$callback}() NOT found for route\n";
        }
    }
}

echo "\n";

echo "TEST 3: Checking VaccineController for callback methods...\n";

$controller_methods = [
    'public function get_upcoming_vaccines(' => 'get_upcoming_vaccines()',
    'public function get_vaccine_history(' => 'get_vaccine_history()',
    'public function get_overdue_vaccines(' => 'get_overdue_vaccines()',
    'private function get_user_children(' => 'get_user_children()'
];

foreach ($controller_methods as $signature => $method_name) {
    if (strpos($controller_content, $signature) !== false) {
        echo "✅ Method implemented: {$method_name}\n";
    } else {
        echo "❌ Method NOT found: {$method_name}\n";
    }
}

echo "\n";

echo "TEST 4: Checking VaccineRecordManager for required methods...\n";

$record_manager_content = file_get_contents($record_manager_path);

$record_methods = [
    'public function get_upcoming_vaccines(' => 'get_upcoming_vaccines()',
    'public function get_overdue_vaccines(' => 'get_overdue_vaccines()',
    'public function get_child_vaccines(' => 'get_child_vaccines()'
];

foreach ($record_methods as $signature => $method_name) {
    if (strpos($record_manager_content, $signature) !== false) {
        echo "✅ Method implemented: {$method_name}\n";
    } else {
        echo "❌ Method NOT found: {$method_name}\n";
    }
}

echo "\n";

echo "TEST 5: Checking for proper error handling in get_overdue_vaccines()...\n";

// Check if get_overdue_vaccines handles the optional child_id parameter
$has_child_id_check = strpos($controller_content, 'if ( $child_id )') !== false;
$has_user_children_call = strpos($controller_content, 'get_user_children( $user_id )') !== false;
$has_array_merge = strpos($controller_content, 'array_merge') !== false;

if ($has_child_id_check) {
    echo "✅ Handles optional child_id parameter\n";
} else {
    echo "❌ Missing child_id parameter check\n";
}

if ($has_user_children_call) {
    echo "✅ Calls get_user_children() for all children\n";
} else {
    echo "❌ Missing get_user_children() call\n";
}

if ($has_array_merge) {
    echo "✅ Merges results from multiple children\n";
} else {
    echo "⚠️  May not properly merge results from multiple children\n";
}

echo "\n";

echo "TEST 6: Syntax check...\n";

$syntax_check = shell_exec("php -l " . escapeshellarg($controller_path) . " 2>&1");

if (strpos($syntax_check, 'No syntax errors') !== false) {
    echo "✅ VaccineController.php has no syntax errors\n";
} else {
    echo "❌ VaccineController.php has syntax errors:\n";
    echo $syntax_check . "\n";
}

$syntax_check = shell_exec("php -l " . escapeshellarg($record_manager_path) . " 2>&1");

if (strpos($syntax_check, 'No syntax errors') !== false) {
    echo "✅ VaccineRecordManager.php has no syntax errors\n";
} else {
    echo "❌ VaccineRecordManager.php has syntax errors:\n";
    echo $syntax_check . "\n";
}

echo "\n=== ALL TESTS COMPLETED ===\n";

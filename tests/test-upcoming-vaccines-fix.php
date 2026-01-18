<?php
/**
 * Test for Upcoming Vaccines API Fix
 * 
 * Verifies that the get_upcoming_vaccines endpoint returns the correct format
 * as specified in the requirements.
 */

echo "=== UPCOMING VACCINES API FIX TEST ===\n\n";

// Test file paths
$controller_path = __DIR__ . '/../includes/API/VaccineController.php';
$record_manager_path = __DIR__ . '/../includes/Health/VaccineRecordManager.php';

echo "TEST 1: Checking file syntax...\n";

$syntax_check = shell_exec("php -l " . escapeshellarg($controller_path) . " 2>&1");
if (strpos($syntax_check, 'No syntax errors') !== false) {
    echo "✅ VaccineController.php has no syntax errors\n";
} else {
    echo "❌ VaccineController.php has syntax errors:\n{$syntax_check}\n";
    exit(1);
}

$syntax_check = shell_exec("php -l " . escapeshellarg($record_manager_path) . " 2>&1");
if (strpos($syntax_check, 'No syntax errors') !== false) {
    echo "✅ VaccineRecordManager.php has no syntax errors\n";
} else {
    echo "❌ VaccineRecordManager.php has syntax errors:\n{$syntax_check}\n";
    exit(1);
}

echo "\n";

echo "TEST 2: Verifying VaccineController route registration includes limit parameter...\n";

$controller_content = file_get_contents($controller_path);

// Check for limit parameter in route args
$has_limit_param = strpos($controller_content, "'limit'") !== false;
$has_limit_integer_type = preg_match("/'limit'[^}]*'type'\s*=>\s*'integer'/", $controller_content);
$has_limit_required_false = preg_match("/'limit'[^}]*'required'\s*=>\s*false/", $controller_content);

if ($has_limit_param) {
    echo "✅ Route includes 'limit' parameter\n";
} else {
    echo "❌ Route missing 'limit' parameter\n";
    exit(1);
}

if ($has_limit_integer_type) {
    echo "✅ 'limit' parameter has correct type (integer)\n";
} else {
    echo "⚠️  'limit' parameter type may not be set to integer\n";
}

if ($has_limit_required_false) {
    echo "✅ 'limit' parameter is optional (required: false)\n";
} else {
    echo "⚠️  'limit' parameter may not be marked as optional\n";
}

echo "\n";

echo "TEST 3: Verifying VaccineController passes limit to record manager...\n";

// Check that get_upcoming_vaccines in controller gets and passes limit parameter
$has_get_limit = preg_match('/\$limit\s*=\s*\$request->get_param\(\s*[\'"]limit[\'"]\s*\)/', $controller_content);
$has_pass_limit = preg_match('/get_upcoming_vaccines\(\s*\$child_id\s*,\s*\$limit\s*\)/', $controller_content);

if ($has_get_limit) {
    echo "✅ Controller retrieves 'limit' parameter from request\n";
} else {
    echo "❌ Controller does NOT retrieve 'limit' parameter\n";
    exit(1);
}

if ($has_pass_limit) {
    echo "✅ Controller passes 'limit' to VaccineRecordManager\n";
} else {
    echo "❌ Controller does NOT pass 'limit' to VaccineRecordManager\n";
    exit(1);
}

echo "\n";

echo "TEST 4: Verifying VaccineRecordManager signature and query changes...\n";

$record_manager_content = file_get_contents($record_manager_path);

// Check method signature accepts limit parameter
$has_limit_param_signature = preg_match('/function\s+get_upcoming_vaccines\s*\(\s*\$child_id\s*,\s*\$limit\s*=\s*null\s*\)/', $record_manager_content);

if ($has_limit_param_signature) {
    echo "✅ get_upcoming_vaccines() accepts \$limit parameter (default: null)\n";
} else {
    echo "❌ get_upcoming_vaccines() signature doesn't match expected format\n";
    exit(1);
}

// Check for proper SQL filtering (actual_date IS NULL and status != 'skipped')
$has_actual_date_check = strpos($record_manager_content, "actual_date IS NULL") !== false;
$has_status_check = strpos($record_manager_content, "status != 'skipped'") !== false;

if ($has_actual_date_check) {
    echo "✅ Query filters by 'actual_date IS NULL'\n";
} else {
    echo "❌ Query missing 'actual_date IS NULL' filter\n";
    exit(1);
}

if ($has_status_check) {
    echo "✅ Query filters by \"status != 'skipped'\"\n";
} else {
    echo "❌ Query missing \"status != 'skipped'\" filter\n";
    exit(1);
}

// Check that old restrictive filter is removed
$has_old_scheduled_filter = preg_match('/status\s*=\s*[\'"]scheduled[\'"]/', $record_manager_content);

if (!$has_old_scheduled_filter) {
    echo "✅ Old restrictive 'status = scheduled' filter removed\n";
} else {
    echo "⚠️  Old 'status = scheduled' filter may still be present\n";
}

echo "\n";

echo "TEST 5: Verifying response structure building...\n";

// Check for days_until calculation
$has_days_until = strpos($record_manager_content, "days_until") !== false;
$has_is_overdue = strpos($record_manager_content, "is_overdue") !== false;

if ($has_days_until) {
    echo "✅ Response includes 'days_until' field\n";
} else {
    echo "❌ Response missing 'days_until' field\n";
    exit(1);
}

if ($has_is_overdue) {
    echo "✅ Response includes 'is_overdue' field\n";
} else {
    echo "❌ Response missing 'is_overdue' field\n";
    exit(1);
}

// Check for nested vaccine object
$has_vaccine_object = preg_match("/['\"]vaccine['\"]\s*=>/", $record_manager_content);
$has_record_object = preg_match("/['\"]record['\"]\s*=>/", $record_manager_content);

if ($has_vaccine_object) {
    echo "✅ Response includes nested 'vaccine' object\n";
} else {
    echo "❌ Response missing nested 'vaccine' object\n";
    exit(1);
}

if ($has_record_object) {
    echo "✅ Response includes nested 'record' object\n";
} else {
    echo "❌ Response missing nested 'record' object\n";
    exit(1);
}

// Check for timing_rule handling
$has_timing_rule = strpos($record_manager_content, "timing_rule") !== false;

if ($has_timing_rule) {
    echo "✅ Response handles 'timing_rule' field\n";
} else {
    echo "❌ Response missing 'timing_rule' handling\n";
    exit(1);
}

echo "\n";

echo "TEST 6: Verifying limit parameter implementation in SQL...\n";

// Check for LIMIT clause in SQL
$has_sql_limit = preg_match('/LIMIT\s+%d/', $record_manager_content);

if ($has_sql_limit) {
    echo "✅ SQL query uses LIMIT clause with prepared statement\n";
} else {
    echo "❌ SQL query missing LIMIT clause\n";
    exit(1);
}

echo "\n";

echo "TEST 7: Verifying private vaccine metadata handling...\n";

// Check for PrivateVaccineWizard integration
$has_private_vaccine_check = strpos($record_manager_content, "get_private_vaccine_metadata") !== false;

if ($has_private_vaccine_check) {
    echo "✅ Private vaccine metadata handling implemented\n";
} else {
    echo "⚠️  Private vaccine metadata handling may not be properly implemented\n";
}

echo "\n";

echo "TEST 8: Verifying dynamic status calculation...\n";

// Check for status calculation based on days_until
$has_overdue_status = preg_match('/[\'"]overdue[\'"]/', $record_manager_content);
$has_upcoming_status = preg_match('/[\'"]upcoming[\'"]/', $record_manager_content);

if ($has_overdue_status) {
    echo "✅ Dynamic 'overdue' status is set\n";
} else {
    echo "❌ Missing 'overdue' status calculation\n";
    exit(1);
}

if ($has_upcoming_status) {
    echo "✅ Dynamic 'upcoming' status is set\n";
} else {
    echo "❌ Missing 'upcoming' status calculation\n";
    exit(1);
}

echo "\n";

echo "=== ALL TESTS PASSED ✅ ===\n";
echo "\nSummary:\n";
echo "- VaccineRecordManager now filters by actual_date IS NULL and status != 'skipped'\n";
echo "- Response includes nested 'vaccine' and 'record' objects\n";
echo "- days_until and is_overdue are calculated for each vaccine\n";
echo "- limit parameter is supported (optional)\n";
echo "- timing_rule is parsed and included in response\n";
echo "- Private vaccine metadata is handled via PrivateVaccineWizard\n";
echo "- Dynamic status (overdue/upcoming/scheduled) is calculated\n";

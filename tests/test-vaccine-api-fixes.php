<?php
/**
 * Static Test Script for Vaccine API Fixes
 * 
 * Tests:
 * 1. Private vaccine addition with user_meta lookup
 * 2. Nested vaccine response structure
 * 3. Schedule response with stats and metadata
 */

echo "=== VACCINE API FIXES STATIC TEST ===\n\n";

// ===== TEST 1: Check Updated Method Signatures =====
echo "TEST 1: Checking updated method signatures...\n";

$wizard_path = __DIR__ . '/../includes/Health/PrivateVaccineWizard.php';
if (!file_exists($wizard_path)) {
    echo "❌ PrivateVaccineWizard.php NOT found\n";
    exit(1);
}

$wizard_content = file_get_contents($wizard_path);

// Check validate_addition signature
if (strpos($wizard_content, 'public function validate_addition($user_id, $child_id, $type, $brand_code, $options = [])') !== false) {
    echo "✅ PrivateVaccineWizard::validate_addition() signature updated with \$user_id\n";
} else {
    echo "❌ PrivateVaccineWizard::validate_addition() signature NOT updated\n";
}

// Check for user_meta usage instead of wp_posts
if (strpos($wizard_content, "get_user_meta(\$user_id, '_kg_children', true)") !== false) {
    echo "✅ PrivateVaccineWizard uses get_user_meta for child lookup\n";
} else {
    echo "❌ PrivateVaccineWizard does NOT use get_user_meta\n";
}

// Count occurrences of wp_posts query in validate_addition
preg_match('/public function validate_addition.*?(?=public function|$)/s', $wizard_content, $matches);
if (isset($matches[0])) {
    $validate_method = $matches[0];
    $wpdb_posts_count = substr_count($validate_method, 'wpdb->posts');
    if ($wpdb_posts_count === 0) {
        echo "✅ wp_posts query removed from validate_addition()\n";
    } else {
        echo "❌ wp_posts query still exists in validate_addition() ({$wpdb_posts_count} occurrences)\n";
    }
}

// Check add_to_schedule method
preg_match('/public function add_to_schedule.*?(?=public function|private function|$)/s', $wizard_content, $matches);
if (isset($matches[0])) {
    $add_method = $matches[0];
    if (strpos($add_method, "get_user_meta(\$user_id, '_kg_children', true)") !== false) {
        echo "✅ add_to_schedule() uses get_user_meta for child lookup\n";
    } else {
        echo "❌ add_to_schedule() does NOT use get_user_meta\n";
    }
    
    $wpdb_posts_count = substr_count($add_method, 'wpdb->posts');
    if ($wpdb_posts_count === 0) {
        echo "✅ wp_posts query removed from add_to_schedule()\n";
    } else {
        echo "❌ wp_posts query still exists in add_to_schedule() ({$wpdb_posts_count} occurrences)\n";
    }
}

echo "\n";

// ===== TEST 2: Check VaccinePrivateController Updates =====
echo "TEST 2: Checking VaccinePrivateController updates...\n";

$private_controller_path = __DIR__ . '/../includes/API/VaccinePrivateController.php';
if (!file_exists($private_controller_path)) {
    echo "❌ VaccinePrivateController.php NOT found\n";
    exit(1);
}

$private_controller_content = file_get_contents($private_controller_path);

// Check if validate_vaccine passes user_id
if (strpos($private_controller_content, "\$wizard->validate_addition( \$user_id, \$child_id, \$type, \$brand_code, \$options )") !== false) {
    echo "✅ validate_vaccine() passes \$user_id to validate_addition()\n";
} else {
    echo "❌ validate_vaccine() does NOT pass \$user_id correctly\n";
}

echo "\n";

// ===== TEST 3: Test VaccineRecordManager Response Structure =====
echo "TEST 3: Checking VaccineRecordManager response structure...\n";

$manager_path = __DIR__ . '/../includes/Health/VaccineRecordManager.php';
if (!file_exists($manager_path)) {
    echo "❌ VaccineRecordManager.php NOT found\n";
    exit(1);
}

$manager_content = file_get_contents($manager_path);

// Check for nested vaccine object structure
if (strpos($manager_content, "\$record['vaccine'] = \$vaccine;") !== false) {
    echo "✅ VaccineRecordManager creates nested vaccine object\n";
} else {
    echo "❌ Nested vaccine object NOT found in VaccineRecordManager\n";
}

// Check for timing_rule in query
if (strpos($manager_content, 'v.timing_rule') !== false) {
    echo "✅ timing_rule field included in query\n";
} else {
    echo "❌ timing_rule NOT included in query\n";
}

// Check for integer conversion
if (strpos($manager_content, "(int)\$record['id']") !== false) {
    echo "✅ Record IDs converted to integers\n";
} else {
    echo "❌ Record IDs NOT converted to integers\n";
}

// Check for timing_rule parsing
if (strpos($manager_content, "json_decode(\$record['timing_rule'], true)") !== false) {
    echo "✅ timing_rule JSON parsing implemented\n";
} else {
    echo "❌ timing_rule JSON parsing NOT found\n";
}

// Check for removal of redundant fields
if (strpos($manager_content, "unset(\$record['name'])") !== false &&
    strpos($manager_content, "unset(\$record['timing_rule'])") !== false) {
    echo "✅ Redundant fields removed from top level\n";
} else {
    echo "❌ Redundant field removal NOT found\n";
}

echo "\n";

// ===== TEST 4: Test VaccineController Schedule Response =====
echo "TEST 4: Checking VaccineController schedule response structure...\n";

$controller_path = __DIR__ . '/../includes/API/VaccineController.php';
if (!file_exists($controller_path)) {
    echo "❌ VaccineController.php NOT found\n";
    exit(1);
}

$controller_content = file_get_contents($controller_path);

// Extract get_child_schedule method
preg_match('/public function get_child_schedule.*?(?=public function|private function|$)/s', $controller_content, $matches);
if (!isset($matches[0])) {
    echo "❌ get_child_schedule() method NOT found\n";
} else {
    $method = $matches[0];
    
    // Check for stats calculation
    $required_stats = ['total', 'done', 'upcoming', 'overdue', 'skipped', 'completion_percentage'];
    $stats_found = true;
    foreach ($required_stats as $stat) {
        if (strpos($method, "'{$stat}'") === false) {
            $stats_found = false;
            echo "❌ Stat '{$stat}' NOT found in response\n";
        }
    }
    if ($stats_found) {
        echo "✅ All required stats fields present\n";
    }
    
    // Check for metadata fields
    $metadata_fields = ['child_id', 'child_name', 'birth_date', 'is_premature', 'schedule_version'];
    $metadata_found = true;
    foreach ($metadata_fields as $field) {
        if (strpos($method, "'{$field}'") === false) {
            $metadata_found = false;
            echo "❌ Metadata field '{$field}' NOT found in response\n";
        }
    }
    if ($metadata_found) {
        echo "✅ All required metadata fields present\n";
    }
    
    // Check for stats array structure
    if (strpos($method, "\$stats = [") !== false || strpos($method, "\$stats=[") !== false) {
        echo "✅ Stats array initialization found\n";
    } else {
        echo "❌ Stats array initialization NOT found\n";
    }
    
    // Check for completion percentage calculation
    if (strpos($method, "completion_percentage") !== false && 
        (strpos($method, "round(") !== false || strpos($method, "/ \$stats['total']") !== false)) {
        echo "✅ Completion percentage calculation found\n";
    } else {
        echo "❌ Completion percentage calculation NOT found\n";
    }
}

echo "\n";

// ===== TEST 5: Code Quality Checks =====
echo "TEST 5: Code quality checks...\n";

// Check for proper error handling
$files = [
    'PrivateVaccineWizard.php' => $wizard_content,
    'VaccineRecordManager.php' => $manager_content,
    'VaccineController.php' => $controller_content
];

$error_handling_ok = true;
foreach ($files as $filename => $content) {
    // Check for WP_Error usage
    if (strpos($content, 'new \WP_Error') === false && strpos($content, 'new WP_Error') === false) {
        echo "⚠️  {$filename} may lack error handling\n";
        $error_handling_ok = false;
    }
}
if ($error_handling_ok) {
    echo "✅ All files have WP_Error usage\n";
}

// Check for is_wp_error checks
$is_wp_error_found = true;
foreach ($files as $filename => $content) {
    if (strpos($content, 'is_wp_error(') === false) {
        echo "⚠️  {$filename} may lack is_wp_error() checks\n";
        $is_wp_error_found = false;
    }
}
if ($is_wp_error_found) {
    echo "✅ All files have is_wp_error() checks\n";
}

echo "\n=== ALL STATIC TESTS COMPLETED ===\n";

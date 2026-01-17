<?php
/**
 * Static Test Script for Vaccine Tracker Fixes
 * 
 * Tests:
 * 1. Age validation shows warnings instead of errors for max_age_weeks_first_dose
 * 2. Initial status is set to 'upcoming' instead of 'scheduled'
 * 3. Dynamic status calculation in get_child_vaccines()
 * 4. Stats calculation includes all status types
 */

echo "=== VACCINE TRACKER FIXES TEST ===\n\n";

// ===== TEST 1: Check Age Validation Changes =====
echo "TEST 1: Checking age validation changes in PrivateVaccineWizard...\n";

$wizard_path = __DIR__ . '/../includes/Health/PrivateVaccineWizard.php';
if (!file_exists($wizard_path)) {
    echo "❌ PrivateVaccineWizard.php NOT found\n";
    exit(1);
}

$wizard_content = file_get_contents($wizard_path);

// Check that max_age_weeks_first_dose now adds to warnings array instead of errors
if (strpos($wizard_content, 'if (isset($brand[\'max_age_weeks_first_dose\']) && $age_weeks > $brand[\'max_age_weeks_first_dose\']) {') !== false) {
    // Found the check, now verify it adds to warnings
    preg_match('/if \(isset\(\$brand\[\'max_age_weeks_first_dose\'\]\).*?\n.*?\}/s', $wizard_content, $matches);
    if (isset($matches[0])) {
        $age_check_block = $matches[0];
        if (strpos($age_check_block, '$warnings[]') !== false) {
            echo "✅ max_age_weeks_first_dose validation adds to warnings array\n";
        } else {
            echo "❌ max_age_weeks_first_dose validation does NOT add to warnings array\n";
        }
        
        if (strpos($age_check_block, '$errors[]') === false) {
            echo "✅ max_age_weeks_first_dose validation does NOT add to errors array\n";
        } else {
            echo "❌ max_age_weeks_first_dose validation still adds to errors array\n";
        }
        
        if (strpos($age_check_block, 'Doktorunuza danışmanız önerilir') !== false) {
            echo "✅ Warning message includes doctor consultation suggestion\n";
        } else {
            echo "❌ Warning message does NOT include doctor consultation suggestion\n";
        }
    }
} else {
    echo "❌ max_age_weeks_first_dose check NOT found\n";
}

echo "\n";

// ===== TEST 2: Check Initial Status Changes =====
echo "TEST 2: Checking initial status changes in VaccineRecordManager...\n";

$manager_path = __DIR__ . '/../includes/Health/VaccineRecordManager.php';
if (!file_exists($manager_path)) {
    echo "❌ VaccineRecordManager.php NOT found\n";
    exit(1);
}

$manager_content = file_get_contents($manager_path);

// Check create_schedule_for_child method
preg_match('/public function create_schedule_for_child.*?(?=public function|$)/s', $manager_content, $matches);
if (isset($matches[0])) {
    $create_method = $matches[0];
    
    // Count occurrences of 'status' => 'upcoming' vs 'scheduled'
    $upcoming_count = substr_count($create_method, "'status' => 'upcoming'");
    $scheduled_count = substr_count($create_method, "'status' => 'scheduled'");
    
    if ($upcoming_count > 0 && $scheduled_count == 0) {
        echo "✅ create_schedule_for_child() sets initial status to 'upcoming'\n";
    } else if ($scheduled_count > 0) {
        echo "❌ create_schedule_for_child() still sets status to 'scheduled'\n";
    } else {
        echo "⚠️  Could not determine initial status in create_schedule_for_child()\n";
    }
}

// Check add_private_vaccine method
preg_match('/public function add_private_vaccine.*?(?=public function|$)/s', $manager_content, $matches);
if (isset($matches[0])) {
    $add_method = $matches[0];
    
    $upcoming_count = substr_count($add_method, "'status' => 'upcoming'");
    $scheduled_count = substr_count($add_method, "'status' => 'scheduled'");
    
    if ($upcoming_count > 0 && $scheduled_count == 0) {
        echo "✅ add_private_vaccine() sets initial status to 'upcoming'\n";
    } else if ($scheduled_count > 0) {
        echo "❌ add_private_vaccine() still sets status to 'scheduled'\n";
    } else {
        echo "⚠️  Could not determine initial status in add_private_vaccine()\n";
    }
}

echo "\n";

// ===== TEST 3: Check Dynamic Status Calculation =====
echo "TEST 3: Checking dynamic status calculation in get_child_vaccines()...\n";

preg_match('/public function get_child_vaccines.*?(?=public function|$)/s', $manager_content, $matches);
if (isset($matches[0])) {
    $get_method = $matches[0];
    
    // Check for dynamic status calculation
    if (strpos($get_method, 'Calculate dynamic status') !== false || 
        strpos($get_method, "!in_array(\$record['status'], ['done', 'skipped'])") !== false) {
        echo "✅ get_child_vaccines() includes dynamic status calculation\n";
    } else {
        echo "❌ get_child_vaccines() does NOT include dynamic status calculation\n";
    }
    
    // Check for overdue status
    if (strpos($get_method, "'overdue'") !== false && strpos($get_method, '$scheduled_date < $today') !== false) {
        echo "✅ get_child_vaccines() calculates 'overdue' status\n";
    } else {
        echo "❌ get_child_vaccines() does NOT calculate 'overdue' status\n";
    }
    
    // Check for upcoming status (within 7 days)
    if (strpos($get_method, "'upcoming'") !== false && strpos($get_method, 'days_until') !== false) {
        echo "✅ get_child_vaccines() calculates 'upcoming' status based on days\n";
    } else {
        echo "❌ get_child_vaccines() does NOT calculate 'upcoming' status based on days\n";
    }
}

echo "\n";

// ===== TEST 4: Check Stats Calculation Updates =====
echo "TEST 4: Checking stats calculation in VaccineController...\n";

$controller_path = __DIR__ . '/../includes/API/VaccineController.php';
if (!file_exists($controller_path)) {
    echo "❌ VaccineController.php NOT found\n";
    exit(1);
}

$controller_content = file_get_contents($controller_path);

// Check get_child_schedule method
preg_match('/public function get_child_schedule.*?(?=public function|$)/s', $controller_content, $matches);
if (isset($matches[0])) {
    $schedule_method = $matches[0];
    
    // Check stats array includes 'scheduled'
    if (strpos($schedule_method, "'scheduled' => 0") !== false) {
        echo "✅ Stats array includes 'scheduled' counter\n";
    } else {
        echo "❌ Stats array does NOT include 'scheduled' counter\n";
    }
    
    // Check switch statement has case for each status
    if (strpos($schedule_method, "case 'scheduled':") !== false) {
        echo "✅ Stats calculation includes 'scheduled' case\n";
    } else {
        echo "❌ Stats calculation does NOT include 'scheduled' case\n";
    }
    
    if (strpos($schedule_method, "case 'overdue':") !== false) {
        echo "✅ Stats calculation includes 'overdue' case\n";
    } else {
        echo "❌ Stats calculation does NOT include 'overdue' case\n";
    }
    
    if (strpos($schedule_method, "case 'upcoming':") !== false) {
        echo "✅ Stats calculation includes 'upcoming' case\n";
    } else {
        echo "❌ Stats calculation does NOT include 'upcoming' case\n";
    }
    
    // Check that it doesn't recalculate dates (since get_child_vaccines does it)
    $stats_section = substr($schedule_method, strpos($schedule_method, 'Calculate statistics'));
    if ($stats_section && strpos($stats_section, 'current_time') === false) {
        echo "✅ Stats calculation uses pre-calculated status (no date recalculation)\n";
    } else {
        echo "⚠️  Stats calculation may recalculate dates\n";
    }
}

echo "\n";

// ===== TEST 5: Check PrivateVaccineWizard Status =====
echo "TEST 5: Checking PrivateVaccineWizard create_vaccine_record()...\n";

preg_match('/private function create_vaccine_record.*?(?=public function|private function|$)/s', $wizard_content, $matches);
if (isset($matches[0])) {
    $create_record_method = $matches[0];
    
    $upcoming_count = substr_count($create_record_method, "'status' => 'upcoming'");
    $scheduled_count = substr_count($create_record_method, "'status' => 'scheduled'");
    
    if ($upcoming_count > 0 && $scheduled_count == 0) {
        echo "✅ create_vaccine_record() sets status to 'upcoming'\n";
    } else if ($scheduled_count > 0) {
        echo "❌ create_vaccine_record() sets status to 'scheduled'\n";
    } else {
        echo "⚠️  Could not determine status in create_vaccine_record()\n";
    }
}

echo "\n";

// ===== SUMMARY =====
echo "=== TEST SUMMARY ===\n";
echo "All static code checks completed.\n";
echo "Review the output above to ensure all changes are properly implemented.\n";

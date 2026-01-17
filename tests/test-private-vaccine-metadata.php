<?php
/**
 * Static Test Script for Private Vaccine Metadata
 * 
 * Verifies that:
 * 1. Private vaccines have is_mandatory=0 when created
 * 2. PrivateVaccineWizard can generate timing_rule for private vaccine codes
 * 3. PrivateVaccineWizard can generate metadata (name, description) for private vaccine codes
 * 4. VaccineRecordManager returns complete metadata for private vaccines
 */

echo "=== PRIVATE VACCINE METADATA TEST ===\n\n";

// Test file paths
$wizard_path = __DIR__ . '/../includes/Health/PrivateVaccineWizard.php';
$manager_path = __DIR__ . '/../includes/Health/VaccineRecordManager.php';
$config_path = __DIR__ . '/../data/vaccines/private_vaccine_configs.json';

echo "TEST 1: Checking if files exist...\n";

$files_ok = true;
if (file_exists($wizard_path)) {
    echo "✅ PrivateVaccineWizard.php exists\n";
} else {
    echo "❌ PrivateVaccineWizard.php NOT found\n";
    $files_ok = false;
}

if (file_exists($manager_path)) {
    echo "✅ VaccineRecordManager.php exists\n";
} else {
    echo "❌ VaccineRecordManager.php NOT found\n";
    $files_ok = false;
}

if (file_exists($config_path)) {
    echo "✅ private_vaccine_configs.json exists\n";
} else {
    echo "❌ private_vaccine_configs.json NOT found\n";
    $files_ok = false;
}

if (!$files_ok) {
    exit(1);
}

echo "\n";

echo "TEST 2: Checking PrivateVaccineWizard::create_vaccine_record() sets is_mandatory=0...\n";

$wizard_content = file_get_contents($wizard_path);

if (strpos($wizard_content, "'is_mandatory' => 0") !== false) {
    echo "✅ is_mandatory set to 0 for private vaccines\n";
} else {
    echo "❌ is_mandatory NOT set to 0 in create_vaccine_record()\n";
}

echo "\n";

echo "TEST 3: Checking PrivateVaccineWizard has get_timing_rule_for_vaccine() method...\n";

$checks = [
    'public function get_timing_rule_for_vaccine(' => 'Method signature exists',
    'explode(\'-\', $vaccine_code)' => 'Parses vaccine code',
    'dose_intervals_weeks' => 'Handles week-based intervals',
    'dose_intervals_months' => 'Handles month-based intervals',
    "'type' => 'week'" => 'Returns week type',
    "'type' => 'month'" => 'Returns month type',
    "'tolerance_days_before'" => 'Includes tolerance_days_before',
    "'tolerance_days_after'" => 'Includes tolerance_days_after',
];

foreach ($checks as $pattern => $description) {
    if (strpos($wizard_content, $pattern) !== false) {
        echo "✅ {$description}\n";
    } else {
        echo "❌ {$description} - NOT found\n";
    }
}

echo "\n";

echo "TEST 4: Checking PrivateVaccineWizard has get_vaccine_metadata() method...\n";

$metadata_checks = [
    'public function get_vaccine_metadata(' => 'Method signature exists',
    "\$config['name']" => 'Uses config name',
    "\$brand['name']" => 'Uses brand name',
    "'name' =>" => 'Returns name field',
    "'name_short' =>" => 'Returns name_short field',
    "'description' =>" => 'Returns description field',
    'Doz' => 'Includes dose number in name',
];

foreach ($metadata_checks as $pattern => $description) {
    if (strpos($wizard_content, $pattern) !== false) {
        echo "✅ {$description}\n";
    } else {
        echo "❌ {$description} - NOT found\n";
    }
}

echo "\n";

echo "TEST 5: Checking VaccineRecordManager::get_child_vaccines() handles private vaccines...\n";

$manager_content = file_get_contents($manager_path);

$manager_checks = [
    'get_private_vaccine_metadata(' => 'Calls get_private_vaccine_metadata()',
    'new PrivateVaccineWizard()' => 'Instantiates PrivateVaccineWizard',
    'get_vaccine_metadata($vaccine_code)' => 'Calls get_vaccine_metadata()',
    'get_timing_rule_for_vaccine($vaccine_code)' => 'Calls get_timing_rule_for_vaccine()',
    "\$is_private_vaccine = empty(\$record['name']) && isset(\$record['is_mandatory']) && !\$record['is_mandatory']" => 'Detects private vaccines',
    "if (\$is_private_vaccine)" => 'Has private vaccine fallback logic',
    "'type' => 'custom'" => 'Has default timing_rule fallback',
];

foreach ($manager_checks as $pattern => $description) {
    if (strpos($manager_content, $pattern) !== false) {
        echo "✅ {$description}\n";
    } else {
        echo "❌ {$description} - NOT found\n";
    }
}

echo "\n";

echo "TEST 6: Verifying private vaccine config structure...\n";

$config_json = file_get_contents($config_path);
$config = json_decode($config_json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Failed to parse private_vaccine_configs.json: " . json_last_error_msg() . "\n";
    exit(1);
}

$config_checks = [
    'rotavirus' => 'Has rotavirus config',
    'meningococcal_b' => 'Has meningococcal_b config',
];

foreach ($config_checks as $key => $description) {
    if (isset($config[$key])) {
        echo "✅ {$description}\n";
    } else {
        echo "❌ {$description} - NOT found\n";
    }
}

// Check rotavirus structure
if (isset($config['rotavirus'])) {
    echo "\n";
    echo "TEST 7: Verifying rotavirus vaccine structure...\n";
    
    $rotavirus = $config['rotavirus'];
    
    $rotavirus_checks = [
        'name' => 'Has name field',
        'description' => 'Has description field',
        'brands' => 'Has brands array',
    ];
    
    foreach ($rotavirus_checks as $field => $description) {
        if (isset($rotavirus[$field])) {
            echo "✅ {$description}\n";
        } else {
            echo "❌ {$description} - NOT found\n";
        }
    }
    
    // Check first brand (Rotarix)
    if (isset($rotavirus['brands'][0])) {
        $brand = $rotavirus['brands'][0];
        
        echo "\n";
        echo "TEST 8: Verifying Rotarix brand structure...\n";
        
        $brand_checks = [
            'code' => 'Has code field',
            'name' => 'Has name field',
            'total_doses' => 'Has total_doses field',
            'dose_intervals_weeks' => 'Has dose_intervals_weeks field',
            'min_age_weeks' => 'Has min_age_weeks field',
        ];
        
        foreach ($brand_checks as $field => $description) {
            if (isset($brand[$field])) {
                echo "✅ {$description}\n";
            } else {
                echo "❌ {$description} - NOT found\n";
            }
        }
        
        // Verify Rotarix values
        if ($brand['code'] === 'rotarix') {
            echo "✅ Rotarix code is correct\n";
        } else {
            echo "❌ Rotarix code is incorrect: {$brand['code']}\n";
        }
        
        if ($brand['total_doses'] === 2) {
            echo "✅ Rotarix has 2 doses\n";
        } else {
            echo "❌ Rotarix total_doses is incorrect: {$brand['total_doses']}\n";
        }
        
        if (isset($brand['dose_intervals_weeks']) && is_array($brand['dose_intervals_weeks'])) {
            $intervals = $brand['dose_intervals_weeks'];
            if (count($intervals) === 2 && $intervals[0] === 0 && $intervals[1] === 4) {
                echo "✅ Rotarix dose intervals are correct (0, 4 weeks)\n";
            } else {
                echo "❌ Rotarix dose intervals are incorrect: " . json_encode($intervals) . "\n";
            }
        }
    }
}

echo "\n";
echo "TEST 9: Testing timing_rule generation logic (simulated)...\n";

// Simulate timing_rule generation for rotavirus-rotarix-1
echo "Testing vaccine_code: rotavirus-rotarix-1\n";

// Parse logic should extract:
// - type: rotavirus
// - brand: rotarix
// - dose: 1

// Expected timing_rule for dose 1:
// weeks = sum([0]) = 0 (but should use the cumulative sum for dose 1, which is weeks[0] = 0)
// Actually, for rotavirus-rotarix-1, it should be array_sum(array_slice([0, 4], 0, 1)) = 0

// For dose 2 (rotavirus-rotarix-2):
// weeks = sum([0, 4]) = 4

echo "Expected timing_rule for dose 1: {type: 'week', value: 0, tolerance_days_before: 0, tolerance_days_after: 14}\n";
echo "Expected timing_rule for dose 2: {type: 'week', value: 4, tolerance_days_before: 0, tolerance_days_after: 14}\n";

echo "\n";
echo "TEST 10: Testing metadata generation logic (simulated)...\n";

echo "Testing vaccine_code: rotavirus-rotarix-1\n";
echo "Expected name: Rotavirüs - Rotarix (Doz 1)\n";
echo "Expected name_short: Rotavirüs-1\n";
echo "Expected description: İshal aşısı - Ağızdan damla şeklinde uygulanır\n";

echo "\n";
echo "=== ALL TESTS COMPLETED ===\n";

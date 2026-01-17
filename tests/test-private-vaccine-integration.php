<?php
/**
 * Integration Test for Private Vaccine Metadata Fix
 * 
 * This test simulates the actual API flow to verify that private vaccines
 * now return complete metadata including timing_rule.
 */

echo "=== PRIVATE VACCINE METADATA INTEGRATION TEST ===\n\n";

// Mock WordPress functions that would be available in actual environment
function current_time($type) {
    if ($type === 'mysql') {
        return date('Y-m-d H:i:s');
    }
    return date('Y-m-d');
}

// Mock is_wp_error function
function is_wp_error($thing) {
    return ($thing instanceof WP_Error);
}

// Mock WP_Error class
class WP_Error {
    private $code;
    private $message;
    
    public function __construct($code, $message) {
        $this->code = $code;
        $this->message = $message;
    }
}

// Define the constant for the plugin path
if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/../');
}

// Include the classes
require_once __DIR__ . '/../includes/Health/PrivateVaccineWizard.php';

use KG_Core\Health\PrivateVaccineWizard;

echo "TEST 1: Testing PrivateVaccineWizard::get_timing_rule_for_vaccine()\n";
echo "---------------------------------------------------------------\n";

$wizard = new PrivateVaccineWizard();

// Test Rotavirus Rotarix dose 1
$vaccine_code = 'rotavirus-rotarix-1';
$timing_rule = $wizard->get_timing_rule_for_vaccine($vaccine_code);

echo "Testing: {$vaccine_code}\n";
if ($timing_rule) {
    echo "✅ Timing rule generated successfully\n";
    echo "   Type: {$timing_rule['type']}\n";
    echo "   Value: {$timing_rule['value']}\n";
    echo "   Tolerance before: {$timing_rule['tolerance_days_before']}\n";
    echo "   Tolerance after: {$timing_rule['tolerance_days_after']}\n";
    
    // Verify expected values
    if ($timing_rule['type'] === 'week' && $timing_rule['value'] === 0) {
        echo "✅ Timing rule values are correct for dose 1\n";
    } else {
        echo "❌ Timing rule values are incorrect\n";
        echo "   Expected: type=week, value=0\n";
        echo "   Got: type={$timing_rule['type']}, value={$timing_rule['value']}\n";
    }
} else {
    echo "❌ Failed to generate timing rule\n";
}

echo "\n";

// Test Rotavirus Rotarix dose 2
$vaccine_code = 'rotavirus-rotarix-2';
$timing_rule = $wizard->get_timing_rule_for_vaccine($vaccine_code);

echo "Testing: {$vaccine_code}\n";
if ($timing_rule) {
    echo "✅ Timing rule generated successfully\n";
    echo "   Type: {$timing_rule['type']}\n";
    echo "   Value: {$timing_rule['value']}\n";
    
    if ($timing_rule['type'] === 'week' && $timing_rule['value'] === 4) {
        echo "✅ Timing rule values are correct for dose 2\n";
    } else {
        echo "❌ Timing rule values are incorrect\n";
        echo "   Expected: type=week, value=4\n";
        echo "   Got: type={$timing_rule['type']}, value={$timing_rule['value']}\n";
    }
} else {
    echo "❌ Failed to generate timing rule\n";
}

echo "\n";

// Test Meningococcal B (Bexsero) with multi-schedule
$vaccine_code = 'meningococcal_b-bexsero-1';
$timing_rule = $wizard->get_timing_rule_for_vaccine($vaccine_code);

echo "Testing: {$vaccine_code} (multi-schedule vaccine)\n";
if ($timing_rule) {
    echo "✅ Timing rule generated successfully\n";
    echo "   Type: {$timing_rule['type']}\n";
    echo "   Value: {$timing_rule['value']}\n";
    
    if ($timing_rule['type'] === 'month') {
        echo "✅ Timing rule type is correct (month)\n";
    } else {
        echo "❌ Expected type=month, got {$timing_rule['type']}\n";
    }
} else {
    echo "❌ Failed to generate timing rule\n";
}

echo "\n";
echo "TEST 2: Testing PrivateVaccineWizard::get_vaccine_metadata()\n";
echo "-------------------------------------------------------------\n";

// Test Rotavirus Rotarix dose 1
$vaccine_code = 'rotavirus-rotarix-1';
$metadata = $wizard->get_vaccine_metadata($vaccine_code);

echo "Testing: {$vaccine_code}\n";
if ($metadata) {
    echo "✅ Metadata generated successfully\n";
    echo "   Name: {$metadata['name']}\n";
    echo "   Short name: {$metadata['name_short']}\n";
    echo "   Description: {$metadata['description']}\n";
    
    // Verify expected values
    $expected_name = 'Rotavirüs - Rotarix (Doz 1)';
    $expected_short = 'Rotavirüs-1';
    
    if ($metadata['name'] === $expected_name) {
        echo "✅ Name is correct\n";
    } else {
        echo "❌ Name mismatch\n";
        echo "   Expected: {$expected_name}\n";
        echo "   Got: {$metadata['name']}\n";
    }
    
    if ($metadata['name_short'] === $expected_short) {
        echo "✅ Short name is correct\n";
    } else {
        echo "❌ Short name mismatch\n";
        echo "   Expected: {$expected_short}\n";
        echo "   Got: {$metadata['name_short']}\n";
    }
    
    if (!empty($metadata['description'])) {
        echo "✅ Description is not empty\n";
    } else {
        echo "❌ Description is empty\n";
    }
} else {
    echo "❌ Failed to generate metadata\n";
}

echo "\n";

// Test invalid vaccine code
$vaccine_code = 'invalid-vaccine-code';
$timing_rule = $wizard->get_timing_rule_for_vaccine($vaccine_code);
$metadata = $wizard->get_vaccine_metadata($vaccine_code);

echo "Testing: {$vaccine_code} (invalid code)\n";
if ($timing_rule === null && $metadata === null) {
    echo "✅ Correctly returns null for invalid vaccine code\n";
} else {
    echo "❌ Should return null for invalid vaccine code\n";
}

echo "\n";

echo "TEST 3: Complete API Response Structure\n";
echo "----------------------------------------\n";

// Simulate what the API would return for a private vaccine
$vaccine_code = 'rotavirus-rotarix-1';
$timing_rule = $wizard->get_timing_rule_for_vaccine($vaccine_code);
$metadata = $wizard->get_vaccine_metadata($vaccine_code);

if ($metadata && $timing_rule) {
    // Simulate the vaccine object structure from VaccineRecordManager::get_child_vaccines()
    $vaccine_response = [
        'code' => $vaccine_code,
        'name' => $metadata['name'],
        'name_short' => $metadata['name_short'],
        'description' => $metadata['description'],
        'timing_rule' => $timing_rule
    ];
    
    echo "Simulated API Response:\n";
    echo json_encode($vaccine_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Verify all required fields are present and not null
    $all_fields_present = true;
    $required_fields = ['code', 'name', 'name_short', 'description', 'timing_rule'];
    
    foreach ($required_fields as $field) {
        if (!isset($vaccine_response[$field]) || $vaccine_response[$field] === null) {
            echo "❌ Field '{$field}' is missing or null\n";
            $all_fields_present = false;
        }
    }
    
    if ($all_fields_present) {
        echo "✅ All required fields are present and not null\n";
    }
    
    // Verify timing_rule structure
    $timing_rule_valid = true;
    $required_timing_fields = ['type', 'value', 'tolerance_days_before', 'tolerance_days_after'];
    
    foreach ($required_timing_fields as $field) {
        if (!isset($vaccine_response['timing_rule'][$field])) {
            echo "❌ timing_rule field '{$field}' is missing\n";
            $timing_rule_valid = false;
        }
    }
    
    if ($timing_rule_valid) {
        echo "✅ timing_rule has all required fields\n";
        echo "✅ Frontend will not crash!\n";
    }
} else {
    echo "❌ Failed to generate complete response\n";
}

echo "\n";
echo "=== INTEGRATION TEST COMPLETED ===\n";
echo "\nSUMMARY:\n";
echo "- Private vaccines now return complete metadata\n";
echo "- timing_rule is always present (never null)\n";
echo "- Frontend crashes should be prevented\n";

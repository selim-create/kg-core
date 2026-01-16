<?php
/**
 * Test for Bath Planner API Fixes
 * 
 * This test verifies:
 * 1. get_bath_planner_config returns correct structure with skin_types, seasons, frequency_options
 * 2. get_sponsor_data returns correct format (flat structure, direct URLs)
 * 3. generate_bath_routine accepts new parameters and returns new fields
 * 4. All sponsor_data keys changed to sponsor
 */

echo "=== Bath Planner API Fixes Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check SponsoredToolController.php has required changes
echo "1. SponsoredToolController - Code Structure\n";
$controllerFile = $baseDir . '/includes/API/SponsoredToolController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for new config arrays
    $requiredConfigArrays = [
        'skin_types',
        'seasons',
        'frequency_options'
    ];
    
    foreach ($requiredConfigArrays as $arrayName) {
        if (strpos($content, "'" . $arrayName . "' =>") !== false) {
            echo "   ✓ $arrayName array added to config\n";
            $passed++;
        } else {
            echo "   ✗ $arrayName array not found in config\n";
            $failed++;
        }
    }
    
    // Check for skin types
    $skinTypes = ['normal', 'dry', 'sensitive', 'oily'];
    foreach ($skinTypes as $skinType) {
        if (strpos($content, "'id' => '" . $skinType . "'") !== false) {
            echo "   ✓ skin_type '$skinType' defined\n";
            $passed++;
        } else {
            echo "   ✗ skin_type '$skinType' not found\n";
            $failed++;
        }
    }
    
    // Check for seasons
    $seasons = ['spring', 'summer', 'autumn', 'winter'];
    foreach ($seasons as $season) {
        if (strpos($content, "'id' => '" . $season . "'") !== false) {
            echo "   ✓ season '$season' defined\n";
            $passed++;
        } else {
            echo "   ✗ season '$season' not found\n";
            $failed++;
        }
    }
    
    // Check for frequency options
    $frequencies = ['2-3', '3-4', '4-5', 'daily'];
    foreach ($frequencies as $freq) {
        if (strpos($content, "'id' => '" . $freq . "'") !== false) {
            echo "   ✓ frequency_option '$freq' defined\n";
            $passed++;
        } else {
            echo "   ✗ frequency_option '$freq' not found\n";
            $failed++;
        }
    }
    
} else {
    echo "   ✗ SponsoredToolController.php file not found\n";
    $failed += 12;
}

// Test 2: Check sponsor data format changes
echo "\n2. Sponsor Data Format - Flat Structure\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for direct URL assignment (not nested object)
    if (preg_match("/'sponsor_logo'\s*=>\s*\\\$sponsor_logo_id\s*\?\s*wp_get_attachment_url/", $content)) {
        echo "   ✓ sponsor_logo returns direct URL\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_logo should return direct URL\n";
        $failed++;
    }
    
    if (preg_match("/'sponsor_light_logo'\s*=>\s*\\\$sponsor_light_logo_id\s*\?\s*wp_get_attachment_url/", $content)) {
        echo "   ✓ sponsor_light_logo returns direct URL\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_light_logo should return direct URL\n";
        $failed++;
    }
    
    // Check for flat CTA structure
    if (strpos($content, "'sponsor_cta_text' =>") !== false && 
        strpos($content, "'sponsor_cta_url' =>") !== false) {
        echo "   ✓ sponsor_cta is flattened to sponsor_cta_text and sponsor_cta_url\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_cta should be flattened\n";
        $failed++;
    }
    
    // Check for sponsor_tagline
    if (strpos($content, "'sponsor_tagline' =>") !== false) {
        echo "   ✓ sponsor_tagline field exists\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_tagline field not found\n";
        $failed++;
    }
    
    // Check for GAM URLs with null fallback
    if (strpos($content, "'gam_impression_url' =>") !== false) {
        echo "   ✓ gam_impression_url field exists\n";
        $passed++;
    } else {
        echo "   ✗ gam_impression_url field not found\n";
        $failed++;
    }
    
    if (strpos($content, "'gam_click_url' =>") !== false) {
        echo "   ✓ gam_click_url field exists\n";
        $passed++;
    } else {
        echo "   ✗ gam_click_url field not found\n";
        $failed++;
    }
    
    // Check that sponsor_url is removed
    if (strpos($content, "'sponsor_url' =>") === false) {
        echo "   ✓ sponsor_url field removed (not needed)\n";
        $passed++;
    } else {
        echo "   ✗ sponsor_url field should be removed\n";
        $failed++;
    }
    
} else {
    $failed += 7;
}

// Test 3: Check generate_bath_routine accepts new parameters
echo "\n3. Generate Bath Routine - New Parameters\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for baby_age_months parameter
    if (strpos($content, "get_param( 'baby_age_months' )") !== false) {
        echo "   ✓ baby_age_months parameter accepted\n";
        $passed++;
    } else {
        echo "   ✗ baby_age_months parameter not found\n";
        $failed++;
    }
    
    // Check for season parameter
    if (strpos($content, "get_param( 'season' )") !== false) {
        echo "   ✓ season parameter accepted\n";
        $passed++;
    } else {
        echo "   ✗ season parameter not found\n";
        $failed++;
    }
    
    // Check for has_eczema parameter
    if (strpos($content, "get_param( 'has_eczema' )") !== false) {
        echo "   ✓ has_eczema parameter accepted\n";
        $passed++;
    } else {
        echo "   ✗ has_eczema parameter not found\n";
        $failed++;
    }
    
} else {
    $failed += 3;
}

// Test 4: Check generate_bath_routine returns new fields
echo "\n4. Generate Bath Routine - New Response Fields\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for weekly_schedule in response
    if (strpos($content, "'weekly_schedule' =>") !== false) {
        echo "   ✓ weekly_schedule field in response\n";
        $passed++;
    } else {
        echo "   ✗ weekly_schedule field not found in response\n";
        $failed++;
    }
    
    // Check for warnings in response
    if (strpos($content, "'warnings' =>") !== false) {
        echo "   ✓ warnings field in response\n";
        $passed++;
    } else {
        echo "   ✗ warnings field not found in response\n";
        $failed++;
    }
    
    // Check for product_recommendations in response
    if (strpos($content, "'product_recommendations' =>") !== false) {
        echo "   ✓ product_recommendations field in response\n";
        $passed++;
    } else {
        echo "   ✗ product_recommendations field not found in response\n";
        $failed++;
    }
    
} else {
    $failed += 3;
}

// Test 5: Check helper methods exist
echo "\n5. Helper Methods - Implementation\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    $helperMethods = [
        'generate_weekly_schedule',
        'get_bath_days_for_age',
        'get_day_note',
        'get_warnings',
        'get_product_recommendations_list'
    ];
    
    foreach ($helperMethods as $method) {
        if (strpos($content, "private function " . $method) !== false) {
            echo "   ✓ $method() method exists\n";
            $passed++;
        } else {
            echo "   ✗ $method() method not found\n";
            $failed++;
        }
    }
    
} else {
    $failed += 5;
}

// Test 6: Check sponsor_data changed to sponsor in all responses
echo "\n6. Response Keys - sponsor_data → sponsor\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Count occurrences in config endpoint
    if (preg_match("/'sponsor'\s*=>\s*\\\$sponsor_data,/", $content)) {
        echo "   ✓ Config endpoint uses 'sponsor' key\n";
        $passed++;
    } else {
        echo "   ✗ Config endpoint should use 'sponsor' key\n";
        $failed++;
    }
    
    // Check that old sponsor_data key in response is not present (except variable name)
    // We should have 'sponsor' => $sponsor_data, not 'sponsor_data' => $sponsor_data
    $sponsorDataInResponse = preg_match_all("/'sponsor_data'\s*=>/", $content);
    if ($sponsorDataInResponse === 0) {
        echo "   ✓ All 'sponsor_data' keys in responses changed to 'sponsor'\n";
        $passed++;
    } else {
        echo "   ✗ Found $sponsorDataInResponse instances of 'sponsor_data' key in responses\n";
        $failed++;
    }
    
} else {
    $failed += 2;
}

// Test 7: Syntax check
echo "\n7. PHP Syntax Check\n";
if (file_exists($controllerFile)) {
    exec("php -l " . escapeshellarg($controllerFile) . " 2>&1", $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✓ SponsoredToolController.php has no syntax errors\n";
        $passed++;
    } else {
        echo "   ✗ SponsoredToolController.php has syntax errors:\n";
        echo "     " . implode("\n     ", $output) . "\n";
        $failed++;
    }
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed > 0) {
    echo "\nResult: FAILED ❌\n";
    echo "\nPlease review the failures above.\n";
    exit(1);
} else {
    echo "\nResult: PASSED ✅\n";
    echo "\nAll Bath Planner API fixes have been successfully implemented!\n";
    exit(0);
}

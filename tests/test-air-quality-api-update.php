<?php
/**
 * Test for Air Quality API Update
 * 
 * This test verifies:
 * 1. analyze_air_quality method accepts new frontend parameters
 * 2. Returns correct response structure with risk_level, risk_score, etc.
 * 3. Backward compatibility with old AQI parameter
 * 4. All new helper methods exist and are callable
 */

echo "=== Air Quality API Update Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check SponsoredToolController.php has updated analyze_air_quality method
echo "1. Updated analyze_air_quality Method\n";
$controllerFile = $baseDir . '/includes/API/SponsoredToolController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for new parameters
    $newParams = [
        'child_age_months',
        'home_type',
        'heating_type',
        'has_pets',
        'has_smoker',
        'season',
        'ventilation_frequency',
        'cooking_frequency',
    ];
    
    foreach ($newParams as $param) {
        if (strpos($content, "get_param( '$param'") !== false) {
            echo "   ✓ Parameter '$param' is handled\n";
            $passed++;
        } else {
            echo "   ✗ Parameter '$param' not found\n";
            $failed++;
        }
    }
    
    // Check for response fields
    $responseFields = [
        "'risk_level'",
        "'risk_score'",
        "'risk_factors'",
        "'recommendations'",
        "'seasonal_alerts'",
        "'indoor_tips'",
    ];
    
    foreach ($responseFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ Response field $field exists\n";
            $passed++;
        } else {
            echo "   ✗ Response field $field not found\n";
            $failed++;
        }
    }
    
    // Check backward compatibility with AQI
    if (strpos($content, "'external_aqi'") !== false && strpos($content, "get_param( 'aqi'") !== false) {
        echo "   ✓ Backward compatibility with AQI parameter maintained\n";
        $passed++;
    } else {
        echo "   ✗ Backward compatibility with AQI parameter not maintained\n";
        $failed++;
    }
} else {
    echo "   ✗ SponsoredToolController.php file not found\n";
    $failed += 16;
}

// Test 2: Check for new helper methods
echo "\n2. New Helper Methods\n";
$requiredMethods = [
    'get_current_season',
    'calculate_indoor_air_risk',
    'get_indoor_risk_factors',
    'get_child_air_quality_recommendations',
    'get_air_quality_seasonal_alerts',
];

if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method(") !== false) {
            echo "   ✓ Method '$method' exists\n";
            $passed++;
        } else {
            echo "   ✗ Method '$method' not found\n";
            $failed++;
        }
    }
} else {
    $failed += count($requiredMethods);
}

// Test 3: Check calculate_indoor_air_risk implementation
echo "\n3. Calculate Indoor Air Risk Implementation\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for risk calculation logic
    $riskElements = [
        "'stove'",
        "'natural_gas'",
        "'central'",
        "'apartment'",
        "'ground_floor'",
        "'winter'",
        "'summer'",
        "'spring'",
        "'autumn'",
    ];
    
    $foundElements = 0;
    foreach ($riskElements as $element) {
        if (strpos($content, $element) !== false) {
            $foundElements++;
        }
    }
    
    if ($foundElements >= 7) {
        echo "   ✓ Risk calculation includes home_type, heating_type, and season parameters\n";
        $passed++;
    } else {
        echo "   ✗ Risk calculation missing key parameters (found $foundElements/9)\n";
        $failed++;
    }
    
    // Check for risk level determination
    if (strpos($content, "'low'") !== false && 
        strpos($content, "'medium'") !== false && 
        strpos($content, "'high'") !== false) {
        echo "   ✓ Risk levels (low, medium, high) are defined\n";
        $passed++;
    } else {
        echo "   ✗ Risk levels not properly defined\n";
        $failed++;
    }
} else {
    $failed += 2;
}

// Test 4: Check get_indoor_risk_factors implementation
echo "\n4. Indoor Risk Factors Implementation\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    $riskFactors = [
        'Sigara Dumanı',
        'Soba Isıtma',
        'Doğalgaz Kombi',
        'Evcil Hayvan',
        'Zemin Kat',
    ];
    
    $foundFactors = 0;
    foreach ($riskFactors as $factor) {
        if (strpos($content, $factor) !== false) {
            $foundFactors++;
        }
    }
    
    if ($foundFactors >= 4) {
        echo "   ✓ Risk factors include smoking, heating, pets, and home type\n";
        $passed++;
    } else {
        echo "   ✗ Risk factors incomplete (found $foundFactors/5)\n";
        $failed++;
    }
    
    // Check for factor structure (factor, impact, severity, category)
    if (strpos($content, "'factor'") !== false && 
        strpos($content, "'impact'") !== false && 
        strpos($content, "'severity'") !== false &&
        strpos($content, "'category'") !== false) {
        echo "   ✓ Risk factor structure includes factor, impact, severity, category\n";
        $passed++;
    } else {
        echo "   ✗ Risk factor structure incomplete\n";
        $failed++;
    }
} else {
    $failed += 2;
}

// Test 5: Check get_child_air_quality_recommendations implementation
echo "\n5. Child Air Quality Recommendations Implementation\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for age-specific recommendations
    if (strpos($content, 'child_age_months < 6') !== false ||
        strpos($content, 'child_age_months < 12') !== false ||
        strpos($content, 'child_age_months < 36') !== false) {
        echo "   ✓ Age-specific recommendations exist\n";
        $passed++;
    } else {
        echo "   ✗ Age-specific recommendations not found\n";
        $failed++;
    }
    
    // Check for smoker-specific warnings
    if (strpos($content, 'has_smoker') !== false && 
        strpos($content, 'sigara') !== false) {
        echo "   ✓ Smoker-specific warnings exist\n";
        $passed++;
    } else {
        echo "   ✗ Smoker-specific warnings not found\n";
        $failed++;
    }
    
    // Check for respiratory issue handling
    if (strpos($content, 'has_respiratory') !== false) {
        echo "   ✓ Respiratory issue recommendations exist\n";
        $passed++;
    } else {
        echo "   ✗ Respiratory issue recommendations not found\n";
        $failed++;
    }
} else {
    $failed += 3;
}

// Test 6: Check get_air_quality_seasonal_alerts implementation
echo "\n6. Seasonal Alerts Implementation\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    $seasons = ['winter', 'spring', 'summer', 'autumn'];
    $foundSeasons = 0;
    
    foreach ($seasons as $season) {
        if (preg_match("/case '$season':/", $content)) {
            $foundSeasons++;
        }
    }
    
    if ($foundSeasons >= 4) {
        echo "   ✓ All four seasons have specific alerts\n";
        $passed++;
    } else {
        echo "   ✗ Not all seasons have alerts (found $foundSeasons/4)\n";
        $failed++;
    }
    
    // Check for heating-specific winter alerts
    if (strpos($content, 'karbonmonoksit') !== false) {
        echo "   ✓ Heating-specific alerts exist for winter\n";
        $passed++;
    } else {
        echo "   ✗ Heating-specific alerts not found\n";
        $failed++;
    }
} else {
    $failed += 2;
}

// Test 7: Check get_current_season implementation
echo "\n7. Current Season Detection\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for date-based season detection
    if (preg_match("/date\s*\(\s*'n'\s*\)/", $content)) {
        echo "   ✓ Season detection uses current month\n";
        $passed++;
    } else {
        echo "   ✗ Season detection not using current month\n";
        $failed++;
    }
} else {
    $failed += 1;
}

// Test 8: Check updated get_indoor_air_tips
echo "\n8. Updated Indoor Air Tips\n";
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Look for the improved tips
    $tipsCount = substr_count($content, 'Günde en az 2-3 kez') + 
                 substr_count($content, 'HEPA filtreli') + 
                 substr_count($content, 'Kimyasal temizlik ürünleri');
    
    if ($tipsCount >= 2) {
        echo "   ✓ Indoor air tips expanded with more comprehensive advice\n";
        $passed++;
    } else {
        echo "   ✗ Indoor air tips not sufficiently expanded\n";
        $failed++;
    }
} else {
    $failed += 1;
}

// Test 9: PHP Syntax Check
echo "\n9. PHP Syntax Check\n";
if (file_exists($controllerFile)) {
    exec("php -l " . escapeshellarg($controllerFile) . " 2>&1", $output, $returnCode);
    if ($returnCode === 0) {
        echo "   ✓ SponsoredToolController.php has no syntax errors\n";
        $passed++;
    } else {
        echo "   ✗ SponsoredToolController.php has syntax errors\n";
        echo "   " . implode("\n   ", $output) . "\n";
        $failed++;
    }
} else {
    $failed += 1;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed > 0) {
    echo "\nResult: FAILED ❌\n";
    exit(1);
} else {
    echo "\nResult: PASSED ✅\n";
    exit(0);
}

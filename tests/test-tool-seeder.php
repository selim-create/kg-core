<?php
/**
 * Test for Tool Seeder Implementation
 * 
 * This test verifies:
 * 1. ToolSeeder class exists and is properly integrated
 * 2. All 13 tools are defined in seeder data
 * 3. Tool.php has all required tool_type choices
 * 4. kg-core.php includes and initializes ToolSeeder
 * 5. Activation hook is registered
 */

echo "=== Tool Seeder Implementation Test ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check ToolSeeder.php exists
echo "1. ToolSeeder Class - File Existence\n";
$seederFile = $baseDir . '/includes/Admin/ToolSeeder.php';
if (file_exists($seederFile)) {
    echo "   ✓ ToolSeeder.php exists\n";
    $passed++;
    
    $content = file_get_contents($seederFile);
    
    // Check class declaration
    if (strpos($content, 'class ToolSeeder') !== false) {
        echo "   ✓ ToolSeeder class declared\n";
        $passed++;
    } else {
        echo "   ✗ ToolSeeder class not found\n";
        $failed++;
    }
    
    // Check namespace
    if (strpos($content, 'namespace KG_Core\Admin') !== false) {
        echo "   ✓ Correct namespace\n";
        $passed++;
    } else {
        echo "   ✗ Incorrect namespace\n";
        $failed++;
    }
} else {
    echo "   ✗ ToolSeeder.php file not found\n";
    $failed += 3;
}

// Test 2: Check all 13 tools are defined
echo "\n2. Tool Data - All 13 Tools Defined\n";
$requiredTools = [
    'bath-planner' => 'Banyo Rutini Planlayıcı',
    'hygiene-calculator' => 'Günlük Hijyen İhtiyacı Hesaplayıcı',
    'diaper-calculator' => 'Akıllı Bez Hesaplayıcı',
    'air-quality' => 'Hava Kalitesi Rehberi',
    'stain-encyclopedia' => 'Leke Ansiklopedisi',
    'blw-testi' => 'BLW Hazırlık Testi',
    'persentil' => 'Persentil Hesaplayıcı',
    'su-ihtiyaci' => 'Su İhtiyacı Hesaplayıcı',
    'ek-gida-rehberi' => 'Ek Gıda Rehberi',
    'ek-gidaya-baslama' => 'Ek Gıdaya Başlama Kontrolü',
    'bu-gida-verilir-mi' => 'Bu Gıda Verilir mi?',
    'alerjen-planlayici' => 'Alerjen Deneme Planlayıcı',
    'besin-takvimi' => 'Besin Deneme Takvimi',
];

if (file_exists($seederFile)) {
    $content = file_get_contents($seederFile);
    
    foreach ($requiredTools as $slug => $title) {
        if (strpos($content, "'slug' => '$slug'") !== false && strpos($content, $title) !== false) {
            echo "   ✓ $slug - $title found\n";
            $passed++;
        } else {
            echo "   ✗ $slug - $title not found\n";
            $failed++;
        }
    }
} else {
    $failed += count($requiredTools);
}

// Test 3: Check required meta fields in seeder
echo "\n3. Tool Seeder - Meta Fields\n";
$requiredMetaFields = [
    '_kg_tool_type',
    '_kg_tool_icon',
    '_kg_is_active',
    '_kg_requires_auth',
    '_kg_tool_is_sponsored',
];

if (file_exists($seederFile)) {
    $content = file_get_contents($seederFile);
    
    foreach ($requiredMetaFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ $field meta field handled\n";
            $passed++;
        } else {
            echo "   ✗ $field meta field not found\n";
            $failed++;
        }
    }
} else {
    $failed += count($requiredMetaFields);
}

// Test 4: Check Tool.php has all tool types
echo "\n4. Tool Post Type - Tool Type Choices\n";
$toolFile = $baseDir . '/includes/PostTypes/Tool.php';
if (file_exists($toolFile)) {
    $content = file_get_contents($toolFile);
    
    $requiredToolTypes = [
        'bath_planner',
        'hygiene_calculator',
        'diaper_calculator',
        'air_quality_guide',
        'stain_encyclopedia',
        'blw_test',
        'percentile',
        'water_calculator',
        'food_guide',
        'solid_food_readiness',
        'food_checker',
        'allergen_planner',
        'food_trial_calendar',
    ];
    
    foreach ($requiredToolTypes as $toolType) {
        if (strpos($content, "'" . $toolType . "'") !== false) {
            echo "   ✓ $toolType added to choices\n";
            $passed++;
        } else {
            echo "   ✗ $toolType not found in choices\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ Tool.php file not found\n";
    $failed += 13;
}

// Test 5: Check kg-core.php integration
echo "\n5. kg-core.php Integration\n";
$coreFile = $baseDir . '/kg-core.php';
if (file_exists($coreFile)) {
    $content = file_get_contents($coreFile);
    
    // Check ToolSeeder is included
    if (strpos($content, "includes/Admin/ToolSeeder.php") !== false) {
        echo "   ✓ ToolSeeder.php included\n";
        $passed++;
    } else {
        echo "   ✗ ToolSeeder.php not included\n";
        $failed++;
    }
    
    // Check ToolSeeder is initialized
    if (strpos($content, "new \KG_Core\Admin\ToolSeeder()") !== false) {
        echo "   ✓ ToolSeeder initialized\n";
        $passed++;
    } else {
        echo "   ✗ ToolSeeder not initialized\n";
        $failed++;
    }
    
    // Check activation hook
    if (strpos($content, 'register_activation_hook') !== false && 
        strpos($content, 'ToolSeeder::seed_on_activation') !== false) {
        echo "   ✓ Activation hook registered\n";
        $passed++;
    } else {
        echo "   ✗ Activation hook not registered\n";
        $failed++;
    }
} else {
    echo "   ✗ kg-core.php file not found\n";
    $failed += 3;
}

// Test 6: Check ToolSeeder methods
echo "\n6. ToolSeeder Methods\n";
if (file_exists($seederFile)) {
    $content = file_get_contents($seederFile);
    
    $requiredMethods = [
        'add_seeder_page',
        'render_seeder_page',
        'ajax_seed_tool',
        'seed_tool',
        'seed_on_activation',
        'get_tools_status',
    ];
    
    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ $method method exists\n";
            $passed++;
        } else {
            echo "   ✗ $method method not found\n";
            $failed++;
        }
    }
} else {
    $failed += 6;
}

// Test 7: Check admin menu integration
echo "\n7. Admin Menu Integration\n";
if (file_exists($seederFile)) {
    $content = file_get_contents($seederFile);
    
    if (strpos($content, "add_submenu_page") !== false) {
        echo "   ✓ Submenu page registered\n";
        $passed++;
    } else {
        echo "   ✗ Submenu page not registered\n";
        $failed++;
    }
    
    if (strpos($content, "edit.php?post_type=tool") !== false) {
        echo "   ✓ Correct parent menu (tool post type)\n";
        $passed++;
    } else {
        echo "   ✗ Incorrect parent menu\n";
        $failed++;
    }
    
    if (strpos($content, "kg-tool-seeder") !== false) {
        echo "   ✓ Menu slug defined\n";
        $passed++;
    } else {
        echo "   ✗ Menu slug not found\n";
        $failed++;
    }
} else {
    $failed += 3;
}

// Test 8: Check AJAX handler security
echo "\n8. Security Implementation\n";
if (file_exists($seederFile)) {
    $content = file_get_contents($seederFile);
    
    if (strpos($content, 'wp_verify_nonce') !== false) {
        echo "   ✓ Nonce verification implemented\n";
        $passed++;
    } else {
        echo "   ✗ Nonce verification missing\n";
        $failed++;
    }
    
    if (strpos($content, 'current_user_can') !== false && strpos($content, 'manage_options') !== false) {
        echo "   ✓ Capability check implemented\n";
        $passed++;
    } else {
        echo "   ✗ Capability check missing\n";
        $failed++;
    }
    
    if (strpos($content, 'sanitize_text_field') !== false) {
        echo "   ✓ Input sanitization implemented\n";
        $passed++;
    } else {
        echo "   ✗ Input sanitization missing\n";
        $failed++;
    }
} else {
    $failed += 3;
}

// Test 9: Check duplicate prevention
echo "\n9. Duplicate Prevention\n";
if (file_exists($seederFile)) {
    $content = file_get_contents($seederFile);
    
    if (strpos($content, 'get_page_by_path') !== false) {
        echo "   ✓ Duplicate check using get_page_by_path\n";
        $passed++;
    } else {
        echo "   ✗ Duplicate check not found\n";
        $failed++;
    }
    
    if (strpos($content, 'update') !== false && strpos($content, 'mode') !== false) {
        echo "   ✓ Update mode supported\n";
        $passed++;
    } else {
        echo "   ✗ Update mode not supported\n";
        $failed++;
    }
} else {
    $failed += 2;
}

// Test 10: Check ACF compatibility
echo "\n10. ACF Compatibility\n";
if (file_exists($seederFile)) {
    $content = file_get_contents($seederFile);
    
    if (strpos($content, 'function_exists') !== false && strpos($content, 'update_field') !== false) {
        echo "   ✓ ACF compatibility check included\n";
        $passed++;
    } else {
        echo "   ✗ ACF compatibility check missing\n";
        $failed++;
    }
    
    if (strpos($content, 'update_post_meta') !== false) {
        echo "   ✓ Fallback to update_post_meta\n";
        $passed++;
    } else {
        echo "   ✗ Fallback to update_post_meta missing\n";
        $failed++;
    }
} else {
    $failed += 2;
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";
echo str_repeat("=", 50) . "\n";

if ($failed === 0) {
    echo "✅ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    exit(1);
}

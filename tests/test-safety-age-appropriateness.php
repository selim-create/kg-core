<?php
/**
 * Test script for SafetyCheckService Age Appropriateness Fix
 * 
 * Tests that recipes for older age groups correctly set is_safe = false
 * for younger children.
 * 
 * Usage: php test-safety-age-appropriateness.php
 */

// Try to load WordPress
$wp_load_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../wp-load.php',
];

$wordpress_loaded = false;
foreach ($wp_load_paths as $wp_load_path) {
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
        $wordpress_loaded = true;
        break;
    }
}

if (!$wordpress_loaded) {
    echo "\033[33mWarning: WordPress not found. Running static validation only.\033[0m\n";
    echo "\n=== STATIC VALIDATION ===\n";
    
    // Check that SafetyCheckService file exists
    $service_file = __DIR__ . '/../includes/Services/SafetyCheckService.php';
    if (file_exists($service_file)) {
        $content = file_get_contents($service_file);
        
        // Check for new method
        if (strpos($content, 'is_recipe_for_older_children') !== false) {
            echo "✓ is_recipe_for_older_children method exists\n";
        } else {
            echo "✗ is_recipe_for_older_children method NOT found\n";
            exit(1);
        }
        
        // Check for is_for_older flag
        if (strpos($content, "'is_for_older'") !== false) {
            echo "✓ is_for_older flag exists in code\n";
        } else {
            echo "✗ is_for_older flag NOT found\n";
            exit(1);
        }
        
        // Check for critical severity on older age groups (normalize quotes for search)
        if (strpos($content, "= 'critical'") !== false || 
            strpos($content, '= "critical"') !== false) {
            echo "✓ Critical severity set for older age groups\n";
        } else {
            echo "✗ Critical severity NOT found\n";
            exit(1);
        }
        
        echo "\n✓ Static validation passed\n";
        exit(0);
    } else {
        echo "✗ SafetyCheckService.php not found\n";
        exit(1);
    }
}

echo "\n=== SafetyCheckService Age Appropriateness Tests ===\n\n";

// Test counters
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;

function test($description, $callback) {
    global $total_tests, $passed_tests, $failed_tests;
    $total_tests++;
    
    try {
        $result = $callback();
        if ($result) {
            $passed_tests++;
            echo "✓ {$description}\n";
            return true;
        } else {
            $failed_tests++;
            echo "✗ {$description}\n";
            return false;
        }
    } catch (Exception $e) {
        $failed_tests++;
        echo "✗ {$description}: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test 1: Method exists
echo "--- Method Existence ---\n";

test('is_recipe_for_older_children method exists', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    $reflection = new ReflectionClass($service);
    return $reflection->hasMethod('is_recipe_for_older_children');
});

// Test 2: Mock test with simulated data
echo "\n--- Functional Tests (Mock Data) ---\n";

test('Recipe for older age group sets is_safe = false', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create a mock child (4 months old - very young)
    $mock_child = [
        'id' => 'test-child-123',
        'name' => 'Test Bebek',
        'birth_date' => date('Y-m-d', strtotime('-4 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a recipe with an older age group (2+ years)
    $args = [
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [
            [
                'taxonomy' => 'age-group',
                'field' => 'slug',
                'terms' => '2-yas-ve-uzeri',
            ],
        ],
    ];
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        echo " (No recipes found with 2+ age group, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $mock_child);
    
    // Verify is_safe is false
    if ($result['is_safe'] === false) {
        return true;
    }
    
    echo " (Expected is_safe=false, got: " . var_export($result['is_safe'], true) . ")";
    return false;
});

test('Recipe for older age group has critical severity alert', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create a mock child (4 months old - very young)
    $mock_child = [
        'id' => 'test-child-123',
        'name' => 'Test Bebek',
        'birth_date' => date('Y-m-d', strtotime('-4 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a recipe with an older age group
    $args = [
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [
            [
                'taxonomy' => 'age-group',
                'field' => 'slug',
                'terms' => ['12-24-ay-gecis', '2-yas-ve-uzeri'],
                'operator' => 'IN',
            ],
        ],
    ];
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        echo " (No recipes found with older age groups, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $mock_child);
    
    // Check for critical alert
    $has_critical = false;
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'age' && $alert['severity'] === 'critical') {
            $has_critical = true;
            break;
        }
    }
    
    if ($has_critical) {
        return true;
    }
    
    echo " (No critical age alert found)";
    return false;
});

test('Recipe for older age group has is_for_older flag', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create a mock child (6 months old)
    $mock_child = [
        'id' => 'test-child-123',
        'name' => 'Test Bebek',
        'birth_date' => date('Y-m-d', strtotime('-6 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a recipe with an older age group
    $args = [
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [
            [
                'taxonomy' => 'age-group',
                'field' => 'slug',
                'terms' => '2-yas-ve-uzeri',
            ],
        ],
    ];
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        echo " (No recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $mock_child);
    
    // Check for is_for_older flag
    $has_flag = false;
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'age' && 
            isset($alert['is_for_older']) && 
            $alert['is_for_older'] === true) {
            $has_flag = true;
            break;
        }
    }
    
    if ($has_flag) {
        return true;
    }
    
    echo " (is_for_older flag not found in age alerts)";
    return false;
});

test('Recipe for same age group remains safe', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create a mock child (8 months old)
    $mock_child = [
        'id' => 'test-child-123',
        'name' => 'Test Bebek',
        'birth_date' => date('Y-m-d', strtotime('-8 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a recipe with matching age group (6-8 months)
    $args = [
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [
            [
                'taxonomy' => 'age-group',
                'field' => 'slug',
                'terms' => '6-8-ay-baslangic',
            ],
        ],
    ];
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        echo " (No recipes found with matching age group, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $mock_child);
    
    // Should be safe (no age alerts should be generated)
    // Check that there are no critical age alerts
    $has_critical_age_alert = false;
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'age' && $alert['severity'] === 'critical') {
            $has_critical_age_alert = true;
            break;
        }
    }
    
    if (!$has_critical_age_alert) {
        return true;
    }
    
    echo " (Unexpected critical age alert for matching age group)";
    return false;
});

test('Recipe for younger age group is informational only', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create a mock child (24 months old - older)
    $mock_child = [
        'id' => 'test-child-123',
        'name' => 'Test Bebek',
        'birth_date' => date('Y-m-d', strtotime('-24 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a recipe with younger age group (6-8 months)
    $args = [
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [
            [
                'taxonomy' => 'age-group',
                'field' => 'slug',
                'terms' => '6-8-ay-baslangic',
            ],
        ],
    ];
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        echo " (No recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $mock_child);
    
    // Recipe for younger age group should still be safe
    // (Older children can eat food for younger children)
    if ($result['is_safe'] !== true) {
        echo " (Expected is_safe=true for younger age group recipe)";
        return false;
    }
    
    // If there's an age alert, it should be info level with is_for_older = false
    $has_correct_alert = true;
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'age') {
            if ($alert['severity'] !== 'info' || 
                !isset($alert['is_for_older']) || 
                $alert['is_for_older'] !== false) {
                $has_correct_alert = false;
                break;
            }
        }
    }
    
    if ($has_correct_alert) {
        return true;
    }
    
    echo " (Age alert should be info level with is_for_older=false)";
    return false;
});

// Print summary
echo "\n=== Test Summary ===\n";
echo "Total tests: {$total_tests}\n";
echo "Passed: \033[32m{$passed_tests}\033[0m\n";
echo "Failed: " . ($failed_tests > 0 ? "\033[31m{$failed_tests}\033[0m" : $failed_tests) . "\n";
echo "Success rate: " . round(($passed_tests / $total_tests) * 100, 1) . "%\n";

if ($failed_tests === 0) {
    echo "\n\033[32m✓ All tests passed!\033[0m\n\n";
    exit(0);
} else {
    echo "\n\033[31m✗ Some tests failed. Please review the output above.\033[0m\n\n";
    exit(1);
}

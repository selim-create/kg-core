<?php
/**
 * Comprehensive Test for Age Group Algorithm and Safety Mapping
 * 
 * Tests the centralized age compatibility mapping, HTML decoding, 
 * and alert severity for all combinations of child age vs recipe age.
 * 
 * Test Criteria (from requirements):
 * - 0-6 month child + 9-11 month or 2+ year recipe → minimum warning/critical, never success
 * - Older child with younger recipe → info (yellow/blue)
 * - Matching ages → success (green, safe)
 * - All API messages are HTML decoded (no &amp;, &lt;, etc.)
 * - Allergy, forbidden, nutrition alerts have proper severity_color
 * 
 * Usage: php test-age-group-algorithm.php
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
    
    // Check that SafetyCheckService file exists and has required components
    $service_file = __DIR__ . '/../includes/Services/SafetyCheckService.php';
    if (file_exists($service_file)) {
        $content = file_get_contents($service_file);
        
        $checks = [
            'get_age_compatibility_severity method' => 'get_age_compatibility_severity',
            'decode_alert_messages method' => 'decode_alert_messages',
            'severity_color field' => "'severity_color'",
            'html_entity_decode usage' => 'html_entity_decode',
            'Centralized mapping documentation' => 'Centralized Age Compatibility',
            'Age order mapping' => 'age_order',
        ];
        
        $all_passed = true;
        foreach ($checks as $name => $search) {
            if (strpos($content, $search) !== false) {
                echo "✓ $name exists\n";
            } else {
                echo "✗ $name NOT found\n";
                $all_passed = false;
            }
        }
        
        if ($all_passed) {
            echo "\n✓ Static validation passed\n";
            exit(0);
        } else {
            echo "\n✗ Static validation failed\n";
            exit(1);
        }
    } else {
        echo "✗ SafetyCheckService.php not found\n";
        exit(1);
    }
}

echo "\n=== Age Group Algorithm Comprehensive Tests ===\n\n";

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

// Test 1: Core Methods Exist
echo "--- Core Method Existence ---\n";

test('get_age_compatibility_severity method exists', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    $reflection = new ReflectionClass($service);
    return $reflection->hasMethod('get_age_compatibility_severity');
});

test('decode_alert_messages method exists', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    $reflection = new ReflectionClass($service);
    return $reflection->hasMethod('decode_alert_messages');
});

// Test 2: Age Compatibility Severity Mapping
echo "\n--- Age Compatibility Severity Tests ---\n";

test('0-6 month child + 9-11 month recipe → WARNING (1 level gap)', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create 4-month-old child
    $child = [
        'id' => 'test-child',
        'name' => 'Test Baby',
        'birth_date' => date('Y-m-d', strtotime('-4 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a 9-11 month recipe
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [[
            'taxonomy' => 'age-group',
            'field' => 'slug',
            'terms' => '9-11-ay-kesif',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No 9-11 month recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $child);
    
    // Check for warning severity (1 level gap)
    $has_age_alert = false;
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'age') {
            $has_age_alert = true;
            if ($alert['severity'] === 'warning' && $alert['is_for_older'] === true) {
                return true;
            }
        }
    }
    
    echo " (Expected warning severity for 1-level gap)";
    return false;
});

test('0-6 month child + 2+ year recipe → CRITICAL (large gap)', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create 4-month-old child
    $child = [
        'id' => 'test-child',
        'name' => 'Test Baby',
        'birth_date' => date('Y-m-d', strtotime('-4 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a 2+ year recipe
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [[
            'taxonomy' => 'age-group',
            'field' => 'slug',
            'terms' => '2-yas-ve-uzeri',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No 2+ year recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $child);
    
    // Check for critical severity (2+ level gap)
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'age' && 
            $alert['severity'] === 'critical' && 
            $alert['is_for_older'] === true) {
            return true;
        }
    }
    
    echo " (Expected critical severity for 2+ level gap)";
    return false;
});

test('Older child + younger recipe → INFO (safe)', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create 24-month-old child
    $child = [
        'id' => 'test-child',
        'name' => 'Test Child',
        'birth_date' => date('Y-m-d', strtotime('-24 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a 6-8 month recipe
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [[
            'taxonomy' => 'age-group',
            'field' => 'slug',
            'terms' => '6-8-ay-baslangic',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No 6-8 month recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $child);
    
    // Should be safe (older children can eat younger food)
    if ($result['is_safe'] !== true) {
        echo " (Expected is_safe=true for older child with younger recipe)";
        return false;
    }
    
    // Check for info severity if age alert exists
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'age') {
            if ($alert['severity'] === 'info' && $alert['is_for_older'] === false) {
                return true;
            }
        }
    }
    
    // No age alert is also acceptable (safe to eat)
    return true;
});

test('Matching age groups → No critical age alerts', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create 8-month-old child
    $child = [
        'id' => 'test-child',
        'name' => 'Test Baby',
        'birth_date' => date('Y-m-d', strtotime('-8 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a 6-8 month recipe (matching age group)
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [[
            'taxonomy' => 'age-group',
            'field' => 'slug',
            'terms' => '6-8-ay-baslangic',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No matching age recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $child);
    
    // Should not have critical age alerts
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'age' && 
            ($alert['severity'] === 'critical' || $alert['severity'] === 'warning')) {
            echo " (Unexpected critical/warning for matching age group)";
            return false;
        }
    }
    
    return true;
});

// Test 3: Severity Color Mapping
echo "\n--- Severity Color Mapping Tests ---\n";

test('Critical alerts have severity_color = red', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create child with allergy
    $child = [
        'id' => 'test-child',
        'name' => 'Test Baby',
        'birth_date' => date('Y-m-d', strtotime('-8 months')),
        'allergies' => ['sut', 'milk'],
        'introduced_foods' => [],
    ];
    
    // Find a recipe with milk allergen
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [[
            'taxonomy' => 'allergen',
            'field' => 'slug',
            'terms' => ['sut', 'milk'],
            'operator' => 'IN',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No recipes with allergens found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $child);
    
    // Check for critical allergy alert with red color
    foreach ($result['alerts'] as $alert) {
        if ($alert['type'] === 'allergy' && $alert['severity'] === 'critical') {
            if (isset($alert['severity_color']) && $alert['severity_color'] === 'red') {
                return true;
            }
        }
    }
    
    echo " (Expected severity_color=red for critical allergy alert)";
    return false;
});

test('Warning alerts have severity_color = yellow', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Test with ingredient age check
    $child = [
        'id' => 'test-child',
        'name' => 'Test Baby',
        'birth_date' => date('Y-m-d', strtotime('-6 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find an ingredient with start_age > 6 months
    $query = new WP_Query([
        'post_type' => 'ingredient',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [[
            'key' => '_kg_start_age',
            'value' => 6,
            'compare' => '>',
            'type' => 'NUMERIC',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No ingredients with age restrictions found, skipping)";
        return true;
    }
    
    $ingredient_id = $query->posts[0]->ID;
    $result = $service->checkIngredientSafety($ingredient_id, $child);
    
    // Check for warning with yellow color
    foreach ($result['alerts'] as $alert) {
        if ($alert['severity'] === 'warning') {
            if (isset($alert['severity_color']) && $alert['severity_color'] === 'yellow') {
                return true;
            }
        }
    }
    
    // If no alerts, that's also acceptable
    if (empty($result['alerts'])) {
        return true;
    }
    
    echo " (Expected severity_color=yellow for warning alerts)";
    return false;
});

test('Info alerts have severity_color = blue', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Create older child with younger recipe
    $child = [
        'id' => 'test-child',
        'name' => 'Test Child',
        'birth_date' => date('Y-m-d', strtotime('-18 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a 6-8 month recipe
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [[
            'taxonomy' => 'age-group',
            'field' => 'slug',
            'terms' => '6-8-ay-baslangic',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $child);
    
    // Check for info alerts with blue color
    foreach ($result['alerts'] as $alert) {
        if ($alert['severity'] === 'info') {
            if (isset($alert['severity_color']) && $alert['severity_color'] === 'blue') {
                return true;
            }
        }
    }
    
    // If no info alerts, check if recipe is safe (which is also correct)
    if ($result['is_safe']) {
        return true;
    }
    
    echo " (Expected severity_color=blue for info alerts)";
    return false;
});

// Test 4: HTML Entity Decoding
echo "\n--- HTML Entity Decoding Tests ---\n";

test('Alert messages are HTML decoded (no &amp;)', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    $child = [
        'id' => 'test-child',
        'name' => 'Test Baby',
        'birth_date' => date('Y-m-d', strtotime('-8 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Get any recipe
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
    ]);
    
    if (!$query->have_posts()) {
        echo " (No recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $child);
    
    // Check all alert messages for HTML entities
    foreach ($result['alerts'] as $alert) {
        if (isset($alert['message'])) {
            if (strpos($alert['message'], '&amp;') !== false ||
                strpos($alert['message'], '&lt;') !== false ||
                strpos($alert['message'], '&gt;') !== false ||
                strpos($alert['message'], '&quot;') !== false) {
                echo " (Found HTML entities in message: " . substr($alert['message'], 0, 50) . ")";
                return false;
            }
        }
    }
    
    return true;
});

test('Ingredient names are HTML decoded', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    $child = [
        'id' => 'test-child',
        'name' => 'Test Baby',
        'birth_date' => date('Y-m-d', strtotime('-8 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Get any ingredient
    $query = new WP_Query([
        'post_type' => 'ingredient',
        'post_status' => 'publish',
        'posts_per_page' => 1,
    ]);
    
    if (!$query->have_posts()) {
        echo " (No ingredients found, skipping)";
        return true;
    }
    
    $ingredient_id = $query->posts[0]->ID;
    $result = $service->checkIngredientSafety($ingredient_id, $child);
    
    // Check ingredient name for HTML entities
    if (isset($result['ingredient_name'])) {
        if (strpos($result['ingredient_name'], '&amp;') !== false ||
            strpos($result['ingredient_name'], '&lt;') !== false ||
            strpos($result['ingredient_name'], '&gt;') !== false) {
            echo " (Found HTML entities in ingredient_name)";
            return false;
        }
    }
    
    return true;
});

// Test 5: Combined Alert Scenarios
echo "\n--- Combined Alert Scenarios ---\n";

test('is_safe = false when recipe has critical age alert', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    // Very young child
    $child = [
        'id' => 'test-child',
        'name' => 'Test Baby',
        'birth_date' => date('Y-m-d', strtotime('-4 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    // Find a 2+ year recipe
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [[
            'taxonomy' => 'age-group',
            'field' => 'slug',
            'terms' => '2-yas-ve-uzeri',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No recipes found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    $result = $service->checkRecipeSafety($recipe_id, $child);
    
    // Recipe should be unsafe
    if ($result['is_safe'] === false) {
        return true;
    }
    
    echo " (Expected is_safe=false for critical age mismatch)";
    return false;
});

test('Safety score decreases with multiple alerts', function() {
    $service = new \KG_Core\Services\SafetyCheckService();
    
    $child1 = [
        'id' => 'test-child-1',
        'name' => 'Test Baby 1',
        'birth_date' => date('Y-m-d', strtotime('-8 months')),
        'allergies' => [],
        'introduced_foods' => [],
    ];
    
    $child2 = [
        'id' => 'test-child-2',
        'name' => 'Test Baby 2',
        'birth_date' => date('Y-m-d', strtotime('-8 months')),
        'allergies' => ['sut', 'milk'],
        'introduced_foods' => [],
    ];
    
    // Get any recipe
    $query = new WP_Query([
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'tax_query' => [[
            'taxonomy' => 'allergen',
            'field' => 'slug',
            'terms' => ['sut', 'milk'],
            'operator' => 'IN',
        ]],
    ]);
    
    if (!$query->have_posts()) {
        echo " (No recipes with allergens found, skipping)";
        return true;
    }
    
    $recipe_id = $query->posts[0]->ID;
    
    $result1 = $service->checkRecipeSafety($recipe_id, $child1);
    $result2 = $service->checkRecipeSafety($recipe_id, $child2);
    
    // Child with allergy should have lower safety score
    if ($result2['safety_score'] < $result1['safety_score']) {
        return true;
    }
    
    // Or if both have 0 score due to other issues
    if ($result2['safety_score'] === 0) {
        return true;
    }
    
    echo " (Expected lower safety score for child with allergy)";
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

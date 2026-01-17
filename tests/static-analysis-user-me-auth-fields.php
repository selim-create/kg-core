<?php
/**
 * Static Analysis Test for UserController get_user_me() Changes
 * 
 * This test verifies that the get_user_me() method in UserController.php
 * includes all the required authorization fields as specified in the requirements.
 * 
 * This is a static code analysis test that does not require WordPress.
 */

$file = __DIR__ . '/../includes/API/UserController.php';

if (!file_exists($file)) {
    echo "❌ ERROR: UserController.php not found at: $file\n";
    exit(1);
}

$content = file_get_contents($file);

echo "=== STATIC ANALYSIS: UserController get_user_me() Authorization Fields ===\n\n";

$all_passed = true;

// Find the get_user_me method
if (preg_match('/public function get_user_me\s*\([^)]*\)\s*\{(.*?)(?=public function|\}[\s\n]*$)/s', $content, $matches)) {
    $method_content = $matches[1];
    echo "✅ Found get_user_me() method\n\n";
    
    // Check for required variable assignments
    echo "Checking for required calculations:\n";
    
    $required_calculations = [
        '/\$is_admin\s*=\s*in_array\s*\(\s*[\'"]administrator[\'"]\s*,\s*\$roles\s*\)/' => 'is_admin calculation',
        '/\$is_editor\s*=\s*in_array\s*\(\s*[\'"]editor[\'"]\s*,\s*\$roles\s*\)\s*\|\|\s*\$is_admin/' => 'is_editor calculation',
        '/\$has_editor_access\s*=\s*\$is_admin\s*\|\|\s*\$is_editor\s*\|\|\s*\$is_expert/' => 'has_editor_access calculation',
        '/\$admin_url\s*=\s*defined\s*\(\s*[\'"]KG_API_URL[\'"]\s*\)/' => 'admin_url calculation',
        '/\$can_edit\s*=\s*\[/' => 'can_edit array',
        '/\$can_edit_others\s*=\s*\[/' => 'can_edit_others array',
        '/\$edit_urls\s*=\s*\[/' => 'edit_urls array',
    ];
    
    foreach ($required_calculations as $pattern => $description) {
        if (preg_match($pattern, $method_content)) {
            echo "   ✅ $description found\n";
        } else {
            echo "   ❌ $description MISSING\n";
            $all_passed = false;
        }
    }
    
    echo "\nChecking for required response fields:\n";
    
    // Check if response array includes new fields
    $required_response_fields = [
        'is_admin' => '/[\'"]is_admin[\'"]\s*=>\s*\$is_admin/',
        'is_editor' => '/[\'"]is_editor[\'"]\s*=>\s*\$is_editor/',
        'has_editor_access' => '/[\'"]has_editor_access[\'"]\s*=>\s*\$has_editor_access/',
        'admin_url' => '/[\'"]admin_url[\'"]\s*=>\s*\$has_editor_access\s*\?\s*\$admin_url\s*:\s*null/',
        'edit_urls' => '/[\'"]edit_urls[\'"]\s*=>\s*\$has_editor_access\s*\?\s*\$edit_urls\s*:\s*null/',
        'can_edit' => '/[\'"]can_edit[\'"]\s*=>\s*\$can_edit/',
        'can_edit_others' => '/[\'"]can_edit_others[\'"]\s*=>\s*\$can_edit_others/',
    ];
    
    foreach ($required_response_fields as $field => $pattern) {
        if (preg_match($pattern, $method_content)) {
            echo "   ✅ Response field '$field' found\n";
        } else {
            echo "   ❌ Response field '$field' MISSING\n";
            $all_passed = false;
        }
    }
    
    echo "\nChecking can_edit structure:\n";
    
    // Check can_edit array structure
    $can_edit_fields = ['posts', 'recipes', 'ingredients', 'discussions'];
    foreach ($can_edit_fields as $field) {
        $pattern = "/['\"]" . $field . "['\"]\\s*=>\\s*\\\$user->has_cap\\s*\\(\\s*['\"]edit_posts['\"]\\s*\\)/";
        if (preg_match($pattern, $method_content)) {
            echo "   ✅ can_edit['$field'] found\n";
        } else {
            echo "   ⚠️  can_edit['$field'] not found (may be on separate line)\n";
        }
    }
    
    echo "\nChecking can_edit_others structure:\n";
    
    // Check can_edit_others array structure
    foreach ($can_edit_fields as $field) {
        $pattern = "/['\"]" . $field . "['\"]\\s*=>\\s*\\\$user->has_cap\\s*\\(\\s*['\"]edit_others_posts['\"]\\s*\\)/";
        if (preg_match($pattern, $method_content)) {
            echo "   ✅ can_edit_others['$field'] found\n";
        } else {
            echo "   ⚠️  can_edit_others['$field'] not found (may be on separate line)\n";
        }
    }
    
    echo "\nChecking edit_urls structure:\n";
    
    // Check edit_urls array structure
    $edit_url_fields = ['new_post', 'new_recipe', 'new_ingredient', 'new_discussion'];
    foreach ($edit_url_fields as $field) {
        $pattern = "/['\"]" . $field . "['\"]\\s*=>/";
        if (preg_match($pattern, $method_content)) {
            echo "   ✅ edit_urls['$field'] found\n";
        } else {
            echo "   ⚠️  edit_urls['$field'] not found (may be on separate line)\n";
        }
    }
    
} else {
    echo "❌ ERROR: Could not find get_user_me() method\n";
    $all_passed = false;
}

echo "\n";
echo "========================================\n";

if ($all_passed) {
    echo "✅ STATIC ANALYSIS PASSED!\n";
    echo "All required authorization fields are present in get_user_me() method.\n";
    echo "========================================\n";
    exit(0);
} else {
    echo "❌ STATIC ANALYSIS FAILED\n";
    echo "Some required fields are missing.\n";
    echo "========================================\n";
    exit(1);
}

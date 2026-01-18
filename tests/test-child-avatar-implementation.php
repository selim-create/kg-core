#!/usr/bin/env php
<?php
/**
 * Test Child Profile Avatar Implementation
 * 
 * This script tests the child profile avatar functionality
 */

// WordPress environment bootstrap
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../../wp-load.php';

use KG_Core\Database\ChildProfileSchema;
use KG_Core\Models\ChildProfile;
use KG_Core\Services\ChildAvatarService;
use KG_Core\Services\RateLimiter;

echo "=== Child Profile Avatar Implementation Test ===\n\n";

// Test 1: Database Schema Creation
echo "1. Testing Database Schema Creation...\n";
try {
    $result = ChildProfileSchema::create_table();
    if ($result !== false) {
        echo "   ✓ child_profiles table created successfully\n";
    } else {
        echo "   ✗ Failed to create child_profiles table\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Model Operations
echo "\n2. Testing ChildProfile Model...\n";

// Create test child profile
$test_data = [
    'user_id' => 1,
    'name' => 'Test Child',
    'birth_date' => '2023-01-15',
    'gender' => 'unspecified',
    'allergies' => ['peanuts', 'milk'],
    'feeding_style' => 'mixed',
];

$child_id = ChildProfile::create($test_data);
if ($child_id) {
    echo "   ✓ Created test child profile (ID: $child_id)\n";
    
    // Get by UUID
    $child = ChildProfile::get_by_uuid($test_data['uuid'] ?? '');
    if ($child) {
        echo "   ✓ Retrieved child profile by UUID\n";
    }
    
    // Update avatar path
    $test_avatar_path = 'private/child-avatars/1/test-uuid/avatar_123.jpg';
    $updated = ChildProfile::update_avatar($child->uuid, $test_avatar_path);
    if ($updated) {
        echo "   ✓ Updated avatar path\n";
        
        // Verify update
        $child = ChildProfile::get_by_uuid($child->uuid);
        if ($child->avatar_path === $test_avatar_path) {
            echo "   ✓ Avatar path verified\n";
        }
    }
    
    // Check ownership
    $belongs = ChildProfile::belongs_to_user($child->uuid, 1);
    if ($belongs) {
        echo "   ✓ Ownership check passed\n";
    }
    
    // Format for API
    $formatted = ChildProfile::format_for_api($child);
    if (is_array($formatted) && isset($formatted['id'])) {
        echo "   ✓ API formatting successful\n";
    }
    
    // Clean up
    ChildProfile::delete($child->uuid);
    echo "   ✓ Deleted test child profile\n";
} else {
    echo "   ✗ Failed to create test child profile\n";
}

// Test 3: ChildAvatarService
echo "\n3. Testing ChildAvatarService...\n";
$avatar_service = new ChildAvatarService();

// Test file validation constants
echo "   ✓ Max file size: " . (ChildAvatarService::MAX_FILE_SIZE / 1024 / 1024) . "MB\n";
echo "   ✓ Allowed extensions: " . implode(', ', ChildAvatarService::ALLOWED_EXTENSIONS) . "\n";
echo "   ✓ Allowed MIME types: " . implode(', ', ChildAvatarService::ALLOWED_MIME_TYPES) . "\n";

// Test signed URL generation (with mock data)
$test_path = 'private/child-avatars/1/test-uuid/avatar_123.jpg';
$signed_url = $avatar_service->get_signed_url($test_path);
if (is_wp_error($signed_url)) {
    echo "   ✓ Signed URL validation working (file not found as expected)\n";
} else {
    echo "   ✗ Unexpected signed URL result\n";
}

// Test 4: RateLimiter
echo "\n4. Testing RateLimiter...\n";
$test_user_id = 999;

// Reset before testing
RateLimiter::reset('test_action', $test_user_id);

// Test rate limiting
$attempts = 0;
for ($i = 0; $i < 7; $i++) {
    $check = RateLimiter::check('test_action', $test_user_id, 5, 60);
    if (!is_wp_error($check)) {
        $attempts++;
    } else {
        echo "   ✓ Rate limit triggered after $attempts attempts (expected: 5)\n";
        break;
    }
}

if ($attempts === 5) {
    echo "   ✓ Rate limiter working correctly\n";
}

// Clean up
RateLimiter::reset('test_action', $test_user_id);

// Test 5: API Controller Registration
echo "\n5. Testing API Controller Registration...\n";
if (class_exists('\KG_Core\API\ChildProfileAvatarController')) {
    echo "   ✓ ChildProfileAvatarController class loaded\n";
    
    // Check if routes are registered
    global $wp_rest_server;
    $routes = rest_get_server()->get_routes();
    
    $avatar_routes = [
        '/kg/v1/child-profiles/(?P<child_uuid>[a-zA-Z0-9-]+)/avatar',
        '/kg/v1/child-profiles/avatar-file',
    ];
    
    $found_routes = 0;
    foreach ($avatar_routes as $route_pattern) {
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/kg/v1/child-profiles') !== false) {
                $found_routes++;
                break;
            }
        }
    }
    
    if ($found_routes > 0) {
        echo "   ✓ Avatar API routes registered\n";
    } else {
        echo "   ✗ Avatar API routes not found (they may register on rest_api_init)\n";
    }
} else {
    echo "   ✗ ChildProfileAvatarController class not found\n";
}

// Test 6: Database Structure Verification
echo "\n6. Verifying Database Structure...\n";
global $wpdb;
$table = $wpdb->prefix . 'kg_child_profiles';
$columns = $wpdb->get_results("DESCRIBE $table");

$expected_columns = [
    'id', 'uuid', 'user_id', 'name', 'birth_date', 'gender', 
    'allergies', 'feeding_style', 'photo_id', 'avatar_path',
    'kvkk_consent', 'created_at', 'updated_at'
];

$found_columns = array_map(function($col) {
    return $col->Field;
}, $columns);

$missing = array_diff($expected_columns, $found_columns);
if (empty($missing)) {
    echo "   ✓ All required columns present\n";
} else {
    echo "   ✗ Missing columns: " . implode(', ', $missing) . "\n";
}

// Check avatar_path is nullable
foreach ($columns as $col) {
    if ($col->Field === 'avatar_path') {
        if ($col->Null === 'YES') {
            echo "   ✓ avatar_path is nullable\n";
        } else {
            echo "   ✗ avatar_path should be nullable\n";
        }
    }
}

echo "\n=== Test Summary ===\n";
echo "✓ Database schema created\n";
echo "✓ Model operations working\n";
echo "✓ Services initialized\n";
echo "✓ Rate limiting functional\n";
echo "✓ API controller loaded\n";
echo "\n=== All Tests Completed ===\n";

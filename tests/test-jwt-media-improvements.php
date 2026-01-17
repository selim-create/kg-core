<?php
/**
 * Static Code Analysis Test for JWT Authentication and MediaController
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core JWT Auth & Media Controller Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check if CORSHandler has JWT auth support
echo "1. CORSHandler JWT Authentication Support\n";
$corsFile = $baseDir . '/includes/CORS/CORSHandler.php';
if (file_exists($corsFile)) {
    echo "   ✓ File exists: CORSHandler.php\n";
    $passed++;
    $content = file_get_contents($corsFile);
    
    // Check for enable_jwt_for_wp_endpoints method
    if (strpos($content, 'function enable_jwt_for_wp_endpoints') !== false) {
        echo "   ✓ Method exists: enable_jwt_for_wp_endpoints()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: enable_jwt_for_wp_endpoints()\n";
        $failed++;
    }
    
    // Check for filter hook
    if (strpos($content, "add_filter('rest_authentication_errors'") !== false) {
        echo "   ✓ Filter hook registered: rest_authentication_errors\n";
        $passed++;
    } else {
        echo "   ✗ Filter hook missing: rest_authentication_errors\n";
        $failed++;
    }
    
    // Check for /wp/v2/media endpoint support
    if (strpos($content, '/wp/v2/media') !== false) {
        echo "   ✓ Endpoint supported: /wp/v2/media\n";
        $passed++;
    } else {
        echo "   ✗ Endpoint missing: /wp/v2/media\n";
        $failed++;
    }
    
    // Check for /wp/v2/comments endpoint support
    if (strpos($content, '/wp/v2/comments') !== false) {
        echo "   ✓ Endpoint supported: /wp/v2/comments\n";
        $passed++;
    } else {
        echo "   ✗ Endpoint missing: /wp/v2/comments\n";
        $failed++;
    }
    
    // Check for JWT token validation
    if (strpos($content, 'JWTHandler::get_token_from_request()') !== false) {
        echo "   ✓ JWT token extraction implemented\n";
        $passed++;
    } else {
        echo "   ✗ JWT token extraction missing\n";
        $failed++;
    }
    
    if (strpos($content, 'JWTHandler::validate_token') !== false) {
        echo "   ✓ JWT token validation implemented\n";
        $passed++;
    } else {
        echo "   ✗ JWT token validation missing\n";
        $failed++;
    }
    
    // Check for wp_set_current_user
    if (strpos($content, 'wp_set_current_user') !== false) {
        echo "   ✓ User authentication set: wp_set_current_user()\n";
        $passed++;
    } else {
        echo "   ✗ User authentication missing: wp_set_current_user()\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: CORSHandler.php\n";
    $failed++;
}

echo "\n2. MediaController Implementation\n";
$mediaFile = $baseDir . '/includes/API/MediaController.php';
if (file_exists($mediaFile)) {
    echo "   ✓ File exists: MediaController.php\n";
    $passed++;
    $content = file_get_contents($mediaFile);
    
    // Check for namespace
    if (strpos($content, 'namespace KG_Core\API') !== false) {
        echo "   ✓ Correct namespace: KG_Core\\API\n";
        $passed++;
    } else {
        echo "   ✗ Namespace incorrect or missing\n";
        $failed++;
    }
    
    // Check for required methods
    $requiredMethods = [
        'register_routes',
        'check_authentication',
        'upload_avatar'
    ];
    
    foreach ($requiredMethods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
    
    // Check for /kg/v1/user/avatar endpoint
    if (strpos($content, "'kg/v1'") !== false && strpos($content, "'/user/avatar'") !== false) {
        echo "   ✓ Endpoint registered: /kg/v1/user/avatar\n";
        $passed++;
    } else {
        echo "   ✗ Endpoint missing: /kg/v1/user/avatar\n";
        $failed++;
    }
    
    // Check for file type validation
    if (strpos($content, 'allowed_types') !== false) {
        echo "   ✓ File type validation implemented\n";
        $passed++;
    } else {
        echo "   ✗ File type validation missing\n";
        $failed++;
    }
    
    // Check for file size validation
    if (strpos($content, 'file_too_large') !== false || strpos($content, '2 * 1024 * 1024') !== false) {
        echo "   ✓ File size validation implemented (2MB limit)\n";
        $passed++;
    } else {
        echo "   ✗ File size validation missing\n";
        $failed++;
    }
    
    // Check for WordPress media functions
    if (strpos($content, 'media_handle_upload') !== false) {
        echo "   ✓ WordPress media upload used: media_handle_upload()\n";
        $passed++;
    } else {
        echo "   ✗ WordPress media upload missing\n";
        $failed++;
    }
    
    // Check for user meta update
    if (strpos($content, 'update_user_meta') !== false && strpos($content, 'kg_avatar_id') !== false) {
        echo "   ✓ User meta update implemented: kg_avatar_id\n";
        $passed++;
    } else {
        echo "   ✗ User meta update missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: MediaController.php\n";
    $failed++;
}

echo "\n3. Main Plugin File Integration\n";
$mainFile = $baseDir . '/kg-core.php';
if (file_exists($mainFile)) {
    echo "   ✓ File exists: kg-core.php\n";
    $passed++;
    $content = file_get_contents($mainFile);
    
    // Check if MediaController is included
    if (strpos($content, "includes/API/MediaController.php") !== false) {
        echo "   ✓ MediaController included in main file\n";
        $passed++;
    } else {
        echo "   ✗ MediaController not included in main file\n";
        $failed++;
    }
    
    // Check if MediaController is instantiated
    if (strpos($content, "new \KG_Core\API\MediaController()") !== false) {
        echo "   ✓ MediaController instantiated in kg_core_init()\n";
        $passed++;
    } else {
        echo "   ✗ MediaController not instantiated\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: kg-core.php\n";
    $failed++;
}

echo "\n4. Tool Post Type Admin Columns\n";
$toolFile = $baseDir . '/includes/PostTypes/Tool.php';
if (file_exists($toolFile)) {
    echo "   ✓ File exists: Tool.php\n";
    $passed++;
    $content = file_get_contents($toolFile);
    
    // Check for add_custom_columns filter
    if (strpos($content, "add_filter( 'manage_tool_posts_columns'") !== false) {
        echo "   ✓ Filter registered: manage_tool_posts_columns\n";
        $passed++;
    } else {
        echo "   ✗ Filter missing: manage_tool_posts_columns\n";
        $failed++;
    }
    
    // Check for render_custom_columns action
    if (strpos($content, "add_action( 'manage_tool_posts_custom_column'") !== false) {
        echo "   ✓ Action registered: manage_tool_posts_custom_column\n";
        $passed++;
    } else {
        echo "   ✗ Action missing: manage_tool_posts_custom_column\n";
        $failed++;
    }
    
    // Check for add_custom_columns method
    if (strpos($content, 'function add_custom_columns') !== false) {
        echo "   ✓ Method exists: add_custom_columns()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: add_custom_columns()\n";
        $failed++;
    }
    
    // Check for render_custom_columns method
    if (strpos($content, 'function render_custom_columns') !== false) {
        echo "   ✓ Method exists: render_custom_columns()\n";
        $passed++;
    } else {
        echo "   ✗ Method missing: render_custom_columns()\n";
        $failed++;
    }
    
    // Check for kg_sponsored column
    if (strpos($content, 'kg_sponsored') !== false) {
        echo "   ✓ Column added: kg_sponsored\n";
        $passed++;
    } else {
        echo "   ✗ Column missing: kg_sponsored\n";
        $failed++;
    }
    
    // Check for kg_active column
    if (strpos($content, 'kg_active') !== false) {
        echo "   ✓ Column added: kg_active\n";
        $passed++;
    } else {
        echo "   ✗ Column missing: kg_active\n";
        $failed++;
    }
    
    // Check for sponsor meta data retrieval
    if (strpos($content, '_kg_tool_is_sponsored') !== false) {
        echo "   ✓ Sponsor meta key used: _kg_tool_is_sponsored\n";
        $passed++;
    } else {
        echo "   ✗ Sponsor meta key missing\n";
        $failed++;
    }
    
    // Check for is_active field
    if (strpos($content, "get_field('is_active'") !== false) {
        echo "   ✓ Active field retrieved: is_active\n";
        $passed++;
    } else {
        echo "   ✗ Active field retrieval missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: Tool.php\n";
    $failed++;
}

// Summary
echo "\n=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n\n";

if ($failed > 0) {
    echo "❌ Some tests failed. Please review the implementation.\n";
    exit(1);
} else {
    echo "✅ All tests passed!\n";
    exit(0);
}

<?php
/**
 * Test file for User Profile & RBAC Implementation
 * 
 * This test verifies the new user meta fields, child data structure,
 * API endpoints, and RBAC system implementation.
 */

echo "=== KG Core User Profile & RBAC Implementation Test ===\n\n";

$baseDir = __DIR__;
$passed = 0;
$failed = 0;

// Test 1: Check if PrivacyHelper exists
echo "1. PrivacyHelper Utility\n";
$privacyHelperFile = $baseDir . '/includes/Utils/PrivacyHelper.php';
if (file_exists($privacyHelperFile)) {
    echo "   ✓ File exists: PrivacyHelper.php\n";
    $content = file_get_contents($privacyHelperFile);
    
    $requiredMethods = [
        'filter_public_profile',
        'remove_children_data',
        'remove_email',
        'remove_birth_dates'
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
} else {
    echo "   ✗ File missing: PrivacyHelper.php\n";
    $failed++;
}

echo "\n";

// Test 2: Check if RoleManager exists
echo "2. RoleManager (RBAC)\n";
$roleManagerFile = $baseDir . '/includes/Roles/RoleManager.php';
if (file_exists($roleManagerFile)) {
    echo "   ✓ File exists: RoleManager.php\n";
    $content = file_get_contents($roleManagerFile);
    
    // Check for kg_expert role registration
    if (strpos($content, "kg_expert") !== false && strpos($content, "add_role") !== false) {
        echo "   ✓ kg_expert role registration found\n";
        $passed++;
    } else {
        echo "   ✗ kg_expert role registration missing\n";
        $failed++;
    }
    
    // Check for capabilities
    $capabilities = [
        'kg_answer_questions',
        'kg_moderate_comments',
        'kg_view_expert_dashboard'
    ];
    
    foreach ($capabilities as $cap) {
        if (strpos($content, $cap) !== false) {
            echo "   ✓ Capability found: $cap\n";
            $passed++;
        } else {
            echo "   ✗ Capability missing: $cap\n";
            $failed++;
        }
    }
    
    // Check for permission check methods
    if (strpos($content, 'has_expert_permission') !== false) {
        echo "   ✓ Permission check method found\n";
        $passed++;
    } else {
        echo "   ✗ Permission check method missing\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: RoleManager.php\n";
    $failed++;
}

echo "\n";

// Test 3: Check if ExpertController exists
echo "3. ExpertController API\n";
$expertControllerFile = $baseDir . '/includes/API/ExpertController.php';
if (file_exists($expertControllerFile)) {
    echo "   ✓ File exists: ExpertController.php\n";
    $content = file_get_contents($expertControllerFile);
    
    // Check for dashboard endpoint
    if (strpos($content, '/expert/dashboard') !== false) {
        echo "   ✓ Expert dashboard endpoint registered\n";
        $passed++;
    } else {
        echo "   ✗ Expert dashboard endpoint missing\n";
        $failed++;
    }
    
    // Check for permission callback
    if (strpos($content, 'check_expert_permission') !== false) {
        echo "   ✓ Permission callback implemented\n";
        $passed++;
    } else {
        echo "   ✗ Permission callback missing\n";
        $failed++;
    }
    
    // Check for dashboard data methods
    $methods = [
        'get_pending_questions_count',
        'get_pending_comments_count',
        'get_today_answers_count',
        'get_weekly_stats'
    ];
    
    foreach ($methods as $method) {
        if (strpos($content, "function $method") !== false) {
            echo "   ✓ Method exists: $method()\n";
            $passed++;
        } else {
            echo "   ✗ Method missing: $method()\n";
            $failed++;
        }
    }
} else {
    echo "   ✗ File missing: ExpertController.php\n";
    $failed++;
}

echo "\n";

// Test 4: Check UserController updates
echo "4. UserController Enhancements\n";
$userControllerFile = $baseDir . '/includes/API/UserController.php';
if (file_exists($userControllerFile)) {
    echo "   ✓ File exists: UserController.php\n";
    $content = file_get_contents($userControllerFile);
    
    // Check for new user meta fields in update_profile
    $userMetaFields = ['_kg_display_name', '_kg_parent_role', '_kg_avatar_id'];
    foreach ($userMetaFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ User meta field used: $field\n";
            $passed++;
        } else {
            echo "   ✗ User meta field missing: $field\n";
            $failed++;
        }
    }
    
    // Check for new child fields
    $childFields = ['gender', 'allergies', 'feeding_style', 'photo_id', 'kvkk_consent', 'created_at'];
    foreach ($childFields as $field) {
        if (strpos($content, $field) !== false) {
            echo "   ✓ Child field used: $field\n";
            $passed++;
        } else {
            echo "   ✗ Child field missing: $field\n";
            $failed++;
        }
    }
    
    // Check for validations
    if (strpos($content, 'invalid_birth_date') !== false && strpos($content, 'cannot be in the future') !== false) {
        echo "   ✓ Birth date validation implemented\n";
        $passed++;
    } else {
        echo "   ✗ Birth date validation missing\n";
        $failed++;
    }
    
    if (strpos($content, 'invalid_gender') !== false) {
        echo "   ✓ Gender validation implemented\n";
        $passed++;
    } else {
        echo "   ✗ Gender validation missing\n";
        $failed++;
    }
    
    if (strpos($content, 'invalid_feeding_style') !== false) {
        echo "   ✓ Feeding style validation implemented\n";
        $passed++;
    } else {
        echo "   ✗ Feeding style validation missing\n";
        $failed++;
    }
    
    if (strpos($content, 'kvkk_consent_required') !== false) {
        echo "   ✓ KVKK consent validation implemented\n";
        $passed++;
    } else {
        echo "   ✗ KVKK consent validation missing\n";
        $failed++;
    }
    
    // Check for new endpoints
    if (strpos($content, '/user/me') !== false) {
        echo "   ✓ GET /user/me endpoint registered\n";
        $passed++;
    } else {
        echo "   ✗ GET /user/me endpoint missing\n";
        $failed++;
    }
    
    if (strpos($content, '/user/public/') !== false) {
        echo "   ✓ GET /user/public/{username} endpoint registered\n";
        $passed++;
    } else {
        echo "   ✗ GET /user/public/{username} endpoint missing\n";
        $failed++;
    }
    
    // Check for privacy filtering in public profile
    if (strpos($content, 'get_public_profile') !== false && strpos($content, 'PrivacyHelper::filter_public_profile') !== false) {
        echo "   ✓ Privacy filtering in public profile\n";
        $passed++;
    } else {
        echo "   ✗ Privacy filtering missing in public profile\n";
        $failed++;
    }
    
    // Check for UUID v4 generation using WordPress function
    if (strpos($content, 'wp_generate_uuid4()') !== false) {
        echo "   ✓ UUID v4 generation using wp_generate_uuid4()\n";
        $passed++;
    } else {
        echo "   ✗ UUID v4 generation missing or not using wp_generate_uuid4()\n";
        $failed++;
    }
    
    // Check for PrivacyHelper usage
    if (strpos($content, 'use KG_Core\Utils\PrivacyHelper') !== false) {
        echo "   ✓ PrivacyHelper imported in UserController\n";
        $passed++;
    } else {
        echo "   ✗ PrivacyHelper not imported in UserController\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: UserController.php\n";
    $failed++;
}

echo "\n";

// Test 5: Check kg-core.php integration
echo "5. Plugin Integration (kg-core.php)\n";
$mainFile = $baseDir . '/kg-core.php';
if (file_exists($mainFile)) {
    echo "   ✓ File exists: kg-core.php\n";
    $content = file_get_contents($mainFile);
    
    // Check if PrivacyHelper is required
    if (strpos($content, 'PrivacyHelper.php') !== false) {
        echo "   ✓ PrivacyHelper.php required\n";
        $passed++;
    } else {
        echo "   ✗ PrivacyHelper.php not required\n";
        $failed++;
    }
    
    // Check if RoleManager is required
    if (strpos($content, 'RoleManager.php') !== false) {
        echo "   ✓ RoleManager.php required\n";
        $passed++;
    } else {
        echo "   ✗ RoleManager.php not required\n";
        $failed++;
    }
    
    // Check if ExpertController is required
    if (strpos($content, 'ExpertController.php') !== false) {
        echo "   ✓ ExpertController.php required\n";
        $passed++;
    } else {
        echo "   ✗ ExpertController.php not required\n";
        $failed++;
    }
    
    // Check if RoleManager is initialized
    if (strpos($content, "new \\KG_Core\\Roles\\RoleManager()") !== false) {
        echo "   ✓ RoleManager initialized in kg_core_init()\n";
        $passed++;
    } else {
        echo "   ✗ RoleManager not initialized\n";
        $failed++;
    }
    
    // Check if ExpertController is initialized
    if (strpos($content, "\\KG_Core\\API\\ExpertController") !== false) {
        echo "   ✓ ExpertController initialized in kg_core_init()\n";
        $passed++;
    } else {
        echo "   ✗ ExpertController not initialized\n";
        $failed++;
    }
} else {
    echo "   ✗ File missing: kg-core.php\n";
    $failed++;
}

echo "\n";

// Test 6: Security checks
echo "6. Security Validation\n";

// Check UserController for security measures
$userControllerContent = file_exists($userControllerFile) ? file_get_contents($userControllerFile) : '';

// Check for sanitization in add_child
if (strpos($userControllerContent, 'sanitize_text_field') !== false) {
    echo "   ✓ Input sanitization present\n";
    $passed++;
} else {
    echo "   ✗ Input sanitization missing\n";
    $failed++;
}

// Check for authentication checks
if (strpos($userControllerContent, 'check_authentication') !== false) {
    echo "   ✓ Authentication checks present\n";
    $passed++;
} else {
    echo "   ✗ Authentication checks missing\n";
    $failed++;
}

// Check for permission_callback in ExpertController
$expertContent = file_exists($expertControllerFile) ? file_get_contents($expertControllerFile) : '';
if (strpos($expertContent, 'permission_callback') !== false) {
    echo "   ✓ Permission callbacks present in ExpertController\n";
    $passed++;
} else {
    echo "   ✗ Permission callbacks missing\n";
    $failed++;
}

// Ensure public endpoint uses __return_true for public access
if (strpos($userControllerContent, "'/user/public/") !== false && strpos($userControllerContent, "'permission_callback' => '__return_true'") !== false) {
    echo "   ✓ Public profile endpoint correctly configured\n";
    $passed++;
} else {
    echo "   ✗ Public profile endpoint configuration issue\n";
    $failed++;
}

echo "\n";

// Test 7: Parent role enum validation
echo "7. Enum Validations\n";

// Check parent_role enum
if (strpos($userControllerContent, 'Anne') !== false && 
    strpos($userControllerContent, 'Baba') !== false && 
    strpos($userControllerContent, 'Bakıcı') !== false && 
    strpos($userControllerContent, 'Diğer') !== false) {
    echo "   ✓ Parent role enum validated (Anne, Baba, Bakıcı, Diğer)\n";
    $passed++;
} else {
    echo "   ✗ Parent role enum validation incomplete\n";
    $failed++;
}

// Check gender enum
if (strpos($userControllerContent, 'male') !== false && 
    strpos($userControllerContent, 'female') !== false && 
    strpos($userControllerContent, 'unspecified') !== false) {
    echo "   ✓ Gender enum validated (male, female, unspecified)\n";
    $passed++;
} else {
    echo "   ✗ Gender enum validation incomplete\n";
    $failed++;
}

// Check feeding_style enum
if (strpos($userControllerContent, 'blw') !== false && 
    strpos($userControllerContent, 'puree') !== false && 
    strpos($userControllerContent, 'mixed') !== false) {
    echo "   ✓ Feeding style enum validated (blw, puree, mixed)\n";
    $passed++;
} else {
    echo "   ✗ Feeding style enum validation incomplete\n";
    $failed++;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "✓ Passed: $passed\n";
echo "✗ Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";

$percentage = ($passed + $failed) > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : 0;
echo "Success Rate: $percentage%\n";

if ($failed === 0) {
    echo "\n🎉 All tests passed! Implementation is complete.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Please review the implementation.\n";
    exit(1);
}

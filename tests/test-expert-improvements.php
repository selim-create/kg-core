<?php
/**
 * Static Code Analysis Test for Expert List API and Role Improvements
 * 
 * This test verifies the implementation without requiring WordPress
 */

echo "=== KG Core Expert Improvements Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

// Test 1: Check ExpertController for new endpoint
echo "1. ExpertController - GET /kg/v1/experts Endpoint\n";
$expertControllerFile = $baseDir . '/includes/API/ExpertController.php';
if (file_exists($expertControllerFile)) {
    echo "   ✓ File exists: ExpertController.php\n";
    $content = file_get_contents($expertControllerFile);
    
    // Check for experts route registration
    if (strpos($content, "'/experts'") !== false) {
        echo "   ✓ Route registered: /experts\n";
        $passed++;
    } else {
        echo "   ✗ Route missing: /experts\n";
        $failed++;
    }
    
    // Check for get_experts_list method
    if (strpos($content, 'function get_experts_list') !== false) {
        echo "   ✓ Method exists: get_experts_list()\n";
        $passed++;
        
        // Check for expert roles array
        if (strpos($content, "'kg_expert', 'author', 'editor'") !== false) {
            echo "   ✓ Expert roles defined correctly\n";
            $passed++;
        } else {
            echo "   ✗ Expert roles not properly defined\n";
            $failed++;
        }
        
        // Check for user meta fields
        $requiredMeta = ['_kg_biography', '_kg_expertise', '_kg_social_links', '_kg_show_email'];
        $allMetaFound = true;
        foreach ($requiredMeta as $meta) {
            if (strpos($content, $meta) === false) {
                $allMetaFound = false;
                break;
            }
        }
        if ($allMetaFound) {
            echo "   ✓ All required user meta fields included\n";
            $passed++;
        } else {
            echo "   ✗ Some user meta fields missing\n";
            $failed++;
        }
        
        // Check for get_expert_stats call
        if (strpos($content, 'get_expert_stats') !== false) {
            echo "   ✓ Stats retrieval implemented\n";
            $passed++;
        } else {
            echo "   ✗ Stats retrieval missing\n";
            $failed++;
        }
    } else {
        echo "   ✗ Method missing: get_experts_list()\n";
        $failed++;
    }
    
    // Check for get_expert_stats method
    if (strpos($content, 'function get_expert_stats') !== false) {
        echo "   ✓ Method exists: get_expert_stats()\n";
        $passed++;
        
        // Check for recipe count query
        if (strpos($content, "'post_type' => 'recipe'") !== false) {
            echo "   ✓ Recipe count query implemented\n";
            $passed++;
        } else {
            echo "   ✗ Recipe count query missing\n";
            $failed++;
        }
        
        // Check for post count query
        if (strpos($content, "'post_type' => 'post'") !== false) {
            echo "   ✓ Post count query implemented\n";
            $passed++;
        } else {
            echo "   ✗ Post count query missing\n";
            $failed++;
        }
        
        // Check for approved recipes query
        if (strpos($content, "'key' => '_kg_expert_id'") !== false) {
            echo "   ✓ Approved recipes query implemented\n";
            $passed++;
        } else {
            echo "   ✗ Approved recipes query missing\n";
            $failed++;
        }
    } else {
        echo "   ✗ Method missing: get_expert_stats()\n";
        $failed++;
    }
    
    // Check for public permission callback
    if (strpos($content, "'permission_callback' => '__return_true'") !== false) {
        echo "   ✓ Public endpoint permission set correctly\n";
        $passed++;
    } else {
        echo "   ✗ Public endpoint permission not set\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: ExpertController.php\n";
    $failed++;
}
echo "\n";

// Test 2: Check RoleManager for updated capabilities
echo "2. RoleManager - KG Expert Role Capabilities\n";
$roleManagerFile = $baseDir . '/includes/Roles/RoleManager.php';
if (file_exists($roleManagerFile)) {
    echo "   ✓ File exists: RoleManager.php\n";
    $content = file_get_contents($roleManagerFile);
    
    // Check for role removal before adding
    if (strpos($content, "remove_role( 'kg_expert' )") !== false) {
        echo "   ✓ Role removal implemented for updates\n";
        $passed++;
    } else {
        echo "   ✗ Role removal not implemented\n";
        $failed++;
    }
    
    // Check for Editor-like capabilities
    $editorCapabilities = [
        'edit_posts' => true,
        'edit_others_posts' => true,
        'edit_published_posts' => true,
        'publish_posts' => true,
        'delete_posts' => true,
        'delete_others_posts' => true,
        'delete_published_posts' => true,
        'delete_private_posts' => true,
        'edit_private_posts' => true,
        'read_private_posts' => true,
        'edit_pages' => true,
        'edit_others_pages' => true,
        'edit_published_pages' => true,
        'publish_pages' => true,
        'delete_pages' => true,
        'delete_others_pages' => true,
        'delete_published_pages' => true,
        'delete_private_pages' => true,
        'edit_private_pages' => true,
        'read_private_pages' => true,
        'moderate_comments' => true,
        'manage_categories' => true
    ];
    
    $missingCapabilities = [];
    foreach (array_keys($editorCapabilities) as $capability) {
        if (strpos($content, "'$capability' => true") === false) {
            $missingCapabilities[] = $capability;
        }
    }
    
    if (empty($missingCapabilities)) {
        echo "   ✓ All Editor capabilities added\n";
        $passed++;
    } else {
        echo "   ✗ Missing capabilities: " . implode(', ', $missingCapabilities) . "\n";
        $failed++;
    }
    
    // Check for custom KG capabilities
    $kgCapabilities = ['kg_answer_questions', 'kg_moderate_comments', 'kg_view_expert_dashboard'];
    $allKgCapsFound = true;
    foreach ($kgCapabilities as $capability) {
        if (strpos($content, "'$capability' => true") === false) {
            $allKgCapsFound = false;
            break;
        }
    }
    
    if ($allKgCapsFound) {
        echo "   ✓ Custom KG capabilities retained\n";
        $passed++;
    } else {
        echo "   ✗ Some custom KG capabilities missing\n";
        $failed++;
    }
    
    // Check for update_expert_capabilities method
    if (strpos($content, 'function update_expert_capabilities') !== false) {
        echo "   ✓ Method exists: update_expert_capabilities()\n";
        $passed++;
        
        // Check for user query with kg_expert role
        if (strpos($content, "'role' => 'kg_expert'") !== false) {
            echo "   ✓ User query for kg_expert role implemented\n";
            $passed++;
        } else {
            echo "   ✗ User query for kg_expert role missing\n";
            $failed++;
        }
        
        // Check for set_role call
        if (strpos($content, "set_role( 'kg_expert' )") !== false) {
            echo "   ✓ Role re-assignment implemented\n";
            $passed++;
        } else {
            echo "   ✗ Role re-assignment missing\n";
            $failed++;
        }
    } else {
        echo "   ✗ Method missing: update_expert_capabilities()\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: RoleManager.php\n";
    $failed++;
}
echo "\n";

// Test 3: Check kg-core.php for activation hook
echo "3. Plugin Activation Hook\n";
$pluginFile = $baseDir . '/kg-core.php';
if (file_exists($pluginFile)) {
    echo "   ✓ File exists: kg-core.php\n";
    $content = file_get_contents($pluginFile);
    
    // Check for activation hook
    if (strpos($content, 'register_activation_hook') !== false) {
        echo "   ✓ Activation hook registered\n";
        $passed++;
        
        // Check for role manager initialization
        if (strpos($content, 'RoleManager()') !== false) {
            echo "   ✓ RoleManager initialized in activation hook\n";
            $passed++;
        } else {
            echo "   ✗ RoleManager not initialized in activation hook\n";
            $failed++;
        }
        
        // Check for update_expert_capabilities call
        if (strpos($content, 'update_expert_capabilities()') !== false) {
            echo "   ✓ update_expert_capabilities() called in activation hook\n";
            $passed++;
        } else {
            echo "   ✗ update_expert_capabilities() not called in activation hook\n";
            $failed++;
        }
        
        // Check for flush_rewrite_rules
        if (strpos($content, 'flush_rewrite_rules()') !== false) {
            echo "   ✓ flush_rewrite_rules() called\n";
            $passed++;
        } else {
            echo "   ✗ flush_rewrite_rules() not called\n";
            $failed++;
        }
    } else {
        echo "   ✗ Activation hook not registered\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: kg-core.php\n";
    $failed++;
}
echo "\n";

// Test 4: Code quality checks
echo "4. Code Quality Checks\n";

// Check for consistent naming conventions
$allFiles = [
    $expertControllerFile,
    $roleManagerFile,
    $pluginFile
];

$qualityPassed = true;

foreach ($allFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Check for TODO or FIXME comments (shouldn't be in production code)
        if (preg_match('/\/\/\s*(TODO|FIXME)/i', $content)) {
            echo "   ⚠ Warning: Found TODO/FIXME comments in " . basename($file) . "\n";
            $qualityPassed = false;
        }
    }
}

if ($qualityPassed) {
    echo "   ✓ No TODO/FIXME comments found\n";
    $passed++;
} else {
    $failed++;
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "✓ All tests passed! Implementation is complete.\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}

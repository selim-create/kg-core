<?php
/**
 * Verification Script for User Management Bug Fixes
 * 
 * This script performs static analysis to verify all the required changes are in place.
 */

echo "=== VERIFYING USER MANAGEMENT BUG FIXES ===\n\n";

$errors = [];
$warnings = [];
$successes = [];

// ===== VERIFICATION 1: Login Username Support =====
echo "1. Checking login_user method for username support...\n";
$user_controller_content = file_get_contents(__DIR__ . '/includes/API/UserController.php');

if (strpos($user_controller_content, 'is_email( $email_or_username )') !== false) {
    $successes[] = "✅ Email check using is_email() found";
} else {
    $errors[] = "❌ Missing email check in login_user";
}

if (strpos($user_controller_content, 'wp_authenticate_username_password') !== false) {
    $successes[] = "✅ Username authentication using wp_authenticate_username_password() found";
} else {
    $errors[] = "❌ Missing username authentication in login_user";
}

if (strpos($user_controller_content, "Email/username and password are required") !== false) {
    $successes[] = "✅ Updated error message for email/username found";
} else {
    $warnings[] = "⚠️  Error message not updated to mention username";
}

if (preg_match("/\'role\'\s*=>\s*\!empty\(\s*\\\$roles\s*\)\s*\?\s*\\\$roles\[0\]\s*:\s*\'subscriber\'/", $user_controller_content)) {
    $successes[] = "✅ Login response includes 'role' field";
} else {
    $errors[] = "❌ Login response missing 'role' field";
}
echo "\n";

// ===== VERIFICATION 2: KVKK Validation Fix =====
echo "2. Checking add_child method for flexible KVKK validation...\n";

if (strpos($user_controller_content, "'on'") !== false && 
    strpos($user_controller_content, "kvkk_consent") !== false) {
    $successes[] = "✅ KVKK validation includes 'on' value";
} else {
    $errors[] = "❌ KVKK validation missing 'on' value support";
}

if (strpos($user_controller_content, "empty( \$kvkk_consent )") !== false) {
    $successes[] = "✅ KVKK validation checks for empty value";
} else {
    $errors[] = "❌ KVKK validation missing empty check";
}
echo "\n";

// ===== VERIFICATION 3: USER/ME Endpoint =====
echo "3. Checking get_user_me method for required fields...\n";

if (strpos($user_controller_content, "'name' => \$user->display_name") !== false) {
    $successes[] = "✅ /user/me response includes 'name' field";
} else {
    $errors[] = "❌ /user/me response missing 'name' field";
}

if (preg_match("/\'role\'\s*=>\s*\\\$primary_role.*get_user_me/s", $user_controller_content)) {
    $successes[] = "✅ /user/me response includes 'role' field";
} else {
    $errors[] = "❌ /user/me response missing 'role' field";
}
echo "\n";

// ===== VERIFICATION 4: AUTH/ME Endpoint =====
echo "4. Checking get_current_user method for complete data...\n";

// Extract the get_current_user method
if (preg_match("/public function get_current_user.*?^\s{4}\}/ms", $user_controller_content, $matches)) {
    $get_current_user_method = $matches[0];
    
    // Check for children
    if (strpos($get_current_user_method, "_kg_children") !== false) {
        $successes[] = "✅ /auth/me retrieves children data";
    } else {
        $errors[] = "❌ /auth/me missing children data retrieval";
    }
    
    // Check for avatar_url
    if (strpos($get_current_user_method, "'avatar_url'") !== false) {
        $successes[] = "✅ /auth/me response includes 'avatar_url'";
    } else {
        $errors[] = "❌ /auth/me response missing 'avatar_url'";
    }
    
    // Check for role
    if (strpos($get_current_user_method, "'role' => \$primary_role") !== false) {
        $successes[] = "✅ /auth/me response includes 'role' field";
    } else {
        $errors[] = "❌ /auth/me response missing 'role' field";
    }
    
    // Check for both user_id and id
    if (strpos($get_current_user_method, "'user_id' => \$user->ID") !== false &&
        strpos($get_current_user_method, "'id' => \$user->ID") !== false) {
        $successes[] = "✅ /auth/me response includes both 'user_id' and 'id'";
    } else {
        $errors[] = "❌ /auth/me response missing 'user_id' or 'id' field";
    }
    
    // Check for name field
    if (strpos($get_current_user_method, "'name' => \$user->display_name") !== false) {
        $successes[] = "✅ /auth/me response includes 'name' field";
    } else {
        $errors[] = "❌ /auth/me response missing 'name' field";
    }
    
    // Check for display_name
    if (strpos($get_current_user_method, "'display_name'") !== false) {
        $successes[] = "✅ /auth/me response includes 'display_name' field";
    } else {
        $errors[] = "❌ /auth/me response missing 'display_name' field";
    }
    
    // Check for parent_role
    if (strpos($get_current_user_method, "'parent_role'") !== false) {
        $successes[] = "✅ /auth/me response includes 'parent_role' field";
    } else {
        $errors[] = "❌ /auth/me response missing 'parent_role' field";
    }
    
    // Check for children in response
    if (strpos($get_current_user_method, "'children' => \$children") !== false) {
        $successes[] = "✅ /auth/me response includes 'children' field";
    } else {
        $errors[] = "❌ /auth/me response missing 'children' field";
    }
    
    // Check for created_at
    if (strpos($get_current_user_method, "'created_at'") !== false) {
        $successes[] = "✅ /auth/me response includes 'created_at' field";
    } else {
        $errors[] = "❌ /auth/me response missing 'created_at' field";
    }
} else {
    $errors[] = "❌ Could not find get_current_user method";
}
echo "\n";

// ===== VERIFICATION 5: Expert Dashboard =====
echo "5. Checking ExpertController implementation...\n";
$expert_controller_content = file_get_contents(__DIR__ . '/includes/API/ExpertController.php');

// Check for proper error handling
if (strpos($expert_controller_content, "new \\WP_Error( 'not_authenticated'") !== false) {
    $successes[] = "✅ ExpertController returns WP_Error for missing authentication";
} else {
    $errors[] = "❌ ExpertController missing WP_Error for authentication";
}

if (strpos($expert_controller_content, "new \\WP_Error( 'invalid_token'") !== false) {
    $successes[] = "✅ ExpertController returns WP_Error for invalid token";
} else {
    $errors[] = "❌ ExpertController missing WP_Error for invalid token";
}

if (strpos($expert_controller_content, "new \\WP_Error( 'user_not_found'") !== false) {
    $successes[] = "✅ ExpertController returns WP_Error for user not found";
} else {
    $errors[] = "❌ ExpertController missing WP_Error for user not found";
}

if (strpos($expert_controller_content, "new \\WP_Error( 'forbidden'") !== false) {
    $successes[] = "✅ ExpertController returns WP_Error for forbidden access";
} else {
    $errors[] = "❌ ExpertController missing WP_Error for forbidden access";
}

// Check for allowed roles
if (strpos($expert_controller_content, "[ 'administrator', 'editor', 'kg_expert' ]") !== false) {
    $successes[] = "✅ ExpertController checks for correct allowed roles";
} else {
    $errors[] = "❌ ExpertController missing allowed roles check";
}

// Check dashboard response structure
if (strpos($expert_controller_content, "'pending_questions'") !== false &&
    strpos($expert_controller_content, "'pending_comments'") !== false &&
    strpos($expert_controller_content, "'today_answers'") !== false &&
    strpos($expert_controller_content, "'weekly_stats'") !== false) {
    $successes[] = "✅ Expert dashboard returns all required fields";
} else {
    $errors[] = "❌ Expert dashboard missing required response fields";
}
echo "\n";

// ===== VERIFICATION 6: kg-core.php loads ExpertController =====
echo "6. Checking kg-core.php loads ExpertController...\n";
$kg_core_content = file_get_contents(__DIR__ . '/kg-core.php');

if (strpos($kg_core_content, "includes/API/ExpertController.php") !== false) {
    $successes[] = "✅ kg-core.php requires ExpertController.php";
} else {
    $errors[] = "❌ kg-core.php doesn't require ExpertController.php";
}

if (strpos($kg_core_content, "\\KG_Core\\API\\ExpertController") !== false) {
    $successes[] = "✅ kg-core.php instantiates ExpertController";
} else {
    $errors[] = "❌ kg-core.php doesn't instantiate ExpertController";
}
echo "\n";

// ===== SUMMARY =====
echo "=== VERIFICATION SUMMARY ===\n\n";

if (!empty($successes)) {
    echo "SUCCESSES (" . count($successes) . "):\n";
    foreach ($successes as $success) {
        echo "  $success\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "  $warning\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  $error\n";
    }
    echo "\n";
    exit(1);
}

echo "✅ ALL VERIFICATIONS PASSED!\n";
echo "\nTotal checks: " . count($successes) . " passed, " . count($warnings) . " warnings, " . count($errors) . " errors\n";
exit(0);

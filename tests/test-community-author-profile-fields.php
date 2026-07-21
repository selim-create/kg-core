<?php
/**
 * Static verification for community author profile fields and numeric public profile lookup.
 */

echo "=== Community Author/Profile Field Verification ===\n\n";

$baseDir = dirname(__DIR__);
$passed = 0;
$failed = 0;

$userControllerPath = $baseDir . '/includes/API/UserController.php';
$discussionControllerPath = $baseDir . '/includes/API/DiscussionController.php';

echo "1. UserController numeric ID support\n";
if ( file_exists( $userControllerPath ) ) {
    $content = file_get_contents( $userControllerPath );

    if ( strpos( $content, "is_numeric( \$username )" ) !== false && strpos( $content, "get_user_by( 'id', (int) \$username )" ) !== false ) {
        echo "   ✓ get_public_profile supports numeric IDs\n";
        $passed++;
    } else {
        echo "   ✗ Numeric ID lookup missing in get_public_profile\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: UserController.php\n";
    $failed++;
}

echo "\n2. DiscussionController author payload fields\n";
if ( file_exists( $discussionControllerPath ) ) {
    $content = file_get_contents( $discussionControllerPath );

    if ( strpos( $content, "'username' =>" ) !== false ) {
        echo "   ✓ username field present in author payload\n";
        $passed++;
    } else {
        echo "   ✗ username field missing in author payload\n";
        $failed++;
    }

    if ( strpos( $content, "'is_expert' =>" ) !== false ) {
        echo "   ✓ is_expert field present in author payload\n";
        $passed++;
    } else {
        echo "   ✗ is_expert field missing in author payload\n";
        $failed++;
    }

    if ( strpos( $content, "get_user_meta( \$user_id, 'is_expert', true )" ) !== false && strpos( $content, "RoleManager::is_expert( \$user_id )" ) !== false ) {
        echo "   ✓ expert detection covers role and is_expert meta\n";
        $passed++;
    } else {
        echo "   ✗ expert detection does not cover both role and meta\n";
        $failed++;
    }
} else {
    echo "   ✗ File not found: DiscussionController.php\n";
    $failed++;
}

echo "\n=== Test Results ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ( $failed === 0 ) {
    echo "\n✅ All checks passed!\n";
    exit( 0 );
}

echo "\n❌ Some checks failed.\n";
exit( 1 );

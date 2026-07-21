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

$extractMethod = function( $content, $method_signature, $next_signature = null ) {
    $start = strpos( $content, $method_signature );
    if ( $start === false ) {
        return '';
    }

    $end = $next_signature ? strpos( $content, $next_signature, $start + 1 ) : false;
    if ( $end === false ) {
        $end = strlen( $content );
    }

    return substr( $content, $start, $end - $start );
};

echo "1. UserController numeric ID support\n";
if ( file_exists( $userControllerPath ) ) {
    $content = file_get_contents( $userControllerPath );

    if ( preg_match( "/is_numeric\\s*\\(\\s*\\\$username\\s*\\)/", $content ) && preg_match( "/get_user_by\\s*\\(\\s*'id'\\s*,\\s*\\(int\\)\\s*\\\$username\\s*\\)/", $content ) ) {
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
    $comments_method = $extractMethod( $content, 'public function get_comments', 'private function prepare_discussion_response' );
    $prepare_method = $extractMethod( $content, 'private function prepare_discussion_response', 'public function get_top_contributors' );
    $expert_method = $extractMethod( $content, 'private function is_expert_user', 'public function vote_discussion' );

    if ( strpos( $comments_method, "'username' =>" ) !== false && strpos( $prepare_method, "'username' =>" ) !== false ) {
        echo "   ✓ username field present in discussion and comment author payloads\n";
        $passed++;
    } else {
        echo "   ✗ username field missing in discussion/comment author payload\n";
        $failed++;
    }

    if ( strpos( $comments_method, "'is_expert' =>" ) !== false && strpos( $prepare_method, "'is_expert' =>" ) !== false ) {
        echo "   ✓ is_expert field present in discussion and comment author payloads\n";
        $passed++;
    } else {
        echo "   ✗ is_expert field missing in discussion/comment author payload\n";
        $failed++;
    }

    $has_role_check = strpos( $expert_method, '$expert_roles = [ \'kg_expert\', \'author\', \'editor\', \'administrator\' ];' ) !== false
        || strpos( $expert_method, 'array_intersect( $expert_roles, $user->roles )' ) !== false;
    $has_meta_check = strpos( $expert_method, "get_user_meta( \$user_id, 'is_expert', true )" ) !== false;
    if ( $has_role_check && $has_meta_check ) {
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

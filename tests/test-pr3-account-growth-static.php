<?php
/**
 * Static verification for PR3 backend gaps:
 * - Apple auth code exchange + refresh token persistence
 * - Soft delete + cancel deletion + cleanup cron wiring
 * - Growth PUT/DELETE endpoints
 */

echo "=== PR3 Static Verification ===\n\n";

$base_path = dirname( __DIR__ );
$passed    = 0;
$failed    = 0;

$user_controller = $base_path . '/includes/API/UserController.php';
$apple_auth      = $base_path . '/includes/Auth/AppleAuth.php';
$growth_ctrl     = $base_path . '/includes/API/GrowthController.php';
$core_file       = $base_path . '/kg-core.php';

function assert_contains( $content, $needle, $label, &$passed, &$failed ) {
    if ( strpos( $content, $needle ) !== false ) {
        echo "✓ {$label}\n";
        $passed++;
    } else {
        echo "✗ {$label}\n";
        $failed++;
    }
}

foreach ( [ $user_controller, $apple_auth, $growth_ctrl, $core_file ] as $file ) {
    if ( ! file_exists( $file ) ) {
        echo "✗ Missing file: {$file}\n";
        $failed++;
        exit( 1 );
    }
}

$user_content   = file_get_contents( $user_controller );
$apple_content  = file_get_contents( $apple_auth );
$growth_content = file_get_contents( $growth_ctrl );
$core_content   = file_get_contents( $core_file );

echo "1) Apple auth refresh token flow\n";
assert_contains( $user_content, 'authorization_code', 'Apple route accepts authorization_code', $passed, $failed );
assert_contains( $user_content, 'exchange_authorization_code', 'apple_auth calls exchange_authorization_code', $passed, $failed );
assert_contains( $user_content, "update_user_meta( \$user->ID, 'apple_refresh_token'", 'apple_refresh_token persisted', $passed, $failed );
assert_contains( $apple_content, 'function exchange_authorization_code', 'AppleAuth::exchange_authorization_code exists', $passed, $failed );

echo "\n2) Soft delete + restore + cleanup\n";
assert_contains( $user_content, 'kg_account_deleted_at', 'Soft delete meta key exists', $passed, $failed );
assert_contains( $user_content, 'cancel_account_deletion', 'Cancel deletion endpoint handler exists', $passed, $failed );
assert_contains( $user_content, '/user/account/cancel-deletion', 'Cancel deletion route registered', $passed, $failed );
assert_contains( $user_content, 'check_soft_delete_status', 'Shared soft delete check exists', $passed, $failed );
assert_contains( $user_content, 'function hard_delete_user', 'Hard delete helper exists', $passed, $failed );
assert_contains( $user_content, 'kg_cleanup_deleted_accounts', 'Cleanup cron callback registered', $passed, $failed );
assert_contains( $core_content, "wp_schedule_event( time(), 'daily', 'kg_cleanup_deleted_accounts' )", 'Cron schedule exists', $passed, $failed );
assert_contains( $core_content, "wp_clear_scheduled_hook( 'kg_cleanup_deleted_accounts' )", 'Cron unschedule exists', $passed, $failed );

echo "\n3) Growth PUT/DELETE CRUD\n";
assert_contains( $growth_content, "'methods'             => 'PUT'", 'Growth PUT route registered', $passed, $failed );
assert_contains( $growth_content, "'methods'             => 'DELETE'", 'Growth DELETE route registered', $passed, $failed );
assert_contains( $growth_content, 'function update_growth_record', 'update_growth_record exists', $passed, $failed );
assert_contains( $growth_content, 'function delete_growth_record', 'delete_growth_record exists', $passed, $failed );

echo "\n4) Syntax checks\n";
foreach ( [ $user_controller, $apple_auth, $growth_ctrl, $core_file ] as $file ) {
    $output = [];
    $code   = 0;
    exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $output, $code );
    if ( 0 === $code ) {
        echo "✓ Syntax valid: " . basename( $file ) . "\n";
        $passed++;
    } else {
        echo "✗ Syntax error: " . basename( $file ) . "\n";
        echo implode( "\n", $output ) . "\n";
        $failed++;
    }
}

echo "\n=============================\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "=============================\n";

exit( $failed > 0 ? 1 : 0 );

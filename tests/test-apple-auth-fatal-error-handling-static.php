#!/usr/bin/env php
<?php
echo "=== Apple Auth Fatal Error Guard Test ===\n";

$base_path = dirname( __DIR__ );
$apple_auth_file = $base_path . '/includes/Auth/AppleAuth.php';
$user_controller_file = $base_path . '/includes/API/UserController.php';

if ( ! file_exists( $apple_auth_file ) || ! file_exists( $user_controller_file ) ) {
    echo "Missing required files.\n";
    exit( 1 );
}

$apple_auth_content = file_get_contents( $apple_auth_file );
$user_controller_content = file_get_contents( $user_controller_file );

$checks = [
    [ $apple_auth_content, 'ensure_jwt_dependencies', 'AppleAuth has dependency guard' ],
    [ $apple_auth_content, "class_exists( '\\Firebase\\JWT\\JWT' )", 'JWT dependency check exists' ],
    [ $apple_auth_content, "class_exists( '\\Firebase\\JWT\\JWK' )", 'JWK dependency check exists' ],
    [ $apple_auth_content, 'catch ( \\Throwable $e )', 'AppleAuth catches Throwable' ],
    [ $apple_auth_content, "new \\WP_Error(\n                'apple_internal_error'", 'AppleAuth returns internal error WP_Error' ],
    [ $user_controller_content, 'catch ( \\Throwable $e )', 'Apple endpoint catches Throwable' ],
    [ $user_controller_content, "'apple_auth_error'", 'Apple endpoint returns JSON WP_Error for fatal paths' ],
];

$failed = 0;
foreach ( $checks as $check ) {
    [ $haystack, $needle, $label ] = $check;
    if ( false !== strpos( $haystack, $needle ) ) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failed++;
    }
}

if ( $failed > 0 ) {
    echo "FAILED: {$failed} check(s).\n";
    exit( 1 );
}

echo "PASSED\n";

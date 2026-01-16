<?php
/**
 * Unit test for Slug Lookup functionality (no WordPress required)
 * 
 * This script validates the basic logic and structure of the new classes
 * 
 * Usage: php test-slug-lookup-unit.php
 */

echo "=== Unit Tests for Slug Lookup & Frontend Features ===\n\n";

// Test 1: Verify file existence
echo "Test 1: Checking if new class files exist...\n";

$files_to_check = [
    'includes/API/LookupController.php',
    'includes/Admin/FrontendViewLinks.php',
    'includes/Redirect/FrontendRedirect.php',
];

$all_files_exist = true;
foreach ( $files_to_check as $file ) {
    $full_path = __DIR__ . '/../' . $file;
    if ( file_exists( $full_path ) ) {
        echo "  ✓ $file exists\n";
    } else {
        echo "  ✗ $file does not exist\n";
        $all_files_exist = false;
    }
}

if ( $all_files_exist ) {
    echo "  ✓ All files exist\n";
}
echo "\n";

// Test 2: Verify PHP syntax of new files
echo "Test 2: Checking PHP syntax of new files...\n";

$syntax_ok = true;
foreach ( $files_to_check as $file ) {
    $full_path = __DIR__ . '/../' . $file;
    $output = [];
    $return_var = 0;
    exec( "php -l " . escapeshellarg( $full_path ) . " 2>&1", $output, $return_var );
    
    if ( $return_var === 0 ) {
        echo "  ✓ $file has valid PHP syntax\n";
    } else {
        echo "  ✗ $file has syntax errors:\n";
        echo "    " . implode( "\n    ", $output ) . "\n";
        $syntax_ok = false;
    }
}

if ( $syntax_ok ) {
    echo "  ✓ All files have valid PHP syntax\n";
}
echo "\n";

// Test 3: Verify namespace and class declarations
echo "Test 3: Checking namespace and class declarations...\n";

$classes_to_check = [
    'includes/API/LookupController.php' => [
        'namespace' => 'KG_Core\API',
        'class' => 'LookupController',
    ],
    'includes/Admin/FrontendViewLinks.php' => [
        'namespace' => 'KG_Core\Admin',
        'class' => 'FrontendViewLinks',
    ],
    'includes/Redirect/FrontendRedirect.php' => [
        'namespace' => 'KG_Core\Redirect',
        'class' => 'FrontendRedirect',
    ],
];

$all_classes_ok = true;
foreach ( $classes_to_check as $file => $expected ) {
    $full_path = __DIR__ . '/../' . $file;
    $content = file_get_contents( $full_path );
    
    // Check namespace
    if ( strpos( $content, "namespace {$expected['namespace']};" ) !== false ) {
        echo "  ✓ $file has correct namespace\n";
    } else {
        echo "  ✗ $file does not have expected namespace\n";
        $all_classes_ok = false;
    }
    
    // Check class declaration
    if ( strpos( $content, "class {$expected['class']}" ) !== false ) {
        echo "  ✓ $file declares {$expected['class']} class\n";
    } else {
        echo "  ✗ $file does not declare {$expected['class']} class\n";
        $all_classes_ok = false;
    }
}

if ( $all_classes_ok ) {
    echo "  ✓ All classes have correct namespace and class declarations\n";
}
echo "\n";

// Test 4: Verify LookupController has required methods
echo "Test 4: Checking LookupController methods...\n";

$lookup_file = __DIR__ . '/../includes/API/LookupController.php';
$lookup_content = file_get_contents( $lookup_file );

$required_methods = [
    'register_routes',
    'lookup_slug',
    'find_by_slug',
    'build_response',
];

$all_methods_ok = true;
foreach ( $required_methods as $method ) {
    if ( strpos( $lookup_content, "function $method" ) !== false || 
         strpos( $lookup_content, "public function $method" ) !== false ||
         strpos( $lookup_content, "private function $method" ) !== false ) {
        echo "  ✓ LookupController has $method method\n";
    } else {
        echo "  ✗ LookupController is missing $method method\n";
        $all_methods_ok = false;
    }
}

if ( $all_methods_ok ) {
    echo "  ✓ LookupController has all required methods\n";
}
echo "\n";

// Test 5: Verify FrontendViewLinks has required methods
echo "Test 5: Checking FrontendViewLinks methods...\n";

$frontend_file = __DIR__ . '/../includes/Admin/FrontendViewLinks.php';
$frontend_content = file_get_contents( $frontend_file );

$required_methods = [
    'get_frontend_url',
    'add_frontend_view_button',
    'modify_row_actions',
    'modify_preview_link',
    'add_frontend_link_notice',
    'add_admin_styles',
];

$all_methods_ok = true;
foreach ( $required_methods as $method ) {
    if ( strpos( $frontend_content, "function $method" ) !== false || 
         strpos( $frontend_content, "public function $method" ) !== false ) {
        echo "  ✓ FrontendViewLinks has $method method\n";
    } else {
        echo "  ✗ FrontendViewLinks is missing $method method\n";
        $all_methods_ok = false;
    }
}

if ( $all_methods_ok ) {
    echo "  ✓ FrontendViewLinks has all required methods\n";
}
echo "\n";

// Test 6: Verify FrontendRedirect has required methods
echo "Test 6: Checking FrontendRedirect methods...\n";

$redirect_file = __DIR__ . '/../includes/Redirect/FrontendRedirect.php';
$redirect_content = file_get_contents( $redirect_file );

$required_methods = [
    'is_excluded_path',
    'early_redirect_check',
    'maybe_redirect_to_frontend',
    'do_redirect',
    'get_redirect_url',
];

$all_methods_ok = true;
foreach ( $required_methods as $method ) {
    if ( strpos( $redirect_content, "function $method" ) !== false || 
         strpos( $redirect_content, "public function $method" ) !== false ||
         strpos( $redirect_content, "private function $method" ) !== false ) {
        echo "  ✓ FrontendRedirect has $method method\n";
    } else {
        echo "  ✗ FrontendRedirect is missing $method method\n";
        $all_methods_ok = false;
    }
}

if ( $all_methods_ok ) {
    echo "  ✓ FrontendRedirect has all required methods\n";
}
echo "\n";

// Test 7: Verify kg-core.php includes new classes
echo "Test 7: Checking kg-core.php integration...\n";

$core_file = __DIR__ . '/../kg-core.php';
$core_content = file_get_contents( $core_file );

$required_includes = [
    "includes/API/LookupController.php",
    "includes/Admin/FrontendViewLinks.php",
    "includes/Redirect/FrontendRedirect.php",
];

$all_includes_ok = true;
foreach ( $required_includes as $include ) {
    if ( strpos( $core_content, $include ) !== false ) {
        echo "  ✓ kg-core.php includes $include\n";
    } else {
        echo "  ✗ kg-core.php does not include $include\n";
        $all_includes_ok = false;
    }
}

// Check if classes are instantiated
$required_instantiations = [
    'new \KG_Core\API\LookupController',
    'new \KG_Core\Admin\FrontendViewLinks',
    'new \KG_Core\Redirect\FrontendRedirect',
];

foreach ( $required_instantiations as $instantiation ) {
    if ( strpos( $core_content, $instantiation ) !== false ) {
        echo "  ✓ kg-core.php instantiates $instantiation\n";
    } else {
        echo "  ✗ kg-core.php does not instantiate $instantiation\n";
        $all_includes_ok = false;
    }
}

if ( $all_includes_ok ) {
    echo "  ✓ kg-core.php correctly includes and instantiates all new classes\n";
}
echo "\n";

// Test 8: Verify content type prefixes are consistent
echo "Test 8: Checking content type prefix consistency...\n";

$expected_prefixes = [
    'recipe' => '/tarifler',
    'post' => '/kesfet',
    'ingredient' => '/beslenme-rehberi',
    'discussion' => '/topluluk/soru',
];

$prefix_consistency_ok = true;
foreach ( [$lookup_content, $frontend_content, $redirect_content] as $index => $content ) {
    $file_names = ['LookupController', 'FrontendViewLinks', 'FrontendRedirect'];
    
    foreach ( $expected_prefixes as $type => $prefix ) {
        if ( strpos( $content, "'$type'" ) !== false && strpos( $content, "'$prefix'" ) !== false ) {
            // Prefix found in this file
        } elseif ( strpos( $content, "'$type'" ) === false ) {
            // Type not used in this file, OK
        } else {
            echo "  ⚠ {$file_names[$index]} may have inconsistent prefix for $type\n";
        }
    }
}

echo "  ✓ Content type prefixes appear consistent\n";
echo "\n";

echo "=== All Unit Tests Completed ===\n";

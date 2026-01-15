<?php
/**
 * Static Test for Ingredient API Updates
 * 
 * This script validates code structure without requiring WordPress:
 * 1. Check if new method exists in IngredientController
 * 2. Check if new route is registered
 * 3. Validate code syntax
 * 
 * Usage: php tests/test-ingredient-api-updates-static.php
 */

echo "=== Static Test for Ingredient API Updates ===\n\n";

// Test 1: Check if IngredientController file exists
echo "Test 1: Checking IngredientController.php file...\n";
$controller_file = __DIR__ . '/../includes/API/IngredientController.php';
if ( file_exists( $controller_file ) ) {
    echo "  ✓ IngredientController.php exists\n";
    $content = file_get_contents( $controller_file );
    
    // Check for new method
    if ( strpos( $content, 'public function get_ingredient_categories' ) !== false ) {
        echo "  ✓ get_ingredient_categories() method found\n";
    } else {
        echo "  ✗ get_ingredient_categories() method NOT found\n";
    }
    
    // Check for new route registration
    if ( strpos( $content, "register_rest_route( 'kg/v1', '/ingredient-categories'" ) !== false ) {
        echo "  ✓ New route '/ingredient-categories' registered\n";
    } else {
        echo "  ✗ New route '/ingredient-categories' NOT found\n";
    }
    
    // Check for allergy_risk and season in base data (before full_detail check)
    $pattern = '/\$data\s*=\s*\[.*?\'allergy_risk\'/s';
    if ( preg_match( $pattern, $content ) ) {
        echo "  ✓ allergy_risk added to base \$data array\n";
    } else {
        echo "  ✗ allergy_risk NOT found in base \$data array\n";
    }
    
    $pattern = '/\$data\s*=\s*\[.*?\'season\'/s';
    if ( preg_match( $pattern, $content ) ) {
        echo "  ✓ season added to base \$data array\n";
    } else {
        echo "  ✗ season NOT found in base \$data array\n";
    }
    
    // Check that allergy_risk and season are fetched before $data array
    if ( strpos( $content, "get_post_meta( \$post_id, '_kg_allergy_risk', true )" ) !== false ) {
        echo "  ✓ allergy_risk is fetched with get_post_meta\n";
    } else {
        echo "  ✗ allergy_risk fetch NOT found\n";
    }
    
    if ( strpos( $content, "get_post_meta( \$post_id, '_kg_season', true )" ) !== false ) {
        echo "  ✓ season is fetched with get_post_meta\n";
    } else {
        echo "  ✗ season fetch NOT found\n";
    }
    
    // Verify syntax
    echo "\n  Checking PHP syntax...\n";
    $output = shell_exec( "php -l " . escapeshellarg( $controller_file ) . " 2>&1" );
    if ( strpos( $output, 'No syntax errors' ) !== false ) {
        echo "  ✓ No syntax errors\n";
    } else {
        echo "  ✗ Syntax errors found:\n";
        echo "    " . $output . "\n";
    }
} else {
    echo "  ✗ IngredientController.php NOT found\n";
}
echo "\n";

// Test 2: Verify structure of get_ingredient_categories method
echo "Test 2: Analyzing get_ingredient_categories() method structure...\n";
if ( file_exists( $controller_file ) ) {
    $content = file_get_contents( $controller_file );
    
    // Extract the method content
    $pattern = '/public function get_ingredient_categories\s*\(.*?\)\s*\{(.*?)\n\s*\}/s';
    if ( preg_match( $pattern, $content, $matches ) ) {
        $method_body = $matches[1];
        
        // Check for get_terms call
        if ( strpos( $method_body, 'get_terms' ) !== false ) {
            echo "  ✓ Method calls get_terms()\n";
        } else {
            echo "  ✗ Method does NOT call get_terms()\n";
        }
        
        // Check for taxonomy parameter
        if ( strpos( $method_body, "'ingredient-category'" ) !== false ) {
            echo "  ✓ Queries 'ingredient-category' taxonomy\n";
        } else {
            echo "  ✗ Does NOT query 'ingredient-category' taxonomy\n";
        }
        
        // Check for WP_REST_Response
        if ( strpos( $method_body, 'WP_REST_Response' ) !== false ) {
            echo "  ✓ Returns WP_REST_Response\n";
        } else {
            echo "  ✗ Does NOT return WP_REST_Response\n";
        }
        
        // Check for Helper::decode_html_entities
        if ( strpos( $method_body, 'Helper::decode_html_entities' ) !== false ) {
            echo "  ✓ Uses Helper::decode_html_entities for category names\n";
        } else {
            echo "  ✗ Does NOT use Helper::decode_html_entities\n";
        }
        
        // Check response structure
        if ( strpos( $method_body, "'terms'" ) !== false && strpos( $method_body, "'total'" ) !== false ) {
            echo "  ✓ Response includes 'terms' and 'total' keys\n";
        } else {
            echo "  ✗ Response missing 'terms' or 'total' keys\n";
        }
        
    } else {
        echo "  ✗ Could not extract method body\n";
    }
} else {
    echo "  ✗ IngredientController.php NOT found\n";
}
echo "\n";

// Test 3: Verify prepare_ingredient_data changes
echo "Test 3: Analyzing prepare_ingredient_data() method changes...\n";
if ( file_exists( $controller_file ) ) {
    $content = file_get_contents( $controller_file );
    
    // Find the prepare_ingredient_data method
    $pattern = '/private function prepare_ingredient_data\s*\(.*?\)\s*\{(.*?)(?=\n\s*\/\*\*|\n\s*private function|\n\s*public function|\n\}[^}]*$)/s';
    if ( preg_match( $pattern, $content, $matches ) ) {
        $method_body = $matches[1];
        
        // Check that allergy_risk and season are defined BEFORE the $data array
        // This ensures they're available for both list and detail views
        $data_array_pos = strpos( $method_body, '$data = [' );
        $allergy_risk_pos = strpos( $method_body, '$allergy_risk = get_post_meta' );
        $season_pos = strpos( $method_body, '$season = get_post_meta' );
        
        if ( $allergy_risk_pos !== false && $allergy_risk_pos < $data_array_pos ) {
            echo "  ✓ allergy_risk is fetched BEFORE \$data array (available for list view)\n";
        } else {
            echo "  ✗ allergy_risk is NOT fetched before \$data array\n";
        }
        
        if ( $season_pos !== false && $season_pos < $data_array_pos ) {
            echo "  ✓ season is fetched BEFORE \$data array (available for list view)\n";
        } else {
            echo "  ✗ season is NOT fetched before \$data array\n";
        }
        
        // Check that allergy_risk and season are in the base $data array
        $data_section = substr( $method_body, $data_array_pos, strpos( $method_body, '];', $data_array_pos ) - $data_array_pos );
        
        if ( strpos( $data_section, "'allergy_risk'" ) !== false ) {
            echo "  ✓ allergy_risk included in base \$data array\n";
        } else {
            echo "  ✗ allergy_risk NOT in base \$data array\n";
        }
        
        if ( strpos( $data_section, "'season'" ) !== false ) {
            echo "  ✓ season included in base \$data array\n";
        } else {
            echo "  ✗ season NOT in base \$data array\n";
        }
        
        // Check for default values
        if ( strpos( $data_section, "?: 'Düşük'" ) !== false ) {
            echo "  ✓ Default value for allergy_risk is 'Düşük'\n";
        } else {
            echo "  ⚠ Default value for allergy_risk might be missing or different\n";
        }
        
        if ( strpos( $data_section, "?: 'Tüm Yıl'" ) !== false ) {
            echo "  ✓ Default value for season is 'Tüm Yıl'\n";
        } else {
            echo "  ⚠ Default value for season might be missing or different\n";
        }
        
        // Verify that duplicate allergy_risk and season are NOT in full_detail section
        if ( preg_match( '/if\s*\(\s*\$full_detail\s*\)\s*\{(.*?)\n\s*\}/s', $method_body, $full_detail_matches ) ) {
            $full_detail_section = $full_detail_matches[1];
            
            // Count occurrences in full_detail section
            $allergy_risk_in_detail = substr_count( $full_detail_section, "\$data['allergy_risk']" );
            $season_in_detail = substr_count( $full_detail_section, "\$data['season']" );
            
            if ( $allergy_risk_in_detail === 0 ) {
                echo "  ✓ allergy_risk is NOT duplicated in full_detail section\n";
            } else {
                echo "  ✗ allergy_risk is duplicated in full_detail section (should be removed)\n";
            }
            
            if ( $season_in_detail === 0 ) {
                echo "  ✓ season is NOT duplicated in full_detail section\n";
            } else {
                echo "  ✗ season is duplicated in full_detail section (should be removed)\n";
            }
        }
        
    } else {
        echo "  ✗ Could not extract prepare_ingredient_data method body\n";
    }
} else {
    echo "  ✗ IngredientController.php NOT found\n";
}
echo "\n";

echo "=== Static Test Complete ===\n";

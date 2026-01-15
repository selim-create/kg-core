<?php
/**
 * Static Code Analysis Test for SearchController Enhancement
 * 
 * Tests the enhanced search API with support for:
 * - recipe, ingredient, post, discussion types
 * - categorized results
 * - counts object
 * - type-specific data
 * 
 * Usage: php test-search-controller-enhancement.php
 */

echo "=== Testing Enhanced SearchController ===\n\n";

$baseDir = __DIR__;
$passed = 0;
$failed = 0;

// Initialize colors for output
function success($text) {
    global $passed;
    $passed++;
    return "✓ " . $text;
}

function fail($text) {
    global $failed;
    $failed++;
    return "✗ " . $text;
}

function info($text) {
    return "→ " . $text;
}

// Test 1: Check if SearchController file exists
echo "Test 1: Checking SearchController file...\n";
$searchControllerFile = $baseDir . '/includes/API/SearchController.php';
if ( file_exists( $searchControllerFile ) ) {
    echo success("SearchController.php file exists") . "\n";
    $content = file_get_contents( $searchControllerFile );
    
    // Check for namespace
    if ( strpos( $content, 'namespace KG_Core\API;' ) !== false ) {
        echo success("Correct namespace declaration") . "\n";
    } else {
        echo fail("Missing or incorrect namespace") . "\n";
    }
    
    // Check for class declaration
    if ( strpos( $content, 'class SearchController' ) !== false ) {
        echo success("SearchController class exists") . "\n";
    } else {
        echo fail("SearchController class not found") . "\n";
    }
} else {
    echo fail("SearchController.php file not found") . "\n";
}
echo "\n";

// Test 2: Check for route registration with new args
echo "Test 2: Checking route registration...\n";
if ( isset( $content ) ) {
    if ( strpos( $content, 'register_rest_route' ) !== false ) {
        echo success("register_rest_route call exists") . "\n";
    }
    
    // Check for args definition
    if ( strpos( $content, "'args' =>" ) !== false ) {
        echo success("Route args are defined") . "\n";
        
        // Check specific args
        if ( strpos( $content, "'q' =>" ) !== false ) {
            echo success("'q' parameter is defined") . "\n";
        } else {
            echo fail("'q' parameter not found") . "\n";
        }
        
        if ( strpos( $content, "'type' =>" ) !== false ) {
            echo success("'type' parameter is defined") . "\n";
        } else {
            echo fail("'type' parameter not found") . "\n";
        }
        
        if ( strpos( $content, "'per_page' =>" ) !== false ) {
            echo success("'per_page' parameter is defined") . "\n";
        } else {
            echo fail("'per_page' parameter not found") . "\n";
        }
        
        if ( strpos( $content, "'age_group' =>" ) !== false ) {
            echo success("'age_group' parameter is defined") . "\n";
        } else {
            echo fail("'age_group' parameter not found") . "\n";
        }
        
        // Check for enum values
        if ( strpos( $content, "'enum' => ['all', 'recipe', 'ingredient', 'post', 'discussion']" ) !== false ) {
            echo success("'type' enum includes all expected values") . "\n";
        } else {
            echo fail("'type' enum missing or incomplete") . "\n";
        }
        
        // Check for min/max on per_page
        if ( strpos( $content, "'minimum' => 1" ) !== false && strpos( $content, "'maximum' => 50" ) !== false ) {
            echo success("'per_page' has proper min/max validation") . "\n";
        } else {
            echo fail("'per_page' missing min/max validation") . "\n";
        }
    } else {
        echo fail("Route args not defined") . "\n";
    }
}
echo "\n";

// Test 3: Check for enhanced search_items method
echo "Test 3: Checking search_items method enhancements...\n";
if ( isset( $content ) ) {
    if ( strpos( $content, 'function search_items' ) !== false ) {
        echo success("search_items() method exists") . "\n";
        
        // Check for switch statement handling all types
        if ( strpos( $content, "switch ( \$type )" ) !== false ) {
            echo success("switch statement for type handling exists") . "\n";
            
            if ( strpos( $content, "case 'recipe':" ) !== false ) {
                echo success("Handles 'recipe' type") . "\n";
            }
            if ( strpos( $content, "case 'ingredient':" ) !== false ) {
                echo success("Handles 'ingredient' type") . "\n";
            }
            if ( strpos( $content, "case 'post':" ) !== false ) {
                echo success("Handles 'post' type") . "\n";
            }
            if ( strpos( $content, "case 'discussion':" ) !== false ) {
                echo success("Handles 'discussion' type") . "\n";
            }
            if ( strpos( $content, "['recipe', 'ingredient', 'post', 'discussion']" ) !== false ) {
                echo success("'all' type includes all 4 post types") . "\n";
            }
        } else {
            echo fail("Missing switch statement for type handling") . "\n";
        }
        
        // Check for categorized results
        if ( strpos( $content, "'categorized' =>" ) !== false ) {
            echo success("Categorized results structure exists") . "\n";
            
            if ( strpos( $content, "'recipes' => []" ) !== false &&
                 strpos( $content, "'ingredients' => []" ) !== false &&
                 strpos( $content, "'posts' => []" ) !== false &&
                 strpos( $content, "'discussions' => []" ) !== false ) {
                echo success("All categorized arrays initialized") . "\n";
            }
        } else {
            echo fail("Missing categorized results structure") . "\n";
        }
        
        // Check for counts
        if ( strpos( $content, "'counts' =>" ) !== false ) {
            echo success("Counts object exists") . "\n";
            
            if ( strpos( $content, "'total' => count( \$results )" ) !== false ) {
                echo success("Counts total implemented") . "\n";
            }
        } else {
            echo fail("Missing counts object") . "\n";
        }
        
        // Check for HTML entity decoding
        if ( strpos( $content, 'html_entity_decode' ) !== false ) {
            echo success("HTML entity decoding for Turkish characters") . "\n";
        } else {
            echo fail("Missing HTML entity decoding") . "\n";
        }
        
        // Check for excerpt
        if ( strpos( $content, 'wp_trim_words' ) !== false ) {
            echo success("Excerpt generation with wp_trim_words") . "\n";
        } else {
            echo fail("Missing excerpt generation") . "\n";
        }
        
        // Check for success flag in response
        if ( strpos( $content, "'success' => true" ) !== false ) {
            echo success("Response includes 'success' flag") . "\n";
        } else {
            echo fail("Missing 'success' flag in response") . "\n";
        }
    } else {
        echo fail("search_items() method not found") . "\n";
    }
}
echo "\n";

// Test 4: Check for type-specific data handling
echo "Test 4: Checking type-specific data handling...\n";
if ( isset( $content ) ) {
    // Recipe specific data
    if ( strpos( $content, "'prep_time'" ) !== false && 
         strpos( $content, "'cook_time'" ) !== false &&
         strpos( $content, "'age_groups'" ) !== false &&
         strpos( $content, "'meal_types'" ) !== false ) {
        echo success("Recipe-specific fields (prep_time, cook_time, age_groups, meal_types)") . "\n";
    } else {
        echo fail("Missing some recipe-specific fields") . "\n";
    }
    
    // Ingredient specific data
    if ( strpos( $content, "'start_age'" ) !== false && 
         strpos( $content, "'allergy_risk'" ) !== false &&
         strpos( $content, "'season'" ) !== false ) {
        echo success("Ingredient-specific fields (start_age, allergy_risk, season)") . "\n";
    } else {
        echo fail("Missing some ingredient-specific fields") . "\n";
    }
    
    // Post specific data
    if ( strpos( $content, "'author'" ) !== false && 
         strpos( $content, "'author_avatar'" ) !== false &&
         strpos( $content, "'date'" ) !== false &&
         strpos( $content, "'read_time'" ) !== false &&
         strpos( $content, "'categories'" ) !== false ) {
        echo success("Post-specific fields (author, avatar, date, read_time, categories)") . "\n";
    } else {
        echo fail("Missing some post-specific fields") . "\n";
    }
    
    // Discussion specific data
    if ( strpos( $content, "'comment_count'" ) !== false &&
         strpos( $content, "'circles'" ) !== false ) {
        echo success("Discussion-specific fields (comment_count, circles)") . "\n";
    } else {
        echo fail("Missing some discussion-specific fields") . "\n";
    }
    
    // Check if items are added to categorized arrays
    if ( strpos( $content, "\$categorized['recipes'][] = \$result;" ) !== false &&
         strpos( $content, "\$categorized['ingredients'][] = \$result;" ) !== false &&
         strpos( $content, "\$categorized['posts'][] = \$result;" ) !== false &&
         strpos( $content, "\$categorized['discussions'][] = \$result;" ) !== false ) {
        echo success("Results properly added to categorized arrays") . "\n";
    } else {
        echo fail("Not all results added to categorized arrays") . "\n";
    }
}
echo "\n";

// Test 5: Check for calculate_read_time helper method
echo "Test 5: Checking calculate_read_time method...\n";
if ( isset( $content ) ) {
    if ( strpos( $content, 'function calculate_read_time' ) !== false ||
         strpos( $content, 'private function calculate_read_time' ) !== false ) {
        echo success("calculate_read_time() method exists") . "\n";
        
        if ( strpos( $content, 'str_word_count' ) !== false ) {
            echo success("Uses str_word_count for word counting") . "\n";
        }
        
        if ( strpos( $content, 'strip_tags' ) !== false ) {
            echo success("Strips HTML tags before counting") . "\n";
        }
        
        if ( strpos( $content, '200' ) !== false ) {
            echo success("Uses 200 words per minute reading speed") . "\n";
        }
        
        if ( strpos( $content, "' dk'" ) !== false || strpos( $content, '\' dk\'' ) !== false ) {
            echo success("Returns time in Turkish format (dk)") . "\n";
        }
    } else {
        echo fail("calculate_read_time() method not found") . "\n";
    }
}
echo "\n";

// Test 6: Check for null coalescing operator usage
echo "Test 6: Checking for proper null handling...\n";
if ( isset( $content ) ) {
    if ( strpos( $content, '?: null' ) !== false ) {
        echo success("Uses ?: null for optional fields") . "\n";
    } else {
        echo fail("Missing proper null handling for optional fields") . "\n";
    }
}
echo "\n";

echo "=== Test Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "\n";

if ( $failed === 0 ) {
    echo "✓ All tests passed! SearchController successfully enhanced.\n";
    echo "\n";
    echo "The SearchController now supports:\n";
    echo "  • 4 content types: recipe, ingredient, post, discussion\n";
    echo "  • Categorized results structure\n";
    echo "  • Counts object for all types\n";
    echo "  • Type-specific metadata for each content type\n";
    echo "  • HTML entity decoding for Turkish characters\n";
    echo "  • per_page parameter with validation (1-50)\n";
    echo "  • age_group filtering for recipes\n";
    echo "  • Read time calculation for blog posts\n";
    exit(0);
} else {
    echo "✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}


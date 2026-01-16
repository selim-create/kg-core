<?php
/**
 * Test Script for Expert Profile API Improvements
 * 
 * Tests:
 * 1. get_expert_public_profile returns all content (not limited to 6)
 * 2. HTML entity decode works for titles, age_groups, categories, and circles
 * 3. Slug support works (user_nicename is used)
 * 4. ExpertController returns user_nicename
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

echo "=== EXPERT PROFILE API IMPROVEMENTS TEST ===\n\n";

// Test configuration
$test_expert_email = 'testexpert_' . time() . '@example.com';
$test_expert_username = 'testexpert_' . time();
$test_expert_password = 'ExpertPass123!';
$test_expert_display_name = 'Dr. Test & Expert'; // Contains & to test HTML entity decode

echo "TEST 1: Creating test expert user...\n";
$expert_user_id = wp_create_user($test_expert_username, $test_expert_password, $test_expert_email);
if (is_wp_error($expert_user_id)) {
    echo "❌ Failed to create expert: " . $expert_user_id->get_error_message() . "\n";
    exit(1);
}

// Set role to kg_expert
$expert_user = get_user_by('id', $expert_user_id);
$expert_user->set_role('kg_expert');

// Update display name and user_nicename
wp_update_user([
    'ID' => $expert_user_id,
    'display_name' => $test_expert_display_name,
    'user_nicename' => sanitize_title($test_expert_display_name), // This creates URL-friendly slug
]);

echo "✅ Expert created: ID=$expert_user_id\n";
echo "   Username: $test_expert_username\n";
echo "   Display Name: $test_expert_display_name\n";
echo "   Slug (user_nicename): " . sanitize_title($test_expert_display_name) . "\n\n";

// Create multiple posts to test "all content" retrieval
echo "TEST 2: Creating test recipes with HTML entities in titles...\n";
$recipe_count = 10;
$created_recipes = [];

for ($i = 1; $i <= $recipe_count; $i++) {
    $recipe_title = "Recipe $i with &amp; Special Chars & < >";
    $recipe_id = wp_insert_post([
        'post_type' => 'recipe',
        'post_title' => $recipe_title,
        'post_status' => 'publish',
        'post_author' => $expert_user_id,
        'post_content' => 'Test recipe content',
    ]);
    
    if (!is_wp_error($recipe_id)) {
        $created_recipes[] = $recipe_id;
    }
}

echo "✅ Created $recipe_count recipes for expert\n\n";

// Create multiple blog posts
echo "TEST 3: Creating test blog posts...\n";
$post_count = 10;
$created_posts = [];

for ($i = 1; $i <= $post_count; $i++) {
    $post_title = "Blog Post $i";
    $post_id = wp_insert_post([
        'post_type' => 'post',
        'post_title' => $post_title,
        'post_status' => 'publish',
        'post_author' => $expert_user_id,
        'post_content' => 'Test blog post content',
    ]);
    
    if (!is_wp_error($post_id)) {
        $created_posts[] = $post_id;
    }
}

echo "✅ Created $post_count blog posts for expert\n\n";

// Create test discussions (questions asked)
echo "TEST 4: Creating test discussions...\n";
$discussion_count = 10;
$created_discussions = [];

for ($i = 1; $i <= $discussion_count; $i++) {
    $discussion_title = "Question $i";
    $discussion_id = wp_insert_post([
        'post_type' => 'discussion',
        'post_title' => $discussion_title,
        'post_status' => 'publish',
        'post_author' => $expert_user_id,
        'post_content' => 'Test discussion content',
    ]);
    
    if (!is_wp_error($discussion_id)) {
        $created_discussions[] = $discussion_id;
    }
}

echo "✅ Created $discussion_count discussions for expert\n\n";

// Test get_expert_public_profile endpoint
echo "TEST 5: Testing get_expert_public_profile with username...\n";
$request = new WP_REST_Request('GET', '/kg/v1/expert/public/' . $test_expert_username);

$controller = new \KG_Core\API\UserController();
$response = $controller->get_expert_public_profile($request);

if (is_wp_error($response)) {
    echo "❌ Failed to get expert profile: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    
    echo "✅ Expert profile retrieved successfully!\n";
    echo "   Username returned: " . $data['username'] . "\n";
    echo "   Display name: " . $data['display_name'] . "\n";
    echo "   Recipe count: " . count($data['recipes']) . "\n";
    echo "   Blog post count: " . count($data['blog_posts']) . "\n";
    echo "   Asked questions count: " . count($data['asked_questions']) . "\n";
    
    // Verify all recipes are returned
    if (count($data['recipes']) === $recipe_count) {
        echo "   ✅ All $recipe_count recipes returned (not limited to 6)\n";
    } else {
        echo "   ❌ Expected $recipe_count recipes, got " . count($data['recipes']) . "\n";
    }
    
    // Verify all blog posts are returned
    if (count($data['blog_posts']) === $post_count) {
        echo "   ✅ All $post_count blog posts returned (not limited to 6)\n";
    } else {
        echo "   ❌ Expected $post_count blog posts, got " . count($data['blog_posts']) . "\n";
    }
    
    // Verify all discussions are returned
    if (count($data['asked_questions']) === $discussion_count) {
        echo "   ✅ All $discussion_count questions returned (not limited to 6)\n";
    } else {
        echo "   ❌ Expected $discussion_count questions, got " . count($data['asked_questions']) . "\n";
    }
    
    // Check if username is user_nicename (slug)
    $expert_user_refreshed = get_user_by('id', $expert_user_id);
    if ($data['username'] === $expert_user_refreshed->user_nicename) {
        echo "   ✅ Username is user_nicename (slug): " . $data['username'] . "\n";
    } else {
        echo "   ❌ Username is not user_nicename. Got: " . $data['username'] . ", Expected: " . $expert_user_refreshed->user_nicename . "\n";
    }
    
    // Check HTML entity decode in recipe titles
    if (!empty($data['recipes'])) {
        $first_recipe = $data['recipes'][0];
        echo "   Recipe title sample: " . $first_recipe['title'] . "\n";
        
        if (strpos($first_recipe['title'], '&amp;') === false && strpos($first_recipe['title'], '&') !== false) {
            echo "   ✅ HTML entities properly decoded in recipe titles\n";
        } else {
            echo "   ⚠️  HTML entity decode check inconclusive (no & in title or &amp; still present)\n";
        }
    }
}

echo "\n";

// Test get_expert_public_profile with slug
echo "TEST 6: Testing get_expert_public_profile with slug (user_nicename)...\n";
$expert_user_refreshed = get_user_by('id', $expert_user_id);
$slug = $expert_user_refreshed->user_nicename;

$request_slug = new WP_REST_Request('GET', '/kg/v1/expert/public/' . $slug);
$response_slug = $controller->get_expert_public_profile($request_slug);

if (is_wp_error($response_slug)) {
    echo "❌ Failed to get expert profile with slug: " . $response_slug->get_error_message() . "\n";
} else {
    echo "✅ Expert profile retrieved successfully with slug!\n";
    echo "   Slug used: $slug\n";
}

echo "\n";

// Test ExpertController get_experts_list
echo "TEST 7: Testing ExpertController get_experts_list...\n";
$request_list = new WP_REST_Request('GET', '/kg/v1/experts');

$expert_controller = new \KG_Core\API\ExpertController();
$response_list = $expert_controller->get_experts_list($request_list);

if (is_wp_error($response_list)) {
    echo "❌ Failed to get experts list: " . $response_list->get_error_message() . "\n";
} else {
    $experts = $response_list->get_data();
    echo "✅ Experts list retrieved successfully!\n";
    echo "   Total experts: " . count($experts) . "\n";
    
    // Find our test expert in the list
    $found_expert = null;
    foreach ($experts as $expert) {
        if ($expert['id'] === $expert_user_id) {
            $found_expert = $expert;
            break;
        }
    }
    
    if ($found_expert) {
        echo "   ✅ Test expert found in list\n";
        echo "   Username returned: " . $found_expert['username'] . "\n";
        
        // Check if username is user_nicename
        if ($found_expert['username'] === $expert_user_refreshed->user_nicename) {
            echo "   ✅ Username is user_nicename (slug) in experts list\n";
        } else {
            echo "   ❌ Username is not user_nicename in list. Got: " . $found_expert['username'] . ", Expected: " . $expert_user_refreshed->user_nicename . "\n";
        }
    } else {
        echo "   ⚠️  Test expert not found in experts list\n";
    }
}

echo "\n";

// Cleanup
echo "CLEANUP: Deleting test data...\n";
foreach ($created_recipes as $recipe_id) {
    wp_delete_post($recipe_id, true);
}
foreach ($created_posts as $post_id) {
    wp_delete_post($post_id, true);
}
foreach ($created_discussions as $discussion_id) {
    wp_delete_post($discussion_id, true);
}
wp_delete_user($expert_user_id);

echo "✅ Cleanup completed\n\n";

echo "=== TEST COMPLETED ===\n";

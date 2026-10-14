<?php
/**
 * WordPress integration tests for the advisory publication guard.
 *
 * This script runs in a disposable WordPress installation:
 *   wp eval-file /absolute/path/to/tests/test-recipe-publication-wordpress.php
 *
 * Only fake recipe records in the disposable database are created.
 */
require_once dirname(__DIR__) . '/includes/Services/RecipePublicationGuard.php';

use KG_Core\Services\RecipePublicationGuard;

function kg_test_expect($condition, $message) {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    WP_CLI::log('PASS: ' . $message);
}

register_post_type('recipe', [
    'public' => true,
    'show_in_rest' => true,
    'rest_base' => 'recipes',
    'supports' => ['title', 'editor', 'custom-fields'],
    'capability_type' => 'post',
]);
register_taxonomy('age-group', ['recipe'], [
    'hierarchical' => true,
    'show_in_rest' => true,
    'rest_base' => 'age-group',
]);

$infant = wp_insert_term('6-8 Ay', 'age-group', ['slug' => '6-8-ay-baslangic']);
$older = wp_insert_term('12-24 Ay', 'age-group', ['slug' => '12-24-ay-gecis']);
kg_test_expect(!is_wp_error($infant) && !is_wp_error($older), 'age taxonomy available');
$infant_id = (int) $infant['term_id'];
$older_id = (int) $older['term_id'];

$editor_id = wp_create_user('kg_ci_editor', wp_generate_password(30), 'kg-ci-editor@example.invalid');
kg_test_expect(!is_wp_error($editor_id), 'editor user created');
$user = new WP_User($editor_id);
$user->set_role('editor');
wp_set_current_user($editor_id);
kg_test_expect(current_user_can('publish_posts'), 'editor can publish');

$guard = new RecipePublicationGuard();
$recipe = wp_insert_post([
    'post_type' => 'recipe',
    'post_status' => 'draft',
    'post_title' => 'CI Bal Test Tarifi',
    'post_content' => 'Hazırlama açıklaması',
], true);
kg_test_expect(!is_wp_error($recipe), 'create draft recipe');
update_post_meta($recipe, '_kg_ingredients', [['name' => 'Bal']]);
wp_set_post_terms($recipe, [$infant_id], 'age-group');

$ack = $guard->acknowledgeSavedVersion($recipe, $editor_id);
kg_test_expect(is_array($ack) && !empty($ack['signature']), 'acknowledgment saved');
$result = wp_update_post(['ID' => $recipe, 'post_status' => 'publish'], true);
kg_test_expect(!is_wp_error($result) && get_post_status($recipe) === 'publish', 'editor publishes despite honey warning');
$logs = get_post_meta($recipe, '_kg_recipe_safety_publication_log', false);
kg_test_expect(count($logs) === 1, 'publication logged');
kg_test_expect($logs[0]['explicitly_acknowledged'] === true, 'explicit editor acknowledgment distinguished');
kg_test_expect($logs[0]['publisher_user_id'] === $editor_id, 'publishing editor recorded');
kg_test_expect(get_post_meta($recipe, '_kg_recipe_safety_acknowledgment', true) === '', 'ack consumed after publish');

// Revisions are allowed without further acknowledgment and are logged honestly.
wp_update_post(['ID' => $recipe, 'post_content' => 'Düzenlenmiş hazırlama açıklaması']);
$logs = get_post_meta($recipe, '_kg_recipe_safety_publication_log', false);
kg_test_expect(count($logs) === 2 && $logs[1]['explicitly_acknowledged'] === false, 'updated recipe audit without forced draft');

// REST taxonomy-only changes must be observed *after* term persistence.
$recipe_rest = wp_insert_post([
    'post_type' => 'recipe',
    'post_status' => 'draft',
    'post_title' => 'CI REST Bal Tarifi',
], true);
kg_test_expect(!is_wp_error($recipe_rest), 'create REST test draft');
update_post_meta($recipe_rest, '_kg_ingredients', [['name' => 'Bal']]);
wp_set_post_terms($recipe_rest, [$older_id], 'age-group');
wp_update_post(['ID' => $recipe_rest, 'post_status' => 'publish'], true);
kg_test_expect(get_post_meta($recipe_rest, '_kg_recipe_safety_warning', true) === '', 'no under-12 warning for 12+ recipe');

$request = new WP_REST_Request('POST', '/wp/v2/recipes/' . $recipe_rest);
$request->set_param('age-group', [$infant_id]);
$response = rest_do_request($request);
kg_test_expect(!is_wp_error($response) && $response->get_status() === 200, 'WordPress REST API updates recipe age group');
kg_test_expect(in_array($infant_id, wp_get_post_terms($recipe_rest, 'age-group', ['fields' => 'ids']), true), 'REST change to under-12 group saved');
kg_test_expect(get_post_meta($recipe_rest, '_kg_recipe_safety_warning', true) !== '', 'REST age-only edit produces warning');
$rest_logs = get_post_meta($recipe_rest, '_kg_recipe_safety_publication_log', false);
kg_test_expect(count($rest_logs) === 1 && $rest_logs[0]['explicitly_acknowledged'] === false, 'REST published revision audited without impersonated approval');

// A scheduled publish must not impersonate an editor.
$scheduled_recipe = wp_insert_post([
    'post_type' => 'recipe',
    'post_status' => 'draft',
    'post_title' => 'CI Zamanlanmış Bal Tarifi',
], true);
kg_test_expect(!is_wp_error($scheduled_recipe), 'scheduled recipe draft created');
update_post_meta($scheduled_recipe, '_kg_ingredients', [['name' => 'Bal']]);
wp_set_post_terms($scheduled_recipe, [$infant_id], 'age-group');
$future_time = gmdate('Y-m-d H:i:s', time() + 3600);
$schedule_result = wp_update_post([
    'ID' => $scheduled_recipe,
    'post_status' => 'future',
    'post_date_gmt' => $future_time,
    'post_date' => get_date_from_gmt($future_time),
], true);
$scheduled_post = get_post($scheduled_recipe);
WP_CLI::log('SCHEDULE DEBUG: ' . wp_json_encode([
    'result' => is_wp_error($schedule_result) ? $schedule_result->get_error_message() : $schedule_result,
    'status' => $scheduled_post->post_status,
    'post_date_gmt' => $scheduled_post->post_date_gmt,
    'post_date' => $scheduled_post->post_date,
    'requested_date_gmt' => $future_time,
    'now_utc' => current_time('mysql', true),
]));
kg_test_expect(get_post_status($scheduled_recipe) === 'future', 'future status saved');
wp_set_current_user(0);
// A WP-Cron publish callback invokes wp_publish_post(), which calls
// wp_transition_post_status() directly rather than wp_update_post().
wp_publish_post($scheduled_recipe);
kg_test_expect(get_post_status($scheduled_recipe) === 'publish', 'scheduled recipe published by WordPress core');
$schedule_logs = get_post_meta($scheduled_recipe, '_kg_recipe_safety_publication_log', false);
kg_test_expect(count($schedule_logs) === 1, 'scheduled publication audit recorded');
kg_test_expect($schedule_logs[0]['publisher_user_id'] === 0, 'scheduled publication has system identity');
kg_test_expect($schedule_logs[0]['explicitly_acknowledged'] === false, 'scheduled publication not marked as editor-approved');

WP_CLI::success('WordPress recipe publication integration tests passed.');

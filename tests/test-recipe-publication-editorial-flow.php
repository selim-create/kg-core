<?php
/**
 * WordPress-hook-independent tests of editorial acknowledgment and publication.
 * Run: php tests/test-recipe-publication-editorial-flow.php
 */
$GLOBALS['kg_test_meta'] = [];
$GLOBALS['kg_test_ages'] = ['baslangic-tadim-6-8-ay'];
$GLOBALS['kg_test_user_id'] = 27;

function add_action() {}
function get_post_meta($post_id, $key, $single = false) {
    $values = $GLOBALS['kg_test_meta'][$post_id][$key] ?? [];
    return $single ? ($values[0] ?? '') : $values;
}
function update_post_meta($post_id, $key, $value) {
    $GLOBALS['kg_test_meta'][$post_id][$key] = [$value];
    return true;
}
function add_post_meta($post_id, $key, $value) {
    $GLOBALS['kg_test_meta'][$post_id][$key][] = $value;
    return true;
}
function delete_post_meta($post_id, $key) {
    unset($GLOBALS['kg_test_meta'][$post_id][$key]);
    return true;
}
function wp_get_post_terms($post_id, $taxonomy, $args = []) {
    return $GLOBALS['kg_test_ages'];
}
function is_wp_error($value) { return false; }
function wp_is_post_revision($id) { return false; }
function wp_is_post_autosave($id) { return false; }
function current_time($format, $gmt = false) { return '2026-10-14 20:00:00'; }
function get_current_user_id() { return $GLOBALS['kg_test_user_id']; }

require_once dirname(__DIR__) . '/includes/Services/RecipePublicationGuard.php';

use KG_Core\Services\RecipePublicationGuard as Guard;

function check($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

$post_id = 111;
$guard = new Guard();
update_post_meta($post_id, '_kg_ingredients', [['name' => 'Bal']]);

$signoff = $guard->acknowledgeSavedVersion($post_id, 27);
check(is_array($signoff) && ($signoff['user_id'] ?? null) === 27, 'authorized acknowledgment structure');
check(isset($signoff['signature']), 'acknowledgment tied to saved recipe');

$published = (object)['ID' => $post_id, 'post_type' => 'recipe', 'post_status' => 'publish'];
$draft = (object)['ID' => $post_id, 'post_type' => 'recipe', 'post_status' => 'draft'];
$guard->recordReview($post_id, $published, true, $draft);
$logs = get_post_meta($post_id, '_kg_recipe_safety_publication_log', false);
check(count($logs) === 1, 'initial publication logged exactly once');
check($logs[0]['explicitly_acknowledged'] === true, 'explicit approval recorded distinctly');
check($logs[0]['publisher_user_id'] === 27, 'publisher identity recorded');
check(get_post_meta($post_id, '_kg_recipe_safety_acknowledgment', true) === '', 'approval consumed after publishing');

$guard->recordReview($post_id, $published, true, $published);
check(count(get_post_meta($post_id, '_kg_recipe_safety_publication_log', false)) === 1, 'duplicate publish callback is idempotent');

// A changed ingredient invalidates the old sign-off, and editing an already
// published recipe is still permitted. No automatic unpublish.
update_post_meta($post_id, '_kg_ingredients', [['name' => 'Bal'], ['name' => 'Yumurta']]);
$guard->recordReview($post_id, $published, true, $published);
$logs = get_post_meta($post_id, '_kg_recipe_safety_publication_log', false);
check(count($logs) === 2, 'changed published version creates a new audit entry');
check($logs[1]['explicitly_acknowledged'] === false, 'unacknowledged publish is tracked without being blocked');
check($published->post_status === 'publish', 'published post status remains under editor control');

$guard->recordReview($post_id, $draft, true, $published);
check(count(get_post_meta($post_id, '_kg_recipe_safety_publication_log', false)) === 2, 'draft edits do not count as publication');

// A scheduled publish occurs as system user 0.
$post_id = 112;
$GLOBALS['kg_test_user_id'] = 0;
update_post_meta($post_id, '_kg_ingredients', [['name' => 'Bal']]);
$future = (object)['ID' => $post_id, 'post_type' => 'recipe', 'post_status' => 'future'];
$scheduled = (object)['ID' => $post_id, 'post_type' => 'recipe', 'post_status' => 'publish'];
$guard->recordReview($post_id, $scheduled, true, $future);
$logs = get_post_meta($post_id, '_kg_recipe_safety_publication_log', false);
check(count($logs) === 1 && $logs[0]['publisher_user_id'] === 0, 'scheduled publish recorded as system action');
check($logs[0]['explicitly_acknowledged'] === false, 'scheduled publish does not impersonate an editor');

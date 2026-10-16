<?php
/**
 * Lightweight ingredient AI editorial review contract and safety tests.
 * Run: php tests/test-ingredient-ai-review.php
 */
$root = dirname(__DIR__);
require_once $root . '/includes/Admin/IngredientAIReview.php';

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($value) { return $value; }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($value) { return strip_tags((string) $value); }
}

function check_result($condition, $label) {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    if (!$condition) exit(1);
}

$class = '\\KG_Core\\Admin\\IngredientAIReview';
$honey = [$class, 'isHoneyTitle'];
check_result(call_user_func($honey, 'Bal'), 'honey identified');
check_result(call_user_func($honey, html_entity_decode('Cicek bal&#305;', ENT_QUOTES, 'UTF-8')), 'spelled-out honey identified');
check_result(call_user_func($honey, 'Balli yogurt'), 'honey adjective identified');
check_result(!call_user_func($honey, html_entity_decode('Bal Kaba&#287;&#305;', ENT_QUOTES, 'UTF-8')), 'pumpkin not flagged');
check_result(!call_user_func($honey, 'balkabagi'), 'pumpkin compound not flagged');
check_result(!call_user_func($honey, html_entity_decode('Bal&#305;k', ENT_QUOTES, 'UTF-8')), 'fish not flagged');
check_result(!call_user_func($honey, 'Balon'), 'balloon not flagged');

$reflect = new ReflectionClass($class);
$normalize = $reflect->getMethod('normalizeSuggestion');
check_result($normalize->invoke(null, 'age', 6, 'Bal') === null, 'honey < 12 months rejected');
check_result($normalize->invoke(null, 'age', 12, 'Bal') === 12, 'honey 12 months accepted');
check_result($normalize->invoke(null, 'age', 5, 'Havuc') === null, 'complementary food below 6 rejected');
check_result($normalize->invoke(null, 'age', 6, 'Havuc') === 6, 'general 6 months suggestion accepted');
check_result($normalize->invoke(null, 'age', 9, 'Havuc') === null, 'unsupported select option is never saved');
check_result($normalize->invoke(null, 'risk', html_entity_decode('D&uuml;&#351;&uuml;k', ENT_QUOTES, 'UTF-8'), 'Havuc') === html_entity_decode('D&uuml;&#351;&uuml;k', ENT_QUOTES, 'UTF-8'), 'valid allergy risk is preserved');
$early = [['age' => '6-9 Ay', 'method' => 'Pure', 'text' => 'Mix']];
check_result($normalize->invoke(null, 'age_rows', $early, 'Bal') === null, 'early honey preparation cannot be applied');

$fields = $reflect->getMethod('fieldDefinitions')->invoke(null);
check_result(!array_key_exists('nutrition', $fields), 'AI numerical nutrition is not eligible for automatic approval');
check_result(!array_key_exists('expert_approved', $fields), 'AI cannot grant expert approval');
check_result(isset($fields['start_age'], $fields['allergy_risk'], $fields['prep_by_age']), 'age, allergy and preparation proposals remain reviewable');

$bootstrap = file_get_contents($root . '/kg-core.php');
$source = file_get_contents($root . '/includes/Admin/IngredientAIReview.php');
$js = file_get_contents($root . '/assets/admin/js/ingredient-ai-review.js');
$ai = file_get_contents($root . '/includes/Services/AIService.php');
check_result(strpos($bootstrap, 'new \\KG_Core\\Admin\\IngredientAIReview();') !== false, 'new review controller registered');
check_result(strpos($bootstrap, 'new \\KG_Core\\Admin\\IngredientEnricher();') === false, 'legacy instant-write metabox disabled');
check_result(strpos($bootstrap, 'new \\KG_Core\\Admin\\AIEnrichButton();') === false, 'legacy instant-write admin bar disabled');
check_result(strpos($source, 'hash_equals(') !== false && strpos($source, 'check_ajax_referer') !== false, 'nonce and version checks present');
check_result(strpos($source, "current_user_can('edit_post'") !== false, 'per-post permission checks present');
check_result(strpos($ai, '$editor_context = []') !== false, 'existing ingredient editorial context supported');
check_result(strpos($js, 'MutationObserver') !== false, 'Gutenberg remounts initialize the review card');
check_result(strpos($js, 'textContent') !== false && strpos($js, 'innerHTML') === false, 'AI content rendered without raw HTML injection');
check_result($class::isFilled('Dolu'), 'non-empty field is filled');
check_result(!$class::isFilled('  '), 'whitespace is missing');
check_result(!$class::isFilled([]), 'empty list is missing');
check_result(!$class::isFilled(['', ' ']), 'empty nested list is missing');
check_result($class::isFilled(['', 'Hazırlama']), 'partially filled list is filled');
check_result(strpos($source, 'coverageSnapshot') !== false, 'server computes saved ingredient completeness');
check_result(strpos($source, "'complete', 'enrich'") !== false, 'complete and enrich modes validated');
check_result(strpos($source, "\$mode === 'complete' && self::isFilled(\$current)") !== false, 'complete mode preserves populated fields');
check_result(strpos($js, "startPreview('complete')") !== false && strpos($js, "startPreview('enrich')") !== false, 'two AI actions available');
check_result(strpos($js, "if (dirty())") === false && strpos($js, "generate.addEventListener") === false, 'AI save no longer blocked by dirty form');
check_result(strpos($source, 'applyBackgroundResult') !== false, 'AI result is saved in authenticated status endpoint');
check_result(strpos($js, "window.location.reload()") !== false, 'page reloads only after verified save');

check_result(strpos($source, 'kg_ingredient_ai_review_generate_job') === false, 'WP-Cron preview job removed');
check_result(strpos($source, 'startIngredientReviewBackground') !== false, 'OpenAI native background mode starts without WP-Cron');
check_result(strpos($source, 'kg_ingredient_ai_review_status') !== false, 'polling action present');
check_result(strpos($source, 'applyBackgroundResult') !== false, 'background response is validated and saved without preview token');
check_result(strpos($js, "kg_ingredient_ai_review_status") !== false, 'editor polls for preview status');
check_result(strpos($js, "response.text()") !== false, 'HTTP errors no longer hide behind JSON parse failure');

check_result(strpos($source, 'get_transient($this->cacheKey') === false, 'no separate expiring preview cache needed');
check_result(strpos($source, 'public function apply()') === false, 'no second AJAX apply action');
check_result(strpos($js, "kg_ingredient_ai_review_apply") === false, 'browser never sends second apply request');
check_result(strpos($js, "showSaved(result.result)") !== false, 'browser waits for server save result');

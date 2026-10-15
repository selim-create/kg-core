<?php
/**
 * Lightweight contract checks for the recipe studio's preserved save schema.
 * Run: php tests/test-recipe-editor-ux.php
 */
$source = file_get_contents(__DIR__ . '/../includes/Admin/RecipeMetaBox.php');
$bootstrap = file_get_contents(__DIR__ . '/../kg-core.php');
$script = file_get_contents(__DIR__ . '/../assets/admin/js/recipe-editor-studio.js');
function ensure($ok, $label) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
ensure(substr_count($source, '<details class="kg-recipe-section') === 9, 'all nine sections are present');
ensure(substr_count($source, '</div></details>') === 9, 'every recipe section closes');
foreach (['basics','ingredients','instructions','substitutes','nutrition','expert','notes','media','cross-sell'] as $section) {
    ensure(strpos($source, 'id="kg-recipe-' . $section . '"') !== false, $section . ' section retained');
}
foreach ([
    'kg_prep_time','kg_cook_time','kg_serving_size','kg_difficulty',
    'kg_storage_info','kg_ingredients[','kg_instructions[','kg_substitutes[',
    'kg_calories','kg_protein','kg_carbs','kg_fat','kg_fiber','kg_sugar',
    'kg_sodium','kg_vitamins','kg_minerals','kg_expert_user_id',
    'kg_expert_approved','kg_special_notes','kg_video_url','kg_cross_sell_mode'
] as $field) {
    ensure(strpos($source, 'name="' . $field) !== false, 'save field exists: ' . $field);
}
ensure(strpos($source, "wp_nonce_field( 'kg_recipe_save', 'kg_recipe_nonce' )") !== false, 'existing recipe nonce preserved');
ensure(strpos($source, 'public function save_custom_meta_data(') !== false, 'existing saving method preserved');
ensure(strpos($bootstrap, "'kg-recipe-editor-studio'") !== false, 'studio script enqueued');
ensure(strpos($script, 'section.open = true') !== false, 'section navigation opens hidden fields');
ensure(strpos($script, 'MutationObserver') !== false, 'repeater counters observe added items');

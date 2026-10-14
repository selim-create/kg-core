<?php
/**
 * Standalone recipe output safety checks.
 * Run: php tests/test-recipe-ai-safety.php
 */
namespace {
    class WP_Error {
        private $code;
        public function __construct($code, $message) { $this->code = $code; }
        public function get_error_code() { return $this->code; }
    }
    function get_option($name, $default = false) { return $default; }
    function is_wp_error($value) { return $value instanceof WP_Error; }
}
namespace KG_Core\Services {
    require_once dirname(__DIR__) . '/includes/Services/OpenAIClient.php';
    require_once dirname(__DIR__) . '/includes/Services/AIService.php';

    $service = new AIService();
    $parse = new \ReflectionMethod(AIService::class, 'parseRecipeResponse');
    $parse->setAccessible(true);
    $cases = [
        ['invalid JSON', '{"age_groups":', 'json_parse_error'],
        ['array root', '[{"age_groups":[]}]', 'invalid_recipe_shape'],
        ['0-6 months', '{"age_groups":["hazirlk-evresi-0-6-ay"]}', 'unsafe_age_group'],
        ['invented age slug', '{"age_groups":["six-months"]}', 'invalid_age_group'],
        ['wrong age type', '{"age_groups":"baslangic-tadim-6-8-ay"}', 'invalid_age_groups'],
    ];
    foreach ($cases as [$name, $raw, $error]) {
        $result = $parse->invoke($service, $raw);
        if (!($result instanceof \WP_Error) || $result->get_error_code() !== $error) {
            fwrite(STDERR, "FAIL: $name\n");
            exit(1);
        }
        echo "PASS: $name\n";
    }
    $good = $parse->invoke($service, '{"age_groups":["baslangic-tadim-6-8-ay"],"nutrition":{"calories":"250 kcal"}}');
    if ($good instanceof \WP_Error || !array_key_exists('nutrition', $good) || $good['nutrition'] !== null) {
        fwrite(STDERR, "FAIL: unverified nutrition must be null\n");
        exit(1);
    }
    echo "PASS: unverified nutrition discarded\n";
    $safety = new \ReflectionMethod(AIService::class, 'validateRecipeIngredientSafety');
    $safety->setAccessible(true);
    $cases = [
        ['honey at 6 months', ['ingredients' => [['name' => 'Bal']]], ['age_groups' => ['baslangic-tadim-6-8-ay']], 'honey_infant_risk'],
        ['honey at 9 months', ['ingredients' => ['bal']], ['age_groups' => ['kesif-puturlye-gecis-9-11-ay']], 'honey_infant_risk'],
        ['honey without age', ['ingredients' => ['Bal']], ['age_groups' => []], 'honey_infant_risk'],
        ['untagged egg', ['ingredients' => ['Yumurta']], ['age_groups' => ['aile-sofrasina-gecis-12-24-ay'], 'allergens' => []], 'allergen_review_required'],
        ['missing ingredients', ['ingredients' => []], ['age_groups' => ['aile-sofrasina-gecis-12-24-ay']], 'recipe_ingredients_missing'],
    ];
    foreach ($cases as [$name, $recipe, $ai, $expected]) {
        $result = $safety->invoke($service, $recipe, $ai);
        if (!($result instanceof \WP_Error) || $result->get_error_code() !== $expected) {
            fwrite(STDERR, "FAIL: $name\n");
            exit(1);
        }
        echo "PASS: $name\n";
    }
    // 'Balık' is not honey: no substring false positive.
    $ok = $safety->invoke($service, ['ingredients' => ['Balık']], [
        'age_groups' => ['baslangic-tadim-6-8-ay'], 'allergens' => ['Balık']
    ]);
    if ($ok instanceof \WP_Error) {
        fwrite(STDERR, "FAIL: fish must not be identified as honey\n");
        exit(1);
    }
    echo "PASS: fish is not honey\n";

}

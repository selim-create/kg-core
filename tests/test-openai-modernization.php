<?php
/**
 * Lightweight static regression checks for the OpenAI modernization.
 * Run: php tests/test-openai-modernization.php
 */

$root = dirname(__DIR__);
$client = file_get_contents($root . '/includes/Services/OpenAIClient.php');
$ai = file_get_contents($root . '/includes/Services/AIService.php');
$seo = file_get_contents($root . '/includes/Services/RecipeSEOGenerator.php');
$settings = file_get_contents($root . '/includes/Admin/SettingsPage.php');
$bootstrap = file_get_contents($root . '/kg-core.php');

$checks = [
    'Responses API endpoint is used' => strpos($client, 'api.openai.com/v1/responses') !== false,
    'New default model is GPT-6.1 Sol' => strpos($client, "DEFAULT_MODEL = 'gpt-6.1-sol'") !== false,
    'Legacy model migration is present' => strpos($client, 'LEGACY_OPENAI_MODELS') !== false,
    'Ingredient generation uses shared client' => strpos($ai, 'new OpenAIClient(') !== false,
    'SEO generation uses shared client' => strpos($seo, 'new OpenAIClient(') !== false,
    'Old chat completions endpoint removed from ingredient service' => strpos($ai, '/v1/chat/completions') === false,
    'Old chat completions endpoint removed from SEO service' => strpos($seo, '/v1/chat/completions') === false,
    'Admin exposes GPT-6.1 Sol' => strpos($settings, 'gpt-6.1-sol') !== false,
    'Admin exposes GPT-6 Luna' => strpos($settings, 'gpt-6-luna') !== false,
    'Admin exposes GPT-6 Astra' => strpos($settings, 'gpt-6-astra') !== false,
    'OpenAI client loads before AI services' => strpos($bootstrap, 'OpenAIClient.php') < strpos($bootstrap, 'AIService.php'),
    'Outdated fixed egg-delay rule removed' => strpos($ai, 'Tam yumurta (sarısı+beyazı): start_age = 8') === false,
];

$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? "PASS" : "FAIL") . ": {$label}\n";
    if (!$ok) {
        $failed++;
    }
}

exit($failed === 0 ? 0 : 1);

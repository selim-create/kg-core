<?php
$root = dirname(__DIR__);
$controller = file_get_contents($root . '/includes/Admin/IngredientAIReview.php');
$client = file_get_contents($root . '/includes/Services/OpenAIClient.php');
$service = file_get_contents($root . '/includes/Services/AIService.php');
$js = file_get_contents($root . '/assets/admin/js/ingredient-ai-review.js');
$checks = [
 'no WP-Cron preview scheduler' => strpos($controller, 'wp_schedule_single_event') === false,
 'no WP-Cron preview hook' => strpos($controller, 'kg_ingredient_ai_review_generate_job') === false,
 'start OpenAI background job' => strpos($controller, 'startIngredientReviewBackground') !== false,
 'poll OpenAI in status endpoint' => strpos($controller, 'pollIngredientReviewBackground') !== false,
 'request native background' => strpos($client, "'background' => true") !== false,
 'background responses stored temporarily' => strpos($client, "'store' => true") !== false,
 'API stores server-side response ID' => strpos($controller, "'response_id' => \$started['response_id']") !== false,
 'user approves fields before writing' => strpos($controller, "public function apply()") !== false,
 'new worker accepts only approved fields' => strpos($controller, 'normalizeSuggestion') !== false,
 'frontend no longer suggests WP-Cron timeout' => strpos($js, 'WP-Cron ve sunucu zaman') === false,
 'focused generation method exists' => strpos($service, 'generateIngredientReviewSuggestions') !== false,
];
foreach ($checks as $name => $pass) {
 echo ($pass ? 'PASS' : 'FAIL') . ': ' . $name . "\n";
 if (!$pass) exit(1);
}

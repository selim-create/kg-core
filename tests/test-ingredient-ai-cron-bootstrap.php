<?php
$root = dirname(__DIR__);
$bootstrap = file_get_contents($root . '/kg-core.php');
$controller = file_get_contents($root . '/includes/Admin/IngredientAIReview.php');
$start = strpos($bootstrap, "if ( class_exists( '\\KG_Core\\Admin\\IngredientAIReview' ) )");
$old = strpos($bootstrap, "if ( is_admin() && class_exists( '\\KG_Core\\Admin\\IngredientAIReview' ) )");
if ($start === false || $old !== false) { fwrite(STDERR, "FAIL: non-admin worker bootstrap\n"); exit(1); }
$worker = strpos($controller, "add_action('kg_ingredient_ai_review_generate_job'");
$gate = strpos($controller, "if (!is_admin())");
if ($worker === false || $gate === false || $worker >= $gate) { fwrite(STDERR, "FAIL: cron hook must precede admin gate\n"); exit(1); }
if (strpos($controller, "add_action('wp_ajax_kg_ingredient_ai_review_apply'") === false) { exit(1); }
echo "PASS: cron worker registered outside admin; privileged UI stays admin-only\n";

<?php
$root = dirname(__DIR__);
require_once $root . '/includes/Admin/IngredientAIReview.php';
$class = '\\KG_Core\\Admin\\IngredientAIReview';
$test = function($condition,$name){if(!$condition){fwrite(STDERR, "FAIL: $name\n");exit(1);}echo "PASS: $name\n";};
$test($class::savedValueMatches('6',6),'numeric meta scalar normalization');
$test($class::savedValueMatches(['age'=>6],['age'=>'6']),'nested meta arrays');
$test(!$class::savedValueMatches('7',6),'different values remain different');
if(!function_exists('sanitize_text_field')){function sanitize_text_field($v){return trim(strip_tags((string)$v));}}
$normalize = (new ReflectionClass($class))->getMethod('normalizeSuggestion');
$test($normalize->invoke(null,'risk','low risk','Carrot')==='Düşük','supported risk alias');
$pairs=$normalize->invoke(null,'pairings',[['name'=>'Brokoli']],'Carrot');
$test(is_array($pairs) && count($pairs)===1 && $pairs[0]['emoji']==='🍽️','pairings without emoji');
$js=file_get_contents($root.'/assets/admin/js/ingredient-ai-review.js');
$test(strpos($js,'pct >= 86')!==false && strpos($js,'pct >= 70')!==false,'coverage tiers');

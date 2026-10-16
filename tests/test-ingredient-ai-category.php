<?php
$root = dirname(__DIR__);
require_once $root . '/includes/Admin/IngredientAIReview.php';
if (!function_exists('sanitize_text_field')) {function sanitize_text_field($s){return trim(strip_tags((string)$s));}}
$class = '\\KG_Core\\Admin\\IngredientAIReview';
$check = function($ok,$name){echo ($ok?'PASS':'FAIL').': '.$name."\n";if(!$ok)exit(1);};
$allowed=$class::allowedIngredientCategories();
$check(count($allowed)===10 && count(array_unique($allowed))===10,'10 fixed categories');
$normalize=(new ReflectionClass($class))->getMethod('normalizeSuggestion');
$check($normalize->invoke(null,'category','Proteinler','Tavuk Göğsü')==='Proteinler','valid category');
$check($normalize->invoke(null,'category','proteinler','Tavuk Göğsü')==='Proteinler','case insensitive');
$check($normalize->invoke(null,'category','Etler','Tavuk Göğsü')===null,'invalid category rejected');
$source=file_get_contents($root.'/includes/Admin/IngredientAIReview.php');
$check(strpos($source,'wp_insert_term(')===false,'never creates taxonomy term');
$check(strpos($source,"unset(\$definitions['category'])")!==false,'preserves chosen category');
$check(strpos($source,"wp_set_post_terms(")!==false,'assigns existing term');

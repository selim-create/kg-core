<?php
require_once dirname(__DIR__) . '/includes/Admin/IngredientAIReview.php';
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($x) {return trim(strip_tags((string)$x));} }
$class='\\KG_Core\\Admin\\IngredientAIReview';
$fields=(new ReflectionClass($class))->getMethod('fieldDefinitions')->invoke(null);
foreach (['seo_title'=>'rank_math_title','seo_description'=>'rank_math_description','seo_focus_keyword'=>'rank_math_focus_keyword'] as $field=>$target) {
 if (!isset($fields[$field]) || $fields[$field]['target']!==$target) {fwrite(STDERR,"FAIL: $field\n");exit(1);}
}
$normalize=(new ReflectionClass($class))->getMethod('normalizeSuggestion');
$check=function($v,$label){if(!$v){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";};
$check($normalize->invoke(null,'seo_title','Tavuk Göğsü: Bebek Beslenmesinde Kullanımı','Tavuk Göğsü')!==null,'SEO title valid');
$check($normalize->invoke(null,'seo_title','kısa','Tavuk Göğsü')===null,'SEO title too short rejected');
$check($normalize->invoke(null,'seo_description','Tavuk göğsünün çocuk beslenmesindeki yeri, hazırlanması ve uygun sunum seçenekleri hakkında temel bilgileri keşfedin.','Tavuk Göğsü')!==null,'meta description valid');
$check($normalize->invoke(null,'seo_keyword','tavuk göğsü','Tavuk Göğsü')==='tavuk göğsü','focus keyword valid');
$source=file_get_contents(dirname(__DIR__) . '/includes/Admin/IngredientAIReview.php');
$check(strpos($source,"strpos(\$target, 'rank_math_') === 0")!==false,'Rank Math values saved');
$check(strpos($source,"'title' => 'Rank Math SEO'")!==false,'coverage includes Rank Math');

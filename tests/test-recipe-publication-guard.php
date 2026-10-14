<?php
/** Standalone publication gate tests: php tests/test-recipe-publication-guard.php */
require_once dirname(__DIR__) . '/includes/Services/RecipePublicationGuard.php';
use KG_Core\Services\RecipePublicationGuard as Guard;
$cases = [
    ['empty ingredient list', [], [], true],
    ['honey for infant', [['name' => 'Bal']], ['baslangic-tadim-6-8-ay'], true],
    ['honey at 9 months', ['Bal'], ['kesif-puturlye-gecis-9-11-ay'], true],
    ['honey missing age', ['Bal'], [], true],
    ['honey 12+ months', ['Bal'], ['aile-sofrasina-gecis-12-24-ay'], false],
    ['fish not honey', ['Balık'], ['baslangic-tadim-6-8-ay'], false],
    ['pumpkin not honey', ['Bal kabağı'], ['baslangic-tadim-6-8-ay'], false],
    ['honey flavored', ['Ballı yoğurt'], ['baslangic-tadim-6-8-ay'], true],
    ['ordinary recipe', ['Havuç'], ['baslangic-tadim-6-8-ay'], false],
];
foreach ($cases as [$label, $ingredients, $ages, $blocked]) {
    $result = Guard::assess($ingredients, $ages);
    if (($result !== null) !== $blocked) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
    echo "PASS: $label\n";
}

$ack = ['user_id' => 3, 'signature' => 'abc'];
if (!Guard::ackMatches($ack, 'abc') || Guard::ackMatches($ack, 'different') || Guard::ackMatches([], 'abc')) {
    fwrite(STDERR, "FAIL: editorial approval fingerprint check\n");
    exit(1);
}
echo "PASS: explicit approval is bound to content fingerprint\n";

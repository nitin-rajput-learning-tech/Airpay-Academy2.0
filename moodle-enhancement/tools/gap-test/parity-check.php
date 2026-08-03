<?php
// Authoritative en/hi lang parity checker for the 11-gap build.
// Loads each plugin's en + hi lang arrays as PHP (not regex) and diffs the keys.
// Run: php moodle-enhancement/tools/gap-test/parity-check.php
define('MOODLE_INTERNAL', true);

$base = __DIR__ . '/../../local';
$plugins = [
    'sentientia_skillsai', 'sentientia_authoring', 'sentientia_learningpath',
    'sentientia_content_market', 'sentientia_analytics', 'sentientia_assistant',
    'sentientia_xapi', 'sentientia_talent', 'sentientia_api',
];

function load_strings(string $file): array {
    if (!is_file($file)) {
        return ['__MISSING__' => true];
    }
    $string = [];
    require $file;
    return $string;
}

$allok = true;
foreach ($plugins as $p) {
    $comp = "local_$p";
    $en = load_strings("$base/$p/lang/en/$comp.php");
    $hi = load_strings("$base/$p/lang/hi/$comp.php");
    $onlyen = array_keys(array_diff_key($en, $hi));
    $onlyhi = array_keys(array_diff_key($hi, $en));
    $match = empty($onlyen) && empty($onlyhi) && count($en) === count($hi);
    if (!$match) {
        $allok = false;
    }
    printf("%-28s EN=%-4d HI=%-4d %s\n", $p, count($en), count($hi), $match ? 'OK' : '*** DRIFT');
    if ($onlyen) {
        echo "    only in EN: " . implode(', ', $onlyen) . "\n";
    }
    if ($onlyhi) {
        echo "    only in HI: " . implode(', ', $onlyhi) . "\n";
    }
}
echo $allok ? "\nALL PARITY OK\n" : "\nPARITY DRIFT DETECTED\n";

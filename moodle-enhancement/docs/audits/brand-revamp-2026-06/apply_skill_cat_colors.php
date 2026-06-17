<?php
// Apply the Revised Brand Book 2026-06 seeded category-colour migration to the
// LOCAL DB directly (idempotent). Mirrors local/sentientia_skills/db/upgrade.php
// step 2026061700 so item #2 lands even if the full-site upgrade is blocked by
// an unrelated plugin. Safe to re-run: matched rows shrink to zero.
//   #0f7a73 retired teal        -> #1985DD brand bright-blue (Financial Literacy)
//   #7c3aed Tailwind violet     -> #6d58a5 brand purple      (Technical)
//   #ea580c Tailwind orange-600 -> #ed692b brand orange      (Product Knowledge)
define('CLI_SCRIPT', true);
require('C:/xampp/htdocs/moodle5/config.php');

global $DB;
$repaint = [
    '#0f7a73' => '#1985DD',
    '#7c3aed' => '#6d58a5',
    '#ea580c' => '#ed692b',
];
$total = 0;
foreach ($repaint as $old => $new) {
    $n = $DB->count_records('local_sentientia_skill_cats', ['color' => $old]);
    if ($n > 0) {
        $DB->set_field('local_sentientia_skill_cats', 'color', $new, ['color' => $old]);
        echo "repainted {$n} row(s): {$old} -> {$new}" . PHP_EOL;
        $total += $n;
    } else {
        echo "no rows for {$old} (already brand-correct)" . PHP_EOL;
    }
}
echo "TOTAL repainted: {$total}" . PHP_EOL;

<?php
/**
 * Export all Airpay plugin + theme strings to CSV for translation review.
 * Columns: Plugin, Key, English, Hindi, Marathi, Swahili, Kannada
 */
define('CLI_SCRIPT', true);
require_once('C:\\xampp\\htdocs\\moodle\\config.php');

$output = fopen('D:\\Claude Local\\airpay-ld-os\\airpay_translations.csv', 'w');
// UTF-8 BOM for Excel compatibility.
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Plugin', 'String Key', 'English', 'Hindi (hi)', 'Marathi (mr)', 'Swahili (sw)', 'Kannada (kn)']);

// All plugins + theme to export.
$sources = [
    ['type' => 'local', 'name' => 'airpay_catalog'],
    ['type' => 'local', 'name' => 'airpay_gamification'],
    ['type' => 'local', 'name' => 'airpay_compliance_report'],
    ['type' => 'local', 'name' => 'airpay_skills'],
    ['type' => 'local', 'name' => 'airpay_notifications'],
    ['type' => 'local', 'name' => 'airpay_privacy'],
    ['type' => 'local', 'name' => 'airpay_assistant'],
    ['type' => 'local', 'name' => 'airpay_analytics'],
    ['type' => 'local', 'name' => 'airpay_pages'],
    ['type' => 'local', 'name' => 'airpay_emails'],
    ['type' => 'theme', 'name' => 'airpayux'],
];

$langs = ['hi', 'mr', 'sw', 'kn'];
$totalrows = 0;

foreach ($sources as $src) {
    if ($src['type'] === 'local') {
        $component = 'local_' . $src['name'];
        $basepath = $CFG->dirroot . '/local/' . $src['name'] . '/lang';
    } else {
        $component = 'theme_' . $src['name'];
        $basepath = $CFG->dirroot . '/theme/' . $src['name'] . '/lang';
    }

    // Load English strings.
    $enfile = $basepath . '/en/' . $component . '.php';
    if (!file_exists($enfile)) continue;

    $string = [];
    include($enfile);
    $en_strings = $string;

    // Load each language.
    $translations = [];
    foreach ($langs as $lang) {
        $string = [];
        $langfile = $basepath . '/' . $lang . '/' . $component . '.php';
        if (file_exists($langfile)) {
            include($langfile);
        }
        $translations[$lang] = $string;
    }

    // Write rows.
    foreach ($en_strings as $key => $value) {
        $row = [
            $component,
            $key,
            $value,
            $translations['hi'][$key] ?? '',
            $translations['mr'][$key] ?? '',
            $translations['sw'][$key] ?? '',
            $translations['kn'][$key] ?? '',
        ];
        fputcsv($output, $row);
        $totalrows++;
    }
}

fclose($output);
echo "Exported $totalrows strings to airpay_translations.csv\n";

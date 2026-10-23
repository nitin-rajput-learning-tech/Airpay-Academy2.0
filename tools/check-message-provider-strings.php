<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Gate: every message provider a Sentientia plugin declares has its 'messageprovider:<name>' string in lang/en.
 *
 * Moodle shows that string on every user's notification preferences page. Without it the page prints
 * "[[messageprovider:<name>]]". Three plugins shipped that way (local_sentientia_emails notification_alert,
 * local_sentientia_lifecycle compliance_deadline, local_sentientia_classroom waitlist_promoted) until the
 * 2026-10-20 visual pass found them (fixed in c3cc82ba6). Nothing caught the class: lang parity compares en with hi
 * and cannot see a key that is missing from both.
 *
 *   php tools/check-message-provider-strings.php            (from the repo root; exit 1 on any missing string)
 *
 * Both plugin trees are checked (moodle-enhancement/{local,blocks} and the root local/ and blocks/), plus
 * theme/sentientia. Read-only; no Moodle bootstrap.
 */

define('MOODLE_INTERNAL', true);
// Constants db/messages.php files use in their 'defaults' arrays.
foreach (['MESSAGE_PERMITTED' => 0x01, 'MESSAGE_DISALLOWED' => 0x04, 'MESSAGE_FORCED' => 0x08,
        'MESSAGE_DEFAULT_ENABLED' => 0x10, 'MESSAGE_DEFAULT_LOGGEDIN' => 0x02, 'MESSAGE_DEFAULT_LOGGEDOFF' => 0x02] as $c => $v) {
    if (!defined($c)) {
        define($c, $v);
    }
}

$root = getcwd();
$prefix = ['local' => 'local_', 'blocks' => 'block_', 'theme' => 'theme_'];
$files = array_merge(
    glob("$root/moodle-enhancement/local/*/db/messages.php"),
    glob("$root/moodle-enhancement/blocks/*/db/messages.php"),
    glob("$root/local/*/db/messages.php"),
    glob("$root/blocks/*/db/messages.php"),
    glob("$root/theme/sentientia/db/messages.php")
);
$checked = 0;
$missing = [];
foreach ($files as $file) {
    $plugindir = dirname(dirname($file));
    $type = basename(dirname($plugindir));
    $component = ($prefix[$type] ?? '') . basename($plugindir);
    $messageproviders = [];
    include($file);
    $string = [];
    $langfile = "$plugindir/lang/en/$component.php";
    if (is_file($langfile)) {
        include($langfile);
    }
    foreach (array_keys($messageproviders) as $name) {
        $checked++;
        if (!array_key_exists("messageprovider:$name", $string)) {
            $missing[] = substr($plugindir, strlen($root) + 1) . ": messageprovider:$name";
        }
    }
}
foreach ($missing as $m) {
    echo "FAIL  $m (lang/en has no string: notification preferences would show [[messageprovider:...]])\n";
}
echo "message-provider strings: $checked provider(s) checked in " . count($files) . " db/messages.php file(s), "
    . count($missing) . " missing\n";
exit($missing ? 1 : 0);

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Gate: every block region a theme_sentientia layout declares is printed by the template(s) that layout renders.
 *
 * Core core_renderer::standard_head_html() runs get_content() for every region the page layout declares, so that
 * block JS reaches the page head. It does this whether or not the layout prints the region. A block whose markup
 * never reaches the page still runs, and its JS can then fail. On 2026-10-23 that put a "Cannot read properties of
 * null (reading 'addEventListener')" popup on UAT /my/: the mydashboard layout declared side-pre plus nine BizLMS
 * layer* regions, dashboard.mustache printed none of them, and core block_recentlyaccesseditems' init found no root
 * (fixed in 83f9f252f). Removing an unprinted region is not a fix either: blocks recorded in an undeclared region
 * are merged into the page's default region and appear there.
 *
 *   php tools/check-theme-layout-regions.php [<theme dir>]      (default theme/sentientia; exit 1 on any finding)
 *
 * A region counts as printed when the layout file assigns $x = $OUTPUT->blocks('<region>' ...) (real blocks, not
 * fake-blocks-only), passes $x under some key K in the template context, and every theme_sentientia template the
 * layout file names prints {{{ K }}} (theme partials followed), or when the layout echoes $OUTPUT->blocks('<region>')
 * itself. Read-only; no Moodle bootstrap.
 */

namespace core\output {
    // config.php reads \core\output\icon_system::FONTAWESOME, and outside Moodle that class does not exist.
    if (!class_exists(icon_system::class, false)) {
        class icon_system {
            const FONTAWESOME = '\core\output\icon_system_fontawesome';
            const STANDARD = '\core\output\icon_system_standard';
        }
    }
}

namespace {
define('MOODLE_INTERNAL', true);
define('BLOCK_ADDBLOCK_POSITION_FLATNAV', 3);

// Known exceptions, each with the reason it is not fixed. Keep this list short and dated.
$allowed = [
    // Boost does the same: embedded pages print only fake blocks (quiz navigation) from side-pre.
    'embedded:side-pre' => 'fake blocks only, as in Boost',
    // 2026-10-24: course-pre is printed by nothing; removing it would move its blocks into the course drawer (a
    // visible change), so it waits for Nitin's call (docs/visual-evidence/2026-10-24/dashboard-hidden-regions).
    'course:course-pre' => 'decision pending (show in drawer or drop)',
];

$themedir = rtrim(str_replace('\\', '/', $argv[1] ?? getcwd() . '/theme/sentientia'), '/');
if (!is_file("$themedir/config.php")) {
    fwrite(STDERR, "no config.php in $themedir\n");
    exit(2);
}
global $CFG;
$CFG = (object) ['dirroot' => dirname($themedir, 2), 'libdir' => dirname($themedir, 2) . '/lib'];
$THEME = new stdClass();
include("$themedir/config.php");

$templatetext = function (string $name) use ($themedir): ?string {
    $file = "$themedir/templates/$name.mustache";
    return is_file($file) ? (string) file_get_contents($file) : null;
};
// The template's own text plus every theme_sentientia partial it pulls in, recursively.
$expand = function (string $name, array &$seen = []) use (&$expand, $templatetext): string {
    if (isset($seen[$name])) {
        return '';
    }
    $seen[$name] = true;
    $text = $templatetext($name) ?? '';
    preg_match_all('/\{\{>\s*theme_sentientia\/([a-z0-9_\/]+)\s*\}\}/', $text, $m);
    foreach ($m[1] as $partial) {
        $text .= "\n" . $expand($partial, $seen);
    }
    return $text;
};

$findings = 0;
$checked = 0;
foreach ($THEME->layouts as $layoutname => $layout) {
    $regions = $layout['regions'] ?? [];
    if (!$regions) {
        continue;
    }
    $file = "$themedir/layout/" . $layout['file'];
    $php = is_file($file) ? (string) file_get_contents($file) : '';
    preg_match_all("/'theme_sentientia\\/([a-z0-9_]+)'/", $php, $tm);
    $templates = array_values(array_unique(array_filter($tm[1], fn($t) => $templatetext($t) !== null)));
    foreach ($regions as $region) {
        $checked++;
        $q = preg_quote($region, '/');
        $printed = (bool) preg_match('/echo\s+\$OUTPUT->blocks\(\s*[\'"]' . $q . '[\'"]/', $php);
        $why = 'the layout never calls $OUTPUT->blocks(\'' . $region . '\')';
        if (!$printed && preg_match_all('/\$(\w+)\s*=\s*\$OUTPUT->blocks\(\s*[\'"]' . $q . '[\'"]([^;]*)\)\s*;/', $php, $am, PREG_SET_ORDER)) {
            $why = 'it is rendered but no template prints it';
            foreach ($am as [, $var, $rest]) {
                if (preg_match('/,\s*true\s*$/', trim($rest))) {
                    $why = 'only its fake blocks are rendered';
                    continue;
                }
                preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*\$' . $var . '\b/', $php, $km);
                foreach (array_unique($km[1]) as $key) {
                    $everywhere = (bool) $templates;
                    foreach ($templates as $t) {
                        if (!preg_match('/\{\{(\{\s*' . $key . '\s*\}|&\s*' . $key . '\s*)\}\}/', $expand($t))) {
                            $everywhere = false;
                        }
                    }
                    if ($everywhere) {
                        $printed = true;
                    } else if ($templates) {
                        $why = "it is passed as '$key' but not every template prints it (" . implode(', ', $templates) . ')';
                    }
                }
            }
        }
        if ($printed) {
            continue;
        }
        $id = "$layoutname:$region";
        if (isset($allowed[$id])) {
            echo "allowed  $id ({$allowed[$id]})\n";
            continue;
        }
        $findings++;
        echo "FAIL  layout '$layoutname' ({$layout['file']}) declares region '$region' but nothing prints it: $why."
            . " Core still runs its blocks and queues their JS.\n";
    }
}
echo "theme layout regions: $checked declared region(s) checked, $findings unprinted\n";
exit($findings ? 1 : 0);
}

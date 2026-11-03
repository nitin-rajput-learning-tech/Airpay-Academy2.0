<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY, read-only: the URL list for coverage_crawl.mjs (2026-11-02).
 *
 * The persona journeys follow fixed paths, so a course format or activity type nobody walks can fatal unseen (the
 * single-activity course page did, 2026-11-02). This lists, from the local database:
 *   - one visible course per course format in use;
 *   - one visible course module per module type in use (its view.php);
 *   - every local_sentientia_* plugin's index.php, where the file exists.
 *
 *   php coverage_urls.php > <file>.json          (cwd = moodle5/public)
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');

global $CFG, $DB;
$host = parse_url($CFG->wwwroot, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "refused: local site only\n");
    exit(1);
}

$urls = [];
$formats = $DB->get_records_sql(
    "SELECT format, MIN(id) AS id FROM {course} WHERE id <> :site AND visible = 1 GROUP BY format",
    ['site' => SITEID]
);
foreach ($formats as $f) {
    $urls[] = ['kind' => 'format', 'name' => $f->format, 'url' => '/course/view.php?id=' . $f->id];
}
$mods = $DB->get_records_sql(
    "SELECT m.name, MIN(cm.id) AS cmid
       FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module
       JOIN {course} c ON c.id = cm.course
      WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND c.visible = 1 AND m.visible = 1
   GROUP BY m.name"
);
foreach ($mods as $m) {
    if (file_exists($CFG->dirroot . '/mod/' . $m->name . '/view.php')) {
        $urls[] = ['kind' => 'module', 'name' => $m->name, 'url' => '/mod/' . $m->name . '/view.php?id=' . $m->cmid];
    }
}
foreach (core_component::get_plugin_list('local') as $name => $dir) {
    if (strpos($name, 'sentientia_') === 0 && file_exists($dir . '/index.php')) {
        $urls[] = ['kind' => 'plugin', 'name' => 'local_' . $name, 'url' => '/local/' . $name . '/index.php'];
    }
}
echo json_encode($urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

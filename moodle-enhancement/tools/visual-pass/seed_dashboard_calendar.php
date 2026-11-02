<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY seed for the 2026-11-02 theme evidence: a calendar block on vp_admin1's dashboard (side-pre).
 *
 * The blocks drawer shows only when the dashboard has blocks, and the local persona's own dashboard has none. This adds
 * one block_calendar_month to that persona's private dashboard page, once (idempotent). It stays as test data.
 *
 *   php seed_dashboard_calendar.php          (cwd = moodle5/public)
 *
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
require_once($CFG->dirroot . '/my/lib.php');

global $CFG, $DB;
$host = parse_url($CFG->wwwroot, PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1'], true) || empty($CFG->noemailever)) {
    fwrite(STDERR, "refused: run only on the local site with noemailever set\n");
    exit(1);
}

$user = $DB->get_record('user', ['username' => 'vp_admin1', 'deleted' => 0], '*', MUST_EXIST);
$mypage = my_get_page($user->id, MY_PAGE_PRIVATE);
if (!$mypage || empty($mypage->userid)) {
    $mypage = my_copy_page($user->id, MY_PAGE_PRIVATE);
}
$context = context_user::instance($user->id);

$existing = $DB->get_record('block_instances', ['blockname' => 'calendar_month', 'parentcontextid' => $context->id,
    'pagetypepattern' => 'my-index', 'subpagepattern' => (string) $mypage->id]);
if (!$existing) {
    $page = new moodle_page();
    $page->set_context($context);
    $page->set_pagelayout('mydashboard');
    $page->set_pagetype('my-index');
    $page->set_subpage($mypage->id);
    $page->blocks->add_region('side-pre');
    $page->blocks->add_block('calendar_month', 'side-pre', 0, false, 'my-index', $mypage->id);
}
echo json_encode(['user' => (int) $user->id, 'page' => (int) $mypage->id, 'added' => !$existing]) . "\n";

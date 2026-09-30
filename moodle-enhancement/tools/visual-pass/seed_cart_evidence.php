<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: create the cart orders the ADR-031 decision-3 screen checks need
 * (docs/visual-evidence/2026-09-29/README.md, "Cart ... screen checks").
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set, so it can
 * never touch UAT/production or mail anyone.
 *
 * Runs the real code path, not hand-written rows:
 *   1. two new priced courses, "VP Cart In-Tenant" (/1) and "VP Cart Other
 *      Tenant" (/177), each with an enrol_fee instance;
 *   2. a pending order for vp_learner1 (/1) holding both - an order that
 *      predates the purchase gate - then cart_manager::mark_paid(): the /1
 *      line is enrolled, the /177 line is withheld, the refund-due note is
 *      written, the buyer and site-admin messages are sent (popup only);
 *   3. a second pending order failed by cart_manager::mark_failed() with a
 *      gateway payload that contains markup (the PARAM_RAW case).
 *
 * Idempotent: courses are reused by shortname; an order is only created when
 * vp_learner1 has no order with that order number yet, and a pending one is finished.
 *
 *   php seed_cart_evidence.php     (run with cwd = moodle5/public, after provision_local_personas.php)
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: wwwroot {$CFG->wwwroot} is not a local development host.\n");
    exit(1);
}
if (empty($CFG->noemailever)) {
    fwrite(STDERR, "Refusing: \$CFG->noemailever is not set - this script sends order messages.\n");
    exit(1);
}

use local_sentientia_cart\cart_manager;

global $DB;
$buyer = $DB->get_record('user', ['username' => 'vp_learner1', 'deleted' => 0], '*', MUST_EXIST);
$studentrole = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
$category = (int) $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');

$priced = function (string $shortname, string $fullname, string $path) use ($DB, $studentrole, $category): stdClass {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $course = create_course((object) ['fullname' => $fullname, 'shortname' => $shortname,
            'category' => $category, 'visible' => 1, 'format' => 'topics', 'numsections' => 1]);
    }
    $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
    if (!$DB->record_exists('enrol', ['enrol' => 'fee', 'courseid' => $course->id])) {
        $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $course->id,
            'sortorder' => 9, 'cost' => '1000.00', 'currency' => 'INR', 'roleid' => $studentrole,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }
    return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
};

$mine = $priced('vp_cart_mine', 'VP Cart In-Tenant', '/1');
$theirs = $priced('vp_cart_theirs', 'VP Cart Other Tenant', '/177');

$pending = function (int $orderid, array $courses) use ($DB, $buyer): ?int {
    $existing = $DB->get_record('local_sentientia_cart_history', ['userid' => $buyer->id, 'orderid' => $orderid]);
    if ($existing) {
        // A run that stopped half way leaves the order pending: finish it.
        return $existing->status === 'pending' ? (int) $existing->id : null;
    }
    $items = [];
    foreach ($courses as $c) {
        $items[] = ['courseid' => (int) $c->id, 'name' => format_string($c->fullname), 'price' => 1000.00];
    }
    $now = time();
    return (int) $DB->insert_record('local_sentientia_cart_history', (object) [
        'orderid' => $orderid, 'userid' => $buyer->id,
        'costcenterid' => cart_manager::get_tenant_root($buyer),
        'items_json' => json_encode($items), 'subtotal' => 1000.00 * count($items),
        'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 1000.00 * count($items),
        'currency' => 'INR', 'status' => 'pending', 'gateway' => 'airpay', 'gateway_ref' => '',
        'billing_name' => 'VP Learner', 'billing_email' => 'vp_learner1@example.invalid',
        'billing_phone' => '', 'billing_address' => '', 'billing_gstn' => '', 'notes' => '',
        'timecreated' => $now, 'timepaid' => 0, 'timemodified' => $now,
    ]);
};

$paidorder = 920000 + (int) $buyer->id;
$failedorder = 930000 + (int) $buyer->id;

if ($id = $pending($paidorder, [$mine, $theirs])) {
    cart_manager::mark_paid($id, 'VP-TXN-WITHHELD', []);
    echo "paid order #{$paidorder} (history {$id}): /1 line granted, /177 line withheld\n";
} else {
    echo "paid order #{$paidorder} already exists\n";
}
if ($id = $pending($failedorder, [$mine])) {
    cart_manager::mark_failed($id, 'Gateway reported failure: {"MESSAGE":"Declined <br>by bank"}');
    echo "failed order #{$failedorder} (history {$id}): gateway note with markup\n";
} else {
    echo "failed order #{$failedorder} already exists\n";
}

$enrolled = is_enrolled(context_course::instance($mine->id), $buyer->id) ? 'yes' : 'no';
$leaked = is_enrolled(context_course::instance($theirs->id), $buyer->id) ? 'YES (wrong)' : 'no';
echo "vp_learner1 enrolled in VP Cart In-Tenant: {$enrolled}; in VP Cart Other Tenant: {$leaked}\n";

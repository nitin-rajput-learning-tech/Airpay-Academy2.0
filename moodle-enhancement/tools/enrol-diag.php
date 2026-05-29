<?php
// Enrolment diagnostic for local_airpay_catalog free self-enrol (QA-walk E-01,
// 2026-05-29). READ-ONLY — safe on any environment. Inspects a course's enrol
// instances + a user's enrolment/tenant state and, if a user is given, asks the
// enrol plugins (as that user) whether self-enrol would be offered.
//
// Run:  php moodle-enhancement/tools/enrol-diag.php [courseid] [userid]
//   e.g. php moodle-enhancement/tools/enrol-diag.php 71 3421
// Moodle public/ dir defaults to C:/xampp/htdocs/moodle5/public; override with
// the MOODLE_PUBLIC env var (e.g. MOODLE_PUBLIC=/var/www/moodle/public).
// (Note: the ambient MOODLE_ROOT var on the dev box points elsewhere — don't use it.)

define('CLI_SCRIPT', true);
$moodlepublic = getenv('MOODLE_PUBLIC') ?: 'C:/xampp/htdocs/moodle5/public';
if (!is_file($moodlepublic . '/config.php')) {
    fwrite(STDERR, "Moodle config.php not found under '$moodlepublic'. "
        . "Set MOODLE_PUBLIC to your Moodle public/ directory.\n");
    exit(2);
}
require($moodlepublic . '/config.php');

global $DB;

$courseid = (int) ($argv[1] ?? 71);
$userid   = (int) ($argv[2] ?? 0);

function line($k, $v) { printf("  %-22s %s\n", $k, $v); }

echo "=== COURSE $courseid ===\n";
$c = $DB->get_record('course', ['id' => $courseid],
    'id, fullname, shortname, visible, format, open_path, open_categoryid', IGNORE_MISSING);
if (!$c) {
    echo "  !! course $courseid NOT FOUND\n";
} else {
    line('fullname',  $c->fullname);
    line('shortname', $c->shortname);
    line('visible',   $c->visible);
    line('open_path', var_export($c->open_path, true));
}

echo "\n=== ENROL INSTANCES on course $courseid ===\n";
foreach ($DB->get_records('enrol', ['courseid' => $courseid], 'sortorder ASC') as $i) {
    printf("  [%s] id=%d status=%s(%s) name=%s\n",
        $i->enrol, $i->id, $i->status,
        ($i->status == 0 ? 'ENABLED' : 'disabled'), var_export($i->name, true));
    if ($i->enrol === 'self') {
        line('  customint6 (allownew)', $i->customint6);
        line('  customint5 (cohortonly)', $i->customint5);
        line('  password (enrolkey)', ($i->password === '' ? '(none)' : '(SET)'));
        line('  enrolstart/end', $i->enrolstartdate . ' / ' . $i->enrolenddate);
    }
}
echo "  enrol_plugins_enabled: " . get_config('core', 'enrol_plugins_enabled') . "\n";

if ($userid > 0) {
    echo "\n=== USER $userid ===\n";
    $u = $DB->get_record('user', ['id' => $userid],
        'id, username, deleted, suspended, confirmed, open_path', IGNORE_MISSING);
    if (!$u) {
        echo "  !! user $userid NOT FOUND\n";
    } else {
        line('username', $u->username);
        line('suspended/deleted', $u->suspended . ' / ' . $u->deleted);
        line('open_path', var_export($u->open_path, true));

        echo "\n=== USER $userid enrolments ===\n";
        $ues = $DB->get_records_sql(
            "SELECT ue.id, e.enrol, e.courseid, ue.status AS uestatus
               FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :uid ORDER BY e.courseid", ['uid' => $userid]);
        if (!$ues) {
            echo "  (none)\n";
        } else {
            foreach ($ues as $r) {
                printf("  course=%d via %s (ue.status=%d)\n", $r->courseid, $r->enrol, $r->uestatus);
            }
        }

        if ($c) {
            echo "\n=== As user $userid: policy + enrol availability on course $courseid ===\n";
            \core\session\manager::set_user($DB->get_record('user', ['id' => $userid]));
            $ctx = \context_course::instance($courseid, IGNORE_MISSING);
            if ($ctx) {
                line('is_enrolled()', is_enrolled($ctx, $u) ? 'YES' : 'no');
            }
            if (class_exists('\\local_airpay_catalog\\enrolment')) {
                $pricing = \local_airpay_catalog\commerce::get_course_price($courseid);
                line('pricing.is_free', !empty($pricing['is_free']) ? 'true' : 'false');
                line('should_offer_oneclick()',
                    \local_airpay_catalog\enrolment::should_offer_oneclick($u, $pricing) ? 'YES' : 'no');
            }
            $plugins = enrol_get_plugins(true);
            foreach (enrol_get_instances($courseid, true) as $inst) {
                if (!isset($plugins[$inst->enrol])) { continue; }
                $p = $plugins[$inst->enrol];
                $canself = method_exists($p, 'can_self_enrol') ? $p->can_self_enrol($inst) : 'n/a';
                printf("  [%s] show_enrolme_link=%s can_self_enrol=%s\n",
                    $inst->enrol, $p->show_enrolme_link($inst) ? '1' : '0',
                    ($canself === true ? 'TRUE' : var_export($canself, true)));
            }
        }
    }
}

echo "\n=== TENANT self-enrol coverage (visible courses w/ active self) ===\n";
$rows = $DB->get_records_sql("
    SELECT t.root, COUNT(DISTINCT t.cid) AS courses,
           COUNT(DISTINCT CASE WHEN t.hasself = 1 THEN t.cid END) AS with_active_self
      FROM (SELECT c.id AS cid,
                   SUBSTRING_INDEX(SUBSTRING_INDEX(c.open_path,'/',2),'/',-1) AS root,
                   MAX(CASE WHEN e.enrol='self' AND e.status=0 THEN 1 ELSE 0 END) AS hasself
              FROM {course} c LEFT JOIN {enrol} e ON e.courseid = c.id
             WHERE c.visible = 1 AND c.id > 1 GROUP BY c.id, root) t
  GROUP BY t.root ORDER BY courses DESC");
printf("  %-10s %-10s %s\n", 'tenant', 'courses', 'with_active_self');
foreach ($rows as $r) {
    printf("  %-10s %-10s %s\n", '/' . $r->root, $r->courses, $r->with_active_self);
}

echo "\nDONE (read-only).\n";

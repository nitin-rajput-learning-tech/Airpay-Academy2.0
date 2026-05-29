<?php
// Read-only probe — sentientia_live sessions for the F-024 visual walk.
define('CLI_SCRIPT', true);
require('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

echo "── Live sessions ──\n";
$sessions = $DB->get_records('local_sentientia_live_sessions', null, 'id ASC');
foreach ($sessions as $s) {
    $slides = $DB->count_records('local_sentientia_live_slides', ['sessionid' => $s->id]);
    $parts  = $DB->count_records('local_sentientia_live_participants', ['sessionid' => $s->id]);
    // Responses join via slideid -> slide.sessionid (no direct sessionid col).
    $resps = (int) $DB->get_field_sql(
        "SELECT COUNT(r.id)
           FROM {local_sentientia_live_responses} r
           JOIN {local_sentientia_live_slides} sl ON sl.id = r.slideid
          WHERE sl.sessionid = :sid",
        ['sid' => $s->id]);
    printf("  id=%-3d  state=%-8s  owner=%-4d  code=%-8s  slides=%d parts=%d resp=%d\n",
        $s->id, $s->state, $s->ownerid, $s->code, $slides, $parts, $resps);
    printf("        title: %s\n", $s->title);
}

echo "\n── live.* feature flags ──\n";
if (class_exists('\\local_airpay_core\\feature_flags')) {
    foreach ([
        'live.enabled', 'live.realtime.enabled', 'live.allow_anonymous',
        'live.questiontype.multichoice', 'live.questiontype.wordcloud',
        'live.questiontype.quiz', 'live.questiontype.rating',
        'live.questiontype.ranking', 'live.questiontype.openended',
    ] as $f) {
        $on = \local_airpay_core\feature_flags::is_enabled($f);
        printf("  %-32s %s\n", $f, $on ? 'ON' : 'off');
    }
}

echo "\n── slide breakdown for the most-populated session ──\n";
$best = $DB->get_record_sql(
    "SELECT sl.sessionid, COUNT(sl.id) AS n
       FROM {local_sentientia_live_slides} sl
   GROUP BY sl.sessionid
   ORDER BY COUNT(sl.id) DESC", null, IGNORE_MULTIPLE);
if ($best) {
    $slides = $DB->get_records('local_sentientia_live_slides',
        ['sessionid' => $best->sessionid], 'position ASC, id ASC');
    foreach ($slides as $sl) {
        $rc = $DB->count_records('local_sentientia_live_responses', ['slideid' => $sl->id]);
        printf("  session %d slide id=%-3d type=%-12s resp=%-3d  '%s'\n",
            $best->sessionid, $sl->id, $sl->type ?? '?', $rc,
            substr($sl->question ?? $sl->title ?? '', 0, 40));
    }
}

echo "\n── admin user for login ──\n";
$admin = $DB->get_record_sql(
    "SELECT id, username, firstname, lastname FROM {user}
      WHERE username = 'admin' OR id = 2
   ORDER BY (username='admin') DESC, id ASC LIMIT 1");
if ($admin) {
    printf("  id=%d  username=%s  (%s %s)\n",
        $admin->id, $admin->username, $admin->firstname, $admin->lastname);
}

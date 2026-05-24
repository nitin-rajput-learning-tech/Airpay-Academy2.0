<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

$courses = $DB->get_records_sql(
    "SELECT id, fullname, shortname, idnumber, format, visible, timecreated
       FROM {course}
      WHERE timecreated > :since
        AND id > 1
   ORDER BY timecreated DESC
      LIMIT 5",
    ['since' => time() - 600] // last 10 minutes
);

if (empty($courses)) {
    echo "No courses created in last 10 minutes\n";
} else {
    echo "Recent courses (last 10 min):\n";
    foreach ($courses as $c) {
        echo sprintf("  id=%d shortname=%s fullname=\"%s\" format=%s visible=%d created=%s\n",
            $c->id, $c->shortname, $c->fullname, $c->format, $c->visible,
            date('H:i:s', $c->timecreated));
    }
}

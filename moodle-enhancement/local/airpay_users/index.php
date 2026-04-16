<?php
// Airpay User Management — redirects to BizLMS user list during transition.
// After BizLMS removal, this becomes the primary user management page.

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();

// If BizLMS user management still exists, redirect there.
if (file_exists($CFG->dirroot . '/local/users/index.php')) {
    redirect(new moodle_url('/local/users/index.php'));
}

// Airpay user listing (post-BizLMS).
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_users/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_airpay_users'));
$PAGE->set_heading(get_string('pluginname', 'local_airpay_users'));
$PAGE->set_pagelayout('admin');

require_capability('local/airpay_users:view', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_airpay_users'));

// Minimal user list — tenant-scoped.
$tenantid = \local_airpay_org\tenant_manager::get_tenant_id();
$pathfilter = \local_airpay_org\tenant_manager::get_user_path_filter();

$sql = "SELECT id, firstname, lastname, email, open_designation, lastaccess
          FROM {user}
         WHERE deleted = 0 AND suspended = 0"
     . (!empty($pathfilter) ? " AND open_path LIKE :upath" : "")
     . " ORDER BY lastname ASC, firstname ASC";
$params = !empty($pathfilter) ? ['upath' => $pathfilter] : [];
$users = $DB->get_records_sql($sql, $params, 0, 100);

$table = new html_table();
$table->head = ['Name', 'Email', 'Designation', 'Last Access'];
$table->attributes['class'] = 'generaltable';

foreach ($users as $u) {
    $profileurl = new moodle_url('/local/airpay_users/profile.php', ['id' => $u->id]);
    $table->data[] = [
        html_writer::link($profileurl, fullname($u)),
        s($u->email),
        s($u->open_designation ?? '—'),
        $u->lastaccess ? userdate($u->lastaccess) : get_string('never'),
    ];
}

echo html_writer::table($table);
if (count($users) >= 100) {
    echo html_writer::tag('p', 'Showing first 100 users.', ['class' => 'text-muted']);
}

echo $OUTPUT->footer();

<?php
// Smoke test: photo-upload server-side processing pipeline.
//
// Tests the actual gdlib path used by photo.php — pulls a real PNG
// fixture, runs it through process_new_icon, asserts user.picture
// flips from 0 to a non-zero filename hash.
//
// Run: php public/local/sentientia_users/cli/smoke_photo_upload.php
//
// Exit codes: 0 = pass, non-zero = fail.

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/gdlib.php');

global $DB;

// Pick a user.
$user = $DB->get_record_sql(
    "SELECT id, picture FROM {user} WHERE deleted = 0 AND id > 2
       AND username NOT IN ('admin', 'guest')
       ORDER BY id ASC LIMIT 1");
if (!$user) {
    fwrite(STDERR, "FAIL: no user.\n");
    exit(1);
}
$userid = (int) $user->id;
$pre = (int) $user->picture;
echo "User $userid: pre-test picture=$pre\n";

// Find the PNG fixture.
$fixture = 'D:/Claude Local/airpay-ld-os/moodle-enhancement/audit/playwright/fixtures/test-avatar.png';
if (!file_exists($fixture)) {
    fwrite(STDERR, "FAIL: fixture missing: $fixture\n");
    exit(2);
}

$usercontext = context_user::instance($userid);
$newpicture = (int) process_new_icon($usercontext, 'user', 'icon', 0, $fixture);
if ($newpicture <= 0) {
    fwrite(STDERR, "FAIL: process_new_icon returned $newpicture\n");
    exit(3);
}
echo "process_new_icon returned: $newpicture\n";

$DB->set_field('user', 'picture', $newpicture, ['id' => $userid]);

// Re-read user.picture.
$after = (int) $DB->get_field('user', 'picture', ['id' => $userid]);
if ($after === $pre) {
    fwrite(STDERR, "FAIL: picture flag didn't change ($pre → $after)\n");
    exit(4);
}
echo "user.picture: $pre → $after ✓\n";

// Verify the files actually exist in user file area.
$fs = get_file_storage();
$files = $fs->get_area_files($usercontext->id, 'user', 'icon',
    0, '', false);
echo "User icon files: " . count($files) . "\n";
$hasf1 = false; $hasf2 = false;
foreach ($files as $f) {
    $name = $f->get_filename();
    if (strpos($name, 'f1') === 0) $hasf1 = true;
    if (strpos($name, 'f2') === 0) $hasf2 = true;
    echo "  - $name (" . $f->get_filesize() . " bytes)\n";
}
if (!$hasf1 || !$hasf2) {
    fwrite(STDERR, "FAIL: expected f1.* + f2.* (100×100 + 35×35); have f1=$hasf1 f2=$hasf2\n");
    exit(5);
}
echo "f1 + f2 thumbnails present ✓\n";

// Cleanup — reset to pre.
$DB->set_field('user', 'picture', $pre, ['id' => $userid]);
foreach ($files as $f) {
    $f->delete();
}
echo "Cleanup ✓\n";

echo "\nALL OK ✓\n";
exit(0);

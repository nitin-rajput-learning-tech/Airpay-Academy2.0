<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * cli/smoke_waitlist.php must not touch a database before it has refused the ones it must not run on.
 *
 * The script boots the real site (config.php), so it cannot run inside PHPUnit. What can be pinned is the ORDER of its
 * guards in the source: the --dev acknowledgement (it uses the first four real accounts as learners and notifies one),
 * then the refusal on a database that holds BizLMS import map rows, then everything that writes. A later edit that moves
 * a write above a guard fails here.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group local_sentientia_classroom
 */
final class smoke_waitlist_guard_test extends \advanced_testcase {

    private function source(): string {
        $source = file_get_contents(__DIR__ . '/../cli/smoke_waitlist.php');
        $this->assertNotFalse($source);
        return (string) $source;
    }

    public function test_the_dev_acknowledgement_comes_before_anything_is_read_or_written(): void {
        $source = $this->source();
        $dev = strpos($source, "'--dev'");
        $this->assertNotFalse($dev, 'the script asks for --dev');
        $this->assertStringContainsString('REFUSED', substr($source, $dev, 700));
        $this->assertStringContainsString('exit(2)', substr($source, $dev, 700), 'a refusal is exit 2, nothing touched');

        foreach (['legacymap', 'FROM {user}', '->insert_record(', '->delete_records(', 'noemailever'] as $later) {
            $at = strpos($source, $later, $dev);
            $this->assertNotFalse($at, $later . ' is still used');
            $this->assertGreaterThan($dev, $at, $later . ' comes after the --dev guard');
        }
    }

    public function test_the_import_database_refusal_comes_before_the_first_write(): void {
        $source = $this->source();
        $refusal = strpos($source, 'local_sentientia_legacymap');
        $this->assertNotFalse($refusal);
        $this->assertLessThan(strpos($source, '->insert_record('), $refusal);
        $this->assertLessThan(strpos($source, 'FROM {user}'), $refusal);
    }

    public function test_the_script_never_edits_or_deletes_a_classroom_it_did_not_create(): void {
        $source = $this->source();
        $this->assertStringNotContainsString("FROM {local_sentientia_classroom} ORDER BY id LIMIT 1", $source,
            'it used to take the first classroom of the table');
        $this->assertStringNotContainsString("update_record('local_sentientia_classroom'", $source);
    }
}

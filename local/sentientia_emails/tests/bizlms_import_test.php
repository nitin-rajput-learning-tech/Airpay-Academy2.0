<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_emails\bizlms\importer;
use local_sentientia_emails\bizlms\log_step;
use local_sentientia_emails\bizlms\redactor;
use local_sentientia_org\test\bizlms_fixture;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer as importer_interface;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The notifications importer (ADR-032, mapping doc section 11): local_emaillogs and local_email_logs
 * become rows of local_sentientia_email_log.
 *
 * The contract traits give the generic tests (not applicable without tables, dry run writes nothing, apply
 * reconciles, second apply is a no-op, resume, source change, no side effects, privacy declared). The rest
 * pins what is specific to this feature, from the fixture section of the mapping doc:
 *
 *  - one row per source row, the queue never sent, status preserved under noemailever;
 *  - credentials: a users-module message, a template with the password placeholder and an unresolvable
 *    template that reads like an account message are imported with subject masked and body NULL, and text
 *    that still carries a secret is scrubbed;
 *  - tenant from the RECIPIENT, never from the template first;
 *  - template_key and rule_id stay NULL so the reminder engine never counts an imported row;
 *  - source timestamps kept, the legacy tables untouched.
 *
 * The legacy tables are the fixture notifications.install.xml (production shape; BizLMS declares no
 * install.xml for them). Tenant roots 1, 77 and 177 are the registered ones.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @covers     \local_sentientia_emails\bizlms\importer
 * @covers     \local_sentientia_emails\bizlms\log_step
 * @covers     \local_sentientia_emails\bizlms\emaillogs_step
 * @covers     \local_sentientia_emails\bizlms\email_logs_step
 *
 * @group local_sentientia_emails
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use bizlms_fixture;

    /** Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** The target table. */
    private const TARGET = 'local_sentientia_email_log';

    /** The owner's signed values for this feature (docs/cutover/bizlms-import-decisions.json, 2026-09-30). */
    private const SIGNED = [
        'notifications.import_bodies' => true,
        'notifications.queue_status' => 'not_sent',
        'notifications.keep_sender' => true,
        'notifications.retention' => 'keep_no_purge',
        'notifications.deleted_recipient_sent' => 'sent_with_note',
        'tenant.unresolved.notifications' => 'pathless',
    ];

    /** @var array<string, mixed> The decision values a test runs with; a test may change some. */
    private array $decisionvalues = self::SIGNED;

    /** @var array<string, int> Ids of what the seed created, by name. */
    private array $ids = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/notifications.install.xml'];
    }

    protected function contract_importer(): importer_interface {
        return new importer();
    }

    protected function contract_decisions(): decisions {
        return decisions::from_array($this->decisionvalues);
    }

    protected function contract_seed(): void {
        $this->seed_notifications();
    }

    protected function contract_mutate_source(): void {
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $this->legacy_row('local_emaillogs', [
            'id' => 99, 'notification_infoid' => 1, 'from_userid' => $this->ids['sender'],
            'to_userid' => $this->ids['a'], 'subject' => 'Late arrival', 'status' => 0,
            'timecreated' => self::T0 + 99,
        ]);
    }

    protected function contract_user_columns(): array {
        // The columns the import added that name a person or hold a message.
        return [self::TARGET => ['sender_userid', 'body_html']];
    }

    // The seed.

    /**
     * Insert into a legacy table whose real schema this codebase does not own: every NOT NULL column without a
     * default that the caller did not name gets a neutral value, and a column the table lacks is ignored.
     *
     * @param string $table
     * @param array<string, mixed> $data Must carry id.
     * @return int The id.
     */
    private function legacy_row(string $table, array $data): int {
        global $DB;
        $row = [];
        foreach ($DB->get_columns($table, false) as $name => $column) {
            if (array_key_exists($name, $data)) {
                $row[$name] = $data[$name];
            } else if ($name !== 'id' && $column->not_null && !$column->has_default) {
                $row[$name] = in_array($column->meta_type, ['I', 'N', 'F', 'R', 'L'], true) ? 0 : '';
            }
        }
        $DB->import_record($table, (object) $row);
        return (int) $row['id'];
    }

    /**
     * A user at a tenant path; null path leaves open_path empty.
     *
     * @param string|null $path
     * @param array $extra
     * @return int
     */
    private function user_at(?string $path, array $extra = []): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user($extra);
        if ($path !== null) {
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        }
        return (int) $user->id;
    }

    /**
     * The seed. Numbers a test may rely on:
     *
     *  local_emaillogs  17 rows. 16 import, 13 (an unknown recipient) is skipped: orphan_user.
     *    1 sent, to a /1 user, course kept, created = sent date       2 queued, users module: subject masked, body NULL
     *    3 status NULL, /77 recipient                                  4 created 0, only timemodified set
     *    5 template gone, subject reads like an account message        6 sent to a deleted user
     *    7 manager copy (teammemberid set)                             8 courseid -1 (custom mail)
     *    9 courseid that does not exist                                10 /1 recipient, template of /177
     *    11 recipient with no path, template of /77                    12 recipient whose root is not a tenant
     *    13 recipient that does not exist                              14 status 7
     *    15 password placeholder in a non-users template               16 body with a secret inline
     *    17 notification type whose shortname exceeds the target column
     *  local_email_logs  4 rows. 1 and 4 import... 1 sent, 2 never sent, 3 unknown recipient (skipped),
     *    4 account credentials (masked). So 3 import and 1 is skipped.
     *
     * @return void
     */
    private function seed_notifications(): void {
        global $DB;
        $this->ensure_bizlms_schema();
        $t = self::T0;

        $this->ids['a'] = $this->user_at('/1/5');
        $this->ids['b'] = $this->user_at(' 1/5/ ');
        $this->ids['c'] = $this->user_at('/77');
        $this->ids['d'] = $this->user_at('/1/5');
        $DB->set_field('user', 'deleted', 1, ['id' => $this->ids['d']]);
        $this->ids['manager'] = $this->user_at('/1/5');
        $this->ids['sender'] = $this->user_at('/1');
        $this->ids['nopath'] = $this->user_at(null);
        $this->ids['badroot'] = $this->user_at('/999/5');
        $course = $this->getDataGenerator()->create_course();
        $this->ids['course'] = (int) $course->id;

        foreach ([
            1 => ['course_enrol', 'courses'],
            2 => ['users_welcome_email', 'users'],
            3 => ['classroom_reminder', 'classroom'],
            4 => ['course_custom', 'courses'],
            5 => ['long_' . str_repeat('y', 115), 'courses'],
        ] as $id => [$short, $plugin]) {
            $this->legacy_row('local_notification_type', ['id' => $id, 'name' => $short, 'shortname' => $short,
                'pluginname' => $plugin]);
        }
        foreach ([
            1 => [1, '/1', 'Enrolled in [coursename]', 'Hello, you have been enrolled.'],
            2 => [2, '/1', 'Welcome [employee_username]', 'Username: [employee_username] Password: [employee_password]'],
            3 => [3, '/177', 'Class reminder', 'Your class starts soon.'],
            4 => [4, '/1', 'Custom', 'Your temporary password is [employee_password].'],
            5 => [3, '/77', 'Class reminder', 'Your class starts soon.'],
            6 => [5, '/1', 'Long type', 'Body'],
            // Templates the preflight reports: an open_path without its leading slash, and an empty one.
            7 => [1, '1/5', 'No slash', 'Body'],
            8 => [1, '', 'No path', 'Body'],
        ] as $id => [$type, $path, $subject, $body]) {
            $this->legacy_row('local_notification_info', ['id' => $id, 'notificationid' => $type, 'open_path' => $path,
                'subject' => $subject, 'body' => $body, 'costcenterid' => 1, 'timecreated' => $t, 'timemodified' => $t]);
        }

        $mail = function (int $id, int $to, int $info, $status, array $more = []) use ($t): void {
            $this->legacy_row('local_emaillogs', $more + [
                'id' => $id, 'notification_infoid' => $info, 'from_userid' => $this->ids['sender'], 'to_userid' => $to,
                'subject' => 'Subject ' . $id, 'emailbody' => '<p>Body ' . $id . '</p>', 'status' => $status,
                'timecreated' => $t + $id, 'timemodified' => $t + 1000 + $id, 'sent_date' => 0,
            ]);
        };
        $a = $this->ids['a'];
        $mail(1, $a, 1, 1, ['subject' => 'Enrolled in Safety 101', 'emailbody' => '<p>Hello, you have been enrolled.</p>',
            'sent_date' => $t + 10, 'courseid' => $this->ids['course']]);
        $mail(2, $this->ids['b'], 2, 0, ['subject' => 'Welcome jdoe', 'moduletype' => 'users',
            'emailbody' => 'Username: jdoe<br>Password: Zx81!qpL']);
        $mail(3, $this->ids['c'], 1, null, ['subject' => 'Enrolled in Finance 201']);
        $mail(4, $a, 1, 0, ['subject' => 'Reminder due soon', 'timecreated' => 0, 'time_created' => 0,
            'timemodified' => $t + 44]);
        $mail(5, $a, 999, 1, ['subject' => 'Welcome to Airpay Academy - your login details',
            'emailbody' => 'Use user jdoe and password Hunter2x', 'sent_date' => $t + 50]);
        $mail(6, $this->ids['d'], 1, 1, ['sent_date' => $t + 60]);
        $mail(7, $this->ids['manager'], 1, 1, ['subject' => 'Team member enrolled', 'teammemberid' => $a,
            'sent_date' => $t + 70]);
        $mail(8, $a, 1, 1, ['subject' => 'Custom mail', 'courseid' => -1, 'sent_date' => $t + 80]);
        $mail(9, $a, 1, 1, ['courseid' => 999999, 'sent_date' => $t + 90]);
        $mail(10, $a, 3, 1, ['sent_date' => $t + 100]);
        $mail(11, $this->ids['nopath'], 5, 1, ['sent_date' => $t + 110]);
        $mail(12, $this->ids['badroot'], 1, 1, ['sent_date' => $t + 120]);
        $mail(13, 987654, 1, 1, ['sent_date' => $t + 130]);
        $mail(14, $a, 1, 7);
        $mail(15, $a, 4, 1, ['subject' => 'Custom', 'emailbody' => 'Your temporary password is Qw3rty!9.',
            'sent_date' => $t + 150]);
        $mail(16, $a, 1, 1, ['emailbody' => '<p>Your account: password: Sup3rSecret. Bye</p>', 'sent_date' => $t + 160]);
        $mail(17, $a, 6, 1, ['sent_date' => $t + 170]);

        $log = function (int $id, int $to, array $more = []) use ($t): void {
            $this->legacy_row('local_email_logs', $more + [
                'id' => $id, 'notification_infoid' => 0, 'from_userid' => $this->ids['sender'], 'to_userid' => $to,
                'subject' => 'Log ' . $id, 'body_html' => '<p>Log body ' . $id . '</p>', 'sent_date' => 0,
                'created_date' => 0, 'time_created' => 0, 'courseid' => 0,
            ]);
        };
        $log(1, $a, ['subject' => 'ILT reminder', 'body_html' => '<p>Session tomorrow</p>', 'sent_date' => $t + 200,
            'created_date' => $t + 190, 'courseid' => $this->ids['course']]);
        $log(2, $this->ids['b'], ['subject' => 'Custom email', 'body_html' => 'Hello', 'time_created' => $t + 210,
            'courseid' => -1]);
        $log(3, 987654, ['created_date' => $t + 220]);
        $log(4, $this->ids['c'], ['subject' => 'Your account credentials', 'body_html' => 'pw: Tr0ub4dor&3',
            'sent_date' => $t + 230, 'created_date' => $t + 225]);
    }

    // Helpers for what the import wrote.

    /**
     * The imported row a source row became.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @return \stdClass|null
     */
    private function imported(string $sourcetable, int $sourceid): ?\stdClass {
        global $DB;
        $row = $DB->get_record_sql(
            'SELECT l.* FROM {' . self::TARGET . '} l
               JOIN {' . legacymap::TABLE . '} m ON m.targettable = :tt AND m.targetid = l.id
              WHERE m.sourcetable = :st AND m.sourceid = :sid AND m.subkey = :sub',
            ['tt' => self::TARGET, 'st' => $sourcetable, 'sid' => $sourceid, 'sub' => '']);
        return $row ?: null;
    }

    /**
     * @param string $sourcetable
     * @param int $sourceid
     * @return \stdClass The primary map row.
     */
    private function map_row(string $sourcetable, int $sourceid): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['sourcetable' => $sourcetable, 'sourceid' => $sourceid, 'subkey' => ''],
            '*', MUST_EXIST);
    }

    /**
     * A row the import must have written, for the assertions to read.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @return \stdClass
     */
    private function row(string $sourcetable, int $sourceid): \stdClass {
        $row = $this->imported($sourcetable, $sourceid);
        $this->assertNotNull($row, "{$sourcetable}#{$sourceid} was imported");
        return $row;
    }

    /**
     * Summed warnings, tenant methods and outcome counts of the notification steps of a run.
     *
     * @param report $report
     * @param string $section warnings or tenant_methods
     * @return array<string, int>
     */
    private function tally(report $report, string $section): array {
        $out = [];
        foreach ($report->to_array()['features']['notifications']['steps'] ?? [] as $step) {
            foreach ($step[$section] ?? [] as $code => $n) {
                $out[$code] = ($out[$code] ?? 0) + $n;
            }
        }
        return $out;
    }

    /**
     * A hash per legacy table, to prove the import changed none of them.
     *
     * @return array<string, string>
     */
    private function legacy_snapshot(): array {
        global $DB;
        $out = [];
        foreach (['local_emaillogs', 'local_email_logs', 'local_notification_info', 'local_notification_type',
                  'local_notification_strings'] as $table) {
            $rows = array_map(static fn($r) => (array) $r, array_values($DB->get_records($table, null, 'id')));
            $out[$table] = md5(serialize($rows));
        }
        return $out;
    }

    // What is specific to this feature.

    public function test_the_importer_declares_what_the_map_says(): void {
        $importer = new importer();
        $this->assertSame('notifications', $importer->feature());
        $this->assertSame('local_sentientia_emails', $importer->component());
        $this->assertSame([], $importer->depends(), 'the map run order: notifications depends on nothing');
        $this->assertSame([self::TARGET], $importer->target_tables());
        $this->assertSame([], $importer->core_writes(), 'nothing is written to core');
        $this->assertFalse($importer->atomic(), 'the largest local table: batch mode');
        $this->assertSame(['local_emaillogs', 'local_email_logs'], array_keys($importer->sources()));
        $this->assertEqualsCanonicalizing(
            ['local_notification_info', 'local_notification_type', 'local_notification_strings'],
            array_keys($importer->declined_tables()));
        $this->assertGreaterThanOrEqual($importer->requires_version(),
            (int) get_config('local_sentientia_emails', 'version'), 'the installed plugin carries the schema');
    }

    public function test_the_decisions_the_importer_declares_are_accepted_in_the_signed_file(): void {
        $path = \core_component::get_component_directory('local_sentientia_platform')
            . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json';
        $this->assertFileExists($path);
        $signed = decisions::load($path);
        $declared = 0;
        foreach ((new importer())->decisions() as $decision) {
            $declared++;
            $this->assertTrue($signed->has($decision->key), "{$decision->key} is accepted in the signed file");
            if ($decision->allowed !== null) {
                $this->assertContains($signed->get($decision->key), $decision->allowed,
                    "{$decision->key}: the signed value is one the importer accepts");
            }
            $this->assertSame(self::SIGNED[$decision->key], $signed->get($decision->key),
                "{$decision->key}: this test's SIGNED table matches the file");
        }
        $this->assertSame(count(self::SIGNED), $declared);
    }

    public function test_preflight_reports_what_the_import_will_meet_and_blocks_nothing(): void {
        $this->contract_begin();
        $this->seed_notifications();
        $runner = new runner(['decisions' => $this->contract_decisions()]);
        $pf = $runner->preflight(['notifications'])['preflights']['notifications'];
        $this->assertFalse($pf->has_blockers(), implode('; ', $pf->blockers()));
        $warnings = $pf->warnings();
        $this->assertContains('orphan_recipients:local_emaillogs:1', $warnings);
        $this->assertContains('orphan_recipients:local_email_logs:1', $warnings);
        $this->assertContains('sent_to_deleted_recipient:local_emaillogs:1', $warnings);
        $this->assertContains('template_not_found:local_emaillogs:1', $warnings);
        $this->assertContains('credential_rows_users_type:local_emaillogs:1', $warnings);
        $this->assertContains('template_open_path_without_leading_slash:1', $warnings);
        $this->assertContains('template_open_path_empty:1', $warnings);
        $this->assertSame(17, $pf->counts()['rows:notifications.emaillogs']);
        $this->assertSame(4, $pf->counts()['rows:notifications.email_logs']);
        $this->assertSame(['sent (1)' => 13, 'queued (0)' => 2, 'null' => 1, 'other' => 1],
            $pf->histograms()['local_emaillogs.status']);
    }

    public function test_every_seeded_case_is_imported_as_the_map_says(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_notifications();
        $legacybefore = $this->legacy_snapshot();

        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features']['notifications']);

        // One primary map row per source row; two rows are not imported (an unknown recipient in each table).
        $this->assertSame(17, $DB->count_records(legacymap::TABLE, ['sourcetable' => 'local_emaillogs', 'subkey' => '']));
        $this->assertSame(4, $DB->count_records(legacymap::TABLE, ['sourcetable' => 'local_email_logs', 'subkey' => '']));
        $this->assertSame(19, $DB->count_records(self::TARGET));
        $this->assertSame(19, $DB->count_records(legacymap::TABLE, ['outcome' => 'imported']));
        $this->assertSame(2, $DB->count_records(legacymap::TABLE, ['outcome' => 'skipped', 'reason' => 'orphan_user',
            'detail' => 'user_not_found']));

        // Every imported row is marked, never a reminder the engine sent, in the channel BizLMS used.
        foreach ($DB->get_records(self::TARGET) as $row) {
            $this->assertSame(log_step::SOURCE_LABEL, $row->legacy_source);
            $this->assertNull($row->template_key, 'template_key stays NULL: the reminder dedupe must never see it');
            $this->assertNull($row->rule_id);
            $this->assertNull($row->attachment_filename);
            $this->assertNull($row->certificate_issue_id);
            $this->assertSame('email', $row->channel);
        }

        // 1: delivered. Created = sent date (BizLMS stamped the task's time), sender kept, course kept.
        $r = $this->row('local_emaillogs', 1);
        $this->assertSame('sent', $r->status);
        $this->assertNull($r->error_message);
        $this->assertSame($this->ids['a'], (int) $r->userid);
        $this->assertSame($this->ids['sender'], (int) $r->sender_userid);
        $this->assertSame($this->ids['course'], (int) $r->courseid);
        $this->assertSame(1, (int) $r->tenant_id);
        $this->assertSame(self::T0 + 10, (int) $r->timecreated, 'a delivered row is created when it was sent');
        $this->assertSame(self::T0 + 10, (int) $r->timesent);
        $this->assertSame('course_enrol', $r->legacy_type);
        $this->assertSame('Enrolled in Safety 101', $r->subject);
        $this->assertSame('<p>Hello, you have been enrolled.</p>', $r->body_html);

        // 2: a users-module message that was never delivered. Status not_sent; subject masked, body gone.
        $r = $this->row('local_emaillogs', 2);
        $this->assertSame('not_sent', $r->status);
        $this->assertSame(log_step::NOTE_NOT_SENT, $r->error_message);
        $this->assertSame(redactor::SUBJECT_MASK, $r->subject);
        $this->assertNull($r->body_html);
        $this->assertSame('users_welcome_email', $r->legacy_type, 'the type is kept: it names no secret');
        $this->assertNull($r->timesent);
        $this->assertSame(1, (int) $r->tenant_id, 'the recipient path " 1/5/ " normalises to root 1');

        // 3: status NULL is not delivered either. /77 recipient.
        $r = $this->row('local_emaillogs', 3);
        $this->assertSame('not_sent', $r->status);
        $this->assertSame(77, (int) $r->tenant_id);
        $this->assertSame(self::T0 + 3, (int) $r->timecreated);

        // 4: no creation time of its own: the first non-zero of the other dialects, flagged.
        $r = $this->row('local_emaillogs', 4);
        $this->assertSame(self::T0 + 44, (int) $r->timecreated, 'created falls back to timemodified, the only time it has');
        $this->assertSame(0, (int) $DB->get_field('local_emaillogs', 'timecreated', ['id' => 4]),
            'sanity: the source row has no creation time of its own');

        // 5: the template is gone and the subject reads like an account message: withheld.
        $r = $this->row('local_emaillogs', 5);
        $this->assertSame(redactor::SUBJECT_MASK, $r->subject);
        $this->assertNull($r->body_html);
        $this->assertNull($r->legacy_type, 'an unresolvable template has no type');
        $this->assertSame('sent', $r->status);

        // 6: BizLMS marked a deleted recipient sent without sending: kept sent, with the note.
        $r = $this->row('local_emaillogs', 6);
        $this->assertSame('sent', $r->status);
        $this->assertSame(log_step::NOTE_DELETED_RECIPIENT, $r->error_message);
        $this->assertSame($this->ids['d'], (int) $r->userid, 'history of a deleted user is imported (R11)');

        // 7: a manager copy: the row is the manager's.
        $r = $this->row('local_emaillogs', 7);
        $this->assertSame($this->ids['manager'], (int) $r->userid);

        // 8 and 9: courseid -1 (custom mail) and a course that does not exist become NULL.
        $this->assertNull($this->row('local_emaillogs', 8)->courseid);
        $this->assertNull($this->row('local_emaillogs', 9)->courseid);

        // 10: the recipient is in /1, the template is for /177: the recipient decides.
        $this->assertSame(1, (int) $this->row('local_emaillogs', 10)->tenant_id);

        // 11: the recipient has no path, so the template's is used, and the report says so.
        $this->assertSame(77, (int) $this->row('local_emaillogs', 11)->tenant_id);

        // 12: the recipient's root is not a tenant: NOT filed under the template's tenant. Pathless.
        $this->assertSame(0, (int) $this->row('local_emaillogs', 12)->tenant_id);

        // 13: the recipient does not exist: stays in the legacy table.
        $this->assertNull($this->imported('local_emaillogs', 13));
        $this->assertSame('skipped', $this->map_row('local_emaillogs', 13)->outcome);

        // 14: any other status is not delivered.
        $this->assertSame('not_sent', $this->row('local_emaillogs', 14)->status);

        // 15: the password placeholder in a template that is not of the users module.
        $r = $this->row('local_emaillogs', 15);
        $this->assertSame(redactor::SUBJECT_MASK, $r->subject);
        $this->assertNull($r->body_html);

        // 16: an ordinary message with a secret written into the text: scrubbed, the rest kept.
        $r = $this->row('local_emaillogs', 16);
        $this->assertStringNotContainsString('Sup3rSecret', $r->body_html);
        $this->assertStringContainsString(redactor::MASK, $r->body_html);
        $this->assertStringContainsString('Bye', $r->body_html);
        $this->assertSame('Subject 16', $r->subject);

        // 17: a legacy type name longer than the target column is cut, and says so.
        $r = $this->row('local_emaillogs', 17);
        $this->assertSame(100, \core_text::strlen($r->legacy_type));

        // local_email_logs: delivered when sent_date is set; created_date first, then time_created.
        $r = $this->row('local_email_logs', 1);
        $this->assertSame('sent', $r->status);
        $this->assertSame(self::T0 + 190, (int) $r->timecreated);
        $this->assertSame(self::T0 + 200, (int) $r->timesent);
        $this->assertSame($this->ids['course'], (int) $r->courseid);
        $r = $this->row('local_email_logs', 2);
        $this->assertSame('not_sent', $r->status);
        $this->assertSame(self::T0 + 210, (int) $r->timecreated);
        $this->assertNull($r->courseid, 'courseid -1 is a custom mail');
        $this->assertSame('Custom email', $r->subject);
        $r = $this->row('local_email_logs', 4);
        $this->assertSame(redactor::SUBJECT_MASK, $r->subject);
        $this->assertNull($r->body_html);
        $this->assertNull($this->imported('local_email_logs', 3));

        // Nothing we wrote, read or report carries a secret, in any column of the target.
        foreach ($DB->get_records(self::TARGET) as $row) {
            $text = implode("\n", [$row->subject, (string) $row->body_html, (string) $row->error_message]);
            foreach (['Zx81!qpL', 'Hunter2x', 'Qw3rty!9', 'Sup3rSecret', 'Tr0ub4dor', redactor::PASSWORD_PLACEHOLDER] as $secret) {
                $this->assertStringNotContainsString($secret, $text, 'no secret in the log');
            }
        }

        // The report: the warnings are codes, and the tenant methods say how each tenant was decided.
        $warnings = $this->tally($report, 'warnings');
        $this->assertSame(1, $warnings['credentials_withheld:users_module'] ?? 0, 'local_emaillogs#2');
        $this->assertSame(2, $warnings['credentials_withheld:unresolved_template'] ?? 0,
            'local_emaillogs#5 and local_email_logs#4');
        $this->assertSame(1, $warnings['credentials_withheld:template_placeholder'] ?? 0);
        $this->assertSame(1, $warnings['credential_text_scrubbed'] ?? 0);
        $this->assertSame(1, $warnings['truncated:legacy_type'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $warnings['derived_timestamp'] ?? 0);
        $methods = $this->tally($report, 'tenant_methods');
        foreach (['exact', 'normalised', 'fallback:template', 'unresolved'] as $method) {
            $this->assertArrayHasKey($method, $methods, "tenant method {$method} was counted");
        }

        // The legacy tables are the archive: not one of them changed.
        $this->assertSame($legacybefore, $this->legacy_snapshot());

        // The importer's own verify passes on what it wrote.
        $verify = (new runner(['decisions' => $this->contract_decisions()]))->verify(['notifications']);
        $this->assertSame(0, $verify['exit'], implode('; ', $verify['failures']['notifications'] ?? []));
    }

    public function test_the_queue_is_never_sent_and_keeps_its_status_under_noemailever(): void {
        global $CFG, $DB;
        $this->contract_begin();
        $this->seed_notifications();
        $CFG->noemailever = true;
        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();
        $counts = [];
        foreach (['notifications', 'messages', 'task_adhoc'] as $table) {
            $counts[$table] = $DB->count_records($table);
        }

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        foreach ([['local_emaillogs', 2], ['local_emaillogs', 3], ['local_emaillogs', 4], ['local_emaillogs', 14],
                  ['local_email_logs', 2]] as [$table, $id]) {
            $this->assertSame('not_sent', $this->row($table, $id)->status,
                "{$table}#{$id}: a queue row stays not sent; under noemailever a send would have said suppressed");
        }
        $this->assertSame(0, $DB->count_records(self::TARGET, ['status' => 'suppressed']));
        $this->assertSame(0, $DB->count_records(self::TARGET, ['status' => 'failed']), 'the dashboard failure tile stays 0');
        $this->assertSame(0, $events->count());
        $this->assertSame(0, $messages->count());
        $this->assertSame(0, $emails->count());
        foreach ($counts as $table => $before) {
            $this->assertSame($before, $DB->count_records($table), "{$table} is untouched");
        }
        if ($DB->get_manager()->table_exists('local_sentientia_notif_log')) {
            $this->assertSame(0, $DB->count_records('local_sentientia_notif_log'), 'the navbar list is untouched');
        }
    }

    public function test_no_imported_row_counts_as_a_reminder_the_engine_sent(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_notifications();
        $this->contract_run(true);

        // The native engine restamps "sent" reminders of a course when the learner completes it. An imported row
        // has no template_key, and the query also excludes legacy_source rows, so none is touched.
        $imported = $this->row('local_emaillogs', 1);
        $DB->set_field(self::TARGET, 'template_key', 'reminders/not_started', ['id' => $imported->id]);
        $DB->set_field(self::TARGET, 'status', 'sent', ['id' => $imported->id]);
        $native = (int) $DB->insert_record(self::TARGET, (object) [
            'userid' => $imported->userid, 'courseid' => $imported->courseid, 'tenant_id' => 1, 'channel' => 'email',
            'subject' => 'Native reminder', 'template_key' => 'reminders/not_started', 'status' => 'sent',
            'timecreated' => self::T0,
        ]);

        delivery_log::mark_reminders_suppressed_on_completion((int) $imported->userid, (int) $imported->courseid);
        $this->assertSame('suppressed_completion', $DB->get_field(self::TARGET, 'status', ['id' => $native]),
            'the native reminder is restamped');
        $this->assertSame('sent', $DB->get_field(self::TARGET, 'status', ['id' => $imported->id]),
            'the imported row, even with a template key put on it, is history');
    }

    public function test_the_reminder_engine_queries_exclude_imported_rows(): void {
        // The four dedupe and cap queries of the cron task must all carry the guard. Their SQL is inside a task
        // that needs a whole course fixture to run, so this pins the source: if a guard is removed, an imported
        // row starts to count as a reminder the new engine sent (and learners stop getting real reminders).
        $source = file_get_contents(__DIR__ . '/../classes/task/process_rules.php');
        $this->assertSame(4, substr_count($source, 'legacy_source IS NULL'),
            'process_rules.php: two dedupe queries, the per-course cap and the once-a-day check');
    }

    public function test_body_import_off_sender_off_and_suppressed_variants(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_notifications();
        $this->decisionvalues['notifications.import_bodies'] = false;
        $this->decisionvalues['notifications.keep_sender'] = false;
        $this->decisionvalues['notifications.deleted_recipient_sent'] = 'suppressed';
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $this->assertSame(0, $DB->count_records_select(self::TARGET, 'body_html IS NOT NULL'), 'no body is imported');
        $this->assertSame(0, $DB->count_records_select(self::TARGET, 'sender_userid IS NOT NULL'), 'no sender is imported');
        $r = $this->row('local_emaillogs', 6);
        $this->assertSame('suppressed', $r->status);
        $this->assertSame(log_step::NOTE_DELETED_RECIPIENT, $r->error_message);
        $this->assertSame('Enrolled in Safety 101', $this->row('local_emaillogs', 1)->subject, 'subjects are still imported');
        $verify = (new runner(['decisions' => $this->contract_decisions()]))->verify(['notifications']);
        $this->assertSame(0, $verify['exit'], implode('; ', $verify['failures']['notifications'] ?? []));
    }

    public function test_a_row_whose_tenant_cannot_be_resolved_is_skipped_when_the_owner_says_so(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_notifications();
        $this->decisionvalues['tenant.unresolved.notifications'] = 'skip';
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $this->assertNull($this->imported('local_emaillogs', 12));
        $map = $this->map_row('local_emaillogs', 12);
        $this->assertSame('skipped', $map->outcome);
        $this->assertSame('tenant_unresolved', $map->reason);
        $this->assertSame(0, $DB->count_records_select(self::TARGET, 'tenant_id = 0'), 'nothing is filed under no tenant');
    }

    public function test_the_import_cannot_run_without_the_owners_choice(): void {
        $this->contract_begin();
        $this->seed_notifications();
        $this->decisionvalues = [];
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('missing_decision:notifications.import_bodies', implode(' ', $result['blockers']));
        $this->assertFalse(legacymap::feature_complete('notifications'));
    }

    public function test_a_value_the_importer_does_not_know_blocks_the_feature(): void {
        $this->contract_begin();
        $this->seed_notifications();
        $this->decisionvalues['notifications.queue_status'] = 'failed';
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit'], 'never failed: it would light the dashboard failure tile');
        $this->assertStringContainsString('decision_value_not_allowed:notifications.queue_status',
            implode(' ', $result['blockers']));
    }

    public function test_verify_catches_a_credential_that_got_through(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_notifications();
        $this->contract_run(true);
        $clean = (new runner(['decisions' => $this->contract_decisions()]))->verify(['notifications']);
        $this->assertSame(0, $clean['exit'], implode('; ', $clean['failures']['notifications'] ?? []));

        $row = $this->row('local_emaillogs', 1);
        $DB->set_field(self::TARGET, 'body_html', 'Your password is ' . redactor::PASSWORD_PLACEHOLDER, ['id' => $row->id]);
        $DB->set_field(self::TARGET, 'template_key', 'reminders/x', ['id' => $row->id]);
        $masked = $this->row('local_emaillogs', 2);
        $DB->set_field(self::TARGET, 'body_html', '<p>still here</p>', ['id' => $masked->id]);

        $bad = (new runner(['decisions' => $this->contract_decisions()]))->verify(['notifications']);
        $this->assertSame(1, $bad['exit']);
        $failures = implode(' ', $bad['failures']['notifications']);
        $this->assertStringContainsString('imported_body_carries_the_password_placeholder', $failures);
        $this->assertStringContainsString('imported_row_carries_a_template_key_or_rule', $failures);
        $this->assertStringContainsString('withheld_subject_with_a_body', $failures);
    }

    public function test_a_table_without_the_optional_columns_still_imports(): void {
        global $DB;
        $this->contract_begin();
        // courseid and time_created are read by the sender task but declared by no install file.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_emaillogs');
        $dbman->drop_index($table, new \xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']));
        $dbman->drop_field($table, new \xmldb_field('courseid'));
        $dbman->drop_field($table, new \xmldb_field('time_created'));
        $this->seed_notifications();

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame(19, $DB->count_records(self::TARGET));
        $this->assertNull($this->row('local_emaillogs', 1)->courseid, 'there is no course column to read');
        $this->assertSame(self::T0 + 44, (int) $this->row('local_emaillogs', 4)->timecreated);
    }

    public function test_a_recipient_without_a_tenant_root_is_pathless_not_filed_under_another_tenant(): void {
        global $DB;
        $this->contract_begin();
        $this->seed_notifications();
        // A recipient whose path does not parse is "no path": the template's root is then the fallback.
        $broken = $this->user_at('not/a/path');
        $this->legacy_row('local_emaillogs', ['id' => 50, 'notification_infoid' => 5, 'from_userid' => $this->ids['sender'],
            'to_userid' => $broken, 'subject' => 'Broken path', 'status' => 1, 'sent_date' => self::T0 + 500,
            'timecreated' => self::T0 + 500]);
        $this->contract_run(true);
        $this->assertSame(77, (int) $this->row('local_emaillogs', 50)->tenant_id, 'template of /77 is the fallback');
        // ... while a recipient with a real path that is not a tenant is never filed under the template's.
        $this->assertSame(0, (int) $this->row('local_emaillogs', 12)->tenant_id);
        $this->assertSame(1, $DB->count_records(self::TARGET, ['tenant_id' => 0]));
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tests\bizlms\static_scanner;

/**
 * The static scan of every classes/bizlms/ file (ADR-032, "Side-effect safety" 4).
 *
 * The first test is the gate: it scans the real source of the framework and of
 * every importer plugin on disk and fails on a banned construct. The others
 * prove the scanner itself: it catches each banned construct, and it does not
 * fire on comments, docblocks or strings (a guard that fires on its own
 * documentation gets switched off).
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\tests\bizlms\static_scanner
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_static_scan_test extends \advanced_testcase {

    public function test_every_bizlms_source_file_is_clean(): void {
        global $CFG;
        $files = glob($CFG->dirroot . '/local/*/classes/bizlms/*.php') ?: [];
        $this->assertGreaterThan(25, count($files), 'the scan found suspiciously few files; an empty scan proves nothing');

        $findings = [];
        foreach ($files as $file) {
            $normalised = str_replace('\\', '/', $file);
            $framework = strpos($normalised, '/local/sentientia_platform/classes/bizlms/') !== false;
            $iswriter = $framework && basename($file) === 'writer.php';
            foreach (static_scanner::scan((string) file_get_contents($file), $framework, $iswriter) as $finding) {
                $findings[] = basename(dirname(dirname(dirname($file)))) . '/' . basename($file) . ' ' . $finding;
            }
        }
        $this->assertSame([], $findings, "banned constructs in classes/bizlms:\n  - " . implode("\n  - ", $findings));
    }

    /**
     * @dataProvider banned_provider
     * @param string $code Body of a method, in an importer (not the framework).
     * @param string $needle Fragment the finding must contain.
     */
    public function test_scanner_catches_every_banned_construct(string $code, string $needle): void {
        $findings = static_scanner::scan("<?php\nclass x {\n    public function go() {\n        {$code}\n    }\n}\n", false, false);
        $this->assertNotEmpty($findings, "not caught: {$code}");
        $this->assertStringContainsString($needle, implode(' ', $findings));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function banned_provider(): array {
        return [
            'message_send' => ['message_send($m);', 'message_send'],
            'email_to_user' => ['email_to_user($a, $b, $c, $d);', 'email_to_user'],
            'event trigger' => ['$event->trigger();', '->trigger'],
            'role_assign' => ['role_assign(1, 2, 3);', 'role_assign'],
            'enrol_user' => ['$plugin->enrol_user($i, 1, 5);', 'enrol_user'],
            'enrol_try_internal_enrol' => ['enrol_try_internal_enrol(1, 2);', 'enrol_try_internal_enrol'],
            'completion_completion' => ['$c = new \\completion_completion(["course" => 1]);', 'completion_completion'],
            'mark_complete' => ['$c->mark_complete();', 'mark_complete'],
            'cohort_add_member' => ['cohort_add_member(1, 2);', 'cohort_add_member'],
            'core_tag_tag' => ['\\core_tag_tag::add_item_tag("a", "b", 1, $ctx, "x");', 'core_tag_tag'],
            'update_course' => ['update_course($data);', 'update_course'],
            'calendar_event create' => ['\\calendar_event::create($e);', 'calendar_event::create'],
            'get_recordset_sql' => ['$rs = $DB->get_recordset_sql($sql);', 'get_recordset_sql'],
            'get_recordset' => ['$rs = $DB->get_recordset("t");', 'get_recordset'],
            'get_records outside the framework' => ['$rows = $DB->get_records("t");', 'get_records'],
            'session_manager' => ['\\local_sentientia_classroom\\session_manager::create_session($x);', 'session_manager'],
            'waitlist_manager' => ['waitlist_manager::promote(1);', 'waitlist_manager'],
            'path_manager' => ['\\local_sentientia_learningpath\\path_manager::enrol(1, 2);', 'path_manager'],
            'program_manager' => ['program_manager::x();', 'program_manager'],
            'request_manager' => ['request_manager::x();', 'request_manager'],
            'cart_manager' => ['cart_manager::x();', 'cart_manager'],
            'invoicer' => ['invoicer::x();', 'invoicer'],
            'notifier' => ['notifier::x();', 'notifier'],
            'delivery_log log' => ['delivery_log::log($x);', 'delivery_log::log'],
            'evaluation_manager submit_response' => ['evaluation_manager::submit_response($x);', 'submit_response'],
            'recompletion_engine' => ['recompletion_engine::x();', 'recompletion_engine'],
            'skills_manager' => ['skills_manager::x();', 'skills_manager'],
            'rating_manager submit_rating' => ['rating_manager::submit_rating($x);', 'submit_rating'],
            'DB insert_record' => ['$DB->insert_record("t", $r);', 'insert_record'],
            'DB update_record' => ['$DB->update_record("t", $r);', 'update_record'],
            'DB delete_records' => ['$DB->delete_records("t");', 'delete_records'],
            'DB set_field' => ['$DB->set_field("t", "a", 1);', 'set_field'],
            'DB execute' => ['$DB->execute("x");', 'execute'],
            'DDL add_field' => ['$dbman->add_field($t, $f);', 'add_field'],
            'DDL create_table' => ['$DB->get_manager()->create_table($t);', 'create_table'],
            'reset_sequence outside finalise' => ['$dbman->reset_sequence("t");', 'reset_sequence'],
        ];
    }

    public function test_scanner_allows_reset_sequence_only_inside_finalise(): void {
        $finalise = "<?php\nclass x {\n    public function finalise() {\n        \$dbman->reset_sequence('t');\n    }\n}\n";
        $this->assertSame([], static_scanner::scan($finalise, false, false));

        $other = "<?php\nclass x {\n    public function finalise() {\n        \$a = 1;\n    }\n    public function later() {\n"
            . "        \$dbman->reset_sequence('t');\n    }\n}\n";
        $findings = static_scanner::scan($other, false, false);
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('line 7', $findings[0]);
    }

    public function test_scanner_lets_the_framework_read_with_get_records_and_the_writer_write(): void {
        $read = "<?php\nclass x {\n    public function go() {\n        \$DB->get_records('t');\n    }\n}\n";
        $this->assertSame([], static_scanner::scan($read, true, false));
        $write = "<?php\nclass x {\n    public function go() {\n        \$DB->insert_record('t', \$r);\n        \$DB->get_manager()->reset_sequence('t');\n    }\n}\n";
        $this->assertSame([], static_scanner::scan($write, true, true));
        $this->assertNotEmpty(static_scanner::scan($write, true, false), 'outside writer.php the same code is a finding');
    }

    public function test_scanner_ignores_comments_docblocks_and_strings(): void {
        $code = <<<'PHP'
<?php
/**
 * Never call message_send, email_to_user or $event->trigger(), and never session_manager::create_session().
 * $DB->insert_record() and $DB->get_recordset_sql() are banned too.
 */
class x {
    // $DB->delete_records('t'); role_assign(1, 2, 3);
    public function go() {
        $text = 'message_send( $DB->insert_record( ->trigger( get_recordset';
        return "session_manager::x() {$text}";
    }
}
PHP;
        $this->assertSame([], static_scanner::scan($code, false, false));
    }
}

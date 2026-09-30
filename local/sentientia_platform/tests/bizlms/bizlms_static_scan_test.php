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
 * every importer, in every plugin type and in every sub-directory of classes/bizlms/,
 * and fails on a banned construct. The others prove the scanner itself: it catches
 * each banned construct, including the ways a call can be dressed up (an alias of
 * $DB, a property, $GLOBALS['DB'], ?->, a dynamic method name, call_user_func), and
 * it does not fire on comments, docblocks or strings (a guard that fires on its own
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

    /**
     * Every PHP file under classes/bizlms/ of every installed plugin, sub-directories included.
     *
     * @return array<int, array{file: string, label: string}>
     */
    private static function bizlms_source_files(): array {
        $files = [];
        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            foreach (\core_component::get_plugin_list($type) as $name => $plugindir) {
                $dir = $plugindir . '/classes/bizlms';
                if (!is_dir($dir)) {
                    continue;
                }
                foreach (static_scanner::php_files($dir) as $path) {
                    $files[] = [
                        'file' => $path,
                        'label' => $type . '_' . $name . '/' . substr(str_replace('\\', '/', $path),
                            strlen(str_replace('\\', '/', $plugindir)) + 1),
                    ];
                }
            }
        }
        usort($files, fn(array $a, array $b): int => strcmp($a['file'], $b['file']));
        return $files;
    }

    public function test_every_bizlms_source_file_is_clean(): void {
        $files = self::bizlms_source_files();
        $this->assertGreaterThan(25, count($files), 'the scan found suspiciously few files; an empty scan proves nothing');

        $findings = [];
        foreach ($files as $item) {
            $normalised = str_replace('\\', '/', $item['file']);
            $framework = strpos($normalised, '/local/sentientia_platform/classes/bizlms/') !== false;
            $iswriter = str_ends_with($normalised, '/local/sentientia_platform/classes/bizlms/writer.php');
            foreach (static_scanner::scan((string) file_get_contents($item['file']), $framework, $iswriter) as $finding) {
                $findings[] = $item['label'] . ' ' . $finding;
            }
        }
        $this->assertSame([], $findings, "banned constructs in classes/bizlms:\n  - " . implode("\n  - ", $findings));
    }

    public function test_the_scan_reaches_sub_directories(): void {
        // A flat glob of classes/bizlms/*.php would have skipped everything below the first level.
        $root = make_request_directory();
        check_dir_exists($root . '/nested/deeper');
        file_put_contents($root . '/top.php', '<?php');
        file_put_contents($root . '/nested/x.php', '<?php');
        file_put_contents($root . '/nested/deeper/y.php', '<?php');
        file_put_contents($root . '/nested/readme.txt', 'not php');

        $found = array_map(fn(string $path): string => substr(str_replace('\\', '/', $path), strlen($root) + 1),
            static_scanner::php_files($root));
        $this->assertSame(['nested/deeper/y.php', 'nested/x.php', 'top.php'], $found);
        $this->assertSame([], static_scanner::php_files($root . '/missing'));
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
            'role_unassign' => ['role_unassign(1, 2, 3);', 'role_unassign'],
            'role_unassign_all' => ['role_unassign_all(["roleid" => 1]);', 'role_unassign_all'],
            'enrol_user' => ['$plugin->enrol_user($i, 1, 5);', 'enrol_user'],
            'unenrol_user' => ['$plugin->unenrol_user($i, 5);', 'unenrol_user'],
            'enrol_try_internal_enrol' => ['enrol_try_internal_enrol(1, 2);', 'enrol_try_internal_enrol'],
            'delete_user' => ['delete_user($user);', 'delete_user'],
            'groups_add_member' => ['groups_add_member(1, 2);', 'groups_add_member'],
            'completion_completion' => ['$c = new \\completion_completion(["course" => 1]);', 'completion_completion'],
            'completion_info' => ['$c = new \\completion_info($course);', 'completion_info'],
            'update_state' => ['$info->update_state($cm, COMPLETION_COMPLETE, 5);', 'update_state'],
            'mark_complete' => ['$c->mark_complete();', 'mark_complete'],
            'cohort_add_member' => ['cohort_add_member(1, 2);', 'cohort_add_member'],
            'core_tag_tag' => ['\\core_tag_tag::add_item_tag("a", "b", 1, $ctx, "x");', 'core_tag_tag'],
            'update_course' => ['update_course($data);', 'update_course'],
            'calendar_event create' => ['\\calendar_event::create($e);', 'calendar_event::create'],
            'grade_update' => ['grade_update("x", 1, 2, 3, 4, $g);', 'grade_update'],
            'grade_regrade_final_grades' => ['grade_regrade_final_grades(1);', 'grade_regrade_final_grades'],
            'grade_item' => ['$i = \\grade_item::fetch(["id" => 1]);', 'grade_item'],
            'queue_adhoc_task' => ['\\core\\task\\manager::queue_adhoc_task($task);', 'queue_adhoc_task'],
            'reschedule_or_queue_adhoc_task' => ['manager::reschedule_or_queue_adhoc_task($task);', 'reschedule_or_queue_adhoc_task'],
            'feature_flags set' => ['\\local_sentientia_platform\\feature_flags::set("k", 0, true);', 'feature_flags::set'],
            'call_user_func hides a call' => ['call_user_func([$DB, "insert_record"], "t", $r);', 'call_user_func'],
            'call_user_func_array' => ['call_user_func_array($f, []);', 'call_user_func_array'],
            'set_config outside finalise' => ['set_config("k", 1, "local_x");', 'set_config'],
            'unset_config outside finalise' => ['unset_config("k", "local_x");', 'unset_config'],
            'purge_all_caches outside finalise' => ['purge_all_caches();', 'purge_all_caches'],
            'purge_caches outside finalise' => ['purge_caches(["theme" => true]);', 'purge_caches'],
            'rebuild_course_cache outside finalise' => ['rebuild_course_cache(1, true);', 'rebuild_course_cache'],
            'cache_helper outside finalise' => ['\\cache_helper::purge_by_definition("core", "string");', 'cache_helper'],
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
            'DB delete_records_subquery' => ['$DB->delete_records_subquery("t", "id", "id", "SELECT 1");', 'delete_records_subquery'],
            'DB set_field' => ['$DB->set_field("t", "a", 1);', 'set_field'],
            'DB execute' => ['$DB->execute("x");', 'execute'],
            'write through an alias of DB' => ['$h = $DB; $h->insert_record("t", $r);', 'insert_record'],
            'write through a reference to DB' => ['$h =& $DB; $h->execute("x");', 'execute'],
            'write through a property' => ['$this->db->delete_records("t");', 'delete_records'],
            'execute through a property' => ['$this->db->execute("x");', 'execute'],
            'write through GLOBALS' => ['$GLOBALS["DB"]->update_record("t", $r);', 'update_record'],
            'execute through GLOBALS' => ['$GLOBALS["DB"]->execute("x");', 'execute'],
            'write through an alias taken from GLOBALS' => ['$h = $GLOBALS["DB"]; $h->execute("x");', 'execute'],
            'write through the nullsafe operator' => ['$DB?->set_field("t", "a", 1);', 'set_field'],
            'execute through the nullsafe operator' => ['$DB?->execute("x");', 'execute'],
            'write through a moodle_database parameter' => ['$f = function (\\moodle_database $h) { $h->execute("x"); };', 'execute'],
            'write on some other object' => ['$repository->insert_record("t", $r);', 'insert_record'],
            'dynamic method name' => ['$m = "insert_record"; $DB->$m("t", $r);', 'dynamic method call'],
            'dynamic method name in braces' => ['$DB->{"insert" . "_record"}("t", $r);', 'dynamic method call'],
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

    public function test_scanner_allows_config_and_cache_purges_inside_finalise_and_never_a_flag_flip(): void {
        $ok = "<?php\nclass x {\n    public function finalise(\$ctx) {\n        set_config('k', 1, 'local_x');\n"
            . "        purge_all_caches();\n        \\cache_helper::purge_by_definition('core', 'string');\n    }\n}\n";
        $this->assertSame([], static_scanner::scan($ok, false, false));

        $flip = "<?php\nclass x {\n    public function finalise(\$ctx) {\n"
            . "        \\local_sentientia_platform\\feature_flags::set('sentientia.live.polls', 0, true);\n    }\n}\n";
        $findings = static_scanner::scan($flip, false, false);
        $this->assertCount(1, $findings, 'the import never flips a flag, not even in finalise()');
        $this->assertStringContainsString('feature_flags::set', $findings[0]);
    }

    public function test_scanner_does_not_fire_on_look_alikes(): void {
        $code = <<<'PHP'
<?php
class x {
    public function set_config_value() {
        $stmt = $connection->execute($query);
        $rows = $this->store->execute();
        $grade = 5;
        $x = $config->purge_state;
        return $this->execute_later($grade);
    }
    public function grade_label() {
        return 'x';
    }
}
PHP;
        $this->assertSame([], static_scanner::scan($code, false, false));
    }

    public function test_scanner_lets_the_framework_read_with_get_records_and_the_writer_write(): void {
        $read = "<?php\nclass x {\n    public function go() {\n        \$DB->get_records('t');\n    }\n}\n";
        $this->assertSame([], static_scanner::scan($read, true, false));
        $write = "<?php\nclass x {\n    public function go() {\n        \$DB->insert_record('t', \$r);\n        \$DB->get_manager()->reset_sequence('t');\n        set_config('k', 1, 'c');\n    }\n}\n";
        $this->assertSame([], static_scanner::scan($write, true, true));
        $this->assertNotEmpty(static_scanner::scan($write, true, false), 'outside writer.php the same code is a finding');
    }

    public function test_scanner_ignores_comments_docblocks_and_strings(): void {
        $code = <<<'PHP'
<?php
/**
 * Never call message_send, email_to_user or $event->trigger(), and never session_manager::create_session().
 * $DB->insert_record() and $DB->get_recordset_sql() are banned too. grade_update() and set_config() as well.
 */
class x {
    // $DB->delete_records('t'); role_assign(1, 2, 3); purge_all_caches();
    public function go() {
        $text = 'message_send( $DB->insert_record( ->trigger( get_recordset grade_update( completion_info';
        return "session_manager::x() {$text}";
    }
}
PHP;
        $this->assertSame([], static_scanner::scan($code, false, false));
    }
}

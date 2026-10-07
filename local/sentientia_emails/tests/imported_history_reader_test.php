<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_org\test\bizlms_fixture;
use local_sentientia_platform\feature_flags;

/**
 * What the notification log shows of the BizLMS history the importer wrote (ADR-032, mapping doc section 11,
 * code fixes 2 to 6).
 *
 * Every reader change ships behind a default-OFF flag, and ADR-031 still holds: a scoped tenant admin reads only
 * their own tenant, and the rows whose tenant could not be resolved (tenant_id 0) are for cross-tenant callers.
 * These rows are inserted directly; the importer that writes them is tested in bizlms_import_test.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @covers     \local_sentientia_emails\imported_history
 * @covers     \local_sentientia_emails\delivery_log
 * @covers     \local_sentientia_emails\manage_controller
 * @covers     \local_sentientia_emails\legacy_bridge
 *
 * @group local_sentientia_emails
 * @group bizlms_import
 * @group tenant_isolation
 */
final class imported_history_reader_test extends \advanced_testcase {
    use bizlms_fixture;

    private const LOG = 'local_sentientia_email_log';

    /** @var \xmldb_table[] Temporary BizLMS tables this test created; dropped in tearDown(). */
    private array $temptables = [];

    /** @var \stdClass The recipient of every row. */
    private \stdClass $recipient;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        $this->recipient = $this->getDataGenerator()->create_user(['email' => 'recipient@example.com']);
    }

    protected function tearDown(): void {
        global $DB;
        foreach ($this->temptables as $table) {
            $DB->get_manager()->drop_table($table);
        }
        $this->temptables = [];
        try {
            feature_flags::set(imported_history::FLAG_HISTORY, 0, null);
            feature_flags::set(imported_history::FLAG_BODY, 0, null);
        } catch (\Throwable $e) {
            // The database is reset after the test anyway; a flag left behind cannot outlive it.
            debugging('could not unset the test flags: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        parent::tearDown();
    }

    /**
     * @param string $path
     * @return \stdClass A user at a tenant path holding the manager role at system context (a tenant admin).
     */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A row Sentientia wrote itself.
     *
     * @param int $tenant
     * @param array $more
     * @return int
     */
    private function native(int $tenant, array $more = []): int {
        global $DB;
        return (int) $DB->insert_record(self::LOG, (object) ($more + [
            'userid' => $this->recipient->id, 'tenant_id' => $tenant, 'channel' => 'email', 'subject' => 'Native ' . $tenant,
            'template_key' => 'reminders/not_started', 'status' => 'sent', 'timecreated' => time(),
        ]));
    }

    /**
     * A row the importer would have written.
     *
     * @param int $tenant
     * @param array $more
     * @return int
     */
    private function imported(int $tenant, array $more = []): int {
        global $DB;
        return (int) $DB->insert_record(self::LOG, (object) ($more + [
            'userid' => $this->recipient->id, 'tenant_id' => $tenant, 'channel' => 'email',
            'subject' => 'Imported ' . $tenant, 'legacy_type' => 'course_enrol', 'status' => 'sent',
            'timecreated' => 1700000000, 'legacy_source' => 'bizlms', 'timesent' => 1700000100,
            'body_html' => '<p>Body of the imported message</p>', 'sender_userid' => $this->recipient->id,
        ]));
    }

    /**
     * Turn the two reader flags ON or OFF for every customer and tenant.
     *
     * @param bool $history sentientia.emails.imported_history.enabled
     * @param bool $body sentientia.emails.imported_body_detail.enabled
     * @return void
     */
    private function flags(bool $history, bool $body): void {
        feature_flags::set(imported_history::FLAG_HISTORY, 0, $history);
        feature_flags::set(imported_history::FLAG_BODY, 0, $body);
    }

    /**
     * @param array $filters
     * @return int[] Ids the log lists.
     */
    private function listed(array $filters = []): array {
        $ids = array_map(static fn($r) => (int) $r->id, delivery_log::get_logs($filters, 0, 500)->records);
        sort($ids);
        return $ids;
    }

    public function test_the_flags_ship_off(): void {
        $this->assertFalse(imported_history::history_enabled());
        $this->assertFalse(imported_history::body_enabled());
        $registry = feature_flags::load_registry();
        foreach ([imported_history::FLAG_HISTORY, imported_history::FLAG_BODY] as $key) {
            $this->assertArrayHasKey($key, $registry, "{$key} is registered in db/feature_flags.php");
            $this->assertFalse($registry[$key]['default'], "{$key} defaults to OFF");
        }
    }

    public function test_the_log_has_an_index_on_sender_userid(): void {
        global $DB;
        // F-64: the privacy provider looks a sender up on export and on erasure; without the index that scans the log.
        $this->assertTrue($DB->get_manager()->index_exists(new \xmldb_table(self::LOG),
            new \xmldb_index('idx_sender_userid', XMLDB_INDEX_NOTUNIQUE, ['sender_userid'])));
    }

    public function test_the_body_flag_does_nothing_without_the_history_flag(): void {
        $this->flags(false, true);
        $this->assertFalse(imported_history::body_enabled(),
            'a row the list does not show must not be reachable by id either');
        $this->assertNull(delivery_log::get_imported_detail($this->imported(1)));
    }

    public function test_with_the_flag_off_the_log_is_what_it_was_before_the_import(): void {
        $native = $this->native(1);
        $imported = $this->imported(1);
        $pathless = $this->imported(0);

        $this->assertSame([$native], $this->listed());
        $this->assertSame(1, (int) delivery_log::get_stats(0)->total, 'the tiles count only what Sentientia wrote');
        $this->assertSame(0, (int) delivery_log::get_stats(0)->failed);

        $lines = [];
        $written = delivery_log::stream_csv([], static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });
        $this->assertSame(1, $written);
        $this->assertSame("ID,Date,User,Email,Tenant,Channel,Subject,Template,Status,Error\n", $lines[0],
            'the export has the columns it had');
        $this->assertStringNotContainsString('Imported', implode('', $lines));
        $this->assertStringNotContainsString('LegacyType', implode('', $lines));

        $data = manage_controller::get_logs_data([], 0, 50);
        $this->assertFalse($data['show_imported']);
        $this->assertSame([$native], array_map(static fn($l) => (int) $l['id'], $data['logs']));
        $this->assertNull(delivery_log::get_imported_detail($imported));
        $this->assertNull(delivery_log::get_imported_detail($pathless));
    }

    public function test_with_the_flag_on_the_log_lists_the_history_marked_as_such(): void {
        $native = $this->native(1);
        $imported = $this->imported(1, ['status' => 'not_sent', 'timesent' => null, 'error_message' => 'BizLMS queue',
            'template_key' => null]);
        $this->flags(true, false);

        $this->assertSame([$native, $imported], $this->listed());
        $this->assertSame(2, (int) delivery_log::get_stats(0)->total);

        $data = manage_controller::get_logs_data([], 0, 50);
        $this->assertTrue($data['show_imported']);
        $rows = array_column($data['logs'], null, 'id');
        $this->assertFalse($rows[$native]['imported']);
        $this->assertTrue($rows[$imported]['imported']);
        $this->assertSame('course_enrol', $rows[$imported]['template_label'],
            'with no template key the BizLMS notification type is shown');
        $this->assertSame('reminders/not_started', $rows[$native]['template_label']);
        $this->assertTrue($rows[$imported]['status_notsent'], 'an imported queue row gets its badge');
        $this->assertFalse($rows[$imported]['status_failed']);
        $this->assertSame('', $rows[$imported]['sent_on'], 'never delivered: no Sent on');
        $this->assertSame('', $rows[$imported]['view_url'], 'the View link needs the second flag');
        $this->assertFalse($rows[$imported]['can_view']);
    }

    public function test_a_list_never_reads_the_message_body(): void {
        $imported = $this->imported(1);
        $this->flags(true, true);
        $records = delivery_log::get_logs([], 0, 50)->records;
        $this->assertCount(1, $records);
        $this->assertFalse(property_exists($records[0], 'body_html'), 'a list must not pull the bodies');
        $this->assertSame(1, (int) $records[0]->has_body);
        $data = manage_controller::get_logs_data([], 0, 50);
        $this->assertTrue($data['logs'][0]['can_view']);
        $this->assertStringContainsString('email_detail.php?id=' . $imported, $data['logs'][0]['view_url']);
    }

    public function test_the_export_carries_the_import_columns_only_when_the_flag_is_on(): void {
        $this->native(1);
        $this->imported(1, ['sender_userid' => $this->getDataGenerator()->create_user(
            ['firstname' => 'Sasha', 'lastname' => 'Sender'])->id]);
        $this->flags(true, false);
        $lines = [];
        $written = delivery_log::stream_csv([], static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });
        $this->assertSame(2, $written);
        $this->assertSame("ID,Date,User,Email,Tenant,Channel,Subject,Template,Status,Error,LegacyType,SentFrom,SentOn\n",
            $lines[0]);
        $csv = implode('', $lines);
        $this->assertStringContainsString('Sasha Sender', $csv);
        $this->assertStringNotContainsString('Body of the imported message', $csv, 'a body is never exported');
    }

    // F-65: nothing an old mail would fetch from outside, and nothing a spreadsheet would run.

    public function test_an_old_mails_remote_images_are_removed_before_the_body_is_shown(): void {
        $html = '<p>Hi</p><img src="https://track.example/p.gif?u=1" width="1" height="1">'
            . '<img src=\'//cdn.example/logo.png\'><img src=http://pixel.example/a.png>'
            . '<img src="data:image/png;base64,iVBOR//wKGgo=" alt="inline">'
            . '<img src="/pluginfile.php/1/x.png">'
            . '<div style="background:url(\'http://bg.example/bg.png\') no-repeat">x</div>'
            . '<table background="https://tbl.example/bg.jpg"><tr><td>y</td></tr></table>';
        $clean = imported_history::without_external_resources($html, '[removed]');

        foreach (['track.example', 'cdn.example', 'pixel.example', 'bg.example', 'tbl.example'] as $host) {
            $this->assertStringNotContainsString($host, $clean, "{$host} would be fetched when the message is shown");
        }
        $this->assertSame(3, substr_count($clean, '[removed]'), 'the three off-site images are replaced');
        $this->assertStringContainsString('data:image/png;base64,iVBOR//wKGgo=', $clean, 'an inline image stays');
        $this->assertStringContainsString('/pluginfile.php/1/x.png', $clean, 'a relative one stays');
        $this->assertStringContainsString('<p>Hi</p>', $clean);
        $this->assertStringContainsString('url(', imported_history::without_external_resources('<i style="background:url(a.png)">', ''),
            'a relative CSS url stays');
        $this->assertSame('<p>No images</p>', imported_history::without_external_resources('<p>No images</p>'));
    }

    public function test_a_spreadsheet_formula_in_an_old_subject_is_defused_in_the_export(): void {
        $this->imported(1, ['subject' => '=HYPERLINK("http://x.example/steal","click")']);
        $this->native(1, ['subject' => '@SUM(1+1)']);
        $this->flags(true, false);
        $csv = '';
        delivery_log::stream_csv([], static function (string $line) use (&$csv): void {
            $csv .= $line;
        });
        $this->assertStringContainsString("\"'=HYPERLINK", $csv, 'the subject cell no longer starts with =');
        $this->assertStringContainsString("\"'@SUM", $csv);
        $this->assertStringNotContainsString("\"=HYPERLINK", $csv);

        foreach (['=a', '+a', '-a', '@a', "\ta", "\ra"] as $text) {
            $this->assertSame("'" . $text, delivery_log::csv_safe($text));
        }
        foreach (['a=', 'Hello', '', '1+1', 'sent'] as $text) {
            $this->assertSame($text, delivery_log::csv_safe($text));
        }
    }

    public function test_the_export_streams_the_whole_log_not_the_first_ten_thousand(): void {
        global $DB;
        $total = delivery_log::EXPORT_PAGE * 2 + 205;
        $rows = [];
        for ($i = 0; $i < $total; $i++) {
            $rows[] = (object) ['userid' => $this->recipient->id, 'tenant_id' => 1, 'channel' => 'email',
                'subject' => 'Bulk ' . $i, 'status' => 'sent', 'timecreated' => 1700000000 + $i];
        }
        $DB->insert_records(self::LOG, $rows);

        $ids = [];
        $lines = 0;
        $written = delivery_log::stream_csv([], static function (string $line) use (&$ids, &$lines): void {
            $lines++;
            if ($lines > 1) {
                $ids[] = (int) explode(',', $line, 2)[0];
            }
        });
        $this->assertSame($total, $written, 'every row, across pages');
        $this->assertSame($total, count($ids));
        $this->assertSame($total, count(array_unique($ids)), 'no row is repeated across a page boundary');
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids, 'newest id first, the keyset order');
    }

    public function test_a_tenant_admin_reads_only_their_own_tenants_history(): void {
        $own = $this->imported(1);
        $other = $this->imported(77);
        $pathless = $this->imported(0);
        $native = $this->native(1);
        $this->flags(true, true);
        $this->setUser($this->tenant_admin('/1'));

        foreach ([[], ['tenant_id' => 77], ['tenant_id' => 0]] as $filters) {
            $ids = $this->listed($filters);
            $this->assertContains($own, $ids);
            $this->assertContains($native, $ids);
            $this->assertNotContains($other, $ids, 'another tenant\'s history is not readable');
            $this->assertNotContains($pathless, $ids, 'rows whose tenant is unresolved are for cross-tenant callers only');
        }
        $this->assertSame(2, (int) delivery_log::get_stats(0)->total);
        $this->assertSame(2, (int) delivery_log::get_stats(77)->total, 'clamped to the caller\'s own tenant');

        $this->assertNotNull(delivery_log::get_imported_detail($own));
        $this->assertNull(delivery_log::get_imported_detail($other), 'a row of another tenant reads as not found');
        $this->assertNull(delivery_log::get_imported_detail($pathless));

        $csv = '';
        delivery_log::stream_csv(['tenant_id' => 77], static function (string $line) use (&$csv): void {
            $csv .= $line;
        });
        $this->assertStringNotContainsString('Imported 77', $csv);
        $this->assertStringContainsString('Imported 1', $csv);
    }

    public function test_a_caller_whose_tenant_does_not_resolve_reads_no_history(): void {
        $imported = $this->imported(1);
        $pathless = $this->imported(0);
        $this->flags(true, true);
        $this->setUser($this->tenant_admin(''));

        $this->assertSame([], $this->listed());
        $this->assertSame(0, (int) delivery_log::get_stats(0)->total);
        $this->assertNull(delivery_log::get_imported_detail($imported));
        $this->assertNull(delivery_log::get_imported_detail($pathless), 'tenant_id 0 is not "every tenant"');
        $written = delivery_log::stream_csv([], static function (string $line): void {
        });
        $this->assertSame(0, $written);
    }

    public function test_a_cross_tenant_caller_sees_everything_including_the_pathless_rows(): void {
        $ids = [$this->imported(1), $this->imported(77), $this->imported(0)];
        $this->flags(true, true);
        $this->assertSame($ids, $this->listed());
        foreach ($ids as $id) {
            $detail = delivery_log::get_imported_detail($id);
            $this->assertNotNull($detail);
            $this->assertSame('<p>Body of the imported message</p>', $detail->body_html);
            $this->assertSame('recipient@example.com', $detail->email);
        }
    }

    public function test_the_detail_view_is_only_for_imported_rows(): void {
        $native = $this->native(1);
        $this->flags(true, true);
        $this->assertNull(delivery_log::get_imported_detail($native), 'a native row has no imported detail');
        $this->assertNull(delivery_log::get_imported_detail(987654));
    }

    public function test_the_dashboard_hides_the_bizlms_queue_card_only_when_the_same_emails_are_in_the_tiles(): void {
        global $DB;
        $this->imported(1);
        $this->assertTrue(manage_controller::get_dashboard_data(0)['show_legacy'],
            'flag OFF: the imported rows are not in the tiles, so the card stays');
        $this->flags(true, false);
        $this->assertFalse(manage_controller::get_dashboard_data(0)['show_legacy'],
            'flag ON with imported rows: counted in the tiles, so the card would count them twice');

        $DB->delete_records(self::LOG);
        $this->assertTrue(manage_controller::get_dashboard_data(0)['show_legacy'],
            'flag ON but nothing imported: the card is the only place the BizLMS queue shows');
    }

    // The template tenant filter (code fix 6).

    /**
     * The BizLMS template tables, as temporary tables when the test site has none (in the production shape: open_path
     * and no costcenterid). Returns false when a real table without open_path is there.
     *
     * @return bool
     */
    private function ensure_template_tables(): bool {
        global $DB;
        $dbman = $DB->get_manager();
        $type = new \xmldb_table('local_notification_type');
        if (!$dbman->table_exists($type)) {
            $type->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $type->add_field('name', XMLDB_TYPE_CHAR, '255');
            $type->add_field('shortname', XMLDB_TYPE_CHAR, '255');
            $type->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_temp_table($type);
            $this->temptables[] = $type;
        }
        $info = new \xmldb_table('local_notification_info');
        if (!$dbman->table_exists($info)) {
            $info->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $info->add_field('open_path', XMLDB_TYPE_CHAR, '255');
            $info->add_field('notificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $info->add_field('subject', XMLDB_TYPE_CHAR, '255');
            $info->add_field('body', XMLDB_TYPE_TEXT);
            $info->add_field('adminbody', XMLDB_TYPE_TEXT);
            // No costcenterid: the April 2026 production copy has open_path only. The filter must work on this shape.
            $info->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $info->add_field('completiondays', XMLDB_TYPE_INTEGER, '10');
            $info->add_field('reminderdays', XMLDB_TYPE_INTEGER, '10');
            $info->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $info->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $info->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_temp_table($info);
            $this->temptables[] = $info;
        }
        return array_key_exists('open_path', $DB->get_columns('local_notification_info'));
    }

    public function test_the_template_tenant_filter_matches_open_path_on_a_whole_segment(): void {
        global $DB;
        if (!$this->ensure_template_tables()) {
            $this->markTestSkipped('a local_notification_info without open_path is on this test site');
        }
        // The production shape has no costcenterid; a site whose real table has one gets the older rows too.
        $hascost = array_key_exists('costcenterid', $DB->get_columns('local_notification_info'));
        $typeid = (int) $DB->insert_record('local_notification_type', (object) ['name' => 'Course enrolment',
            'shortname' => 'course_enrol']);
        // Writers since 2022 set open_path and never costcenterid; older rows set costcenterid only.
        $templates = [
            'T1' => ['/1', 0],
            'T2' => ['/1/5', 0],
            'T3' => ['/10', 0],
            'T4' => ['/177', 0],
            'T5' => ['', 1],
            'T6' => ['/77', 77],
            'T7' => ['1/5', 0],
        ];
        $ids = [];
        foreach ($templates as $subject => [$path, $costcenter]) {
            $record = (object) [
                'open_path' => $path, 'notificationid' => $typeid, 'subject' => $subject, 'body' => '<p>b</p>',
                'active' => 1, 'timecreated' => 1, 'timemodified' => 1,
            ];
            if ($hascost) {
                $record->costcenterid = $costcenter;
            }
            $ids[$subject] = (int) $DB->insert_record('local_notification_info', $record);
        }
        $subjects = static fn(): array => array_column(legacy_bridge::get_bizlms_templates(), 'subject');

        $this->setUser($this->tenant_admin('/1'));
        $seen = $subjects();
        sort($seen);
        $this->assertSame($hascost ? ['T1', 'T2', 'T5'] : ['T1', 'T2'], $seen,
            '/1 and /1/5 match; /10 and /177 do not (whole segment); where the table has costcenterid that row '
            . 'still matches too; a path without its leading slash is not matched (the importer preflight counts those)');
        $this->assertNotNull(legacy_bridge::get_bizlms_template($ids['T2']));
        $this->assertNull(legacy_bridge::get_bizlms_template($ids['T3']), '/10 is not tenant 1');
        $this->assertNull(legacy_bridge::get_bizlms_template($ids['T6']));
        if (!$hascost) {
            $this->assertSame(1, (int) legacy_bridge::get_bizlms_template($ids['T2'])->costcenterid,
                'the result carries the tenant whichever column it came from');
        }

        $this->setUser($this->tenant_admin(''));
        $this->assertSame([], $subjects(), 'a caller whose tenant does not resolve sees none');

        $this->setAdminUser();
        $this->assertCount(count($templates), $subjects(), 'a cross-tenant caller sees every template');
        if (!$hascost) {
            $labels = array_column(legacy_bridge::get_bizlms_templates(), 'label', 'bizlms_id');
            $this->assertStringContainsString('(Tenant 1)', $labels[$ids['T2']],
                'a template without costcenterid is labelled with the root of its open_path');
        }
    }

    public function test_the_template_tenant_filter_is_built_from_the_columns_the_table_has(): void {
        // The three shapes of local_notification_info found in the wild. Naming a column the table lacks makes the
        // query throw, which emptied the Templates tab for everybody on the production shape (path only).
        $this->setUser($this->tenant_admin('/1'));
        [$both] = legacy_bridge::tenant_filter_for_columns('ni', true, true);
        $this->assertStringContainsString('ni.open_path', $both);
        $this->assertStringContainsString('ni.costcenterid', $both);
        [$pathonly] = legacy_bridge::tenant_filter_for_columns('ni', true, false);
        $this->assertStringContainsString('ni.open_path', $pathonly);
        $this->assertStringNotContainsString('costcenterid', $pathonly, 'the production shape has no such column');
        [$costonly] = legacy_bridge::tenant_filter_for_columns('ni', false, true);
        $this->assertStringContainsString('ni.costcenterid', $costonly);
        $this->assertStringNotContainsString('open_path', $costonly);
        [$neither, $args] = legacy_bridge::tenant_filter_for_columns('ni', false, false);
        $this->assertSame('1=0', $neither, 'nothing to scope by: fail closed');
        $this->assertSame([], $args);

        $this->setAdminUser();
        $this->assertSame('1=1', legacy_bridge::tenant_filter_for_columns('ni', true, false)[0],
            'a cross-tenant caller is not filtered');
    }
}

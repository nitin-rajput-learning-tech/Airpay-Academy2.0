<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use local_sentientia_authoring\privacy\provider;
use local_sentientia_authoring\scd\document;
use local_sentientia_authoring\scd\repository;
use local_sentientia_platform\feature_flags;

/**
 * ADR-034 Stage 1 slice 1: course documents are stored per tenant, validated
 * first, with their canonical JSON and sha256, behind a default-OFF flag; and
 * the privacy provider anonymises the people the new tables record.
 *
 * Tenant scope follows ADR-031: a scoped caller reads and lists only their own
 * tenant's documents and files only under their own tenant; a cross-tenant
 * caller (site admin) reads every tenant; a caller with no resolvable tenant
 * reads nothing and stores nothing. Tenants here are flat costcenterid values
 * (1 = Airpay, 177 = ZEEA); nothing matches on an org path.
 *
 * The open_path fixture trait adds mdl_user.open_path (a BizLMS column absent
 * from vanilla PHPUnit) in its setUp() and marks every test resetAfterTest.
 *
 * @package    local_sentientia_authoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_authoring\scd\repository
 * @covers     \local_sentientia_authoring\privacy\provider
 * @group      tenant_isolation
 */
final class scd_repository_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /** Canonical sha256 of the harness fixture (see scd_validator_test). */
    private const FIXTURE_CANONICAL_SHA256 = 'f64b9f5517b35eed51c570ae0969f6365d017f18ada7762f274bb8dd36a7d963';

    /**
     * A fresh decoded copy of the harness fixture, optionally at another version.
     *
     * @param string|null $version
     * @return \stdClass
     */
    private static function fixture(?string $version = null): \stdClass {
        $doc = document::decode((string) file_get_contents(__DIR__ . '/fixtures/scd/course-upi-dispute.json'));
        if ($version !== null) {
            $doc->course->version = $version;
        }
        return $doc;
    }

    /**
     * A user pinned to an org path.
     *
     * @param string $openpath
     * @return \stdClass
     */
    private function user_at(string $openpath): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $openpath, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * Switch the master flag on for every tenant: the global override
     * (customer 0, tenant 0).
     *
     * @return void
     */
    private function flag_on(): void {
        feature_flags::set(repository::FLAG, 0, true, (int) get_admin()->id, 'scd_repository_test');
    }

    /**
     * The fixture with a chain of extra decision nodes in its scenario, so
     * that the scenario has more nodes than the SCORM 1.2 cap (60). The graph
     * stays valid: n3 continues into the chain, the chain ends at t_success.
     *
     * @param int $extra Nodes to add (the fixture's scenario has 10).
     * @return \stdClass
     */
    private static function fixture_with_big_scenario(int $extra): \stdClass {
        $doc = self::fixture();
        foreach ($doc->blocks as $block) {
            if ($block->type !== 'scenario') {
                continue;
            }
            for ($i = 0; $i < $extra; $i++) {
                $next = $i < $extra - 1 ? sprintf('chain_%03d', $i + 1) : 't_success';
                $edges = [];
                foreach (['a' => true, 'b' => false] as $suffix => $correct) {
                    $edges[] = (object) ['id' => sprintf('chain_%03d_%s', $i, $suffix),
                        'text' => (object) ['en' => $correct ? 'A' : 'B'], 'to' => $next,
                        'score_delta' => $correct ? 1 : 0, 'outcome' => $correct ? 'acceptable' : 'recoverable',
                        'correct' => $correct];
                }
                $block->nodes[] = (object) ['id' => sprintf('chain_%03d', $i), 'kind' => 'decision',
                    'prompt' => (object) ['en' => 'Next?'], 'edges' => $edges];
            }
            foreach ($block->nodes as $node) {
                if ($node->id === 'n3') {
                    $node->edges[0]->to = 'chain_000';
                }
            }
        }
        return $doc;
    }

    /**
     * @param \stdClass[] $rows
     * @return int[]
     */
    private static function ids(array $rows): array {
        return array_map(static function (\stdClass $r): int {
            return (int) $r->id;
        }, $rows);
    }

    /**
     * Run a call and return the moodle_exception error code it threw.
     *
     * @param callable $call
     * @return string
     */
    private function error_of(callable $call): string {
        try {
            $call();
        } catch (\moodle_exception $e) {
            return $e->errorcode;
        }
        $this->fail('Expected a moodle_exception.');
    }

    public function test_the_flag_is_registered_and_off_by_default(): void {
        $this->assertArrayHasKey(repository::FLAG, feature_flags::load_registry());
        $this->assertFalse(repository::is_enabled(1));
        $author = $this->user_at('/1/2');
        $this->assertSame('err_scd_disabled', $this->error_of(fn() => repository::create(self::fixture(), $author)));
        global $DB;
        $this->assertSame(0, $DB->count_records(repository::TABLE));
    }

    public function test_create_stores_the_canonical_json_and_its_hash(): void {
        $this->flag_on();
        $author = $this->user_at('/1/2');
        $row = repository::create(self::fixture(), $author);

        $stored = repository::get((int) $row->id, $author);
        $this->assertNotNull($stored);
        $this->assertSame(1, (int) $stored->costcenterid);
        $this->assertSame(1, (int) $stored->customerid);
        $this->assertSame('sop-cs-014-upi-dispute-handling', $stored->slug);
        $this->assertSame('0.1.0', $stored->version);
        $this->assertSame('0.1', $stored->schemaversion);
        $this->assertSame('approved', $stored->state);
        $this->assertSame((int) $author->id, (int) $stored->usercreated);
        $this->assertSame(document::canonical(self::fixture()), $stored->scd_json);
        $this->assertSame(self::FIXTURE_CANONICAL_SHA256, $stored->sha256,
            'The PHP side hashes the same bytes as the reference harness.');
        $this->assertSame($stored->sha256, hash('sha256', $stored->scd_json));
        $this->assertGreaterThan(0, (int) $stored->timecreated);
        $this->assertSame((int) $stored->timecreated, (int) $stored->timemodified);

        // The JSON text form stores the same document.
        $again = repository::create(document::canonical(self::fixture('0.1.1')), $author);
        $this->assertSame('0.1.1', $again->version);
        $this->assertSame(document::sha256(self::fixture('0.1.1')), $again->sha256);
        $this->assertEquals(self::fixture('0.1.1'), repository::decode(repository::get((int) $again->id, $author)));
    }

    public function test_a_tenant_reads_and_lists_only_its_own_documents(): void {
        $this->flag_on();
        $airpay = $this->user_at('/1/2');
        $airpaycolleague = $this->user_at('/1/5/9');
        $zeea = $this->user_at('/177/4');
        $own = repository::create(self::fixture(), $airpay);
        // Same slug and version in another tenant is another document.
        $foreign = repository::create(self::fixture(), $zeea);
        $this->assertSame(177, (int) $foreign->costcenterid);

        $this->assertNotNull(repository::get((int) $own->id, $airpaycolleague));
        $this->assertNull(repository::get((int) $foreign->id, $airpaycolleague));
        $this->assertNull(repository::get((int) $own->id, $zeea));
        $this->assertSame([(int) $own->id], self::ids(repository::list_for_actor($airpaycolleague)));
        $this->assertSame([(int) $foreign->id], self::ids(repository::list_for_actor($zeea)));
        $this->assertSame('error_outoftenant',
            $this->error_of(fn() => repository::list_for_actor($zeea, 1)));
        $this->assertSame('error_outoftenant',
            $this->error_of(fn() => repository::create(self::fixture('0.2.0'), $airpay, 177)),
            'A scoped author cannot file a document under another tenant.');

        // A listing never carries the document text.
        $listed = repository::list_for_actor($airpay);
        $this->assertFalse(property_exists(reset($listed), 'scd_json'));
    }

    public function test_a_cross_tenant_caller_reads_every_tenant(): void {
        $this->flag_on();
        $own = repository::create(self::fixture(), $this->user_at('/1/2'));
        $foreign = repository::create(self::fixture(), $this->user_at('/177/4'));
        $admin = get_admin();

        $this->assertNotNull(repository::get((int) $foreign->id, $admin));
        $ids = self::ids(repository::list_for_actor($admin));
        sort($ids);
        $this->assertSame([(int) $own->id, (int) $foreign->id], $ids);
        $this->assertSame([(int) $foreign->id], self::ids(repository::list_for_actor($admin, 177)));
        // A cross-tenant caller names the tenant explicitly.
        $filed = repository::create(self::fixture('0.2.0'), $admin, 177);
        $this->assertSame(177, (int) $filed->costcenterid);
    }

    public function test_a_tenantless_caller_gets_nothing_and_stores_nothing(): void {
        $this->flag_on();
        $doc = repository::create(self::fixture(), $this->user_at('/1/2'));
        $nobody = $this->user_at('');

        $this->assertSame([], repository::list_for_actor($nobody));
        $this->assertNull(repository::get((int) $doc->id, $nobody));
        $this->assertSame('err_scd_notenant', $this->error_of(fn() => repository::create(self::fixture('0.2.0'), $nobody)));
    }

    public function test_the_flag_is_read_for_the_documents_own_tenant(): void {
        $this->flag_on();
        $zeea = $this->user_at('/177/4');
        $zeeadoc = repository::create(self::fixture(), $zeea);
        feature_flags::set(repository::FLAG, 177, false, (int) get_admin()->id, 'scd_repository_test');
        $this->assertTrue(repository::is_enabled(1));
        $this->assertFalse(repository::is_enabled(177));
        $this->assertSame('err_scd_disabled', $this->error_of(fn() => repository::create(self::fixture('0.2.0'), $zeea)));
        $this->assertSame('err_scd_disabled', $this->error_of(fn() => repository::list_for_actor($zeea)));
        $this->assertSame('err_scd_disabled', $this->error_of(fn() => repository::get((int) $zeeadoc->id, $zeea)));
        $airpaydoc = repository::create(self::fixture(), $this->user_at('/1/2'));

        // A cross-tenant caller: the switched-off tenant's rows are left out
        // of the all-tenant listing, refused when named or read directly.
        $admin = get_admin();
        $this->assertSame([(int) $airpaydoc->id], self::ids(repository::list_for_actor($admin)));
        $this->assertSame('err_scd_disabled', $this->error_of(fn() => repository::list_for_actor($admin, 177)));
        $this->assertSame('err_scd_disabled', $this->error_of(fn() => repository::get((int) $zeeadoc->id, $admin)));
    }

    /**
     * R05 is judged against the document's own targets (delivery.standards,
     * scorm2004_4 as the validator's scorm2004): a scenario above the SCORM
     * 1.2 node cap is refused while scorm12 is a target (the fixture's list)
     * and stored, with a warning only, once it is not.
     */
    public function test_the_scorm12_node_cap_applies_only_when_the_document_targets_scorm12(): void {
        global $DB;
        $this->flag_on();
        $author = $this->user_at('/1/2');

        $big = self::fixture_with_big_scenario(61);
        $this->assertContains('scorm12', $big->delivery->standards);
        $this->assertSame('err_scd_invalid', $this->error_of(fn() => repository::create($big, $author)));
        $this->assertSame(0, $DB->count_records(repository::TABLE));

        $big->delivery->standards = ['scorm2004_4', 'cmi5'];
        $row = repository::create($big, $author);
        $this->assertSame('0.1.0', $row->version);
        $this->assertSame(1, $DB->count_records(repository::TABLE));
    }

    public function test_an_invalid_document_is_refused_before_anything_is_stored(): void {
        global $DB;
        $this->flag_on();
        $author = $this->user_at('/1/2');

        $broken = self::fixture();
        unset($broken->blocks[0]->title->en);
        $this->assertSame('err_scd_invalid', $this->error_of(fn() => repository::create($broken, $author)));

        $noversion = self::fixture();
        unset($noversion->course->version);
        $this->assertSame('err_scd_noversion', $this->error_of(fn() => repository::create($noversion, $author)));

        // A version with surrounding spaces is refused, not trimmed: the
        // column and the stored JSON must carry the same value.
        $spaced = self::fixture('0.1.0 ');
        $this->assertSame('err_scd_noversion', $this->error_of(fn() => repository::create($spaced, $author)));

        $longversion = self::fixture(str_repeat('9', 33));
        $this->assertSame('err_scd_toolong', $this->error_of(fn() => repository::create($longversion, $author)));

        $this->assertSame('err_scd_invalidjson', $this->error_of(fn() => repository::create('{"scd_version":', $author)));
        $this->assertSame(0, $DB->count_records(repository::TABLE));
    }

    public function test_a_stored_version_is_immutable(): void {
        $this->flag_on();
        $author = $this->user_at('/1/2');
        repository::create(self::fixture(), $author);
        $this->assertSame('err_scd_duplicate', $this->error_of(fn() => repository::create(self::fixture(), $author)));
        $this->assertSame('0.1.1', repository::create(self::fixture('0.1.1'), $author)->version);
        $this->assertCount(2, repository::list_for_actor($author, null, 'sop-cs-014-upi-dispute-handling'));
        $this->assertCount(0, repository::list_for_actor($author, null, 'another-course'));
    }

    public function test_decode_refuses_a_document_whose_text_no_longer_matches_its_hash(): void {
        global $DB;
        $this->flag_on();
        $author = $this->user_at('/1/2');
        $row = repository::create(self::fixture(), $author);
        $DB->set_field(repository::TABLE, 'scd_json',
            str_replace('"approved"', '"published"', $row->scd_json), ['id' => $row->id]);
        $tampered = repository::get((int) $row->id, $author);
        $this->assertSame('err_scd_integrity', $this->error_of(fn() => repository::decode($tampered)));
    }

    // ---------------------------------------------------------------------
    // Privacy provider: the new tables.
    // ---------------------------------------------------------------------

    public function test_privacy_reports_and_anonymises_the_people_a_document_records(): void {
        global $DB;
        $this->flag_on();
        $syscontext = \context_system::instance();
        $subject = $this->user_at('/1/2');
        $other = $this->user_at('/1/3');
        $bystander = $this->user_at('/1/4');
        $mine = repository::create(self::fixture(), $subject);
        $theirs = repository::create(self::fixture('0.1.1'), $other);
        // The subject changed the other author's version (a later slice's state change).
        $DB->set_field(repository::TABLE, 'usermodified', $subject->id, ['id' => $theirs->id]);
        $DB->insert_record(repository::ASSET_TABLE, (object) [
            'scdid' => $mine->id, 'customerid' => 1, 'costcenterid' => 1, 'path' => 'audio/en/b01.mp3',
            'kind' => 'audio', 'lang' => 'en', 'usercreated' => $subject->id, 'usermodified' => $subject->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $contextids = static function (\stdClass $user): array {
            return array_map('intval', provider::get_contexts_for_userid((int) $user->id)->get_contextids());
        };
        $this->assertSame([(int) $syscontext->id], $contextids($subject));
        $this->assertSame([(int) $syscontext->id], $contextids($other));
        $this->assertSame([], $contextids($bystander));

        $userlist = new userlist($syscontext, 'local_sentientia_authoring');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $subject->id, array_map('intval', $userlist->get_userids()));
        $this->assertNotContains((int) $bystander->id, array_map('intval', $userlist->get_userids()));

        provider::delete_data_for_user(new approved_contextlist($subject, 'local_sentientia_authoring',
            [(int) $syscontext->id]));

        $this->assertSame(2, $DB->count_records(repository::TABLE), 'Erasure keeps the tenant\'s documents.');
        $this->assertSame(0, (int) $DB->get_field(repository::TABLE, 'usercreated', ['id' => $mine->id]));
        $this->assertSame(0, (int) $DB->get_field(repository::TABLE, 'usermodified', ['id' => $theirs->id]));
        $this->assertSame((int) $other->id, (int) $DB->get_field(repository::TABLE, 'usercreated', ['id' => $theirs->id]),
            'Another author stays recorded.');
        $this->assertSame(0, $DB->count_records_select(repository::ASSET_TABLE,
            'usercreated = :c OR usermodified = :m', ['c' => $subject->id, 'm' => $subject->id]));
        $this->assertSame(1, $DB->count_records(repository::ASSET_TABLE));
        $this->assertSame([], $contextids($subject));
        // The hash anchor still holds: anonymising never touches the document text.
        $this->assertSame(self::FIXTURE_CANONICAL_SHA256, $DB->get_field(repository::TABLE, 'sha256', ['id' => $mine->id]));

        provider::delete_data_for_users(new approved_userlist($syscontext, 'local_sentientia_authoring',
            [(int) $other->id]));
        $this->assertSame(0, $DB->count_records_select(repository::TABLE, 'usercreated > 0 OR usermodified > 0'));
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_sentientia_challenge\challenge_engine;

/**
 * Privacy provider lock-in tests for local_sentientia_challenge.
 *
 * @package    local_sentientia_challenge
 * @category   test
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    public function test_get_metadata_declares_three_tables(): void {
        $collection = new \core_privacy\local\metadata\collection('local_sentientia_challenge');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();
        $names = array_map(fn($i) => $i->get_name(), $items);
        $this->assertContains('local_sentientia_challenge_challenges', $names);
        $this->assertContains('local_sentientia_challenge_attempts', $names);
        $this->assertContains('local_sentientia_challenge_leaderboard', $names);
    }

    public function test_get_users_in_context_finds_attempt_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge([
            'name' => 'Priv', 'shortname' => 'priv1',
            'status' => challenge_engine::STATUS_ACTIVE,
        ]);
        challenge_engine::join($cid, (int) $u->id);

        $userlist = new \core_privacy\local\request\userlist(\context_system::instance(),
            'local_sentientia_challenge');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $u->id, $userlist->get_userids());
    }

    public function test_export_user_data_includes_attempts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge([
            'name' => 'My Challenge', 'shortname' => 'priv2',
            'status' => challenge_engine::STATUS_ACTIVE,
        ]);
        challenge_engine::join($cid, (int) $u->id);

        $sysctx = \context_system::instance();
        $contextlist = new approved_contextlist($u, 'local_sentientia_challenge', [$sysctx->id]);
        provider::export_user_data($contextlist);

        $this->assertTrue(writer::with_context($sysctx)->has_any_data());
    }

    public function test_delete_data_for_user_nukes_attempts_and_leaderboard(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $u = $this->getDataGenerator()->create_user();
        $cid = challenge_engine::create_challenge([
            'name' => 'Del', 'shortname' => 'priv3',
            'status' => challenge_engine::STATUS_ACTIVE,
        ]);
        challenge_engine::join($cid, (int) $u->id);
        // Seed a leaderboard row.
        $DB->insert_record('local_sentientia_challenge_leaderboard', (object) [
            'challengeid' => $cid, 'userid' => (int) $u->id,
            'costcenterid' => 0, 'points' => 100, 'userrank' => 1,
            'attemptscompleted' => 0, 'lastrecomputed' => time(),
        ]);

        $this->assertTrue($DB->record_exists('local_sentientia_challenge_attempts',
            ['userid' => $u->id]));
        $this->assertTrue($DB->record_exists('local_sentientia_challenge_leaderboard',
            ['userid' => $u->id]));

        $contextlist = new approved_contextlist($u, 'local_sentientia_challenge',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('local_sentientia_challenge_attempts',
            ['userid' => $u->id]));
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_leaderboard',
            ['userid' => $u->id]));
    }

    public function test_delete_data_for_user_anonymises_authored_challenges(): void {
        global $DB;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);
        $cid = challenge_engine::create_challenge([
            'name' => 'Mine', 'shortname' => 'priv4',
            'status' => challenge_engine::STATUS_DRAFT,
        ]);

        // Confirm createdby is $u.
        $row_before = $DB->get_record('local_sentientia_challenge_challenges', ['id' => $cid]);
        $this->assertSame((int) $u->id, (int) $row_before->createdby);

        $this->setAdminUser();
        $contextlist = new approved_contextlist($u, 'local_sentientia_challenge',
            [\context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        $row_after = $DB->get_record('local_sentientia_challenge_challenges', ['id' => $cid]);
        $this->assertSame(0, (int) $row_after->createdby,
            'createdby must be anonymised to 0; row preserved for other participants');
    }
}

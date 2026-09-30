<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The seed every toy-importer test starts from (tests/fixtures/bizlms/toy.install.xml).
 *
 * Numbers a test may rely on:
 *
 *  local_toy_org    5 rows: ids 1, 2 and 7 import, 3 has no name (skipped: no_name), 4 is archived (not_history).
 *                   The highest legacy id that imports is 7, so a native insert after finalise must get 8 or more.
 *  local_toy_item   5 rows: ids 1 to 3 import (2 has a title that needs truncating and a path needing
 *                   normalisation; 3 has no path), 4 has an unknown org (skipped: orphan_org),
 *                   5 has an unknown user (skipped: orphan_user).
 *  local_toy_dup    5 rows with interleaved ids: natkey k1 = 1, 3, 5 and k2 = 2, 4, so 2 targets and 3 merges.
 *  local_toy_event  6 lines in 3 carts (10: two lines, 11: one, 12: three), so 3 derived groups.
 *  local_toy_fan    3 rows, each fanning out into two sub-rows.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait toy_seed {

    /** @var int Timestamp base of the seed. */
    protected static int $toyt0 = 1700000000;

    /**
     * Fill the toy legacy tables.
     *
     * @return \stdClass The user the items belong to.
     */
    protected function seed_toy_data(): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $t = self::$toyt0;

        foreach ([[1, 'Alpha', '/1', 1], [2, 'Beta', '/77', 1], [3, '', '/1', 1], [4, 'Gamma', '/1', 9], [7, 'Delta', '/1', 1]] as [$id, $name, $path, $status]) {
            $DB->import_record('local_toy_org', (object) ['id' => $id, 'name' => $name, 'parentid' => 0, 'path' => $path,
                'status' => $status, 'timecreated' => $t + $id, 'timemodified' => $t + 100 + $id]);
        }
        $rows = [
            [1, 1, $user->id, 'Short title', 'a', '/1'],
            [2, 2, $user->id, str_repeat('long ', 12), 'b', ' 77/ '],
            [3, 1, $user->id, 'Third', 'c', null],
            [4, 99, $user->id, 'Orphan org', 'a', '/1'],
            [5, 1, 987654, 'Orphan user', 'a', '/1'],
        ];
        foreach ($rows as [$id, $orgid, $userid, $title, $kind, $path]) {
            $DB->import_record('local_toy_item', (object) ['id' => $id, 'orgid' => $orgid, 'userid' => $userid,
                'title' => $title, 'kind' => $kind, 'path' => $path, 'timecreated' => $t + $id, 'timemodified' => $t + 50 + $id]);
        }
        foreach ([[1, 'k1'], [2, 'k2'], [3, 'k1'], [4, 'k2'], [5, 'k1']] as [$id, $key]) {
            $DB->import_record('local_toy_dup', (object) ['id' => $id, 'natkey' => $key, 'label' => 'label ' . $id,
                'timecreated' => $t + $id]);
        }
        $id = 0;
        foreach ([10 => 2, 11 => 1, 12 => 3] as $cart => $lines) {
            for ($i = 0; $i < $lines; $i++) {
                $DB->import_record('local_toy_event', (object) ['id' => ++$id, 'cartid' => $cart, 'amount' => 10 * $id,
                    'timecreated' => $t + $id]);
            }
        }
        foreach ([1, 2, 3] as $id) {
            $DB->import_record('local_toy_fan', (object) ['id' => $id, 'title' => 'fan ' . $id, 'timecreated' => $t + $id]);
        }
        return $user;
    }
}

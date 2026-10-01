<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * BizLMS local_rating -> local_sentientia_ratings (MAP).
 *
 * The unit is one legacy rating row; the group is the (user, item, area) key, because the target holds one
 * rating per key (UNIQUE userid, itemid, ratearea) and BizLMS had no unique index, so a race could leave a
 * learner with several rows for one item. The group's valid rows go to the row that changed last
 * (COALESCE(timemodified, timecreated), then the highest id); the others are merged into it and stay in the
 * legacy table.
 *
 * A row is skipped, never guessed at, when:
 *  - the area is not one of the four mapped (unknown_area; certification has no Sentientia entity yet),
 *  - the item is missing, or a classroom, programme or learning plan the import did not bring over
 *    (orphan_item),
 *  - the user is missing, 0 or the guest (orphan_user),
 *  - the rating is not a whole number from 1 to 5 (invalid_rating): the BizLMS web service took any integer.
 *
 * moduleid is not copied (no live writer; the preflight counts it). Source timestamps are kept. If the target
 * already holds a rating for the key (a native one) it stays and the legacy row folds into it.
 *
 * Never calls rating_manager::submit_rating() or the submit web service: they stamp time() and fire nothing the
 * import wants.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ratings_step extends step {

    /** The table the ratings go to. */
    public const TARGET = 'local_sentientia_ratings';

    /** @var native_probe */
    private native_probe $probe;

    public function __construct() {
        $this->probe = new native_probe();
    }

    public function key(): string {
        return 'ratings.ratings';
    }

    public function sourcetable(): string {
        return 'local_rating';
    }

    public function targettable(): string {
        return self::TARGET;
    }

    public function group_by(): array {
        return ['userid', 'itemid', 'ratearea'];
    }

    public function columns(): array {
        return ['id', 'itemid', 'ratearea', 'userid', 'rating', 'timecreated', 'timemodified'];
    }

    public function preload(): array {
        return array_map(static fn(string $table): array => [$table, ''], area_map::parent_tables());
    }

    public function transform(array $rows, context $ctx): array {
        $rows = array_values($rows);
        $first = $rows[0];

        // Everything about the key is the same for every row of the group; only the rating differs.
        [$item, $area, $reason, $detail] = area_map::resolve($ctx, $first->itemid, $first->ratearea);
        if ($reason === null) {
            [$reason, $detail] = row_rules::user($ctx, $first->userid);
        }
        $out = [];
        if ($reason !== null) {
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, $reason, $detail);
            }
            return $out;
        }

        $valid = [];
        foreach ($rows as $row) {
            if ($row->rating === null || !in_array((string) $row->rating, ['1', '2', '3', '4', '5'], true)) {
                $out[] = outcome::skip((int) $row->id, 'invalid_rating',
                    $row->rating === null ? 'rating_missing' : 'rating_out_of_range');
                continue;
            }
            $valid[] = $row;
        }
        if (!$valid) {
            return $out;
        }

        $winner = row_rules::winner($valid);
        [$created, $modified] = row_rules::times($winner);
        $userid = (int) $first->userid;

        $native = $this->probe->find($ctx, self::TARGET, $userid, $item, $area);
        if ($native !== null) {
            $primary = outcome::fold((int) $winner->id, self::TARGET, $native, 'native_row_kept');
        } else {
            $primary = outcome::insert((int) $winner->id, self::TARGET, (object) [
                'itemid' => $item,
                'ratearea' => $area,
                'userid' => $userid,
                'rating' => (int) $winner->rating,
                'timecreated' => $created,
                'timemodified' => $modified,
            ]);
        }
        $out[] = row_rules::warn_times($primary, $winner);

        foreach ($valid as $row) {
            if ($row !== $winner) {
                $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_natural_key');
            }
        }
        return $out;
    }
}

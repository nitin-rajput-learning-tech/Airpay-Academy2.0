<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * BizLMS local_like -> local_sentientia_ratings_reactions (MAP).
 *
 * One reaction per learner and item (UNIQUE userid, itemid, ratearea on the target). BizLMS had no unique
 * index, so the group is the (user, item, area) key and the row that changed last wins
 * (COALESCE(timemodified, timecreated), then the highest id); the others are merged into it.
 *
 * likestatus is kept as stored, NULL becoming 0: 1 is a like and 2 a dislike, and any other value is carried
 * over and never counted (mapping doc section 20). A value that cannot fit the target's tiny column is skipped
 * (invalid_reaction) rather than allowed to fail the batch.
 *
 * Same skips as the ratings step for the area, the item and the user. A reaction the target already holds
 * for the key (a native one) stays and the legacy row folds into it.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reactions_step extends step {

    /** The table the reactions go to. */
    public const TARGET = 'local_sentientia_ratings_reactions';

    /** Largest absolute likestatus the target's TINYINT column is sure to hold. */
    private const STATUS_LIMIT = 127;

    /** @var native_probe */
    private native_probe $probe;

    public function __construct() {
        $this->probe = new native_probe();
    }

    public function key(): string {
        return 'ratings.reactions';
    }

    public function sourcetable(): string {
        return 'local_like';
    }

    public function targettable(): string {
        return self::TARGET;
    }

    public function group_by(): array {
        return ['userid', 'itemid', 'likearea'];
    }

    public function columns(): array {
        return ['id', 'itemid', 'likearea', 'likestatus', 'userid', 'timecreated', 'timemodified'];
    }

    public function preload(): array {
        return array_map(static fn(string $table): array => [$table, ''], area_map::parent_tables());
    }

    public function transform(array $rows, context $ctx): array {
        $rows = array_values($rows);
        $first = $rows[0];

        [$item, $area, $reason, $detail] = area_map::resolve($ctx, $first->itemid, $first->likearea);
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
            if (abs((int) $row->likestatus) > self::STATUS_LIMIT) {
                $out[] = outcome::skip((int) $row->id, 'invalid_reaction', 'status_out_of_range');
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
                'likestatus' => (int) $winner->likestatus,
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

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * BizLMS local_comment -> local_sentientia_ratings_reviews (MAP).
 *
 * One legacy comment row is one review and there is no natural key to dedupe on: the 2013-era rows are
 * distinct reviews, so a learner's two comments on one item are both imported. The text is copied verbatim; it
 * was raw input in BizLMS (nothing cleaned it on the way in), so the reader escapes it on the way out.
 *
 * Skips as for the other two steps: unknown area, missing item or parent, missing user. A blank review
 * (NULL, empty or only white space) is imported and hidden by the reader (decision ratings.blank_reviews =
 * import_hidden); with the other choice (skip) it stays in the legacy table (blank_review).
 *
 * The 2013 rows carry a time column the table's install file no longer declares (and courseid and activityid,
 * which nothing reads): time stands in for a missing timecreated, the rest is not copied.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reviews_step extends step {

    /** The table the reviews go to. */
    public const TARGET = 'local_sentientia_ratings_reviews';

    public function key(): string {
        return 'ratings.reviews';
    }

    public function sourcetable(): string {
        return 'local_comment';
    }

    public function targettable(): string {
        return self::TARGET;
    }

    public function columns(): array {
        // time is the 2013-era column: the importer's source_spec lists it as optional.
        return ['id', 'itemid', 'commentarea', 'comment', 'userid', 'timecreated', 'timemodified', 'time'];
    }

    public function preload(): array {
        return array_map(static fn(string $table): array => [$table, ''], area_map::parent_tables());
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;

            [$item, $area, $reason, $detail] = area_map::resolve($ctx, $row->itemid, $row->commentarea);
            if ($reason === null) {
                [$reason, $detail] = row_rules::user($ctx, $row->userid);
            }
            if ($reason !== null) {
                $out[] = outcome::skip($id, $reason, $detail);
                continue;
            }

            $text = $row->comment === null ? null : (string) $row->comment;
            if (trim((string) $text) === '' && $ctx->decision('ratings.blank_reviews') === 'skip') {
                $out[] = outcome::archive($id, 'blank_review');
                continue;
            }

            [$created, $modified] = row_rules::times($row);
            $out[] = row_rules::warn_times(outcome::insert($id, self::TARGET, (object) [
                'itemid' => $item,
                'ratearea' => $area,
                'userid' => (int) $row->userid,
                'review' => $text,
                'timecreated' => $created,
                'timemodified' => $modified,
            ]), $row);
        }
        return $out;
    }
}

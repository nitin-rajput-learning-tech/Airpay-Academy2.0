<?php
namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * Rating manager — star ratings for courses, classrooms, etc.
 *
 * Replaces BizLMS local_ratings with a clean implementation.
 *
 * The BizLMS fallback this class used to carry (reading local_rating while its own table was empty) is gone
 * (ADR-032, 2026-09-30): the BizLMS import brings those ratings into local_sentientia_ratings, under the
 * Sentientia area names, in the same release. The fallback compared the new area name with rows stored under
 * the old one, so it never matched, and its per-item switch hid every imported rating of an item that had
 * received one new row.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating_manager {

    private const TABLE = 'local_sentientia_ratings';

    /**
     * Owner decision CRS-11 (2026-10-07): the star widget on course pages. Default OFF (db/feature_flags.php).
     *
     * The theme used to render the stars as interactive buttons and never initialised the AMD widget, so learners saw
     * focusable controls that did nothing. OFF (the default) renders the honest read-only stars; ON renders interactive
     * stars and loads the widget, as BizLMS let learners rate a course. Recommended future flip, not decided here: ON for
     * Airpay at cutover, after the visual evidence is reviewed (framework.reader_flags_airpay_at_cutover).
     */
    public const FLAG_WIDGET = 'sentientia.ratings.widget';

    /** @var bool Whether the AMD widget has been asked for on this page already (a page can show several stars). */
    private static bool $widgetrequired = false;

    /**
     * The rating areas a rating, review or reaction may be filed under. Each names the Sentientia plugin that
     * owns the items. The submit web service accepts exactly these and the item reviews page refuses any other,
     * so the column cannot be used to rate an arbitrary table row.
     */
    public const AREAS = [
        'local_sentientia_courses',
        'local_sentientia_classroom',
        'local_sentientia_programs',
        'local_sentientia_learningpath',
        'local_sentientia_exams',
        'local_sentientia_evaluation',
    ];

    /**
     * Get average rating for an item.
     *
     * @param int    $itemid
     * @param string $ratearea
     * @return object {average, count}
     */
    public static function get_average(int $itemid, string $ratearea): object {
        global $DB;

        $result = (object) ['average' => 0, 'count' => 0];

        $rec = $DB->get_record_sql(
            "SELECT AVG(rating) AS avg_rating, COUNT(id) AS cnt
               FROM {" . self::TABLE . "}
              WHERE itemid = :itemid AND ratearea = :area AND rating > 0",
            ['itemid' => $itemid, 'area' => $ratearea]
        );
        if ($rec && $rec->cnt > 0) {
            $result->average = round((float) $rec->avg_rating, 1);
            $result->count = (int) $rec->cnt;
        }

        return $result;
    }

    /**
     * Get current user's rating for an item.
     *
     * @param int    $itemid
     * @param string $ratearea
     * @param int|null $userid
     * @return int  0-5 (0 = not rated)
     */
    public static function get_user_rating(int $itemid, string $ratearea, ?int $userid = null): int {
        global $DB, $USER;
        $userid = $userid ?? $USER->id;

        $val = $DB->get_field(self::TABLE, 'rating', [
            'itemid' => $itemid, 'ratearea' => $ratearea, 'userid' => $userid,
        ]);
        return $val !== false ? (int) $val : 0;
    }

    /**
     * Render star rating HTML for a course/item.
     *
     * W1-3 (2026-05-15) — stars are now **interactive** buttons wired to the
     * `local_sentientia_ratings_submit_rating` web service via the `rating_widget`
     * AMD module. Pages that want clickable ratings need to call:
     *
     *     $PAGE->requires->js_call_amd('local_sentientia_ratings/rating_widget', 'init');
     *
     * Pages that want read-only stars can pass $interactive=false; the markup
     * still renders but the AMD module won't pick it up because the
     * `data-airpay-rating` attribute is omitted.
     *
     * Course pages do not choose: the theme calls render_for_viewer(), which is interactive (and loads the widget)
     * only behind the flag sentientia.ratings.widget (owner decision CRS-11, 2026-10-07).
     *
     * @param int    $itemid
     * @param string $ratearea
     * @param bool   $interactive  Default true. Set false for read-only display.
     * @param int|null $userid     Defaults to $USER->id. Pass 0 to skip user lookup.
     * @param string $suffix       HTML (already escaped) shown after the average, inside the widget's element.
     * @return string HTML
     */
    public static function render(int $itemid, string $ratearea,
                                   bool $interactive = true, ?int $userid = null, string $suffix = ''): string {
        global $USER;
        $userid = $userid ?? (int) ($USER->id ?? 0);

        $avg = self::get_average($itemid, $ratearea);
        $myrating = ($interactive && $userid > 1)
            ? self::get_user_rating($itemid, $ratearea, $userid) : 0;

        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            // Filled state: prefer the user's own rating, fall back to the
            // rounded average so a never-rated user still sees the consensus.
            $filled = ($myrating > 0) ? ($i <= $myrating) : ($i <= round($avg->average));
            $iconcls = $filled ? 'fa fa-star' : 'fa fa-star-o';
            $color   = $filled ? '#ed692b'   : '#9c9b97';

            if ($interactive) {
                $aria = s(get_string('rateaccessibility', 'local_sentientia_ratings', $i));
                $stars .= '<button type="button" class="airpay-rating__star btn btn-link p-0 m-0"'
                    . ' data-rating="' . $i . '" aria-label="' . $aria . '">'
                    . '<i class="' . $iconcls . '" style="color:' . $color . ';font-size:18px"></i>'
                    . '</button>';
            } else {
                $stars .= '<i class="' . $iconcls . '" style="color:' . $color . ';font-size:16px"></i>';
            }
        }

        $counttext = $avg->count > 0
            ? '<span class="airpay-rating__count text-muted">(' . $avg->average
                . ' / ' . $avg->count . ')</span>'
            : '<span class="airpay-rating__count text-muted">'
                . s(get_string('noratings', 'local_sentientia_ratings')) . '</span>';

        // Interactive: the AMD widget picks the element up through data-airpay-rating. Read-only (CRS-11): there is nothing to
        // click, so the stars are one image with a text alternative, not five unlabeled icons.
        $extra = $interactive
            ? ' data-airpay-rating data-itemid="' . $itemid
                . '" data-ratearea="' . s($ratearea)
                . '" data-my-rating="' . $myrating . '"'
            : ' role="img" aria-label="' . s($avg->count > 0
                ? get_string('averagesummary', 'local_sentientia_ratings', $avg)
                : get_string('noratings', 'local_sentientia_ratings')) . '"';

        return '<div class="airpay-rating"' . $extra . '>' . $stars . ' ' . $counttext
            . ($suffix === '' ? '' : ' ' . $suffix) . '</div>';
    }

    /**
     * The like and dislike counts of an item, as a small inline summary, or '' when there is nothing to show.
     *
     * Owner decision CRS-12 (2026-10-07), follow-up code: the counts BizLMS showed beside the thumbs, now beside the stars,
     * behind the flag sentientia.ratings.reactions (default OFF). The flip for Airpay stays Nitin's call, after the visual
     * evidence of this very markup is reviewed. Counts only, site-wide per item as in BizLMS, no person data; an item with
     * no like and no dislike shows nothing. The text alternative is the same pair of strings the reviews page uses.
     *
     * @param int $itemid
     * @param string $ratearea
     * @return string HTML, escaped, or ''.
     */
    public static function render_reactions(int $itemid, string $ratearea): string {
        if (!reaction_manager::enabled()) {
            return '';
        }
        $counts = reaction_manager::get_counts($itemid, $ratearea);
        if ($counts->likes === 0 && $counts->dislikes === 0) {
            return '';
        }
        $label = get_string('reactionlikes', 'local_sentientia_ratings', $counts->likes) . ', '
            . get_string('reactiondislikes', 'local_sentientia_ratings', $counts->dislikes);
        return '<span class="airpay-rating__reactions text-muted" role="img" aria-label="' . s($label) . '">'
            . '<i class="fa fa-thumbs-up" aria-hidden="true"></i> ' . (int) $counts->likes . ' '
            . '<i class="fa fa-thumbs-down" aria-hidden="true"></i> ' . (int) $counts->dislikes
            . '</span>';
    }

    /**
     * Do the stars the viewer sees accept a click? Owner decision CRS-11: only when the flag
     * sentientia.ratings.widget is ON for the viewer's customer and tenant AND the viewer is a signed-in non-guest who
     * holds local/sentientia_ratings:rate (the capability the submit web service enforces). Anything else gets read-only
     * stars, so a control is never shown that would only fail.
     *
     * @return bool
     */
    public static function widget_enabled(): bool {
        global $USER;
        if (empty($USER->id) || isguestuser()) {
            return false;
        }
        // The flag registry belongs to the platform plugin: without it (a bare install) the stars stay read-only.
        if (!class_exists('\local_sentientia_platform\feature_flags')
                || !\local_sentientia_platform\feature_flags::is_enabled(self::FLAG_WIDGET)) {
            return false;
        }
        return has_capability('local/sentientia_ratings:rate', \context_system::instance());
    }

    /**
     * The stars of an item for the page the viewer is on: interactive, with the AMD widget loaded once, when
     * widget_enabled(); read-only otherwise (owner decision CRS-11). This is what the theme's course header and course
     * drawer call; render() itself is unchanged and still takes the explicit $interactive choice.
     *
     * @param int $itemid
     * @param string $ratearea
     * @return string HTML
     */
    public static function render_for_viewer(int $itemid, string $ratearea): string {
        global $PAGE;
        $interactive = self::widget_enabled();
        if ($interactive && !self::$widgetrequired) {
            // init() binds every [data-airpay-rating] on the page, so one call serves the header and the drawer alike;
            // a second would bind each star twice and send two requests per click.
            $PAGE->requires->js_call_amd('local_sentientia_ratings/rating_widget', 'init');
            self::$widgetrequired = true;
        }
        return self::render($itemid, $ratearea, $interactive, null, self::render_reactions($itemid, $ratearea));
    }

    /**
     * Forget that the widget was requested (tests, which render several pages in one process).
     *
     * @return void
     */
    public static function reset_widget_request(): void {
        self::$widgetrequired = false;
    }

    /**
     * W1-3 (2026-05-15) — submit (insert-or-update) a rating for a user.
     *
     * Uses Moodle's $DB API. A small race window exists between the SELECT
     * and the INSERT — if a concurrent rate fires in the same instant, the
     * UNIQUE (userid, itemid, ratearea) index will reject the dup with a
     * `dml_write_exception`. We catch that and retry as an UPDATE, which is
     * the correct semantics anyway (one user, one rating per item).
     *
     * @param int    $itemid
     * @param string $ratearea
     * @param int    $userid    Must be a real user (>1; rejects guest=1 + system=0)
     * @param int    $rating    1-5
     * @return int  ID of the rating row (existing or newly-created)
     * @throws \moodle_exception  If rating is out of bounds or userid is invalid.
     */
    public static function submit_rating(int $itemid, string $ratearea,
                                          int $userid, int $rating): int {
        global $DB;

        if ($rating < 1 || $rating > 5) {
            throw new \moodle_exception('invalidrating', 'local_sentientia_ratings');
        }
        if ($userid <= 1) {
            throw new \moodle_exception('cannotrateasguest', 'local_sentientia_ratings');
        }
        if ($itemid <= 0) {
            throw new \moodle_exception('invaliditemid', 'local_sentientia_ratings');
        }
        if (empty($ratearea) || strlen($ratearea) > 100) {
            throw new \moodle_exception('invalidratearea', 'local_sentientia_ratings');
        }

        $key = ['userid' => $userid, 'itemid' => $itemid, 'ratearea' => $ratearea];
        $now = time();

        // Try the update path first (the hot-path for the typical "user
        // revises their rating" case). If no row exists, fall through to
        // insert. Catch the rare race-window dup and retry as update.
        $existing = $DB->get_record(self::TABLE, $key);
        if ($existing) {
            $existing->rating       = $rating;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int) $existing->id;
        }

        try {
            return (int) $DB->insert_record(self::TABLE, (object) [
                'itemid'       => $itemid,
                'ratearea'     => $ratearea,
                'userid'       => $userid,
                'rating'       => $rating,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        } catch (\dml_write_exception $e) {
            // UNIQUE collision — a concurrent submit beat us. Re-read and update.
            $existing = $DB->get_record(self::TABLE, $key, '*', MUST_EXIST);
            $existing->rating       = $rating;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int) $existing->id;
        }
    }
}

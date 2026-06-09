<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace enrol_sentientiasub;

defined('MOODLE_INTERNAL') || die();

/**
 * Subscription lifecycle state machine (ADR-023, increment 2).
 *
 * Owns the pending -> active -> suspended/cancelled transitions and the
 * enrolment grant/revoke side-effects, DECOUPLED from payment. The payment
 * layer (increments 3-4) only calls activate()/suspend()/record_cycle() in
 * response to the Airpay subscription-callback — keeping the sandbox-gated
 * code thin and this business logic shippable + testable now.
 *
 * Enrolment grant is implemented for scope=course (the per-instance default);
 * scope=category and scope=allaccess record state but defer the catalogue-wide
 * enrolment grant to increment 5 (cohort/category sync) — logged, not fatal.
 *
 * @package enrol_sentientiasub
 */
class subscription_manager {

    /** @var string Subscription created, awaiting mandate authorisation. */
    public const STATUS_PENDING = 'pending';
    /** @var string Mandate active, access granted. */
    public const STATUS_ACTIVE = 'active';
    /** @var string Charge failed / lapsed — access suspended, recoverable. */
    public const STATUS_SUSPENDED = 'suspended';
    /** @var string Ended (learner/admin) — access revoked, terminal. */
    public const STATUS_CANCELLED = 'cancelled';

    /** @var string Subscription unlocks the whole catalogue. */
    public const SCOPE_ALLACCESS = 'allaccess';
    /** @var string Subscription unlocks one category. */
    public const SCOPE_CATEGORY = 'category';
    /** @var string Subscription unlocks one course (per-instance default). */
    public const SCOPE_COURSE = 'course';

    private const TABLE = 'enrol_sentientiasub_subscription';

    /**
     * Create a pending subscription record.
     *
     * @return int new subscription id
     */
    public static function create(int $enrolid, int $userid, string $scope, ?int $scopeid,
            ?float $amount, string $currency, string $billingperiod, int $costcenterid): int {
        global $DB;
        $now = time();
        $record = (object) [
            'enrolid'       => $enrolid,
            'userid'        => $userid,
            'status'        => self::STATUS_PENDING,
            'scope'         => $scope,
            'scopeid'       => $scopeid,
            'billingperiod' => $billingperiod,
            'amount'        => $amount,
            'currency'      => $currency,
            'nextchargets'  => 0,
            'lastchargets'  => 0,
            'startedts'     => 0,
            'cancelledts'   => 0,
            'costcenterid'  => $costcenterid,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ];
        return $DB->insert_record(self::TABLE, $record);
    }

    /** @return \stdClass|null */
    public static function get(int $subid): ?\stdClass {
        global $DB;
        $r = $DB->get_record(self::TABLE, ['id' => $subid]);
        return $r ?: null;
    }

    /** @return \stdClass|null the user's subscription on this enrol instance, if any */
    public static function get_by_user_enrol(int $enrolid, int $userid): ?\stdClass {
        global $DB;
        $r = $DB->get_record(self::TABLE, ['enrolid' => $enrolid, 'userid' => $userid]);
        return $r ?: null;
    }

    public static function is_active(int $subid): bool {
        $sub = self::get($subid);
        return $sub !== null && $sub->status === self::STATUS_ACTIVE;
    }

    /**
     * Activate (pending|suspended -> active): set startedts (first time only),
     * schedule the next charge, and grant access.
     */
    public static function activate(int $subid, int $nextchargets = 0): void {
        $sub = self::require_sub($subid);
        $now = time();
        $sub->status       = self::STATUS_ACTIVE;
        $sub->startedts    = $sub->startedts ?: $now;
        $sub->nextchargets = $nextchargets ?: $sub->nextchargets;
        self::save($sub);
        self::grant_access($sub);
    }

    /**
     * Record a successful recurring charge: stamp lastchargets, advance the
     * next charge, and ensure the subscription is active (recovers a suspend).
     */
    public static function record_cycle(int $subid, int $nextchargets): void {
        $sub = self::require_sub($subid);
        $sub->lastchargets = time();
        $sub->nextchargets = $nextchargets;
        $reactivating = ($sub->status !== self::STATUS_ACTIVE);
        $sub->status = self::STATUS_ACTIVE;
        self::save($sub);
        if ($reactivating) {
            self::grant_access($sub);
        }
    }

    /** Suspend (charge failed / lapsed): access suspended, recoverable. */
    public static function suspend(int $subid): void {
        $sub = self::require_sub($subid);
        if ($sub->status === self::STATUS_CANCELLED) {
            return; // terminal — nothing to suspend
        }
        $sub->status = self::STATUS_SUSPENDED;
        self::save($sub);
        if ($sub->scope === self::SCOPE_COURSE) {
            self::set_course_enrol_status($sub, ENROL_USER_SUSPENDED);
        } else {
            self::cohort_set_member($sub, false); // cohorts have no "suspended" state — remove access
        }
    }

    /** Cancel (learner/admin): terminal, revoke access. */
    public static function cancel(int $subid): void {
        $sub = self::require_sub($subid);
        $sub->status      = self::STATUS_CANCELLED;
        $sub->cancelledts = time();
        self::save($sub);
        self::revoke_access($sub);
    }

    // ---- internals --------------------------------------------------------

    private static function require_sub(int $subid): \stdClass {
        $sub = self::get($subid);
        if ($sub === null) {
            throw new \coding_exception('enrol_sentientiasub: subscription not found: ' . $subid);
        }
        return $sub;
    }

    private static function save(\stdClass $sub): void {
        global $DB;
        $sub->timemodified = time();
        $DB->update_record(self::TABLE, $sub);
    }

    private static function grant_access(\stdClass $sub): void {
        global $DB;
        if ($sub->scope !== self::SCOPE_COURSE) {
            // Catalogue-/category-wide grant (ADR-023 increment 5) — cohort-sync.
            self::cohort_set_member($sub, true);
            return;
        }
        $instance = $DB->get_record('enrol', ['id' => $sub->enrolid], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('sentientiasub');
        if (!$plugin) {
            return;
        }
        $plugin->enrol_user($instance, (int) $sub->userid, $instance->roleid ?: null,
            $sub->startedts ?: time(), 0, ENROL_USER_ACTIVE);
    }

    private static function revoke_access(\stdClass $sub): void {
        global $DB;
        if ($sub->scope !== self::SCOPE_COURSE) {
            self::cohort_set_member($sub, false);
            return;
        }
        $instance = $DB->get_record('enrol', ['id' => $sub->enrolid]);
        $plugin = enrol_get_plugin('sentientiasub');
        if ($instance && $plugin) {
            $plugin->unenrol_user($instance, (int) $sub->userid);
        }
    }

    private static function set_course_enrol_status(\stdClass $sub, int $status): void {
        global $DB;
        if ($sub->scope !== self::SCOPE_COURSE) {
            return;
        }
        $instance = $DB->get_record('enrol', ['id' => $sub->enrolid]);
        $plugin = enrol_get_plugin('sentientiasub');
        if ($instance && $plugin) {
            $plugin->update_user_enrol($instance, (int) $sub->userid, $status);
        }
    }

    /**
     * Resolve the cohort backing a catalogue-/category-scope subscription (ADR-023 increment 5).
     * allaccess → the configured `allaccess_cohortid`; category → a cohort whose idnumber is
     * `sentientiasub_cat_<categoryid>` (an admin creates it + cohort-syncs it to that category).
     *
     * @return int|null cohort id, or null if not configured/found (membership then logged + skipped).
     */
    private static function resolve_cohort(\stdClass $sub): ?int {
        global $DB;
        if ($sub->scope === self::SCOPE_ALLACCESS) {
            $cid = (int) get_config('enrol_sentientiasub', 'allaccess_cohortid');
            return $cid > 0 ? $cid : null;
        }
        if ($sub->scope === self::SCOPE_CATEGORY && !empty($sub->scopeid)) {
            $c = $DB->get_record('cohort', ['idnumber' => 'sentientiasub_cat_' . (int) $sub->scopeid]);
            return $c ? (int) $c->id : null;
        }
        return null;
    }

    /**
     * Add/remove the subscriber to/from the scope's cohort. The actual course access comes from
     * Moodle's cohort-sync (enrol_cohort), configured by an admin on the catalogue/category — this
     * only manages membership. Fails soft (logs) when no cohort is resolved.
     */
    private static function cohort_set_member(\stdClass $sub, bool $add): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohortid = self::resolve_cohort($sub);
        if (!$cohortid) {
            debugging('enrol_sentientiasub: no cohort resolved for scope "' . $sub->scope
                . '" — set allaccess_cohortid, or create a cohort with idnumber '
                . 'sentientiasub_cat_<categoryid>. Access ' . ($add ? 'grant' : 'revoke') . ' skipped.',
                DEBUG_DEVELOPER);
            return;
        }
        $uid = (int) $sub->userid;
        if ($add && !cohort_is_member($cohortid, $uid)) {
            cohort_add_member($cohortid, $uid);
        } else if (!$add && cohort_is_member($cohortid, $uid)) {
            cohort_remove_member($cohortid, $uid);
        }
    }
}

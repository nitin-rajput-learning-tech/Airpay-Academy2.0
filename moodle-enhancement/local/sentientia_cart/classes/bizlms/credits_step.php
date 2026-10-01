<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_biz_cart_credits -> local_sentientia_cart_credit_txn, and the balance each user held (ADR-032, mapping
 * doc section 13).
 *
 * The BizLMS table is a journal: every booking is a row, and the current balance is the balance column of the
 * newest row. Each row becomes one credit_txn row. The step is grouped on the user, so one call sees the whole
 * journal of one person and can write that person's balance row (local_sentientia_cart_credits, unique per
 * user) exactly once, as a sub-row of the first imported booking. Nothing recomputes it later and nothing can
 * write it twice.
 *
 * Credits are frozen history. What to do with a balance (honour it, pay it out, write it off, and who owns the
 * liability) is a finance decision that is still open, so nothing in Sentientia acts on a balance: the import
 * writes the figures, and the cart has no reader or writer of them but the privacy provider and the admin credits
 * page, which is behind a flag that is off.
 *
 * Only INR is imported. The balance table holds one currency per user, so a journal row in another currency is
 * skipped and reported (it stays in the legacy table); so is a row whose currency is not a currency.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class credits_step extends step {

    /** The journal's target. */
    public const TARGET = 'local_sentientia_cart_credit_txn';

    /** The balance table. */
    public const BALANCE = 'local_sentientia_cart_credits';

    /** The only currency whose credits are imported. */
    private const CURRENCY = 'INR';

    /** How far apart (seconds) a journal row and the ledger row that caused it may be. */
    private const MATCH_SECONDS = 5;

    /** Ledger event type => journal event type. */
    private const EVENT_OF = [
        'legacy_cancel_to_credit' => 'earned_cancellation',
        'legacy_credit_correction' => 'correction',
        'legacy_credit_redeemed' => 'redeemed',
        'legacy_credit_payout' => 'payout',
    ];

    /**
     * @return string
     */
    public function key(): string {
        return 'cart.credits';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_biz_cart_credits';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return self::TARGET;
    }

    /**
     * The whole journal of one user is one group.
     *
     * @return string[]
     */
    public function group_by(): array {
        return ['userid'];
    }

    /**
     * @param \stdClass[] $rows The journal of one user, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx->legacy);
        $userid = (int) $rows[0]->userid;

        if ($userid <= 0 || !$ctx->lookups->user_exists($userid)) {
            return $this->skip_all($rows, 'orphan_user', 'user_not_found');
        }
        [$root, $method] = support::root_of_user($userid, $ctx);
        if ($root === 0 && $ctx->decision('tenant.unresolved.cart') === 'skip') {
            return $this->skip_all($rows, 'tenant_unresolved', 'tenant_not_resolved');
        }

        // Which rows are imported: INR only.
        $kept = [];
        $out = [];
        foreach ($rows as $row) {
            $currency = support::currency($row->currency ?? null);
            if ($currency === null) {
                $out[] = outcome::skip((int) $row->id, 'currency_invalid', 'currency_invalid');
            } else if ($currency !== self::CURRENCY) {
                $out[] = outcome::skip((int) $row->id, 'currency_not_inr', 'currency_not_inr');
            } else {
                $kept[] = $row;
            }
        }
        if (!$kept) {
            return $out;
        }

        // The ledger rows each booking can be matched to, and the imported orders they belong to.
        $ledger = $evidence->credit_ledger($userid);
        $matches = [];
        $lines = [];
        $ledgerids = [];
        foreach ($kept as $row) {
            $match = self::match($row, $ledger);
            $matches[(int) $row->id] = $match;
            if ($match !== null) {
                $ledgerids[$match['id']] = true;
                $line = ledger_step::line_of((object) [
                    'identifier' => $match['identifier'], 'schistoryid' => $match['schistoryid'],
                ], $evidence);
                if ($line !== null) {
                    $lines[$line] = true;
                }
            }
        }
        $orders = $lines ? $ctx->map->resolve_many('local_biz_cart_history', array_keys($lines)) : [];
        $ledgerrows = $ledgerids ? $ctx->map->resolve_many('local_biz_cart_ledger', array_keys($ledgerids)) : [];

        $earned = 0;
        $spent = 0;
        $balance = 0;
        $modified = 0;
        $first = null;
        foreach ($kept as $row) {
            $id = (int) $row->id;
            $match = $matches[$id];
            $warnings = [];

            $type = 'legacy_unclassified';
            $historyid = null;
            $orderno = null;
            $ledgerid = null;
            if ($match !== null) {
                $type = self::EVENT_OF[$match['type']] ?? 'legacy_unclassified';
                $ledgerid = $ledgerrows[$match['id']] ?? null;
                $line = ledger_step::line_of((object) [
                    'identifier' => $match['identifier'], 'schistoryid' => $match['schistoryid'],
                ], $evidence);
                $historyid = $line === null ? null : ($orders[$line] ?? null);
                if ($historyid !== null && $match['identifier'] > 0) {
                    $orderno = $match['identifier'];
                }
            }
            if ($type === 'legacy_unclassified') {
                $warnings[] = 'credit_unclassified';
            }

            $amount = (float) ($row->credits ?? 0);
            $created = support::epoch($row->timecreated ?? 0) ?: support::epoch($row->timemodified ?? 0);
            $changed = support::epoch($row->timemodified ?? 0) ?: $created;
            if ($created <= 0) {
                $warnings[] = 'no_timestamp';
            }
            $insert = outcome::insert($id, self::TARGET, (object) [
                'userid' => $userid,
                'costcenterid' => $root,
                'event_type' => $type,
                'amount' => round($amount, 2),
                'balance_after' => round((float) ($row->balance ?? 0), 2),
                'currency' => self::CURRENCY,
                'historyid' => $historyid,
                'orderid' => $orderno,
                'ledgerid' => $ledgerid,
                'initiatedby' => max(0, (int) ($row->usermodified ?? 0)),
                'reason' => null,
                'timecreated' => $created,
                'timemodified' => $changed,
            ])->tenant_method($method);
            foreach ($warnings as $warning) {
                $insert->warn($warning);
            }
            $out[] = $insert;

            // The balance is the newest booking's; earned and spent are the positive and the negative changes.
            if ($amount > 0) {
                $earned += support::paise($amount);
            } else if ($amount < 0) {
                $spent += -support::paise($amount);
            }
            $balance = round((float) ($row->balance ?? 0), 2);
            $modified = max($modified, $changed);
            $first ??= $id;
        }

        $out[] = outcome::insert($first, self::BALANCE, (object) [
            'userid' => $userid,
            'balance' => $balance,
            'currency' => self::CURRENCY,
            'lifetime_earned' => $earned / 100,
            'lifetime_spent' => $spent / 100,
            'timemodified' => $modified,
        ], 'balance');
        return $out;
    }

    /**
     * The ledger row that caused a journal booking: the same user, the same amount whatever its sign, within a few
     * seconds. The nearest in time wins; the lower id breaks a tie.
     *
     * @param \stdClass $row A journal row.
     * @param array<int, array> $ledger The user's ledger rows that moved credits.
     * @return array|null
     */
    private static function match(\stdClass $row, array $ledger): ?array {
        $abs = abs(support::paise($row->credits ?? 0));
        $when = support::epoch($row->timecreated ?? 0) ?: support::epoch($row->timemodified ?? 0);
        $best = null;
        $distance = PHP_INT_MAX;
        foreach ($ledger as $candidate) {
            if ($candidate['abs'] !== $abs || $when <= 0 || $candidate['time'] <= 0) {
                continue;
            }
            $gap = abs($candidate['time'] - $when);
            if ($gap <= self::MATCH_SECONDS && ($gap < $distance || ($gap === $distance && $candidate['id'] < $best['id']))) {
                $best = $candidate;
                $distance = $gap;
            }
        }
        return $best;
    }

    /**
     * @param \stdClass[] $rows
     * @param string $reason
     * @param string $detail
     * @return outcome[]
     */
    private function skip_all(array $rows, string $reason, string $detail): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = outcome::skip((int) $row->id, $reason, $detail);
        }
        return $out;
    }
}

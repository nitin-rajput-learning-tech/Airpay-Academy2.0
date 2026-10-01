<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\legacy_reader;

/**
 * What the cart steps know about an order beyond its own rows: the payment-gateway evidence, the order-number
 * index of the history table and the ledger facts the credit journal is matched against (mapping doc section 13).
 *
 * Every index is built the first time a step asks, by paging the legacy table through the framework's bounded
 * reader, and is kept for the run. The legacy tables never change during a run (the source fingerprint says so),
 * so a dry run and an apply run, and every batch of either, read the same answers. One instance serves one
 * legacy_reader, which the runner creates once per run, so nothing outlives the run.
 *
 * The gateway tables (paygw_airpay, paygw_airpay_errorlog, paygw_course_enrolmentlog) and Moodle's own payments
 * table are READ here as evidence and never imported or written: the mapping doc declines them as tables.
 *
 * Every integer index holds ids and integers only, never a name or an address, so memory is one small number
 * per order or per gateway attempt.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evidence {

    /** Rows per page of an index scan (the reader allows 10000). */
    private const PAGE = 5000;

    /** component of the core payments rows BizLMS created. */
    private const PAYMENT_COMPONENT = 'local_biz_cart';

    /** paygw_airpay.status of a paid attempt (BizLMS process.php writes 2 on success). */
    private const ATTEMPT_PAID = 2;

    /** Moodle's payments table. */
    private const PAYMENTS = 'payments';

    /** @var \WeakMap<legacy_reader, self>|null One instance per reader, i.e. per run. */
    private static ?\WeakMap $instances = null;

    /** @var legacy_reader */
    private legacy_reader $legacy;

    /** @var array<int, int[]>|null Order number => history line ids, ascending. */
    private ?array $orderlines = null;

    /** @var array<int, int>|null Order number => earliest timecreated of a sale ledger row. */
    private ?array $ledgerpaid = null;

    /** @var array<int, \stdClass[]>|null Order number => paygw_airpay attempts of the cart payment area. */
    private ?array $attempts = null;

    /** @var array<string, \stdClass[]>|null ap_orderid => paygw_course_enrolmentlog rows. */
    private ?array $enrolments = null;

    /** @var array<string, int>|null airpay_id => earliest timecreated of an "Order Successfull" error-log row. */
    private ?array $success = null;

    /** @var array<int, \stdClass[]>|null Order number => core payments rows of BizLMS. */
    private ?array $payments = null;

    /** @var array<int, array>|null userid => ledger rows that moved credits. */
    private ?array $creditledger = null;

    /** @var array<int, bool> Tenant roots the registry knows, by root. */
    private array $roots = [];

    /**
     * Use of().
     *
     * @param legacy_reader $legacy
     */
    private function __construct(legacy_reader $legacy) {
        $this->legacy = $legacy;
    }

    /**
     * The evidence of the run a reader belongs to.
     *
     * @param legacy_reader $legacy The context's reader.
     * @return self
     */
    public static function of(legacy_reader $legacy): self {
        self::$instances ??= new \WeakMap();
        if (!isset(self::$instances[$legacy])) {
            self::$instances[$legacy] = new self($legacy);
        }
        return self::$instances[$legacy];
    }

    /**
     * Is this a registered tenant root? Asked of the tenant registry, once per root and run.
     *
     * @param int $root
     * @return bool
     */
    public function root_registered(int $root): bool {
        if (!isset($this->roots[$root])) {
            try {
                \local_sentientia_platform\tenant::assert_valid($root);
                $this->roots[$root] = true;
            } catch (\Throwable $e) {
                $this->roots[$root] = false;
            }
        }
        return $this->roots[$root];
    }

    // The order-number index of the history table.

    /**
     * The history line ids of an order, ascending.
     *
     * @param int $identifier The BizLMS order number.
     * @return int[] Empty when no history line carries it.
     */
    public function line_ids(int $identifier): array {
        if ($this->orderlines === null) {
            $this->orderlines = [];
            foreach ($this->rows('local_biz_cart_history', ['identifier']) as $row) {
                $number = (int) ($row->identifier ?? 0);
                if ($number > 0) {
                    $this->orderlines[$number][] = (int) $row->id;
                }
            }
        }
        return $this->orderlines[$identifier] ?? [];
    }

    /**
     * The first history line of an order: the one that carries the order's map row.
     *
     * @param int $identifier
     * @return int|null
     */
    public function first_line(int $identifier): ?int {
        $ids = $this->line_ids($identifier);
        return $ids ? $ids[0] : null;
    }

    // The ledger.

    /**
     * When BizLMS first booked a sale of this order: the earliest ledger row that records a payment.
     *
     * @param int $identifier
     * @return int Epoch seconds, 0 when there is none.
     */
    public function ledger_paid_time(int $identifier): int {
        if ($this->ledgerpaid === null) {
            $this->ledgerpaid = [];
            foreach ($this->rows('local_biz_cart_ledger', ['identifier', 'timecreated'],
                    ['t.paymentstatus = 2 AND t.itemid > 0', []]) as $row) {
                $number = (int) ($row->identifier ?? 0);
                $time = support::epoch($row->timecreated ?? 0);
                if ($number > 0 && $time > 0 && (!isset($this->ledgerpaid[$number]) || $time < $this->ledgerpaid[$number])) {
                    $this->ledgerpaid[$number] = $time;
                }
            }
        }
        return $this->ledgerpaid[$identifier] ?? 0;
    }

    /**
     * The ledger rows of a user that moved credits, for matching the credit journal against.
     *
     * @param int $userid
     * @return array<int, array{id: int, abs: int, time: int, type: string, identifier: int, schistoryid: int}>
     */
    public function credit_ledger(int $userid): array {
        if ($this->creditledger === null) {
            $this->creditledger = [];
            foreach ($this->rows('local_biz_cart_ledger',
                    ['userid', 'credits', 'timecreated', 'timemodified', 'itemid', 'paymentstatus', 'payment', 'area',
                     'identifier', 'schistoryid'],
                    ['t.credits IS NOT NULL AND t.credits <> 0', []]) as $row) {
                $holder = (int) ($row->userid ?? 0);
                if ($holder <= 0) {
                    continue;
                }
                $this->creditledger[$holder][] = [
                    'id' => (int) $row->id,
                    'abs' => abs(support::paise($row->credits)),
                    'time' => support::epoch($row->timecreated ?? 0) ?: support::epoch($row->timemodified ?? 0),
                    'type' => support::ledger_event_type($row),
                    'identifier' => (int) ($row->identifier ?? 0),
                    'schistoryid' => (int) ($row->schistoryid ?? 0),
                ];
            }
        }
        return $this->creditledger[$userid] ?? [];
    }

    // The gateway evidence.

    /**
     * The paygw_airpay attempts of an order (every attempt BizLMS made to collect it), oldest first.
     *
     * @param int $identifier
     * @param int $userid The buyer: BizLMS matched an attempt on the order number AND the user.
     * @return \stdClass[]
     */
    public function attempts(int $identifier, int $userid): array {
        if ($this->attempts === null) {
            $this->attempts = [];
            foreach ($this->rows('paygw_airpay',
                    ['itemid', 'userid', 'ap_orderid', 'cost', 'paymentid', 'status', 'timecreated', 'timemodified'],
                    ['t.paymentarea = :blmarea', ['blmarea' => 'cart']]) as $row) {
                $this->attempts[(int) $row->itemid][] = $row;
            }
        }
        $out = [];
        foreach ($this->attempts[$identifier] ?? [] as $attempt) {
            if ((int) $attempt->userid === $userid) {
                $out[] = $attempt;
            }
        }
        return $out;
    }

    /**
     * The attempt that collected the money: the latest one with status 2.
     *
     * paygw status 1 is ambiguous (the failure branch writes 1, and a helper uses 1 for "paid"), so only 2 counts.
     *
     * @param int $identifier
     * @param int $userid
     * @return \stdClass|null
     */
    public function paid_attempt(int $identifier, int $userid): ?\stdClass {
        $paid = null;
        foreach ($this->attempts($identifier, $userid) as $attempt) {
            if ((int) $attempt->status === self::ATTEMPT_PAID) {
                $paid = $attempt;
            }
        }
        return $paid;
    }

    /**
     * The first core payments row BizLMS made for the order, if any.
     *
     * @param int $identifier
     * @param int $userid
     * @return \stdClass|null
     */
    public function core_payment(int $identifier, int $userid): ?\stdClass {
        if ($this->payments === null) {
            $this->payments = [];
            foreach ($this->rows(self::PAYMENTS, ['itemid', 'userid', 'gateway'],
                    ['t.component = :blmcomp', ['blmcomp' => self::PAYMENT_COMPONENT]]) as $row) {
                $this->payments[(int) $row->itemid][] = $row;
            }
        }
        foreach ($this->payments[$identifier] ?? [] as $payment) {
            if ((int) $payment->userid === $userid) {
                return $payment;
            }
        }
        return null;
    }

    /**
     * The gateway an online (method 0) payment went through: the one Moodle's payments row names, else airpay when
     * an airpay attempt exists, else the generic label online.
     *
     * @param int $identifier
     * @param int $userid
     * @return string
     */
    public function online_gateway(int $identifier, int $userid): string {
        if ($identifier > 0) {
            $payment = $this->core_payment($identifier, $userid);
            if ($payment !== null && trim((string) ($payment->gateway ?? '')) !== '') {
                return trim((string) $payment->gateway);
            }
            if ($this->attempts($identifier, $userid)) {
                return 'airpay';
            }
        }
        return 'online';
    }

    /**
     * The gateway's own reference for the payment: the transaction id the gateway returned (kept in the
     * enrolment log), else the payment id on the attempt, else the id of Moodle's payments row.
     *
     * @param int $identifier
     * @param int $userid
     * @return string|null
     */
    public function gateway_ref(int $identifier, int $userid): ?string {
        $attempt = $this->paid_attempt($identifier, $userid);
        if ($attempt !== null) {
            $log = $this->paid_log((string) $attempt->ap_orderid);
            if ($log !== null) {
                return (string) $log->transactionid;
            }
            $paymentid = trim((string) ($attempt->paymentid ?? ''));
            if ($paymentid !== '' && $paymentid !== '0') {
                return $paymentid;
            }
        }
        $payment = $this->core_payment($identifier, $userid);
        return $payment !== null ? 'payments:' . (int) $payment->id : null;
    }

    /**
     * When the gateway says the order was paid, for an order whose ledger holds no sale row: the time of the
     * enrolment log row, else of the "Order Successfull" error-log row. (paygw_airpay.timemodified is always 0.)
     *
     * @param int $identifier
     * @param int $userid
     * @return int Epoch seconds, 0 when unknown.
     */
    public function gateway_paid_time(int $identifier, int $userid): int {
        $attempt = $this->paid_attempt($identifier, $userid);
        if ($attempt === null) {
            return 0;
        }
        $log = $this->paid_log((string) $attempt->ap_orderid);
        if ($log !== null && support::epoch($log->timecreated ?? 0) > 0) {
            return support::epoch($log->timecreated);
        }
        if ($this->success === null) {
            $this->success = [];
            foreach ($this->rows('paygw_airpay_errorlog', ['airpay_id', 'timecreated'],
                    ['t.order_state = :blmstate', ['blmstate' => 'Order Successfull']]) as $row) {
                $key = (string) $row->airpay_id;
                $time = support::epoch($row->timecreated ?? 0);
                if ($time > 0 && (!isset($this->success[$key]) || $time < $this->success[$key])) {
                    $this->success[$key] = $time;
                }
            }
        }
        return $this->success[(string) $attempt->ap_orderid] ?? 0;
    }

    /**
     * The enrolment-log row that proves the payment: the latest one for this gateway order id with status 2 and a
     * real transaction id (process.php writes the gateway's transaction id there on success).
     *
     * @param string $aporderid
     * @return \stdClass|null
     */
    private function paid_log(string $aporderid): ?\stdClass {
        if ($this->enrolments === null) {
            $this->enrolments = [];
            foreach ($this->rows('paygw_course_enrolmentlog', ['ap_orderid', 'transactionid', 'status', 'timecreated']) as $row) {
                $this->enrolments[(string) $row->ap_orderid][] = $row;
            }
        }
        $found = null;
        foreach ($this->enrolments[$aporderid] ?? [] as $log) {
            $reference = trim((string) ($log->transactionid ?? ''));
            if ((int) $log->status === self::ATTEMPT_PAID && $reference !== '' && $reference !== '0') {
                $found = $log;
            }
        }
        return $found;
    }

    // Reading.

    /**
     * Every row of a table (or of a filtered part of it), in id order, through the bounded reader. A table that
     * does not exist yields nothing, which is how a site without the gateway plugin looks.
     *
     * @param string $table
     * @param string[] $columns
     * @param array{0: string, 1: array} $filter
     * @return \Generator<\stdClass>
     */
    private function rows(string $table, array $columns, array $filter = ['', []]): \Generator {
        if (!$this->legacy->exists($table)) {
            return;
        }
        $after = 0;
        do {
            $rows = $this->legacy->page($table, $after, self::PAGE, $columns, $filter);
            foreach ($rows as $id => $row) {
                $after = (int) $id;
                yield $row;
            }
        } while (count($rows) === self::PAGE);
    }
}

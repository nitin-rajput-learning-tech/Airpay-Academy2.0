<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * Gap-free document numbers for tax invoices and credit notes (CGST Rule 46(b): consecutive, at most 16 characters).
 *
 * The old invoicer counted the invoices of the year and checked that the candidate was free. Two payments at the
 * same moment read the same count and got the same number (one of them then failed, inside the payment), and a
 * failed invoice left no trace to show a gap. This class replaces it with one counter row per series
 * (local_sentientia_cart_docseq):
 *
 *   1. ensure_series(): insert the row if it is missing, seeded with the highest number already in use in that
 *      series (so invoices issued before this table existed are not issued again). A duplicate-key failure means
 *      another process inserted it first: carry on.
 *   2. allocate(), INSIDE the transaction that inserts the document:
 *        UPDATE lastno = lastno + 1  - takes the row lock, so concurrent issuers queue one behind the other
 *        read lastno                 - this transaction's own number
 *      the caller then inserts the document and commits. A rollback undoes the UPDATE, so the number is free again:
 *      there are no gaps.
 *
 * A series is PREFIX-YYYY. The year is the calendar year of the document date, or, with series_period = financial,
 * the year the Indian financial year (April to March) started, both read in Indian time. A number is
 * PREFIX-YYYY-NNNN, NNNN zero-padded to at least four digits; one that would be longer than 16 characters is
 * refused, which rolls the allocation back.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class docseq {

    /** The longest document number the law allows. */
    public const MAX_LENGTH = 16;

    /** The series year runs on Indian time. */
    public const TIMEZONE = 'Asia/Kolkata';

    /** @var string series_period setting: the calendar year. */
    public const PERIOD_CALENDAR = 'calendar';

    /** @var string series_period setting: the financial year, April to March. */
    public const PERIOD_FINANCIAL = 'financial';

    /**
     * The year of a series for a document date.
     *
     * @param int $timestamp The document date.
     * @param string $period PERIOD_CALENDAR or PERIOD_FINANCIAL (anything else is calendar).
     * @return int YYYY; for a financial series, the year the financial year started.
     */
    public static function series_year(int $timestamp, string $period): int {
        $date = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $year = (int) $date->format('Y');
        if ($period === self::PERIOD_FINANCIAL && (int) $date->format('n') < 4) {
            return $year - 1;
        }
        return $year;
    }

    /**
     * The key of a series: PREFIX-YYYY.
     *
     * @param string $prefix
     * @param int $year
     * @return string
     */
    public static function series_key(string $prefix, int $year): string {
        return $prefix . '-' . $year;
    }

    /**
     * The text of a document number.
     *
     * @param string $prefix
     * @param int $year
     * @param int $no
     * @return string PREFIX-YYYY-NNNN (not checked against MAX_LENGTH)
     */
    public static function format_number(string $prefix, int $year, int $no): string {
        return sprintf('%s-%d-%04d', $prefix, $year, $no);
    }

    /**
     * The highest number already in use in a series, from the documents themselves.
     *
     * Reads both document tables: a series is a prefix, and an administrator who gives invoices and credit notes
     * the same prefix then shares one counter, which keeps every number unique.
     *
     * @param string $prefix
     * @param int $year
     * @return int Zero when the series has no document yet.
     */
    public static function highest_in_use(string $prefix, int $year): int {
        global $DB;
        $stem = self::series_key($prefix, $year) . '-';
        $like = $DB->sql_like_escape($stem) . '%';
        $max = 0;
        foreach (['local_sentientia_cart_invoices' => 'invoice_number',
                  'local_sentientia_cart_creditnotes' => 'cn_number'] as $table => $column) {
            $numbers = $DB->get_fieldset_select($table, $column,
                $DB->sql_like($column, ':stem', true, true) . ' AND ' . $column . ' IS NOT NULL', ['stem' => $like]);
            foreach ($numbers as $number) {
                $tail = substr((string) $number, strlen($stem));
                if ($tail !== '' && ctype_digit($tail)) {
                    $max = max($max, (int) $tail);
                }
            }
        }
        return $max;
    }

    /**
     * Make sure the counter row of a series exists, seeded from the documents already issued.
     *
     * Call it BEFORE the issuing transaction starts: a duplicate-key failure inside a transaction would poison it
     * on PostgreSQL. allocate() calls it too, as the first step of the allocation, and then it only finds the row.
     *
     * @param string $prefix
     * @param int $year
     * @return void
     */
    public static function ensure_series(string $prefix, int $year): void {
        global $DB;
        $series = self::series_key($prefix, $year);
        if ($DB->record_exists('local_sentientia_cart_docseq', ['series' => $series])) {
            return;
        }
        try {
            $DB->insert_record('local_sentientia_cart_docseq', (object) [
                'series'       => $series,
                'lastno'       => self::highest_in_use($prefix, $year),
                'timemodified' => time(),
            ]);
        } catch (\dml_exception $e) {
            // Another process inserted the same series between the check and the insert: its row is the one to use.
            if (!$DB->record_exists('local_sentientia_cart_docseq', ['series' => $series])) {
                throw $e;
            }
        }
    }

    /**
     * Is this document number already taken by an invoice or a credit note?
     *
     * @param string $number
     * @return bool
     */
    private static function in_use(string $number): bool {
        global $DB;
        return $DB->record_exists('local_sentientia_cart_invoices', ['invoice_number' => $number])
            || $DB->record_exists('local_sentientia_cart_creditnotes', ['cn_number' => $number]);
    }

    /**
     * Allocate the next number of a series. Call it INSIDE the transaction that inserts the document.
     *
     * The UPDATE holds the row lock until that transaction ends, so two issuers cannot get the same number, and
     * a rollback gives the number back.
     *
     * @param string $prefix
     * @param int $year
     * @return string The document number.
     * @throws \moodle_exception error_numbertoolong when it would be longer than MAX_LENGTH characters
     */
    public static function allocate(string $prefix, int $year): string {
        global $DB;
        if (!$DB->is_transaction_started()) {
            throw new \coding_exception('docseq::allocate() must run inside the transaction that inserts the document');
        }
        self::ensure_series($prefix, $year);
        $series = self::series_key($prefix, $year);

        // The counter normally leads every number in use. If somebody put a document into the series by hand, or a
        // restore brought documents past the counter, step over the numbers that are taken (the unique keys of the
        // two tables would refuse them anyway). The row lock is held, so nobody else moves the counter meanwhile.
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $DB->execute("UPDATE {local_sentientia_cart_docseq}
                             SET lastno = lastno + 1, timemodified = :now
                           WHERE series = :series",
                ['now' => time(), 'series' => $series]);
            $no = (int) $DB->get_field('local_sentientia_cart_docseq', 'lastno', ['series' => $series], MUST_EXIST);
            $number = self::format_number($prefix, $year, $no);
            if (!self::in_use($number)) {
                break;
            }
        }
        if (self::in_use($number)) {
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart', '',
                'Could not find a free document number in series ' . $series);
        }
        if (strlen($number) > self::MAX_LENGTH) {
            throw new \moodle_exception('error_numbertoolong', 'local_sentientia_cart', '',
                (object) ['number' => $number, 'length' => strlen($number), 'max' => self::MAX_LENGTH]);
        }
        return $number;
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * E-invoice particulars of B2B tax documents (spec 2026-10-14, section 6).
 *
 * A tax invoice or credit note issued to a buyer who gave a GSTIN (while the einvoice_b2b setting is on) is numbered
 * but has the status awaiting_irn: it needs the Invoice Reference Number from the government's Invoice
 * Registration Portal (IRP). Sentientia has NO connection to the IRP. Finance registers the document there, then
 * records the answer here (einvoice.php): the IRN (64 hex characters), the acknowledgement number, the
 * acknowledgement date and the signed QR string. The status then becomes issued, the buyer can see the document,
 * and the IRN and the QR print on it.
 *
 * Access: the capability local/sentientia_cart:manageinvoices (no archetype; site administrators hold every
 * capability), and ADR-031 tenant scoping on top: a holder lists and records only the documents of their own tenant.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class einvoice {

    /** Document type: a tax invoice. */
    public const TYPE_INVOICE = 'invoice';

    /** Document type: a credit note. */
    public const TYPE_CREDITNOTE = 'creditnote';

    /** The longest signed QR string a QR code can carry at the lowest error correction, with room to spare. */
    public const QR_MAX_LENGTH = 2900;

    /**
     * The table of a document type.
     *
     * @param string $type TYPE_INVOICE or TYPE_CREDITNOTE
     * @return string
     * @throws \coding_exception for any other type
     */
    public static function table_of(string $type): string {
        switch ($type) {
            case self::TYPE_INVOICE:
                return 'local_sentientia_cart_invoices';
            case self::TYPE_CREDITNOTE:
                return 'local_sentientia_cart_creditnotes';
        }
        throw new \coding_exception('Unknown e-invoice document type: ' . $type);
    }

    /**
     * An acknowledgement date typed into the form, as a timestamp. The portal shows it in Indian time.
     *
     * @param string $text "2026-10-14T15:42", "2026-10-14 15:42" or with seconds.
     * @return int The timestamp, or 0 when the text is not a date.
     */
    public static function parse_ack_date(string $text): int {
        $text = trim($text);
        $tz = new \DateTimeZone(docseq::TIMEZONE);
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $text, $tz);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->getTimestamp();
            }
        }
        return 0;
    }

    /**
     * Check the four particulars. Nothing is stored.
     *
     * @param string $irn 64 hex characters (either case).
     * @param string $ackno The acknowledgement number.
     * @param int $ackdate The acknowledgement date and time; not in the future (a day of clock slack).
     * @param string $signedqr The signed QR string.
     * @param int|null $now Current time (for tests).
     * @return void
     * @throws \moodle_exception error_irninvalid, error_ackinvalid, error_ackdateinvalid, error_qrinvalid
     */
    public static function validate(string $irn, string $ackno, int $ackdate, string $signedqr, ?int $now = null): void {
        $now = $now ?? time();
        if (preg_match('/^[0-9a-fA-F]{64}$/', $irn) !== 1) {
            throw new \moodle_exception('error_irninvalid', 'local_sentientia_cart');
        }
        if (preg_match('/^[0-9A-Za-z\-]{1,40}$/', $ackno) !== 1) {
            throw new \moodle_exception('error_ackinvalid', 'local_sentientia_cart');
        }
        if ($ackdate <= 0 || $ackdate > $now + DAYSECS) {
            throw new \moodle_exception('error_ackdateinvalid', 'local_sentientia_cart');
        }
        if ($signedqr === '' || strlen($signedqr) > self::QR_MAX_LENGTH
                || preg_match('/^[\x21-\x7e]+$/', $signedqr) !== 1) {
            throw new \moodle_exception('error_qrinvalid', 'local_sentientia_cart');
        }
    }

    /**
     * Record the IRN of a document that awaits it. The status becomes issued.
     *
     * @param string $type TYPE_INVOICE or TYPE_CREDITNOTE
     * @param int $id The document.
     * @param string $irn
     * @param string $ackno
     * @param int $ackdate
     * @param string $signedqr
     * @param int|null $userid Who records it; defaults to the current user.
     * @return \stdClass The updated document row.
     * @throws \moodle_exception error_irninvalid, error_ackinvalid, error_ackdateinvalid, error_qrinvalid,
     *                           error_irnduplicate, error_irnnotawaiting, error_outoftenant; required_capability_exception
     */
    public static function record(string $type, int $id, string $irn, string $ackno, int $ackdate,
            string $signedqr, ?int $userid = null): \stdClass {
        global $DB, $USER;
        $userid = $userid ?? (int) $USER->id;
        require_capability('local/sentientia_cart:manageinvoices', \context_system::instance(), $userid);

        $table = self::table_of($type);
        $doc = $DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
        // ADR-031: only inside the holder's own tenant. The refusal is the one a document of another tenant gets.
        cart_manager::require_order_tenant((int) $doc->costcenterid, $userid);
        if ((string) $doc->status !== invoicer::STATUS_AWAITING_IRN) {
            throw new \moodle_exception('error_irnnotawaiting', 'local_sentientia_cart');
        }

        $irn = trim($irn);
        $ackno = trim($ackno);
        $signedqr = trim($signedqr);
        self::validate($irn, $ackno, $ackdate, $signedqr);
        $irn = strtolower($irn);

        // One IRN identifies one document, in either table.
        foreach ([self::TYPE_INVOICE, self::TYPE_CREDITNOTE] as $t) {
            $other = $DB->get_record(self::table_of($t), ['irn' => $irn], 'id');
            if ($other && !($t === $type && (int) $other->id === $id)) {
                throw new \moodle_exception('error_irnduplicate', 'local_sentientia_cart');
            }
        }

        $update = (object) [
            'id'        => $doc->id,
            'irn'       => $irn,
            'ack_no'    => $ackno,
            'ack_date'  => $ackdate,
            'signed_qr' => $signedqr,
            'status'    => invoicer::STATUS_ISSUED,
        ];
        $DB->update_record($table, $update);
        return $DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The documents that wait for an IRN in the current user's scope, oldest first: every tenant for a cross-tenant
     * user, the user's own tenant for anybody else, nothing for a user whose tenant does not resolve.
     *
     * @param int $limit Most rows per document type.
     * @return array[] Each: type (TYPE_*), id, number, date (timestamp), costcenterid, billing_name, billing_gstn,
     *                 total (float), currency
     */
    public static function awaiting(int $limit = 200): array {
        global $DB;
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::sql_filter('d');
        $rows = [];

        $invoices = $DB->get_records_sql(
            "SELECT d.id, d.invoice_number AS docnumber, d.timecreated, d.costcenterid, d.billing_name, d.billing_gstn,
                    d.total, d.currency
               FROM {local_sentientia_cart_invoices} d
              WHERE d.status = :awaiting AND $tnsql
           ORDER BY d.timecreated ASC, d.id ASC",
            ['awaiting' => invoicer::STATUS_AWAITING_IRN] + $tnargs, 0, $limit);
        foreach ($invoices as $d) {
            $rows[] = self::row(self::TYPE_INVOICE, $d);
        }

        $notes = $DB->get_records_sql(
            "SELECT d.id, d.cn_number AS docnumber, d.timecreated, d.costcenterid, d.billing_name, d.billing_gstn,
                    d.total, d.currency
               FROM {local_sentientia_cart_creditnotes} d
              WHERE d.status = :awaiting AND $tnsql
           ORDER BY d.timecreated ASC, d.id ASC",
            ['awaiting' => invoicer::STATUS_AWAITING_IRN] + $tnargs, 0, $limit);
        foreach ($notes as $d) {
            $rows[] = self::row(self::TYPE_CREDITNOTE, $d);
        }

        usort($rows, static fn($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
        return $rows;
    }

    /**
     * One listing row.
     *
     * @param string $type
     * @param \stdClass $d
     * @return array
     */
    private static function row(string $type, \stdClass $d): array {
        return [
            'type'         => $type,
            'id'           => (int) $d->id,
            'number'       => (string) $d->docnumber,
            'date'         => (int) $d->timecreated,
            'costcenterid' => (int) $d->costcenterid,
            'billing_name' => (string) $d->billing_name,
            'billing_gstn' => (string) $d->billing_gstn,
            'total'        => (float) $d->total,
            'currency'     => (string) $d->currency,
        ];
    }
}

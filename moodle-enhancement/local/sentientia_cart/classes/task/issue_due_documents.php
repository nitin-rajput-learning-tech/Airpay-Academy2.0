<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\task;

use local_sentientia_cart\credit_notes;
use local_sentientia_cart\invoicer;

defined('MOODLE_INTERNAL') || die();

/**
 * Issues every GST tax invoice and credit note that is still due (spec 2026-10-14, section 6). Every 15 minutes.
 *
 * A paid order is marked invoice-due inside the payment and a refund leaves a due credit note; the request that did
 * it tries to issue the document at once, in its own transaction. This task is the retry, and the only issuer when
 * that request could not (incomplete settings, a number that would be longer than 16 characters, a database error,
 * or a payment that ran inside someone else's transaction).
 *
 * While a document cannot be issued because the tax settings are incomplete (or its number would be too long), the
 * site administrators are warned, once a day, until it is fixed; the documents stay due and are issued by the first
 * run after the fix. While the invoice flag is OFF for an order's tenant nothing of that tenant is issued (the
 * document stays due, silently: that is the flag doing its job).
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_due_documents extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_issue_due_documents', 'local_sentientia_cart');
    }

    /**
     * Issue what is due, then warn the administrators if something could not be issued.
     *
     * @return void
     */
    public function execute() {
        $invoices = invoicer::issue_due();
        $notes = credit_notes::issue_due();

        $causes = [];
        foreach (array_merge($invoices['causes'], $notes['causes']) as $cause) {
            $causes[md5(serialize($cause))] = $cause;
        }
        $warned = $causes ? invoicer::warn_admins_once_a_day(array_values($causes)) : false;

        mtrace(sprintf('sentientia_cart: invoices issued=%d skipped=%d failed=%d; credit notes issued=%d skipped=%d failed=%d%s',
            $invoices['issued'], $invoices['skipped'], $invoices['failed'],
            $notes['issued'], $notes['skipped'], $notes['failed'],
            $warned ? '; administrators warned' : ''));
    }
}

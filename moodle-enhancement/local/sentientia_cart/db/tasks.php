<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled tasks of local_sentientia_cart.
 *
 * issue_due_documents (2026-10-14, GST tax invoices spec section 6): a paid order is marked invoice-due inside the
 * payment and a refunded order gets a due credit note; the request that did it tries to issue the document at
 * once, and this task issues everything still due. It is the retry for a document the first attempt could not
 * issue (incomplete settings, a number that would be too long, a database hiccup) and the only issuer when the
 * payment ran inside somebody else's transaction. While the tax settings are incomplete it warns the site
 * administrators once a day.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_cart\task\issue_due_documents',
        'blocking'  => 0,
        'minute'    => '*/15',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * E-invoice IRN page: the B2B tax invoices and credit notes that wait for their IRN, and the form where Finance
 * records it (spec 2026-10-14, section 6). Sentientia has no connection to the government's Invoice Registration
 * Portal: Finance registers the document there and records the answer here.
 *
 * Access: local/sentientia_cart:manageinvoices (no archetype; site administrators hold it), and ADR-031 tenant
 * scoping on top: a holder lists and records only the documents of their own tenant. Every write is a POST with
 * the session key, and the capability, the tenant, the status and every field are checked again on the server.
 *
 * @package local_sentientia_cart
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_cart/einvoice.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('einvoice_title', 'local_sentientia_cart'));
$PAGE->set_heading(get_string('einvoice_title', 'local_sentientia_cart'));
require_capability('local/sentientia_cart:manageinvoices', $ctx);
if (!\local_sentientia_cart\flags::invoices_for_viewer((int) $USER->id)) {
    // GST tax invoices are OFF wherever this viewer works: there is nothing to record (review 2026-10-14, should-fix 5).
    throw new moodle_exception('error_einvoiceoff', 'local_sentientia_cart');
}

$self = new moodle_url('/local/sentientia_cart/einvoice.php');

if (optional_param('action', '', PARAM_ALPHA) === 'record' && data_submitted()) {
    require_sesskey();
    $type = required_param('type', PARAM_ALPHA);
    $docid = required_param('id', PARAM_INT);
    try {
        if (!in_array($type, [\local_sentientia_cart\einvoice::TYPE_INVOICE,
                \local_sentientia_cart\einvoice::TYPE_CREDITNOTE], true)) {
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart', '', 'Unknown document type');
        }
        $doc = \local_sentientia_cart\einvoice::record(
            $type,
            $docid,
            required_param('irn', PARAM_RAW_TRIMMED),
            required_param('ack_no', PARAM_RAW_TRIMMED),
            \local_sentientia_cart\einvoice::parse_ack_date(required_param('ack_date', PARAM_RAW_TRIMMED)),
            required_param('signed_qr', PARAM_RAW_TRIMMED),
            (int) $USER->id);
        $number = $type === \local_sentientia_cart\einvoice::TYPE_INVOICE ? $doc->invoice_number : $doc->cn_number;
        redirect($self, get_string('einvoice_recorded', 'local_sentientia_cart', $number), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (\moodle_exception $e) {
        // Keep the page, show why: nothing was recorded.
        \core\notification::error($e->getMessage());
    }
}

$rows = [];
foreach (\local_sentientia_cart\einvoice::awaiting() as $d) {
    $isinvoice = $d['type'] === \local_sentientia_cart\einvoice::TYPE_INVOICE;
    $rows[] = [
        'type'         => $d['type'],
        'type_label'   => get_string($isinvoice ? 'einvoice_type_invoice' : 'einvoice_type_creditnote', 'local_sentientia_cart'),
        'id'           => $d['id'],
        'number'       => $d['number'],
        'date'         => userdate($d['date'], '%d %b %Y'),
        'billing_name' => $d['billing_name'],
        'billing_gstn' => $d['billing_gstn'],
        'total'        => \local_sentientia_cart\invoicer::currency_symbol($d['currency'])
            . \local_sentientia_cart\gst::money($d['total'], $d['currency']),
        'view_url'     => (new moodle_url($isinvoice ? '/local/sentientia_cart/invoice.php'
            : '/local/sentientia_cart/creditnote.php', ['id' => $d['id']]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_cart/einvoice', [
    'has_rows'   => !empty($rows),
    'rows'       => $rows,
    'sesskey'    => sesskey(),
    'action_url' => $self->out(false),
]);
echo $OUTPUT->footer();

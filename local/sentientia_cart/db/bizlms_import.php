<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-032: the BizLMS importers this plugin owns. Discovered by
 * \local_sentientia_platform\bizlms\registry, the way db/feature_flags.php files are.
 *
 * cart: local_biz_cart_history, _id, _ledger, _invoices and _credits -> local_sentientia_cart_history,
 * _ledger, _invoices, _credit_txn and _credits, as frozen, admin-only history (mapping doc section 13).
 *
 * @package local_sentientia_cart
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    'cart' => \local_sentientia_cart\bizlms\importer::class,
];

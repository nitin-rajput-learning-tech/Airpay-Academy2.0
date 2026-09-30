<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

/**
 * Are requests imported from BizLMS shown in the request lists?
 *
 * The BizLMS import (ADR-032) fills local_sentientia_request with the requests learners made before the
 * cutover and marks each row legacy_source = 'bizlms'. Whether that history is visible is a reader
 * decision, and the ADR's rule is that a changed reader surface ships behind a default-OFF feature flag
 * that the import never flips (the owner decision framework.reader_flags_airpay_at_cutover: the flip
 * is Nitin's call, after the visual evidence). So with the flag OFF - the default - the three lists
 * (My requests, Pending approvals, All requests) and the approver nav badge behave exactly as they did
 * before the import: they leave imported rows out.
 *
 * The scheduled tasks do not use this switch. They skip imported rows always (request_manager).
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class imported_history {

    /** Feature flag key (registered in db/feature_flags.php, default OFF). */
    public const FLAG = 'sentientia.request.imported_history';

    /** Value of legacy_source on a row the BizLMS import wrote. */
    public const SOURCE_BIZLMS = 'bizlms';

    /**
     * Is the flag on for the current user's customer and tenant?
     *
     * @return bool
     */
    public static function visible(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * The SQL to AND onto a WHERE over the request table so imported rows are left out while the flag is off.
     *
     * @param string $alias Table alias, or '' when the query has none.
     * @return string '' when imported rows may be shown, else ' AND <alias.>legacy_source IS NULL'.
     */
    public static function filter_sql(string $alias = 'r'): string {
        if (self::visible()) {
            return '';
        }
        return ' AND ' . ($alias === '' ? '' : $alias . '.') . 'legacy_source IS NULL';
    }
}

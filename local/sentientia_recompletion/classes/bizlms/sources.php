<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Names of what the recompletion import reads and writes, in one place so a step, the evidence index and
 * the importer cannot spell a table differently.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sources {

    /** Feature key of the importer. */
    public const FEATURE = 'recompletion';

    /** Legacy tables (BizLMS local_recompletion 2023012600 has exactly these 16). */
    public const CONFIG = 'local_recompletion_config';
    public const CC = 'local_recompletion_cc';
    public const CC_CC = 'local_recompletion_cc_cc';
    public const CMC = 'local_recompletion_cmc';
    public const QA = 'local_recompletion_qa';
    public const QG = 'local_recompletion_qg';
    public const SST = 'local_recompletion_sst';
    public const LTIA = 'local_recompletion_ltia';
    public const QR = 'local_recompletion_qr';
    public const QR_BOOL = 'local_recompletion_qr_bool';
    public const QR_DATE = 'local_recompletion_qr_date';
    public const QR_M = 'local_recompletion_qr_m';
    public const QR_OTHER = 'local_recompletion_qr_other';
    public const QR_RANK = 'local_recompletion_qr_rank';
    public const QR_SINGLE = 'local_recompletion_qr_single';
    public const QR_TEXT = 'local_recompletion_qr_text';

    /** The core log table the reset events are read from (a core source, not a legacy table). */
    public const LOG = 'logstore_standard_log';

    /** Accounting unit of the rules step: one rule per course of the legacy config. */
    public const RULE_UNIT = '#local_recompletion_config.course';

    /** Accounting unit of the events step: one history row per completion_reset log row. */
    public const EVENT_UNIT = '#logstore_standard_log.completion_reset';

    /** Target tables. */
    public const RULES = 'local_sentientia_recompletion_rules';
    public const HISTORY = 'local_sentientia_recompletion_history';
    public const ARCHIVE = 'local_sentientia_recompletion_archive';

    /** Event name of a legacy reset, as the standard log stores it. */
    public const RESET_EVENT = '\\local_recompletion\\event\\completion_reset';

    /** Event name of a course completion, as the standard log stores it. */
    public const COMPLETED_EVENT = '\\core\\event\\course_completed';

    /** The value of history.source for an imported row. */
    public const LEGACY = 'legacy';

    /**
     * Every legacy table the feature owns, which is the set the log source is tied to.
     *
     * @return string[]
     */
    public static function legacy_tables(): array {
        return [
            self::CONFIG, self::CC, self::CC_CC, self::CMC, self::QA, self::QG, self::SST, self::LTIA, self::QR,
            self::QR_BOOL, self::QR_DATE, self::QR_M, self::QR_OTHER, self::QR_RANK, self::QR_SINGLE, self::QR_TEXT,
        ];
    }

    /**
     * The source filter that selects the legacy reset events of the log.
     *
     * @return array{0: string, 1: array}
     */
    public static function reset_filter(): array {
        return ['t.eventname = :blmresetevent', ['blmresetevent' => self::RESET_EVENT]];
    }

    /**
     * The source filter that selects course completion events of the log.
     *
     * @return array{0: string, 1: array}
     */
    public static function completed_filter(): array {
        return ['t.eventname = :blmcompletedevent', ['blmcompletedevent' => self::COMPLETED_EVENT]];
    }
}

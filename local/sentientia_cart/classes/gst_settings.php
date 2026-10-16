<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The tax settings of local_sentientia_cart, read with their defaults in one place (spec 2026-10-14, section 3).
 *
 * get_config() returns false for a setting nobody has saved (an admin_setting default is only stored when the
 * settings are applied), so every read here falls back to the same default settings.php declares. The constants are
 * the single source of those defaults.
 *
 * Invoicing refuses to start while problems() is not empty: the document stays due, and the issuing task warns the
 * site administrators once a day.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gst_settings {

    /** Default invoice number prefix. ERPNEXT is reserved for invoices imported from BizLMS. */
    public const DEFAULT_INVOICE_PREFIX = 'AIRPAY';

    /**
     * Default credit note number prefix.
     *
     * The spec named AIRPAYCN, but AIRPAYCN-2026-0001 is 18 characters and a number may not exceed 16 (CGST Rule
     * 46(b)), so with that default no credit note could ever be issued. AIRCN-2026-0001 is 15 and leaves room for
     * five digits. Finance confirms the prefixes before the flag goes on (spec section 10, point 3).
     */
    public const DEFAULT_CREDIT_NOTE_PREFIX = 'AIRCN';

    /** Default GST rate in percent. */
    public const DEFAULT_GST_RATE = 18;

    /** Default retention of tax documents, in years, before erasure may blank the buyer's details. */
    public const DEFAULT_RETENTION_YEARS = 8;

    /** The reserved invoice prefix of an invoice imported from BizLMS (ADR-032). */
    public const RESERVED_PREFIX = 'ERPNEXT';

    /**
     * One setting, trimmed.
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    private static function text(string $name, string $default = ''): string {
        $value = get_config('local_sentientia_cart', $name);
        if ($value === false || $value === null) {
            return $default;
        }
        return trim((string) $value);
    }

    /**
     * @return string The supplier's name on the document.
     */
    public static function company_name(): string {
        return self::text('company_name');
    }

    /**
     * @return string The supplier's address (lines separated by line breaks).
     */
    public static function company_address(): string {
        return self::text('company_address');
    }

    /**
     * @return string The supplier's GSTIN, upper-case.
     */
    public static function our_gstn(): string {
        return gst::normalise_gstin(self::text('our_gstn'));
    }

    /**
     * @return string The SAC of the service. No default: Finance confirms it.
     */
    public static function sac_code(): string {
        return self::text('sac_code');
    }

    /**
     * @return string The supplier's GST state code: the setting, else the first two digits of the GSTIN. Empty when
     *                neither gives a valid state.
     */
    public static function supplier_state(): string {
        return gst::supplier_state(self::text('supplier_state_code'), self::text('our_gstn'));
    }

    /**
     * @return string The invoice number prefix.
     */
    public static function invoice_prefix(): string {
        return self::text('invoice_prefix', self::DEFAULT_INVOICE_PREFIX) ?: self::DEFAULT_INVOICE_PREFIX;
    }

    /**
     * @return string The credit note number prefix.
     */
    public static function credit_note_prefix(): string {
        return self::text('credit_note_prefix', self::DEFAULT_CREDIT_NOTE_PREFIX) ?: self::DEFAULT_CREDIT_NOTE_PREFIX;
    }

    /**
     * @return string docseq::PERIOD_CALENDAR or docseq::PERIOD_FINANCIAL.
     */
    public static function series_period(): string {
        return self::text('series_period', docseq::PERIOD_CALENDAR) === docseq::PERIOD_FINANCIAL
            ? docseq::PERIOD_FINANCIAL : docseq::PERIOD_CALENDAR;
    }

    /**
     * @return bool Does a buyer who gives a GSTIN need an e-invoice IRN? Default yes.
     */
    public static function einvoice_b2b(): bool {
        $value = get_config('local_sentientia_cart', 'einvoice_b2b');
        return $value === false || $value === null ? true : (bool) $value;
    }

    /**
     * @return int Years a tax document is kept unblanked; at least 1, default 8.
     */
    public static function retention_years(): int {
        $years = (int) self::text('invoice_retention_years', (string) self::DEFAULT_RETENTION_YEARS);
        return $years >= 1 ? $years : self::DEFAULT_RETENTION_YEARS;
    }

    /**
     * @param string|null $lang Language of the default text; null for the current language.
     * @return string The signatory line: the setting, else "Authorised signatory".
     */
    public static function signatory_text(?string $lang = null): string {
        $value = self::text('signatory_text');
        if ($value !== '') {
            return $value;
        }
        return get_string_manager()->get_string('signatory_default', 'local_sentientia_cart', null, $lang);
    }

    /**
     * What stops invoicing from starting: the setting names, as lang string ids, each at most once.
     *
     * company_name, company_address, our_gstn and sac_code must not be empty, and the supplier state must be a
     * valid GST state code (the setting, or the first two digits of the GSTIN).
     *
     * @return string[] Setting ids: company_name, company_address, our_gstn, sac_code, supplier_state.
     */
    public static function problems(): array {
        $problems = [];
        if (self::company_name() === '') {
            $problems[] = 'company_name';
        }
        if (self::company_address() === '') {
            $problems[] = 'company_address';
        }
        // Our GSTIN must be a GSTIN, and a supplier state set by hand must be the one the GSTIN says: otherwise every
        // invoice would print one state and split the tax by another (review 2026-10-14, should-fix 6).
        $gstin = gst::normalise_gstin(self::our_gstn());
        if ($gstin === '' || !gst::is_gstin_format($gstin)) {
            $problems[] = 'our_gstn';
        }
        if (self::sac_code() === '') {
            $problems[] = 'sac_code';
        }
        $state = self::supplier_state();
        $configured = trim(self::text('supplier_state_code'));
        if ($state === '' || ($configured !== '' && gst::is_gstin_format($gstin)
                && $configured !== gst_states::from_gstin($gstin))) {
            $problems[] = 'supplier_state';
        }
        return $problems;
    }

    /**
     * The problems() ids as the setting labels an administrator sees.
     *
     * @param string[] $problems
     * @param string|null $lang
     * @return string Comma-separated labels.
     */
    public static function problem_labels(array $problems, ?string $lang = null): string {
        $labels = [];
        foreach ($problems as $id) {
            $labels[] = get_string_manager()->get_string('settings_problem_' . $id, 'local_sentientia_cart', null, $lang);
        }
        return implode(', ', $labels);
    }
}

<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The Indian states and union territories with their GST state codes.
 *
 * One list for the whole plugin: the checkout field, the GSTIN-versus-state check, the supplier state setting, the
 * place of supply on a document. A GST state code is the two-digit prefix of a GSTIN; the codes are 01 to 38 and 97
 * ("Other Territory"). Names live in the lang files (gst_state_NN, en and hi), so the codes are the only thing in
 * code, and a name can be read in any language.
 *
 * Two codes are historical but still appear in live GSTINs: 25 (Daman and Diu, merged into 26 in 2020) and 28
 * (Andhra Pradesh before the Telangana split; 37 is Andhra Pradesh now).
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gst_states {

    /** The code of "Other Territory". */
    public const OTHER_TERRITORY = '97';

    /**
     * Every valid GST state code, two digits, in order.
     *
     * @return string[]
     */
    public static function codes(): array {
        $codes = [];
        for ($i = 1; $i <= 38; $i++) {
            $codes[] = sprintf('%02d', $i);
        }
        $codes[] = self::OTHER_TERRITORY;
        return $codes;
    }

    /**
     * Is this a valid GST state code?
     *
     * @param string $code
     * @return bool
     */
    public static function is_valid(string $code): bool {
        return in_array($code, self::codes(), true);
    }

    /**
     * The name of a state, in a language (the current one by default).
     *
     * @param string $code A valid code.
     * @param string|null $lang A language code; null for the current language.
     * @return string The name, or the bare code when it is not a valid code.
     */
    public static function name(string $code, ?string $lang = null): string {
        if (!self::is_valid($code)) {
            return $code;
        }
        return get_string_manager()->get_string('gst_state_' . $code, 'local_sentientia_cart', null, $lang);
    }

    /**
     * "27 - Maharashtra": the code and the name, for a document and for the select.
     *
     * @param string $code
     * @param string|null $lang
     * @return string
     */
    public static function label(string $code, ?string $lang = null): string {
        return self::is_valid($code) ? $code . ' - ' . self::name($code, $lang) : $code;
    }

    /**
     * The options of a state select: code => "27 - Maharashtra".
     *
     * @param string|null $lang
     * @return array<string, string>
     */
    public static function options(?string $lang = null): array {
        $options = [];
        foreach (self::codes() as $code) {
            $options[$code] = self::label($code, $lang);
        }
        return $options;
    }

    /**
     * The state a GSTIN belongs to: its first two digits.
     *
     * @param string $gstin
     * @return string The two digits, or '' when the text does not start with two digits.
     */
    public static function from_gstin(string $gstin): string {
        $gstin = trim($gstin);
        return preg_match('/^\d{2}/', $gstin) === 1 ? substr($gstin, 0, 2) : '';
    }
}

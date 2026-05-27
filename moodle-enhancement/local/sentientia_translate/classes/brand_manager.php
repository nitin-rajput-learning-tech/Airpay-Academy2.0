<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-customer brand-name preservation for AI content translation.
 *
 * Two responsibilities:
 *
 *  1. PROTECTED TERMS — a list of brand / product / regulatory terms that
 *     must NEVER be naively translated (e.g. "Airpay", "UPI", "RBI").
 *     The prompt builder feeds this list to Claude with an instruction to
 *     keep them verbatim unless a script override is supplied. Built from
 *     {@see DEFAULT_PROTECTED} (always-on) plus every `brand_source` row a
 *     customer has configured.
 *
 *  2. SCRIPT OVERRIDES — a per-(customer, target-language) map of
 *     brand_source -> brand_target (e.g. "Airpay" -> the Kannada-script
 *     rendering). Applied as a deterministic post-processing pass on the
 *     translated text via {@see apply_overrides}. Deterministic
 *     post-processing means brand rendering is GUARANTEED regardless of
 *     what the model returned — and unit-testable without the API.
 *
 * The substitution is whole-token, case-sensitive, and longest-first so a
 * multi-word brand ("Airpay Payment Services") wins over a sub-token
 * ("Airpay").
 *
 * @package local_sentientia_translate
 */
class brand_manager {

    public const TABLE = 'local_sentientia_tr_brand';

    /**
     * Always-protected terms. These are kept verbatim in every translation
     * unless a customer supplies a script override. Curated for the Airpay
     * fintech-compliance domain.
     */
    public const DEFAULT_PROTECTED = [
        'Airpay',
        'Sentientia',
        'UPI',
        'RBI',
        'KYC',
        'PAN',
        'Aadhaar',
        'FIU-IND',
        'NEFT',
        'RTGS',
        'IMPS',
        'SCORM',
    ];

    /** Supported target languages (code => English label). */
    public const TARGET_LANGS = [
        'hi' => 'Hindi',
        'mr' => 'Marathi',
        'kn' => 'Kannada',
        'sw' => 'Swahili',
    ];

    /**
     * Is the given code a supported target language?
     *
     * @param string $lang
     * @return bool
     */
    public static function is_supported_lang(string $lang): bool {
        return isset(self::TARGET_LANGS[$lang]);
    }

    /**
     * Distinct protected brand terms for a customer — DEFAULT_PROTECTED
     * merged with every configured `brand_source` for that customer.
     *
     * @param int $customerid
     * @return string[] De-duplicated list of source brand terms.
     */
    public static function get_protected_terms(int $customerid): array {
        global $DB;

        $terms = self::DEFAULT_PROTECTED;
        try {
            $rows = $DB->get_fieldset_select(self::TABLE, 'DISTINCT brand_source',
                'customerid = :cid', ['cid' => $customerid]);
            foreach ($rows as $t) {
                $terms[] = $t;
            }
        } catch (\Throwable $e) {
            // Table missing in unit-test sandbox before install — defaults only.
        }

        // De-dupe preserving order.
        $seen = [];
        $out = [];
        foreach ($terms as $t) {
            $t = trim((string)$t);
            if ($t === '' || isset($seen[$t])) {
                continue;
            }
            $seen[$t] = true;
            $out[] = $t;
        }
        return $out;
    }

    /**
     * Get the script-override map for a (customer, target-language) pair.
     *
     * @param int    $customerid
     * @param string $targetlang
     * @return array<string,string> brand_source => brand_target
     */
    public static function get_overrides(int $customerid, string $targetlang): array {
        global $DB;
        $map = [];
        try {
            $rows = $DB->get_records(self::TABLE,
                ['customerid' => $customerid, 'targetlang' => $targetlang]);
            foreach ($rows as $r) {
                $map[(string)$r->brand_source] = (string)$r->brand_target;
            }
        } catch (\Throwable $e) {
            // Table missing — empty map.
        }
        return $map;
    }

    /**
     * Apply the script-override map to translated text as a deterministic
     * post-processing pass. Returns [text, count_applied].
     *
     * Replacement is whole-word (bounded by non-letter/non-digit edges so
     * we don't substitute inside another word) and processes the longest
     * source terms first so multi-word brands win over their prefixes.
     *
     * @param string                $text       Translated text
     * @param array<string,string>  $overrides  brand_source => brand_target
     * @return array{0:string,1:int} [text with substitutions, number applied]
     */
    public static function apply_overrides(string $text, array $overrides): array {
        if (empty($overrides) || $text === '') {
            return [$text, 0];
        }

        // Longest source first.
        $sources = array_keys($overrides);
        usort($sources, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $count = 0;
        foreach ($sources as $src) {
            $tgt = $overrides[$src];
            if ($src === '' || $src === $tgt) {
                continue;
            }
            // Whole-token boundary: not preceded/followed by a word char.
            // Use \p{L} to respect unicode letters. Case-sensitive on purpose
            // — brand names carry deliberate casing.
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($src, '/') . '(?![\p{L}\p{N}])/u';
            $replaced = preg_replace_callback($pattern, function () use ($tgt, &$count) {
                $count++;
                return $tgt;
            }, $text);
            if ($replaced !== null) {
                $text = $replaced;
            }
        }
        return [$text, $count];
    }

    /**
     * Convenience wrapper — load the override map for (customer, lang) and
     * apply it to text in one call.
     *
     * @param string $text
     * @param int    $customerid
     * @param string $targetlang
     * @return array{0:string,1:int}
     */
    public static function apply_for(string $text, int $customerid, string $targetlang): array {
        $map = self::get_overrides($customerid, $targetlang);
        return self::apply_overrides($text, $map);
    }

    /**
     * Insert or update a brand override for (customer, brand, lang).
     *
     * @param int    $customerid
     * @param string $brandsource
     * @param string $targetlang
     * @param string $brandtarget
     * @return int   The row id
     */
    public static function set_override(int $customerid, string $brandsource,
                                        string $targetlang, string $brandtarget): int {
        global $DB;
        $brandsource = trim($brandsource);
        $brandtarget = trim($brandtarget);
        if ($brandsource === '' || $brandtarget === '') {
            throw new \coding_exception('brand_source and brand_target must be non-empty');
        }
        if (!self::is_supported_lang($targetlang)) {
            throw new \coding_exception("Unsupported target language: {$targetlang}");
        }

        $now = time();
        $existing = $DB->get_record(self::TABLE, [
            'customerid'   => $customerid,
            'brand_source' => $brandsource,
            'targetlang'   => $targetlang,
        ]);

        if ($existing) {
            $existing->brand_target = $brandtarget;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int)$existing->id;
        }

        $row = new \stdClass();
        $row->customerid   = $customerid;
        $row->brand_source = $brandsource;
        $row->targetlang   = $targetlang;
        $row->brand_target = $brandtarget;
        $row->timecreated  = $now;
        $row->timemodified = $now;
        return $DB->insert_record(self::TABLE, $row);
    }

    /**
     * Delete a brand override row.
     *
     * @param int $id
     * @param int $customerid Ownership guard — must match the row's customer.
     * @return bool
     */
    public static function delete_override(int $id, int $customerid): bool {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $id], 'id, customerid', IGNORE_MISSING);
        if (!$row || (int)$row->customerid !== $customerid) {
            return false;
        }
        $DB->delete_records(self::TABLE, ['id' => $id]);
        return true;
    }

    /**
     * List all brand overrides for a customer (for the admin management UI).
     *
     * @param int $customerid
     * @return \stdClass[]
     */
    public static function list_for_customer(int $customerid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE,
            ['customerid' => $customerid], 'brand_source ASC, targetlang ASC'));
    }
}

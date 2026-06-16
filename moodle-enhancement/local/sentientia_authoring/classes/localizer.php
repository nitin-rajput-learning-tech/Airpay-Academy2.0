<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Localization router for the Authoring Studio.
 *
 * Per the P0.3 brief, localized output is routed through the existing
 * local_sentientia_translate plugin — and MUST degrade gracefully when that
 * plugin is absent or its flag is off. Everything here is class_exists-guarded.
 *
 * Two layers of localization apply to a generated course:
 *
 *   1. PROMPT-LEVEL — when the target language is one the course-generation
 *      prompt covers natively (currently en + hi via prompt_builder's v1 /
 *      v2-hindi), Claude produces output directly in that language. No
 *      translate round-trip is needed. {@see needs_translation()} returns
 *      false for these.
 *
 *   2. TRANSLATE-LEVEL — for any OTHER target language (the path toward
 *      Invince's 150+), the module is generated in English and then each
 *      text field is translated through local_sentientia_translate. This
 *      build wires the routing + degradation contract; the translate plugin's
 *      own live/mock flag governs whether the translation is real or mock.
 *
 * The translate plugin is itself mock-by-default, so routing through it never
 * incurs live spend in this build.
 *
 * @package local_sentientia_authoring
 */
class localizer {

    /** Languages the generation prompt covers natively (no translate needed). */
    public const NATIVE_LANGS = ['en', 'hi'];

    /**
     * Normalise a locale to its two-letter code.
     *
     * @param string $lang
     * @return string
     */
    public static function normalise_lang(string $lang): string {
        return strtolower(substr(trim($lang), 0, 2)) ?: 'en';
    }

    /**
     * Is the translate plugin available AND its master flag enabled?
     *
     * @return bool
     */
    public static function translate_available(): bool {
        if (!class_exists('\\local_sentientia_translate\\translate_engine')) {
            return false;
        }
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        return \local_sentientia_platform\feature_flags::is_enabled('sentientia.translate.enabled');
    }

    /**
     * Does generating in $targetlang require a translate round-trip?
     *
     * Returns false for languages the prompt covers natively (en, hi), true
     * otherwise. Always false when the translate plugin is unavailable — in
     * that case the studio degrades to native-language output (English),
     * which is the safe, no-dependency default.
     *
     * @param string $targetlang
     * @return bool
     */
    public static function needs_translation(string $targetlang): bool {
        $code = self::normalise_lang($targetlang);
        if (in_array($code, self::NATIVE_LANGS, true)) {
            return false;
        }
        return self::translate_available();
    }

    /**
     * Translate a single text field into $targetlang via the translate plugin.
     *
     * Degrades to returning the source text unchanged when:
     *   - the translate plugin is unavailable / flag off, OR
     *   - the target language is a native prompt language (no-op), OR
     *   - the translation call fails for any reason.
     *
     * This keeps the studio functional with or without the translate plugin —
     * it never hard-fails on a missing optional dependency.
     *
     * @param string $text       Source text (typically English).
     * @param string $targetlang Target language code.
     * @param int    $customerid Customer scope for the translate call.
     * @return string Translated text, or the source text on any degradation.
     */
    public static function localize_text(string $text, string $targetlang, int $customerid = 1): string {
        if (trim($text) === '') {
            return $text;
        }
        if (!self::needs_translation($targetlang)) {
            return $text;
        }
        // Defensive: only call into a method we know exists.
        if (!method_exists('\\local_sentientia_translate\\translate_engine', 'translate_text')) {
            return $text;
        }
        try {
            $result = \local_sentientia_translate\translate_engine::translate_text(
                $text, self::normalise_lang($targetlang), $customerid);
            if (is_string($result) && trim($result) !== '') {
                return $result;
            }
        } catch (\Throwable $e) {
            debugging('local_sentientia_authoring: translate degradation — ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
        return $text;
    }

    /**
     * Human-readable label for how a draft's localization will be handled.
     * Returns a language-string KEY the UI resolves via get_string().
     *
     * @param string $targetlang
     * @return string One of: 'localize_native' | 'localize_translate' | 'localize_degraded'
     */
    public static function strategy_key(string $targetlang): string {
        $code = self::normalise_lang($targetlang);
        if (in_array($code, self::NATIVE_LANGS, true)) {
            return 'localize_native';
        }
        return self::translate_available() ? 'localize_translate' : 'localize_degraded';
    }
}

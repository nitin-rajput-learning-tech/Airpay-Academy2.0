<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * How a BizLMS evaluation item and its stored answers become Sentientia questions and answers
 * (ADR-032, mapping doc section 18, "Column maps").
 *
 * Pure: no database, no Moodle state. Every step of the evaluation importer goes through this class, so the
 * question step, the template payload, the response step and the value step cannot disagree about what a
 * given item or value means.
 *
 * The BizLMS storage formats it reads (local/evaluation/item/*):
 *
 *  - multichoice: presentation "r>>>>>A|B|C<<<<<1" (r radio, d dropdown, c checkboxes; the part after <<<<< is a
 *    layout flag and a dropdown has none). An answer is the 1-BASED POSITION of the option; checkboxes store
 *    positions joined by "|" ("1|3"); no selection is "" (or 0).
 *  - multichoicerated: the same, with each option written "weight####text". The weights are not imported.
 *  - numeric: presentation "from|to" (either may be empty); an answer is a float written as text.
 *  - textfield, textarea: an answer is the text after s() (so "'" is stored as &#039;).
 *  - info, label, pagebreak, captcha: layout, not questions.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class answer_mapper {

    /** BizLMS item types that ask something. */
    public const QUESTION_TYPES = ['textfield', 'textarea', 'multichoice', 'multichoicerated', 'numeric'];

    /** BizLMS item types that only lay a form out. */
    public const NON_QUESTION_TYPES = ['info', 'label', 'pagebreak', 'captcha'];

    /**
     * depends_on_value of a question whose BizLMS dependvalue was empty. In BizLMS an empty dependvalue matched
     * nothing, so the dependent item never showed; in Sentientia a NULL value means "show on any answer". This
     * value is one no answer equals, which keeps the question hidden exactly as BizLMS did.
     */
    public const NEVER_MATCHES = '__bizlms_never__';

    /** BizLMS separators (evaluation_item_multichoice and _multichoicerated). */
    private const TYPE_SEP = '>>>>>';
    private const ADJUST_SEP = '<<<<<';
    private const LINE_SEP = '|';
    private const VALUE_SEP = '####';

    /**
     * Is this a BizLMS item type the importer knows at all?
     *
     * @param string $typ
     * @return bool
     */
    public static function is_known_type(string $typ): bool {
        return in_array($typ, self::QUESTION_TYPES, true) || in_array($typ, self::NON_QUESTION_TYPES, true);
    }

    /**
     * Does this BizLMS item type only lay a form out?
     *
     * @param string $typ
     * @return bool
     */
    public static function is_non_question_type(string $typ): bool {
        return in_array($typ, self::NON_QUESTION_TYPES, true);
    }

    /**
     * Valid UTF-8, no NUL bytes. Legacy text can carry either, and the writer refuses both.
     *
     * @param string|null $value
     * @return string
     */
    public static function utf8(?string $value): string {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (function_exists('fix_utf8')) {
            return (string) fix_utf8($value);
        }
        return str_replace("\0", '', (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8'));
    }

    /**
     * Plain text of a piece of BizLMS markup: block-level tags become a space (so two paragraphs do not run
     * together), the tags are stripped, entities decoded once, runs of white space collapsed, the ends trimmed.
     *
     * The mapping doc says trim(html_entity_decode(strip_tags(x))). The only difference is the space for a
     * block-level tag. It is used for question text, option texts and dependvalue alike, so a dependvalue still
     * equals the option it named.
     *
     * @param string|null $value
     * @return string
     */
    public static function normalise_text(?string $value): string {
        $value = self::utf8($value);
        if ($value === '') {
            return '';
        }
        $value = (string) preg_replace('~</?\s*(br|p|div|li|ul|ol|tr|td|th|h[1-6])\b[^>]*>~i', ' ', $value);
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $value);
        return trim($collapsed ?? $value);
    }

    /**
     * A stored free-text answer back to the text the respondent typed. BizLMS ran it through s() before storing
     * it, so one round of entity decoding undoes that and no more (a typed "&amp;" was stored "&amp;amp;").
     *
     * @param string|null $value
     * @return string Trimmed; empty means no answer.
     */
    public static function text_answer(?string $value): string {
        $value = self::utf8($value);
        if ($value === '') {
            return '';
        }
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * What one BizLMS item is in Sentientia.
     *
     * @param \stdClass $item A local_evaluation_item row (typ, presentation).
     * @return item_shape|null Null for an item that is not a question (see is_non_question_type()) and for a type
     *         this importer does not know (the caller tells the two apart with is_known_type()).
     */
    public static function describe(\stdClass $item): ?item_shape {
        $typ = trim((string) ($item->typ ?? ''));
        $presentation = self::utf8((string) ($item->presentation ?? ''));
        switch ($typ) {
            case 'textfield':
            case 'textarea':
                return new item_shape('text');
            case 'numeric':
                $parts = explode(self::LINE_SEP, $presentation);
                return new item_shape('numeric', [], self::bound($parts[0] ?? ''), self::bound($parts[1] ?? ''));
            case 'multichoice':
            case 'multichoicerated':
                [$subtype, $choices] = self::choices($presentation, $typ === 'multichoicerated');
                $multi = $typ === 'multichoice' && $subtype === 'c';
                return new item_shape($multi ? 'multichoice_multi' : 'multichoice', $choices);
        }
        return null;
    }

    /**
     * Turn one stored BizLMS answer into the value Sentientia keeps in response_data.
     *
     * @param item_shape $shape
     * @param string|null $raw The stored value.
     * @return array{0: bool, 1: mixed} [ok, value]. ok false: the value cannot be an answer to this item (an
     *         option position that does not exist, text where a number belongs). ok true with a null value: the
     *         respondent gave no answer. The value is a string (text, single choice), a list of strings (several
     *         choices), an int or a float (number), or null.
     */
    public static function map_value(item_shape $shape, ?string $raw): array {
        $raw = (string) $raw;
        switch ($shape->type) {
            case 'text':
                $text = self::text_answer($raw);
                return [true, $text === '' ? null : $text];

            case 'numeric':
                $text = trim($raw);
                if ($text === '') {
                    return [true, null];
                }
                if (!is_numeric($text)) {
                    return [false, null];
                }
                $number = (float) $text;
                if (!is_finite($number)) {
                    return [false, null];
                }
                return [true, (floor($number) === $number && abs($number) < 1.0e15) ? (int) $number : $number];

            case 'multichoice':
                $text = trim($raw);
                if ($text === '' || $text === '0') {
                    return [true, null];
                }
                $option = self::option_at($shape, $text);
                return $option === null ? [false, null] : [true, $option];

            case 'multichoice_multi':
                $text = trim($raw);
                if ($text === '') {
                    return [true, null];
                }
                $picked = [];
                foreach (explode(self::LINE_SEP, $text) as $token) {
                    $token = trim($token);
                    if ($token === '' || $token === '0') {
                        continue;
                    }
                    $option = self::option_at($shape, $token);
                    if ($option === null) {
                        return [false, null];
                    }
                    if (!in_array($option, $picked, true)) {
                        $picked[] = $option;
                    }
                }
                return [true, $picked ?: null];
        }
        return [false, null];
    }

    /**
     * The option a stored position names.
     *
     * @param item_shape $shape
     * @param string $position A decimal position, 1-based.
     * @return string|null Null when it is not a position of this item, or the option has no text.
     */
    private static function option_at(item_shape $shape, string $position): ?string {
        if (!ctype_digit($position)) {
            return null;
        }
        $index = (int) $position - 1;
        if ($index < 0 || !isset($shape->choices[$index]) || $shape->choices[$index] === '') {
            return null;
        }
        return $shape->choices[$index];
    }

    /**
     * A numeric bound from the presentation: a number, or null when empty or not numeric.
     *
     * @param string $text
     * @return float|null
     */
    private static function bound(string $text): ?float {
        $text = trim($text);
        return ($text !== '' && is_numeric($text)) ? (float) $text : null;
    }

    /**
     * The sub-type and the normalised option texts of a choice item, by BizLMS position.
     *
     * @param string $presentation
     * @param bool $rated True for multichoicerated, whose options are written "weight####text".
     * @return array{0: string, 1: string[]} [sub-type r|d|c, option texts in position order]
     */
    private static function choices(string $presentation, bool $rated): array {
        $subtype = 'r';
        $rest = $presentation;
        $separator = strpos($presentation, self::TYPE_SEP);
        if ($separator !== false) {
            $subtype = trim(substr($presentation, 0, $separator));
            $rest = substr($presentation, $separator + strlen(self::TYPE_SEP));
        }
        if ($subtype !== 'd') {
            // What follows <<<<< is the layout flag, not an option.
            $adjust = strpos($rest, self::ADJUST_SEP);
            if ($adjust !== false) {
                $rest = substr($rest, 0, $adjust);
            }
        }
        $choices = [];
        foreach (explode(self::LINE_SEP, $rest) as $line) {
            if ($rated) {
                $parts = explode(self::VALUE_SEP, $line);
                $line = count($parts) > 1 ? $parts[1] : $parts[0];
            }
            $choices[] = self::normalise_text($line);
        }
        return [$subtype, $choices];
    }
}

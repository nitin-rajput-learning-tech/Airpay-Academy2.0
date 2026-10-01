<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * What one BizLMS evaluation item becomes in Sentientia (ADR-032, mapping doc section 18).
 *
 * A value object built by answer_mapper::describe(). It carries the Sentientia question type and the
 * normalised option texts BY THEIR BIZLMS POSITION, because a BizLMS answer to a choice item is the
 * 1-based position of the option in its presentation string, not the option text.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_shape {

    /**
     * @param string $type Sentientia question type: multichoice, multichoice_multi, numeric or text.
     * @param string[] $choices Normalised option texts, one per BizLMS position (index 0 is position 1).
     *        A position whose text is empty after normalisation keeps its slot, so positions still line up.
     * @param float|null $min Numeric lower bound, null when the item has none.
     * @param float|null $max Numeric upper bound, null when the item has none.
     */
    public function __construct(
        public readonly string $type,
        public readonly array $choices = [],
        public readonly ?float $min = null,
        public readonly ?float $max = null,
    ) {
    }

    /**
     * The option texts Sentientia stores: the non-empty positions, in order.
     *
     * @return string[]
     */
    public function options(): array {
        return array_values(array_filter($this->choices, static fn(string $c): bool => $c !== ''));
    }

    /**
     * Does a numeric bound have a fractional part, which Sentientia's integer bounds cannot hold?
     *
     * @return bool
     */
    public function bounds_truncated(): bool {
        foreach ([$this->min, $this->max] as $bound) {
            if ($bound !== null && floor($bound) !== $bound) {
                return true;
            }
        }
        return false;
    }

    /**
     * The value of questions.options: a JSON list of option texts for a choice question, {"min":..,"max":..} for a
     * number (integers, truncated toward zero, as evaluation_manager::build_question_options_json() stores them),
     * null for free text.
     *
     * @return string|null
     */
    public function options_json(): ?string {
        switch ($this->type) {
            case 'multichoice':
            case 'multichoice_multi':
                return json_encode($this->options(), JSON_INVALID_UTF8_SUBSTITUTE);
            case 'numeric':
                return json_encode([
                    'min' => $this->min === null ? null : (int) $this->min,
                    'max' => $this->max === null ? null : (int) $this->max,
                ]);
            default:
                return null;
        }
    }

    /**
     * The same options as the template payload carries them (evaluation_manager::export_template() writes
     * decode_options() of the stored JSON): a list of texts, or {min, max}, or an empty list for free text.
     *
     * @return array
     */
    public function options_array(): array {
        $json = $this->options_json();
        if ($json === null) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

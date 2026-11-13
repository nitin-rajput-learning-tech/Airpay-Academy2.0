<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring\scd;

defined('MOODLE_INTERNAL') || die();

/**
 * What validator::validate() learned about one course document.
 *
 * The PHP twin of the reference harness's ValidationResult
 * (pilot/lib/schema_lite.py): a list of issues, each with a level (error,
 * warning, info), a rule ('schema', 'R01'..'R12', 'slides', 'source') and the
 * JSON Pointer it is about, plus a per-rule status.
 *
 * Rule status values: 'pass', 'warn', 'fail' as in the harness, and
 * 'not_run' when the rules never ran (the document root was not an object).
 * A 'pass' on a partially covered rule (R05, R08) means the part this slice
 * checks passed; validator::coverage() says which part that is.
 *
 * @package local_sentientia_authoring
 */
class validation_result {

    /** @var array[] Issues in the order found: [level, rule, pointer, message]. */
    public $issues = [];

    /** @var string[] JSON Pointers of every i18n object the schema walk met. */
    public $i18npointers = [];

    /** @var array<string,int> Units still to translate, per declared language (R01). */
    public $pending = [];

    /** @var array<string,string> Rule id => pass | warn | fail. */
    public $rulestatus = [];

    /**
     * Record one finding.
     *
     * @param string $level   error | warning | info
     * @param string $rule    schema | R01..R12 | slides | source
     * @param string $pointer JSON Pointer ('' is reported as '/')
     * @param string $message
     * @return void
     */
    public function add(string $level, string $rule, string $pointer, string $message): void {
        $this->issues[] = [
            'level'   => $level,
            'rule'    => $rule,
            'pointer' => $pointer === '' ? '/' : $pointer,
            'message' => $message,
        ];
    }

    /**
     * @return array[] Error-level issues.
     */
    public function errors(): array {
        return $this->of_level('error');
    }

    /**
     * @return array[] Warning-level issues.
     */
    public function warnings(): array {
        return $this->of_level('warning');
    }

    /**
     * True when no error-level issue was found.
     *
     * @return bool
     */
    public function ok(): bool {
        return empty($this->errors());
    }

    /**
     * Issues raised under one rule.
     *
     * @param string $rule
     * @return array[]
     */
    public function by_rule(string $rule): array {
        return array_values(array_filter($this->issues, static function (array $i) use ($rule): bool {
            return $i['rule'] === $rule;
        }));
    }

    /**
     * Status of one rule ('not_run' when the rules never ran).
     *
     * @param string $rule
     * @return string
     */
    public function status(string $rule): string {
        return $this->rulestatus[$rule] ?? 'not_run';
    }

    /**
     * Short, plain-text summary of the first errors, for an exception message.
     * Pointers and rule ids only come from the validator; document text can
     * appear inside a message, so callers escape it like any other text.
     *
     * @param int $max
     * @return string
     */
    public function summary(int $max = 5): string {
        $errors = $this->errors();
        $lines = [];
        foreach (array_slice($errors, 0, $max) as $e) {
            $lines[] = "[{$e['rule']}] {$e['pointer']}: {$e['message']}";
        }
        if (count($errors) > $max) {
            $lines[] = '... ' . (count($errors) - $max) . ' more';
        }
        return implode('; ', $lines);
    }

    /**
     * Plain array form (same keys as the harness's as_dict()).
     *
     * @return array
     */
    public function as_array(): array {
        return [
            'ok'                     => $this->ok(),
            'error_count'            => count($this->errors()),
            'warning_count'          => count($this->warnings()),
            'rule_status'            => $this->rulestatus,
            'pending_units_per_lang' => $this->pending,
            'issues'                 => $this->issues,
        ];
    }

    /**
     * @param string $level
     * @return array[]
     */
    private function of_level(string $level): array {
        return array_values(array_filter($this->issues, static function (array $i) use ($level): bool {
            return $i['level'] === $level;
        }));
    }
}

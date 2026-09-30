<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_recompletion\bizlms\mapper;

/**
 * What an imported rule's legacy_config says, in the order the rules page shows it.
 *
 * The BizLMS plugin kept one name/value row per setting per course. A Sentientia rule cannot express most of
 * them (the email templates, the extra quiz attempt, the archive switches, the assignment, LTI and questionnaire
 * choices), so the import keeps them all as one JSON object on the rule (rules.legacy_config) and this class
 * turns it into lines a person can read. It is pure: a JSON string in, plain lines out.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_summary {

    /** A legacy on/off setting. */
    public const SWITCH = 'switch';

    /** A legacy nothing / delete / extra-attempt setting (0, 1, 2). */
    public const CHOICE = 'choice';

    /** A duration. */
    public const DAYS = 'days';

    /** @var array<string, string> Setting name => kind, in display order. */
    private const SETTINGS = [
        'enable' => self::SWITCH,
        'recompletionduration' => self::DAYS,
        'deletegradedata' => self::SWITCH,
        'archivecompletiondata' => self::SWITCH,
        'quiz' => self::CHOICE,
        'archivequiz' => self::SWITCH,
        'scorm' => self::CHOICE,
        'archivescorm' => self::SWITCH,
        'assign' => self::CHOICE,
        'lti' => self::SWITCH,
        'archivelti' => self::SWITCH,
        'questionnaire' => self::CHOICE,
        'archivequestionnaire' => self::SWITCH,
        'pulse' => self::CHOICE,
        'recompletionemailenable' => self::SWITCH,
    ];

    /**
     * The legacy settings of a rule, as plain lines.
     *
     * @param string|null $json rules.legacy_config; null for a rule made in Sentientia.
     * @return array<array{name: string, kind: string, value: string}> A setting the course did not have is
     *         left out. value is '1'/'0' for a switch, '0'/'1'/'2' for a choice, whole days for a duration.
     */
    public static function lines(?string $json): array {
        $config = self::parse($json);
        if ($config === null) {
            return [];
        }
        $lines = [];
        foreach (self::SETTINGS as $name => $kind) {
            if (!array_key_exists($name, $config)) {
                continue;
            }
            $value = trim((string) $config[$name]);
            if ($kind === self::DAYS) {
                $days = mapper::period_days($value);
                if ($days === null) {
                    continue;
                }
                $value = (string) $days;
            } else if ($kind === self::SWITCH) {
                $value = (string) mapper::switch_on($value);
            } else {
                $value = in_array($value, ['0', '1', '2'], true) ? $value : '0';
            }
            $lines[] = ['name' => $name, 'kind' => $kind, 'value' => $value];
        }
        return $lines;
    }

    /**
     * Was the course's SCORM choice one that could not work under the BizLMS plugin? Its installer wrote the
     * setting as "deletescormdata" while the SCORM handler read "scorm", so a course that only has the old name
     * had "do nothing" for SCORM.
     *
     * @param string|null $json
     * @return bool
     */
    public static function has_dead_scorm_setting(?string $json): bool {
        $config = self::parse($json);
        return $config !== null && array_key_exists('deletescormdata', $config);
    }

    /**
     * The settings as a name => value map.
     *
     * @param string|null $json
     * @return array<string, mixed>|null Null when there is no legacy config or it is not a JSON object.
     */
    public static function parse(?string $json): ?array {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $config = json_decode($json, true);
        return is_array($config) ? $config : null;
    }
}

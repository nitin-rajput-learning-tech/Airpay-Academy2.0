<?php
/**
 * Notification Rule CRUD + query methods.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

class rule_manager {

    const TABLE = 'local_sentientia_email_rules';

    /**
     * Rule types the rule editor offers, in display order (D-12, 2026-11-20). Each has a rule_type_<type> string.
     *
     * The list used to be hard-coded in tab_rules.mustache and lacked the Sprint B types (course_completed,
     * course_incomplete) and the COMMS-N7 parity types (course_enrolled, learning_path_enrolled,
     * manager_course_completed), so editing such a rule forced it onto another type.
     */
    public const RULE_TYPES = [
        'compliance_enrolled',
        'compliance_reminder',
        'compliance_overdue',
        'deadline_approaching',
        'course_not_started',
        'course_incomplete',
        'course_completed',
        'course_enrolled',
        'learning_path_enrolled',
        'manager_course_completed',
        'streak_broken',
        'manager_nudge',
        'weekly_escalation',
        'new_course',
        'custom',
    ];

    /** Channel values the rule editor offers (notification_sender::send() splits them on ","). */
    public const CHANNELS = ['email,popup', 'popup', 'email'];

    /** Audiences the rule editor offers. */
    public const AUDIENCES = ['learner', 'manager', 'admin', 'all'];

    /** Most entries a reminder cadence may have (the admin setting and the rule editor share it). */
    public const CADENCE_MAX_ENTRIES = 10;

    /**
     * The trigger event a new rule of an event-driven type is saved with; every other type is evaluated by cron.
     * The same events the seeded rules (db/upgrade.php) and parity_senders::DEFAULTS carry.
     */
    private const TYPE_EVENTS = [
        'course_completed'         => '\\core\\event\\course_completed',
        'manager_course_completed' => '\\core\\event\\course_completed',
        'course_enrolled'          => '\\core\\event\\user_enrolment_created',
        'new_course'               => '\\core\\event\\course_created',
    ];

    /** Columns whose NULL means something else than 0 or '' (process_rules reads a NULL trigger_days as its default). */
    private const NULL_SIGNIFICANT = ['trigger_days'];

    /**
     * Get all rules, optionally filtered by tenant.
     *
     * @param int $tenantid 0 = all tenants (cross-tenant callers only)
     * @return array of rule objects
     */
    public static function get_rules(int $tenantid = 0): array {
        global $DB;
        // ADR-031: "all tenants" is for cross-tenant callers only. A scoped
        // caller reaching here with 0 has no resolvable tenant: nothing.
        if ($tenantid <= 0 && !\local_sentientia_platform\tenant::is_cross_tenant()) {
            return [];
        }
        $conditions = [];
        $params = [];
        if ($tenantid > 0) {
            $conditions[] = "(tenant_id = :tid OR tenant_id = 0)";
            $params['tid'] = $tenantid;
        }
        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';
        return $DB->get_records_select(self::TABLE, $where, $params, 'priority DESC, rule_name ASC');
    }

    /**
     * Get a single rule by ID.
     */
    public static function get_rule(int $id): ?object {
        global $DB;
        $rule = $DB->get_record(self::TABLE, ['id' => $id]);
        return $rule ?: null;
    }

    /**
     * The template key a rule stores for the one a request names (G1, 2026-11-19).
     *
     * A rule's template_key is rendered as local_sentientia_emails/{key} for every recipient
     * (notification_sender::send()). The rule editor took it as PARAM_RAW and stored it unchecked,
     * so a crafted form could point a rule at any template name: this plugin's own manage/* page
     * templates, a partial, a ../ path. The preview, the template editor and the template web
     * services were closed to the same gap this morning (email_renderer::known_template_key(),
     * version 2026111900); the rule editor goes through the same registry check now.
     *
     * @param string|null $requested the key as the request gave it ('' or null = no template)
     * @return string|null '' for no template (plain text), the registry key (the legacy spelling
     *                     with "_" for "/" comes back as the registry key), or null when the
     *                     request names something that is not a registry template
     */
    public static function rule_template_key(?string $requested): ?string {
        $requested = trim((string) $requested);
        if ($requested === '') {
            return '';
        }
        $key = email_renderer::known_template_key($requested);
        return $key === '' ? null : $key;
    }

    /**
     * The values the rule editor accepts for a choice field (D-12, 2026-11-20).
     *
     * The offered list, plus the stored value of the rule being edited when that value is not in the list (a rule
     * written by an upgrade step or an older editor), so re-saving such a rule keeps it instead of failing.
     *
     * @param string $field 'rule_type', 'channel' or 'audience'
     * @param \stdClass|null $stored the rule being edited; null for a new rule
     * @return string[]
     */
    public static function choices(string $field, ?\stdClass $stored = null): array {
        switch ($field) {
            case 'rule_type':
                $values = self::RULE_TYPES;
                break;
            case 'channel':
                $values = self::CHANNELS;
                break;
            case 'audience':
                $values = self::AUDIENCES;
                break;
            default:
                throw new \coding_exception("No choices for rule field '{$field}'.");
        }
        $current = $stored !== null ? trim((string) ($stored->$field ?? '')) : '';
        if ($current !== '' && !in_array($current, $values, true)) {
            $values[] = $current;
        }
        return $values;
    }

    /**
     * The trigger event a rule of this type is saved with when the editor creates it or changes its type.
     *
     * @param string $ruletype
     * @return string an event class name, or 'cron'
     */
    public static function default_trigger_event(string $ruletype): string {
        return self::TYPE_EVENTS[$ruletype] ?? 'cron';
    }

    /**
     * Why a reminder cadence is not valid, or null when it is (empty = use the site default).
     *
     * The rules of local_sentientia_emails\admin\setting_cadence_json, which calls this: a JSON array of 1 to
     * CADENCE_MAX_ENTRIES positive integers.
     *
     * @param string $data
     * @return string|null the error message (plain text; it can quote the input), or null
     */
    public static function cadence_error(string $data): ?string {
        if (trim($data) === '') {
            return null;
        }
        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return get_string('cadence_error_not_array', 'local_sentientia_emails');
        }
        if (empty($decoded)) {
            return get_string('cadence_error_empty', 'local_sentientia_emails');
        }
        if (count($decoded) > self::CADENCE_MAX_ENTRIES) {
            return get_string('cadence_error_too_long', 'local_sentientia_emails', self::CADENCE_MAX_ENTRIES);
        }
        foreach ($decoded as $v) {
            // Strict int check: JSON's "1" (string-quoted) is the admin typing the wrong format.
            if (!is_int($v) || $v <= 0) {
                return get_string('cadence_error_bad_value', 'local_sentientia_emails', var_export($v, true));
            }
        }
        return null;
    }

    /**
     * The rule record to save for a rule-editor submission (D-12, 2026-11-20).
     *
     * Editing a saved rule used to reset what the form did not show or pre-select: the channel became
     * "Email + Popup", the audience "Learner", the trigger event 'cron', the conditions were cleared, a blank
     * Trigger Days became 0, and the Enabled box could never switch a rule off (an unticked box posts nothing,
     * and the save defaulted it to 1). Now:
     *   - a field the request does not carry (null or missing) keeps the stored value on an edit, and gets the
     *     column default on a create;
     *   - trigger_event and conditions_json are never read from the request: an edit keeps them (the trigger
     *     event follows the rule type only when the type is changed), a create takes the type's event and '';
     *   - rule_type, channel and audience must be one of choices(); the template must be "None" or a registry key;
     *   - a blank Trigger Days is NULL, a blank cadence is NULL (the site default);
     *   - on an edit the record carries the id and only the columns whose value changed, so everything the
     *     admin did not edit is not written at all.
     *
     * @param array $form the submitted values: rule_name, rule_type, trigger_days, channel, audience, template_key,
     *                    tenant_id, enabled, priority, cadence_days_json, max_reminders_per_user,
     *                    auto_stop_on_completion (null or missing = not submitted). Other keys are ignored.
     * @param \stdClass|null $stored the rule being edited; null for a new rule
     * @return \stdClass for save_rule()
     * @throws \moodle_exception rule_field_invalid, rule_cadence_invalid or template_unknown; nothing is saved
     */
    public static function rule_from_form(array $form, ?\stdClass $stored = null): \stdClass {
        $isedit = $stored !== null;
        $get = static function(string $key) use ($form): ?string {
            return (array_key_exists($key, $form) && $form[$key] !== null) ? trim((string) $form[$key]) : null;
        };
        $old = static function(string $field) use ($stored) {
            return $stored !== null && isset($stored->$field) ? $stored->$field : null;
        };
        $new = [];

        // Rule name: required on a create, never blank.
        $name = $get('rule_name');
        if ($name !== null) {
            if ($name === '' || \core_text::strlen($name) > 255) {
                throw self::invalid('rule_field_name');
            }
            $new['rule_name'] = $name;
        } else if (!$isedit) {
            throw self::invalid('rule_field_name');
        }

        // Rule type, channel, audience: required on a create; exact match against the offered values.
        foreach (['rule_type' => 'rule_field_type', 'channel' => 'rule_field_channel',
                'audience' => 'rule_field_audience'] as $field => $label) {
            $value = $get($field);
            if ($value === null) {
                if (!$isedit) {
                    throw self::invalid($label);
                }
                continue;
            }
            if (!in_array($value, self::choices($field, $stored), true)) {
                throw self::invalid($label);
            }
            $new[$field] = $value;
        }

        // Trigger days: blank = NULL ("event" in the list; process_rules applies its default).
        $days = $get('trigger_days');
        if ($days !== null) {
            $new['trigger_days'] = self::int_field($days, -365, 365, 'rule_field_trigger_days', $old('trigger_days'), true);
        } else if (!$isedit) {
            $new['trigger_days'] = null;
        }

        // Priority: 1-100, never blank.
        $priority = $get('priority');
        if ($priority !== null) {
            $new['priority'] = self::int_field($priority, 1, 100, 'rule_field_priority', $old('priority'), false);
        } else if (!$isedit) {
            $new['priority'] = 50;
        }

        // Template: "None" or a registry key (G1).
        $template = $get('template_key');
        if ($template !== null) {
            $key = self::rule_template_key($template);
            if ($key === null) {
                throw new \moodle_exception('template_unknown', 'local_sentientia_emails');
            }
            $new['template_key'] = $key;
        } else if (!$isedit) {
            $new['template_key'] = '';
        }

        // Tenant scope: a tenant root id or 0 (global). Who may write which scope is tenant_scope's call.
        $tenant = $get('tenant_id');
        if ($tenant !== null) {
            if (!preg_match('/^\d{1,10}$/', $tenant)) {
                throw self::invalid('rule_field_scope');
            }
            $new['tenant_id'] = (int) $tenant;
        } else if (!$isedit) {
            $new['tenant_id'] = 0;
        }

        // Check boxes: the form posts a hidden 0 before each box, so an unticked box arrives as 0.
        foreach (['enabled' => 1, 'auto_stop_on_completion' => 1] as $field => $default) {
            $value = $get($field);
            if ($value !== null) {
                $new[$field] = ((int) $value) !== 0 ? 1 : 0;
            } else if (!$isedit) {
                $new[$field] = $default;
            }
        }

        // Reminder cadence: blank = NULL (the site default); an unchanged value is kept as stored.
        $cadence = $get('cadence_days_json');
        if ($cadence !== null) {
            if ($cadence === '') {
                $new['cadence_days_json'] = null;
            } else if ($cadence === trim((string) $old('cadence_days_json'))) {
                $new['cadence_days_json'] = $old('cadence_days_json');
            } else {
                $error = self::cadence_error($cadence);
                if ($error !== null) {
                    throw new \moodle_exception('rule_cadence_invalid', 'local_sentientia_emails', '', $error);
                }
                $new['cadence_days_json'] = json_encode(array_values(json_decode($cadence, true)));
            }
        } else if (!$isedit) {
            $new['cadence_days_json'] = null;
        }

        // Most reminders per learner: 0 = no limit; blank = 0.
        $max = $get('max_reminders_per_user');
        if ($max !== null) {
            $new['max_reminders_per_user'] = $max === '' ? 0
                : self::int_field($max, 0, 999, 'rule_field_max_reminders', $old('max_reminders_per_user'), false);
        } else if (!$isedit) {
            $new['max_reminders_per_user'] = 0;
        }

        // Not on the form: the trigger event follows the rule type, conditions are kept.
        if (!$isedit) {
            $new['trigger_event'] = self::default_trigger_event($new['rule_type']);
            $new['conditions_json'] = '';
            return (object) $new;
        }
        if (isset($new['rule_type']) && $new['rule_type'] !== (string) $stored->rule_type) {
            $new['trigger_event'] = self::default_trigger_event($new['rule_type']);
        }

        // An edit writes only what changed.
        foreach ($new as $field => $value) {
            $was = $old($field);
            $same = in_array($field, self::NULL_SIGNIFICANT, true)
                ? (($value === null) === ($was === null) && (string) $value === (string) $was)
                : ((string) $value === (string) $was);
            if ($same) {
                unset($new[$field]);
            }
        }
        $new['id'] = (int) $stored->id;
        return (object) $new;
    }

    /**
     * An integer form field, or NULL for a blank one when $blankisnull.
     *
     * @param string $raw trimmed submitted value
     * @param int $min
     * @param int $max
     * @param string $label lang string id of the field's label, for the error
     * @param mixed $stored the stored value; submitted unchanged it is kept even outside min..max
     * @param bool $blankisnull
     * @return int|null
     * @throws \moodle_exception rule_field_invalid
     */
    private static function int_field(string $raw, int $min, int $max, string $label, $stored, bool $blankisnull): ?int {
        if ($raw === '') {
            if ($blankisnull) {
                return null;
            }
            throw self::invalid($label);
        }
        if ($stored !== null && $raw === (string) $stored) {
            return (int) $stored;
        }
        if (!preg_match('/^[+-]?\d{1,6}$/', $raw)) {
            throw self::invalid($label);
        }
        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            throw self::invalid($label);
        }
        return $value;
    }

    /**
     * The refusal for a field the rule editor does not accept.
     *
     * @param string $label lang string id of the field's label
     * @return \moodle_exception
     */
    private static function invalid(string $label): \moodle_exception {
        return new \moodle_exception('rule_field_invalid', 'local_sentientia_emails', '',
            get_string($label, 'local_sentientia_emails'));
    }

    /**
     * Save or update a rule.
     *
     * G1 (2026-11-19): a template_key that is set must be '' (no template) or a key from the
     * template registry; it is stored as the registry key. Anything else is refused before the
     * database is touched. A rule saved without a template_key keeps the one it has.
     *
     * @param object $data rule data
     * @return int rule ID
     * @throws \moodle_exception template_unknown when template_key names no registry template
     */
    public static function save_rule(object $data): int {
        global $DB, $USER;

        if (isset($data->template_key)) {
            $templatekey = self::rule_template_key((string) $data->template_key);
            if ($templatekey === null) {
                throw new \moodle_exception('template_unknown', 'local_sentientia_emails');
            }
            $data->template_key = $templatekey;
        }

        $data->usermodified = $USER->id;
        $data->timemodified = time();

        if (!empty($data->id)) {
            $DB->update_record(self::TABLE, $data);
            return $data->id;
        }

        $data->timecreated = time();
        return $DB->insert_record(self::TABLE, $data);
    }

    /**
     * Toggle a rule's enabled status.
     *
     * @param int $id rule ID
     * @param bool $enabled
     */
    public static function toggle_rule(int $id, bool $enabled): void {
        global $DB;
        $DB->set_field(self::TABLE, 'enabled', (int)$enabled, ['id' => $id]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Delete a rule.
     */
    public static function delete_rule(int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Get enabled rules for a specific type (used by cron task).
     *
     * @param string $ruletype
     * @param int $tenantid 0 = all
     * @return array
     */
    public static function get_enabled_rules(string $ruletype, int $tenantid = 0): array {
        global $DB;
        $conditions = ['rule_type = :rtype', 'enabled = 1'];
        $params = ['rtype' => $ruletype];
        if ($tenantid > 0) {
            $conditions[] = "(tenant_id = :tid OR tenant_id = 0)";
            $params['tid'] = $tenantid;
        }
        return $DB->get_records_select(self::TABLE, implode(' AND ', $conditions),
            $params, 'priority DESC');
    }

    /**
     * Get rule statistics for dashboard.
     *
     * ADR-031: counts the rules that apply to $tenantid (its own plus the
     * global ones). 0 = every rule, for cross-tenant callers only; a scoped
     * caller with no tenant gets zeros.
     *
     * @param int $tenantid
     * @return object {total, enabled, disabled, by_type: [{type, count}]}
     */
    public static function get_stats(int $tenantid = 0): object {
        global $DB;
        if ($tenantid > 0) {
            $where = '(tenant_id = :tid OR tenant_id = 0)';
            $params = ['tid' => $tenantid];
        } else if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            $where = '1=1';
            $params = [];
        } else {
            $where = '1=0';
            $params = [];
        }
        $total = $DB->count_records_select(self::TABLE, $where, $params);
        $enabled = $DB->count_records_select(self::TABLE, "$where AND enabled = 1", $params);
        $bytype = $DB->get_records_sql(
            "SELECT rule_type, COUNT(*) AS cnt FROM {" . self::TABLE . "} WHERE $where
           GROUP BY rule_type ORDER BY cnt DESC", $params
        );
        return (object)[
            'total'    => $total,
            'enabled'  => $enabled,
            'disabled' => $total - $enabled,
            'by_type'  => array_values($bytype),
        ];
    }
}

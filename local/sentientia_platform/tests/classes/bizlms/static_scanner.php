<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The static scan of every classes/bizlms/ file (ADR-032, "Side-effect safety" 4).
 *
 * It tokenises the source, so a banned name in a comment, a docblock or a string
 * is never a finding, and it tracks the enclosing function so that reset_sequence
 * is only allowed inside finalise().
 *
 * Banned everywhere in classes/bizlms/ (framework and importers):
 *   message_send, email_to_user, ->trigger(, role_assign(, enrol_user(,
 *   enrol_try_internal_enrol(, completion_completion, mark_complete(,
 *   cohort_add_member(, core_tag_tag::, update_course(, calendar_event::create,
 *   any get_recordset*, and the managers the mapping forbids:
 *   session_manager::, waitlist_manager::, path_manager::, program_manager::,
 *   request_manager::, cart_manager::, invoicer::, notifier::, delivery_log::log,
 *   evaluation_manager::submit_response, recompletion_engine::, skills_manager::,
 *   rating_manager::submit_rating.
 *
 * Banned outside writer.php: every $DB write method and every DDL call, because
 * the writer is the only code that writes.
 *
 * reset_sequence is allowed only inside finalise() (the runner calls it from
 * finalise_feature()) and in writer.php. ->get_records( is banned outside the
 * framework (local_sentientia_platform).
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class static_scanner {

    /** @var string[] Function calls banned everywhere. */
    private const BANNED_CALLS = [
        'message_send', 'email_to_user', 'role_assign', 'enrol_user', 'enrol_try_internal_enrol',
        'mark_complete', 'cohort_add_member', 'update_course',
    ];

    /** @var string[] Classes that may not be instantiated or called statically. */
    private const BANNED_CLASSES = ['completion_completion'];

    /**
     * Static calls banned on a class: class => methods ('*' = any).
     *
     * @var array<string, string[]>
     */
    private const BANNED_STATIC = [
        'core_tag_tag' => ['*'],
        'calendar_event' => ['create'],
        'session_manager' => ['*'], 'waitlist_manager' => ['*'], 'path_manager' => ['*'],
        'program_manager' => ['*'], 'request_manager' => ['*'], 'cart_manager' => ['*'],
        'invoicer' => ['*'], 'notifier' => ['*'],
        'delivery_log' => ['log'],
        'evaluation_manager' => ['submit_response'],
        'recompletion_engine' => ['*'],
        'skills_manager' => ['*'],
        'rating_manager' => ['submit_rating'],
    ];

    /**
     * Functions that may call reset_sequence: an importer's finalise(), and the runner's
     * finalise_feature(), which resets every PRESERVE target through the writer after the
     * last commit. writer.php itself is always allowed.
     *
     * @var string[]
     */
    private const FINALISE_FUNCTIONS = ['finalise', 'finalise_feature'];

    /** @var string[] $DB methods that write. */
    private const DB_WRITES = [
        'insert_record', 'insert_records', 'insert_record_raw', 'import_record', 'update_record',
        'update_record_raw', 'set_field', 'set_field_select', 'delete_records', 'delete_records_select',
        'delete_records_list', 'execute',
    ];

    /** @var string[] database_manager methods that change the schema. */
    private const DDL = [
        'create_table', 'drop_table', 'rename_table', 'add_field', 'drop_field', 'rename_field',
        'change_field_type', 'change_field_precision', 'change_field_notnull', 'change_field_default',
        'add_key', 'drop_key', 'add_index', 'drop_index', 'install_from_xmldb_file',
        'install_one_table_from_xmldb_file', 'install_from_xmldb_structure', 'create_temp_table',
    ];

    /**
     * Scan one file's source.
     *
     * @param string $code PHP source.
     * @param bool $framework True for a file of local_sentientia_platform's classes/bizlms/.
     * @param bool $iswriter True for writer.php, the only file that may write.
     * @return string[] Findings, one line each: "line N: what".
     */
    public static function scan(string $code, bool $framework, bool $iswriter): array {
        $tokens = [];
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)) {
                continue;
            }
            $tokens[] = is_array($token) ? $token : [null, $token, 0];
        }

        $findings = [];
        $stack = [];
        $depth = 0;
        $pending = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            [$id, $text, $line] = $tokens[$i];

            // Track the enclosing function.
            if ($id === T_FUNCTION) {
                $next = $tokens[$i + 1] ?? [null, '', 0];
                if ($next[1] === '&') {
                    $next = $tokens[$i + 2] ?? [null, '', 0];
                }
                $pending = $next[0] === T_STRING ? $next[1] : '{closure}';
            } else if ($text === ';' && $pending !== null) {
                $pending = null;
            } else if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
                if ($pending !== null && $text === '{') {
                    $stack[] = [$pending, $depth];
                    $pending = null;
                }
            } else if ($text === '}') {
                if ($stack && end($stack)[1] === $depth) {
                    array_pop($stack);
                }
                $depth--;
            }
            $function = $stack ? end($stack)[0] : '';

            $name = self::last_segment($id, $text);
            $next = $tokens[$i + 1] ?? [null, '', 0];

            // A banned function called by name.
            if ($name !== null && in_array($name, self::BANNED_CALLS, true) && $next[1] === '(') {
                $findings[] = "line {$line}: {$name}()";
            }
            if ($name !== null && in_array($name, self::BANNED_CLASSES, true)) {
                $findings[] = "line {$line}: {$name}";
            }
            // get_recordset*: an object method or a function.
            if ($id === T_STRING && strncmp($text, 'get_recordset', 13) === 0) {
                $findings[] = "line {$line}: {$text}";
            }
            // Banned static calls.
            if ($name !== null && isset(self::BANNED_STATIC[$name]) && $next[0] === T_DOUBLE_COLON) {
                $method = $tokens[$i + 2][1] ?? '';
                $rules = self::BANNED_STATIC[$name];
                if (in_array('*', $rules, true) || in_array($method, $rules, true)) {
                    $findings[] = "line {$line}: {$name}::{$method}";
                }
            }
            // ->trigger(, ->get_records(, $DB write methods and DDL.
            if ($id === T_OBJECT_OPERATOR) {
                $method = $next[1];
                $after = $tokens[$i + 2][1] ?? '';
                if ($next[0] === T_STRING && $after === '(') {
                    if ($method === 'trigger') {
                        $findings[] = "line {$line}: ->trigger()";
                    }
                    if ($method === 'get_records' && !$framework) {
                        $findings[] = "line {$line}: ->get_records() outside the framework";
                    }
                    if (!$iswriter && in_array($method, self::DB_WRITES, true) && self::receiver_is_db($tokens, $i)) {
                        $findings[] = "line {$line}: \$DB->{$method}() outside writer.php";
                    }
                    if (!$iswriter && in_array($method, self::DDL, true)) {
                        $findings[] = "line {$line}: DDL {$method}() outside writer.php";
                    }
                    if ($method === 'reset_sequence' && !$iswriter
                            && !in_array($function, self::FINALISE_FUNCTIONS, true)) {
                        $findings[] = "line {$line}: reset_sequence() outside finalise()";
                    }
                }
            }
        }
        return $findings;
    }

    /**
     * The last segment of a name token (handles PHP 8 qualified names).
     *
     * @param int|null $id
     * @param string $text
     * @return string|null
     */
    private static function last_segment(?int $id, string $text): ?string {
        if ($id === T_STRING) {
            return $text;
        }
        if ($id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED || $id === T_NAME_RELATIVE) {
            $parts = explode('\\', $text);
            return end($parts);
        }
        return null;
    }

    /**
     * Is the object before ->method the global $DB (or a variable clearly holding it)?
     *
     * @param array $tokens
     * @param int $index Index of the T_OBJECT_OPERATOR token.
     * @return bool
     */
    private static function receiver_is_db(array $tokens, int $index): bool {
        $before = $tokens[$index - 1] ?? [null, '', 0];
        return $before[0] === T_VARIABLE && in_array($before[1], ['$DB', '$db'], true);
    }
}

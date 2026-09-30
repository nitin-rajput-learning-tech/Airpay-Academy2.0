<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\tests\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The static scan of every classes/bizlms/ file (ADR-032, "Side-effect safety" 4).
 *
 * It tokenises the source, so a banned name in a comment, a docblock or a string
 * is never a finding, and it tracks the enclosing function so that reset_sequence,
 * set_config and the cache purges are only allowed inside finalise().
 *
 * Banned everywhere in classes/bizlms/ (framework and importers):
 *   message_send, message_post_message, send_message, send_message_to_conversation, email_to_user,
 *   set_user_preference, unset_user_preference, ->trigger(, role_assign(, role_unassign*(, enrol_user(,
 *   ->unenrol_user(, enrol_try_internal_enrol(, delete_user(, groups_add_member(,
 *   groups_remove_member(, completion_completion, completion_info, ->update_state(,
 *   mark_complete(, cohort_add_member(, core_tag_tag::, update_course(, calendar_event::create,
 *   every grade_* function and class, ::queue_adhoc_task( and ::reschedule_or_queue_adhoc_task(,
 *   feature_flags:: (the import never flips a flag), call_user_func* and forward_static_call*
 *   (they hide a call from this scan), any get_recordset*, and the managers the mapping forbids:
 *   session_manager::, waitlist_manager::, path_manager::, program_manager::,
 *   request_manager::, cart_manager::, invoicer::, notifier::, delivery_log::log,
 *   evaluation_manager::submit_response, recompletion_engine::, skills_manager::,
 *   rating_manager::submit_rating.
 *
 * Banned in importer code (not the framework) because they hide the callee or reach around the context:
 *   a call through a variable ($f(...)), new $class, $class::method(), a banned name as a callable string
 *   ('role_assign', [$DB, 'insert_record'], 'manager::send_message'), and the framework's own runner, writer,
 *   guard, guard_permit, sideeffect_guard, registry and capability_repair classes. An allow-list of the
 *   namespaces importer code may call would be sound where this deny-list is not; until then this is a
 *   tripwire for honest mistakes, and the runtime tripwire is what catches the rest.
 *
 * Banned outside writer.php: every $DB write method and every DDL call, because
 * the writer is the only code that writes. A write method whose name only $DB has
 * (insert_record, update_record, delete_records and the like) is a finding on ANY
 * receiver, so an alias, a property, $GLOBALS['DB'], ?-> and a moodle_database
 * parameter cannot hide it. execute() is a generic name, so it counts on a receiver
 * that is $DB, holds it (alias, ->db, $GLOBALS['DB'], a moodle_database parameter)
 * or is called dynamically.
 *
 * Allowed only inside finalise() and finalise_feature(), and in writer.php:
 * reset_sequence, set_config, unset_config, purge_all_caches, purge_caches,
 * purge_other_caches, rebuild_course_cache and cache_helper::.
 * ->get_records( is banned outside the framework (local_sentientia_platform).
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class static_scanner {

    /** @var string[] Names banned wherever they are called: a function, a method or a static method. */
    private const BANNED_CALLS = [
        'message_send', 'message_post_message', 'send_message', 'send_message_to_conversation', 'email_to_user',
        'set_user_preference', 'unset_user_preference', 'role_assign', 'role_unassign', 'role_unassign_all', 'enrol_user',
        'unenrol_user', 'enrol_try_internal_enrol', 'mark_complete', 'update_state', 'cohort_add_member',
        'update_course', 'delete_user', 'groups_add_member', 'groups_remove_member', 'queue_adhoc_task',
        'reschedule_or_queue_adhoc_task', 'call_user_func', 'call_user_func_array', 'forward_static_call',
        'forward_static_call_array',
    ];

    /** @var string[] Classes that may not be instantiated or called statically. */
    private const BANNED_CLASSES = [
        'completion_completion', 'completion_info', 'grade_item', 'grade_grade', 'grade_category', 'grade_scale',
        'grade_outcome',
    ];

    /**
     * Framework classes importer code has no business with: it reaches the database, the run and the permit only
     * through its context. Matched on a static call, on new and on a use import, in importer code only.
     *
     * @var string[]
     */
    private const FRAMEWORK_ONLY_CLASSES = [
        'runner', 'writer', 'guard', 'guard_permit', 'sideeffect_guard', 'registry', 'capability_repair',
    ];

    /** @var string Prefix of the grade functions (grade_update, grade_regrade_final_grades, ...). */
    private const GRADE_PREFIX = 'grade_';

    /**
     * Static calls banned on a class: class => methods ('*' = any).
     *
     * @var array<string, string[]>
     */
    private const BANNED_STATIC = [
        'core_tag_tag' => ['*'],
        'calendar_event' => ['create'],
        'feature_flags' => ['*'],
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
     * Functions that may hold the finalise-only calls: an importer's finalise(), and the runner's
     * finalise_feature(), which resets every PRESERVE target through the writer after the last
     * commit. writer.php itself is always allowed.
     *
     * @var string[]
     */
    private const FINALISE_FUNCTIONS = ['finalise', 'finalise_feature'];

    /** @var string[] Calls (function or method) allowed only where FINALISE_FUNCTIONS says. */
    private const FINALISE_ONLY_CALLS = [
        'reset_sequence', 'set_config', 'unset_config', 'purge_all_caches', 'purge_caches', 'purge_other_caches',
        'rebuild_course_cache',
    ];

    /** @var string[] Classes whose static calls are allowed only where FINALISE_FUNCTIONS says. */
    private const FINALISE_ONLY_STATIC = ['cache_helper'];

    /** @var string[] $DB write methods only the database object has: a finding on any receiver. */
    private const DB_WRITES = [
        'insert_record', 'insert_records', 'insert_record_raw', 'import_record', 'update_record',
        'update_record_raw', 'set_field', 'set_field_select', 'delete_records', 'delete_records_select',
        'delete_records_list', 'delete_records_subquery', 'replace_all_text',
    ];

    /** @var string[] $DB methods whose name is generic: a finding only on a receiver that holds the database. */
    private const DB_WRITES_ON_DB = ['execute'];

    /** @var string[] database_manager methods that change the schema. */
    private const DDL = [
        'create_table', 'drop_table', 'rename_table', 'add_field', 'drop_field', 'rename_field',
        'change_field_type', 'change_field_precision', 'change_field_notnull', 'change_field_default',
        'add_key', 'drop_key', 'add_index', 'drop_index', 'install_from_xmldb_file',
        'install_one_table_from_xmldb_file', 'install_from_xmldb_structure', 'create_temp_table',
        'change_database_structure',
    ];

    /** @var string[] Variables that hold the database object by convention. */
    private const DB_VARIABLES = ['$DB', '$db'];

    /** @var string[] Methods that, by convention, return the database object: $this->db()->execute(...). */
    private const DB_ACCESSORS = ['db', 'database', 'get_db', 'get_database', 'getdb', 'getdatabase'];

    /**
     * Every PHP file below a directory, sub-directories included, in a stable order.
     *
     * @param string $dir
     * @return string[] Paths; empty when the directory does not exist.
     */
    public static function php_files(string $dir): array {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

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
        $aliases = [];
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
            $infinalise = $iswriter || in_array($function, self::FINALISE_FUNCTIONS, true);

            // A variable that holds the database: $x = $DB; $x =& $GLOBALS['DB']; $x = $this->db;
            // or a parameter typed moodle_database.
            if ($id === T_VARIABLE) {
                if (self::is_alias_assignment($tokens, $i, $aliases)) {
                    $aliases[$text] = true;
                }
                $type = $tokens[$i - 1] ?? [null, '', 0];
                if (($type[0] === T_STRING || $type[0] === T_NAME_QUALIFIED || $type[0] === T_NAME_FULLY_QUALIFIED)
                        && self::last_segment($type[0], $type[1]) === 'moodle_database') {
                    $aliases[$text] = true;
                }
            }

            $name = self::last_segment($id, $text);
            $next = $tokens[$i + 1] ?? [null, '', 0];
            $previous = $tokens[$i - 1] ?? [null, '', 0];
            $declaration = $previous[0] === T_FUNCTION;

            // A banned function called by name.
            if (!$declaration && $name !== null && in_array($name, self::BANNED_CALLS, true) && $next[1] === '(') {
                $findings[] = "line {$line}: {$name}()";
            }
            if ($name !== null && in_array($name, self::BANNED_CLASSES, true)) {
                $findings[] = "line {$line}: {$name}";
            }
            if (!$framework) {
                foreach (self::importer_only_findings($tokens, $i, $name, $line) as $finding) {
                    $findings[] = $finding;
                }
            }
            // The grade_* functions.
            if (!$declaration && $name !== null && strncmp($name, self::GRADE_PREFIX, strlen(self::GRADE_PREFIX)) === 0
                    && $next[1] === '(') {
                $findings[] = "line {$line}: {$name}()";
            }
            // set_config and the cache purges outside finalise().
            if (!$declaration && !$infinalise && $name !== null && in_array($name, self::FINALISE_ONLY_CALLS, true)
                    && $next[1] === '(') {
                $findings[] = "line {$line}: {$name}() outside finalise()";
            }
            // get_recordset*: an object method or a function.
            if ($id === T_STRING && strncmp($text, 'get_recordset', 13) === 0) {
                $findings[] = "line {$line}: {$text}";
            }
            // Banned static calls.
            if ($name !== null && $next[0] === T_DOUBLE_COLON) {
                $method = $tokens[$i + 2][1] ?? '';
                if (isset(self::BANNED_STATIC[$name])) {
                    $rules = self::BANNED_STATIC[$name];
                    if (in_array('*', $rules, true) || in_array($method, $rules, true)) {
                        $findings[] = "line {$line}: {$name}::{$method}";
                    }
                }
                if (!$infinalise && in_array($name, self::FINALISE_ONLY_STATIC, true)) {
                    $findings[] = "line {$line}: {$name}::{$method} outside finalise()";
                }
            }
            // ->trigger(, ->get_records(, $DB write methods and DDL.
            if ($id === T_OBJECT_OPERATOR || $id === T_NULLSAFE_OBJECT_OPERATOR) {
                $method = $next[1];
                $after = $tokens[$i + 2][1] ?? '';
                $receiverisdb = self::receiver_is_db($tokens, $i, $aliases);
                if ($next[0] === T_STRING && $after === '(') {
                    if ($method === 'trigger') {
                        $findings[] = "line {$line}: ->trigger()";
                    }
                    if ($method === 'get_records' && !$framework) {
                        $findings[] = "line {$line}: ->get_records() outside the framework";
                    }
                    if (!$iswriter && in_array($method, self::DB_WRITES, true)) {
                        $findings[] = "line {$line}: \$DB->{$method}() outside writer.php";
                    }
                    if (!$iswriter && $receiverisdb && in_array($method, self::DB_WRITES_ON_DB, true)) {
                        $findings[] = "line {$line}: \$DB->{$method}() outside writer.php";
                    }
                    if (!$iswriter && in_array($method, self::DDL, true)) {
                        $findings[] = "line {$line}: DDL {$method}() outside writer.php";
                    }
                } else if ($receiverisdb && !$iswriter && ($next[0] === T_VARIABLE || $next[1] === '{')) {
                    // $DB->$method(...) or $DB->{$name}(...): the name is not in the source.
                    $findings[] = "line {$line}: dynamic method call on \$DB outside writer.php";
                }
            }
        }
        return $findings;
    }

    /**
     * Constructs that hide the callee, and the framework's own classes, in importer code.
     *
     * @param array $tokens
     * @param int $i Index of the current token.
     * @param string|null $name Last segment of the current token when it is a name.
     * @param int $line
     * @return string[]
     */
    private static function importer_only_findings(array $tokens, int $i, ?string $name, int $line): array {
        [$id, $text] = $tokens[$i];
        $next = $tokens[$i + 1] ?? [null, '', 0];
        $previous = $tokens[$i - 1] ?? [null, '', 0];
        $out = [];

        // $f(...): the name of the function is not in the source.
        if ($id === T_VARIABLE && $next[1] === '(' && $previous[0] !== T_NEW) {
            $out[] = "line {$line}: call through a variable ({$text}(...)) hides the callee";
        }
        // $class::method(...) and new $class(...).
        if ($id === T_VARIABLE && $next[0] === T_DOUBLE_COLON) {
            $out[] = "line {$line}: static call through a variable ({$text}::) hides the class";
        }
        if ($id === T_NEW && $next[0] === T_VARIABLE) {
            $out[] = "line {$line}: new {$next[1]} hides the class";
        }
        // A banned name passed as a callable: array_map('role_assign', ...), [$DB, 'insert_record'], 'manager::send_message'.
        if ($id === T_CONSTANT_ENCAPSED_STRING) {
            $value = trim($text, '\'"');
            $parts = explode('::', $value);
            $value = end($parts);
            $banned = in_array($value, self::BANNED_CALLS, true) || in_array($value, self::DB_WRITES, true)
                || in_array($value, self::DDL, true) || in_array($value, self::FINALISE_ONLY_CALLS, true)
                || preg_match('/^grade_[a-z_]+$/', $value);
            if ($banned) {
                $out[] = "line {$line}: {$value} as a callable string hides the call";
            }
        }
        // The framework's own classes.
        if ($name !== null && in_array($name, self::FRAMEWORK_ONLY_CLASSES, true)
                && ($next[0] === T_DOUBLE_COLON || $previous[0] === T_NEW || $previous[0] === T_USE)) {
            $out[] = "line {$line}: {$name} is the framework's, importer code reaches it through its context";
        }
        return $out;
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
     * Does the expression that ends at $end (an index of the token before -> or ?->) hold the database?
     * $DB, $db, an alias, ->db, ->DB, ->database and $GLOBALS['DB'].
     *
     * @param array $tokens
     * @param int $index Index of the T_OBJECT_OPERATOR (or nullsafe) token.
     * @param array<string, bool> $aliases Variables known to hold the database.
     * @return bool
     */
    private static function receiver_is_db(array $tokens, int $index, array $aliases): bool {
        $before = $tokens[$index - 1] ?? [null, '', 0];
        if ($before[0] === T_VARIABLE) {
            return in_array($before[1], self::DB_VARIABLES, true) || isset($aliases[$before[1]]);
        }
        if ($before[0] === T_STRING && in_array(strtolower($before[1]), ['db', 'database'], true)) {
            $operator = $tokens[$index - 2] ?? [null, '', 0];
            return in_array($operator[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
        }
        if ($before[1] === ')') {
            // $this->db()->, self::database()->: the method that returns the database object.
            $depth = 0;
            for ($j = $index - 1; $j >= 0; $j--) {
                if ($tokens[$j][1] === ')') {
                    $depth++;
                } else if ($tokens[$j][1] === '(') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            if ($j < 2) {
                return false;
            }
            $method = $tokens[$j - 1];
            $operator = $tokens[$j - 2];
            return $method[0] === T_STRING && in_array(strtolower($method[1]), self::DB_ACCESSORS, true)
                && in_array($operator[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);
        }
        if ($before[1] === ']') {
            // $GLOBALS['DB']->
            $key = $tokens[$index - 2] ?? [null, '', 0];
            $bracket = $tokens[$index - 3] ?? [null, '', 0];
            $globals = $tokens[$index - 4] ?? [null, '', 0];
            return $globals[1] === '$GLOBALS' && $bracket[1] === '[' && $key[0] === T_CONSTANT_ENCAPSED_STRING
                && in_array(trim($key[1], '\'"'), ['DB', 'db'], true);
        }
        return false;
    }

    /**
     * Is the variable at $index assigned the database object itself ($x = $DB;)?
     *
     * @param array $tokens
     * @param int $index Index of the T_VARIABLE token.
     * @param array<string, bool> $aliases
     * @return bool
     */
    private static function is_alias_assignment(array $tokens, int $index, array $aliases): bool {
        if (($tokens[$index + 1][1] ?? '') !== '=') {
            return false;
        }
        $at = $index + 2;
        if (($tokens[$at][1] ?? '') === '&') {
            $at++;
        }
        $first = $tokens[$at] ?? [null, '', 0];
        $end = [';', ',', ')'];
        if ($first[0] === T_VARIABLE) {
            if (in_array($first[1], self::DB_VARIABLES, true) || isset($aliases[$first[1]])) {
                return in_array($tokens[$at + 1][1] ?? '', $end, true);
            }
            if ($first[1] === '$this') {
                // $x = $this->db;
                $arrow = $tokens[$at + 1] ?? [null, '', 0];
                $property = $tokens[$at + 2] ?? [null, '', 0];
                return in_array($arrow[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)
                    && $property[0] === T_STRING && in_array(strtolower($property[1]), ['db', 'database'], true)
                    && in_array($tokens[$at + 3][1] ?? '', $end, true);
            }
            if ($first[1] === '$GLOBALS') {
                $key = $tokens[$at + 2] ?? [null, '', 0];
                return ($tokens[$at + 1][1] ?? '') === '[' && $key[0] === T_CONSTANT_ENCAPSED_STRING
                    && in_array(trim($key[1], '\'"'), ['DB', 'db'], true) && ($tokens[$at + 3][1] ?? '') === ']'
                    && in_array($tokens[$at + 4][1] ?? '', $end, true);
            }
        }
        return false;
    }
}

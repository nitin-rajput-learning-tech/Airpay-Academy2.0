<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Finds the BizLMS legacy tables that sit in the database (ADR-032, "Parity
 * hooks" 1 and 3).
 *
 * Production's BizLMS plugin code is not deployed, but its tables stay in the
 * database as the archive. Two notions are needed:
 *
 * - known(): the tables of the 22 purchased plugins, taken from the verified
 *   inventory in docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md section 1.
 *   A database holding any of them is a BizLMS database. This is what the
 *   Phase 0 guards on the old copy and seed scripts test, because it stays
 *   true even if a BizLMS plugin's code were on disk.
 * - detect(): every table that looks legacy: known tables that exist, plus any
 *   other local_%, block_% or paygw_% table that no installed plugin declares
 *   (ADR-032 parity hook 1 and the unclaimed-table check). Sentientia's own
 *   local_sentientia_% tables and tables of installed plugins are excluded,
 *   so core tables such as block_instances never count as legacy.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_tables {

    /** Name prefixes of tables that may be legacy. */
    public const PREFIXES = ['local_', 'block_', 'paygw_'];

    /** Prefix of Sentientia's own tables, never legacy. */
    public const SENTIENTIA_PREFIX = 'local_sentientia_';

    /**
     * Tables of the 22 BizLMS plugins (mapping doc section 1), without prefix.
     * Tables whose existence is evidence rather than a snapshot declaration
     * (local_challenge, local_positions, local_domains, block_request_*) are
     * included.
     *
     * @var string[]
     */
    public const KNOWN = [
        'block_request_comments', 'block_request_config', 'block_request_records', 'block_trending_modules',
        'local_bc_completion_criteria', 'local_bc_level_comp_bk', 'local_bc_level_completions',
        'local_bcl_cmplt_criteria', 'local_biz_cart_credits', 'local_biz_cart_history', 'local_biz_cart_id',
        'local_biz_cart_invoices', 'local_biz_cart_ledger', 'local_certificate', 'local_certification',
        'local_challenge', 'local_classroom', 'local_classroom_attendance', 'local_classroom_categories',
        'local_classroom_completion', 'local_classroom_courses', 'local_classroom_sessions',
        'local_classroom_test_score', 'local_classroom_trainerfb', 'local_classroom_trainers',
        'local_classroom_users', 'local_classroom_waitlist', 'local_comment', 'local_costcenter',
        'local_costcenter_permissions', 'local_course_levels', 'local_course_types', 'local_coursedetails',
        'local_courseerrors', 'local_custom_category', 'local_dashboardcourses', 'local_domains',
        'local_email_logs', 'local_emaillogs', 'local_eval_completedtmp', 'local_eval_sitecourse_map',
        'local_eval_valuetmp', 'local_evaluation_completed', 'local_evaluation_item',
        'local_evaluation_template', 'local_evaluation_users', 'local_evaluation_value', 'local_evaluations',
        'local_filters', 'local_groups', 'local_interested_skills', 'local_learningplan',
        'local_learningplan_approval', 'local_learningplan_courses', 'local_learningplan_user', 'local_like',
        'local_location_institutes', 'local_location_room', 'local_logs', 'local_moduleconfig',
        'local_notification_info', 'local_notification_strings', 'local_notification_type',
        'local_onlinetests', 'local_org_dept_roles', 'local_plan_course_status', 'local_positions',
        'local_program', 'local_program_completions_bk', 'local_program_level_courses', 'local_program_levels',
        'local_program_test_score', 'local_program_trainerfb', 'local_program_trainers', 'local_program_users',
        'local_rating', 'local_ratings_likes', 'local_recompletion_cc', 'local_recompletion_cc_cc',
        'local_recompletion_cmc', 'local_recompletion_config', 'local_recompletion_ltia',
        'local_recompletion_qa', 'local_recompletion_qg', 'local_recompletion_qr', 'local_recompletion_qr_bool',
        'local_recompletion_qr_date', 'local_recompletion_qr_m', 'local_recompletion_qr_other',
        'local_recompletion_qr_rank', 'local_recompletion_qr_single', 'local_recompletion_qr_text',
        'local_recompletion_sst', 'local_request_comments', 'local_request_config', 'local_request_form_data',
        'local_request_formfields', 'local_request_records', 'local_skill', 'local_skill_categories',
        'local_skillmatrix', 'local_syncerrors', 'local_tag_mapping', 'local_tags', 'local_transcript_history',
        'local_uniquelogins', 'local_userdata', 'local_userssyncdata',
    ];

    /** @var array<string, bool>|null Memo of tables declared by installed plugins. */
    private static ?array $schema = null;

    /**
     * Known BizLMS tables that exist in this database.
     *
     * @return string[] Sorted.
     */
    public static function known_present(): array {
        global $DB;
        $present = [];
        foreach (self::KNOWN as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                $present[] = $table;
            }
        }
        return $present;
    }

    /**
     * Does this database hold BizLMS tables? The test the Phase 0 guards use.
     *
     * @return bool
     */
    public static function holds_bizlms(): bool {
        global $DB;
        foreach (self::KNOWN as $table) {
            if ($DB->get_manager()->table_exists($table)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Every legacy table present in the database.
     *
     * @return string[] Table names without prefix, sorted.
     */
    public static function detect(): array {
        global $DB;
        $schema = self::installed_schema_tables();
        $runtime = self::runtime_tables();
        $known = array_flip(self::KNOWN);
        $found = [];
        foreach (array_keys($DB->get_tables()) as $table) {
            if (isset($known[$table])) {
                $found[$table] = true;
                continue;
            }
            if (strncmp($table, self::SENTIENTIA_PREFIX, strlen(self::SENTIENTIA_PREFIX)) === 0) {
                continue;
            }
            $candidate = false;
            foreach (self::PREFIXES as $prefix) {
                if (strncmp($table, $prefix, strlen($prefix)) === 0) {
                    $candidate = true;
                    break;
                }
            }
            if ($candidate && !isset($schema[$table]) && !isset($runtime[$table])) {
                $found[$table] = true;
            }
        }
        $names = array_keys($found);
        sort($names);
        return $names;
    }

    /**
     * Tables declared by an install.xml of any installed plugin or of core.
     *
     * Read with a regular expression, not the XMLDB loader: it is fast, and a
     * malformed install.xml elsewhere cannot stop an inventory.
     *
     * @return array<string, bool>
     */
    public static function installed_schema_tables(): array {
        global $DB;
        if (self::$schema === null) {
            $schema = [];
            foreach ($DB->get_manager()->get_install_xml_files() as $file) {
                $raw = @file_get_contents($file);
                if ($raw !== false && preg_match_all('/<TABLE\s+NAME="([^"]+)"/', $raw, $m)) {
                    foreach ($m[1] as $table) {
                        $schema[$table] = true;
                    }
                }
            }
            self::$schema = $schema;
        }
        return self::$schema;
    }

    /**
     * Tables Sentientia plugins create at runtime and list in a
     * classes/schema/*::TABLES constant (the convention privacy_coverage_test
     * reads).
     *
     * @return array<string, bool>
     */
    public static function runtime_tables(): array {
        $tables = [];
        foreach (\core_component::get_plugin_list('local') as $name => $dir) {
            foreach (glob($dir . '/classes/schema/*.php') ?: [] as $file) {
                $class = '\\local_' . $name . '\\schema\\' . basename($file, '.php');
                if (!class_exists($class)) {
                    continue;
                }
                $reflection = new \ReflectionClass($class);
                if (!$reflection->hasConstant('TABLES')) {
                    continue;
                }
                foreach ((array) $reflection->getConstant('TABLES') as $table) {
                    if (is_string($table)) {
                        $tables[$table] = true;
                    }
                }
            }
        }
        return $tables;
    }

    /**
     * Forget the memoised install schema (tests that change plugins on disk).
     *
     * @return void
     */
    public static function reset(): void {
        self::$schema = null;
    }
}

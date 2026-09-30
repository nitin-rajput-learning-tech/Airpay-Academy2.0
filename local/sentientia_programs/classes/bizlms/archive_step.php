<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * The three BizLMS tables that are kept only in the legacy archive: local_program_completions_bk,
 * local_bc_level_comp_bk and local_program_test_score.
 *
 * Their writers are commented out in BizLMS (program.php:1663-1667,1677-1681) and nothing shows them, so they
 * are expected to be empty. A row that does exist is archived with a reason that needs the owner: the signed
 * decision program.bk_tables is "archive only", and parity exits 2 until the owner accepts the reason in
 * writing, because the row is learner history that no Sentientia page shows.
 *
 * The nominal target is never written; the registry only needs a declared table of the plugin.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class archive_step extends base_step {

    /** @var string The legacy table. */
    private string $table;

    /** @var string The step key suffix. */
    private string $suffix;

    /** @var string The nominal target table. */
    private string $nominal;

    /**
     * @param string $table Legacy table without prefix.
     * @param string $suffix Step key suffix.
     * @param string $nominal A target table of the plugin, never written.
     */
    public function __construct(string $table, string $suffix, string $nominal) {
        $this->table = $table;
        $this->suffix = $suffix;
        $this->nominal = $nominal;
    }

    public function key(): string {
        return 'program.' . $this->suffix;
    }

    public function sourcetable(): string {
        return $this->table;
    }

    public function targettable(): string {
        return $this->nominal;
    }

    public function columns(): array {
        return ['id'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = outcome::archive((int) $row->id, 'bk_rows_archived');
        }
        return $out;
    }
}

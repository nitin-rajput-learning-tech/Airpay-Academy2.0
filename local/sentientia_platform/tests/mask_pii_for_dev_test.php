<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * The dev masking script names the columns it masks, and they must exist (F-60, F-87).
 *
 * cli/mask_pii_for_dev.php updated a to_email column of local_sentientia_email_log that the table has never had; the
 * statement would have stopped the script on the first real run, and nobody saw it because nothing runs the script
 * under a test. The script bootstraps Moodle and writes to a database, so it cannot be run here. This test reads its
 * text and the install.xml of the three tables the BizLMS import fills with personal text (the e-mail log, requests and
 * the imported admin log), and checks that the script updates each of them on columns that exist.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @coversNothing
 *
 * @group local_sentientia_platform
 */
final class mask_pii_for_dev_test extends \basic_testcase {

    /** @var array<string, array{0: string, 1: string[]}> table => [owning component, columns the script must name] */
    private const MASKED = [
        'local_sentientia_email_log' => ['local_sentientia_emails', ['subject', 'body_html', 'legacy_source']],
        'local_sentientia_request' => ['local_sentientia_request', ['decision_note', 'legacy_source']],
        'local_sentientia_admin_log' => ['local_sentientia_core', ['description']],
    ];

    /**
     * @return string The script.
     */
    private function script(): string {
        $path = __DIR__ . '/../cli/mask_pii_for_dev.php';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    public function test_the_script_no_longer_updates_a_column_that_does_not_exist(): void {
        $this->assertStringNotContainsString('to_email', $this->script(),
            'local_sentientia_email_log has no to_email column; the statement would stop the script');
    }

    public function test_it_masks_the_imported_text_on_columns_the_tables_have(): void {
        $script = $this->script();
        foreach (self::MASKED as $table => [$component, $columns]) {
            $this->assertStringContainsString('UPDATE {' . $table . '}', $script, "{$table} is masked");
            $xml = (string) file_get_contents(\core_component::get_component_directory($component) . '/db/install.xml');
            $this->assertSame(1, preg_match('~<TABLE NAME="' . preg_quote($table, '~') . '".*?</TABLE>~s', $xml, $m),
                "{$table} is declared in {$component}");
            preg_match_all('~<FIELD NAME="([a-z_0-9]+)"~', $m[0], $fields);
            foreach ($columns as $column) {
                $this->assertContains($column, $fields[1], "{$table}.{$column} exists");
                $this->assertMatchesRegularExpression('~\b' . preg_quote($column, '~') . '\b~', $script,
                    "the script names {$table}.{$column}");
            }
        }
    }

    public function test_the_credentials_mask_it_keeps_is_the_one_the_importer_writes(): void {
        // local_sentientia_emails\bizlms\redactor::SUBJECT_MASK; the platform does not depend on that plugin, so the
        // text is repeated here and the emails plugin's own tests pin the constant.
        $this->assertStringContainsString("'[withheld: account credentials]'", $this->script(),
            'the subject mask the import writes for a credential message is left as it is');
    }
}

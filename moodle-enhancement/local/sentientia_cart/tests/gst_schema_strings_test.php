<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The shape of the GST tax invoice release (spec 2026-10-14, section 11): the schema, the version, the lang packs and
 * the templates.
 *
 *  - install.xml and upgrade.php both create the schema (a fresh install and an upgraded site must end the same), and
 *    install.xml respects the XMLDB limits (index names under 30 characters, VARCHAR up to 1333, no raw '<' in a
 *    COMMENT, which breaks the whole file and which upgrade.php would hide);
 *  - the version is 2026101400, release 1.2.0, in the plugin's version.php;
 *  - every string the new code asks for exists in English and in Hindi, with the same keys in both packs;
 *  - the new templates print everything with the escaping braces (no triple brace) and no word of their own.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\doc_view
 * @covers     \local_sentientia_cart\receipts
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 */
final class gst_schema_strings_test extends \advanced_testcase {

    /** The columns this release adds, by table. */
    private const NEW_COLUMNS = [
        'local_sentientia_cart_history'  => ['billing_state', 'gst_rate', 'invoice_due'],
        'local_sentientia_cart_invoices' => ['billing_state', 'place_of_supply', 'sac_code', 'gst_rate', 'irn', 'ack_no',
            'ack_date', 'signed_qr', 'supplier_json'],
    ];

    private function plugin_dir(): string {
        global $CFG;
        return $CFG->dirroot . '/local/sentientia_cart';
    }

    // ── schema ───────────────────────────────────────────────────────────

    public function test_install_xml_defines_the_new_tables_and_columns(): void {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->load($this->plugin_dir() . '/db/install.xml'),
            'install.xml must be well-formed XML (a raw "<" in a COMMENT attribute breaks it)');
        $xpath = new \DOMXPath($dom);

        foreach (['local_sentientia_cart_creditnotes', 'local_sentientia_cart_docseq'] as $table) {
            $this->assertSame(1, $xpath->query("//TABLE[@NAME='$table']")->length, "install.xml defines $table");
        }
        foreach (self::NEW_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertSame(1, $xpath->query("//TABLE[@NAME='$table']/FIELDS/FIELD[@NAME='$column']")->length,
                    "install.xml gives $table the column $column");
            }
        }
        // The columns the spec names, with their types.
        $type = fn(string $t, string $c) => $xpath->query("//TABLE[@NAME='$t']/FIELDS/FIELD[@NAME='$c']")->item(0);
        $this->assertSame('char', $type('local_sentientia_cart_history', 'billing_state')->getAttribute('TYPE'));
        $this->assertSame('2', $type('local_sentientia_cart_history', 'billing_state')->getAttribute('LENGTH'));
        $this->assertSame('number', $type('local_sentientia_cart_history', 'gst_rate')->getAttribute('TYPE'));
        $this->assertSame('5', $type('local_sentientia_cart_history', 'gst_rate')->getAttribute('LENGTH'));
        $this->assertSame('2', $type('local_sentientia_cart_history', 'gst_rate')->getAttribute('DECIMALS'));
        $this->assertSame('int', $type('local_sentientia_cart_history', 'invoice_due')->getAttribute('TYPE'));
        $this->assertSame('1', $type('local_sentientia_cart_history', 'invoice_due')->getAttribute('LENGTH'));
        $this->assertSame('0', $type('local_sentientia_cart_history', 'invoice_due')->getAttribute('DEFAULT'));
        $this->assertSame('text', $type('local_sentientia_cart_invoices', 'signed_qr')->getAttribute('TYPE'));
        $this->assertSame('char', $type('local_sentientia_cart_docseq', 'series')->getAttribute('TYPE'));
        $this->assertSame('40', $type('local_sentientia_cart_docseq', 'series')->getAttribute('LENGTH'));
        $this->assertSame(1, $xpath->query("//TABLE[@NAME='local_sentientia_cart_docseq']/KEYS/KEY[@TYPE='unique'][@FIELDS='series']")->length,
            'the series is unique');
        $this->assertSame(1, $xpath->query("//TABLE[@NAME='local_sentientia_cart_creditnotes']/KEYS/KEY[@TYPE='unique'][@FIELDS='cn_number']")->length,
            'the credit note number is unique');
    }

    public function test_install_xml_respects_the_xmldb_limits(): void {
        $dom = new \DOMDocument();
        $dom->load($this->plugin_dir() . '/db/install.xml');
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//INDEX') as $index) {
            $name = $index->getAttribute('NAME');
            $this->assertLessThan(30, strlen($name), "index name '$name' must be under 30 characters");
        }
        foreach ($xpath->query('//KEY') as $key) {
            $this->assertLessThan(30, strlen($key->getAttribute('NAME')));
        }
        foreach ($xpath->query("//FIELD[@TYPE='char']") as $field) {
            $this->assertLessThanOrEqual(1333, (int) $field->getAttribute('LENGTH'), $field->getAttribute('NAME'));
        }
        foreach ($xpath->query('//*[@COMMENT]') as $node) {
            $this->assertStringNotContainsString('<', $node->getAttribute('COMMENT'),
                'no raw "<" in a COMMENT: write "less than"');
        }
    }

    public function test_the_database_has_what_install_xml_defines(): void {
        global $DB, $CFG;
        $dbman = $DB->get_manager();
        foreach (['local_sentientia_cart_creditnotes', 'local_sentientia_cart_docseq'] as $table) {
            $this->assertTrue($dbman->table_exists($table), "$table exists");
        }
        foreach (self::NEW_COLUMNS as $table => $columns) {
            $actual = $DB->get_columns($table);
            foreach ($columns as $column) {
                $this->assertArrayHasKey($column, $actual, "$table.$column");
            }
        }
        $this->assertSame(0, (int) $DB->get_columns('local_sentientia_cart_history')['invoice_due']->default_value);

        $file = new \xmldb_file($CFG->dirroot . '/local/sentientia_cart/db/install.xml');
        $file->loadXMLStructure();
        $errors = $dbman->check_database_schema($file->getStructure());
        $mine = array_intersect_key($errors, array_flip(['local_sentientia_cart_creditnotes', 'local_sentientia_cart_docseq']));
        $this->assertSame([], $mine, 'the new tables match install.xml');
        $newfields = array_merge(...array_values(self::NEW_COLUMNS));
        foreach (['local_sentientia_cart_history', 'local_sentientia_cart_invoices'] as $table) {
            foreach ($errors[$table] ?? [] as $error) {
                foreach ($newfields as $field) {
                    $this->assertStringNotContainsString($field, $error, "$table: $error");
                }
            }
        }
    }

    public function test_upgrade_php_adds_the_same_schema_idempotently(): void {
        $upgrade = file_get_contents($this->plugin_dir() . '/db/upgrade.php');
        $this->assertStringContainsString('if ($oldversion < 2026101400)', $upgrade);
        $this->assertStringContainsString("upgrade_plugin_savepoint(true, 2026101400, 'local', 'sentientia_cart')", $upgrade);
        foreach (['billing_state', 'gst_rate', 'invoice_due', 'place_of_supply', 'sac_code', 'irn', 'ack_no', 'ack_date',
                'signed_qr', 'supplier_json', 'local_sentientia_cart_creditnotes', 'local_sentientia_cart_docseq'] as $name) {
            $this->assertStringContainsString("'$name'", $upgrade, "upgrade.php creates $name");
        }
        $step = substr($upgrade, strpos($upgrade, 'if ($oldversion < 2026101400)'));
        // The three history columns are guarded one by one, the invoice columns in a loop.
        $this->assertGreaterThanOrEqual(4, substr_count($step, 'field_exists('), 'each added column is guarded');
        $this->assertGreaterThanOrEqual(2, substr_count($step, 'index_exists('), 'each added index is guarded');
        $this->assertSame(2, substr_count($step, 'table_exists('), 'each new table is guarded');
    }

    public function test_the_version_is_2026101400_release_1_2_0(): void {
        $plugin = new \stdClass();
        include($this->plugin_dir() . '/version.php');
        $this->assertSame(2026101400, $plugin->version);
        $this->assertSame('1.2.0', $plugin->release);
        $this->assertSame('local_sentientia_cart', $plugin->component);
    }

    // ── lang packs ───────────────────────────────────────────────────────

    /**
     * The string ids of a lang file.
     *
     * @param string $lang
     * @return string[]
     */
    private function pack_keys(string $lang): array {
        $string = [];
        include($this->plugin_dir() . "/lang/$lang/local_sentientia_cart.php");
        return array_keys($string);
    }

    public function test_english_and_hindi_have_exactly_the_same_keys(): void {
        $en = $this->pack_keys('en');
        $hi = $this->pack_keys('hi');
        $this->assertSame([], array_values(array_diff($en, $hi)), 'in English but not in Hindi');
        $this->assertSame([], array_values(array_diff($hi, $en)), 'in Hindi but not in English');
    }

    public function test_every_label_and_string_the_new_code_asks_for_exists(): void {
        $en = array_flip($this->pack_keys('en'));
        $hi = array_flip($this->pack_keys('hi'));
        $wanted = [];

        // The labels a document and a receipt print.
        foreach ([[doc_view::class, 'DOC_LABELS', 'doc_'], [receipts::class, 'LABELS', 'receipt_']] as [$class, $const, $prefix]) {
            $labels = (new \ReflectionClassConstant($class, $const))->getValue();
            $this->assertNotEmpty($labels);
            foreach ($labels as $label) {
                $wanted[] = $prefix . $label;
            }
        }
        // The states, and what the code says by name.
        foreach (gst_states::codes() as $code) {
            $wanted[] = 'gst_state_' . $code;
        }
        foreach (['doc_for', 'doc_creditline', 'receipt_page_title', 'signatory_default', 'notify_paid_receipt',
                'notify_gst_subject', 'notify_gst_body', 'task_issue_due_documents', 'messageprovider:gst_admin_warning',
                'sentientia_cart:manageinvoices', 'error_invoicesettings', 'error_numbertoolong', 'error_creditnotelimit',
                'error_statemissing', 'error_statenotvalid', 'error_gstinformat', 'error_gstinstate', 'error_irninvalid',
                'error_ackinvalid', 'error_ackdateinvalid', 'error_qrinvalid', 'error_irnduplicate', 'error_irnnotawaiting',
                'error_notavailable', 'billingstate', 'billingstate_choose', 'billingstate_hint', 'history_documents',
                'history_receipt', 'history_taxinvoice', 'history_creditnote', 'viewreceipt', 'einvoice_link'] as $id) {
            $wanted[] = $id;
        }
        foreach (['company_name', 'company_address', 'our_gstn', 'sac_code', 'supplier_state'] as $problem) {
            $wanted[] = 'settings_problem_' . $problem;
        }
        foreach (['settings_gst', 'settings_saccode', 'settings_supplierstate', 'settings_creditnoteprefix',
                'settings_seriesperiod', 'settings_einvoiceb2b', 'settings_retentionyears', 'settings_signatory'] as $setting) {
            $wanted[] = $setting;
            $wanted[] = $setting . '_desc';
        }

        // Everything the pages, classes and templates look up by a literal name.
        foreach ($this->source_files() as $path) {
            $text = file_get_contents($path);
            if (preg_match_all("/get_string\(\s*'([A-Za-z0-9_:]+)'\s*,\s*'local_sentientia_cart'/", $text, $m)) {
                array_push($wanted, ...$m[1]);
            }
            if (preg_match_all('/\{\{#str\}\}\s*([A-Za-z0-9_:]+)\s*,\s*local_sentientia_cart\s*\{\{\/str\}\}/', $text, $m)) {
                array_push($wanted, ...$m[1]);
            }
        }

        $missingen = $missinghi = [];
        foreach (array_unique($wanted) as $id) {
            if (!isset($en[$id])) {
                $missingen[] = $id;
            }
            if (!isset($hi[$id])) {
                $missinghi[] = $id;
            }
        }
        $this->assertSame([], $missingen, 'strings the code asks for that English lacks');
        $this->assertSame([], $missinghi, 'strings the code asks for that Hindi lacks');
    }

    public function test_the_hindi_strings_are_really_translated(): void {
        $string = [];
        include($this->plugin_dir() . '/lang/hi/local_sentientia_cart.php');
        $hindi = $string;
        $string = [];
        include($this->plugin_dir() . '/lang/en/local_sentientia_cart.php');
        $english = $string;
        // Technical labels that are the same word in both languages, and words that are names.
        $same = ['doc_gstin', 'doc_sac', 'doc_cgst', 'doc_sgst', 'doc_igst', 'doc_irn'];
        foreach (array_keys($hindi) as $id) {
            if (strpos($id, 'doc_') === 0 || strpos($id, 'receipt_') === 0 || strpos($id, 'gst_state_') === 0
                    || strpos($id, 'einvoice_') === 0 || strpos($id, 'settings_problem_') === 0) {
                if (in_array($id, $same, true)) {
                    continue;
                }
                $this->assertNotSame($english[$id], $hindi[$id], "$id is not translated");
                $this->assertMatchesRegularExpression('/[\x{0900}-\x{097F}]/u', $hindi[$id], "$id has no Devanagari");
            }
        }
    }

    // ── templates ────────────────────────────────────────────────────────

    /**
     * Every PHP file and template of the plugin that can ask for a string: not the lang packs, not the tests.
     *
     * @return string[]
     */
    private function source_files(): array {
        $files = [];
        $base = $this->plugin_dir();
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $p = str_replace('\\', '/', $f->getPathname());
            if (strpos($p, '/lang/') !== false || strpos($p, '/tests/') !== false || strpos($p, '/amd/') !== false) {
                continue;
            }
            if (preg_match('/\.(php|mustache)$/', $p)) {
                $files[] = $p;
            }
        }
        return $files;
    }

    public function test_the_new_templates_use_only_the_escaping_braces(): void {
        foreach (['tax_document', 'tax_document_pdf', 'receipt', 'receipt_pdf', 'einvoice'] as $name) {
            $text = file_get_contents($this->plugin_dir() . "/templates/$name.mustache");
            $this->assertStringNotContainsString('{{{', $text, "$name: a triple brace prints raw HTML");
            $this->assertStringNotContainsString('{{&', $text, "$name: an ampersand tag prints raw HTML");
        }
    }

    public function test_the_new_templates_say_nothing_of_their_own(): void {
        // A template's words are lang strings (l.xxx, {{#str}}). Strip the comment, the style block, the tags and the
        // mustache tags: what is left may only be punctuation, symbols and numbers.
        foreach (['tax_document', 'tax_document_pdf', 'receipt', 'receipt_pdf', 'einvoice'] as $name) {
            $text = file_get_contents($this->plugin_dir() . "/templates/$name.mustache");
            $text = preg_replace('/\{\{!.*?\}\}/s', '', $text);
            $text = preg_replace('/<style>.*?<\/style>/s', '', $text);
            $text = preg_replace('/\{\{#str\}\}.*?\{\{\/str\}\}/s', '', $text);   // a lang string: its id is not text
            $text = preg_replace('/\{\{.*?\}\}/s', '', $text);
            $text = preg_replace('/<[^>]*>/s', '', $text);
            $text = html_entity_decode($text);
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{2,}/', $text, "$name has English text of its own: "
                . trim(substr(preg_replace('/\s+/', ' ', $text), 0, 120)));
        }
    }

    public function test_the_document_words_come_in_the_viewers_language(): void {
        $this->resetAfterTest();
        $english = doc_view::labels(['taxinvoice', 'creditnote', 'reversecharge'], 'doc_', 'en');
        $this->assertSame('Tax Invoice', $english['taxinvoice']);
        $this->assertSame('Credit Note', $english['creditnote']);
        if (!get_string_manager()->translation_exists('hi', false)) {
            // As in notifier_test: the Hindi words are read from this plugin's own pack once the language is installed.
            $this->markTestSkipped('The Hindi language pack is not installed in this environment.');
        }
        $hindi = doc_view::labels(['taxinvoice', 'creditnote', 'reversecharge'], 'doc_', 'hi');
        $this->assertSame('टैक्स इनवॉइस', $hindi['taxinvoice']);
        $this->assertNotSame($english['taxinvoice'], $hindi['taxinvoice']);
    }
}

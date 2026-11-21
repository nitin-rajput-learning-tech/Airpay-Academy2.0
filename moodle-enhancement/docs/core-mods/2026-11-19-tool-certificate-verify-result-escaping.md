# Core-mod record: `tool_certificate` (vendor plugin): the verification result escapes the holder's name and the certificate name

- **Date:** 2026-11-19
- **Author:** Claude (workflow step after D32). Approved by Nitin in chat on 2026-11-19 at 15:05: escape the holder
  full name and certificate name cells of the verification result as a minimal tagged vendor hunk under ADR-041. Raised
  as Risk 3 of `2026-11-19-tool-certificate-verify-on-arrival.md`. No feature flag: a bug fix to an existing page
  (ADR-041 D2).
- **Tag at the site:** `// SENTIENTIA-CORE-MOD: <reason>` opening the hunk, `// END SENTIENTIA-CORE-MOD` closing it
  (ADR-041 D3).
- **Files** (top-level tree only, which is the package source; tool_certificate has no `moodle-enhancement/` twin):
  - `admin/tool/certificate/classes/output/verify_certificate_result.php`: one hunk (the patch)
  - `admin/tool/certificate/tests/sentientia_verify_result_escaping_test.php`: new PHPUnit file (Sentientia-owned, not
    upstream)
- **Vendor release patched:** tool_certificate 4.5.7 (`version.php` 2025031804). `version.php` is not changed (see
  "Version bump").
- **Companion change (theme, not a core-mod):** the verification page now carries
  `<meta name="robots" content="noindex, nofollow" />` from `theme/sentientia/classes/output/core_renderer.php`
  (`robots_noindex_meta()`, theme 2026111901 / 1.0.72-beta). That closes Risk 1 of the D32 record; it is listed here
  only because both changes answer the same review of the page.

## The problem

`verify_certificate_result::export_for_template()` builds the result table from `html_table_cell`s and prints it with
`html_writer::table()`, which outputs cell text as HTML. `tool_certificate/verify_certificate_result.mustache` then
prints the finished table with `{{{table}}}`. Two cells held stored text without escaping:

- the holder's full name, read from the issue's `data` JSON (`userfullname`, written by `fullname()` at issue time,
  and carried over with the 11,048 BizLMS-era issues by Stage B);
- the certificate name, the template's `name` column, which `certificate::verify()` selects as `certificatename`.

So a `<` in either value started a tag and an `&` went out as a bare ampersand. Moodle's own forms clean both values
(names with `PARAM_NOTAGS`, template names with `PARAM_TEXT`, which keeps only multilang spans), so a tag needs a
value that reached the database another way, an import or a direct write. The page answers anyone who holds a code,
logged out, and since D32 on a plain GET, so it should not trust stored text.

The certificate name was also printed without `format_string()`: filters did not run on it, so a multilang name
showed every language's text. The plugin formats the same name elsewhere in the template's context
(`persistent\template::get_formatted_name()`).

## Before (`classes/output/verify_certificate_result.php`, lines 92-93)

```php
        $this->userfullname = @json_decode($issue->data, true)['userfullname'];
        $this->certificatename = $issue->certificatename;
```

## After (lines 92-98)

```php
        // SENTIENTIA-CORE-MOD: escape the holder's name and the certificate name, which export_for_template() puts in
        // html_table_cell()s that html_writer::table() prints as HTML. The template's context drives format_string().
        // See moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md
        $this->userfullname = s(@json_decode($issue->data, true)['userfullname']);
        $this->certificatename = format_string($issue->certificatename, true,
            ['context' => \context::instance_by_id($issue->contextid, IGNORE_MISSING) ?: \context_system::instance()]);
        // END SENTIENTIA-CORE-MOD
```

`export_for_template()`, which builds the cells, is unchanged.

## Why a patch, and why here (ADR-041 D1)

- **Theme template:** the theme already overrides `verify_certificate_results.mustache` (QW-CERT-VERIFY), but the
  table reaches it as finished HTML through the vendor's result partial. A template cannot escape inside it.
- **Theme renderer:** a `render_verify_certificate_results()` method in the theme's renderer could rebuild the table
  from the renderable's public properties. It would copy the vendor's table code (five rows, their strings and
  classes) into the theme, where a later vendor change to the table would not show, and it would fix only pages
  rendered with this theme. Nitin chose the vendor hunk.
- **Sentientia replacement page:** no; every printed QR code and shared link points at the vendor URL (see the D32
  record).
- **Why the constructor rather than the cells:** only the constructor has the issue row, and the row carries the
  template's `contextid`, which `format_string()` needs (filters and multilang are set per context).
  `export_for_template()` sees only the two values. Escaping them where they are read keeps the patch to one hunk of two
  statements. The two public properties now hold HTML-safe text. Nothing else reads them: the only caller is
  `verify_certificate_results::export_for_template()`, and no Sentientia code names the class (checked in
  `admin/tool/certificate/`, `theme/sentientia/classes/` and `local/sentientia_pages/`). The result template's doc
  comment lists `userfullname` and `certificatename` as context variables, but `export_for_template()` exports
  neither (a stale upstream comment).

## Behaviour change

- A full name or certificate name with `&`, `<`, `>` or quotes is shown as text. Ordinary names look the same.
- The certificate name goes through `format_string()` in the template's context: tags are stripped (the default
  `formatstringstriptags`), and with "Filter all strings" on, filters such as multilang apply, as on the plugin's
  other pages.
- If a template's context no longer exists, the name is formatted in the system context. The old code never read the
  context, so `IGNORE_MISSING` keeps a code that verified before from turning into an exception page.
- Nothing else changes: dates, status, the "View certificate" link, the valid and expired boxes.

## Version bump: none (ADR-041 D5)

The patch changes one class: no schema, capability, cache definition, string, template or AMD module. PHP reads the
file on the next request (on a host with `opcache.validate_timestamps=0`, reload Apache after the copy). Deploy with
`--no-upgrade`.

## Tests (written, not run)

`admin/tool/certificate/tests/sentientia_verify_result_escaping_test.php` (PHPUnit, group `tool_certificate` as an
attribute):

1. `test_names_are_escaped_in_the_result_table`: issues a certificate from a template in a course category, writes a
   full name with `<script>`, an apostrophe and `&` into the issue's data and `Safety & <b>Compliance</b>` into the
   template name, verifies the code logged out and renders the result the way `index.php` does. No `<script>` and no
   `<b>` in the HTML; the name appears escaped; the certificate name equals `format_string()` in the category context.
2. `test_missing_template_context_falls_back_to_system`: an issue whose `contextid` names no context renders, escaped.
3. `test_verify_certificate_result_carries_the_patch`: a tripwire. The file has exactly one `SENTIENTIA-CORE-MOD`
   hunk and both statements. Test 1 also fails if the hunk is dropped; this one names the cause.

Also fixed in the D32 test file `admin/tool/certificate/tests/sentientia_verify_on_arrival_test.php`: its class has
attributes, and PHPUnit 11 reads no doc-comment metadata from such a class (`ParserChain::forClass()` in PHPUnit
11.5.55, `C:\xampp\htdocs\moodle5\vendor\phpunit\phpunit\src\Metadata\Parser\`), so its `@group tool_certificate`
line was ignored and `--group tool_certificate` skipped the file. It now carries
`#[\PHPUnit\Framework\Attributes\Group('tool_certificate')]`.

Run: from `C:\xampp\htdocs\moodle5\`, `vendor\bin\phpunit --filter sentientia_verify_result_escaping_test` after the
PHPUnit site is initialised; then `vendor\bin\phpunit --group tool_certificate`.

## Upgrade-safety (how it merges with a future vendor update)

- One hunk of two statements in the constructor of a 149-line upstream file, unchanged in this repo since the vendor
  update in commit 82fee0969. Find it with `git grep -n "SENTIENTIA-CORE-MOD" -- admin/tool/certificate/`.
- At the next import (ADR-041 D7): check whether upstream now escapes these two values (`s()` or `format_string()`
  in `verify_certificate_result.php`, or a table built in a template with `{{ }}`). If it does, take upstream, add a
  "Reverted" note here and delete the Sentientia test file. If not, re-apply the hunk with its markers; the tripwire
  fails until that is done.
- If upstream starts exporting `userfullname` or `certificatename` to its template and prints them with `{{ }}`, this
  hunk would escape them twice: move the escaping to the table cells then.
- Moodle 5.3: `s()` (`lib/weblib.php`), `format_string()` (`lib/classes/formatting.php`) and
  `context::instance_by_id(..., IGNORE_MISSING)` behave as on 5.1.3+ (checked in `D:/Claude Local/moodle53/moodle/public`).
- Upstream contribution: generic, a candidate pull request to the plugin's project (ADR-041 open question 3). Not filed.

## Risks

1. **Stored entities show as text.** A full name stored already escaped (for example `&amp;`, written by an import
   that escaped it) now shows `&amp;`. `s()` keeps numeric entities such as `&#039;`. Step 2 below counts the
   affected rows before cutover.
2. **Tags in template names are stripped.** Intended: template names come from a `PARAM_TEXT` field, so only
   multilang spans are expected there.

## How to verify

1. Copy `admin/tool/certificate/classes/output/verify_certificate_result.php` to
   `C:\xampp\htdocs\moodle5\public\admin\tool\certificate\classes\output\` (copying into `moodle5\public\admin\` is
   denied by `.claude/settings.json`, so the copy is Nitin's or needs his permission). No upgrade, no purge.
2. Read-only, local and later on the Stage B rehearsal data:
   `SELECT COUNT(*) FROM mdl_tool_certificate_issues WHERE data LIKE '%&%' OR data LIKE '%<%'` and
   `SELECT id, name FROM mdl_tool_certificate_templates WHERE name LIKE '%&%' OR name LIKE '%<%'`.
3. As admin, rename a local test template to `Fire & Safety`, open a verification link for one of its issues, view
   source: the certificate cell holds `Fire &amp; Safety`; the page shows "Fire & Safety".
4. A verification link for an ordinary certificate looks as before (name, certificate, dates, status).
5. Run the PHPUnit tests above.

UAT (Nitin's call): `tools/uat/deploy_to_uat.sh --no-upgrade admin/tool/certificate/classes/output/verify_certificate_result.php`
(dry run), then with `--yes`, and repeat step 4 with one of UAT's test certificates.

## Addendum (19 Nov, lead)

A second tagged hunk adds `defined('MOODLE_INTERNAL') || die();` after the `use` block, because the project pre-commit hook (CHECK 2) requires the guard in every committed PHP file and upstream has none in this namespaced class. It has no runtime effect. The tripwire test now expects 2 markers. The three earlier patched vendor files already carry the guard.

---

## Addendum 2026-11-20 (re-vendored to 5.0.12 on 2026-11-20)

- **Still required on 5.0.12.** Upstream 5.0.12 still assigns `@json_decode($issue->data, true)['userfullname']` and
  `$issue->certificatename` unescaped (tag v5.0.12; equal to UAT's copy fetched before the 19 Nov 16:19 deploy).
- **Re-applied** unchanged: the guard hunk at lines 35-38 and the escaping hunk at lines 96-102. On 4.5.7 they were at
  35-38 and 97-103 (5.0.12 has no blank line after the class declaration).
- **Upstream change kept:** 5.0.12 computes `expired` with `\core\di::get(\core\clock::class)->time()`. The
  4.5.7-based file deployed to UAT on 19 Nov uses `time()`: the same at run time, but only the 5.0.12 line follows a
  test's mocked clock, so deploy the 5.0.12-based file to UAT (`--no-upgrade`, Nitin's go, decision D-8).
- Tripwire unchanged (2 markers, both statements present). **Vendor release patched:** now tool_certificate 5.0.12
  (`version.php` 2026111000). Import record: `2026-11-20-tool-certificate-5.0.12-revendor.md`.

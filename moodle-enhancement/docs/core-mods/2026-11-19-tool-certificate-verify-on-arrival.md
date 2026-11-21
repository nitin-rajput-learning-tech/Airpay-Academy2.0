# Core-mod record: `tool_certificate` (vendor plugin): verification links show the result on arrival

- **Date:** 2026-11-19 (post-demo plan `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §2.2, decision D32)
- **Author:** Claude (D32 workflow step). Approved by Nitin as decision D32 in §6a of the plan: "Patch tool_certificate as a recorded core-mod", as recommended. No feature flag: it is a bug fix to an existing page.
- **Tag at the site:** `// SENTIENTIA-CORE-MOD: <reason>` opening each hunk, `// END SENTIENTIA-CORE-MOD` closing it (the README convention). The rules for vendor hunks are in ADR-041.
- **Files** (top-level tree only, which is the package source; tool_certificate has no `moodle-enhancement/` twin):
  - `admin/tool/certificate/index.php`: two hunks (the patch)
  - `admin/tool/certificate/tests/sentientia_verify_on_arrival_test.php`: new PHPUnit file (Sentientia-owned, not upstream)
  - `admin/tool/certificate/tests/behat/sentientia_verify_on_arrival.feature`: new Behat file (Sentientia-owned, not upstream)
- **Vendor release patched:** tool_certificate 4.5.7 (`version.php` 2025031804). `version.php` is not changed (see "Version bump").

## The problem

Three links open the verification page with the code in the query string: the QR code printed on every certificate,
the "Verify" button in the certificate gallery (`local/sentientia_pages/certificates.php`) and the LinkedIn link. All
three use `\tool_certificate\template::verification_url($code)`, which is `/admin/tool/certificate/index.php?code=XXXX`.

1. **No result on arrival.** `index.php` printed a result only inside `if ($form->get_data())`, which is true only
   for a submitted form. A link is a GET with no form submission, so the page showed the code in the field and nothing
   else. The visitor had to press Verify again.
2. **A second "code" input.** The form was built with `$pageurl`, a `moodle_url` that carries `?code=`. For a
   `moodle_url` action, formslib prints every query parameter as a hidden input
   (`MoodleQuickForm::__construct`: `$this->_pageparams = html_writer::input_hidden_params($action)`, `lib/formslib.php`
   line 1756 on 5.1.3+, 1747 on 5.3). The page then has two inputs named `code`. The field's client-side "required"
   check runs on the field's `change` event and reads `frm.elements['code'].value`
   (`lib/pear/HTML/QuickForm/RuleRegistry.php`, `_getJsValue()`); with two inputs of one name that is a
   `RadioNodeList`, whose `.value` is empty for text inputs. So as soon as the visitor edits the code, a false
   "Required" appears under the field although it holds a value. The check that runs on submit does **not** block the
   press: it is handed `frm.elements['code']` itself, the `RadioNodeList`, which has no `name`, so
   `validate_<form>_code()` returns true without checking (`lib/formslib.php`, `getValidationScript()`). The message
   appears during the press (the field loses focus on mouse-down) and pushes the Verify button down, so it can make
   that first click miss: a likely reason a press seemed to do nothing, not confirmed in a browser. On the server the
   text input wins, because it comes after the hidden one, so the typed code is the one verified. (Mechanism read from
   the source and corrected in review on 2026-11-19; not yet seen in a browser.)

## Before (`admin/tool/certificate/index.php`, lines 50-51 and 60)

```php
$form = new \tool_certificate\verify_certificate_form($pageurl, null, 'post', '',
    ['class' => 'mt-3 mb-5 p-4 bg-light']);
...
$form->display();
if ($form->get_data()) {
```

## After (lines 50-55 and 63-67)

```php
// SENTIENTIA-CORE-MOD: post the form to the bare URL. Given $pageurl, formslib prints its ?code= as a second
// input named "code", and the client-side "required" check then reads an empty value. See
// moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md
$form = new \tool_certificate\verify_certificate_form(new moodle_url('/admin/tool/certificate/index.php'), null, 'post', '',
    ['class' => 'mt-3 mb-5 p-4 bg-light']);
// END SENTIENTIA-CORE-MOD
...
$form->display();
// SENTIENTIA-CORE-MOD: verify on arrival. A link that carries ?code= (certificate QR code, gallery "Verify", LinkedIn)
// shows the result without a second press of Verify. Read-only, same lookup as a submit; see the record named above.
if ($form->get_data() || ($code && !$form->is_submitted())) {
// END SENTIENTIA-CORE-MOD
```

Unchanged on purpose:

- `$PAGE->set_url($pageurl)` keeps the code, so the page URL and the links built from it (the language switcher,
  for one) keep it, and switching language shows the same result.
- `if ($code) { $form->set_data(['code' => $code]); }` still fills the field, so the form above the result shows the
  code that was checked.
- The capability check (`permission::can_verify()`, which uses the guest role for a visitor who is not logged in) runs
  before either branch, exactly as before. Anonymous verification works as it did.

## Why a patch, and why here

- Nothing outside the plugin can reach this. `index.php` is a page script: it has no hook, callback or renderer
  between reading the code and deciding whether to print a result. The theme already overrides the result template
  (`theme/sentientia/templates/tool_certificate/verify_certificate_results.mustache`, QW-CERT-VERIFY), but a template
  cannot run the lookup.
- A Sentientia-owned verification page would also work, but every printed QR code and every LinkedIn post already
  points at `/admin/tool/certificate/index.php`, including the 11,048 BizLMS-era codes that Stage B carries over. The
  URL has to keep working, so the fix belongs on that page.
- Each hunk changes one statement: smallest possible footprint.

## Branch logic

| Request | `get_data()` | `is_submitted()` | Result printed |
|---|---|---|---|
| GET `index.php` (no code) | null | false | none (as before) |
| GET `index.php?code=X` (QR, gallery, LinkedIn) | null | false | **yes (new)** |
| POST from the form, valid sesskey | data | true | yes (as before) |
| POST from the form, bad or missing sesskey | n/a | n/a | `invalidsesskey` exception in the form constructor (as before) |
| POST with a code but no form marker (`_qf__...`) | null | false | yes, the same read-only lookup as the GET |

`$code` comes from `optional_param('code', '', PARAM_ALPHANUM)`, which reads POST before GET, as before.

## Safety of verifying on GET

- **Read-only.** The arrival branch runs `\tool_certificate\certificate::verify($code)`: one parameterised SELECT
  joining `tool_certificate_issues` and `tool_certificate_templates`, then, for a matching code, the
  `\tool_certificate\event\certificate_verified` event. The plugin declares that event a read (`crud = 'r'`,
  `LEVEL_OTHER`). Its only write is the log-store row, the same kind of write as Moodle's `*_viewed` events, which core
  fires on GET (for example `course/view.php`). No observer listens to it: tool_certificate's `db/events.php` observes
  only `\core\event\course_content_deleted`, and no `db/events.php` under `local/`, `moodle-enhancement/local/` or
  `admin/tool/` names it (checked 2026-11-19), and the certificate health block, the certificate pages and the
  analytics plugin do not read it. The new PHPUnit test asserts that a
  valid and an invalid lookup, made as a visitor who is not logged in, change no row in either table and raise
  exactly one event, a read.
- **CSRF / sesskey.** Moodle requires a sesskey for state changes, not for reads. The arrival branch changes no state,
  so it takes none. The submit path is unchanged: moodleform still checks the sesskey whenever the form marker is
  posted (`moodleform::_process_submission()`). The most a forged cross-site request can do is add one "certificate
  verified" log line attributed to the victim, as an `<img>` pointing at any `*_viewed` page can today.
- **Escaping.** The hunks print nothing. `$code` is reduced to `[A-Za-z0-9]` by `PARAM_ALPHANUM` before any use. The
  field value is escaped by the form element template. The result is rendered by the same renderables and templates
  as a submitted check. Pre-existing and not changed here: upstream's result partial prints `{{{table}}}`, built by
  `html_writer::table()` from the issue's stored full name and the template name without `s()`. Those values come
  from the user record at issue time and from admins; see "Risks". Fixed the same day by a separate vendor hunk:
  `2026-11-19-tool-certificate-verify-result-escaping.md`.
- **Enumeration.** No easier than before: the POST path never had a rate limit, and anyone can get a sesskey from the
  page. Codes are 10 random digits plus the holder's initials.

## Behaviour change

- A link with a valid code shows the green "This certificate is valid" box and the details table straight away,
  logged out or logged in. An unknown code shows the "Not verified" box (with the theme's explanation) straight away.
- The form shows one "code" field, so editing the code on a page a link opened no longer shows a false "Required"
  under it (and no longer moves the Verify button during the press).
- Link previews (LinkedIn, WhatsApp, Slack unfurlers) and crawlers that fetch a verification link now receive the
  result, including the holder's name, and each fetch of a valid code logs a "verified" event as user 0. Before, they
  got an empty form. See "Risks".
- Nothing changes for `index.php` opened without a code.

## Version bump: none

`version.php` stays at 2025031804. The patch changes a page script only: no schema, capability, cache definition,
string, template or AMD module, so there is nothing for an upgrade or a cache purge to rebuild. PHP reads the file
on the next request (OPcache revalidates by timestamp in the default configuration; on a host with
`opcache.validate_timestamps=0`, reload Apache after the copy, which a version bump would not do either). Bumping it
would also add a hunk to `version.php`, which every vendor release changes, and would put tool_certificate in the
"upgrade pending" list for no schema work. Deploy with `--no-upgrade`.

## Tests (written, not run)

- `admin/tool/certificate/tests/sentientia_verify_on_arrival_test.php` (PHPUnit, `--group tool_certificate`; the
  group is a `#[Group]` attribute since 2026-11-19, because PHPUnit 11 ignored the class's `@group` doc comment beside
  its attributes, see the escaping record):
  1. `test_verification_link_form_has_one_code_field`: the form built with the link URL renders two `code` inputs
     (the defect); built with the bare URL and `set_data()`, one, holding the code; and it is not "submitted".
  2. `test_verify_on_arrival_is_read_only`: as a visitor who is not logged in, `permission::can_verify()` is true, a
     valid and an invalid lookup change no row in `tool_certificate_issues` or `tool_certificate_templates`, and the
     only event is one `certificate_verified` with `crud = 'r'` and user 0.
  3. `test_index_php_carries_the_patch`: a tripwire. It fails when a vendor import replaces `index.php` without the two
     hunks, so the importer re-applies them or drops them deliberately.
- `admin/tool/certificate/tests/behat/sentientia_verify_on_arrival.feature` (Behat): a logged-out visit with a valid
  code shows "Valid", the holder and the certificate, and no hidden `code` input; a logged-out visit with an unknown
  code shows "Not verified"; with JavaScript, a logged-in user on a page a link opened types another code, presses
  Verify and gets "Valid". The third scenario covers the submit path on such a page; it would also pass before the
  patch, because the submit-time check never blocked the press. The duplicate input itself is caught by the
  hidden-input assertion in the first scenario and by PHPUnit test 1.
- The existing `tests/certificate_test.php::test_verify` and `tests/behat/verify_certificates.feature` cover the
  unchanged submit path.

Run: from `C:\xampp\htdocs\moodle5\`, `vendor\bin\phpunit --filter sentientia_verify_on_arrival_test` after the
PHPUnit site is initialised; then the existing `--group tool_certificate` suite.

## Upgrade-safety (how it merges with a future vendor update)

- Two hunks of one statement each in `index.php`, a 65-line upstream file last changed in this repo by the vendor
  update in commit 82fee0969. They re-apply by hand in a minute. Find them with
  `git grep -n "SENTIENTIA-CORE-MOD" -- admin/tool/certificate/`.
- When a release is imported (with its `Core-Mods-Upstream-Sync: admin/tool/certificate/ <version>` line, README),
  first check whether upstream now verifies on GET or posts to the bare URL. If it does, take upstream, add a
  "Reverted" note here and delete the two Sentientia test files with the patch. If it does not, re-apply the hunks
  with their markers; the tripwire test fails until that is done.
- The two Sentientia-owned test files are new files with no upstream counterpart. A release copied over the
  directory keeps them; a release that replaces the directory deletes them, so restore them from git.
- Moodle 5.3: `moodle_url`, `moodleform::is_submitted()` and the `_pageparams` behaviour are the same as on 5.1.3+
  (checked in `D:/Claude Local/moodle53/moodle/public/lib/formslib.php`). Nothing under `lib/` is touched.
- Upstream contribution: the change is generic, so it is a candidate pull request to the plugin's upstream project.
  Not filed.

## The five tool_certificate vendor patches

| Record | File | Status |
|---|---|---|
| `2026-05-23-certificate-image-imageinfo-guard.md` | `element/image/classes/element.php`: non-image guard | Required on 5.3 |
| `2026-09-03-tool-certificate-5.2-reset-caches.md` | `classes/customfield/issue_handler.php`: `reset_caches(): void` | Required on 5.2 and 5.3 |
| `2026-10-08-tool-certificate-duration-defaultunit.md` | `classes/certificate.php`: `defaultunit` | Required on 5.3 |
| this record | `index.php`: verify on arrival, bare form action | Version independent (bug fix) |
| `2026-11-19-tool-certificate-verify-result-escaping.md` | `classes/output/verify_certificate_result.php`: escape the holder and certificate names | Version independent (bug fix) |

## Risks

1. **Holder's name in link previews and search results.** A crawler that fetches a verification link now gets the
   result page, including the holder's full name. The holder shares the link on purpose (LinkedIn) or prints it on
   the certificate, and anyone holding the code could always see the same result. But a search engine that crawls a
   public LinkedIn post may index the page. Open question for Nitin (raised in the step's return): add
   `noindex` for this page only, from the theme. **Resolved 2026-11-19:** Nitin approved it (15:05); the theme's
   `core_renderer::standard_head_html()` adds `<meta name="robots" content="noindex, nofollow" />` for the page type
   `admin-tool-certificate-index` only (`robots_noindex_meta()`, theme 2026111901 / 1.0.72-beta, test
   `theme/sentientia/tests/certificate_verify_noindex_test.php`). No vendor change. Link previews still show the
   result: noindex keeps the page out of search results, it does not stop a fetch.
2. **Log noise.** Each fetch of a valid code by a person or a bot adds one `certificate_verified` log row. The
   plugins checked above do not count these rows.
3. **Unescaped table cells (pre-existing).** Upstream's result table prints the stored full name and the template
   name without `s()`. The patch does not change that, but the table now renders on a plain GET. Open question for
   Nitin: escape the two cells in a further vendor hunk, or in the theme. **Resolved 2026-11-19:** Nitin chose the
   vendor hunk (15:05); see `2026-11-19-tool-certificate-verify-result-escaping.md`.
4. **Mechanism of the false "Required"** is read from the QuickForm source. No automated test shows it in a browser:
   the Behat `@javascript` scenario covers the submit path, which the duplicate input never blocked. Step 5 of "How
   to verify" checks it by hand (edit the code and leave the field: no "Required" appears).

## How to verify

Local XAMPP (after the deploy workflow that is running now finishes; copying into `moodle5\public\admin\` is denied
by `.claude/settings.json`, so the copy is Nitin's or needs his permission):

1. Copy `admin/tool/certificate/index.php` to `C:\xampp\htdocs\moodle5\public\admin\tool\certificate\index.php`.
   No upgrade, no purge needed.
2. Find a locally issued code: `SELECT code FROM mdl_tool_certificate_issues ORDER BY id DESC LIMIT 3` (read-only).
3. Logged out, open `http://localhost:8080/admin/tool/certificate/index.php?code=<code>`: green "This certificate is
   valid", details table, no "Required" under the field. View source: one `name="code"` input.
4. Logged out, `?code=NOTAVALIDCODE`: red "Not verified" with the theme's explanation, at once.
5. Logged in as a learner: My certificates (gallery) > Verify on a card: the result shows on arrival. Then type
   another code and press Verify: the new result shows (no "Required").
6. Open `index.php` with no code: the empty form, as before.
7. Run the PHPUnit and Behat tests above.

UAT (Nitin's call): `tools/uat/deploy_to_uat.sh --no-upgrade admin/tool/certificate/index.php` (dry run), then with
`--yes`, and repeat steps 3 to 6 on UAT with one of its 7 test certificates.

Owed by CLAUDE.md: desktop and mobile screenshots of steps 3 and 4 in `docs/visual-evidence/2026-11-19/`.

---

## Addendum 2026-11-20 (re-vendored to 5.0.12 on 2026-11-20)

- **Still required on 5.0.12.** Upstream 5.0.12 `index.php` builds the form with `$pageurl` and prints a result only
  `if ($form->get_data())` (tag v5.0.12; equal to UAT's copy fetched before the 19 Nov 15:15 deploy).
- **Re-applied** with the same markers and comment text. 5.0.12 writes the constructor call with one argument per
  line, so hunk 1 now wraps that 7-line statement and changes one line in it, the first argument, from `$pageurl` to
  `new moodle_url('/admin/tool/certificate/index.php')`; the other upstream lines are unchanged.

  ```php
  // SENTIENTIA-CORE-MOD: post the form to the bare URL. Given $pageurl, formslib prints its ?code= as a second
  // input named "code", and the client-side "required" check then reads an empty value. See
  // moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md
  $form = new \tool_certificate\verify_certificate_form(
      new moodle_url('/admin/tool/certificate/index.php'),
      null,
      'post',
      '',
      ['class' => 'mt-3 mb-5 p-4 bg-light']
  );
  // END SENTIENTIA-CORE-MOD
  ```

- **New line numbers:** hunk 1 at lines 50-60, hunk 2 (unchanged text) at lines 69-72. On 4.5.7 they were 50-55 and
  64-67; the "Before" and "After" sections above show the 4.5.7 file.
- **Tripwire:** `test_index_php_carries_the_patch()` now matches the call with
  `assertMatchesRegularExpression('~verify_certificate_form\(\s*new moodle_url\(\'/admin/tool/certificate/index\.php\'\),~', ...)`,
  so it holds for either layout. The marker count (2) and the condition assertion are unchanged.
- **Vendor release patched:** now tool_certificate 5.0.12 (`version.php` 2026111000). The "five tool_certificate
  vendor patches" table above is history: two of them are reverted (fixed upstream). Import record:
  `2026-11-20-tool-certificate-5.0.12-revendor.md`.
- **UAT:** UAT runs the 4.5.7-layout copy deployed on 19 Nov; the behaviour is the same. Deploy the 5.0.12-based file
  and the changed tripwire test with `--no-upgrade` on Nitin's go (decision D-8) so UAT equals the repository.

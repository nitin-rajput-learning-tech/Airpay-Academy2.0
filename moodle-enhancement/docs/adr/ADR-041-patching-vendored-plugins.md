# ADR-041 — Patching vendored plugins: tagged, recorded, tested hunks in third-party code we ship but do not own

- **Status:** Accepted 2026-11-19 (accepted by Nitin in chat, 15:05). Proposed earlier the same day; Nitin approved the first patch made under these rules as decision D32 of the post-demo plan (`docs/plans/2026-11-19-POST-DEMO-PLAN.md` §6a: "Patch tool_certificate as a recorded core-mod", as recommended); the plan reserved this ADR number for the rules (§5, ADR list).
- **Date:** 2026-11-19
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
- **Implementer:** Claude
- **Amends:** nothing. ADR-033 decision 4 (no edits to existing Moodle core files on 5.3) stands: a vendored plugin is not core. This ADR writes down the practice the four tool_certificate records and the learnerscript record already follow, and adds tests and an import procedure.
- **Related:** ADR-001 (lifts the "never modify core" rule, creates `docs/core-mods/`), ADR-002 (feature flags), ADR-033 (5.3 target; open question 4 on tool_certificate), `docs/core-mods/README.md` (record format, the CI gate, `Core-Mods-Upstream-Sync`).
- **Evidence:** `docs/core-mods/2026-10-08-tool-certificate-duration-defaultunit.md` (the precedent), `docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md` (the first patch made under this ADR) and `docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md` (the second).

---

## Context

Sentientia ships third-party plugins that it does not maintain: `tool_certificate` 4.5.7 (`admin/tool/certificate/`,
declared `supported = [400, 405]`) and the learnerscript family (`blocks/learnerscript/` and its report plugins).
They break or misbehave on our Moodle versions and in our flows, and some of the fixes can only be made inside them.

Six vendor patches exist so far, each with a record in `docs/core-mods/` (the last two made under this ADR):

| Record | Plugin | Why it could not live elsewhere |
|---|---|---|
| `2026-05-23-certificate-image-imageinfo-guard.md` | tool_certificate | Fatal inside an element class |
| `2026-09-03-tool-certificate-5.2-reset-caches.md` | tool_certificate | Method signature must match core's |
| `2026-10-08-tool-certificate-duration-defaultunit.md` | tool_certificate | Option key typo throws on 5.3 |
| `2026-10-08-learnerscript-modal-factory-port.md` | block_learnerscript | Core removed the modal factory |
| `2026-11-19-tool-certificate-verify-on-arrival.md` | tool_certificate | The decision is inside a page script |
| `2026-11-19-tool-certificate-verify-result-escaping.md` | tool_certificate | The table reaches the theme as finished HTML |

The four before this ADR were made consistently but under no written rule. The tags differ (`// SENTIENTIA-CORE-MOD (vendor): ...`,
`// SENTIENTIA-CORE-MOD (2026-05-23): ...`, `// SENTIENTIA-CORE-MOD: ...`), none has a test, and nothing says what to
do when a vendor release is imported. The core-mods gate (pre-commit CHECK 6 and `tools/ci/check-core-mods.sh`)
covers `admin/tool/certificate/` because it sits under the guarded `admin/`; it does not cover `blocks/`.

---

## Decision

### D1 — Patch a vendored plugin only when nothing outside it can reach the defect

Try, in order: a theme override (templates, renderers; `theme/sentientia/templates/<component>/`), a Sentientia
plugin (hooks, callbacks, observers), a Sentientia replacement page (only when no printed or shared URL depends on the
vendor page). A vendor hunk is the last resort, and the record says why the earlier options fail.

### D2 — Vendor hunks fix bugs and compatibility only

A vendor hunk restores what the vendor code meant to do (a defect) or keeps it running on our Moodle version. It
takes no feature flag (D32: "no flag: it is a bug fix"). A new behaviour, setting or screen goes into a Sentientia
plugin behind a flag (ADR-002), never into vendor code.

### D3 — Hunk shape

- Smallest change that fixes the defect: change the statement, not the function. No reformatting, renaming or
  refactoring of upstream lines around it. Keep the file's existing line endings.
- Open every hunk with `// SENTIENTIA-CORE-MOD: <reason>` (the comment syntax of the file type: `#` in Gherkin or
  shell, `{{! }}` in Mustache) and close it with `// END SENTIENTIA-CORE-MOD`. The reason says briefly what the hunk
  does and names the record file (a later hunk in the same file may point back to the first one's reference).
- The older forms (`(vendor)`, `(2026-05-23)`) stay as they are: the gates match the token `SENTIENTIA-CORE-MOD`, and
  rewriting them is churn in vendor files. That a patch is a vendor patch is said in its record and in the README
  index, not in the tag.
- No edits to the vendor's language files. New strings go into the theme or a Sentientia plugin, or into an
  additive file (as the Hindi pack record does).

### D4 — One record per patch, in `docs/core-mods/`

The README's required content, plus: the vendor component and the release patched (`version.php` version), a
"Version bump" section (D5), the upstream check for the next import (D7), and a row in the README index marked
"vendor patch". Files that the patch adds (tests) are listed by exact path, so the gate finds them.

### D5 — Do not bump the vendor's `version.php` unless the patch needs an upgrade step

A bump is needed only when the patch changes something that an upgrade or a cache purge rebuilds: schema,
capabilities, cache definitions, services, strings, templates or AMD modules. Otherwise leave it: a bump is a hunk in
the one file every release changes, and a number we invent can collide with the vendor's next one. When a bump is
unavoidable, record it, and at the next import confirm the vendor's version is higher than ours.

### D6 — Each new patch carries Sentientia-owned tests

- In the vendor plugin's own `tests/`, named `sentientia_<slug>_test.php` (and `tests/behat/sentientia_<slug>.feature`
  when page behaviour changes), with a marker header saying the file is ours and naming the record.
- When the hunk sits where a unit test cannot reach it (a page script), add a tripwire test that asserts the hunk is
  still in the file. A release that silently replaces the file then fails the suite.
- Back-filling tests for the four older patches is optional (open question 2).

### D7 — Importing a vendor release

1. Import with the README's `Core-Mods-Upstream-Sync: <dir>/ <version>` line in the same commit.
2. For each record of that component: if upstream fixed the defect, take upstream, add a "Reverted" note to the record
   (keep the record) and remove that patch's Sentientia tests. If not, re-apply the hunks with their markers.
3. Restore the Sentientia-owned test files if the import removed them.
4. Run the plugin's PHPUnit group (the tripwires included) and its Behat features on the target Moodle.

### D8 — Trees and deploy

Vendor plugins live in the top-level tree only (the package source), unless one has a `moodle-enhancement/` twin, in
which case both copies carry the same bytes (`tools/check-tree-drift.php`). Patched files deploy as named files
through `tools/uat/deploy_to_uat.sh` like any other change; with no version bump, use `--no-upgrade`.

---

## Consequences

- **Positive:** every vendor hunk is findable (`git grep SENTIENTIA-CORE-MOD`), justified, tested, and has a known
  path through the next import. A dropped patch fails a test instead of reappearing as a user-facing bug.
- **Negative:** a small patch costs a record and a test file. Sentientia files inside vendor directories can be lost
  by an import that replaces the directory (D7 step 3 covers it).
- **Neutral:** the gate already covers `admin/tool/certificate/`. `blocks/learnerscript/` stays covered by discipline
  only (open question 1).

---

## Alternatives considered

- **Fork each vendor plugin into a `sentientia_*` component.** Clean ownership, but it breaks every URL, capability
  and table name that printed certificates, shared links and the BizLMS data (11,048 issued certificate codes) depend
  on, and cuts us off from upstream fixes. Rejected.
- **Keep patches as `.patch` files applied at package time.** Keeps vendor trees pristine in git, but adds a build
  step, and a patch that stops applying fails late, at packaging. Rejected while there are five small patches.
- **Never patch; wait for upstream.** tool_certificate 4.5.7 does not support 5.x; waiting leaves user-facing bugs and
  a 5.3 fatal in place. Rejected; contributing fixes upstream is encouraged as well (open question 3).

---

## Implementation actions

1. Done 2026-11-19: `docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md`, its two hunks in
   `admin/tool/certificate/index.php`, its PHPUnit and Behat files, and the README index row.
2. Done 2026-11-19: `docs/core-mods/2026-11-19-tool-certificate-verify-result-escaping.md`, its hunk in
   `admin/tool/certificate/classes/output/verify_certificate_result.php`, its PHPUnit file
   (`tests/sentientia_verify_result_escaping_test.php`, with a tripwire) and the README index row. The companion
   noindex for the verification page went into the theme (D1), not the vendor plugin.
3. Done 2026-11-20 (re-vendored to 5.0.12 on 2026-11-20, decision D-7; also answers ADR-033 open question 4):
   tool_certificate 5.0.12 (`version.php` 2026111000, `supported = [500, 503]`), the release UAT runs, imported
   under D7 (`docs/core-mods/2026-11-20-tool-certificate-5.0.12-revendor.md`, which carries the
   `Core-Mods-Upstream-Sync: admin/tool/certificate/ v5.0.12 (2026111000)` line). Per record:
   - image guard (2026-05-23): re-applied, `element/image/classes/element.php` lines 29-30 and 222-235;
   - verify on arrival (2026-11-19): re-applied, `index.php` lines 50-60 and 69-72 (5.0.12 puts one constructor
     argument per line, so hunk 1 wraps the 7-line call); its tripwire now matches either layout;
   - result escaping (2026-11-19): re-applied, `classes/output/verify_certificate_result.php` lines 35-38 and 96-102;
   - reset caches (2026-09-03) and duration `defaultunit` (2026-10-08): reverted, fixed upstream, records kept with a
     "Reverted" note;
   - Hindi pack (2026-05-29): still staged; 13 new and 1 reworded English keys to translate before activation.
   The Sentientia test files were kept (D7 step 3). `pix/icon.png`, which 5.0.12 no longer ships, is listed in the
   import record and not deleted by the import script. Pre-commit CHECK 2 (MOODLE_INTERNAL guard) now skips files
   under a sync line staged with the commit, as CHECK 6 does, so the unguarded upstream class files can be committed
   without `--no-verify`. D7 step 4 (PHPUnit group and Behat on the target Moodle) and the UAT deploy of the 4 files
   that differ from UAT (`--no-upgrade`, D8, decision D-8) are open. The Context table's "4.5.7" is history.

---

## References

- `docs/core-mods/README.md`; `docs/core-mods/2026-10-08-tool-certificate-duration-defaultunit.md`;
  `docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md`
- `.claude/hooks/pre-commit.sh` CHECK 6; `tools/ci/check-core-mods.sh`; `.github/workflows/core-mods.yml`
- ADR-001, ADR-002, ADR-033

---

## Open questions for future ADRs

1. Extend the core-mods gate to a list of vendored plugin directories outside the guarded dirs (`blocks/learnerscript/`
   and its report plugins)?
2. Back-fill tripwire tests for the four earlier vendor patches?
3. Offer the generic fixes (the duration typo, verify on arrival) upstream as pull requests to the plugin projects?

# Gap-build local test pass — report (2026-06-17)

**Branch:** `claude/gap-integration` (HEAD `6aa08a94d` at report time)
**Scope:** local XAMPP only (`C:\xampp\htdocs\moodle5\public`). **Nothing deployed to live.**
**Outcome:** Yield/report-only — a concurrent session was detected editing the same
branch + Moodle instance, so **no commits were made**. All fixes below are either
applied uncommitted in the working tree or supplied as ready-to-apply patches.

---

## 0. IMPORTANT — concurrency caveat (read first)

During this pass the working tree + Moodle DB were being modified by **another active
session** doing the same task:

- HEAD advanced under me through real commits I did not make (`f2242e9a1` → `3b06793dd`
  → `6aa08a94d`), the last being `feat(authoring): ship scoped "Sentientia Author"
  system-context role`.
- `local/sentientia_content_market/index.php` is modified in the working tree by that
  session.
- The PHPUnit test DB schema (`phpu_user.open_*` columns) and the webroot deploy
  flapped between my runs.

Additionally, **my own parallel diagnostic agents over-stepped** their "diagnose only"
brief and applied several fixes to disk + redeployed + re-ran (velocity_calculator,
the talent/enrolment/audience tests, `bizlms_fixture`). So the working tree now holds
an intermingled set of edits. **Reconcile before committing.** Per-file attribution is
in §6.

---

## 1. Per-plugin PASS/FAIL

"Clean baseline" = the controlled run (redeploy HEAD → upgrade → reinit → all 9 suites,
report dir `reports/phpunit-clean-20260617/`). "After fixes" = projected once the
applied + pending fixes in §4/§5 are all in place.

| # | Plugin | Type | Clean baseline | After fixes | Blocking work remaining |
|---|--------|------|----------------|-------------|--------------------------|
| 1 | local_sentientia_skillsai | NEW | **PASS** 43t/191a | PASS | none (deprecation notices only) |
| 2 | local_sentientia_authoring | NEW | **PASS** 55t/103a | PASS | none |
| 3 | local_sentientia_api | NEW | **PASS** 24t (8 skipped) | PASS | none |
| 4 | local_sentientia_assistant | EXTEND | FAIL 1f | **PASS** | my privacy test assertion (applied) |
| 5 | local_sentientia_talent | NEW | FAIL 3f | **PASS** | assertSame→assertEquals (applied) |
| 6 | local_sentientia_analytics | EXTEND | FAIL 1f | **PASS** | predictive at-risk int-cast (applied) |
| 7 | local_sentientia_xapi | NEW | FAIL 1f | PASS *after patch* | timestamp validator (PENDING, §5) |
| 8 | local_sentientia_content_market | NEW | FAIL 1f | PASS *after patch* | retire_missing SQL (PENDING, §5) |
| 9 | local_sentientia_learningpath | EXTEND | ERRORS 8e/8f | PASS *after patch* | quiz_score decimals (PENDING, §5) |

**Bottom line:** 6/9 are green or green-with-applied-fixes. 3 plugins (xapi,
content_market, learningpath) need the 3 pending patches in §5 to reach all-green.
All 19 learningpath failures are explained; most fixed.

---

## 2. Deploy + upgrade

- 6 new plugins installed, 3 extended upgraded; **133 `mdl_local_sentientia_*` tables**.
- Dependencies satisfied (`platform` 2026052801, `skills` 2026052003).
- DB upgrade clean; **no gap-plugin errors** in the Apache log — only correct
  `required_capability` denials (the capability gates working) and pre-existing,
  unrelated BizLMS noise (`local/costcenter:*`, `local/classroom:*`,
  `block/trainerdashboard:*`, `sentientia_org` org-head).

---

## 3. Static compliance audit (9 plugins, adversarially verified)

All 9 audited against the CLAUDE.md hard rules (flag-default-OFF, tenant scoping,
mock-mode default, output escaping, $DB-API-only, privacy provider, en/hi parity).

| Plugin | Flag OFF | Tenant | Mock | Escape | $DB | Privacy | Parity |
|--------|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| skillsai | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 149/149 |
| authoring | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 181/181 |
| learningpath | ✅ | ✅ | n/a | ✅ | ✅ | 🔴→fixed | 95→97/97 |
| content_market | ✅ | ✅ | ✅ | ✅ | ✅ | n/a | 67/67 |
| analytics | ✅ | 🔴→fixed | n/a | ✅ | ✅ | ✅ | 44/44 |
| assistant | ✅ | ✅ | 🔴 legacy | ✅ | ✅ | ✅ | 82/82 |
| xapi | ✅ | 🔴→fixed | n/a | ✅ | ✅ | ✅ | 80/80 |
| talent | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | 85/85 |
| api | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | 49/49 |

---

## 4. Fixes applied in the working tree (uncommitted)

### My deliberate fixes (security/correctness/compliance)
1. **learningpath privacy provider** — pointed at the non-existent `local_airpay_lp_users`;
   rewritten to the real `local_sentientia_learningpath_users` (cols incl. `timecompleted`)
   + `local_sentientia_lp_adaptive_log` across all 7 provider methods. (DPDP: export/erase
   previously silently no-op'd.)
2. **learningpath install.xml** — Bug A fresh-install gap: added `lp_adaptive_log` table +
   `adaptive_mode`/`score_threshold_*` + `is_remedial`/`is_accelerator`/`remedial_for_courseid`
   (were upgrade.php-only → absent on fresh installs / PHPUnit). *Same class as the
   `project_gapbuild_xmldb_ddl_bugs` memory.*
3. **learningpath en/hi lang** — added `privacy:metadata` (EN was missing it) + `:lp:timecompleted`
   to both → parity 95→**97/97**.
4. **analytics roi_calculator** — `active_courses` count now tenant-scoped on `course.open_path`
   (was site-wide → mixed tenants into a per-tenant ROI).
5. **xapi index.php** — LRS viewer now **fails closed** when a non-admin's tenant can't be
   resolved (was `costcenterid=0` → `get_statements(0,…)` returns ALL tenants' actor PII).
6. **content_market** — `idx_provider_ext` now `(provider, external_id, costcenterid)` +
   `upsert_item()` matches on costcenterid + version bump + upgrade migration (same external
   course can now coexist per tenant).
7. **New privacy PHPUnit tests** — assistant + learningpath (export+delete+get_contexts;
   regression guards). Used `assertContainsEquals` for context-id (loose, Moodle returns ids
   as strings).

### Applied by my diagnostic agents (verify before keeping)
- **talent** `talent_manager_test.php` L166/217/223/258 `assertSame`→`assertEquals`.
- **learningpath** `velocity_calculator.php` `if ($expected < 0.5)`→`<= 0` (was nulling the
  legitimate early-completion cap case).
- **learningpath** `enrolment_window_test.php` L43/117 `assertSame(FORMAT_HTML,(int)…)`→
  `assertEquals(FORMAT_HTML,…)` (FORMAT_HTML is the string `'1'`).
- **learningpath** `adaptive_journey_test.php` skills-gap precondition: assert the
  `sentientia.skillsai.enabled` flag is OFF instead of "skillsai not installed" (invalid in
  the integration build).
- **learningpath** `path_assignment_test.php` reorder expected values 1/2→0/1 (production
  dense-packs members; non-members ignored — test literals were wrong).
- **learningpath** `external/list_paths_test.php` assert on `$e->errorcode==='filterstoolong'`
  not the localized message.
- **learningpath** `audience_enroller_test.php` restored `use bizlms_fixture` +
  `ensure_bizlms_schema()`; **companion edit** to `sentientia_org/classes/test/bizlms_fixture.php`
  adding `open_region`/`open_employmenttype`/`open_grade`/`open_hrmsrole` (Bug B: vanilla
  `phpu_user` lacks BizLMS columns).
- **analytics** `predictive_engine_test.php` cast expected ids to `(int)` for the strict
  `assertContains`/`assertNotContains`.

---

## 5. PENDING patches (NOT applied — apply during reconciliation)

### 5a. xapi timestamp validator (code bug)
`local/sentientia_xapi/classes/validator/statement_validator.php` — `createFromFormat`
overflows impossible dates (month 99 → valid object + warning), so invalid timestamps pass.
```php
        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $ts)
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $ts)
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:sP', $ts);
        // createFromFormat() overflows out-of-range components into a valid
        // DateTime and only flags a warning, so reject warnings/errors too.
        $parseerrors = \DateTime::getLastErrors();
        if ($dt === false
                || ($parseerrors !== false
                    && ($parseerrors['warning_count'] > 0 || $parseerrors['error_count'] > 0))) {
            $this->errors[] = get_string('validate_timestamp_format', 'local_sentientia_xapi');
        }
```

### 5b. content_market retire_missing (code bug)
`local/sentientia_content_market/classes/market_aggregator.php` — single-element seen-set
makes `get_in_or_equal` emit `= :eid1`, and the hand-built `NOT $insql` becomes invalid
`NOT =` SQL → caught → sync returns 'failed'. Let `get_in_or_equal` negate (pass `false`):
```php
        [$insql, $params] = $DB->get_in_or_equal($seen_ids, SQL_PARAMS_NAMED, 'eid', false);
        ...
                AND external_id $insql",   // was: external_id NOT $insql
```

### 5c. learningpath quiz_score / velocity_score decimals (schema bug)
`local_sentientia_lp_adaptive_log.quiz_score`/`velocity_score` are `DECIMAL(6,0)` (zero
decimals) — a 72.5 quiz score rounds to 73 (`test_log_row_carries_correct_costcenterid`:
"73.0 is identical to 72.5"). Fix in **install.xml** (for fresh/PHPUnit) AND add an
upgrade migration + version bump (for existing installs), since the original `upgrade.php`
created them with 0 decimals:
```xml
<FIELD NAME="quiz_score"     TYPE="number" LENGTH="6" DECIMALS="2" NOTNULL="false"/>
<FIELD NAME="velocity_score" TYPE="number" LENGTH="6" DECIMALS="2" NOTNULL="false"/>
```
```php
// db/upgrade.php — new step, bump version.php to 2026061601:
if ($oldversion < 2026061601) {
    $table = new xmldb_table('local_sentientia_lp_adaptive_log');
    foreach (['quiz_score', 'velocity_score'] as $col) {
        $f = new xmldb_field($col, XMLDB_TYPE_NUMBER, '6', null, null, null, null);
        $f->setDecimals(2);
        if ($dbman->field_exists($table, $f)) { $dbman->change_field_precision($table, $f); }
    }
    upgrade_plugin_savepoint(true, 2026061601, 'local', 'sentientia_learningpath');
}
```

---

## 6. Flagged for your decision (NOT applied) — assistant legacy chat mock-mode

`local/sentientia_assistant/classes/ai_client.php::ask()` (the **legacy** nav Q&A, pre-dates
the gap build) POSTs to `api.anthropic.com` whenever an `api_key` is set — **no mock default,
no live_api flag**, unlike the new agentic copilot path. This collides with the CLAUDE.md
"AI/TTS stay in mock by default" rule, **but** the fix changes existing production default
behaviour (chat would go mock until a new flag is flipped), which is why I did **not** apply
it. Ready patch: register `sentientia.assistant.live_api` (default OFF) in
`db/feature_flags.php` + a `call_mock()` branch in `ask()`. No live spend occurred during
testing regardless (no key, flags OFF).

---

## 7. Not done (and why)

- **Smoke test (runbook §4 — learner, desktop+590px, flag-by-flag)**: not run. The shared
  environment was non-deterministic (concurrent session churning the webroot/DB), and you
  chose to yield. The tooling is ready: `enable_gap_flags.php` (mock-mode, reversible) +
  persona `fatma.khamis@airpay.tz` / `qa_learner` (pw `[QA-PASSWORD - see local creds sheet]`).
- **PROJECT-STATE.md update + commit + screenshots**: skipped per the no-commit/report-only
  decision. No UI files were changed by me, so no visual evidence is owed.

---

## 8. Recommended next steps

1. Decide ownership with the other session (avoid double-committing the same fixes).
2. Apply the 3 pending patches in §5 + the assistant decision in §6.
3. Re-run all 9 suites once, **single session only**, from `moodle5/` dirroot:
   `vendor\bin\phpunit --testsuite local_sentientia_<plugin>_testsuite`.
4. Then the learner smoke test per runbook §4 (flags via `enable_gap_flags.php`, revert with
   `FLAG_OFF=1`).
5. Keep all new feature flags default OFF; AI/TTS in mock-mode for any local verification.

### Runbook/tooling fixes also worth folding in
- `Run-GapTests.ps1`: em-dash on line 53 breaks Windows PowerShell 5.1 parsing; and it points
  `phpunit` at `public\vendor\bin\` — in this 5.1 split layout phpunit lives at the **dirroot**
  (`moodle5\vendor\bin\phpunit`, `moodle5\phpunit.xml`) and `init.php` at
  `public\admin\tool\phpunit\cli\init.php`.
- Add a **fresh-install smoke** to the rollout gate (Bug A + the decimals bug are both
  fresh-install/upgrade-only defects that a real install or PHPUnit catches but a static
  check does not).

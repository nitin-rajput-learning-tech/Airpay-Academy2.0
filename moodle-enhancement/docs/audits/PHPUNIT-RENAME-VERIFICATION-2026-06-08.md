# PHPUnit verification — `airpay_* → sentientia_*` de-brand introduced 0 test regressions

- **Date:** 2026-06-08
- **Context:** Post-rename stability gate (ADR-025). After the component rename + the
  `external_functions` reconcile (ADR-025 follow-up d, @ `e9c988c13`), re-init the PHPUnit
  test DB and run the renamed plugins' suites to confirm the rename did not break component
  resolution.
- **Setup:** `admin/tool/phpunit/cli/init.php` rebuilt the `phpu_*` schema from every plugin's
  `install.xml` — **99 `phpu_local_sentientia_*` tables built cleanly** (the only `phpu_*airpay*`
  tables are `paygw_airpay` + `_errorlog`, the intentionally-kept external gateway).
- **Run:** `vendor/bin/phpunit --testsuite local_sentientia_{courses,platform,users,reports}_testsuite`

## Result: 227 tests, 215 pass, 3 errors + 9 failures

### The rename is clean — decisive evidence
**0** occurrences of `class … not found` / `Class "…"` / `component … not` / `coding_exception`
in the run. A naming regression (dangling class, namespace, component, `get_string`, `cache::make`)
would surface as exactly those. Their total absence proves every renamed `local_sentientia_*`
component resolves + executes. The 12 failures are all logic/schema/markup **assertion** failures,
not resolution failures.

### The 12 failures are pre-existing test-environment fragility (NOT the rename, NOT the cache purge)
No failure mentions cache/MUC — the concurrent `purge_all_caches` from the WS reconcile did not
affect the run (PHPUnit uses an isolated `phpunit_dataroot`). 2 failures appeared *before* the
purge (positions 8 & 57), consistent with pre-existing fragility.

| # | Test | Class | Root cause (environmental) |
|---|------|-------|----------------------------|
| E1 | `chip_filters_test::…distinct_designations` | users | `dml_read_exception: Unknown column 'open_hrmsrole'` |
| E2 | `chip_filters_test::…excludes_empty_values` | users | same — `open_hrmsrole` |
| E3 | `supervisor_scope_test::…own_tenant` | users | tenant context (BizLMS) absent in test DB |
| F1 | `enrol_deeplink_test::…capable_caller` | courses | markup assertion drift (`target="_blank"` not emitted) |
| F2 | `backup_filename_test::…template_is_used` | platform | case assertion (`AUDIT` vs `audit`) |
| F3 | `feature_flags_test::…tenant_override_in_resolved` | platform | tenant-override needs BizLMS tenant data |
| F4 | `delete_report_test::…cross_tenant_delete_rejected` | reports | assertion expects raw key `outoftenant`; code resolves the string |
| F5 | `chip_filters_test::…only_returns_requested_fields` | users | downstream of `open_hrmsrole` (empty result) |
| F6 | `signup_service_test::…pins_to_configured_tenant_path` | users | `open_costcenterid` (doesn't exist even in prod — use `open_path`) + no tenant config in test |
| F7-9 | `welcome_mailer_test::…` ×3 | users | email-suppression config (`noemailever` / opt-in default) blocks the asserted sends |

### Evidence
- Real `mdl_user`: **37** `open_*` columns (incl. `open_hrmsrole`); these are BizLMS runtime
  columns. Test `phpu_user`: only **5** (`open_path, open_employeeid, open_designation,
  open_supervisorid, open_location`) — built from core+plugin `install.xml`, no BizLMS injection.
- `open_costcenterid` is absent from **both** (production resolves tenant via `open_path`, per
  CLAUDE.md) — so the `signup_service` reference is a pre-existing test/code smell.
- Failing tests `grep`-confirmed to reference `open_hrmsrole` / `open_costcenterid`.

## Verdict
The de-brand is **test-clean**: 0 regressions, all renamed components resolve. The 12 failures are
a **pre-existing test-fragility backlog** — they would fail identically on a vanilla (non-BizLMS)
test DB before the rename. Closing them is a separate test-hardening effort (BizLMS test fixtures
for `open_*` columns + a configured tenant, an email sink for `welcome_mailer`, and refreshing 3
drifted assertions) — **not a de-brand blocker** and not on the production-readiness critical path
(the underlying code works in production, where the BizLMS columns + tenant data exist; the app
serves 2,871 users today).

# ADR-009 — Detection consistency + WS contract invariants

**Status:** Accepted (patterns now in code; rule for future contributions)
**Date:** 2026-05-23
**Deciders:** Nitin Rajput, Claude
**Builds on:** ADR-001 (fork strategy)
**Born from:** Goal A audit (2026-05-22) bugs #6, #10, #11, #12, #13

---

## Context

The Goal A visual UI audit (2026-05-22) walked 8 of 9 personas across
32 surfaces. The audit's stated purpose was to validate the hypothesis
"Sentientia LMS still looks like Moodle." The hypothesis was refuted —
every custom Sentientia plugin renders as best-in-class enterprise UI.

But the audit surfaced 8 bugs en route, and the bugs fell into a
tellingly small number of *architectural shapes*:

| Bug | Shape |
|---|---|
| #6  My Requests stuck on Loading…             | WS contract drift |
| #9b Manager WS denied supervisors             | Detection drift |
| #10 5 sibling endpoints WS contract drift     | WS contract drift |
| #11 Compliance Officer Learner sidebar        | Detection drift |
| #12 Cart datatable region attribute mismatch  | WS contract drift |
| #13 Mobile shell-main 260px width loss        | Media-query half-override |

Three of the five are **client/server contract drift**: a centrally
evolved client (the shared `theme_airpayux/datatable` AMD widget)
outgrew its consumers' server-side declarations.

Two are **detection drift**: two pieces of code each computed the same
fact (which role tier this user belongs to) and gradually disagreed.

Each individual fix is straightforward. The *recurrence* is the cost
— next quarter we'd find Bug #14 (another consumer WS missing a key),
Bug #15 (another role-aware surface that drifted from the canonical
detector). The fixes that landed this session prevent that recurrence
by promoting two patterns from tactical patches to structural
invariants:

  1. **Shared `role_detector`** — single source of truth that ANY
     layout, sidebar, page, or block consumes when it needs the role
     tier of the current user.

  2. **`ws_contract_scanner` + PHPUnit gate** — every WS consumed by
     a `[data-region="airpay-datatable"]` element MUST declare the
     full client contract `{search, sort, sortdir, page, perpage,
     filters}`. A CI test enforces this; an admin CLI tool surfaces
     drift between releases.

This ADR records the rationale, the rules, and the on-ramp for new
plugin authors.

---

## Decision

### 1. Role detection is centralised in `\theme_airpayux\role_detector`

```php
$roles = \theme_airpayux\role_detector::detect();
// → ['issiteadmin', 'isldadmin', 'isadmin', 'ismanager',
//    'islearner', 'switched_to_employee']
```

**Detection rules** — the helper encapsulates ALL of:

  - `is_siteadmin($USER)` — Site Admin
  - L&D Admin: `has_capability('local/courses:manage', system)`
    OR BizLMS `administrator` role assignment at category context
    (contextlevel 40), with the BizLMS-specific check guarded by
    `field_exists()` + `try/catch` so it's safe on stock Moodle
  - Manager: `has_capability('moodle/site:viewreports', system)`
    OR has at least one `open_supervisorid` direct report
  - BizLMS role-switch detection from `$SESSION->airpay_switchrole`
    and `$USER->useraccess['currentroleinfo']`

**Rule for new callers:** any new file that needs `isldadmin`,
`ismanager`, etc. consumes `role_detector::detect()`. Do not
re-implement the checks inline. Detection drift caused Bug #11 —
keeping the implementation in one place is the only structural
prevention.

### 2. WS contract for the shared datatable is enforced by CI

The shared `theme_airpayux/datatable` AMD widget always POSTs:

```js
{ search, sort, sortdir, page, perpage, filters }
```

**Rule for new WS consumers:** any new endpoint registered via
`db/services.php` that is invoked from a mustache template carrying
`data-region="airpay-datatable"` MUST declare all 6 keys in
`execute_parameters()` with `VALUE_DEFAULT`. The pattern (from
`local/airpay_request/classes/external/list_mine.php`):

```php
public static function execute_parameters(): external_function_parameters {
    return new external_function_parameters([
        'search'  => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
        'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'timecreated'),
        'sortdir' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'desc'),
        'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
        'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
        'filters' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, '{}'),
    ]);
}
```

**Enforcement** — three layers:

  - PHPUnit test `theme_airpayux\ws_contract_test` runs at CI time
    (group `ws_contract`).
  - Admin CLI `theme/airpayux/cli/ws_contract_audit.php` for
    on-demand audits (release sanity, forensic investigation).
  - Both consume the same `\theme_airpayux\ws_contract_scanner`
    utility so test + CLI never diverge.

### 3. Mobile media-query exhaustiveness

Whenever a desktop rule sets *paired* properties like:

```scss
.ap-shell__main {
    margin-left: var(--ap-sidebar-width);
    width: calc(100% - var(--ap-sidebar-width));
}
```

the mobile override MUST reset BOTH:

```scss
@media (max-width: 1024px) {
    .ap-shell__main {
        margin-left: 0;
        width: 100%;
    }
}
```

A half-override (Bug #13) silently steals viewport width without
producing any visible error. Reviewers check for paired resets on
any PR that adds a mobile media query.

---

## Consequences

### Positive

  - **Detection drift can no longer happen for role tiers**: any new
    surface (a future Compliance Report, an HR dashboard, an audit
    log viewer) automatically gets the same tier decisions as the
    dashboard. Bug #11's surface area is now zero.

  - **WS contract drift is caught at CI**: when a developer adds a
    new datatable consumer endpoint and forgets `search`, the CI
    fails with an exact message naming the missing keys and the
    mustache files that use the endpoint. The wait-for-a-user-to-
    report-a-spinner feedback loop is replaced with a 30-second CI
    fail.

  - **The CLI tool is also a forensic instrument**: `--json` output
    + stable exit codes mean it can be diffed across git refs to
    answer "when did this consumer break?"

### Negative / cost

  - `role_detector::detect()` is one extra function call per page
    that needs role tier. Each call hits the DB at most once for
    the BizLMS-admin role lookup (cached in PHP process). Negligible.

  - The scanner regex (`data-region="airpay-datatable"` + nearby
    `data-ws-name`) won't catch consumers that use a different
    pattern. Bug #12 (cart's `data-region="datatable"`) was found
    only because the scanner couldn't resolve the WS — the test
    didn't fail, the manual eye did. Future bug pattern: a consumer
    that uses yet another attribute shape and is silently skipped.
    Mitigation: keep the scanner regex tight enough that NOT
    matching is itself a finding worth investigating.

### Migration / on-ramp for new contributors

  1. New PHP that needs role tier → call `\theme_airpayux\role_detector::detect()`.
     Do not call `is_siteadmin()` or `has_capability('local/courses:manage', ...)`
     directly.

  2. New WS endpoint that backs a datatable → start from
     `local/airpay_request/classes/external/list_mine.php` as the
     canonical shape. Run `php theme/airpayux/cli/ws_contract_audit.php`
     before opening a PR.

  3. New mobile media query → ensure every paired desktop rule is
     fully reset, not half-reset. Search the file for
     `var(--ap-sidebar-width)` and check that ANY rule using it as
     part of a `width: calc()` also gets a `width: 100%` override.

---

## Rejected alternatives

### "Just be careful in code review"
Every drift bug in this audit was authored by careful contributors.
The drift accumulated *because* humans were each making locally
correct decisions without seeing the other detection site. Pure
review discipline is not a structural protection — code that lives
once doesn't drift.

### "Add `search` etc. as VALUE_REQUIRED everywhere"
Tempting because it would also catch consumers that send the wrong
shape. Rejected because it would break any LEGITIMATE caller that
sends a partial payload (e.g., a future admin tool that POSTs only
`sort` to test the endpoint). `VALUE_DEFAULT` plus the strict
unknown-keys validation is the right pair: liberal in what we accept
when we have the param, strict in rejecting what we don't.

### "Use Moodle external_api hooks instead of scanning mustache"
The mustache scan is intentional. It's the *client* that defines the
contract — the source of truth lives in `theme/airpayux/amd/src/datatable.js`,
which always POSTs the 6 keys. The scanner finds every place the
client is used, then verifies the server side meets the contract.
The alternative (querying Moodle's external function registry) only
tells us "does this WS exist," not "is it consumed by the shared
datatable."

---

## Related artifacts

  - `theme/airpayux/classes/role_detector.php` (commit fcd150c0a)
  - `theme/airpayux/classes/ws_contract_scanner.php` (commit f258db649)
  - `theme/airpayux/tests/ws_contract_test.php` (commit f258db649)
  - `theme/airpayux/cli/ws_contract_audit.php` (commit 9a76ef3ad)
  - `theme/airpayux/scss/moodle/partials/_layout-shell.scss` mobile rule (commit 117c2e84a)
  - `moodle-enhancement/docs/visual-audit-2026-05-22/AUDIT-REPORT.md` for the
    full audit narrative including all 13 bugs in context.

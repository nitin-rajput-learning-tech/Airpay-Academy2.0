# QA Walk — Persona: Site Admin (qa_siteadmin)

**Env:** local XAMPP, logged in as qa_siteadmin (added to `$CFG->siteadmins`; `admin/search.php` → 200 = full access).
**Method:** orchestrator drove login + dashboard capture; **Sonnet subagent** drove the read-only breadth walk
against the shared authenticated browser (26-URL `fetch()` probe + 2 screenshots + console check).
**Delegation result:** ✅ OK end-to-end — validates the subagent approach for the remaining personas.

## Surfaces probed (26) — all reachable as siteadmin

Healthy (HTTP 200, no error markers): `/admin/search.php`, `/admin/user.php`, `/admin/roles/manage.php`,
`/admin/roles/assign.php?contextid=1`, `/admin/plugins.php`, `/admin/settings.php?section=optionalsubsystems`,
`/admin/tool/task/scheduledtasks.php`, `/report/log/index.php`, `/report/configlog/index.php`, `/cohort/index.php`,
`/admin/settings.php?section=manageauths`, `/admin/settings.php?section=frontpagesettings`, and the airpay/sentientia
admin landings: `airpay_reports`, `airpay_manager` ("My Team — Learning Dashboard"), `airpay_compliance_report`,
`airpay_analytics`, `airpay_catalog`, `airpay_skills` ("Skills Matrix"), `airpay_learningpath`, `airpay_evaluation`,
`sentientia_live`, `sentientia_leaderboard`. **Zero `error`-level console messages** on the interior page checked.

## Findings

### SA-02 (P2) — FIXED ✅
`/admin/settings.php?section=themesettingairpayux` threw `sectionerror`. Root cause: `theme/airpayux/settings.php:27`
registered the settings page under the leftover **parent-theme** section name `themesettingepsilon`, while Moodle's
theme selector auto-generates the "Settings" link as `themesetting<themename>` = `themesettingairpayux`. Renamed to the
Moodle convention (cf. Boost→`themesettingboost`). Stored settings keyed by `theme_airpayux/*` are unaffected.
**Verified:** section now → HTTP 200. (Tagged `SENTIENTIA-CORE-MOD` in source with rationale.)

### SA-04 (P2?) — OPEN QUESTION (needs Nitin's decision)
`/course/management.php` returns 200 but redirects to `/local/airpay_catalog/index.php` **for site admins too**.
The airpay_catalog storefront intercepts Moodle's native course/category management. Consequence: native
**category management, bulk course operations, and restore-into-category** are not reachable at the canonical URL
for an admin. **Is this intended?** If yes, admins likely need an explicit "native management" escape hatch; if no,
the redirect should be gated to exclude `moodle/category:manage` / siteadmin. (Interceptor not traced this pass —
fast follow if you want it changed.)

### SA-03 — NOT A BUG
`/local/sentientia_recommendations/index.php` 404s, but the plugin is a backend+CLI scaffold (no `index.php`) and
**nothing links to it** (grep clean). No user impact. Optional: add a stub `index.php` that redirects to the
dashboard if a nav entry is ever added.

### SA-05 (P3) — cosmetic
The (now-reachable) theme settings page title reads **"Epsilon"** — an un-rebranded `configtitle` fork string.
Branding leak; matters for the white-label product. Lang-string fix, deferred.

## Screenshots
- `siteadmin-01-dashboard.png` (admin dashboard, dark mode)
- `siteadmin-02-adminsearch.png` (Site administration search) — captured by subagent
- `siteadmin-03-coursemgmt.png` (course mgmt → catalog redirect landing) — captured by subagent

## Notes
- SA-01 (`/admin/course/index.php` 404) was a bad probe URL (not a Moodle 5.x route) — dismissed.
- Read-only walk: no settings changed, no forms submitted, no destructive actions.

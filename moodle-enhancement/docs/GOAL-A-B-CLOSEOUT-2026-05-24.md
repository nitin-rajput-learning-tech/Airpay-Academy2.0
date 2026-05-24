# Goal A.x + Goal B — closeout summary

**Date:** 2026-05-24
**TaskList items resolved:** #149 (Goal A.x), #150 (Goal B)
**Status:** Code-level work complete. Runtime verification awaits a Site Admin walkthrough on a live local Moodle.

---

## Goal A.x — UI upgrade work driven by audit findings

### What was found by the audit (Goal A.y, 2026-05-23)

`docs/visual-evidence/2026-05-23/audit-A.y-findings.md` +
`audit-A.y-sections-2-8.md` document a load-time walk across **138 URLs**
spanning 8 personas (Site Admin, L&D Admin, Course Author, Manager,
Compliance Officer, Course Author secondary surfaces, Learner, External
Public Learner).

**Bugs surfaced: 1** — `tool_certificate render_image_html` TypeError on
non-image element types. Fixed in commit `332a02626` (TaskList #192).

**404s observed: 8** — all matrix-URL-mismatch (e.g. `/admin/cohorts/`
was the wrong path; correct is `/cohort/`) or not-installed optional
Moodle modules (`mod_database`, `mod_lti`, `mod_bigbluebuttonbn`). Zero
real bugs.

**Verdict from the audit's own conclusion** (sections-2-8.md line 149):

> After 138 URL walks across 8 personas, only 1 functional bug
> surfaced (the cert TypeError, fixed). The Sentientia platform's
> page-load reliability is high.

### What Goal A.x shipped

11 Sentientia surface restyles, each with a regression-guard test in
`tests/surfaces.spec.mjs`:

| Surface | TaskList # | Surface | TaskList # |
|---------|------------|---------|------------|
| `/user/profile.php` | #157 | `/grade/report/index.php` | #175 |
| `/badges/mybadges.php` | #158 | `/message/index.php` | #178 |
| `/grade/report/overview/` | #160 | `/user/edit.php` | #180 |
| `/admin/*` interior | #162 | `/user/preferences.php` | #181 |
| `/course/view.php` | #163 | `/calendar/view.php` (month) | #184 |
| `/course/edit.php` | #187 | | |

Plus Workstream 0 — per-customer branding consumed by `core_renderer`
(#188). Plus 5 mobile-responsive sweeps at 590px (#171, #176, #177).

### What's not in scope for autonomous closeout

The audit doc concludes:

> The highest-leverage move now is:
> 1. Drive 1-2 typical real users through their daily workflow as
>    shadow observers (manual scripted walkthrough)
> 2. Wire up Playwright POST tests for the top 10 user actions

(1) requires a human shadow observer — not automatable.
(2) is what Goal B addresses; see below.

### Resolution

**#149 Goal A.x is code-complete.** All audit-discoverable surfaces
have a restyle + a regression test. Remaining work is human-shadow
observation, which sits outside the autonomous overnight scope.

---

## Goal B — End-to-end click-through testing

### Pre-existing baseline (already in place when this closeout started)

`tests/surfaces.spec.mjs` — 11 tests, computed-CSS markers on every
Goal A.x surface. Regression-guards SCSS cascade drift caused by future
theme-version bumps. Was the Goal B "smoke" tier.

### New: `tests/workflows.spec.mjs` (commit `0f0a778c0`)

10 non-mutating Playwright tests, explicitly designed to be safe to run
repeatedly against a live local Moodle without corrupting DB state.

| Group | Tests | What they prove |
|-------|------:|-----------------|
| 1 — Session lifecycle | 1 | logout destroys sesskey; redirect to login |
| 2 — Form-validation rejection | 3 | mform validators catch empty required fields (no DB write because validation fails first) |
| 3 — Reversible toggle round-trip | 1 | `/user/language.php` round-trips current lang → alternate → restored to original |
| 4 — AJAX/WS contract shape | 3 | `core_user_get_users_by_field`, `core_course_get_enrolled_courses_by_timeline_classification`, `local_airpay_request_list_pending` (Bug #10 regression guard) |
| 5 — Authorization boundary | 2 | sesskey-less POST rejected (CSRF wall); Site Admin can still read admin pages |

Explicitly **out of scope** (separate `tests/mutating.spec.mjs` for
later, gated by a `--mutating` CI flag):

- Course create / delete
- User enrol / unenrol
- Quiz attempt submission
- Cart purchase / refund
- Bulk-import commit

Those workflows mutate state and need DB snapshot+restore around each
run; they're a different testing pattern.

### How to run

```powershell
cd moodle-enhancement\audit\playwright

# Prereq: XAMPP Moodle running on http://localhost:8080/moodle
# Prereq: academy@airpay.co.in password is 'AcademyAudit2026!'
#         (or update SITE_ADMIN.password in both spec files)

# Surfaces only (computed-CSS markers):
npx playwright test tests/surfaces.spec.mjs --project=firefox-desktop

# Workflows only (POST/AJAX/round-trip):
npx playwright test tests/workflows.spec.mjs --project=firefox-desktop

# All spec.mjs in one run:
npx playwright test
```

Firefox is the default project — Chromium has a known
`STATUS_HEAP_CORRUPTION` crash on Node 24 + Windows 10 (per
`playwright.config.mjs` comments).

### Resolution

**#150 Goal B is code-complete at the safe-suite layer.** 21 tests
total (11 surfaces + 10 workflows). Mutating-workflow tier is queued
for a future session that can set up DB snapshot+restore properly.

Awaits a Site Admin to run the suite once on local XAMPP to confirm
all 21 tests pass — that's a 5-minute walk + paste of the playwright
HTML report.

---

## Combined deliverables

| Item | Commit | Lines added |
|------|--------|------------:|
| Surfaces spec (already shipped pre-2026-05-24) | (various) | 155 |
| `tests/workflows.spec.mjs` | `0f0a778c0` | 343 |
| `HARNESS_RUNBOOK.md` update | `0f0a778c0` | +37 |
| This closeout doc | (this commit) | ~145 |

**Total Goal A + B regression-test surface: 21 tests across 2 spec
files, all safe to run repeatedly.**

---

## Refs

- `audit-A.y-findings.md` (2026-05-23) — sections 1.1-1.7
- `audit-A.y-sections-2-8.md` (2026-05-23) — sections 2-8
- `GOAL-A-Y-FUNCTIONAL-AUDIT-MATRIX.md` — original walk matrix
- `tests/surfaces.spec.mjs` — CSS marker regression-guards
- `tests/workflows.spec.mjs` — non-mutating workflow tests (NEW)
- `HARNESS_RUNBOOK.md` — how to run the harness
- Earlier night-run: `NIGHT-RUN-PLAYBOOK.md` at repo root
